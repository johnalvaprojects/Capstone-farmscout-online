<?php
/**
 * Minimal admin console — markets, products, reservations, reference prices, users.
 * Access: admin / super_admin only (same as legacy dashboard).
 */
require_once __DIR__ . '/includes/enhanced_functions.php';

if (!isLoggedIn() || !isAdminUser()) {
    header('Location: login.php?error=' . urlencode('Access denied. Admin access required.'));
    exit;
}

$conn = getDB();
if (!$conn) {
    die('Database connection failed.');
}

function fs_admin_has_column(PDO $pdo, string $table, string $column): bool {
    try {
        $s = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
        return $s && $s->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function fs_admin_ensure_reference_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_product_reference_prices` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_name` varchar(255) NOT NULL,
      `category` varchar(100) DEFAULT NULL,
      `unit` varchar(80) DEFAULT NULL,
      `ref_price_min` decimal(10,2) NOT NULL DEFAULT 0.00,
      `ref_price_max` decimal(10,2) NOT NULL DEFAULT 0.00,
      `notes` varchar(255) DEFAULT NULL,
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_ref_combo` (`product_name`(120), `category`(40), `unit`(40))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$hasMpDeleted = fs_admin_has_column($conn, 'market_products', 'deleted_at');
$hasMpApproval = fs_admin_has_column($conn, 'market_products', 'approval_status');

fs_admin_ensure_reference_table($conn);

$flash = '';
$flashType = 'info';

$tab = $_GET['tab'] ?? 'markets';
$allowedTabs = ['markets', 'products', 'reservations', 'references', 'users'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'markets';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $flash = 'Invalid security token. Please try again.';
        $flashType = 'error';
    } else {
        $action = $_POST['action'];
        try {
            switch ($action) {
                case 'market_add': {
                    $name = sanitizeInput($_POST['market_name'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $lat = trim((string)($_POST['latitude'] ?? ''));
                    $lng = trim((string)($_POST['longitude'] ?? ''));
                    if ($name === '' || $address === '') {
                        $flash = 'Market name and location (address) are required.';
                        $flashType = 'error';
                        break;
                    }
                    $latDec = $lat !== '' ? (float)$lat : 16.61590000;
                    $lngDec = $lng !== '' ? (float)$lng : 120.31660000;
                    $stmt = $conn->prepare(
                        'INSERT INTO markets (market_name, address, latitude, longitude, status, created_at, updated_at)
                         VALUES (?, ?, ?, ?, \'active\', NOW(), NOW())'
                    );
                    $stmt->execute([$name, $address, $latDec, $lngDec]);
                    $flash = 'Market added.';
                    $flashType = 'ok';
                    break;
                }
                case 'market_edit': {
                    $id = (int)($_POST['market_id'] ?? 0);
                    $name = sanitizeInput($_POST['market_name'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $lat = trim((string)($_POST['latitude'] ?? ''));
                    $lng = trim((string)($_POST['longitude'] ?? ''));
                    if ($id <= 0 || $name === '' || $address === '') {
                        $flash = 'Invalid market data.';
                        $flashType = 'error';
                        break;
                    }
                    $latDec = $lat !== '' ? (float)$lat : 16.61590000;
                    $lngDec = $lng !== '' ? (float)$lng : 120.31660000;
                    $stmt = $conn->prepare(
                        'UPDATE markets SET market_name = ?, address = ?, latitude = ?, longitude = ?, updated_at = NOW() WHERE id = ?'
                    );
                    $stmt->execute([$name, $address, $latDec, $lngDec, $id]);
                    $flash = 'Market updated.';
                    $flashType = 'ok';
                    break;
                }
                case 'market_delete': {
                    $id = (int)($_POST['market_id'] ?? 0);
                    if ($id <= 0) {
                        break;
                    }
                    $stc = $conn->prepare('SELECT COUNT(*) FROM market_products WHERE market_id = ?');
                    $stc->execute([$id]);
                    $cnt = (int)$stc->fetchColumn();
                    $str = $conn->prepare('SELECT COUNT(*) FROM reservations WHERE market_id = ?');
                    $str->execute([$id]);
                    $cntR = (int)$str->fetchColumn();
                    if ($cnt + $cntR > 0) {
                        $conn->prepare("UPDATE markets SET status = 'inactive' WHERE id = ?")->execute([$id]);
                        $flash = 'Market has linked data — marked inactive instead of deleting.';
                    } else {
                        $conn->prepare('DELETE FROM markets WHERE id = ?')->execute([$id]);
                        $flash = 'Market deleted.';
                    }
                    $flashType = 'ok';
                    break;
                }
                case 'product_delete': {
                    $pid = (int)($_POST['product_id'] ?? 0);
                    if ($pid <= 0) {
                        break;
                    }
                    if ($hasMpDeleted) {
                        $conn->prepare('UPDATE market_products SET deleted_at = NOW() WHERE id = ?')->execute([$pid]);
                        $flash = 'Product listing removed (soft delete).';
                    } else {
                        $conn->prepare('DELETE FROM market_product_units WHERE product_id = ?')->execute([$pid]);
                        try {
                            $conn->prepare('DELETE FROM market_products WHERE id = ?')->execute([$pid]);
                        } catch (Throwable $e) {
                            $conn->prepare('UPDATE market_products SET is_available = 0 WHERE id = ?')->execute([$pid]);
                            $flash = 'Product could not be fully deleted (linked records). Marked unavailable.';
                            $flashType = 'error';
                            break;
                        }
                        $flash = 'Product listing deleted.';
                    }
                    $flashType = 'ok';
                    break;
                }
                case 'reference_save': {
                    $pname = fsCanonicalizeProductName(sanitizeInput($_POST['product_name'] ?? ''));
                    $cat = sanitizeInput($_POST['category'] ?? '');
                    $unit = fsCanonicalizeUnitLabel(sanitizeInput($_POST['unit'] ?? ''));
                    $min = (float)($_POST['ref_price_min'] ?? 0);
                    $max = (float)($_POST['ref_price_max'] ?? 0);
                    $notes = sanitizeInput($_POST['notes'] ?? '');
                    if ($pname === '') {
                        $flash = 'Product name is required.';
                        $flashType = 'error';
                        break;
                    }
                    if ($min < 0 || $max < 0 || ($max > 0 && $min > $max)) {
                        $flash = 'Enter valid min/max prices (max must be ≥ min).';
                        $flashType = 'error';
                        break;
                    }
                    $catNull = $cat === '' ? null : $cat;
                    $unitNull = $unit === '' ? null : $unit;
                    $find = $conn->prepare(
                        'SELECT id FROM admin_product_reference_prices
                         WHERE product_name = ? AND IFNULL(category,\'\') = IFNULL(?,\'\') AND IFNULL(unit,\'\') = IFNULL(?,\'\') LIMIT 1'
                    );
                    $find->execute([$pname, $catNull, $unitNull]);
                    $row = $find->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $u = $conn->prepare(
                            'UPDATE admin_product_reference_prices SET ref_price_min = ?, ref_price_max = ?, notes = ?, updated_at = NOW() WHERE id = ?'
                        );
                        $u->execute([$min, $max, $notes ?: null, $row['id']]);
                    } else {
                        $ins = $conn->prepare(
                            'INSERT INTO admin_product_reference_prices (product_name, category, unit, ref_price_min, ref_price_max, notes)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $ins->execute([$pname, $catNull, $unitNull, $min, $max, $notes ?: null]);
                    }
                    $flash = 'Reference prices saved.';
                    $flashType = 'ok';
                    $tab = 'references';
                    break;
                }
                case 'reference_delete': {
                    $rid = (int)($_POST['ref_id'] ?? 0);
                    if ($rid > 0) {
                        $conn->prepare('DELETE FROM admin_product_reference_prices WHERE id = ?')->execute([$rid]);
                        $flash = 'Reference row removed.';
                        $flashType = 'ok';
                    }
                    $tab = 'references';
                    break;
                }
                default:
                    break;
            }
        } catch (Throwable $e) {
            error_log('admin-console: ' . $e->getMessage());
            $flash = 'Action failed: ' . htmlspecialchars($e->getMessage());
            $flashType = 'error';
        }
    }
}

if ($flash === '' && isset($_GET['message'])) {
    $flash = trim((string)$_GET['message']);
    $flashType = 'ok';
}

// --- Data loads ---
$markets = [];
try {
    $markets = $conn->query('SELECT id, market_name, address, latitude, longitude, status FROM markets ORDER BY market_name ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $markets = [];
}

$editMarketId = isset($_GET['edit_market']) ? (int)$_GET['edit_market'] : 0;
$editMarket = null;
if ($editMarketId > 0) {
    $st = $conn->prepare('SELECT * FROM markets WHERE id = ? LIMIT 1');
    $st->execute([$editMarketId]);
    $editMarket = $st->fetch(PDO::FETCH_ASSOC);
}

$productsSql = 'SELECT mp.id, mp.market_id, mp.farmer_id, mp.product_name, mp.category, mp.price, mp.unit, mp.is_available,
                       m.market_name, u.username AS farmer_username
                FROM market_products mp
                INNER JOIN markets m ON m.id = mp.market_id
                INNER JOIN users u ON u.id = mp.farmer_id';
if ($hasMpDeleted) {
    $productsSql .= ' WHERE mp.deleted_at IS NULL';
}
$productsSql .= ' ORDER BY mp.id DESC LIMIT 500';
$products = $conn->query($productsSql)->fetchAll(PDO::FETCH_ASSOC);

$refs = $conn->query('SELECT * FROM admin_product_reference_prices ORDER BY product_name ASC')->fetchAll(PDO::FETCH_ASSOC);
$refMap = [];
foreach ($refs as $r) {
    $k = strtolower(trim($r['product_name'])) . '|' . strtolower(trim((string)($r['category'] ?? ''))) . '|' . strtolower(trim((string)($r['unit'] ?? '')));
    $refMap[$k] = $r;
}

$resStatus = $_GET['res_status'] ?? '';
$resMarket = isset($_GET['res_market']) ? (int)$_GET['res_market'] : 0;
$resSql = 'SELECT r.*, m.market_name, mp.product_name AS listing_name, uc.username AS consumer_name, uf.username AS farmer_name
           FROM reservations r
           INNER JOIN markets m ON m.id = r.market_id
           INNER JOIN users uc ON uc.id = r.user_id
           INNER JOIN users uf ON uf.id = r.farmer_id
           LEFT JOIN market_products mp ON mp.id = r.product_id';
$where = ['1=1'];
$params = [];
if ($resStatus !== '' && in_array($resStatus, ['pending', 'confirmed', 'paid', 'completed', 'cancelled'], true)) {
    $where[] = 'r.status = ?';
    $params[] = $resStatus;
}
if ($resMarket > 0) {
    $where[] = 'r.market_id = ?';
    $params[] = $resMarket;
}
$resSql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT 400';
$rst = $conn->prepare($resSql);
$rst->execute($params);
$reservations = $rst->fetchAll(PDO::FETCH_ASSOC);

$distinctProducts = [];
try {
    $dSql = 'SELECT DISTINCT mp.product_name, mp.category, mp.unit FROM market_products mp';
    if ($hasMpDeleted) {
        $dSql .= ' WHERE mp.deleted_at IS NULL';
    }
    $dSql .= ' ORDER BY mp.product_name ASC LIMIT 300';
    $distinctProducts = $conn->query($dSql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $distinctProducts = [];
}

$categories = [];
try {
    $categories = function_exists('getCategories') ? getCategories() : [];
} catch (Throwable $e) {
    $categories = [];
}

$usersList = $conn->query(
    'SELECT id, username, email, full_name, user_role, is_active, created_at FROM users ORDER BY id DESC LIMIT 400'
)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Admin Console — FarmScout Online';
$csrf = getCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#f6f2e8;--main2:#f2ecdf;
            --surf:#ffffff;--surf2:#fbf7ee;--surf3:#f3edde;
            --bdr:rgba(31,42,34,.10);
            --grn:#1f7a3a;--grn2:#2f8f4e;--grn-dim:rgba(31,122,58,.10);
            --txt:#1f2a22;--mut:rgba(31,42,34,.55);
            --red:#b91c1c;--yel:#b45309;--blu:#1d4ed8;
            --rad:14px;--tr:.2s cubic-bezier(.4,0,.2,1);
            --sw:230px;--shd:0 16px 46px rgba(17,24,39,.12);
        }
        body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;-webkit-font-smoothing:antialiased;font-size:14px;line-height:1.5}
        ::-webkit-scrollbar{width:5px;height:5px}
        ::-webkit-scrollbar-track{background:var(--surf)}
        ::-webkit-scrollbar-thumb{background:var(--surf3);border-radius:3px}

        /* ── LAYOUT ── */
        .ac-layout{display:flex;min-height:100vh}

        /* ── SIDEBAR ── */
        .ac-sidebar{
            width:var(--sw);min-width:var(--sw);
            background:linear-gradient(180deg,#163323 0%,#0f251a 100%);
            border-right:1px solid rgba(0,0,0,.06);
            display:flex;flex-direction:column;
            position:fixed;top:0;left:0;bottom:0;z-index:200;
            overflow-y:auto;overflow-x:hidden;
        }
        .ac-sidebar::-webkit-scrollbar{width:3px}
        .ac-sidebar__logo{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;flex-shrink:0}
        .ac-sidebar__mark{width:34px;height:34px;border-radius:8px;background:#c7f2d6;display:grid;place-items:center;font-weight:900;font-size:13px;color:#0f251a;flex-shrink:0;letter-spacing:-.04em}
        .ac-sidebar__brand{font-size:13px;font-weight:800;color:#eef7f0;line-height:1.2}
        .ac-sidebar__brand small{display:block;font-size:10px;color:rgba(255,255,255,.55);font-weight:400}
        .ac-sidebar__nav{padding:10px 8px;flex:1}
        .ac-sidebar__ns{font-size:9px;font-weight:800;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.12em;padding:10px 10px 4px}
        .ac-sidebar__link{
            display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:10px;
            text-decoration:none;color:rgba(255,255,255,.72);font-size:13px;font-weight:600;margin-bottom:2px;
            transition:all var(--tr);cursor:pointer;border:none;background:none;width:100%;text-align:left;font-family:inherit;
        }
        .ac-sidebar__link svg{width:15px;height:15px;flex-shrink:0;opacity:.8;transition:opacity var(--tr)}
        .ac-sidebar__link:hover{background:rgba(199,242,214,.12);color:#eef7f0}
        .ac-sidebar__link:hover svg{opacity:1}
        .ac-sidebar__link.act{background:rgba(199,242,214,.18);color:#c7f2d6;font-weight:800;box-shadow:inset 0 0 0 1px rgba(199,242,214,.22);position:relative}
        .ac-sidebar__link.act::before{content:'';position:absolute;left:0;top:8px;bottom:8px;width:3px;background:#c7f2d6;border-radius:0 3px 3px 0}
        .ac-sidebar__link.act svg{opacity:1}
        .ac-sidebar__foot{padding:10px 12px;border-top:1px solid rgba(255,255,255,.07)}
        .ac-sidebar__foot a{display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,.65);text-decoration:none;padding:8px 10px;border-radius:10px;transition:all var(--tr)}
        .ac-sidebar__foot a:hover{color:#eef7f0;background:rgba(199,242,214,.10)}
        .ac-sidebar__foot a svg{width:13px;height:13px;flex-shrink:0}

        /* ── MAIN ── */
        .ac-main{margin-left:var(--sw);flex:1;min-height:100vh;background:radial-gradient(800px 400px at 20% 10%,rgba(31,122,58,.09),transparent 65%),linear-gradient(180deg,var(--bg) 0%,var(--main2) 100%)}
        .ac-topbar{
            display:flex;align-items:center;justify-content:space-between;gap:12px;
            padding:16px 28px;border-bottom:1px solid var(--bdr);
            position:sticky;top:0;z-index:100;
            background:rgba(246,242,232,.90);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
        }
        .ac-topbar__title{font-size:19px;font-weight:900;color:var(--txt);letter-spacing:-.025em}
        .ac-topbar__sub{font-size:12px;color:var(--mut);margin-top:1px}
        .ac-topbar__right{display:flex;gap:8px;align-items:center}
        .ac-body{padding:28px 30px 70px}

        /* ── FLASH ── */
        .ac-flash{padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px}
        .ac-flash.ok{background:rgba(31,122,58,.10);color:var(--grn);border:1px solid rgba(31,122,58,.22)}
        .ac-flash.error{background:rgba(185,28,28,.08);color:var(--red);border:1px solid rgba(185,28,28,.20)}
        .ac-flash.info{background:rgba(0,0,0,.04);border:1px solid var(--bdr)}

        /* ── CARDS ── */
        .ac-card{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--rad);padding:20px 22px 24px;margin-bottom:18px;box-shadow:0 2px 8px rgba(17,24,39,.04)}
        .ac-card__title{font-size:15px;font-weight:900;color:var(--txt);margin-bottom:16px;letter-spacing:-.015em;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
        .ac-card__sub{font-size:12px;color:var(--mut);margin-top:-10px;margin-bottom:14px}

        /* ── TABLE ── */
        .ac-table-wrap{overflow-x:auto;border-radius:var(--rad);border:1px solid var(--bdr)}
        table.ac-table{width:100%;border-collapse:collapse;font-size:13px}
        table.ac-table thead tr{background:var(--surf2)}
        table.ac-table th{padding:11px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--mut);white-space:nowrap}
        table.ac-table td{padding:14px 14px;border-top:1px solid rgba(74,222,128,.08);border-bottom:1px solid rgba(0,0,0,.06);color:var(--txt);vertical-align:middle}
        table.ac-table tbody tr:nth-child(even){background:rgba(251,247,238,.92)}
        table.ac-table tbody tr:nth-child(odd){background:#ffffff}
        table.ac-table tbody tr:hover{background:rgba(31,122,58,.05)}

        /* ── BADGES ── */
        .ac-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
        .ac-badge--fair{background:rgba(31,122,58,.12);color:var(--grn);border:1px solid rgba(31,122,58,.20)}
        .ac-badge--low{background:rgba(29,78,216,.10);color:var(--blu);border:1px solid rgba(29,78,216,.18)}
        .ac-badge--high{background:rgba(185,28,28,.10);color:var(--red);border:1px solid rgba(185,28,28,.18)}
        .ac-badge--na{background:rgba(0,0,0,.05);color:var(--mut);border:1px solid rgba(0,0,0,.08)}
        .ac-badge--active{background:rgba(31,122,58,.12);color:var(--grn);border:1px solid rgba(31,122,58,.20)}
        .ac-badge--inactive{background:rgba(0,0,0,.05);color:var(--mut);border:1px solid rgba(0,0,0,.08)}

        /* ── FORM ── */
        .ac-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;align-items:end}
        .ac-field label{display:block;font-size:11px;font-weight:700;color:var(--mut);margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
        .ac-field input,.ac-field select,.ac-field textarea{
            width:100%;padding:11px 13px;border:1px solid var(--bdr);border-radius:10px;font:inherit;
            background:var(--surf2);color:var(--txt);font-size:13px;transition:border-color var(--tr),box-shadow var(--tr);min-height:44px
        }
        .ac-field input:focus,.ac-field select:focus,.ac-field textarea:focus{outline:none;border-color:var(--grn);box-shadow:0 0 0 3px rgba(31,122,58,.10)}
        .ac-field textarea{min-height:74px;resize:vertical}

        /* ── BUTTONS ── */
        .ac-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;border:1px solid var(--bdr);background:var(--surf);color:var(--txt);text-decoration:none;transition:all var(--tr);white-space:nowrap;font-family:inherit}
        .ac-btn:hover{border-color:rgba(31,42,34,.20);box-shadow:0 2px 8px rgba(0,0,0,.08)}
        .ac-btn--primary{background:var(--grn);color:#fff;border-color:var(--grn)}
        .ac-btn--primary:hover{background:var(--grn2);border-color:var(--grn2)}
        .ac-btn--danger{color:var(--red);border-color:rgba(185,28,28,.30);background:rgba(185,28,28,.06)}
        .ac-btn--danger:hover{background:rgba(185,28,28,.12)}
        .ac-btn--sm{padding:7px 12px;font-size:12px;border-radius:8px}
        .ac-btn--ghost{background:transparent;border-color:var(--bdr);color:var(--mut)}

        /* ── MISC ── */
        .ac-muted{color:var(--mut);font-size:12px}
        .ac-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .ac-strong{font-size:13px;font-weight:700;color:var(--txt)}
        .ac-small{font-size:12px;color:var(--mut)}

        @media(max-width:860px){
            .ac-sidebar{transform:translateX(-100%);transition:transform .3s ease}
            .ac-sidebar.mob-open{transform:none}
            .ac-main{margin-left:0}
            .ac-topbar{padding:14px 16px}
            .ac-body{padding:16px 14px 50px}
            .ac-mob-toggle{display:flex}
        }
        .ac-mob-toggle{display:none;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9px;border:1px solid var(--bdr);background:var(--surf);cursor:pointer;margin-right:10px}
        .ac-mob-toggle svg{width:18px;height:18px}
        .ac-mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:150}
        .ac-mob-overlay.open{display:block}
    </style>
</head>
<body>
<?php
$tabLabels = [
    'markets'      => ['icon' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l7-7 7 7v9a1 1 0 01-1 1H4a1 1 0 01-1-1V9z"/><path d="M8 19V12h4v7"/></svg>', 'label' => 'Markets'],
    'products'     => ['icon' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="16" height="12" rx="2"/><path d="M8 4V2M12 4V2"/><path d="M6 10h8M6 13h5"/></svg>', 'label' => 'Products'],
    'reservations' => ['icon' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="14" height="14" rx="2"/><path d="M3 8h14M8 2v4M12 2v4"/></svg>', 'label' => 'Reservations'],
    'references'   => ['icon' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 2v16M5 7h4M5 11h6M5 15h8"/><circle cx="14" cy="5" r="3"/></svg>', 'label' => 'Ref. Prices'],
    'users'        => ['icon' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="7" r="3"/><path d="M3 17a7 7 0 0114 0"/></svg>', 'label' => 'Users'],
];
$currentLabel = $tabLabels[$tab]['label'] ?? 'Admin';
?>
<div class="ac-mob-overlay" id="acOverlay"></div>
<div class="ac-layout">

    <!-- SIDEBAR -->
    <aside class="ac-sidebar" id="acSidebar">
        <div class="ac-sidebar__logo">
            <div class="ac-sidebar__mark">FS</div>
            <div class="ac-sidebar__brand">FarmScout<small>Admin Console</small></div>
        </div>
        <nav class="ac-sidebar__nav">
            <div class="ac-sidebar__ns">Navigation</div>
            <?php foreach ($allowedTabs as $t): ?>
                <a class="ac-sidebar__link <?php echo $tab === $t ? 'act' : ''; ?>"
                   href="admin-console.php?tab=<?php echo urlencode($t); ?>">
                    <?php echo $tabLabels[$t]['icon'] ?? ''; ?>
                    <?php echo htmlspecialchars($tabLabels[$t]['label'] ?? $t); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="ac-sidebar__foot">
            <a href="logout.php">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 3h4v14h-4M8 14l4-4-4-4M3 10h9"/></svg>
                Sign out
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="ac-main">
        <div class="ac-topbar">
            <div style="display:flex;align-items:center;gap:0">
                <button class="ac-mob-toggle" id="acMobToggle" aria-label="Menu">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
                </button>
                <div>
                    <div class="ac-topbar__title"><?php echo htmlspecialchars($currentLabel); ?></div>
                    <div class="ac-topbar__sub">Admin console &rsaquo; <?php echo htmlspecialchars($currentLabel); ?></div>
                </div>
            </div>
            <div class="ac-topbar__right">
                <span class="ac-muted" style="font-size:12px;font-weight:600"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <a class="ac-btn ac-btn--sm ac-btn--ghost" href="logout.php">Sign out</a>
            </div>
        </div>
        <div class="ac-body">

    <?php if ($flash !== ''): ?>
        <div class="ac-flash <?php echo $flashType === 'ok' ? 'ok' : ($flashType === 'error' ? 'error' : 'info'); ?>">
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'markets'): ?>
        <div class="ac-card">
            <div class="ac-card__title"><?php echo $editMarket ? 'Edit market' : 'Add market'; ?></div>
            <form method="post" class="ac-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="<?php echo $editMarket ? 'market_edit' : 'market_add'; ?>">
                <?php if ($editMarket): ?>
                    <input type="hidden" name="market_id" value="<?php echo (int)$editMarket['id']; ?>">
                <?php endif; ?>
                <div class="ac-field" style="grid-column: span 2;">
                    <label>Name</label>
                    <input type="text" name="market_name" required maxlength="255"
                           value="<?php echo htmlspecialchars($editMarket['market_name'] ?? ''); ?>">
                </div>
                <div class="ac-field" style="grid-column: 1 / -1;">
                    <label>Location (address)</label>
                    <textarea name="address" rows="2" required><?php echo htmlspecialchars($editMarket['address'] ?? ''); ?></textarea>
                </div>
                <div class="ac-field">
                    <label>Latitude</label>
                    <input type="text" name="latitude" placeholder="16.6159"
                           value="<?php echo htmlspecialchars(isset($editMarket['latitude']) ? (string)$editMarket['latitude'] : ''); ?>">
                </div>
                <div class="ac-field">
                    <label>Longitude</label>
                    <input type="text" name="longitude" placeholder="120.3166"
                           value="<?php echo htmlspecialchars(isset($editMarket['longitude']) ? (string)$editMarket['longitude'] : ''); ?>">
                </div>
                <div class="ac-field">
                    <button type="submit" class="ac-btn ac-btn--primary"><?php echo $editMarket ? 'Save changes' : 'Add market'; ?></button>
                    <?php if ($editMarket): ?>
                        <a class="ac-btn ac-btn--sm" href="admin-console.php?tab=markets" style="margin-left:8px;">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
            <p class="ac-muted" style="margin-top:12px;">Leave latitude/longitude blank to use a default map point (La Union area).</p>
        </div>

        <div class="ac-card">
            <div class="ac-card__title">All markets</div>
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($markets as $m): ?>
                        <tr>
                            <td><?php echo (int)$m['id']; ?></td>
                            <td><?php echo htmlspecialchars($m['market_name']); ?></td>
                            <td><?php echo htmlspecialchars(mb_substr($m['address'], 0, 120)); ?><?php echo mb_strlen($m['address']) > 120 ? '…' : ''; ?></td>
                            <td><?php $ms = $m['status'] ?? 'active'; echo '<span class="ac-badge ac-badge--' . ($ms === 'active' ? 'active' : 'inactive') . '">' . htmlspecialchars($ms) . '</span>'; ?></td>
                            <td class="ac-actions">
                                <a class="ac-btn ac-btn--sm" href="admin-console.php?tab=markets&amp;edit_market=<?php echo (int)$m['id']; ?>">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete or deactivate this market?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="action" value="market_delete">
                                    <input type="hidden" name="market_id" value="<?php echo (int)$m['id']; ?>">
                                    <button type="submit" class="ac-btn ac-btn--sm ac-btn--danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($tab === 'products'): ?>
        <div class="ac-card">
            <div class="ac-card__title">All farmer listings</div>
            <p class="ac-muted">Compared to reference bands when name + category + unit match.</p>
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Market</th>
                        <th>Farmer</th>
                        <th>Price</th>
                        <th>Fair band</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $rk = strtolower(trim($p['product_name'])) . '|' . strtolower(trim((string)($p['category'] ?? ''))) . '|' . strtolower(trim((string)($p['unit'] ?? '')));
                        $ref = $refMap[$rk] ?? null;
                        $price = (float)$p['price'];
                        $badge = '<span class="ac-badge ac-badge--na">No band</span>';
                        if ($ref && (float)$ref['ref_price_max'] > 0) {
                            $minR = (float)$ref['ref_price_min'];
                            $maxR = (float)$ref['ref_price_max'];
                            if ($price >= $minR && $price <= $maxR) {
                                $badge = '<span class="ac-badge ac-badge--fair">Within band</span>';
                            } elseif ($price < $minR) {
                                $badge = '<span class="ac-badge ac-badge--low">Below min</span>';
                            } else {
                                $badge = '<span class="ac-badge ac-badge--high">Above max</span>';
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo (int)$p['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['product_name']); ?></strong><br>
                                <span class="ac-muted"><?php echo htmlspecialchars((string)($p['category'] ?? '')); ?> · <?php echo htmlspecialchars((string)($p['unit'] ?? '')); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($p['market_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['farmer_username']); ?></td>
                            <td>₱<?php echo number_format($price, 2); ?></td>
                            <td><?php echo $badge; ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Remove this listing?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="action" value="product_delete">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="ac-btn ac-btn--sm ac-btn--danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($tab === 'reservations'): ?>
        <div class="ac-card">
            <div class="ac-card__title">All reservations</div>
            <form method="get" class="ac-form-grid" style="margin-bottom:14px;">
                <input type="hidden" name="tab" value="reservations">
                <div class="ac-field">
                    <label>Status</label>
                    <select name="res_status">
                        <option value="">All</option>
                        <?php foreach (['pending','confirmed','paid','completed','cancelled'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $resStatus === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ac-field">
                    <label>Market</label>
                    <select name="res_market">
                        <option value="0">All markets</option>
                        <?php foreach ($markets as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>" <?php echo $resMarket === (int)$m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ac-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="ac-btn ac-btn--primary">Apply</button>
                </div>
            </form>
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Consumer</th>
                        <th>Farmer</th>
                        <th>Market</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Created</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <tr>
                            <td><?php echo (int)$r['id']; ?></td>
                            <td><?php
$rs = $r['status'] ?? '';
$rc = match($rs){
    'pending'   =>'ac-badge--na',
    'confirmed' =>'ac-badge--low',
    'paid'      =>'ac-badge--fair',
    'completed' =>'ac-badge--fair',
    'cancelled' =>'ac-badge--high',
    default     =>'ac-badge--na',
};
echo $rs ? '<span class="ac-badge ' . $rc . '">' . htmlspecialchars($rs) . '</span>' : '—';
?></td>
                            <td><?php echo htmlspecialchars($r['consumer_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['farmer_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['market_name']); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['listing_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string)$r['quantity'] . ' ' . (string)($r['unit'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['created_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($tab === 'references'): ?>
        <div class="ac-card">
            <div class="ac-card__title">Set reference price band</div>
            <p class="ac-muted">Min/max used to flag listings as within band, below min, or above max (same product name + category + unit).</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="reference_save">
                <div class="ac-form-grid">
                    <div class="ac-field" style="grid-column: 1 / -1;">
                        <label>Pick existing listing (optional)</label>
                        <select id="acPickCombo" onchange="acFillRef(this)">
                            <option value="">— Choose —</option>
                            <?php foreach ($distinctProducts as $d): ?>
                                <option value="<?php echo htmlspecialchars(json_encode([
                                    'product_name' => $d['product_name'],
                                    'category' => $d['category'] ?? '',
                                    'unit' => $d['unit'] ?? '',
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE)); ?>">
                                    <?php echo htmlspecialchars($d['product_name'] . ' · ' . ($d['category'] ?? '') . ' · ' . ($d['unit'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ac-field">
                        <label>Product name</label>
                        <?php
                        $refProductSuggestions = [];
                        foreach ($distinctProducts as $d) {
                            if (!empty($d['product_name'])) $refProductSuggestions[] = (string)$d['product_name'];
                        }
                        foreach ($refs as $r) {
                            if (!empty($r['product_name'])) $refProductSuggestions[] = (string)$r['product_name'];
                        }
                        $refProductSuggestions = array_values(array_unique(array_filter(array_map('trim', $refProductSuggestions))));
                        sort($refProductSuggestions, SORT_NATURAL | SORT_FLAG_CASE);
                        ?>
                        <input type="text" name="product_name" id="ref_name" required maxlength="255" list="acRefProductNames">
                        <datalist id="acRefProductNames">
                            <?php foreach ($refProductSuggestions as $pn): ?>
                                <option value="<?php echo htmlspecialchars($pn); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="ac-field">
                        <label>Category</label>
                        <select name="category" id="ref_cat">
                            <option value="">—</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php $cn = is_array($cat) ? ($cat['name'] ?? '') : (string)$cat; ?>
                                <?php if ($cn !== ''): ?>
                                    <option value="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ac-field">
                        <label>Unit</label>
                        <?php $unitOpts = function_exists('fsStandardUnitOptions') ? fsStandardUnitOptions() : []; ?>
                        <select name="unit" id="ref_unit">
                            <option value="">—</option>
                            <?php foreach ($unitOpts as $uv => $ul): ?>
                                <option value="<?php echo htmlspecialchars($uv); ?>"><?php echo htmlspecialchars($ul); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ac-field">
                        <label>Min ₱</label>
                        <input type="number" step="0.01" min="0" name="ref_price_min" required value="0">
                    </div>
                    <div class="ac-field">
                        <label>Max ₱</label>
                        <input type="number" step="0.01" min="0" name="ref_price_max" required value="0">
                    </div>
                    <div class="ac-field" style="grid-column: 1 / -1;">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" maxlength="255">
                    </div>
                    <div class="ac-field">
                        <button type="submit" class="ac-btn ac-btn--primary">Save band</button>
                    </div>
                </div>
            </form>
            <script>
                function acFillRef(sel) {
                    var v = sel.value;
                    if (!v) return;
                    try {
                        var o = JSON.parse(v);
                        document.getElementById('ref_name').value = o.product_name || '';
                        // Category: map to dropdown if possible; else custom.
                        var catSel = document.getElementById('ref_cat');
                        var pickedCat = (o.category || '').toString();
                        var catFound = false;
                        if (catSel) {
                            for (var ci = 0; ci < catSel.options.length; ci++) {
                                if (catSel.options[ci].value && catSel.options[ci].value.toLowerCase() === pickedCat.toLowerCase()) {
                                    catSel.value = catSel.options[ci].value;
                                    catFound = true;
                                    break;
                                }
                            }
                            if (!catFound) catSel.value = '';
                        }
                        // Try to map picked unit to canonical dropdown value; else fall back to custom.
                        var unitSel = document.getElementById('ref_unit');
                        var pickedUnit = (o.unit || '').toString();
                        var found = false;
                        if (unitSel) {
                            for (var i = 0; i < unitSel.options.length; i++) {
                                if (unitSel.options[i].value && unitSel.options[i].value.toLowerCase() === pickedUnit.toLowerCase()) {
                                    unitSel.value = unitSel.options[i].value;
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) unitSel.value = '';
                        }
                    } catch (e) {}
                }
            </script>
        </div>
        <div class="ac-card">
            <div class="ac-card__title">Saved reference bands</div>
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Min</th>
                        <th>Max</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($refs as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['category'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['unit'] ?? '—')); ?></td>
                            <td>₱<?php echo number_format((float)$r['ref_price_min'], 2); ?></td>
                            <td>₱<?php echo number_format((float)$r['ref_price_max'], 2); ?></td>
                            <td>
                                <form method="post" style="display:inline;" class="ac-js-confirm" data-confirm-title="Remove reference band?" data-confirm-message="This will permanently remove the saved band.">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="action" value="reference_delete">
                                    <input type="hidden" name="ref_id" value="<?php echo (int)$r['id']; ?>">
                                    <button type="submit" class="ac-btn ac-btn--sm ac-btn--danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: /* users */ ?>
        <div class="ac-card">
            <div class="ac-card__title">Registered users</div>
            <div class="ac-table-wrap">
                <table class="ac-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Active</th>
                        <th>Joined</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usersList as $u): ?>
                        <tr>
                            <td><?php echo (int)$u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td><?php
$ur = $u['user_role'] ?? '';
$uc = $ur === 'super_admin' ? 'ac-badge--high' : ($ur === 'admin' ? 'ac-badge--low' : ($ur === 'farmer' ? 'ac-badge--fair' : 'ac-badge--na'));
echo $ur ? '<span class="ac-badge ' . $uc . '">' . htmlspecialchars($ur) . '</span>' : '—';
?></td>
                            <td><?php echo !empty($u['is_active']) ? '<span class="ac-badge ac-badge--active">Active</span>' : '<span class="ac-badge ac-badge--inactive">Inactive</span>'; ?></td>
                            <td><?php echo htmlspecialchars((string)($u['created_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

        </div><!-- /ac-body -->
    </div><!-- /ac-main -->
</div><!-- /ac-layout -->

<!-- Confirm modal (replaces browser confirm dialogs) -->
<div class="ac-cm" id="acConfirmModal" aria-hidden="true">
    <div class="ac-cm__overlay" data-ac-cm-close></div>
    <div class="ac-cm__panel" role="dialog" aria-modal="true" aria-labelledby="acCmTitle">
        <div class="ac-cm__hd">
            <div class="ac-cm__title" id="acCmTitle">Confirm</div>
            <button type="button" class="ac-cm__x" data-ac-cm-close aria-label="Close">×</button>
        </div>
        <div class="ac-cm__bd">
            <div class="ac-cm__msg" id="acCmMsg">Are you sure?</div>
        </div>
        <div class="ac-cm__ft">
            <button type="button" class="ac-btn ac-btn--sm" data-ac-cm-close>Cancel</button>
            <button type="button" class="ac-btn ac-btn--sm ac-btn--danger" id="acCmOk">Remove</button>
        </div>
    </div>
</div>

<script>
(function(){
    var sidebar = document.getElementById('acSidebar');
    var overlay = document.getElementById('acOverlay');
    var toggle  = document.getElementById('acMobToggle');
    function open(){sidebar.classList.add('mob-open');overlay.classList.add('open');}
    function close(){sidebar.classList.remove('mob-open');overlay.classList.remove('open');}
    if(toggle) toggle.addEventListener('click', function(){sidebar.classList.contains('mob-open')?close():open();});
    if(overlay) overlay.addEventListener('click', close);
})();
</script>

<style>
/* ── Confirm modal ── */
.ac-cm{position:fixed;inset:0;display:none;z-index:9999}
.ac-cm.open{display:block}
.ac-cm__overlay{position:absolute;inset:0;background:rgba(17,24,39,.48);backdrop-filter:blur(2px)}
.ac-cm__panel{position:relative;width:min(520px,calc(100% - 32px));margin:14vh auto 0;background:var(--surf);border:1px solid var(--bdr);border-radius:var(--rad);box-shadow:var(--shd);overflow:hidden}
.ac-cm__hd{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;background:var(--surf2);border-bottom:1px solid var(--bdr)}
.ac-cm__title{font-weight:900;letter-spacing:.2px}
.ac-cm__x{width:34px;height:34px;border-radius:10px;border:1px solid var(--bdr);background:var(--surf);cursor:pointer;font-size:18px;line-height:1;display:inline-flex;align-items:center;justify-content:center}
.ac-cm__x:hover{background:var(--surf3)}
.ac-cm__bd{padding:14px 16px}
.ac-cm__msg{color:var(--mut);font-size:13px;white-space:pre-line}
.ac-cm__ft{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--bdr);background:var(--surf)}
</style>

<script>
(function(){
  var modal = document.getElementById('acConfirmModal');
  var titleEl = document.getElementById('acCmTitle');
  var msgEl = document.getElementById('acCmMsg');
  var okBtn = document.getElementById('acCmOk');
  var pendingForm = null;

  function open(title, msg, okText){
    if(titleEl) titleEl.textContent = title || 'Confirm';
    if(msgEl) msgEl.textContent = msg || 'Are you sure?';
    okBtn.textContent = okText || 'Confirm';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  }
  function close(){
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
    pendingForm = null;
  }

  document.querySelectorAll('[data-ac-cm-close]').forEach(function(el){
    el.addEventListener('click', close);
  });

  document.querySelectorAll('form.ac-js-confirm').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      pendingForm = form;
      open(
        form.getAttribute('data-confirm-title') || 'Confirm action',
        form.getAttribute('data-confirm-message') || 'Are you sure?',
        (form.querySelector('button[type=\"submit\"]') || {}).textContent || 'Confirm'
      );
    });
  });

  okBtn.addEventListener('click', function(){
    if(pendingForm) pendingForm.submit();
    close();
  });

  document.addEventListener('keydown', function(e){
    if(!modal.classList.contains('open')) return;
    if(e.key === 'Escape') close();
  });
})();
</script>
</body>
</html>
