<?php
/**
 * Wardrobe Page
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Allows users to add, view, edit, and delete clothing items.
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$user = get_logged_in_user();
$userId = $user['id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new item
    if (isset($_POST['add_item'])) {
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $color = trim($_POST['color']);
        $size = trim($_POST['size']);
        $season = $_POST['season'];
        
        if (empty($name) || empty($category) || empty($color)) {
            $error = 'Please fill in all required fields.';
        } else {
            $imagePath = null;
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($_FILES['image']['tmp_name']);
                
                if (in_array($fileType, $allowedTypes)) {
                    $uploadDir = 'uploads/' . $userId . '/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $ext;
                    $imagePath = $uploadDir . $filename;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                        $error = 'Failed to upload image.';
                    }
                } else {
                    $error = 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
                }
            }
            
            if (!$error) {
                $stmt = $pdo->prepare('INSERT INTO clothing_items (user_id, name, category, color, size, season, image_path) VALUES (:user_id, :name, :category, :color, :size, :season, :image_path)');
                if ($stmt->execute([
                    'user_id' => $userId,
                    'name' => $name,
                    'category' => $category,
                    'color' => $color,
                    'size' => $size ?: null,
                    'season' => $season,
                    'image_path' => $imagePath
                ])) {
                    $message = 'Item added successfully!';
                } else {
                    $error = 'Failed to add item.';
                }
            }
        }
    }
    
    // Delete item
    if (isset($_POST['delete_item'])) {
        $itemId = $_POST['item_id'];
        
        // Get image path to delete file
        $stmt = $pdo->prepare('SELECT image_path FROM clothing_items WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $itemId, 'user_id' => $userId]);
        $item = $stmt->fetch();
        
        if ($item && $item['image_path'] && file_exists($item['image_path'])) {
            unlink($item['image_path']);
        }
        
        $stmt = $pdo->prepare('DELETE FROM clothing_items WHERE id = :id AND user_id = :user_id');
        if ($stmt->execute(['id' => $itemId, 'user_id' => $userId])) {
            $message = 'Item deleted successfully!';
        } else {
            $error = 'Failed to delete item.';
        }
    }
}

// Get all clothing items for this user
$stmt = $pdo->prepare('SELECT * FROM clothing_items WHERE user_id = :user_id ORDER BY created_at DESC');
$stmt->execute(['user_id' => $userId]);
$clothingItems = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>My Wardrobe</h1>
        <p>Manage your clothing inventory</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="wardrobe-layout">
        <!-- Add Item Form -->
        <div class="card form-card">
            <h2>Add New Item</h2>
            <form method="POST" action="wardrobe.php" enctype="multipart/form-data" id="add-item-form">
                <div class="form-group">
                    <label for="item-name">Item Name *</label>
                    <input type="text" id="item-name" name="name" required placeholder="e.g., Blue Denim Jacket">
                </div>
                <div class="form-group">
                    <label for="item-category">Category *</label>
                    <select id="item-category" name="category" required>
                        <option value="">Select category</option>
                        <option value="tops">Tops</option>
                        <option value="bottoms">Bottoms</option>
                        <option value="footwear">Footwear</option>
                        <option value="accessories">Accessories</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="item-color">Color *</label>
                    <input type="text" id="item-color" name="color" required placeholder="e.g., Blue">
                </div>
                <div class="form-group">
                    <label for="item-size">Size</label>
                    <input type="text" id="item-size" name="size" placeholder="e.g., M, L, 42">
                </div>
                <div class="form-group">
                    <label for="item-season">Season</label>
                    <select id="item-season" name="season">
                        <option value="all">All Seasons</option>
                        <option value="spring">Spring</option>
                        <option value="summer">Summer</option>
                        <option value="autumn">Autumn</option>
                        <option value="winter">Winter</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="item-image">Image</label>
                    <input type="file" id="item-image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                    <img id="image-preview" class="image-preview" style="display: none;">
                </div>
                <button type="submit" name="add_item" class="btn btn-primary">Add Item</button>
            </form>
        </div>

        <!-- Clothing List -->
        <div class="clothing-list">
            <div class="list-controls">
                <h2>My Items (<?php echo count($clothingItems); ?>)</h2>
                <select id="category-filter" class="filter-select">
                    <option value="all">All Categories</option>
                    <option value="tops">Tops</option>
                    <option value="bottoms">Bottoms</option>
                    <option value="footwear">Footwear</option>
                    <option value="accessories">Accessories</option>
                </select>
            </div>

            <?php if (empty($clothingItems)): ?>
                <div class="empty-state">
                    <p>Your wardrobe is empty. Add your first item using the form!</p>
                </div>
            <?php else: ?>
                <div class="items-grid" id="items-grid">
                    <?php foreach ($clothingItems as $item): ?>
                        <div class="clothing-item" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                            <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                            <?php else: ?>
                                <div class="item-image-placeholder">👕</div>
                            <?php endif; ?>
                            <div class="item-info">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="item-category"><?php echo ucfirst($item['category']); ?></p>
                                <p class="item-details"><?php echo htmlspecialchars($item['color']); ?><?php echo $item['size'] ? ' / ' . htmlspecialchars($item['size']) : ''; ?></p>
                                <p class="item-season"><?php echo ucfirst($item['season']); ?></p>
                            </div>
                            <form method="POST" action="wardrobe.php" class="item-actions" onsubmit="return confirm('Delete this item?');">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" name="delete_item" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
