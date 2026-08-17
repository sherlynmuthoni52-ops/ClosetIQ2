<?php
/**
 * Header Include
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Common header with navigation for authenticated pages.
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClosetIQ<?php echo isset($pageTitle) ? ' - ' . htmlspecialchars($pageTitle) : ''; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="dashboard.php" class="logo">
                <span class="logo-icon">👔</span>
                <span class="logo-text">ClosetIQ</span>
            </a>
            <nav class="main-nav">
                <ul>
                    <li><a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="wardrobe.php" class="<?php echo $currentPage === 'wardrobe.php' ? 'active' : ''; ?>">Wardrobe</a></li>
                    <li><a href="outfits.php" class="<?php echo $currentPage === 'outfits.php' ? 'active' : ''; ?>">Outfits</a></li>
                    <li><a href="history.php" class="<?php echo $currentPage === 'history.php' ? 'active' : ''; ?>">History</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <span class="username">Hello, <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
            <button class="mobile-menu-toggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
    <main class="main-content">
