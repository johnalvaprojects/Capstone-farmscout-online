<?php
require_once 'includes/enhanced_functions.php';

// Check if user is logged in and is a DTI admin or super admin
if (!isLoggedIn() || !isAdminUser()) {
    header('Location: login.php?error=' . urlencode('Access denied. DTI access required.'));
    exit;
}

$conn = getDB();
$message = '';
$error = '';

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'promote_to_admin':
            if (!isSuperAdmin()) {
                $error = "Only the Super Admin can manage DTI admin accounts.";
                break;
            }
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    $target_stmt = $conn->prepare("SELECT user_role FROM users WHERE id = ?");
                    $target_stmt->execute([$user_id]);
                    $target_user = $target_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$target_user) {
                        $error = "User not found.";
                    } elseif ($target_user['user_role'] === 'super_admin') {
                        $error = "Cannot modify a Super Admin account.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET user_role = 'admin', is_active = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $message = "User promoted to DTI Admin successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error promoting user: " . $e->getMessage();
                }
            } else {
                $error = "User ID is required.";
            }
            break;

        case 'demote_to_consumer':
            if (!isSuperAdmin()) {
                $error = "Only the Super Admin can manage DTI admin accounts.";
                break;
            }
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    $target_stmt = $conn->prepare("SELECT user_role FROM users WHERE id = ?");
                    $target_stmt->execute([$user_id]);
                    $target_user = $target_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$target_user) {
                        $error = "User not found.";
                    } elseif ($target_user['user_role'] === 'super_admin') {
                        $error = "Cannot modify a Super Admin account.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET user_role = 'consumer' WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $message = "DTI Admin demoted to Consumer successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error demoting user: " . $e->getMessage();
                }
            } else {
                $error = "User ID is required.";
            }
            break;

        case 'deactivate_user':
            if (!isSuperAdmin()) {
                $error = "Only the Super Admin can manage DTI admin accounts.";
                break;
            }
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    $target_stmt = $conn->prepare("SELECT user_role FROM users WHERE id = ?");
                    $target_stmt->execute([$user_id]);
                    $target_user = $target_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$target_user) {
                        $error = "User not found.";
                    } elseif ($target_user['user_role'] === 'super_admin') {
                        $error = "Cannot modify a Super Admin account.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $message = "User deactivated successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error deactivating user: " . $e->getMessage();
                }
            } else {
                $error = "User ID is required.";
            }
            break;

        case 'activate_user':
            if (!isSuperAdmin()) {
                $error = "Only the Super Admin can manage DTI admin accounts.";
                break;
            }
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    $target_stmt = $conn->prepare("SELECT user_role FROM users WHERE id = ?");
                    $target_stmt->execute([$user_id]);
                    $target_user = $target_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$target_user) {
                        $error = "User not found.";
                    } elseif ($target_user['user_role'] === 'super_admin') {
                        $error = "Cannot modify a Super Admin account.";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $message = "User activated successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error activating user: " . $e->getMessage();
                }
            } else {
                $error = "User ID is required.";
            }
            break;

        case 'create_dti_admin':
            if (!isSuperAdmin()) {
                $error = "Only the Super Admin can create DTI admin accounts.";
                break;
            }
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            
            // Validation
            if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
                $error = "All fields are required.";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email address.";
            } else {
                try {
                    // Check if username or email already exists
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check_stmt->execute([$username, $email]);
                    if ($check_stmt->fetch()) {
                        $error = "Username or email already exists.";
                    } else {
                        // Hash password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Check if verification_status column exists
                        $hasVerificationStatus = false;
                        try {
                            $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_status'");
                            $hasVerificationStatus = $col_check->rowCount() > 0;
                        } catch (Exception $e) {
                            // Column doesn't exist, that's okay
                        }
                        
                        // Insert new DTI Admin account
                        if ($hasVerificationStatus) {
                            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, user_role, is_active, login_method, is_verified, verification_status, created_at, updated_at) VALUES (?, ?, ?, ?, 'admin', 1, 'email', 1, 'verified', NOW(), NOW())");
                        } else {
                            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, user_role, is_active, login_method, is_verified, created_at, updated_at) VALUES (?, ?, ?, ?, 'admin', 1, 'email', 1, NOW(), NOW())");
                        }
                        $insert_stmt->execute([$username, $email, $password_hash, $full_name]);
                        $new_admin_id = (int) $conn->lastInsertId();
                        try {
                            $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$new_admin_id]);
                        } catch (Exception $e) {
                            // Legacy `role` column may be absent on some installs
                        }
                        
                        $message = "DTI Admin account created successfully! Username: " . htmlspecialchars($username);
                    }
                } catch (Exception $e) {
                    $error = "Error creating DTI Admin account: " . $e->getMessage();
                }
            }
            break;

        case 'approve_farmer':
            $application_id = $_POST['application_id'] ?? '';
            if ($application_id) {
                try {
                    // Get application details
                    $stmt = $conn->prepare("SELECT * FROM farmer_applications WHERE id = ?");
                    $stmt->execute([$application_id]);
                    $application = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($application) {
                        // Insert into market_farmers table
                        $insert_stmt = $conn->prepare("INSERT INTO market_farmers (market_id, farmer_id, approval_status, approved_at, approved_by, monthly_fee) VALUES (?, ?, 'approved', NOW(), ?, 500.00)");
                        $insert_stmt->execute([$application['market_id'], $application['farmer_id'], $_SESSION['user_id']]);
                        
                        // Update application status
                        $update_stmt = $conn->prepare("UPDATE farmer_applications SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
                        $update_stmt->execute([$_SESSION['user_id'], $application_id]);
                        
                        $message = "Farmer application approved successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error approving application: " . $e->getMessage();
                }
            }
            break;
            
        case 'reject_farmer':
            $application_id = $_POST['application_id'] ?? '';
            if ($application_id) {
                try {
                    $stmt = $conn->prepare("UPDATE farmer_applications SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], $application_id]);
                    $message = "Farmer application rejected.";
                } catch (Exception $e) {
                    $error = "Error rejecting application: " . $e->getMessage();
                }
            }
            break;
            
        case 'suspend_market':
            $market_id = $_POST['market_id'] ?? '';
            if ($market_id) {
                try {
                    $stmt = $conn->prepare("UPDATE markets SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$market_id]);
                    $message = "Market suspended successfully!";
                } catch (Exception $e) {
                    $error = "Error suspending market: " . $e->getMessage();
                }
            }
            break;
            
        case 'activate_market':
            $market_id = $_POST['market_id'] ?? '';
            if ($market_id) {
                try {
                    $stmt = $conn->prepare("UPDATE markets SET status = 'active' WHERE id = ?");
                    $stmt->execute([$market_id]);
                    $message = "Market activated successfully!";
                } catch (Exception $e) {
                    $error = "Error activating market: " . $e->getMessage();
                }
            }
            break;
            
        case 'add_market':
            $market_name = $_POST['market_name'] ?? '';
            $address = $_POST['address'] ?? '';
            $latitude = $_POST['latitude'] ?? '';
            $longitude = $_POST['longitude'] ?? '';
            $contact_number = $_POST['contact_number'] ?? '';
            $operating_hours = $_POST['operating_hours'] ?? '';
            
            if ($market_name && $address && $latitude && $longitude) {
                try {
                    $stmt = $conn->prepare("INSERT INTO markets (market_name, address, latitude, longitude, contact_number, operating_hours, market_type, status) VALUES (?, ?, ?, ?, ?, ?, 'public', 'active')");
                    $stmt->execute([$market_name, $address, $latitude, $longitude, $contact_number, $operating_hours]);
                    $message = "New market added successfully!";
                } catch (Exception $e) {
                    $error = "Error adding market: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in all required fields.";
            }
            break;
            
        case 'edit_market':
            $market_id = $_POST['market_id'] ?? '';
            $market_name = $_POST['market_name'] ?? '';
            $address = $_POST['address'] ?? '';
            $latitude = $_POST['latitude'] ?? '';
            $longitude = $_POST['longitude'] ?? '';
            $contact_number = $_POST['contact_number'] ?? '';
            $operating_hours = $_POST['operating_hours'] ?? '';
            
            if ($market_id && $market_name && $address && $latitude && $longitude) {
                try {
                    $stmt = $conn->prepare("UPDATE markets SET market_name = ?, address = ?, latitude = ?, longitude = ?, contact_number = ?, operating_hours = ? WHERE id = ?");
                    $stmt->execute([$market_name, $address, $latitude, $longitude, $contact_number, $operating_hours, $market_id]);
                    $message = "Market updated successfully!";
                } catch (Exception $e) {
                    $error = "Error updating market: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in all required fields.";
            }
            break;
            
        case 'update_user':
            $user_id = $_POST['user_id'] ?? '';
            $user_role = $_POST['user_role'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Prevent admin from changing their own role or deactivating themselves
            if ($user_id == $_SESSION['user_id']) {
                $error = "You cannot modify your own account.";
            } elseif ($user_id && $user_role) {
                try {
                    // Validate role
                    $valid_roles = ['consumer', 'farmer', 'admin', 'super_admin'];
                    if (!in_array($user_role, $valid_roles)) {
                        $error = "Invalid user role.";
                    } else {
                        $target_stmt = $conn->prepare("SELECT user_role FROM users WHERE id = ?");
                        $target_stmt->execute([$user_id]);
                        $target_user = $target_stmt->fetch(PDO::FETCH_ASSOC);

                        if ($target_user && $target_user['user_role'] === 'super_admin' && !isSuperAdmin()) {
                            $error = "Only the Super Admin can modify a Super Admin account.";
                        } elseif ($user_role === 'super_admin' && !isSuperAdmin()) {
                            $error = "Only the Super Admin can assign the Super Admin role.";
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET user_role = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$user_role, $is_active, $user_id]);
                            $message = "User updated successfully!";
                        }
                    }
                } catch (Exception $e) {
                    $error = "Error updating user: " . $e->getMessage();
                }
            } else {
                $error = "Please fill in all required fields.";
            }
            break;
            
        case 'assign_market_owner':
            $market_id = $_POST['market_id'] ?? '';
            $farmer_id = $_POST['farmer_id'] ?? '';
            
            if ($market_id) {
                try {
                    // If farmer_id is empty, unassign the owner
                    if (empty($farmer_id)) {
                        $stmt = $conn->prepare("UPDATE markets SET vendor_id = NULL WHERE id = ?");
                        $stmt->execute([$market_id]);
                        $message = "Market owner unassigned successfully!";
                    } else {
                        // Verify the user is a farmer
                        $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND user_role = 'farmer'");
                        $check_stmt->execute([$farmer_id]);
                        if ($check_stmt->fetch()) {
                            $stmt = $conn->prepare("UPDATE markets SET vendor_id = ? WHERE id = ?");
                            $stmt->execute([$farmer_id, $market_id]);
                            $message = "Market owner assigned successfully!";
                        } else {
                            $error = "Selected user is not a farmer.";
                        }
                    }
                } catch (Exception $e) {
                    $error = "Error assigning market owner: " . $e->getMessage();
                }
            } else {
                $error = "Market ID is required.";
            }
            break;
    }
}

// Redirect back to admin dashboard with message
$redirect_url = 'admin-console.php';
$hash = '';

// Add hash for DTI admin actions to show the correct section
if (in_array($_POST['action'] ?? '', ['create_dti_admin', 'promote_to_admin', 'demote_to_consumer', 'activate_user', 'deactivate_user'])) {
    $hash = '#dti-admins-view';
}

if ($message) {
    $redirect_url .= '?message=' . urlencode($message);
} elseif ($error) {
    $redirect_url .= '?error=' . urlencode($error);
}

$redirect_url .= $hash;

header("Location: $redirect_url");
exit;
?>
