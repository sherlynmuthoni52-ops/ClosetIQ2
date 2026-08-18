<?php
/**
 * Calendar API
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Handles calendar-related AJAX requests:
 * - Fetch calendar entries for a month
 * - Assign outfit to a date
 * - Remove outfit from a date
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
                SELECT c.id, c.date, c.outfit_history_id, c.notes, c.created_at,
                       h.outfit_details, h.weather_data
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
            
            $formatted = [];
            foreach ($entries as $entry) {
                $details = null;
                if ($entry['outfit_details']) {
                    $details = json_decode($entry['outfit_details'], true);
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
            
        case 'assign_outfit':
            $date = trim($_POST['date'] ?? '');
            $outfit_history_id = !empty($_POST['outfit_history_id']) ? (int) $_POST['outfit_history_id'] : null;
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['success' => false, 'error' => 'Invalid date']);
                exit;
            }
            
            // Verify outfit_history_id belongs to user if provided
            if ($outfit_history_id) {
                $stmt = $pdo->prepare('SELECT id FROM outfit_history WHERE id = :id AND user_id = :user_id');
                $stmt->execute(['id' => $outfit_history_id, 'user_id' => $user_id]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Invalid outfit']);
                    exit;
                }
            }
            
            // Check for repeat outfits - find if any item in the selected outfit was recently worn
            $repeat_warning = null;
            if ($outfit_history_id) {
                $stmt = $pdo->prepare('SELECT outfit_details FROM outfit_history WHERE id = :id');
                $stmt->execute(['id' => $outfit_history_id]);
                $outfit_row = $stmt->fetch();
                
                if ($outfit_row) {
                    $outfit_data = json_decode($outfit_row['outfit_details'], true);
                    if ($outfit_data && isset($outfit_data['items']) && is_array($outfit_data['items'])) {
                        $item_ids = array_column($outfit_data['items'], 'id');
                        
                        if (!empty($item_ids)) {
                            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                            $sql = "
                                SELECT DISTINCT h.id, h.created_at, h.outfit_details
                                FROM outfit_history h
                                JOIN outfit_calendar c ON c.outfit_history_id = h.id
                                WHERE h.user_id = ? 
                                  AND c.date < ?
                                  AND h.id != ?
                            ";
                            $params = array_merge([$user_id, $date, $outfit_history_id]);
                            
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $recent_history = $stmt->fetchAll();
                            
                            $repeated_items = [];
                            foreach ($recent_history as $recent) {
                                $recent_data = json_decode($recent['outfit_details'], true);
                                if ($recent_data && isset($recent_data['items']) && is_array($recent_data['items'])) {
                                    $recent_item_ids = array_column($recent_data['items'], 'id');
                                    $common = array_intersect($item_ids, $recent_item_ids);
                                    if (!empty($common)) {
                                        $repeated_items[] = [
                                            'date' => $recent['created_at'],
                                            'items' => array_map('intval', $common)
                                        ];
                                    }
                                }
                            }
                            
                            if (!empty($repeated_items)) {
                                $repeat_warning = 'Some items from this outfit were recently worn on other dates.';
                            }
                        }
                    }
                }
            }
            
            // Upsert into outfit_calendar
            $stmt = $pdo->prepare('
                INSERT INTO outfit_calendar (user_id, date, outfit_history_id, notes)
                VALUES (:user_id, :date, :outfit_history_id, :notes)
                ON DUPLICATE KEY UPDATE 
                    outfit_history_id = VALUES(outfit_history_id),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([
                'user_id' => $user_id,
                'date' => $date,
                'outfit_history_id' => $outfit_history_id,
                'notes' => $notes
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Outfit assigned successfully',
                'repeat_warning' => $repeat_warning
            ]);
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
                SELECT c.id, c.outfit_history_id, c.notes, h.outfit_details
                FROM outfit_calendar c
                LEFT JOIN outfit_history h ON h.id = c.outfit_history_id
                WHERE c.user_id = :user_id AND c.date = :date
            ');
            $stmt->execute(['user_id' => $user_id, 'date' => $date]);
            $entry = $stmt->fetch();
            
            if ($entry) {
                $details = $entry['outfit_details'] ? json_decode($entry['outfit_details'], true) : null;
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
