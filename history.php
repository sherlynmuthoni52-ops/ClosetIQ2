<?php
/**
 * History Page
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Displays the user's outfit generation history.
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$user = get_logged_in_user();
$userId = $user['id'];

// Get all outfit history for this user
$stmt = $pdo->prepare('SELECT * FROM outfit_history WHERE user_id = :user_id ORDER BY created_at DESC');
$stmt->execute(['user_id' => $userId]);
$outfitHistory = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Outfit History</h1>
        <p>View your previously generated outfit combinations</p>
    </div>

    <?php if (empty($outfitHistory)): ?>
        <div class="empty-state-card">
            <div class="empty-icon">📋</div>
            <h2>No History Yet</h2>
            <p>You haven't generated any outfits yet. <a href="outfits.php">Generate your first outfit</a> to see it here!</p>
        </div>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($outfitHistory as $entry): ?>
                <?php
                $details = json_decode($entry['outfit_details'], true);
                $weather = $entry['weather_data'] ? json_decode($entry['weather_data'], true) : null;
                
                // Skip entries with invalid JSON
                if (!$details || !is_array($details)) {
                    continue;
                }
                ?>
                <div class="history-card">
                    <div class="history-header">
                        <span class="history-date"><?php echo date('F j, Y g:i A', strtotime($entry['created_at'])); ?></span>
                        <?php if ($weather): ?>
                            <span class="history-weather">🌤️ <?php echo htmlspecialchars($weather['temperature']); ?>°C</span>
                        <?php endif; ?>
                    </div>
                    <div class="history-items">
                        <?php if (isset($details['items']) && is_array($details['items'])): ?>
                            <?php foreach ($details['items'] as $item): ?>
                                <div class="history-item">
                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="item-meta"><?php echo ucfirst($item['category']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($details['description'])): ?>
                        <div class="history-description">
                            <p><?php echo htmlspecialchars($details['description']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
