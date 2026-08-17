<?php
/**
 * Dashboard Page
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Shows wardrobe statistics, recent outfits, and current weather.
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$user = get_logged_in_user();
$userId = $user['id'];

// Get wardrobe statistics
$stats = [];
$stmt = $pdo->prepare('SELECT category, COUNT(*) as count FROM clothing_items WHERE user_id = :user_id GROUP BY category');
$stmt->execute(['user_id' => $userId]);
$stats['categories'] = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM clothing_items WHERE user_id = :user_id');
$stmt->execute(['user_id' => $userId]);
$stats['total'] = $stmt->fetch()['total'];

// Get recent outfit history
$stmt = $pdo->prepare('SELECT * FROM outfit_history WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
$stmt->execute(['user_id' => $userId]);
$recentOutfits = $stmt->fetchAll();

// Default city for weather (could be stored in user profile later)
$weatherCity = 'Nairobi';
?>

<div class="container">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($user['username']); ?>! Here's your wardrobe overview.</p>
    </div>

    <div class="dashboard-grid">
        <!-- Weather Card -->
        <div class="card weather-card">
            <h2>Current Weather</h2>
            <div id="weather-display" class="weather-display">
                <p class="loading">Loading weather data...</p>
            </div>
            <form id="weather-form" class="weather-form">
                <div class="form-group">
                    <input type="text" id="weather-city" placeholder="Enter city name" value="<?php echo htmlspecialchars($weatherCity); ?>">
                    <button type="submit" class="btn btn-secondary">Check</button>
                </div>
            </form>
        </div>

        <!-- Statistics Card -->
        <div class="card stats-card">
            <h2>Wardrobe Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total']; ?></span>
                    <span class="stat-label">Total Items</span>
                </div>
                <?php foreach ($stats['categories'] as $cat): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $cat['count']; ?></span>
                        <span class="stat-label"><?php echo ucfirst($cat['category']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="wardrobe.php" class="btn btn-primary">Manage Wardrobe</a>
        </div>

        <!-- Recent Outfits Card -->
        <div class="card recent-card">
            <h2>Recent Outfits</h2>
            <?php if (empty($recentOutfits)): ?>
                <p class="empty-state">No outfits generated yet. <a href="outfits.php">Generate your first outfit!</a></p>
            <?php else: ?>
                <ul class="outfit-list">
                    <?php foreach ($recentOutfits as $outfit): ?>
                        <li class="outfit-item">
                            <div class="outfit-date"><?php echo date('M j, Y g:i A', strtotime($outfit['created_at'])); ?></div>
                            <div class="outfit-details">
                                <?php
                                $details = json_decode($outfit['outfit_details'], true);
                                echo htmlspecialchars($details['description'] ?? 'Outfit combination');
                                ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a href="history.php" class="btn btn-link">View all history</a>
        </div>
    </div>
</div>

<script>
document.getElementById('weather-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const city = document.getElementById('weather-city').value.trim();
    const display = document.getElementById('weather-display');
    
    if (!city) return;
    
    display.innerHTML = '<p class="loading">Loading...</p>';
    
    try {
        const response = await fetch('api/weather.php?city=' + encodeURIComponent(city));
        const data = await response.json();
        
        if (data.error) {
            display.innerHTML = '<p class="error">' + data.error + '</p>';
        } else {
            display.innerHTML = `
                <div class="weather-info">
                    <div class="weather-temp">${data.temperature}°C</div>
                    <div class="weather-desc">${data.description}</div>
                    <div class="weather-details">
                        <span>Feels like: ${data.feels_like}°C</span>
                        <span>Humidity: ${data.humidity}%</span>
                        <span>Wind: ${data.wind_speed} m/s</span>
                    </div>
                </div>
            `;
        }
    } catch (err) {
        display.innerHTML = '<p class="error">Failed to load weather data.</p>';
    }
});

// Load weather on page load
document.getElementById('weather-form').dispatchEvent(new Event('submit'));
</script>

<?php require_once 'includes/footer.php'; ?>
