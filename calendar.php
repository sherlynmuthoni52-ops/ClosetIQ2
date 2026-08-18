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

// Get user's outfit history for the assignment modal
$stmt = $pdo->prepare('SELECT * FROM outfit_history WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50');
$stmt->execute(['user_id' => $userId]);
$outfitHistory = $stmt->fetchAll();
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
    </div>
</div>

<!-- Outfit Assignment Modal -->
<div class="modal-overlay" id="outfit-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-date-title">Assign Outfit</h3>
            <button type="button" class="modal-close" id="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modal-entry-info">
                <p class="modal-hint">Select an outfit from your history to assign to this date.</p>
            </div>
            
            <form id="outfit-assign-form">
                <input type="hidden" id="modal-date">
                <div class="form-group">
                    <label for="outfit-select">Choose Outfit</label>
                    <select id="outfit-select" class="form-control">
                        <option value="">-- No outfit (just notes) --</option>
                        <?php foreach ($outfitHistory as $outfit): ?>
                            <?php
                            $details = json_decode($outfit['outfit_details'], true);
                            $desc = 'Outfit';
                            if ($details && isset($details['description'])) {
                                $desc = $details['description'];
                            } elseif ($details && isset($details['items']) && is_array($details['items'])) {
                                $names = array_column($details['items'], 'name');
                                $desc = implode(', ', array_slice($names, 0, 3));
                                if (count($names) > 3) $desc .= '...';
                            }
                            ?>
                            <option value="<?php echo $outfit['id']; ?>">
                                <?php echo htmlspecialchars(date('M j, Y', strtotime($outfit['created_at']))); ?> - <?php echo htmlspecialchars($desc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="outfit-notes">Notes (optional)</label>
                    <textarea id="outfit-notes" rows="3" placeholder="Add any notes for this day..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="remove-outfit-btn" style="display: none;">Remove Outfit</button>
                    <button type="submit" class="btn btn-primary" id="save-outfit-btn">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Outfit Preview Tooltip -->
<div class="tooltip" id="outfit-tooltip">
    <div class="tooltip-content" id="tooltip-content"></div>
</div>

<?php require_once 'includes/footer.php'; ?>
