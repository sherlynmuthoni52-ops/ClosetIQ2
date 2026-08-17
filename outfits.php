<?php
/**
 * Outfits Page
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Generates outfit suggestions based on user's wardrobe and weather data.
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$user = get_logged_in_user();
$userId = $user['id'];
$outfitResult = null;
$weatherData = null;
$error = '';

// Generate outfit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_outfit'])) {
    $selectedCategories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $includeWeather = isset($_POST['include_weather']);
    $city = trim($_POST['city'] ?? '');
    
    if (empty($selectedCategories)) {
        $error = 'Please select at least one category.';
    } else {
        // Fetch weather if requested
        if ($includeWeather && !empty($city)) {
            $weatherData = fetchWeather($city);
        }
        
        // Fetch clothing items from selected categories
        $placeholders = implode(',', array_fill(0, count($selectedCategories), '?'));
        $stmt = $pdo->prepare("SELECT * FROM clothing_items WHERE user_id = ? AND category IN ($placeholders) ORDER BY category, name");
        $stmt->execute(array_merge([$userId], $selectedCategories));
        $items = $stmt->fetchAll();
        
        if (empty($items)) {
            $error = 'No clothing items found in the selected categories. Add some items first!';
        } else {
            // Group items by category
            $itemsByCategory = [];
            foreach ($items as $item) {
                $itemsByCategory[$item['category']][] = $item;
            }
            
            // Rule-based outfit generation
            $outfit = [];
            $outfitDesc = [];
            
            // Always try to include a top
            if (isset($itemsByCategory['tops']) && !empty($itemsByCategory['tops'])) {
                $outfit[] = $itemsByCategory['tops'][array_rand($itemsByCategory['tops'])];
                $outfitDesc[] = 'Top: ' . $outfit[count($outfit)-1]['name'];
            }
            
            // Always try to include a bottom
            if (isset($itemsByCategory['bottoms']) && !empty($itemsByCategory['bottoms'])) {
                $outfit[] = $itemsByCategory['bottoms'][array_rand($itemsByCategory['bottoms'])];
                $outfitDesc[] = 'Bottom: ' . $outfit[count($outfit)-1]['name'];
            }
            
            // Include footwear if selected and available
            if (in_array('footwear', $selectedCategories) && isset($itemsByCategory['footwear']) && !empty($itemsByCategory['footwear'])) {
                $outfit[] = $itemsByCategory['footwear'][array_rand($itemsByCategory['footwear'])];
                $outfitDesc[] = 'Footwear: ' . $outfit[count($outfit)-1]['name'];
            }
            
            // Include accessories if selected and available
            if (in_array('accessories', $selectedCategories) && isset($itemsByCategory['accessories']) && !empty($itemsByCategory['accessories'])) {
                $outfit[] = $itemsByCategory['accessories'][array_rand($itemsByCategory['accessories'])];
                $outfitDesc[] = 'Accessory: ' . $outfit[count($outfit)-1]['name'];
            }
            
            // Weather-based adjustments
            $weatherNote = '';
            if ($weatherData && isset($weatherData['temperature'])) {
                $temp = $weatherData['temperature'];
                if ($temp < 15) {
                    $weatherNote = ' (Cold: Consider adding layers or a warm jacket)';
                } elseif ($temp > 25) {
                    $weatherNote = ' (Hot: Light fabrics recommended)';
                }
                if (strpos(strtolower($weatherData['description']), 'rain') !== false) {
                    $weatherNote .= ' (Rainy: Consider waterproof footwear)';
                }
            }
            
            $outfitResult = [
                'items' => $outfit,
                'description' => implode(', ', $outfitDesc) . $weatherNote,
                'weather' => $weatherData
            ];
            
            // Save to history
            $stmt = $pdo->prepare('INSERT INTO outfit_history (user_id, outfit_details, weather_data) VALUES (:user_id, :outfit_details, :weather_data)');
            $stmt->execute([
                'user_id' => $userId,
                'outfit_details' => json_encode($outfitResult),
                'weather_data' => $weatherData ? json_encode($weatherData) : null
            ]);
        }
    }
}

/**
 * Fetches weather data from OpenWeatherMap API.
 * Returns weather data array on success, null on failure.
 */
function fetchWeather($city) {
    // Configuration - replace with your actual OpenWeatherMap API key
    $apiKey = 'YOUR_OPENWEATHERMAP_API_KEY_HERE';
    $baseUrl = 'https://api.openweathermap.org/data/2.5/weather';
    
    // Validate city name
    if (!preg_match('/^[a-zA-Z\s\-]+$/', $city)) {
        return null;
    }
    
    $url = $baseUrl . '?q=' . urlencode($city) . '&appid=' . $apiKey . '&units=metric';
    
    // Try file_get_contents first
    $response = @file_get_contents($url);
    
    // Fallback to cURL if file_get_contents fails
    if ($response === false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    }
    
    if ($response === false) {
        return null;
    }
    
    $weatherData = json_decode($response, true);
    
    if (!$weatherData || isset($weatherData['cod']) && $weatherData['cod'] != 200) {
        return null;
    }
    
    return [
        'city' => $weatherData['name'],
        'country' => $weatherData['sys']['country'],
        'temperature' => $weatherData['main']['temp'],
        'feels_like' => $weatherData['main']['feels_like'],
        'humidity' => $weatherData['main']['humidity'],
        'description' => ucfirst($weatherData['weather'][0]['description']),
        'icon' => $weatherData['weather'][0]['icon'],
        'wind_speed' => $weatherData['wind']['speed']
    ];
}

// Get user's categories with items
$stmt = $pdo->prepare('SELECT category, COUNT(*) as count FROM clothing_items WHERE user_id = :user_id GROUP BY category');
$stmt->execute(['user_id' => $userId]);
$userCategories = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Outfit Suggestions</h1>
        <p>Get personalized outfit recommendations based on your wardrobe and weather</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="outfits-layout">
        <!-- Generate Outfit Form -->
        <div class="card form-card">
            <h2>Generate Outfit</h2>
            <form method="POST" action="outfits.php" id="outfit-form">
                <div class="form-group">
                    <label>Select Categories</label>
                    <div class="checkbox-group">
                        <?php
                        $allCategories = ['tops' => 'Tops', 'bottoms' => 'Bottoms', 'footwear' => 'Footwear', 'accessories' => 'Accessories'];
                        foreach ($allCategories as $key => $label):
                            $hasItems = in_array($key, array_column($userCategories, 'category'));
                        ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="categories[]" value="<?php echo $key; ?>" <?php echo $hasItems ? '' : 'disabled'; ?>>
                                <span><?php echo $label; ?></span>
                                <?php if (!$hasItems): ?>
                                    <small>(no items)</small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="include_weather" checked> Include weather data
                    </label>
                </div>
                <div class="form-group">
                    <label for="outfit-city">City (for weather)</label>
                    <input type="text" id="outfit-city" name="city" placeholder="e.g., Nairobi" value="Nairobi">
                </div>
                <button type="submit" name="generate_outfit" class="btn btn-primary btn-block">Generate Outfit</button>
            </form>
        </div>

        <!-- Outfit Result -->
        <div class="outfit-result">
            <?php if ($outfitResult): ?>
                <div class="card result-card">
                    <h2>Your Outfit</h2>
                    <div class="outfit-items">
                        <?php foreach ($outfitResult['items'] as $item): ?>
                            <div class="outfit-item-card">
                                <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="item-icon">👕</div>
                                <?php endif; ?>
                                <div class="outfit-item-info">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p><?php echo ucfirst($item['category']); ?> | <?php echo htmlspecialchars($item['color']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="outfit-description">
                        <p><?php echo htmlspecialchars($outfitResult['description']); ?></p>
                    </div>
                    <?php if ($outfitResult['weather']): ?>
                        <div class="weather-badge">
                            🌤️ <?php echo htmlspecialchars($outfitResult['weather']['temperature']); ?>°C - <?php echo htmlspecialchars($outfitResult['weather']['description']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card empty-card">
                    <h2>No Outfit Generated Yet</h2>
                    <p>Select categories and click "Generate Outfit" to get suggestions based on your wardrobe.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
