<?php
/**
 * Weather API Proxy
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Server-side proxy to OpenWeatherMap API.
 * Hides the API key from client-side code.
 * 
 * Usage: api/weather.php?city=London
 * Returns JSON with temperature, condition, humidity, etc.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow logged-in users to access weather data
require_login();

header('Content-Type: application/json');

// Configuration - replace with your actual OpenWeatherMap API key
define('OWM_API_KEY', '57afe33e7588afac53705e3caf3750a4 ');
define('OWM_BASE_URL', 'https://api.openweathermap.org/data/2.5/weather');

$city = isset($_GET['city']) ? trim($_GET['city']) : '';

if (empty($city)) {
    http_response_code(400);
    echo json_encode(['error' => 'City parameter is required']);
    exit;
}

// Validate city name (basic validation)
if (!preg_match('/^[a-zA-Z\s\-]+$/', $city)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid city name']);
    exit;
}

$url = OWM_BASE_URL . '?q=' . urlencode($city) . '&appid=' . OWM_API_KEY . '&units=metric';

$response = @file_get_contents($url);

if ($response === false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
}

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch weather data']);
    exit;
}

$weatherData = json_decode($response, true);

if (!$weatherData || $weatherData['cod'] != 200) {
    http_response_code(404);
    echo json_encode(['error' => 'City not found']);
    exit;
}

// Return only the data we need
$result = [
    'city' => $weatherData['name'],
    'country' => $weatherData['sys']['country'],
    'temperature' => $weatherData['main']['temp'],
    'feels_like' => $weatherData['main']['feels_like'],
    'humidity' => $weatherData['main']['humidity'],
    'description' => ucfirst($weatherData['weather'][0]['description']),
    'icon' => $weatherData['weather'][0]['icon'],
    'wind_speed' => $weatherData['wind']['speed']
];

echo json_encode($result);
