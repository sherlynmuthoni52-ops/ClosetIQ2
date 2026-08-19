<?php
/**
 * AI Outfit Suggestion API
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Generates AI-powered outfit suggestions using Ollama LLM
 * with weather data and occasion context.
 * 
 * Usage: POST with JSON body { city, occasion, include_weather }
 * Returns JSON with curated outfit, vibe, and styling tip.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// Ensure no previous output contaminates JSON
if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

// Helper: always return valid JSON and stop execution
function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $rawInput = @file_get_contents('php://input');
    $input = json_decode($rawInput ?: '{}', true);
    
    if (!is_array($input)) {
        $input = [];
    }

    $city = isset($input['city']) ? trim((string)($input['city'] ?? '')) : '';
    $occasion = isset($input['occasion']) ? trim((string)($input['occasion'] ?? '')) : '';
    $includeWeather = !empty($input['include_weather']);

    if (empty($occasion)) {
        json_response(['success' => false, 'error' => 'Please select an occasion'], 400);
    }

    $user = get_logged_in_user();
    $userId = (int)($user['id'] ?? 0);

    if ($userId <= 0) {
        json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    // Fetch weather data if requested
    $weatherData = null;
    if ($includeWeather && !empty($city)) {
        $apiKey = '57afe33e7588afac53705e3caf3750a4 ';
        $baseUrl = 'https://api.openweathermap.org/data/2.5/weather';
        $url = $baseUrl . '?q=' . urlencode($city) . '&appid=' . $apiKey . '&units=metric';
        
        $response = @file_get_contents($url);
        if ($response === false) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
        }
        
        if ($response !== false) {
            $weatherJson = json_decode($response, true);
            if ($weatherJson && is_array($weatherJson) && isset($weatherJson['cod']) && $weatherJson['cod'] == 200) {
                $weatherData = [
                    'city' => (string)($weatherJson['name'] ?? ''),
                    'country' => (string)($weatherJson['sys']['country'] ?? ''),
                    'temperature' => (float)($weatherJson['main']['temp'] ?? 0),
                    'feels_like' => (float)($weatherJson['main']['feels_like'] ?? 0),
                    'humidity' => (int)($weatherJson['main']['humidity'] ?? 0),
                    'description' => (string)ucfirst($weatherJson['weather'][0]['description'] ?? ''),
                    'icon' => (string)($weatherJson['weather'][0]['icon'] ?? ''),
                    'wind_speed' => (float)($weatherJson['wind']['speed'] ?? 0)
                ];
            }
        }
    }

    // Fetch user's wardrobe items
    $stmt = $pdo->prepare('SELECT id, name, category, color, season, image_path FROM clothing_items WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $wardrobeItems = $stmt->fetchAll();

    if (empty($wardrobeItems)) {
        json_response(['success' => false, 'error' => 'Your wardrobe is empty. Add some clothing items first!'], 400);
    }

    // Build wardrobe summary for LLM
    $wardrobeSummary = [];
    foreach ($wardrobeItems as $item) {
        $wardrobeSummary[] = [
            'name' => (string)$item['name'],
            'category' => (string)$item['category'],
            'color' => (string)$item['color'],
            'season' => (string)$item['season']
        ];
    }

    // Build LLM prompt
    $weatherContext = $weatherData 
        ? "Temperature: {$weatherData['temperature']}°C, Feels like: {$weatherData['feels_like']}°C, Condition: {$weatherData['description']}, Humidity: {$weatherData['humidity']}%, Wind: {$weatherData['wind_speed']} km/h"
        : 'No weather data available';

    $systemPrompt = <<<PROMPT
You are a professional fashion stylist. Your task is to create a curated outfit suggestion based on the user's wardrobe, current weather, and occasion.

RULES:
1. Select 2-4 items ONLY from the provided wardrobe list.
2. You must choose at least one top and one bottom if available.
3. Consider the weather conditions when selecting items (e.g., layers for cold, light fabrics for heat).
4. Consider the occasion when selecting items (e.g., formal wear for formal events, comfortable wear for workouts).
5. Provide a short "vibe" description (1-2 sentences) that captures the overall aesthetic of the look.
6. Provide a practical "styling_tip" (1-2 sentences) with actionable advice.

OUTPUT FORMAT (STRICT JSON ONLY, NO MARKDOWN, NO EXTRA TEXT):
{
    "items": [
        {"name": "exact item name from wardrobe", "category": "category", "color": "color"}
    ],
    "vibe": "short vibe description",
    "styling_tip": "practical styling advice"
}

Return ONLY valid JSON. Do not include markdown code blocks or any explanatory text.
PROMPT;

    $userPrompt = <<<PROMPT
Occasion: {$occasion}
Weather: {$weatherContext}

My Wardrobe:
PROMPT;

    foreach ($wardrobeSummary as $idx => $item) {
        $userPrompt .= ($idx + 1) . ". {$item['name']} ({$item['category']}, {$item['color']}, {$item['season']})\n";
    }

    $userPrompt .= "\nGenerate a curated outfit for this occasion and weather using ONLY items from my wardrobe above.";

    // Call Ollama
    $outfitResult = null;
    $aiGenerated = 0;

    try {
        $ollamaPayload = json_encode([
            'model' => 'llama3.2',
            'stream' => false,
            'format' => 'json',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'options' => [
                'temperature' => 0.7,
                'num_predict' => 512
            ]
        ]);

        if ($ollamaPayload === false) {
            throw new Exception('Failed to encode LLM payload');
        }

        $ch = curl_init('http://localhost:11434/api/chat');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $ollamaPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $ollamaResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $ollamaResponse !== false) {
            $ollamaData = json_decode($ollamaResponse, true);
            if ($ollamaData && is_array($ollamaData) && isset($ollamaData['message']['content'])) {
                $content = (string)($ollamaData['message']['content'] ?? '');
                $content = trim($content);
                
                // Strip markdown code blocks if present
                if (strpos($content, '```') !== false) {
                    $content = preg_replace('/```(?:json)?\s*/', '', $content);
                    $content = str_replace('```', '', $content);
                    $content = trim($content);
                }
                
                $parsed = json_decode($content, true);
                if ($parsed && is_array($parsed) && isset($parsed['items']) && is_array($parsed['items'])) {
                    // Validate items are from wardrobe
                    $validItems = [];
                    $validNames = array_column($wardrobeItems, 'name');
                    
                    foreach ($parsed['items'] as $item) {
                        $name = trim((string)($item['name'] ?? ''));
                        if (in_array($name, $validNames)) {
                            $validItems[] = $item;
                        }
                    }
                    
                    if (!empty($validItems)) {
                        // Enrich items with full wardrobe data
                        $enrichedItems = [];
                        foreach ($validItems as $item) {
                            $name = $item['name'];
                            foreach ($wardrobeItems as $wi) {
                                if ($wi['name'] === $name) {
                                    $enrichedItems[] = [
                                        'id' => (int)$wi['id'],
                                        'name' => (string)$wi['name'],
                                        'category' => (string)$wi['category'],
                                        'color' => (string)$wi['color'],
                                        'season' => (string)$wi['season'],
                                        'image_path' => $wi['image_path'] ? (string)$wi['image_path'] : null
                                    ];
                                    break;
                                }
                            }
                        }
                        
                        $outfitResult = [
                            'items' => $enrichedItems,
                            'description' => implode(', ', array_column($validItems, 'name')),
                            'vibe' => (string)($parsed['vibe'] ?? 'A stylish look'),
                            'styling_tip' => (string)($parsed['styling_tip'] ?? 'Rock it with confidence!'),
                            'weather' => $weatherData,
                            'occasion' => $occasion
                        ];
                        $aiGenerated = 1;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $outfitResult = null;
    }

    // Fallback to rule-based generation if LLM failed
    if ($outfitResult === null) {
        $itemsByCategory = [];
        foreach ($wardrobeItems as $item) {
            $itemsByCategory[$item['category']][] = $item;
        }
        
        $outfit = [];
        $outfitDesc = [];
        
        if (isset($itemsByCategory['tops']) && !empty($itemsByCategory['tops'])) {
            $selected = $itemsByCategory['tops'][array_rand($itemsByCategory['tops'])];
            $outfit[] = $selected;
            $outfitDesc[] = $selected['name'];
        }
        
        if (isset($itemsByCategory['bottoms']) && !empty($itemsByCategory['bottoms'])) {
            $selected = $itemsByCategory['bottoms'][array_rand($itemsByCategory['bottoms'])];
            $outfit[] = $selected;
            $outfitDesc[] = $selected['name'];
        }
        
        if (isset($itemsByCategory['footwear']) && !empty($itemsByCategory['footwear'])) {
            $selected = $itemsByCategory['footwear'][array_rand($itemsByCategory['footwear'])];
            $outfit[] = $selected;
            $outfitDesc[] = $selected['name'];
        }
        
        if (isset($itemsByCategory['accessories']) && !empty($itemsByCategory['accessories'])) {
            $selected = $itemsByCategory['accessories'][array_rand($itemsByCategory['accessories'])];
            $outfit[] = $selected;
            $outfitDesc[] = $selected['name'];
        }
        
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
            'vibe' => 'A classic ' . $occasion . ' look',
            'styling_tip' => 'Keep it simple and let the fit do the work. Confidence is your best accessory.',
            'weather' => $weatherData,
            'occasion' => $occasion
        ];
    }

    // Save to history
    $stmt = $pdo->prepare('INSERT INTO outfit_history (user_id, outfit_details, weather_data, occasion, ai_generated) VALUES (:user_id, :outfit_details, :weather_data, :occasion, :ai_generated)');
    $stmt->execute([
        'user_id' => $userId,
        'outfit_details' => json_encode($outfitResult, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        'weather_data' => $weatherData ? json_encode($weatherData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
        'occasion' => $occasion,
        'ai_generated' => $aiGenerated
    ]);

    $outfitResult['id'] = (int)$pdo->lastInsertId();

    json_response([
        'success' => true,
        'data' => $outfitResult,
        'ai_generated' => (bool)$aiGenerated
    ]);

} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
