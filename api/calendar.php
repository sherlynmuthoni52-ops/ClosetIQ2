<?php
/**
 * Calendar API
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Handles calendar-related AJAX requests:
 * - Fetch calendar entries for a month
 * - Assign outfit to a date
 * - Remove outfit from a date
 * - Fetch wardrobe items for bottom sheet picker
 * - Check outfit repeats
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_month':
            $year = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
            $month = isset($_GET['month']) ? (int) $_GET['month'] : date('m');
            
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));
            
            $stmt = $pdo->prepare('
                SELECT c.id, c.date, c.outfit_history_id, c.outfit_details, c.notes, c.created_at,
                       h.outfit_details as history_outfit_details, h.weather_data
                FROM outfit_calendar c
                LEFT JOIN outfit_history h ON h.id = c.outfit_history_id
                WHERE c.user_id = :user_id 
                  AND c.date BETWEEN :start_date AND :end_date
                ORDER BY c.date ASC
            ');
            $stmt->execute([
                'user_id' => $user_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            $entries = $stmt->fetchAll();
            
            // Compute global wear counts for this user across all calendar entries
            $stmt = $pdo->prepare('SELECT outfit_details FROM outfit_calendar WHERE user_id = :user_id AND outfit_details IS NOT NULL');
            $stmt->execute(['user_id' => $user_id]);
            $all_calendar = $stmt->fetchAll();
            
            $stmt = $pdo->prepare('
                SELECT h.outfit_details 
                FROM outfit_history h
                JOIN outfit_calendar c ON c.outfit_history_id = h.id
                WHERE c.user_id = :user_id
            ');
            $stmt->execute(['user_id' => $user_id]);
            $all_history = $stmt->fetchAll();
            
            $global_wear_counts = [];
            foreach (array_merge($all_calendar, $all_history) as $entry) {
                $details = json_decode($entry['outfit_details'], true);
                if ($details && isset($details['items']) && is_array($details['items'])) {
                    foreach ($details['items'] as $item) {
                        if (isset($item['id'])) {
                            $global_wear_counts[$item['id']] = ($global_wear_counts[$item['id']] ?? 0) + 1;
                        }
                    }
                }
            }
            
            $formatted = [];
            foreach ($entries as $entry) {
                $details = null;
                if ($entry['outfit_details']) {
                    $details = json_decode($entry['outfit_details'], true);
                } elseif ($entry['history_outfit_details']) {
                    $details = json_decode($entry['history_outfit_details'], true);
                }
                
                // Attach wear counts to items
                if ($details && isset($details['items']) && is_array($details['items'])) {
                    foreach ($details['items'] as &$item) {
                        if (isset($item['id'])) {
                            $item['wear_count'] = $global_wear_counts[$item['id']] ?? 0;
                        }
                    }
                }
                
                $formatted[] = [
                    'id' => $entry['id'],
                    'date' => $entry['date'],
                    'outfit_history_id' => $entry['outfit_history_id'],
                    'notes' => $entry['notes'],
                    'outfit_details' => $details
                ];
            }
            
            echo json_encode(['success' => true, 'entries' => $formatted]);
            break;
            
        case 'get_wardrobe_items':
            $stmt = $pdo->prepare('SELECT id, name, category, color, image_path FROM clothing_items WHERE user_id = :user_id ORDER BY category, name');
            $stmt->execute(['user_id' => $user_id]);
            $items = $stmt->fetchAll();
            
            // Compute wear counts
            $stmt = $pdo->prepare('SELECT outfit_details FROM outfit_calendar WHERE user_id = :user_id AND outfit_details IS NOT NULL');
            $stmt->execute(['user_id' => $user_id]);
            $all_calendar = $stmt->fetchAll();
            
            $stmt = $pdo->prepare('
                SELECT h.outfit_details 
                FROM outfit_history h
                JOIN outfit_calendar c ON c.outfit_history_id = h.id
                WHERE c.user_id = :user_id
            ');
            $stmt->execute(['user_id' => $user_id]);
            $all_history = $stmt->fetchAll();
            
            $wear_counts = [];
            foreach (array_merge($all_calendar, $all_history) as $entry) {
                $details = json_decode($entry['outfit_details'], true);
                if ($details && isset($details['items']) && is_array($details['items'])) {
                    foreach ($details['items'] as $item) {
                        if (isset($item['id'])) {
                            $wear_counts[$item['id']] = ($wear_counts[$item['id']] ?? 0) + 1;
                        }
                    }
                }
            }
            
            $grouped = [];
            foreach ($items as $item) {
                $item['wear_count'] = $wear_counts[$item['id']] ?? 0;
                $grouped[$item['category']][] = $item;
            }
            
            echo json_encode(['success' => true, 'items' => $grouped]);
            break;
            
        case 'assign_outfit':
            $date = trim($_POST['date'] ?? '');
            $outfit_history_id = !empty($_POST['outfit_history_id']) ? (int) $_POST['outfit_history_id'] : null;
            $items = !empty($_POST['items']) ? json_decode($_POST['items'], true) : null;
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['success' => false, 'error' => 'Invalid date']);
                exit;
            }
            
            $outfit_details = null;
            
            if ($items && is_array($items)) {
                $item_ids = array_column($items, 'id');
                if (!empty($item_ids)) {
                    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                    $stmt = $pdo->prepare("SELECT id, name, category, color, image_path FROM clothing_items WHERE user_id = ? AND id IN ($placeholders)");
                    $stmt->execute(array_merge([$user_id], $item_ids));
                    $valid_items = $stmt->fetchAll();
                    $outfit_details = json_encode(['items' => $valid_items]);
                }
            } elseif ($outfit_history_id) {
                $stmt = $pdo->prepare('SELECT outfit_details FROM outfit_history WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $outfit_history_id, 'user_id' => $user_id]);
                $row = $stmt->fetch();
                if ($row) {
                    $outfit_details = $row['outfit_details'];
                }
            }
            
            // Upsert into outfit_calendar
            $stmt = $pdo->prepare('
                INSERT INTO outfit_calendar (user_id, date, outfit_history_id, outfit_details, notes)
                VALUES (:user_id, :date, :outfit_history_id, :outfit_details, :notes)
                ON DUPLICATE KEY UPDATE 
                    outfit_history_id = VALUES(outfit_history_id),
                    outfit_details = VALUES(outfit_details),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([
                'user_id' => $user_id,
                'date' => $date,
                'outfit_history_id' => $outfit_history_id,
                'outfit_details' => $outfit_details,
                'notes' => $notes
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Outfit assigned successfully']);
            break;
            
        case 'remove_outfit':
            $date = trim($_POST['date'] ?? '');
            
            if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['success' => false, 'error' => 'Invalid date']);
                exit;
            }
            
            $stmt = $pdo->prepare('DELETE FROM outfit_calendar WHERE user_id = :user_id AND date = :date');
            $stmt->execute(['user_id' => $user_id, 'date' => $date]);
            
            echo json_encode(['success' => true, 'message' => 'Outfit removed successfully']);
            break;
            
        case 'get_entry':
            $date = trim($_GET['date'] ?? '');
            
            if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['success' => false, 'error' => 'Invalid date']);
                exit;
            }
            
            $stmt = $pdo->prepare('
                SELECT c.id, c.outfit_history_id, c.outfit_details, c.notes, h.outfit_details as history_outfit_details
                FROM outfit_calendar c
                LEFT JOIN outfit_history h ON h.id = c.outfit_history_id
                WHERE c.user_id = :user_id AND c.date = :date
            ');
            $stmt->execute(['user_id' => $user_id, 'date' => $date]);
            $entry = $stmt->fetch();
            
            if ($entry) {
                $details = null;
                if ($entry['outfit_details']) {
                    $details = json_decode($entry['outfit_details'], true);
                } elseif ($entry['history_outfit_details']) {
                    $details = json_decode($entry['history_outfit_details'], true);
                }
                echo json_encode([
                    'success' => true,
                    'entry' => [
                        'id' => $entry['id'],
                        'outfit_history_id' => $entry['outfit_history_id'],
                        'notes' => $entry['notes'],
                        'outfit_details' => $details
                    ]
                ]);
            } else {
                echo json_encode(['success' => true, 'entry' => null]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
