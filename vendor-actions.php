<?php
require_once 'includes/enhanced_functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || $_SESSION['user_role'] !== 'farmer') {
    header('Location: login.php?error=' . urlencode('Access denied. Farmer access required.'));
    exit;
}

$farmer_id = $_SESSION['user_id'];
$conn = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_farmer':
            $application_id = intval($_POST['application_id'] ?? 0);
            $market_id = intval($_POST['market_id'] ?? 0);
            $farmer_id = intval($_POST['farmer_id'] ?? 0);
            
            if ($application_id && $market_id && $farmer_id) {
                try {
                    // Start transaction
                    $conn->beginTransaction();
                    
                    // Update application status
                    $stmt = $conn->prepare("
                        UPDATE farmer_applications 
                        SET status = 'approved', 
                            approved_at = NOW(),
                            approved_by = ?
                        WHERE id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$farmer_id, $application_id]);
                    
                    // Add farmer to market_farmers table
                    $stmt2 = $conn->prepare("
                        INSERT INTO market_farmers 
                        (market_id, farmer_id, approval_status, approved_at, approved_by, monthly_fee)
                        VALUES (?, ?, 'approved', NOW(), ?, 500.00)
                        ON DUPLICATE KEY UPDATE 
                            approval_status = 'approved',
                            approved_at = NOW(),
                            approved_by = ?
                    ");
                    $stmt2->execute([$market_id, $farmer_id, $farmer_id, $farmer_id]);
                    
                    $conn->commit();
                    
                    header('Location: farmer-dashboard.php?message=' . urlencode('Farmer approved successfully!'));
                    exit;
                } catch (Exception $e) {
                    $conn->rollBack();
                    header('Location: farmer-dashboard.php?error=' . urlencode('Error approving farmer: ' . $e->getMessage()));
                    exit;
                }
            }
            break;
            
        case 'reject_farmer':
            $application_id = intval($_POST['application_id'] ?? 0);
            $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? 'Application rejected by market coordinator');
            
            if ($application_id) {
                try {
                    $stmt = $conn->prepare("
                        UPDATE farmer_applications 
                        SET status = 'rejected',
                            rejection_reason = ?,
                            approved_by = ?
                        WHERE id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$rejection_reason, $farmer_id, $application_id]);
                    
                    header('Location: farmer-dashboard.php?message=' . urlencode('Application rejected.'));
                    exit;
                } catch (Exception $e) {
                    header('Location: farmer-dashboard.php?error=' . urlencode('Error rejecting application: ' . $e->getMessage()));
                    exit;
                }
            }
            break;
            
        case 'update_market_hours':
            $market_id = intval($_POST['market_id'] ?? 0);
            $opening_time = sanitizeInput($_POST['opening_time'] ?? '06:00');
            $closing_time = sanitizeInput($_POST['closing_time'] ?? '18:00');
            $operating_days = isset($_POST['operating_days']) ? implode(',', $_POST['operating_days']) : 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
            
            if ($market_id) {
                try {
                    // Verify vendor owns this market
                    $check = $conn->prepare("SELECT vendor_id FROM markets WHERE id = ?");
                    $check->execute([$market_id]);
                    $market = $check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($market && $market['vendor_id'] == $farmer_id) {
                        $stmt = $conn->prepare("
                            UPDATE markets 
                            SET opening_time = ?,
                                closing_time = ?,
                                operating_days = ?
                            WHERE id = ? AND vendor_id = ?
                        ");
                        $stmt->execute([$opening_time, $closing_time, $operating_days, $market_id, $farmer_id]);
                        
                        header('Location: farmer-dashboard.php?message=' . urlencode('Market hours updated successfully!'));
                        exit;
                    } else {
                        header('Location: farmer-dashboard.php?error=' . urlencode('You do not have permission to update this market.'));
                        exit;
                    }
                } catch (Exception $e) {
                    header('Location: farmer-dashboard.php?error=' . urlencode('Error updating market hours: ' . $e->getMessage()));
                    exit;
                }
            }
            break;
            
        case 'toggle_market_status':
            $market_id = intval($_POST['market_id'] ?? 0);
            // Get the actual value (0 or 1) from the form
            $is_open = intval($_POST['is_open'] ?? 0);
            
            if ($market_id) {
                try {
                    // Verify vendor owns this market
                    $check = $conn->prepare("SELECT vendor_id FROM markets WHERE id = ?");
                    $check->execute([$market_id]);
                    $market = $check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($market && $market['vendor_id'] == $farmer_id) {
                        $stmt = $conn->prepare("
                            UPDATE markets 
                            SET is_open = ?
                            WHERE id = ? AND vendor_id = ?
                        ");
                        $stmt->execute([$is_open, $market_id, $farmer_id]);
                        
                        $status_text = $is_open ? 'open' : 'closed';
                        header('Location: farmer-dashboard.php?message=' . urlencode("Market marked as $status_text!"));
                        exit;
                    } else {
                        header('Location: farmer-dashboard.php?error=' . urlencode('You do not have permission to update this market.'));
                        exit;
                    }
                } catch (Exception $e) {
                    header('Location: farmer-dashboard.php?error=' . urlencode('Error updating market status: ' . $e->getMessage()));
                    exit;
                }
            }
            break;
            
        default:
            header('Location: farmer-dashboard.php?error=' . urlencode('Invalid action'));
            exit;
    }
} else {
    header('Location: farmer-dashboard.php');
    exit;
}
