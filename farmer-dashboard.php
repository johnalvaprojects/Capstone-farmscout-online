<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/fs_reservation_helpers.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || $_SESSION['user_role'] !== 'farmer') {
    header('Location: login.php?error=' . urlencode('Access denied. Farmer access required.'));
    exit;
}

$page_title = 'Farmer Dashboard - FarmScout Online';
$page_description = 'Manage your products and market presence';

// Get current section from URL or default to 'market'
$current_section = isset($_GET['section']) ? sanitizeInput($_GET['section']) : 'market';
$valid_sections = ['market', 'products', 'reservations', 'messages', 'price_history'];
if (!in_array($current_section, $valid_sections)) {
    $current_section = 'market';
}

$success_message = '';
$error_message = '';

// Handle messages
if (isset($_GET['message'])) {
    $success_message = sanitizeInput($_GET['message']);
}
if (isset($_GET['error'])) {
    $error_message = sanitizeInput($_GET['error']);
}

// Get database connection early
$conn = getDB();
$farmer_id = $_SESSION['user_id'];
$farmer_name = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Farmer'));
$farmer_email = (string)($_SESSION['email'] ?? '');
$farmer_initials = 'F';
if ($farmer_name !== '') {
    $parts = preg_split('/\s+/', $farmer_name) ?: [];
    $letters = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $letters .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($letters) >= 2) break;
    }
    $farmer_initials = $letters !== '' ? $letters : 'F';
}

// Handle reservation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_action'])) {
    require_once 'includes/security.php';
    
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $reservation_id = intval($_POST['reservation_id'] ?? 0);
        $action = sanitizeInput($_POST['reservation_action']);
        $root_reservation_id = $reservation_id;
        
        if ($reservation_id > 0 && $conn) {
            try {
                if (function_exists('fs_reservation_root_id')) {
                    $root_reservation_id = fs_reservation_root_id($conn, $reservation_id);
                }
                // Verify reservation belongs to farmer (root row for multi-product orders)
                $check_stmt = $conn->prepare("SELECT * FROM reservations WHERE id = :id AND farmer_id = :farmer_id");
                $check_stmt->bindParam(':id', $root_reservation_id, PDO::PARAM_INT);
                $check_stmt->bindParam(':farmer_id', $farmer_id);
                $check_stmt->execute();
                $reservation = $check_stmt->fetch(PDO::FETCH_ASSOC);

                $reservationWhere = 'id = :gid AND farmer_id = :fid';
                $reservationBind = [':gid' => $root_reservation_id, ':fid' => $farmer_id];
                if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
                    $reservationWhere = '(id = :gid OR parent_reservation_id = :gid2) AND farmer_id = :fid';
                    $reservationBind = [':gid' => $root_reservation_id, ':gid2' => $root_reservation_id, ':fid' => $farmer_id];
                }
                
                if ($reservation) {
                    if ($action === 'accept') {
                        $accept_note = sanitizeInput($_POST['accept_note'] ?? '');
                        // Check if accept_note column exists
                        $column_check = $conn->query("SHOW COLUMNS FROM reservations LIKE 'accept_note'");
                        // New flow: farmer confirms (legacy: accepted)
                        $hasPaymentMethod = $conn->query("SHOW COLUMNS FROM reservations LIKE 'payment_method'")->rowCount() > 0;
                        $hasGcashReference = $conn->query("SHOW COLUMNS FROM reservations LIKE 'gcash_reference'")->rowCount() > 0;
                        $hasPaidAt = $conn->query("SHOW COLUMNS FROM reservations LIKE 'paid_at'")->rowCount() > 0;

                        // Best logic:
                        // - Reservation stays pending until farmer confirms.
                        // - If buyer already submitted a GCash reference, farmer confirmation can set status to PAID.
                        $pm = $hasPaymentMethod ? strtolower((string)($reservation['payment_method'] ?? '')) : '';
                        $gref = $hasGcashReference ? trim((string)($reservation['gcash_reference'] ?? '')) : '';
                        $statusToSet = ($pm === 'gcash' && $gref !== '') ? 'paid' : 'confirmed';

                        $acceptedCol = $conn->query("SHOW COLUMNS FROM reservations LIKE 'accepted_at'")->rowCount() > 0;
                        $confirmedCol = $conn->query("SHOW COLUMNS FROM reservations LIKE 'confirmed_at'")->rowCount() > 0;
                        $tsCol = $confirmedCol ? 'confirmed_at' : ($acceptedCol ? 'accepted_at' : null);
                        $setTs = $tsCol ? (", {$tsCol} = NOW()") : "";
                        if ($statusToSet === 'paid' && $hasPaidAt) {
                            $setTs .= ", paid_at = COALESCE(paid_at, NOW())";
                        }

                        if ($column_check->rowCount() > 0) {
                            $update_stmt = $conn->prepare("UPDATE reservations SET status = '{$statusToSet}'{$setTs}, accept_note = :accept_note, updated_at = NOW() WHERE " . $reservationWhere);
                        } else {
                            $update_stmt = $conn->prepare("UPDATE reservations SET status = '{$statusToSet}'{$setTs}, updated_at = NOW() WHERE " . $reservationWhere);
                        }
                        foreach ($reservationBind as $k => $v) {
                            $update_stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                        }
                        if ($column_check->rowCount() > 0) {
                            $update_stmt->bindValue(':accept_note', $accept_note);
                        }
                        if ($update_stmt->execute()) {
                            // Send email to user
                            $user_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = :user_id");
                            $user_stmt->bindParam(':user_id', $reservation['user_id']);
                            $user_stmt->execute();
                            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $product_stmt = $conn->prepare("SELECT mp.*, mp.product_name, m.market_name FROM market_products mp LEFT JOIN markets m ON mp.market_id = m.id WHERE mp.id = :product_id");
                            $product_stmt->bindParam(':product_id', $reservation['product_id']);
                            $product_stmt->execute();
                            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

                            $lineDesc = $product ? (string)$product['product_name'] : '';
                            if ($product && fs_reservations_parent_column_exists($conn)) {
                                $ls = $conn->prepare(
                                    "SELECT GROUP_CONCAT(CONCAT(r.quantity, ' ', r.unit, ' ', mp2.product_name) ORDER BY r.id SEPARATOR '; ') AS d
                                     FROM reservations r
                                     INNER JOIN market_products mp2 ON r.product_id = mp2.id
                                     WHERE r.id = :rid OR r.parent_reservation_id = :rid2"
                                );
                                $ls->execute([':rid' => $root_reservation_id, ':rid2' => $root_reservation_id]);
                                $agg = (string)($ls->fetchColumn() ?: '');
                                if ($agg !== '') {
                                    $lineDesc = $agg;
                                }
                            }
                            
                            if (function_exists('sendReservationEmail') && $user && $product) {
                                try {
                                    sendReservationEmail([
                                        'type' => 'user_accepted',
                                        'user_email' => $user['email'],
                                        'user_name' => $user['full_name'],
                                        'product_name' => $lineDesc,
                                        'quantity' => $reservation['quantity'],
                                        'unit' => $reservation['unit'],
                                        'market_name' => $product['market_name'],
                                        'accept_note' => $accept_note
                                    ]);
                                } catch (Exception $emailError) {
                                    error_log("Failed to send accept email: " . $emailError->getMessage());
                                    // Don't fail the accept action if email fails
                                }
                            }
                            
                            // Create notification for user
                            if (function_exists('createNotification')) {
                                try {
                                    $notification_title = 'Reservation Confirmed';
                                    $notification_message = 'Your reservation (' . $lineDesc . ') has been confirmed by the farmer.';
                                    createNotification(
                                        $reservation['user_id'],
                                        'reservation_confirmed',
                                        $notification_title,
                                        $notification_message,
                                        'user-account.php?section=reservations'
                                    );
                                } catch (Exception $notifError) {
                                    error_log("Failed to create notification for user: " . $notifError->getMessage());
                                }
                            }
                            
                            // Create system message in chat
                            if (function_exists('createSystemMessage')) {
                                try {
                                    $system_message = "Reservation confirmed" . (!empty($accept_note) ? " by farmer" : "");
                                    createSystemMessage($root_reservation_id, $system_message);
                                } catch (Exception $msgError) {
                                    error_log("Failed to create system message: " . $msgError->getMessage());
                                }
                            }
                            
                            $success_message = ($statusToSet === 'paid')
                                ? 'Reservation confirmed and marked as paid.'
                                : 'Reservation confirmed successfully.';
                            header('Location: farmer-dashboard.php?message=' . urlencode($success_message));
                            exit;
                        }
                    } elseif ($action === 'decline') {
                        $reason = sanitizeInput($_POST['reason'] ?? '');
                        // Check if decline_reason column exists, if not, use a simpler query
                        $column_check = $conn->query("SHOW COLUMNS FROM reservations LIKE 'decline_reason'");
                        if ($column_check->rowCount() > 0) {
                            // New flow: cancelled (legacy: declined)
                            $statusToSet = 'cancelled';
                            $declinedCol = $conn->query("SHOW COLUMNS FROM reservations LIKE 'declined_at'")->rowCount() > 0;
                            $setTs = $declinedCol ? ", declined_at = NOW()" : "";
                            $update_stmt = $conn->prepare("UPDATE reservations SET status = '{$statusToSet}'{$setTs}, decline_reason = :reason, updated_at = NOW() WHERE " . $reservationWhere);
                        } else {
                            $statusToSet = 'cancelled';
                            $declinedCol = $conn->query("SHOW COLUMNS FROM reservations LIKE 'declined_at'")->rowCount() > 0;
                            $setTs = $declinedCol ? ", declined_at = NOW()" : "";
                            $update_stmt = $conn->prepare("UPDATE reservations SET status = '{$statusToSet}'{$setTs}, updated_at = NOW() WHERE " . $reservationWhere);
                        }
                        foreach ($reservationBind as $k => $v) {
                            $update_stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                        }
                        if ($column_check->rowCount() > 0) {
                            $update_stmt->bindValue(':reason', $reason);
                        }
                        if ($update_stmt->execute()) {
                            // Send email to user
                            $user_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = :user_id");
                            $user_stmt->bindParam(':user_id', $reservation['user_id']);
                            $user_stmt->execute();
                            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $product_stmt = $conn->prepare("SELECT mp.*, mp.product_name, m.market_name FROM market_products mp LEFT JOIN markets m ON mp.market_id = m.id WHERE mp.id = :product_id");
                            $product_stmt->bindParam(':product_id', $reservation['product_id']);
                            $product_stmt->execute();
                            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

                            $lineDescDecl = $product ? (string)$product['product_name'] : '';
                            if ($product && fs_reservations_parent_column_exists($conn)) {
                                $lsd = $conn->prepare(
                                    "SELECT GROUP_CONCAT(CONCAT(r.quantity, ' ', r.unit, ' ', mp2.product_name) ORDER BY r.id SEPARATOR '; ') FROM reservations r INNER JOIN market_products mp2 ON r.product_id = mp2.id WHERE r.id = :rid OR r.parent_reservation_id = :rid2"
                                );
                                $lsd->execute([':rid' => $root_reservation_id, ':rid2' => $root_reservation_id]);
                                $aggd = (string)($lsd->fetchColumn() ?: '');
                                if ($aggd !== '') {
                                    $lineDescDecl = $aggd;
                                }
                            }
                            
                            if (function_exists('sendReservationEmail') && $user && $product) {
                                try {
                                    sendReservationEmail([
                                        'type' => 'user_declined',
                                        'user_email' => $user['email'],
                                        'user_name' => $user['full_name'],
                                        'product_name' => $lineDescDecl,
                                        'quantity' => $reservation['quantity'],
                                        'unit' => $reservation['unit'],
                                        'reason' => $reason
                                    ]);
                                } catch (Exception $emailError) {
                                    error_log("Failed to send decline email: " . $emailError->getMessage());
                                    // Don't fail the decline action if email fails
                                }
                            }
                            
                            // Create notification for user
                            if (function_exists('createNotification')) {
                                try {
                                    $notification_title = 'Reservation Cancelled';
                                    $decline_message = !empty($reason) ? 'Reason: ' . $reason : 'No reason provided.';
                                    $notification_message = 'Your reservation (' . $lineDescDecl . ') has been cancelled by the farmer. ' . $decline_message;
                                    createNotification(
                                        $reservation['user_id'],
                                        'reservation_cancelled',
                                        $notification_title,
                                        $notification_message,
                                        'user-account.php?section=reservations'
                                    );
                                } catch (Exception $notifError) {
                                    error_log("Failed to create notification for user: " . $notifError->getMessage());
                                }
                            }
                            
                            // Create system message in chat
                            if (function_exists('createSystemMessage')) {
                                try {
                                    $system_message = "Reservation cancelled by farmer" . (!empty($reason) ? ": " . $reason : "");
                                    createSystemMessage($root_reservation_id, $system_message);
                                } catch (Exception $msgError) {
                                    error_log("Failed to create system message: " . $msgError->getMessage());
                                }
                            }
                            
                            $success_message = 'Reservation cancelled.';
                            header('Location: farmer-dashboard.php?message=' . urlencode($success_message));
                            exit;
                        }
                    } elseif ($action === 'complete') {
                        $update_stmt = $conn->prepare("UPDATE reservations SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE " . $reservationWhere);
                        foreach ($reservationBind as $k => $v) {
                            $update_stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                        }
                        if ($update_stmt->execute()) {
                            // Get product details for notification
                            $product_query = "SELECT mp.product_name FROM market_products mp WHERE mp.id = :product_id";
                            $product_stmt = $conn->prepare($product_query);
                            $product_stmt->bindParam(':product_id', $reservation['product_id']);
                            $product_stmt->execute();
                            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

                            $lineDescCmp = $product ? (string)$product['product_name'] : '';
                            if ($product && fs_reservations_parent_column_exists($conn)) {
                                $lsc = $conn->prepare(
                                    "SELECT GROUP_CONCAT(CONCAT(r.quantity, ' ', r.unit, ' ', mp2.product_name) ORDER BY r.id SEPARATOR '; ') FROM reservations r INNER JOIN market_products mp2 ON r.product_id = mp2.id WHERE r.id = :rid OR r.parent_reservation_id = :rid2"
                                );
                                $lsc->execute([':rid' => $root_reservation_id, ':rid2' => $root_reservation_id]);
                                $aggc = (string)($lsc->fetchColumn() ?: '');
                                if ($aggc !== '') {
                                    $lineDescCmp = $aggc;
                                }
                            }
                            
                            // Create notification for user
                            if (function_exists('createNotification') && $product) {
                                try {
                                    $notification_title = 'Reservation Completed';
                                    $notification_message = 'Your reservation (' . $lineDescCmp . ') has been marked as completed by the farmer.';
                                    createNotification(
                                        $reservation['user_id'],
                                        'reservation_completed',
                                        $notification_title,
                                        $notification_message,
                                        'user-account.php?section=reservations'
                                    );
                                } catch (Exception $notifError) {
                                    error_log("Failed to create notification for user: " . $notifError->getMessage());
                                }
                            }
                            
                            // Create system message in chat
                            if (function_exists('createSystemMessage')) {
                                try {
                                    $system_message = "Reservation marked as completed";
                                    createSystemMessage($root_reservation_id, $system_message);
                                } catch (Exception $msgError) {
                                    error_log("Failed to create system message: " . $msgError->getMessage());
                                }
                            }
                            
                            $success_message = 'Reservation marked as completed.';
                            header('Location: farmer-dashboard.php?section=reservations&message=' . urlencode($success_message));
                            exit;
                        }
                    }
                } else {
                    $error_message = 'Reservation not found.';
                }
            } catch (PDOException $e) {
                error_log("Error processing reservation action: " . $e->getMessage());
                $error_message = 'An error occurred. Please try again.';
            }
        }
    } else {
        $error_message = 'Invalid security token.';
    }
}

// Get farmer's reservations
$reservations = [];
$status_filter = isset($_GET['reservation_status']) ? sanitizeInput($_GET['reservation_status']) : '';
$status_condition = '';
$hasPaymentMethod = false;
$hasGcashReference = false;
$hasConfirmedAt = false;
$hasPaidAt = false;
if ($conn) {
    try {
        $hasPaymentMethod = $conn->query("SHOW COLUMNS FROM reservations LIKE 'payment_method'")->rowCount() > 0;
        $hasGcashReference = $conn->query("SHOW COLUMNS FROM reservations LIKE 'gcash_reference'")->rowCount() > 0;
        $hasConfirmedAt = $conn->query("SHOW COLUMNS FROM reservations LIKE 'confirmed_at'")->rowCount() > 0;
        $hasPaidAt = $conn->query("SHOW COLUMNS FROM reservations LIKE 'paid_at'")->rowCount() > 0;
    } catch (Exception $e) {
        // Backward compatible: columns may not exist.
    }
}

// Normalize legacy filter values to the new flow (keep old links working).
$status_filter_norm = $status_filter;
if ($status_filter_norm === 'accepted') $status_filter_norm = 'confirmed';
if ($status_filter_norm === 'declined') $status_filter_norm = 'cancelled';

if ($status_filter_norm && in_array($status_filter_norm, ['pending', 'confirmed', 'paid', 'completed', 'cancelled'], true)) {
    // Treat legacy accepted/declined as confirmed/cancelled when filtering.
    if ($status_filter_norm === 'confirmed') {
        $status_condition = "AND (r.status = 'confirmed' OR r.status = 'accepted')";
    } elseif ($status_filter_norm === 'cancelled') {
        $status_condition = "AND (r.status = 'cancelled' OR r.status = 'declined')";
    } else {
        $status_condition = "AND r.status = :status";
    }
} else {
    // When "ALL" is selected, exclude cancelled and completed (show actionable)
    // Include pending/confirmed/paid + legacy accepted.
    $status_condition = "AND r.status NOT IN ('declined', 'cancelled', 'completed')";
}

if ($conn) {
    try {
        $fd_root_only = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
            ? ' AND r.parent_reservation_id IS NULL '
            : '';
        $reservations_query = "SELECT r.*, 
                              mp.product_name, mp.product_image,
                              u.full_name as user_name, u.email as user_email, u.phone_number as user_phone,
                              m.market_name
                              FROM reservations r
                              LEFT JOIN market_products mp ON r.product_id = mp.id
                              LEFT JOIN users u ON r.user_id = u.id
                              LEFT JOIN markets m ON r.market_id = m.id
                              WHERE r.farmer_id = :farmer_id
                              $fd_root_only
                              $status_condition
                              ORDER BY r.created_at DESC";
        
        $reservations_stmt = $conn->prepare($reservations_query);
        $reservations_stmt->bindParam(':farmer_id', $farmer_id);
        if ($status_filter_norm && in_array($status_filter_norm, ['pending', 'paid', 'completed'], true)) {
            $reservations_stmt->bindParam(':status', $status_filter_norm);
        } elseif ($status_filter_norm === 'confirmed' || $status_filter_norm === 'cancelled') {
            // No bind needed; handled by status_condition OR clause.
        } elseif ($status_filter_norm && in_array($status_filter_norm, ['confirmed', 'cancelled'], true)) {
            // No-op
        } elseif ($status_filter_norm) {
            // fallback: if unknown, don't bind
        }
        $reservations_stmt->execute();
        $reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($reservations) && function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
            $fd_line_stmt = $conn->prepare(
                "SELECT r.quantity, r.unit, mp.product_name FROM reservations r
                 INNER JOIN market_products mp ON r.product_id = mp.id
                 WHERE r.id = :rid OR r.parent_reservation_id = :rid2
                 ORDER BY r.id ASC"
            );
            foreach ($reservations as $fi => $fr) {
                $rid = (int)($fr['id'] ?? 0);
                $fd_line_stmt->execute([':rid' => $rid, ':rid2' => $rid]);
                $reservations[$fi]['line_items'] = $fd_line_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching reservations: " . $e->getMessage());
        $reservations = [];
    }
} else {
    $reservations = [];
}

// Dashboard stat: reservations waiting for accept/decline (actionable, not always "1")
$farmer_pending_reservations_count = 0;
if ($conn) {
    try {
        $fd_pend_root = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
            ? " AND parent_reservation_id IS NULL "
            : '';
        $pending_cnt_stmt = $conn->prepare(
            "SELECT COUNT(*) FROM reservations WHERE farmer_id = ? AND status = 'pending' $fd_pend_root"
        );
        $pending_cnt_stmt->execute([$farmer_id]);
        $farmer_pending_reservations_count = (int) $pending_cnt_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting pending reservations: " . $e->getMessage());
    }
}

// Get farmer's information
$farmer_id = $_SESSION['user_id'];
$conn = getDB();

// Get farmer's markets
$markets_query = "SELECT m.*, mf.approval_status, mf.stall_number, mf.monthly_fee 
                  FROM markets m 
                  JOIN market_farmers mf ON m.id = mf.market_id 
                  WHERE mf.farmer_id = :farmer_id";
$markets_stmt = $conn->prepare($markets_query);
$markets_stmt->bindParam(':farmer_id', $farmer_id);
$markets_stmt->execute();
$farmer_markets = $markets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get markets owned by this farmer (vendor functionality) - moved up to use in products query
$owned_markets_query = "SELECT * FROM markets WHERE vendor_id = :farmer_id";
$owned_markets_stmt = $conn->prepare($owned_markets_query);
$owned_markets_stmt->bindParam(':farmer_id', $farmer_id);
$owned_markets_stmt->execute();
$owned_markets = $owned_markets_stmt->fetchAll(PDO::FETCH_ASSOC);
$owned_market_ids = array_column($owned_markets, 'id');

// Also check session for managed market IDs (set during login)
$managed_market_ids = $_SESSION['managed_market_ids'] ?? [];
$active_market_id = $_SESSION['active_market_id'] ?? null;

// Combine owned markets with managed markets from session
$all_market_ids = array_unique(array_merge($owned_market_ids, $managed_market_ids));
if ($active_market_id && !in_array($active_market_id, $all_market_ids)) {
    $all_market_ids[] = $active_market_id;
}

// Check if deleted_at column exists
$hasDeletedAt = false;
try {
    $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
    $hasDeletedAt = $col_check->rowCount() > 0;
} catch (Exception $e) {
    // Column doesn't exist, that's okay
}
$deletedAtCheck = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";

// Get farmer's products - include products they added AND products from markets they own/manage
// Use all positional parameters to avoid mixing named and positional
if (!empty($all_market_ids)) {
    // User owns/manages markets - show products they added OR products from their markets
    $placeholders = str_repeat('?,', count($all_market_ids) - 1) . '?';
    $products_query = "SELECT mp.*, m.market_name 
                       FROM market_products mp 
                       JOIN markets m ON mp.market_id = m.id 
                       WHERE (mp.farmer_id = ? OR mp.market_id IN ($placeholders))
                       $deletedAtCheck
                       ORDER BY mp.market_id, mp.product_name";
    $products_stmt = $conn->prepare($products_query);
    $products_stmt->execute(array_merge([$farmer_id], $all_market_ids));
} else {
    // User doesn't own/manage markets - only show products they added
$products_query = "SELECT mp.*, m.market_name 
                   FROM market_products mp 
                   JOIN markets m ON mp.market_id = m.id 
                   WHERE mp.farmer_id = ?
                   $deletedAtCheck
                   ORDER BY mp.market_id, mp.product_name";
$products_stmt = $conn->prepare($products_query);
    $products_stmt->execute([$farmer_id]);
}

$farmer_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
$farmer_products = attachUnitOptionsToProducts($farmer_products, 'id');

// Get categories for icons and info
$categories = getCategories();
$category_map = [];
foreach ($categories as $cat) {
    $category_map[strtolower(trim($cat['name']))] = $cat;
    $category_map[strtolower(trim($cat['filipino_name']))] = $cat;
}

// Suggestions for product names to prevent mismatches with admin reference bands.
$product_name_suggestions = [];
try {
    $stmt = $conn->query("SELECT DISTINCT product_name FROM market_products WHERE product_name IS NOT NULL AND product_name <> '' ORDER BY product_name ASC LIMIT 800");
    $product_name_suggestions = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
} catch (Throwable $e) {
    $product_name_suggestions = [];
}

// Fallback: always include this farmer's current product names (ensures dropdown is never empty).
foreach (($farmer_products ?? []) as $fp) {
    if (!empty($fp['product_name'])) {
        $product_name_suggestions[] = (string)$fp['product_name'];
    }
}
$product_name_suggestions = array_values(array_unique(array_filter(array_map('trim', $product_name_suggestions))));
sort($product_name_suggestions, SORT_NATURAL | SORT_FLAG_CASE);

// Final standardized product list for dropdowns (server-side).
$fd_product_names = $product_name_suggestions;

// Group products by category
$products_by_category = [];
foreach ($farmer_products as $product) {
    $category = !empty($product['category']) ? trim($product['category']) : 'Uncategorized';
    if (!isset($products_by_category[$category])) {
        $products_by_category[$category] = [];
    }
    $products_by_category[$category][] = $product;
}

// Note: Removed "Available Markets" query since each farmer is assigned to one market

// Get market IDs for filtering (owned_markets already fetched above)
$market_ids = array_column($owned_markets, 'id');
$market_ids_placeholder = $market_ids ? implode(',', array_fill(0, count($market_ids), '?')) : '0';

// Removed: Pending Applications and Approved Farmers sections
// These are not needed since farmers are directly assigned to markets by admins
// No application/approval process is required

// Get market statistics (only for owned markets)
$market_stats = ['total_farmers' => 0, 'total_products' => 0, 'average_rating' => 0];
if (!empty($market_ids)) {
    $stats_query = "SELECT 
                        COUNT(DISTINCT mf.farmer_id) as total_farmers,
                        COUNT(DISTINCT mp.id) as total_products,
                        AVG(mr.rating) as average_rating
                    FROM markets m
                    LEFT JOIN market_farmers mf ON m.id = mf.market_id AND mf.approval_status = 'approved'
                    LEFT JOIN market_products mp ON m.id = mp.market_id
                    LEFT JOIN market_reviews mr ON m.id = mr.market_id
                    WHERE m.vendor_id = :farmer_id";
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bindParam(':farmer_id', $farmer_id);
    $stats_stmt->execute();
    $market_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: $market_stats;
}

// Set current page for navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="fd2-page">
<?php
include 'includes/header-market-finder.php';
?>

<?php
/* ── Extra data computed for the dashboard UI ── */

// Assigned market (for Market Settings section)
$_fd_mkt = null;
if (!empty($owned_markets))         $_fd_mkt = $owned_markets[0];
elseif (!empty($active_market_id) && $conn) {
    try { $_s = $conn->prepare("SELECT * FROM markets WHERE id = ?"); $_s->execute([$active_market_id]); $_fd_mkt = $_s->fetch(PDO::FETCH_ASSOC) ?: null; } catch(Exception $e){}
}
if (!$_fd_mkt && !empty($farmer_markets)) $_fd_mkt = $farmer_markets[0];

// Conversations / Messages
$_fd_convos = []; $_fd_unread = 0;
if ($conn) {
    try {
        $_fd_msg_root = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
            ? ' AND r.parent_reservation_id IS NULL '
            : '';
        $_fd_msg_refs = (function_exists('fs_reservations_public_ref_columns_exist') && fs_reservations_public_ref_columns_exist($conn))
            ? 'r.public_ref, r.chat_public_ref, '
            : '';
        $_cq = $conn->prepare("SELECT DISTINCT r.id, r.status, r.quantity, r.unit,
            mp.product_name, $_fd_msg_refs u.full_name AS user_name, u.username AS user_username,
            (SELECT COUNT(*) FROM reservation_messages rm  WHERE rm.reservation_id=r.id AND rm.sender_id!=:fid AND rm.read_at IS NULL) AS unread_count,
            (SELECT rm2.message_body FROM reservation_messages rm2 WHERE rm2.reservation_id=r.id ORDER BY rm2.created_at DESC LIMIT 1) AS last_message,
            (SELECT rm3.created_at  FROM reservation_messages rm3 WHERE rm3.reservation_id=r.id ORDER BY rm3.created_at DESC LIMIT 1) AS last_message_time
            FROM reservations r
            INNER JOIN reservation_messages rm ON r.id=rm.reservation_id
            INNER JOIN market_products mp ON r.product_id=mp.id
            INNER JOIN users u ON r.user_id=u.id
            WHERE r.farmer_id=:fid $_fd_msg_root ORDER BY last_message_time DESC");
        $_cq->bindParam(':fid',$farmer_id,PDO::PARAM_INT); $_cq->execute();
        $_fd_convos = $_cq->fetchAll(PDO::FETCH_ASSOC);
        foreach($_fd_convos as $_c) $_fd_unread += (int)($_c['unread_count']??0);
    } catch(Exception $e){ error_log("FD convos: ".$e->getMessage()); }
}

// Completed reservations count
$_fd_done = 0;
if ($conn) { try { $_fdcr=(function_exists('fs_reservations_parent_column_exists')&&fs_reservations_parent_column_exists($conn))?' AND parent_reservation_id IS NULL ':''; $_cs=$conn->prepare("SELECT COUNT(*) FROM reservations WHERE farmer_id=? AND status='completed'$_fdcr"); $_cs->execute([$farmer_id]); $_fd_done=(int)$_cs->fetchColumn(); } catch(Exception $e){} }

// Markets available for product form
$_fd_all_mkts   = array_values(array_unique(array_merge($farmer_markets??[],$owned_markets??[]),SORT_REGULAR));
$_fd_one_mkt    = count($_fd_all_mkts)===1;
$_fd_def_mid    = $_fd_one_mkt?(int)($_fd_all_mkts[0]['id']??0):(int)($active_market_id??0);
$_fd_lock_mid   = (int)($_fd_mkt['id'] ?? $_fd_def_mid ?? 0);
$_fd_lock_mname = (string)($_fd_mkt['market_name'] ?? ($_fd_all_mkts[0]['market_name'] ?? ''));

// Map URL section → JS section name
$_fd_init_map   = ['market'=>'market','products'=>'products','reservations'=>'reservations','messages'=>'messages','price_history'=>'price'];
$_fd_init       = $_fd_init_map[$current_section]??'market';

// Products as JSON for modal population
$_fd_prods_js   = json_encode(array_map(function($p){
    $uopts = array_map(function($u){ return ['unit_label'=>(string)($u['unit_label']??''),'price'=>(float)($u['price']??0),'is_default'=>!empty($u['is_default']),'display_label'=>(string)($u['display_label']??$u['unit_label']??'')]; }, $p['unit_options']??[]);
    return ['id'=>(int)($p['id']??0),'product_name'=>(string)($p['product_name']??''),'category'=>(string)($p['category']??''),'description'=>(string)($p['product_description']??''),'image'=>(string)($p['product_image']??''),'is_available'=>(int)($p['is_available']??1),'market_id'=>(int)($p['market_id']??0),'unit_options'=>$uopts];
},$farmer_products),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

$_fd_csrf = function_exists('getCSRFToken') ? getCSRFToken() : '';
?>

<style>
/* ================================================================
   FARMSCOUT — FARMER DASHBOARD
   Dark forest-green | Syne + DM Sans | Chart.js
   ================================================================ */
*,*::before,*::after{box-sizing:border-box}

body.fd2-page{
    margin:0;padding:0;
    font-family:'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    background:#f6f2e8;
    color:#1f2a22;
    min-height:100vh;
    -webkit-font-smoothing:antialiased;
}
body.fd2-page .header,
body.fd2-page .admin-header-spacer{display:none!important}

:root{
    /* Light / earthy palette */
    --bg:#f6f2e8;
    --main:#f6f2e8;
    --main2:#f2ecdf;
    --surf:#ffffff;
    --surf2:#fbf7ee;
    --surf3:#f3edde;
    --surf4:#eef7f0;
    --card:#ffffff;
    --bdr:rgba(31,42,34,.10);

    --grn:#1f7a3a;
    --grn2:#2f8f4e;
    --grn-dim:rgba(31,122,58,.10);

    --txt:#1f2a22;
    --mut:rgba(31,42,34,.55);

    --red:#b91c1c;
    --yel:#b45309;
    --blu:#1d4ed8;
    --pur:#6d28d9;
    --rad:14px;--tr:.2s cubic-bezier(.4,0,.2,1);
    --sw:220px;--shd:0 16px 46px rgba(17,24,39,.14);
}

::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--surf)}
::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:3px}

/* ── LAYOUT ── */
.fd{display:flex;min-height:100vh}

/* ── SIDEBAR ── */
.fd-s{
    width:var(--sw);min-width:var(--sw);
    background:linear-gradient(180deg, #163323 0%, #0f251a 100%);
    border-right:1px solid rgba(0,0,0,.06);
    display:flex;flex-direction:column;
    position:fixed;top:0;left:0;bottom:0;z-index:200;
    overflow-y:auto;overflow-x:hidden;
    animation:sidebarIn .4s cubic-bezier(.4,0,.2,1) both;
}
.fd-s::-webkit-scrollbar{width:3px}
@keyframes sidebarIn{from{transform:translateX(calc(-1*var(--sw)));opacity:0}to{transform:none;opacity:1}}

.fd-s__logo{padding:22px 18px 18px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:10px;flex-shrink:0}
.fd-s__mark{width:34px;height:34px;border-radius:8px;background:#c7f2d6;display:grid;place-items:center;font-family:'InterDisplay','Inter',system-ui,sans-serif;font-weight:900;font-size:13px;color:#0f251a;flex-shrink:0;letter-spacing:-.04em}
.fd-s__brand{font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:13px;font-weight:800;color:var(--txt);line-height:1.2}
.fd-s__brand small{display:block;font-size:10px;color:rgba(255,255,255,.65);font-weight:400}

.fd-s__user{padding:14px 18px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:10px;flex-shrink:0}
.fd-s__avatar{width:34px;height:34px;border-radius:50%;background:rgba(199,242,214,.16);border:1.5px solid rgba(199,242,214,.25);display:grid;place-items:center;font-size:12px;font-weight:800;color:#c7f2d6;flex-shrink:0}
.fd-s__uname{font-size:13px;font-weight:700;color:#eef7f0;margin:0 0 1px}
.fd-s__urole{font-size:10px;color:rgba(255,255,255,.65);text-transform:uppercase;letter-spacing:.06em}

.fd-s__nav{padding:10px 8px;flex:1}
.fd-s__ns{font-size:9px;font-weight:800;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.12em;padding:10px 10px 4px}
.fd-s__link{
    display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:10px;
    text-decoration:none;color:rgba(255,255,255,.78);font-size:13px;font-weight:600;margin-bottom:2px;
    transition:all var(--tr);position:relative;cursor:pointer;border:none;background:none;
    width:100%;text-align:left;font-family:'Inter',system-ui,sans-serif;
}
.fd-s__link svg{width:15px;height:15px;flex-shrink:0;opacity:.85;transition:opacity var(--tr)}
.fd-s__link:hover{background:rgba(199,242,214,.12);color:#eef7f0}
.fd-s__link:hover svg{opacity:1}
.fd-s__link.act{
    background:rgba(199,242,214,.18);
    color:#c7f2d6;
    font-weight:800;
    box-shadow:inset 0 0 0 1px rgba(199,242,214,.25);
}
.fd-s__link.act::before{content:'';position:absolute;left:0;top:8px;bottom:8px;width:3px;background:#c7f2d6;border-radius:0 3px 3px 0;animation:actPulse 2.5s ease infinite}
@keyframes actPulse{0%,100%{opacity:1}50%{opacity:.4}}
.fd-s__link.act svg{opacity:1}
.fd-s__badge{margin-left:auto;background:#c7f2d6;color:#0f251a;font-size:10px;font-weight:900;border-radius:999px;padding:1px 7px;min-width:18px;text-align:center}

.fd-s__mkt{
    margin:10px 10px 8px;
    padding:13px 14px;
    border-radius:12px;
    background:linear-gradient(180deg, rgba(199,242,214,.18) 0%, rgba(199,242,214,.10) 100%);
    border:1px solid rgba(199,242,214,.22);
    box-shadow:0 14px 34px rgba(0,0,0,.22);
}
.fd-s__mkt-lbl{font-size:9px;font-weight:900;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.fd-s__mkt-name{font-size:12px;font-weight:800;color:#eef7f0;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fd-s__dot{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700}
.fd-s__dot::before{content:'';width:6px;height:6px;border-radius:50%}
.fd-s__dot--o{color:var(--grn)}.fd-s__dot--o::before{background:var(--grn);box-shadow:0 0 6px var(--grn);animation:dotPls 2s ease infinite}
.fd-s__dot--c{color:var(--red)}.fd-s__dot--c::before{background:var(--red)}
@keyframes dotPls{0%,100%{box-shadow:0 0 4px var(--grn)}50%{box-shadow:0 0 10px var(--grn)}}

.fd-s__foot{padding:10px 12px;border-top:1px solid var(--bdr)}
.fd-s__foot a{
    display:flex;align-items:center;gap:8px;
    font-size:12px;
    color:rgba(255,255,255,.78);
    text-decoration:none;
    padding:8px 10px;
    border-radius:10px;
    transition:all var(--tr)
}
.fd-s__foot a svg{width:13px;height:13px}
.fd-s__foot a:hover{color:#eef7f0;background:rgba(199,242,214,.12)}

/* ── MAIN ── */
.fd-m{
    margin-left:var(--sw);
    flex:1;min-height:100vh;
    background:radial-gradient(900px 520px at 22% 8%, rgba(31,122,58,.10), transparent 72%),
               linear-gradient(180deg, var(--main) 0%, var(--main2) 100%);
}
.fd-m__top{
    display:flex;align-items:center;justify-content:space-between;
    padding:18px 30px;border-bottom:1px solid var(--bdr);
    position:sticky;top:0;z-index:100;gap:12px;
    background:rgba(246,242,232,.88);
    backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
}
.fd-m__title{font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:20px;font-weight:900;color:var(--txt);margin:0;letter-spacing:-.025em}
.fd-m__top-r{display:flex;gap:8px;align-items:center}
.fd-m__body{padding:34px 34px 70px}

/* ── SECTIONS ── */
.fd-sec{display:none}.fd-sec.act{display:block}

/* ── STAT CARDS ── */
.fd-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:18px;margin-bottom:34px}
.fd-stat{
    background:var(--card);
    border:1px solid rgba(31,42,34,.12);
    border-radius:var(--rad);
    padding:24px 24px;
    min-height:140px;
    opacity:0;transform:translateY(18px);
    transition:opacity .4s ease,transform .4s ease,border-color .2s,box-shadow .2s,background .2s;
    position:relative;
    overflow:hidden;
}
.fd-stat::before{
    content:'';
    position:absolute;left:0;right:0;top:0;height:3px;
    background:rgba(255,255,255,.06);
}
.fd-stat--products::before{background:linear-gradient(90deg, rgba(74,222,128,.95), rgba(34,197,94,.55))}
.fd-stat--pending::before{background:linear-gradient(90deg, rgba(251,191,36,.95), rgba(245,158,11,.55))}
.fd-stat--completed::before{background:linear-gradient(90deg, rgba(96,165,250,.95), rgba(59,130,246,.55))}
.fd-stat--messages::before{background:linear-gradient(90deg, rgba(167,139,250,.95), rgba(139,92,246,.55))}
.fd-stat.in{opacity:1;transform:none}
.fd-stat:hover{border-color:rgba(74,222,128,.26);box-shadow:var(--shd);background:rgba(17,26,20,.98)}
.fd-stat:hover{border-color:rgba(31,122,58,.22);box-shadow:var(--shd);background:#ffffff}
.fd-stat__icon{
    width:52px;height:52px;border-radius:999px;
    display:grid;place-items:center;margin-bottom:16px;
    border:1px solid rgba(31,42,34,.10);
    box-shadow:0 10px 18px rgba(17,24,39,.10);
}
.fd-stat__icon svg{width:22px;height:22px}
.fd-stat__icon--products{background:rgba(74,222,128,.14);color:var(--grn)}
.fd-stat__icon--pending{background:rgba(180,83,9,.12);color:var(--yel)}
.fd-stat__icon--completed{background:rgba(29,78,216,.10);color:var(--blu)}
.fd-stat__icon--messages{background:rgba(109,40,217,.10);color:var(--pur)}
.fd-stat__val{font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:44px;font-weight:900;color:var(--txt);line-height:1;margin-bottom:6px;letter-spacing:-.035em}
.fd-stat__lbl{font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.07em}

/* ── TABLE ── */
.fd-tw{overflow-x:auto;border-radius:var(--rad);border:1px solid var(--bdr)}
.fd-t{width:100%;border-collapse:collapse;font-size:13px}
.fd-t thead tr{background:var(--surf2)}
.fd-t th{padding:11px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--mut);white-space:nowrap}
.fd-t td{
    padding:18px 16px;
    border-top:1px solid rgba(74,222,128,.10);
    border-bottom:1px solid rgba(0,0,0,.18);
    color:var(--txt);
    vertical-align:middle
}
.fd-t tbody tr:nth-child(even){background:rgba(251,247,238,.92)}
.fd-t tbody tr:nth-child(odd){background:#ffffff}
.fd-t tbody tr:hover{background:rgba(31,122,58,.06)}
.fd-t .mt{text-align:center;padding:40px;color:var(--mut);font-size:14px}

/* ── BADGES ── */
.fdb{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
.fdb--pending{background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.2);animation:bPls 2.5s ease infinite}
.fdb--confirmed,.fdb--accepted{background:rgba(74,222,128,.12);color:#4ade80;border:1px solid rgba(74,222,128,.2)}
.fdb--paid{background:rgba(96,165,250,.12);color:#60a5fa;border:1px solid rgba(96,165,250,.2)}
.fdb--completed{background:rgba(167,139,250,.12);color:#a78bfa;border:1px solid rgba(167,139,250,.2)}
.fdb--cancelled,.fdb--declined{background:rgba(248,113,113,.12);color:#f87171;border:1px solid rgba(248,113,113,.2)}
.fdb--available{background:rgba(74,222,128,.12);color:#4ade80;border:1px solid rgba(74,222,128,.2)}
.fdb--unavailable{background:rgba(248,113,113,.12);color:#f87171;border:1px solid rgba(248,113,113,.2)}
@keyframes bPls{0%,100%{opacity:1}50%{opacity:.55}}

/* ── BUTTONS ── */
.fb{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-size:12px;font-weight:600;font-family:'Inter',system-ui,sans-serif;border:none;cursor:pointer;text-decoration:none;transition:all var(--tr);white-space:nowrap}
.fb svg{width:13px;height:13px;flex-shrink:0}
.fb--p{background:#166534;color:#ffffff}.fb--p:hover{background:#14532d;transform:translateY(-1px);box-shadow:0 6px 20px rgba(22,101,52,.28)}
.fb--g{background:var(--surf2);color:var(--txt);border:1px solid var(--bdr)}.fb--g:hover{background:var(--surf3);border-color:rgba(74,222,128,.2)}
.fb--d{background:rgba(248,113,113,.1);color:var(--red);border:1px solid rgba(248,113,113,.2)}.fb--d:hover{background:rgba(248,113,113,.2)}
.fb--sm{padding:6px 12px;font-size:11px}

/* ── INPUTS ── */
.fg{margin-bottom:18px}
.fg label{display:block;font-size:10px;font-weight:700;color:var(--mut);text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px}
.fi{width:100%;padding:12px 14px;background:var(--surf2);border:1px solid var(--bdr);border-radius:10px;color:var(--txt);font-size:13px;font-family:'Inter',system-ui,sans-serif;transition:border-color var(--tr),box-shadow var(--tr);appearance:none;-webkit-appearance:none;min-height:44px}
.fi:focus{outline:none;border-color:var(--grn);box-shadow:0 0 0 3px rgba(74,222,128,.12)}
.fi::placeholder{color:var(--mut)}
.fi option{background:#ffffff;color:var(--txt)}

/* Pricing option dropdown (unit select) readability */
#fd-prows select.fi,
.fmod select.fi{color-scheme:light}
#fd-prows select.fi:focus,
.fmod select.fi:focus{
    border-color:rgba(31,42,34,.28);
    box-shadow:0 0 0 3px rgba(180,155,90,.18);
}
.f2col{display:grid;grid-template-columns:1fr 1fr;gap:18px}

/* ── CARDS ── */
.fc{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--rad);padding:26px;margin-bottom:22px}
.fc__t{font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:15px;font-weight:900;color:var(--txt);margin:0 0 16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}

/* ── PRODUCTS GRID ── */
.fp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:18px}
.fp-card{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--rad);overflow:hidden;transition:all var(--tr);min-height:370px;display:flex;flex-direction:column}
.fp-card:hover{border-color:rgba(74,222,128,.22);transform:translateY(-3px);box-shadow:var(--shd)}
.fp-card__iw{position:relative;aspect-ratio:4/3;overflow:hidden;background:var(--surf2);min-height:170px}
.fp-card__img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}
.fp-card:hover .fp-card__img{transform:scale(1.06)}
.fp-card__ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.65),transparent);opacity:0;transition:opacity var(--tr);display:flex;align-items:flex-end;padding:10px}
.fp-card:hover .fp-card__ov{opacity:1}
.fp-card__ni{width:100%;aspect-ratio:4/3;background:var(--surf2);display:grid;place-items:center}
.fp-card__ni svg{width:36px;height:36px;color:var(--mut);opacity:.3}
.fp-card__b{padding:16px;flex:1;display:flex;flex-direction:column}
.fp-card__name{font-size:14px;font-weight:700;color:var(--txt);margin:0 0 3px}
.fp-card__cat{font-size:11px;color:var(--mut);margin:0 0 6px}
.fp-card__desc{font-size:12px;color:var(--mut);margin:0 0 10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.fp-card__price{font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:15px;font-weight:900;color:var(--grn);margin:0 0 10px}
.fp-card__acts{display:flex;gap:6px;flex-wrap:wrap;margin-top:auto}

/* ── FILTER TABS ── */
.fd-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px}
.fd-tab{padding:7px 15px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;border:1px solid var(--bdr);background:var(--surf);color:var(--mut);cursor:pointer;transition:all var(--tr);font-family:'Inter',system-ui,sans-serif}
.fd-tab:hover{border-color:rgba(74,222,128,.2);color:var(--txt)}
.fd-tab.act{background:var(--grn-dim);border-color:rgba(74,222,128,.3);color:var(--grn)}

/* ── CONVERSATIONS ── */
.fconv{display:flex;gap:14px;align-items:flex-start;padding:18px 18px;border-radius:var(--rad);background:var(--surf);border:1px solid var(--bdr);margin-bottom:12px;text-decoration:none;color:inherit;transition:all var(--tr);min-height:78px}
.fconv:hover{border-color:rgba(74,222,128,.2);background:var(--surf2)}
.fconv--unr{border-left:3px solid var(--grn)}
.fconv__av{width:46px;height:46px;border-radius:50%;background:var(--surf3);border:1.5px solid var(--bdr);display:grid;place-items:center;font-size:15px;font-weight:700;color:var(--grn);flex-shrink:0}
.fconv__b{flex:1;min-width:0}
.fconv__name{font-size:13px;font-weight:700;color:var(--txt);margin:0 0 2px;display:flex;align-items:center;gap:7px}
.fconv__prod{font-size:11px;color:var(--mut);margin:0 0 4px}
.fconv__prev{font-size:12px;color:rgba(209,250,229,.55);font-style:italic;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fconv__r{text-align:right;flex-shrink:0;min-width:50px}
.fconv__t{font-size:11px;color:var(--mut)}

/* ── MARKET SETTINGS ── */
.fmkt-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
.fhold-wrap{background:var(--surf2);border-radius:999px;height:5px;margin-bottom:7px;overflow:hidden;border:1px solid var(--bdr)}
.fhold-bar{height:100%;width:0;background:var(--grn);border-radius:999px;transition:width .1s linear}
.fhold-hint{font-size:11px;color:var(--mut);text-align:center;margin:0 0 8px}

/* ── PRICE HISTORY ── */
.fph-row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;margin-bottom:22px}
.fph-row .fg{margin-bottom:0;flex:1;min-width:140px}
.fph-views{display:flex;gap:6px}
.fph-vb{padding:9px 15px;border-radius:9px;font-size:12px;font-weight:700;border:1px solid var(--bdr);background:var(--surf);color:var(--mut);cursor:pointer;font-family:'Inter',system-ui,sans-serif;transition:all var(--tr)}
.fph-vb.act{background:var(--grn-dim);color:var(--grn);border-color:rgba(74,222,128,.3)}
#fd-chart-wrap{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--rad);padding:18px;display:none}
#fd-ph-ph{text-align:center;padding:48px;color:var(--mut);font-size:14px}

/* ── MODAL ── */
.fmod-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:16px;overflow-y:auto}
.fmod-bg.open{display:flex}
.fmod{background:var(--surf);border:1px solid var(--bdr);border-radius:18px;width:100%;max-width:580px;box-shadow:0 28px 80px rgba(0,0,0,.65);animation:mIn .25s cubic-bezier(.4,0,.2,1) both;max-height:90vh;overflow-y:auto}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:none}}
.fmod__hd{display:flex;align-items:center;justify-content:space-between;padding:20px 22px 0}
.fmod__title{font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:18px;font-weight:900;color:var(--txt);margin:0}
.fmod__x{background:none;border:none;color:var(--mut);font-size:22px;cursor:pointer;padding:0;line-height:1;transition:color var(--tr)}.fmod__x:hover{color:var(--red)}
.fmod__bd{padding:18px 22px 22px}

/* ── PRICING ROWS ── */
.fprow{display:grid;grid-template-columns:130px 1fr 100px auto;gap:8px;align-items:center;padding:9px 11px;background:var(--surf2);border:1px solid var(--bdr);border-radius:10px;margin-bottom:7px}
.fprow__def{display:flex;align-items:center;gap:6px;font-size:10px;color:var(--mut);font-weight:700;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap}
.fprow__def input{accent-color:var(--grn)}
.fprow__rm{background:rgba(248,113,113,.1);border:none;color:var(--red);border-radius:7px;padding:6px 10px;cursor:pointer;font-size:12px;transition:background var(--tr);font-family:'Inter',system-ui,sans-serif}.fprow__rm:hover{background:rgba(248,113,113,.22)}

/* ── FLASH TOAST ── */
.fflash{
    position:fixed;
    top:18px;
    left:50%;
    right:auto;
    transform:translateX(-50%);
    padding:12px 18px;
    border-radius:12px;
    font-size:13px;font-weight:600;
    z-index:9999;
    display:flex;align-items:flex-start;gap:10px;
    max-width:360px;
    animation:fIn .3s ease;
    border:1px solid;
    box-shadow:0 14px 36px rgba(17,24,39,.14);
}
.fflash > span{flex:1;min-width:0;line-height:1.3}
.fflash--ok{background:rgba(74,222,128,.14);border-color:rgba(74,222,128,.3);color:var(--grn)}
.fflash--err{background:rgba(248,113,113,.14);border-color:rgba(248,113,113,.3);color:var(--red)}
.fflash__x{
    background:none;border:none;color:inherit;cursor:pointer;
    font-size:18px;line-height:1;
    margin-left:auto;padding:2px 6px;
    opacity:.8;
    flex:0 0 auto;
    position:relative;z-index:1;
}
.fflash__x:hover{opacity:1}
.fflash.out{animation:fOut .3s ease forwards}
@keyframes fIn{from{opacity:0;transform:translateX(-50%) translateY(-10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
@keyframes fOut{to{opacity:0;transform:translateX(-50%) translateY(-10px)}}

/* ── RESPONSIVE ── */
@media(max-width:900px){
    .fd-s{transform:translateX(calc(-1*var(--sw)));transition:transform .3s ease}
    .fd-s.open{transform:none}
    .fd-m{margin-left:0}
    .fd-m__top{padding:14px 16px}
    .fd-m__body{padding:16px 16px 40px}
    .fd-stats{grid-template-columns:1fr 1fr}
    .fp-grid{grid-template-columns:1fr 1fr}
    .fmkt-grid{grid-template-columns:1fr}
    .fprow{grid-template-columns:1fr}
    .f2col{grid-template-columns:1fr}
    #fd-sb-toggle{display:inline-flex!important}
}
@media(max-width:480px){
    .fd-stats{grid-template-columns:1fr}
    .fp-grid{grid-template-columns:1fr}
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>

<div class="fd">

<!-- ══ SIDEBAR ══ -->
<aside class="fd-s" id="fd-sidebar">
    <div class="fd-s__logo">
        <div class="fd-s__mark">FS</div>
        <div class="fd-s__brand">FarmScout<small>Farmer Portal</small></div>
    </div>
    <div class="fd-s__user">
        <div class="fd-s__avatar"><?php echo htmlspecialchars($farmer_initials); ?></div>
                    <div>
            <div class="fd-s__uname"><?php echo htmlspecialchars($farmer_name ?: 'Farmer'); ?></div>
            <div class="fd-s__urole">Farmer</div>
                    </div>
                    </div>
    <nav class="fd-s__nav">
        <div class="fd-s__ns">Main</div>
        <button type="button" class="fd-s__link act" data-sec="market" onclick="showSec('market')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
        </button>
        <button type="button" class="fd-s__link" data-sec="mkt-settings" onclick="showSec('mkt-settings')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Market Settings
        </button>
        <button type="button" class="fd-s__link" data-sec="products" onclick="showSec('products')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>Products
        </button>
        <button type="button" class="fd-s__link" data-sec="reservations" onclick="showSec('reservations')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>Reservations
            <?php if ($farmer_pending_reservations_count > 0): ?><span class="fd-s__badge"><?php echo (int)$farmer_pending_reservations_count; ?></span><?php endif; ?>
        </button>
        <button type="button" class="fd-s__link" data-sec="messages" onclick="showSec('messages')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Messages
            <?php if ($_fd_unread > 0): ?><span class="fd-s__badge"><?php echo (int)$_fd_unread; ?></span><?php endif; ?>
        </button>
        <button type="button" class="fd-s__link" data-sec="price" onclick="showSec('price')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Price History
        </button>
    </nav>

    <?php if ($_fd_mkt): ?>
    <div class="fd-s__mkt">
        <div class="fd-s__mkt-lbl">My Market</div>
        <div class="fd-s__mkt-name"><?php echo htmlspecialchars($_fd_mkt['market_name']); ?></div>
        <div class="fd-s__dot fd-s__dot--<?php echo ($_fd_mkt['is_open']??1)?'o':'c'; ?>">
            <?php echo ($_fd_mkt['is_open']??1)?'OPEN':'CLOSED'; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="fd-s__foot">
        <a href="user-account.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Account
        </a>
        <a href="logout.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out
        </a>
        </div>
</aside>

<!-- ══ MAIN ══ -->
<main class="fd-m">
    <div class="fd-m__top">
        <h1 class="fd-m__title" id="fd-page-title">Dashboard</h1>
        <div class="fd-m__top-r">
            <button type="button" class="fb fb--g fb--sm" id="fd-sb-toggle" style="display:none" onclick="document.getElementById('fd-sidebar').classList.toggle('open')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <button type="button" class="fb fb--p" onclick="openProdModal(null)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Product
            </button>
    </div>
    </div>

    <div class="fd-m__body">

        <!-- ── DASHBOARD ── -->
        <section class="fd-sec act" id="sec-market" data-title="Dashboard">
            <div class="fd-stats" id="fd-stat-cards">
                <div class="fd-stat fd-stat--products" data-delay="0">
                    <div class="fd-stat__icon fd-stat__icon--products" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2"/>
                            <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
                        </svg>
                    </div>
                    <div class="fd-stat__val"><?php echo count($farmer_products); ?></div>
                    <div class="fd-stat__lbl">Total Products</div>
                    </div>
                <div class="fd-stat fd-stat--pending" data-delay="80" style="cursor:pointer" onclick="showSec('reservations');filterRes('pending')">
                    <div class="fd-stat__icon fd-stat__icon--pending" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                </div>
                    <div class="fd-stat__val"><?php echo (int)$farmer_pending_reservations_count; ?></div>
                    <div class="fd-stat__lbl">Pending Orders</div>
            </div>
                <div class="fd-stat fd-stat--completed" data-delay="160" style="cursor:pointer" onclick="showSec('reservations');filterRes('completed')">
                    <div class="fd-stat__icon fd-stat__icon--completed" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="fd-stat__val"><?php echo (int)$_fd_done; ?></div>
                    <div class="fd-stat__lbl">Completed</div>
                    </div>
                <div class="fd-stat fd-stat--messages" data-delay="240" style="cursor:pointer" onclick="showSec('messages')">
                    <div class="fd-stat__icon fd-stat__icon--messages" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                    </div>
                    <div class="fd-stat__val"><?php echo (int)$_fd_unread; ?></div>
                    <div class="fd-stat__lbl">Unread Messages</div>
                </div>
            </div>

            <p style="font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:16px;font-weight:900;color:var(--txt);margin:0 0 14px">Recent Reservations</p>
            <div class="fd-tw">
                <table class="fd-t">
                    <thead><tr><th>Customer</th><th>Product</th><th>Qty</th><th>Status</th><th>Payment</th><th>Date</th></tr></thead>
                    <tbody>
        <?php
                    // PHP: loop recent 5 reservations
                    $recent5 = array_slice($reservations, 0, 5);
                    if (empty($recent5)): ?>
                    <tr><td colspan="6" class="mt">No reservations yet.</td></tr>
                    <?php else: foreach ($recent5 as $rr):
                        $rs = $rr['status']??'';
                        if ($rs==='accepted') $rs='confirmed';
                        if ($rs==='declined') $rs='cancelled';
                        $rpm = $hasPaymentMethod?(string)($rr['payment_method']??''):'';
                    ?>
                    <tr>
                        <td><span style="font-weight:700"><?php echo htmlspecialchars($rr['user_name']??'—'); ?></span></td>
                        <td><?php echo htmlspecialchars($rr['product_name']??'—'); ?></td>
                        <td><?php echo htmlspecialchars(($rr['quantity']??'').' '.($rr['unit']??'')); ?></td>
                        <td><span class="fdb fdb--<?php echo htmlspecialchars(strtolower($rs)); ?>"><?php echo htmlspecialchars(strtoupper($rs)); ?></span></td>
                        <td><?php echo htmlspecialchars(!empty($rpm)?strtoupper($rpm):'—'); ?></td>
                        <td style="color:var(--mut)"><?php echo $rr['created_at']?date('M d',strtotime($rr['created_at'])):'—'; ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ── MARKET SETTINGS ── -->
        <section class="fd-sec" id="sec-mkt-settings" data-title="Market Settings">
            <?php if ($_fd_mkt): ?>
            <div class="fc">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                                    <div>
                        <p style="font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:20px;font-weight:900;color:var(--txt);margin:0 0 4px"><?php echo htmlspecialchars($_fd_mkt['market_name']); ?></p>
                        <p style="font-size:13px;color:var(--mut);margin:0"><?php echo htmlspecialchars($_fd_mkt['address']??''); ?></p>
                                    </div>
                    <span class="fdb fdb--<?php echo ($_fd_mkt['is_open']??1)?'confirmed':'cancelled'; ?>"><?php echo ($_fd_mkt['is_open']??1)?'OPEN':'CLOSED'; ?></span>
                                </div>
                                            </div>
            <div class="fmkt-grid">
                <div class="fc">
                    <p class="fc__t">Operating Hours</p>
                    <form method="POST" action="vendor-actions.php">
                        <input type="hidden" name="action" value="update_market_hours">
                        <input type="hidden" name="market_id" value="<?php echo (int)$_fd_mkt['id']; ?>">
                        <?php if ($_fd_csrf): ?><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_fd_csrf); ?>"><?php endif; ?>
                        <div class="fg"><label>Opening Time</label><input type="time" class="fi" name="opening_time" value="<?php echo htmlspecialchars($_fd_mkt['opening_time']??'06:00'); ?>"></div>
                        <div class="fg"><label>Closing Time</label><input type="time" class="fi" name="closing_time" value="<?php echo htmlspecialchars($_fd_mkt['closing_time']??'18:00'); ?>"></div>
                        <button type="submit" class="fb fb--p">Update Hours</button>
                                        </form>
                                    </div>
                <div class="fc">
                    <p class="fc__t">Market Status</p>
                    <form id="fd-mkt-form" method="POST" action="vendor-actions.php">
                                            <input type="hidden" name="action" value="toggle_market_status">
                        <input type="hidden" name="market_id" value="<?php echo (int)$_fd_mkt['id']; ?>">
                        <input type="hidden" name="is_open" id="fd-is-open-val" value="<?php echo ($_fd_mkt['is_open']??1)?'0':'1'; ?>">
                        <?php if ($_fd_csrf): ?><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_fd_csrf); ?>"><?php endif; ?>
                        <p style="font-size:13px;color:var(--mut);margin:0 0 14px">Current: <span class="fdb fdb--<?php echo ($_fd_mkt['is_open']??1)?'confirmed':'cancelled'; ?>"><?php echo ($_fd_mkt['is_open']??1)?'OPEN':'CLOSED'; ?></span></p>
                        <div class="fhold-wrap"><div class="fhold-bar" id="fd-hold-bar"></div></div>
                        <p class="fhold-hint" id="fd-hold-hint">Hold to <?php echo ($_fd_mkt['is_open']??1)?'close':'open'; ?> market</p>
                        <button type="button" id="fd-hold-btn" class="fb fb--g" style="width:100%;min-height:46px">Hold to <?php echo ($_fd_mkt['is_open']??1)?'Close':'Open'; ?> Market</button>
                        <p style="font-size:11px;color:var(--mut);text-align:center;margin:8px 0 0">Hold until the bar fills</p>
                                        </form>
                        </div>
                    </div>
                <?php else: ?>
            <div class="fc" style="text-align:center;padding:44px"><p style="color:var(--mut)">No market assigned. Contact an administrator.</p></div>
                <?php endif; ?>
        </section>

        <!-- ── PRODUCTS ── -->
        <section class="fd-sec" id="sec-products" data-title="My Products">
                <?php if (empty($farmer_products)): ?>
            <div class="fc" style="text-align:center;padding:48px">
                <p style="font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:18px;font-weight:900;color:var(--txt);margin:0 0 8px">No products yet</p>
                <p style="color:var(--mut);font-size:13px;margin:0 0 20px">Add your first product to start selling.</p>
                <button type="button" class="fb fb--p" onclick="openProdModal(null)">Add First Product</button>
                    </div>
                <?php else: ?>
            <div class="fp-grid">
                            <?php 
                // PHP: loop $farmer_products here
                foreach ($farmer_products as $p):
                    $pname = htmlspecialchars($p['product_name']??'');
                    $pcat  = htmlspecialchars($p['category']??'');
                    $pdesc = htmlspecialchars($p['product_description'] ?? '');
                    $pimg  = !empty($p['product_image'])?htmlspecialchars($p['product_image']):'';
                    $avail = (int)($p['is_available']??1);
                    $pid   = (int)($p['id']??0);
                    $uopts = $p['unit_options']??[];
                    $def   = array_values(array_filter($uopts,fn($u)=>!empty($u['is_default'])));
                    $def   = !empty($def)?$def[0]:(!empty($uopts)?$uopts[0]:null);
                    $price = $def?'₱'.number_format((float)$def['price'],2).' / '.htmlspecialchars($def['display_label']??$def['unit_label']??'unit'):'';
                ?>
                <div class="fp-card">
                    <div class="fp-card__iw">
                        <?php if ($pimg): ?>
                        <img src="<?php echo $pimg; ?>" alt="<?php echo $pname; ?>" class="fp-card__img"
                             onerror="this.parentElement.innerHTML='<div class=\'fp-card__ni\'><svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'14\' rx=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg></div>'">
                        <?php else: ?>
                        <div class="fp-card__ni">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="14" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    </div>
                                        <?php endif; ?>
                        <div class="fp-card__ov">
                            <button type="button" class="fb fb--sm" style="background:rgba(255,255,255,.14);color:#fff;backdrop-filter:blur(6px)" onclick="openProdModal(<?php echo $pid; ?>)">Edit</button>
                                    </div>
                                </div>
                    <div class="fp-card__b">
                        <p class="fp-card__name"><?php echo $pname; ?></p>
                        <p class="fp-card__cat"><?php echo $pcat?:'Uncategorized'; ?> &nbsp;<span class="fdb fdb--<?php echo $avail?'available':'unavailable'; ?>"><?php echo $avail?'In Stock':'Not Available'; ?></span></p>
                        <?php if (!empty($pdesc)): ?><p class="fp-card__desc"><?php echo $pdesc; ?></p><?php endif; ?>
                        <?php if ($price): ?><p class="fp-card__price"><?php echo $price; ?></p><?php endif; ?>
                        <div class="fp-card__acts">
                            <button type="button" class="fb fb--g fb--sm" onclick="openProdModal(<?php echo $pid; ?>)">Edit</button>
                            <?php if ($avail): ?>
                            <form method="POST" action="manage-products.php?product_id=<?php echo $pid; ?>" style="display:inline" id="fd-oos-<?php echo $pid; ?>">
                                <input type="hidden" name="action" value="mark_out_of_stock">
                                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                                <button type="button" class="fb fb--d fb--sm" onclick="openConfirmModal('Mark as not available?', 'This will hide the product from customers until you restock it.', 'fd-oos-<?php echo $pid; ?>')">Not Available</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" action="manage-products.php?product_id=<?php echo $pid; ?>" style="display:inline" id="fd-rst-<?php echo $pid; ?>">
                                <input type="hidden" name="action" value="restock_product">
                                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                                <button type="button" class="fb fb--p fb--sm" onclick="openConfirmModal('Restock product?', 'This will make the product visible and available again.', 'fd-rst-<?php echo $pid; ?>')">Restock</button>
                            </form>
                            <?php endif; ?>
                                </div>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ── RESERVATIONS ── -->
        <section class="fd-sec" id="sec-reservations" data-title="Reservations">
            <div class="fd-tabs">
                <button type="button" class="fd-tab act" onclick="filterRes('all')">All</button>
                <button type="button" class="fd-tab" onclick="filterRes('pending')">Pending<?php if($farmer_pending_reservations_count>0): ?> <span style="background:rgba(251,191,36,.18);color:#fbbf24;border-radius:999px;padding:0 5px;font-size:10px"><?php echo (int)$farmer_pending_reservations_count; ?></span><?php endif; ?></button>
                <button type="button" class="fd-tab" onclick="filterRes('confirmed')">Confirmed</button>
                <button type="button" class="fd-tab" onclick="filterRes('paid')">Paid</button>
                <button type="button" class="fd-tab" onclick="filterRes('completed')">Completed</button>
                <button type="button" class="fd-tab" onclick="filterRes('cancelled')">Cancelled</button>
                                </div>
            <div class="fd-tw">
                <table class="fd-t">
                    <thead><tr><th>Customer</th><th>Product</th><th>Qty</th><th>Pickup</th><th>Status</th><th>Payment</th><th>GCash Ref</th><th>Notes</th><th>Actions</th></tr></thead>
                    <tbody id="fd-res-tbody">
                                            <?php 
                    // PHP: loop $reservations here
                    if (empty($reservations)): ?>
                    <tr><td colspan="9" class="mt">No reservations found.</td></tr>
                    <?php else: foreach ($reservations as $res):
                        $rss = $res['status']??'';
                        if ($rss==='accepted') $rss='confirmed';
                        if ($rss==='declined') $rss='cancelled';
                        $rId  = (int)($res['id']??0);
                        $rpm  = $hasPaymentMethod?(string)($res['payment_method']??''):'';
                        $rgcr = $hasGcashReference?(string)($res['gcash_reference']??''):'';
                        $pickup='';
                        if (!empty($res['preferred_pickup_date'])) {
                            $pickup=date('M d',strtotime($res['preferred_pickup_date']));
                            if (!empty($res['preferred_pickup_time'])) $pickup.=' '.date('g:i A',strtotime($res['preferred_pickup_time']));
                        }
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($rss); ?>">
                        <td>
                            <div style="font-weight:700"><?php echo htmlspecialchars($res['user_name']??'—'); ?></div>
                            <?php if (!empty($res['user_phone'])): ?><div style="font-size:11px;color:var(--mut)"><?php echo htmlspecialchars($res['user_phone']); ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $fd_lines = !empty($res['line_items']) ? $res['line_items'] : [];
                                $fd_prod_title = htmlspecialchars($res['product_name'] ?? '—');
                                if (count($fd_lines) > 1) {
                                    $fd_prod_title = htmlspecialchars(count($fd_lines) . ' products');
                                }
                            ?>
                            <div><?php echo $fd_prod_title; ?></div>
                            <div style="font-size:11px;color:var(--mut)"><?php echo htmlspecialchars($res['market_name']??''); ?></div>
                            <?php if (!empty($res['public_ref']) || !empty($res['chat_public_ref'])): ?>
                                <div style="font-size:10px;color:var(--mut);margin-top:4px;">
                                    <?php if (!empty($res['public_ref'])): ?>RSV <?php echo htmlspecialchars((string)$res['public_ref']); ?><?php endif; ?>
                                    <?php if (!empty($res['public_ref']) && !empty($res['chat_public_ref'])): ?> · <?php endif; ?>
                                    <?php if (!empty($res['chat_public_ref'])): ?>CHAT <?php echo htmlspecialchars((string)$res['chat_public_ref']); ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (count($fd_lines) > 1): ?>
                                <ul style="margin:6px 0 0;padding-left:14px;font-size:11px;line-height:1.35;color:var(--mut);">
                                    <?php foreach ($fd_lines as $li): ?>
                                        <li><?php echo htmlspecialchars(trim(($li['quantity'] ?? '') . ' ' . ($li['unit'] ?? '') . ' — ' . ($li['product_name'] ?? ''))); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td><?php echo count($fd_lines) > 1 ? (int)count($fd_lines) . ' lines' : htmlspecialchars(($res['quantity']??'').' '.($res['unit']??'')); ?></td>
                        <td style="color:var(--mut);font-size:12px"><?php echo htmlspecialchars($pickup?:'—'); ?></td>
                        <td><span class="fdb fdb--<?php echo htmlspecialchars(strtolower($rss)); ?>"><?php echo htmlspecialchars(strtoupper($rss)); ?></span></td>
                        <td><?php echo htmlspecialchars(!empty($rpm)?strtoupper($rpm):'—'); ?></td>
                        <td style="font-size:12px;color:var(--mut)"><?php echo htmlspecialchars(!empty($rgcr)?$rgcr:'—'); ?></td>
                        <td style="font-size:12px;color:var(--mut);max-width:140px"><?php echo htmlspecialchars($res['notes']??'—'); ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <?php if ($rss==='pending'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="reservation_action" value="accept">
                                    <input type="hidden" name="reservation_id" value="<?php echo $rId; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_fd_csrf); ?>">
                                    <button type="submit" class="fb fb--p fb--sm">Confirm</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="reservation_action" value="decline">
                                    <input type="hidden" name="reservation_id" value="<?php echo $rId; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_fd_csrf); ?>">
                                    <button type="submit" class="fb fb--d fb--sm">Cancel</button>
                                </form>
                            <?php elseif (in_array($rss,['confirmed','paid','accepted'],true)): ?>
                                <form method="POST" style="display:inline" id="fd-cmplt-<?php echo $rId; ?>">
                                    <input type="hidden" name="reservation_action" value="complete">
                                    <input type="hidden" name="reservation_id" value="<?php echo $rId; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_fd_csrf); ?>">
                                    <button type="button" class="fb fb--g fb--sm" onclick="openConfirm(<?php echo $rId; ?>)">Complete</button>
                                </form>
                                                <?php endif; ?>
                                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                                                </div>
        </section>

        <!-- ── MESSAGES ── -->
        <section class="fd-sec" id="sec-messages" data-title="Messages">
            <?php if (empty($_fd_convos)): ?>
            <div class="fc" style="text-align:center;padding:48px">
                <p style="font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:18px;font-weight:900;color:var(--txt);margin:0 0 8px">No messages yet</p>
                <p style="color:var(--mut);font-size:13px;margin:0">Customer conversations will appear here once they message you through a reservation.</p>
            </div>
            <?php else: ?>
            <div>
                                            <?php 
                // PHP: loop $_fd_convos here
                foreach ($_fd_convos as $conv):
                    $unread  = (int)($conv['unread_count']??0);
                    $cname   = $conv['user_name']?:($conv['user_username']??'?');
                    $parts   = preg_split('/\s+/',trim($cname));
                    $inits   = strtoupper(mb_substr($parts[0]??'?',0,1).(count($parts)>1?mb_substr($parts[1],0,1):''));
                    $lastMsg = $conv['last_message']??'';
                    $tRaw    = $conv['last_message_time']??'';
                    $diff    = $tRaw?(time()-strtotime($tRaw)):0;
                    $tStr    = !$tRaw?'':($diff<60?'just now':($diff<3600?floor($diff/60).'m':($diff<86400?floor($diff/3600).'h':date('M d',strtotime($tRaw)))));
                    $prev    = mb_strlen($lastMsg)>65?mb_substr($lastMsg,0,65).'…':$lastMsg;
                ?>
                <a class="fconv <?php echo $unread>0?'fconv--unr':''; ?>" href="farmer-dashboard.php?section=reservations">
                    <div class="fconv__av"><?php echo htmlspecialchars($inits); ?></div>
                    <div class="fconv__b">
                        <div class="fconv__name">
                            <?php echo htmlspecialchars($cname); ?>
                            <?php if ($unread>0): ?><span class="fdb fdb--pending"><?php echo $unread; ?> new</span><?php endif; ?>
                                                </div>
                        <div class="fconv__prod"><?php echo htmlspecialchars($conv['product_name']??''); ?> · <?php echo htmlspecialchars(($conv['quantity']??'').' '.($conv['unit']??'')); ?><?php if (!empty($conv['chat_public_ref'])): ?> · <?php echo htmlspecialchars((string)$conv['chat_public_ref']); ?><?php endif; ?></div>
                        <?php if ($prev): ?><div class="fconv__prev">"<?php echo htmlspecialchars($prev); ?>"</div><?php endif; ?>
                    </div>
                    <?php if ($tStr): ?><div class="fconv__r"><span class="fconv__t"><?php echo $tStr; ?></span></div><?php endif; ?>
                </a>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
        </section>

        <!-- ── PRICE HISTORY ── -->
        <section class="fd-sec" id="sec-price" data-title="Price History">
            <div class="fph-row">
                <div class="fg">
                    <label>Product</label>
                    <select class="fi" id="fd-ph-product" onchange="loadPH()">
                        <option value="">— Choose product —</option>
                        <?php
                        // PHP: loop $farmer_products for price history dropdown
                        foreach ($farmer_products as $pp): ?>
                        <option value="<?php echo (int)($pp['id']??0); ?>"><?php echo htmlspecialchars($pp['product_name']??''); ?></option>
                        <?php endforeach; ?>
                    </select>
                                                </div>
                <div class="fg"><label>From</label><input type="date" class="fi" id="fd-ph-from" onchange="loadPH()"></div>
                <div class="fg"><label>To</label><input type="date" class="fi" id="fd-ph-to" onchange="loadPH()"></div>
                <div class="fg">
                    <label>Change Type</label>
                    <select class="fi" id="fd-ph-type" onchange="loadPH()">
                        <option value="all">All Changes</option>
                        <option value="increase">Increases</option>
                        <option value="decrease">Decreases</option>
                        <option value="new">New Prices</option>
                    </select>
                                            </div>
                <div class="fph-views">
                    <button type="button" class="fph-vb act" id="fd-ph-cb" onclick="setPHView('chart')">Chart</button>
                    <button type="button" class="fph-vb" id="fd-ph-tb" onclick="setPHView('table')">Table</button>
                </div>
            </div>
            <div id="fd-ph-ph">Select a product above to view its price history.</div>
            <div id="fd-chart-wrap"><canvas id="fd-ph-canvas" height="90"></canvas></div>
            <div id="fd-ph-table-wrap" style="display:none" class="fd-tw">
                <table class="fd-t">
                    <thead><tr><th>Date &amp; Time</th><th>Old Price</th><th>New Price</th><th>Change</th><th>Unit</th></tr></thead>
                    <tbody id="fd-ph-tbody"></tbody>
                </table>
            </div>
        </section>

    </div><!-- /fd-m__body -->
</main>
</div><!-- /fd -->

<!-- ── PRODUCT MODAL ── -->
<div class="fmod-bg" id="fd-prod-modal">
    <div class="fmod">
        <div class="fmod__hd">
            <h2 class="fmod__title" id="fd-mod-title">Add Product</h2>
            <button type="button" class="fmod__x" onclick="closeProdModal()">×</button>
        </div>
        <div class="fmod__bd">
            <form method="POST" action="manage-products.php" enctype="multipart/form-data" id="fd-prod-form">
                <input type="hidden" name="action" value="add_product" id="fd-prod-action">
                <input type="hidden" name="product_id" value="" id="fd-prod-pid">
                <input type="hidden" name="confirm_outside_ref_band" value="0" id="fd-confirm-outside-band">
                <div class="f2col">
                    <div class="fg">
                        <label>Market</label>
                        <?php if ($_fd_lock_mid > 0): ?>
                        <input class="fi" type="text" value="<?php echo htmlspecialchars($_fd_lock_mname ?: 'Current Market'); ?>" readonly style="opacity:.55">
                        <input type="hidden" name="market_id" value="<?php echo (int)$_fd_lock_mid; ?>" id="fd-prod-market-hidden">
                                                <?php else: ?>
                        <select class="fi" name="market_id" id="fd-prod-mkt" required>
                            <option value="">Select Market</option>
                            <?php foreach ($_fd_all_mkts as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['market_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                                                <?php endif; ?>
                                            </div>
                    <div class="fg">
                        <label>Product Name *</label>
                        <?php $fd_std_products = function_exists('fsGetStandardProductNames') ? fsGetStandardProductNames() : []; ?>
                        <input class="fi" type="text" name="product_name" id="fd-prod-name" required placeholder="Start typing…" list="fd-prod-name-list" autocomplete="off">
                        <datalist id="fd-prod-name-list">
                            <?php foreach (($fd_std_products ?? []) as $pn): ?>
                                <option value="<?php echo htmlspecialchars((string)$pn); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div id="fd-prod-name-err" style="display:none;font-size:12px;color:var(--red);margin-top:6px">
                            Please select an existing product.
                        </div>
                    </div>
                                        </div>
                <div class="fg">
                    <label>Category</label>
                    <select class="fi" name="category" id="fd-prod-cat">
                        <option value="">Select Category</option>
                        <?php
                        // PHP: loop $categories for product form
                        foreach (($categories??[]) as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                    </select>
                                </div>
                <div class="fg"><label>Description</label><textarea class="fi" name="description" id="fd-prod-desc" rows="2" placeholder="Optional description…"></textarea></div>
                <div class="f2col">
                    <div class="fg"><label>Product Image</label><input class="fi" type="file" name="product_image" id="fd-prod-img" accept="image/*" onchange="prevImg(this)"></div>
                    <div class="fg" style="display:flex;align-items:flex-end">
                        <label style="display:flex;align-items:center;gap:10px;text-transform:none;letter-spacing:0;font-size:13px;cursor:pointer;margin-bottom:0">
                            <input type="checkbox" name="is_available" value="1" id="fd-prod-avail" style="width:16px;height:16px;accent-color:var(--grn)"> Available for sale
                        </label>
                            </div>
                        </div>
                <div id="fd-img-prev" style="display:none;margin-bottom:12px">
                    <img id="fd-img-thumb" src="" alt="Preview" style="max-height:110px;border-radius:10px;border:1px solid var(--bdr)">
                    </div>
                <!-- Pricing options -->
                <div style="background:var(--surf2);border:1px solid var(--bdr);border-radius:12px;padding:14px;margin-bottom:14px">
                    <p style="font-family:'InterDisplay','Inter',system-ui,sans-serif;font-size:13px;font-weight:900;color:var(--txt);margin:0 0 12px">Pricing Options</p>
                    <div id="fd-prows"></div>
                    <button type="button" class="fb fb--g fb--sm" style="margin-top:6px" onclick="addProw()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:12px;height:12px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add Option
                    </button>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
                    <button type="button" class="fb fb--g" onclick="closeProdModal()">Cancel</button>
                    <button type="submit" class="fb fb--p" id="fd-prod-submit">Add Product</button>
                </div>
            </form>
        </div>
            </div>
        </div>

<!-- ── CONFIRM MODAL (reused) ── -->
<div class="fmod-bg" id="fd-confirm-modal">
    <div class="fmod" style="max-width:360px">
        <div class="fmod__hd">
            <h2 class="fmod__title" id="fd-confirm-title">Confirm Action</h2>
            <button type="button" class="fmod__x" onclick="closeConfirm()">×</button>
        </div>
        <div class="fmod__bd">
            <p id="fd-confirm-msg" style="color:var(--mut);font-size:13px;margin:0 0 20px;white-space:pre-line">Are you sure?</p>
            <div style="display:flex;justify-content:flex-end;gap:8px">
                <button type="button" class="fb fb--g" onclick="closeConfirm()">Cancel</button>
                <button type="button" class="fb fb--p" id="fd-confirm-ok">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ================================================================
   FARMSCOUT FARMER DASHBOARD — JavaScript
   ================================================================ */
const FD_PRODS = <?php echo $_fd_prods_js; ?>;
const UNITS    = <?php
    $u = function_exists('fsStandardUnitOptions') ? array_keys(fsStandardUnitOptions()) : ['kg','g','piece','bundle','pack','dozen','box','sack','tray','liter','ml'];
    echo json_encode(array_values($u), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;
const FD_LOCKED_MARKET_ID = <?php echo (int)$_fd_lock_mid; ?>;

// Standard products list (Admin Ref Prices = source of truth).
const FD_STD_PRODUCTS = <?php echo json_encode(function_exists('fsGetStandardProductNames') ? fsGetStandardProductNames() : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

// Reference price bands (Admin Ref Prices min/max).
const FD_REF_BANDS = <?php
    $fd_bands = [];
    try {
        $pdo = getDB();
        if ($pdo) {
            $fd_bands = $pdo->query("SELECT product_name, category, unit, ref_price_min, ref_price_max FROM admin_product_reference_prices")
                ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $fd_bands = [];
    }
    echo json_encode($fd_bands, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

function fdKey3(p, c, u) {
    return (p || '').toString().trim().toLowerCase() + '|' + (c || '').toString().trim().toLowerCase() + '|' + (u || '').toString().trim().toLowerCase();
}

function fdBuildBandMap() {
    const m = new Map();
    (FD_REF_BANDS || []).forEach(b => {
        const k = fdKey3(b.product_name, b.category, b.unit);
        m.set(k, { min: Number(b.ref_price_min || 0), max: Number(b.ref_price_max || 0) });
    });
    return m;
}

const FD_BAND_MAP = fdBuildBandMap();

function fdIsStandardProductName(name) {
    const v = (name || '').toString().trim().toLowerCase();
    if (!v) return false;
    return (FD_STD_PRODUCTS || []).some(p => (p || '').toString().trim().toLowerCase() === v);
}

// ── SECTIONS ──
const SECS = {
    market:         {id:'sec-market',         title:'Dashboard'},
    'mkt-settings': {id:'sec-mkt-settings',   title:'Market Settings'},
    products:       {id:'sec-products',        title:'My Products'},
    reservations:   {id:'sec-reservations',    title:'Reservations'},
    messages:       {id:'sec-messages',        title:'Messages'},
    price:          {id:'sec-price',           title:'Price History'},
};

function showSec(name) {
    document.querySelectorAll('.fd-sec').forEach(s=>s.classList.remove('act'));
    document.querySelectorAll('.fd-s__link').forEach(l=>l.classList.remove('act'));
    const m=SECS[name]; if(!m) return;
    const sec=document.getElementById(m.id);
    if(sec){sec.classList.add('act');animateSec(sec);}
    document.querySelectorAll(`.fd-s__link[data-sec="${name}"]`).forEach(l=>l.classList.add('act'));
    const t=document.getElementById('fd-page-title'); if(t) t.textContent=m.title;
    const urlSec={market:'market','mkt-settings':'market',products:'products',reservations:'reservations',messages:'messages',price:'price_history'}[name]||'market';
    try{ const u=new URL(location.href); u.searchParams.set('section',urlSec); history.replaceState(null,'',u); }catch(e){}
}

function animateSec(sec) {
    sec.querySelectorAll('.fd-stat').forEach((c,i)=>{c.classList.remove('in');setTimeout(()=>c.classList.add('in'),i*80);});
}

// ── RESERVATION FILTER ──
function filterRes(status) {
    document.querySelectorAll('.fd-tab').forEach(t=>t.classList.remove('act'));
    const tabs=[...document.querySelectorAll('.fd-tab')];
    const t=tabs.find(t=>t.textContent.trim().toLowerCase().startsWith(status==='all'?'all':status));
    if(t) t.classList.add('act');
    document.querySelectorAll('#fd-res-tbody tr[data-status]').forEach(row=>{
        const rs=row.dataset.status||'';
        row.style.display=(status==='all'||rs===status||(status==='confirmed'&&rs==='accepted')||(status==='cancelled'&&rs==='declined'))?'':'none';
    });
}

// ── PRODUCT MODAL ──
let _eid=null;
function openProdModal(pid) {
    _eid=pid;
    const modal=document.getElementById('fd-prod-modal');
    const form=document.getElementById('fd-prod-form');
    const title=document.getElementById('fd-mod-title');
    const action=document.getElementById('fd-prod-action');
    const pidEl=document.getElementById('fd-prod-pid');
    const submit=document.getElementById('fd-prod-submit');
    form.reset(); document.getElementById('fd-img-prev').style.display='none'; initProws([]);
    const pnErr = document.getElementById('fd-prod-name-err');
    if (pnErr) pnErr.style.display = 'none';
    if(pid){
        const p=FD_PRODS.find(p=>p.id===pid); if(!p) return;
        title.textContent='Edit Product'; action.value='update_product'; pidEl.value=pid; submit.textContent='Update Product';
        form.action='manage-products.php?product_id='+pid;
        sv('fd-prod-name',p.product_name);
        sv('fd-prod-cat',p.category); sv('fd-prod-desc',p.description);
        const av=document.getElementById('fd-prod-avail'); if(av) av.checked=p.is_available===1;
        const mk=document.getElementById('fd-prod-mkt'); if(mk) mk.value=String(FD_LOCKED_MARKET_ID || p.market_id);
        const mkHidden=document.getElementById('fd-prod-market-hidden'); if(mkHidden) mkHidden.value=String(FD_LOCKED_MARKET_ID || p.market_id || '');
        if(p.image){document.getElementById('fd-img-thumb').src=p.image;document.getElementById('fd-img-prev').style.display='block';}
        initProws(p.unit_options||[]);
    } else {
        title.textContent='Add Product'; action.value='add_product'; pidEl.value=''; submit.textContent='Add Product'; form.action='manage-products.php';
        const av=document.getElementById('fd-prod-avail'); if(av) av.checked=true;
        const mk=document.getElementById('fd-prod-mkt'); if(mk && FD_LOCKED_MARKET_ID>0) mk.value=String(FD_LOCKED_MARKET_ID);
        const mkHidden=document.getElementById('fd-prod-market-hidden'); if(mkHidden && FD_LOCKED_MARKET_ID>0) mkHidden.value=String(FD_LOCKED_MARKET_ID);
        initProws([{unit_label:'',price:'',is_default:true}]);
        sv('fd-prod-name','');
    }
    modal.classList.add('open'); document.body.style.overflow='hidden';
}
function closeProdModal(){document.getElementById('fd-prod-modal').classList.remove('open');document.body.style.overflow='';_eid=null;}
function sv(id,val){const e=document.getElementById(id);if(e)e.value=val||'';}
function prevImg(input){if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{document.getElementById('fd-img-thumb').src=e.target.result;document.getElementById('fd-img-prev').style.display='block';};r.readAsDataURL(input.files[0]);}}

// ── PRICING ROWS ──
function uOpts(sel){
    const opts=[['','— Unit —'],...UNITS.map(u=>[u,u])];
    return opts.map(([v,t])=>`<option value="${v}" ${v===sel?'selected':''}>${t}</option>`).join('');
}
function initProws(arr){
    const w=document.getElementById('fd-prows');if(!w)return;
    w.innerHTML='';
    const rows=arr.length?arr:[{unit_label:'',price:'',is_default:true}];
    rows.forEach((o,i)=>_addProw(o,i)); syncRm();
}
function addProw(){_addProw({},document.getElementById('fd-prows').querySelectorAll('.fprow').length);syncRm();}
function _addProw(o,i){
    const w=document.getElementById('fd-prows');if(!w)return;
    const o_=o||{},lbl=o_.unit_label||'',isC=lbl&&!UNITS.includes(lbl);
    const row=document.createElement('div'); row.className='fprow';
    row.innerHTML=`
        <select name="unit_option_label[]" class="fi" style="padding:8px 10px;font-size:12px" required onchange="uChg(this)">${uOpts(lbl)}</select>
        <input type="text" name="unit_option_custom_label[]" class="fi" style="padding:8px 10px;font-size:12px;display:${isC?'block':'none'}" placeholder="Custom unit" value="${isC?lbl:''}">
        <input type="number" name="unit_option_price[]" class="fi" style="padding:8px 10px;font-size:12px" placeholder="₱ Price" step="0.01" min="0" required value="${o_.price||''}">
        <input type="hidden" name="unit_option_quantity[]" value="1">
        <label class="fprow__def"><input type="radio" name="unit_option_default_index" value="${i}" ${o_.is_default?'checked':''}> Default</label>
        <button type="button" class="fprow__rm" onclick="rmProw(this)">✕</button>
    `;
    w.appendChild(row);
}
function rmProw(btn){
    btn.closest('.fprow').remove();
    document.querySelectorAll('#fd-prows .fprow').forEach((r,i)=>{const radio=r.querySelector('input[type="radio"]');if(radio)radio.value=i;});
    syncRm();
}
function syncRm(){const rows=document.querySelectorAll('#fd-prows .fprow');rows.forEach(r=>{const b=r.querySelector('.fprow__rm');if(b)b.style.display=rows.length>1?'':'none';});}
function uChg(sel){const row=sel.closest('.fprow');if(!row)return;const c=row.querySelector('input[name="unit_option_custom_label[]"]');if(!c)return;if(sel.value==='custom'){c.style.display='block';c.required=true;}else{c.style.display='none';c.required=false;c.value='';}}

// Enforce standardized product name selection (Admin Ref Prices source of truth).
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('fd-prod-form');
    const inp  = document.getElementById('fd-prod-name');
    const err  = document.getElementById('fd-prod-name-err');
    const cat  = document.getElementById('fd-prod-cat');
    const confirmFlag = document.getElementById('fd-confirm-outside-band');
    if (!form || !inp) return;

    function showErr(on) {
        if (!err) return;
        err.style.display = on ? 'block' : 'none';
    }

    inp.addEventListener('input', function () {
        // Hide error once they start changing the input.
        showErr(false);
    });

    form.addEventListener('submit', function (e) {
        const v = (inp.value || '').toString();
        if (!fdIsStandardProductName(v)) {
            e.preventDefault();
            showErr(true);
            inp.focus();
            return;
        }

        // Soft enforcement: confirm if outside reference band.
        if (confirmFlag && confirmFlag.value === '1') {
            // Already confirmed; allow submit.
            return;
        }

        const productName = v.trim();
        const category = (cat && cat.value ? cat.value : '').toString();

        // Collect pricing options from DOM.
        const rows = Array.from(document.querySelectorAll('#fd-prows .fprow'));
        const issues = [];
        rows.forEach(r => {
            const unitSel = r.querySelector('select[name="unit_option_label[]"]');
            const priceIn = r.querySelector('input[name="unit_option_price[]"]');
            const unit = unitSel ? unitSel.value : '';
            const price = priceIn ? Number(priceIn.value) : NaN;
            if (!unit || !isFinite(price)) return;
            const band = FD_BAND_MAP.get(fdKey3(productName, category, unit));
            if (!band || !(band.max > 0 || band.min > 0)) return;
            if (band.max > 0 && price > band.max) {
                issues.push({ unit, price, min: band.min, max: band.max, type: 'above' });
            } else if (band.min > 0 && price < band.min) {
                issues.push({ unit, price, min: band.min, max: band.max, type: 'below' });
            }
        });

        if (issues.length) {
            e.preventDefault();
            const headerLines = [
                `Product: ${productName}`,
                `Category: ${category || '—'}`,
                '',
                'Outside reference band:',
            ];
            const detailLines = issues.slice(0, 4).map(it => {
                const minTxt = (Number(it.min) > 0) ? `min ₱${Number(it.min).toFixed(2)}` : '';
                const maxTxt = (Number(it.max) > 0) ? `max ₱${Number(it.max).toFixed(2)}` : '';
                const ref = `Ref ${[minTxt, maxTxt].filter(Boolean).join(' • ')}`.trim();
                const entered = `Entered ₱${Number(it.price).toFixed(2)}`;
                const rel = it.type === 'above' ? 'above max' : 'below min';
                return `- ${it.unit}: ${entered} (${rel}) — ${ref}`;
            });
            const tailLines = [
                ...(issues.length > 4 ? [`(+${issues.length - 4} more)`] : []),
                '',
                'Submit anyway?',
            ];
            openConfirmModal(
                'Price outside reference band',
                [...headerLines, ...detailLines, ...tailLines].join('\n'),
                '__fd_submit_anyway__'
            );

            // Wire confirm button to submit the product form.
            const ok = document.getElementById('fd-confirm-ok');
            if (ok) {
                ok.onclick = function () {
                    if (confirmFlag) confirmFlag.value = '1';
                    closeConfirm();
                    form.submit();
                };
            }
        }
    });
});

// ── CONFIRM COMPLETE ──
let _cfId=null;
function openConfirm(rid){
    openConfirmModal('Mark Completed?', 'Are you sure? This cannot be undone.', 'fd-cmplt-'+rid);
}

function openConfirmModal(title, message, formId){
    _cfId=formId;
    const t=document.getElementById('fd-confirm-title');
    const m=document.getElementById('fd-confirm-msg');
    if(t) t.textContent=title||'Confirm Action';
    if(m) m.textContent=message||'Are you sure?';
    document.getElementById('fd-confirm-modal').classList.add('open');
    document.body.style.overflow='hidden';
}
function closeConfirm(){document.getElementById('fd-confirm-modal').classList.remove('open');document.body.style.overflow='';_cfId=null;}
document.getElementById('fd-confirm-ok').addEventListener('click',function(){if(_cfId){const f=document.getElementById(_cfId);if(f)f.submit();}closeConfirm();});

// ── FLASH ──
function showFlash(msg,type){
    const el=document.createElement('div');
    el.className=`fflash fflash--${type==='success'?'ok':'err'}`;
    el.innerHTML=`<span>${msg}</span><button type="button" class="fflash__x" onclick="this.closest('.fflash').remove()">×</button>`;
    document.body.appendChild(el);
    setTimeout(()=>{el.classList.add('out');setTimeout(()=>el.remove(),320);},4200);
}

// ── HOLD BUTTON ──
function initHold(){
    const btn=document.getElementById('fd-hold-btn'),bar=document.getElementById('fd-hold-bar'),hint=document.getElementById('fd-hold-hint'),form=document.getElementById('fd-mkt-form');
    if(!btn||!bar||!form) return;
    let iv=null,prog=0; const hd=hint?hint.textContent:'';
    const reset=()=>{prog=0;bar.style.width='0%';if(hint)hint.textContent=hd;if(iv){clearInterval(iv);iv=null;}};
    const start=()=>{iv=setInterval(()=>{prog+=2;bar.style.width=Math.min(prog,100)+'%';if(prog>=100){clearInterval(iv);if(hint)hint.textContent='Updating…';setTimeout(()=>form.submit(),120);}},30);};
    ['mousedown','touchstart'].forEach(e=>btn.addEventListener(e,ev=>{ev.preventDefault();start();}));
    ['mouseup','mouseleave','touchend','touchcancel'].forEach(e=>btn.addEventListener(e,ev=>{if(prog<100)reset();}));
}

// ── PRICE HISTORY ──
let _phChart=null,_phView='chart',_phData=null;
function setPHView(v){
    _phView=v;
    document.getElementById('fd-ph-cb').classList.toggle('act',v==='chart');
    document.getElementById('fd-ph-tb').classList.toggle('act',v==='table');
    if(_phData){if(v==='chart')renderPHChart(_phData);else renderPHTable(_phData);}
}
function loadPH(){
    const pid=document.getElementById('fd-ph-product')?.value; if(!pid) return;
    const from=document.getElementById('fd-ph-from')?.value||'';
    const to=document.getElementById('fd-ph-to')?.value||'';
    const type=document.getElementById('fd-ph-type')?.value||'all';
    const ph=document.getElementById('fd-ph-ph'),cw=document.getElementById('fd-chart-wrap'),tw=document.getElementById('fd-ph-table-wrap');
    if(ph){ph.style.display='';ph.textContent='Loading…';}
    if(cw)cw.style.display='none'; if(tw)tw.style.display='none';
    let url=`/farmscout_online/api/get_price_history.php?product_id=${encodeURIComponent(pid)}`;
    if(from)url+=`&date_from=${encodeURIComponent(from)}`;
    if(to)url+=`&date_to=${encodeURIComponent(to)}`;
    if(type&&type!=='all')url+=`&change_type=${encodeURIComponent(type)}`;
    fetch(url).then(r=>r.json()).then(data=>{
        if(ph)ph.style.display='none';
        const hist=data.price_history||data.price_data||[];
        if(!hist.length){if(ph){ph.textContent='No price history found.';ph.style.display='';}return;}
        _phData=data;
        if(_phView==='chart')renderPHChart(data);else renderPHTable(data);
    }).catch(err=>{if(ph){ph.textContent='Failed to load.';ph.style.display='';}console.error(err);});
}
function renderPHChart(data){
    const cw=document.getElementById('fd-chart-wrap'),tw=document.getElementById('fd-ph-table-wrap');
    if(cw)cw.style.display='block';if(tw)tw.style.display='none';
    const hist=data.price_history||data.price_data||[];
    const ctx=document.getElementById('fd-ph-canvas')?.getContext('2d');if(!ctx)return;
    if(_phChart){_phChart.destroy();_phChart=null;}
    _phChart=new Chart(ctx,{type:'line',
        data:{labels:hist.map(h=>h.formatted_date||h.changed_at||''),
              datasets:[{label:'Price (₱)',data:hist.map(h=>parseFloat(h.new_price||h.price||0)),borderColor:'#4ade80',backgroundColor:'rgba(74,222,128,.1)',borderWidth:2,pointBackgroundColor:'#4ade80',pointRadius:4,tension:.35,fill:true}]},
        options:{responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:'#111a14',borderColor:'rgba(74,222,128,.2)',borderWidth:1,titleColor:'#d1fae5',bodyColor:'#d1fae5',callbacks:{label:c=>'₱'+c.parsed.y.toFixed(2)}}},
            scales:{x:{ticks:{color:'rgba(209,250,229,.45)',font:{size:11}},grid:{color:'rgba(74,222,128,.07)'}},y:{ticks:{color:'rgba(209,250,229,.45)',font:{size:11},callback:v=>'₱'+v},grid:{color:'rgba(74,222,128,.07)'},beginAtZero:false}}},
    });
}
function renderPHTable(data){
    const cw=document.getElementById('fd-chart-wrap'),tw=document.getElementById('fd-ph-table-wrap');
    if(cw)cw.style.display='none';if(tw)tw.style.display='block';
    const hist=data.price_history||data.price_data||[];
    const tbody=document.getElementById('fd-ph-tbody');if(!tbody)return;
    tbody.innerHTML=hist.map(h=>{
        const o=parseFloat(h.old_price??0),n=parseFloat(h.new_price||h.price||0),d=n-o;
        const ds=d>0?`<span style="color:#4ade80">▲ ₱${d.toFixed(2)}</span>`:d<0?`<span style="color:#f87171">▼ ₱${Math.abs(d).toFixed(2)}</span>`:`<span style="color:var(--mut)">—</span>`;
        return `<tr><td>${h.formatted_date||h.changed_at||'—'}</td><td>${o>0?'₱'+o.toFixed(2):'—'}</td><td>₱${n.toFixed(2)}</td><td>${ds}</td><td>${h.unit||data.product_unit||'—'}</td></tr>`;
    }).join('');
}

// ── MODAL BACKDROP / ESC ──
['fd-prod-modal','fd-confirm-modal'].forEach(id=>{const el=document.getElementById(id);if(el)el.addEventListener('click',e=>{if(e.target===el){closeProdModal();closeConfirm();}});});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeProdModal();closeConfirm();}});

// ── RESPONSIVE ──
function initResp(){
    const t=document.getElementById('fd-sb-toggle');
    const upd=()=>{if(t)t.style.display=window.innerWidth<=900?'inline-flex':'none';};
    upd(); window.addEventListener('resize',upd);
}

// ── INIT ──
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.fd-stat').forEach((c,i)=>{const d=parseInt(c.dataset.delay)||(i*80);setTimeout(()=>c.classList.add('in'),d);});
    showSec('<?php echo htmlspecialchars($_fd_init); ?>');
    initHold();
    initResp();
    <?php if ($success_message): ?>showFlash('<?php echo addslashes(htmlspecialchars($success_message)); ?>','success');<?php endif; ?>
    <?php if ($error_message): ?>showFlash('<?php echo addslashes(htmlspecialchars($error_message)); ?>','error');<?php endif; ?>
});
</script>
<?php include 'includes/floating_chat.php'; ?>
</body>
</html>