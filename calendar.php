<?php
/**
 * Calendar Page
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Displays a calendar with outfit assignments and allows managing them.
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$user = get_logged_in_user();
$userId = $user['id'];
$pageTitle = 'Calendar';
?>

<div class="container">
    <div class="page-header">
        <h1>Outfit Calendar</h1>
        <p>Plan your outfits and track what you wore each day</p>
    </div>

    <div class="calendar-wrapper">
        <!-- Calendar Controls -->
        <div class="calendar-controls">
            <button type="button" class="btn btn-secondary" id="prev-month">&lt; Prev</button>
            <h2 class="calendar-month-year" id="calendar-month-year"></h2>
            <button type="button" class="btn btn-secondary" id="next-month">Next &gt;</button>
            <button type="button" class="btn btn-primary" id="today-btn">Today</button>
        </div>

        <!-- Calendar Grid -->
        <div class="card calendar-card">
            <div class="calendar-weekdays">
                <div class="weekday">Sun</div>
                <div class="weekday">Mon</div>
                <div class="weekday">Tue</div>
                <div class="weekday">Wed</div>
                <div class="weekday">Thu</div>
                <div class="weekday">Fri</div>
                <div class="weekday">Sat</div>
            </div>
            <div class="calendar-grid" id="calendar-grid">
                <!-- Calendar days will be rendered here by JavaScript -->
            </div>
        </div>

        <!-- Calendar Legend -->
        <div class="calendar-legend">
            <div class="legend-item">
                <div class="legend-dot today-dot"></div>
                <span>Today</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot outfit-dot"></div>
                <span>Has Outfit</span>
            </div>
            <div class="legend-item" style="color: var(--text-light);">
                <span>Tap any day to pick an outfit</span>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Sheet Overlay -->
<div class="bottom-sheet-overlay" id="bottom-sheet-overlay">
    <div class="bottom-sheet" id="bottom-sheet">
        <div class="bottom-sheet-header">
            <h3 id="sheet-date-title">Pick an Outfit</h3>
            <button type="button" class="sheet-close" id="sheet-close">&times;</button>
        </div>
        
        <div class="bottom-sheet-tabs">
            <button type="button" class="sheet-tab active" data-category="all">All</button>
            <button type="button" class="sheet-tab" data-category="tops">Tops</button>
            <button type="button" class="sheet-tab" data-category="bottoms">Bottoms</button>
            <button type="button" class="sheet-tab" data-category="footwear">Footwear</button>
            <button type="button" class="sheet-tab" data-category="accessories">Accessories</button>
        </div>
        
        <div class="bottom-sheet-items" id="sheet-items">
            <div class="loading">Loading your wardrobe...</div>
        </div>
        
        <div class="bottom-sheet-footer">
            <button type="button" class="btn btn-danger" id="sheet-remove-btn" style="display: none;">Remove</button>
            <button type="button" class="btn btn-primary btn-block" id="sheet-save-btn">Save Outfit</button>
        </div>
    </div>
</div>

<!-- Outfit Preview Tooltip -->
<div class="tooltip" id="outfit-tooltip">
    <div class="tooltip-content" id="tooltip-content"></div>
</div>

<?php require_once 'includes/footer.php'; ?>
