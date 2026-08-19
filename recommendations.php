<?php
/**
 * AI Outfit Recommendations Page
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * AI-powered outfit suggestions based on weather and occasion.
 * Uses Ollama LLM for curated looks with vibe and styling tips.
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$user = get_logged_in_user();
$userId = $user['id'];
$occasions = [
    'Casual' => 'Casual',
    'Work/Office' => 'Work/Office',
    'Formal/Event' => 'Formal/Event',
    'Workout' => 'Workout',
    'Date Night' => 'Date Night',
    'Travel' => 'Travel',
    'Beach/Pool' => 'Beach/Pool'
];

// Get user's categories with items
$stmt = $pdo->prepare('SELECT category, COUNT(*) as count FROM clothing_items WHERE user_id = :user_id GROUP BY category');
$stmt->execute(['user_id' => $userId]);
$userCategories = $stmt->fetchAll();
$hasWardrobe = !empty($userCategories);
?>

<div class="container">
    <div class="page-header">
        <h1>AI Outfit Suggestions</h1>
        <p>Get AI-curated looks tailored to your wardrobe, weather, and occasion</p>
    </div>

    <div class="recommendations-layout">
        <!-- Generate Form -->
        <div class="card form-card">
            <h2>Create Your Look</h2>
            <form id="ai-outfit-form">
                <div class="form-group">
                    <label for="ai-city">City</label>
                    <input type="text" id="ai-city" name="city" placeholder="e.g., Nairobi" value="Nairobi">
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="include_weather" id="ai-include-weather" checked>
                        <span>Include weather data</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Occasion</label>
                    <div class="chip-group" id="occasion-chips">
                        <?php foreach ($occasions as $key => $label): ?>
                            <button type="button" class="chip" data-occasion="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="custom-occasion-row">
                        <input type="text" id="custom-occasion-input" placeholder="Custom occasion..." maxlength="40">
                        <button type="button" class="btn btn-sm btn-secondary" id="add-custom-occasion-btn">Add</button>
                    </div>
                    <input type="hidden" name="occasion" id="ai-occasion" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="ai-generate-btn" <?php echo $hasWardrobe ? '' : 'disabled'; ?>>
                    Generate AI Look
                </button>
                <?php if (!$hasWardrobe): ?>
                    <p class="form-hint">Add items to your wardrobe first to generate suggestions.</p>
                <?php endif; ?>
            </form>
        </div>

        <!-- Result -->
        <div class="ai-result-panel" id="ai-result-panel">
            <div class="card empty-card" id="ai-empty-state">
                <h2>No Look Generated Yet</h2>
                <p>Pick an occasion and click "Generate AI Look" to get a curated outfit suggestion based on your wardrobe and weather.</p>
            </div>

            <div class="ai-loading" id="ai-loading" style="display: none;">
                <div class="spinner"></div>
                <p>Styling your perfect look...</p>
            </div>

            <div class="ai-result-content" id="ai-result-content" style="display: none;">
                <div class="card result-card">
                    <div class="ai-result-header">
                        <h2>The Look</h2>
                        <span class="ai-badge" id="ai-badge">AI Generated</span>
                    </div>
                    <div class="outfit-items" id="ai-outfit-items"></div>
                    <div class="weather-badge" id="ai-weather-badge" style="display: none;"></div>
                </div>

                <div class="card vibe-card">
                    <h3>The Vibe</h3>
                    <p id="ai-vibe-text"></p>
                </div>

                <div class="card styling-tip-card">
                    <h3>Styling Tip</h3>
                    <p id="ai-styling-tip-text"></p>
                </div>

                <div class="ai-result-actions">
                    <button class="btn btn-secondary" id="ai-save-btn">Save to Calendar</button>
                </div>
            </div>

            <div class="alert alert-error" id="ai-error" style="display: none;"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('ai-outfit-form');
    const occasionInput = document.getElementById('ai-occasion');
    const chips = document.querySelectorAll('#occasion-chips .chip');
    const generateBtn = document.getElementById('ai-generate-btn');
    const emptyState = document.getElementById('ai-empty-state');
    const loadingState = document.getElementById('ai-loading');
    const resultContent = document.getElementById('ai-result-content');
    const errorState = document.getElementById('ai-error');
    const outfitItemsContainer = document.getElementById('ai-outfit-items');
    const vibeText = document.getElementById('ai-vibe-text');
    const stylingTipText = document.getElementById('ai-styling-tip-text');
    const weatherBadge = document.getElementById('ai-weather-badge');
    const aiBadge = document.getElementById('ai-badge');
    const saveBtn = document.getElementById('ai-save-btn');
    
    let selectedOccasion = '';
    let currentOutfitId = null;
    let currentOutfitDetails = null;

    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            chips.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            selectedOccasion = this.dataset.occasion;
            occasionInput.value = selectedOccasion;
        });
    });

    const customInput = document.getElementById('custom-occasion-input');
    const addCustomBtn = document.getElementById('add-custom-occasion-btn');

    function addCustomOccasion() {
        const value = customInput.value.trim();
        if (!value) return;
        
        // Remove selected from preset chips
        chips.forEach(c => c.classList.remove('selected'));
        
        // Check if this custom occasion already exists
        const existing = document.querySelector(`#occasion-chips .chip[data-occasion="${value}"]`);
        if (existing) {
            existing.classList.add('selected');
            selectedOccasion = value;
            occasionInput.value = value;
            customInput.value = '';
            return;
        }
        
        // Create new chip
        const newChip = document.createElement('button');
        newChip.type = 'button';
        newChip.className = 'chip selected';
        newChip.dataset.occasion = value;
        newChip.textContent = value;
        
        newChip.addEventListener('click', function() {
            document.querySelectorAll('#occasion-chips .chip').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            selectedOccasion = this.dataset.occasion;
            occasionInput.value = selectedOccasion;
        });
        
        document.getElementById('occasion-chips').appendChild(newChip);
        selectedOccasion = value;
        occasionInput.value = value;
        customInput.value = '';
    }

    addCustomBtn.addEventListener('click', addCustomOccasion);
    customInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addCustomOccasion();
        }
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!selectedOccasion) {
            showError('Please select an occasion');
            return;
        }

        showLoading();
        
        const formData = new FormData();
        formData.append('city', document.getElementById('ai-city').value || 'Nairobi');
        formData.append('occasion', selectedOccasion);
        formData.append('include_weather', document.getElementById('ai-include-weather').checked ? '1' : '0');

        try {
            const response = await fetch('api/ai-outfit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    city: document.getElementById('ai-city').value || 'Nairobi',
                    occasion: selectedOccasion,
                    include_weather: document.getElementById('ai-include-weather').checked
                })
            });

            const data = await response.json();

            if (!data.success || !data.data) {
                throw new Error(data.error || 'Failed to generate outfit');
            }

            currentOutfitId = data.data.id;
            currentOutfitDetails = data.data;
            
            renderResult(data.data, data.ai_generated);
            showResult();
        } catch (err) {
            showError(err.message || 'Something went wrong. Please try again.');
        }
    });

    function renderResult(data, aiGenerated) {
        aiBadge.textContent = aiGenerated ? 'AI Generated' : 'Smart Suggestion';
        aiBadge.className = aiGenerated ? 'ai-badge ai' : 'ai-badge fallback';
        
        outfitItemsContainer.innerHTML = '';
        if (data.items && Array.isArray(data.items)) {
            data.items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'outfit-item-card';
                
                if (item.image_path && item.image_path.trim() !== '') {
                    card.innerHTML = `
                        <img src="${escapeHtml(item.image_path)}" alt="${escapeHtml(item.name)}" onerror="this.parentElement.querySelector('.item-icon').style.display='flex'; this.style.display='none';">
                        <div class="item-icon" style="display:none;">👕</div>
                        <div class="outfit-item-info">
                            <h4>${escapeHtml(item.name)}</h4>
                            <p>${escapeHtml(item.category)} | ${escapeHtml(item.color)}</p>
                        </div>
                    `;
                } else {
                    card.innerHTML = `
                        <div class="item-icon">👕</div>
                        <div class="outfit-item-info">
                            <h4>${escapeHtml(item.name)}</h4>
                            <p>${escapeHtml(item.category)} | ${escapeHtml(item.color)}</p>
                        </div>
                    `;
                }
                
                outfitItemsContainer.appendChild(card);
            });
        }
        
        if (data.weather) {
            weatherBadge.style.display = 'block';
            weatherBadge.innerHTML = `🌤️ ${escapeHtml(data.weather.temperature)}°C - ${escapeHtml(data.weather.description)}`;
        } else {
            weatherBadge.style.display = 'none';
        }
        
        vibeText.textContent = data.vibe || 'A stylish look';
        stylingTipText.textContent = data.styling_tip || 'Rock it with confidence!';
    }

    saveBtn.addEventListener('click', async function() {
        if (!currentOutfitId) return;
        
        const formData = new FormData();
        formData.append('action', 'assign_outfit');
        formData.append('outfit_history_id', currentOutfitId);
        formData.append('date', new Date().toISOString().split('T')[0]);
        formData.append('notes', 'AI suggestion: ' + (currentOutfitDetails?.occasion || ''));
        
        try {
            const response = await fetch('api/calendar.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                saveBtn.textContent = 'Saved!';
                saveBtn.disabled = true;
                saveBtn.classList.add('btn-success');
            } else {
                alert(data.error || 'Failed to save');
            }
        } catch (err) {
            alert('Failed to save to calendar');
        }
    });

    function showLoading() {
        emptyState.style.display = 'none';
        resultContent.style.display = 'none';
        errorState.style.display = 'none';
        loadingState.style.display = 'flex';
        generateBtn.disabled = true;
        generateBtn.textContent = 'Generating...';
    }

    function showResult() {
        loadingState.style.display = 'none';
        emptyState.style.display = 'none';
        errorState.style.display = 'none';
        resultContent.style.display = 'block';
        generateBtn.disabled = false;
        generateBtn.textContent = 'Generate AI Look';
    }

    function showError(message) {
        loadingState.style.display = 'none';
        resultContent.style.display = 'none';
        emptyState.style.display = 'none';
        errorState.style.display = 'block';
        errorState.textContent = message;
        generateBtn.disabled = false;
        generateBtn.textContent = 'Generate AI Look';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
