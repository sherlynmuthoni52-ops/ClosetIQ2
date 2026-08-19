/**
 * ClosetIQ - Main JavaScript
 * Handles client-side interactivity and form validation.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
        });
    }

    // Image preview for wardrobe form
    const imageInput = document.getElementById('item-image');
    const imagePreview = document.getElementById('image-preview');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Category filter on wardrobe page
    const categoryFilter = document.getElementById('category-filter');
    const itemsGrid = document.getElementById('items-grid');
    
    if (categoryFilter && itemsGrid) {
        categoryFilter.addEventListener('change', function() {
            const selectedCategory = this.value;
            const items = itemsGrid.querySelectorAll('.clothing-item');
            
            items.forEach(function(item) {
                const category = item.getAttribute('data-category');
                if (selectedCategory === 'all' || category === selectedCategory) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Client-side form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(function(input) {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#e74c3c';
                    
                    // Remove error styling on input
                    input.addEventListener('input', function() {
                        this.style.borderColor = '#ddd';
                    }, { once: true });
                }
            });
            
            // Email validation
            const emailInput = form.querySelector('input[type="email"]');
            if (emailInput && emailInput.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value)) {
                    isValid = false;
                    emailInput.style.borderColor = '#e74c3c';
                }
            }
            
            // Password match validation
            const passwordInput = form.querySelector('input[name="password"]');
            const confirmInput = form.querySelector('input[name="confirm_password"]');
            if (passwordInput && confirmInput && passwordInput.value !== confirmInput.value) {
                isValid = false;
                confirmInput.style.borderColor = '#e74c3c';
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
    });

    // Confirm delete actions
    const deleteForms = document.querySelectorAll('form[onsubmit*="confirm"]');
    deleteForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    // Calendar functionality
    const calendarGrid = document.getElementById('calendar-grid');
    const monthYearLabel = document.getElementById('calendar-month-year');
    const prevBtn = document.getElementById('prev-month');
    const nextBtn = document.getElementById('next-month');
    const todayBtn = document.getElementById('today-btn');
    const bottomSheetOverlay = document.getElementById('bottom-sheet-overlay');
    const bottomSheet = document.getElementById('bottom-sheet');
    const sheetClose = document.getElementById('sheet-close');
    const sheetDateTitle = document.getElementById('sheet-date-title');
    const sheetItems = document.getElementById('sheet-items');
    const sheetSaveBtn = document.getElementById('sheet-save-btn');
    const sheetRemoveBtn = document.getElementById('sheet-remove-btn');
    const sheetTabs = document.querySelectorAll('.sheet-tab');
    const tooltip = document.getElementById('outfit-tooltip');
    const tooltipContent = document.getElementById('tooltip-content');
    
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth() + 1;
    let calendarEntries = [];
    let selectedItems = [];
    let currentSheetDate = null;
    let currentCategory = 'all';
    let wardrobeItemsByCategory = {};
    
    function renderCalendar(year, month) {
        if (!calendarGrid) return;
        
        calendarGrid.innerHTML = '';
        
        const firstDay = new Date(year, month - 1, 1).getDay();
        const daysInMonth = new Date(year, month, 0).getDate();
        const daysInPrevMonth = new Date(year, month - 1, 0).getDate();
        const today = new Date();
        const todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        
        // Update header
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        monthYearLabel.textContent = monthNames[month - 1] + ' ' + year;
        
        // Create lookup map for entries
        const entryMap = {};
        calendarEntries.forEach(function(entry) {
            entryMap[entry.date] = entry;
        });
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const dayEl = createDayElement(dayNum, true, year, month - 1);
            calendarGrid.appendChild(dayEl);
        }
        
        // Current month days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const isToday = dateStr === todayStr;
            const hasOutfit = entryMap.hasOwnProperty(dateStr);
            const dayEl = createDayElement(day, false, year, month, isToday, hasOutfit, dateStr, entryMap[dateStr]);
            calendarGrid.appendChild(dayEl);
        }
        
        // Next month days
        const totalCells = firstDay + daysInMonth;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 1; i <= remainingCells; i++) {
            const dayEl = createDayElement(i, true, year, month + 1);
            calendarGrid.appendChild(dayEl);
        }
    }
    
    function createDayElement(dayNum, isOtherMonth, year, month, isToday, hasOutfit, dateStr, entry) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day';
        if (isOtherMonth) dayEl.classList.add('other-month');
        if (isToday) dayEl.classList.add('today');
        if (hasOutfit) dayEl.classList.add('has-outfit');
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = dayNum;
        dayEl.appendChild(dayNumber);
        
        if (hasOutfit && entry && entry.outfit_details) {
            const items = entry.outfit_details.items || [];
            const maxVisible = 3;
            const visibleItems = items.slice(0, maxVisible);
            
            if (visibleItems.length === 1) {
                const primaryItem = visibleItems[0];
                const wrapper = document.createElement('div');
                wrapper.className = 'outfit-single';
                
                if (primaryItem.image_path && primaryItem.image_path.length > 0) {
                    const img = document.createElement('img');
                    img.className = 'outfit-single-img';
                    img.src = primaryItem.image_path;
                    img.alt = primaryItem.name;
                    img.loading = 'lazy';
                    wrapper.appendChild(img);
                } else {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'outfit-placeholder';
                    placeholder.textContent = '👕';
                    wrapper.appendChild(placeholder);
                }
                
                if (primaryItem.wear_count > 0) {
                    const badge = document.createElement('div');
                    badge.className = 'wear-badge';
                    badge.textContent = '×' + primaryItem.wear_count;
                    wrapper.appendChild(badge);
                }
                
                dayEl.appendChild(wrapper);
            } else if (visibleItems.length >= 2) {
                const stack = document.createElement('div');
                stack.className = 'outfit-stack';
                
                visibleItems.forEach(function(item) {
                    if (item.image_path && item.image_path.length > 0) {
                        const img = document.createElement('img');
                        img.className = 'outfit-stack-img';
                        img.src = item.image_path;
                        img.alt = item.name;
                        img.loading = 'lazy';
                        stack.appendChild(img);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'outfit-stack-more';
                        placeholder.textContent = '👕';
                        stack.appendChild(placeholder);
                    }
                });
                
                if (items.length > maxVisible) {
                    const more = document.createElement('div');
                    more.className = 'outfit-stack-more';
                    more.textContent = '+' + (items.length - maxVisible);
                    stack.appendChild(more);
                }
                
                const maxWear = Math.max.apply(null, items.map(function(i) { return i.wear_count || 0; }));
                if (maxWear > 0) {
                    const badge = document.createElement('div');
                    badge.className = 'outfit-mini-badge';
                    badge.textContent = '×' + maxWear + ' worn';
                    dayEl.appendChild(badge);
                }
                
                dayEl.appendChild(stack);
            }
            
            // Tooltip on hover
            dayEl.addEventListener('mouseenter', function(e) {
                const desc = entry.outfit_details.description || 'Assigned outfit';
                const itemNames = items.map(function(item) { return item.name; }).join(', ');
                tooltipContent.innerHTML = '<h4>Outfit Assigned</h4><p>' + escapeHtml(desc) + '</p><p style="font-size:0.85rem;opacity:0.8">' + escapeHtml(itemNames) + '</p>';
                positionTooltip(e, tooltip);
                tooltip.classList.add('active');
            });
            
            dayEl.addEventListener('mouseleave', function() {
                tooltip.classList.remove('active');
            });
        } else if (!isOtherMonth && dateStr) {
            const addSlot = document.createElement('div');
            addSlot.className = 'empty-slot';
            addSlot.textContent = '+';
            addSlot.setAttribute('aria-label', 'Add outfit for ' + dateStr);
            dayEl.appendChild(addSlot);
        }
        
        if (dateStr && !isOtherMonth) {
            dayEl.addEventListener('click', function() {
                openBottomSheet(dateStr, entry);
            });
        }
        
        return dayEl;
    }
    
    function positionTooltip(e, tooltipEl) {
        tooltipEl.classList.add('active');
        const rect = e.target.getBoundingClientRect();
        const tooltipRect = tooltipEl.getBoundingClientRect();
        
        let left = rect.left;
        let top = rect.bottom + 8;
        
        // Keep tooltip within viewport
        if (left + tooltipRect.width > window.innerWidth) {
            left = window.innerWidth - tooltipRect.width - 16;
        }
        if (top + tooltipRect.height > window.innerHeight) {
            top = rect.top - tooltipRect.height - 8;
        }
        
        tooltipEl.style.left = left + 'px';
        tooltipEl.style.top = top + 'px';
    }
    
    async function openBottomSheet(dateStr, existingEntry) {
        currentSheetDate = dateStr;
        selectedItems = [];
        
        const dateObj = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        sheetDateTitle.textContent = dateObj.toLocaleDateString('en-US', options);
        
        // Pre-populate if existing entry
        if (existingEntry && existingEntry.outfit_details && existingEntry.outfit_details.items) {
            selectedItems = existingEntry.outfit_details.items.map(function(item) { return item.id; });
            sheetRemoveBtn.style.display = 'inline-block';
        } else {
            sheetRemoveBtn.style.display = 'none';
        }
        
        // Reset tabs
        currentCategory = 'all';
        sheetTabs.forEach(function(tab) {
            tab.classList.toggle('active', tab.getAttribute('data-category') === 'all');
        });
        
        // Show sheet
        bottomSheetOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Fetch wardrobe items
        await fetchWardrobeItems('all');
        
        // Focus management
        setTimeout(function() {
            const firstTab = document.querySelector('.sheet-tab');
            if (firstTab) firstTab.focus();
        }, 100);
    }
    
    function closeBottomSheet() {
        bottomSheetOverlay.classList.remove('active');
        document.body.style.overflow = '';
        currentSheetDate = null;
        selectedItems = [];
    }
    
    async function fetchWardrobeItems(category) {
        sheetItems.innerHTML = '<div class="loading">Loading your wardrobe...</div>';
        
        try {
            const response = await fetch('api/calendar.php?action=get_wardrobe_items');
            const data = await response.json();
            
            if (data.success) {
                wardrobeItemsByCategory = data.items;
                renderSheetItems(category);
            } else {
                sheetItems.innerHTML = '<div class="error">Failed to load wardrobe items.</div>';
            }
        } catch (err) {
            sheetItems.innerHTML = '<div class="error">Failed to load wardrobe items.</div>';
        }
    }
    
    function renderSheetItems(category) {
        sheetItems.innerHTML = '';
        
        let itemsToRender = [];
        if (category === 'all') {
            Object.keys(wardrobeItemsByCategory).forEach(function(cat) {
                wardrobeItemsByCategory[cat].forEach(function(item) {
                    itemsToRender.push(item);
                });
            });
        } else if (wardrobeItemsByCategory[category]) {
            itemsToRender = wardrobeItemsByCategory[category];
        }
        
        if (itemsToRender.length === 0) {
            sheetItems.innerHTML = '<div class="empty-state">No items in this category.</div>';
            return;
        }
        
        itemsToRender.forEach(function(item) {
            const itemEl = document.createElement('div');
            itemEl.className = 'sheet-item';
            if (selectedItems.indexOf(item.id) !== -1) {
                itemEl.classList.add('selected');
            }
            itemEl.setAttribute('data-item-id', item.id);
            
            // Check indicator
            const check = document.createElement('div');
            check.className = 'sheet-item-check';
            check.textContent = '✓';
            itemEl.appendChild(check);
            
            // Image
            if (item.image_path && item.image_path.length > 0) {
                const img = document.createElement('img');
                img.className = 'sheet-item-image';
                img.src = item.image_path;
                img.alt = item.name;
                img.loading = 'lazy';
                itemEl.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'sheet-item-placeholder';
                placeholder.textContent = getCategoryIcon(item.category);
                itemEl.appendChild(placeholder);
            }
            
            // Wear count
            if (item.wear_count > 0) {
                const wear = document.createElement('div');
                wear.className = 'sheet-item-wear';
                wear.textContent = '×' + item.wear_count;
                itemEl.appendChild(wear);
            }
            
            // Info
            const info = document.createElement('div');
            info.className = 'sheet-item-info';
            
            const name = document.createElement('div');
            name.className = 'sheet-item-name';
            name.textContent = item.name;
            info.appendChild(name);
            
            const meta = document.createElement('div');
            meta.className = 'sheet-item-meta';
            meta.textContent = capitalizeFirst(item.category) + ' | ' + item.color;
            info.appendChild(meta);
            
            itemEl.appendChild(info);
            
            // Click handler
            itemEl.addEventListener('click', function() {
                toggleItemSelection(item.id);
            });
            
            sheetItems.appendChild(itemEl);
        });
    }
    
    function toggleItemSelection(itemId) {
        const index = selectedItems.indexOf(itemId);
        if (index === -1) {
            selectedItems.push(itemId);
        } else {
            selectedItems.splice(index, 1);
        }
        
        // Update UI
        const itemEl = document.querySelector('.sheet-item[data-item-id="' + itemId + '"]');
        if (itemEl) {
            itemEl.classList.toggle('selected', selectedItems.indexOf(itemId) !== -1);
        }
    }
    
    async function saveOutfit() {
        if (!currentSheetDate || selectedItems.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'assign_outfit');
            formData.append('date', currentSheetDate);
            formData.append('items', JSON.stringify(selectedItems.map(function(id) { return { id: id }; })));
            formData.append('notes', '');
            
            const response = await fetch('api/calendar.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                await fetchCalendarEntries(currentYear, currentMonth);
                closeBottomSheet();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            alert('Failed to save outfit. Please try again.');
        }
    }
    
    async function removeOutfitFromSheet() {
        if (!currentSheetDate) return;
        
        if (!confirm('Remove the outfit assignment for this date?')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'remove_outfit');
            formData.append('date', currentSheetDate);
            
            const response = await fetch('api/calendar.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                await fetchCalendarEntries(currentYear, currentMonth);
                closeBottomSheet();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            alert('Failed to remove outfit. Please try again.');
        }
    }
    
    function getCategoryIcon(category) {
        switch (category) {
            case 'tops': return '👕';
            case 'bottoms': return '👖';
            case 'footwear': return '👟';
            case 'accessories': return '👜';
            default: return '👔';
        }
    }
    
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    async function fetchCalendarEntries(year, month) {
        try {
            calendarGrid.style.opacity = '0.5';
            
            const response = await fetch('api/calendar.php?action=get_month&year=' + year + '&month=' + month);
            const data = await response.json();
            
            calendarGrid.style.opacity = '1';
            
            if (data.success) {
                calendarEntries = data.entries;
                renderCalendar(year, month);
            }
        } catch (err) {
            console.error('Failed to load calendar entries:', err);
            calendarGrid.style.opacity = '1';
        }
    }
    
    // Bottom Sheet Event Listeners
    if (calendarGrid) {
        prevBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            fetchCalendarEntries(currentYear, currentMonth);
        });
        
        nextBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            fetchCalendarEntries(currentYear, currentMonth);
        });
        
        if (todayBtn) {
            todayBtn.addEventListener('click', function() {
                const today = new Date();
                currentYear = today.getFullYear();
                currentMonth = today.getMonth() + 1;
                fetchCalendarEntries(currentYear, currentMonth);
            });
        }
        
        sheetClose.addEventListener('click', closeBottomSheet);
        
        bottomSheetOverlay.addEventListener('click', function(e) {
            if (e.target === bottomSheetOverlay) {
                closeBottomSheet();
            }
        });
        
        sheetSaveBtn.addEventListener('click', saveOutfit);
        
        sheetRemoveBtn.addEventListener('click', removeOutfitFromSheet);
        
        sheetTabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                currentCategory = category;
                
                sheetTabs.forEach(function(t) {
                    t.classList.toggle('active', t === tab);
                });
                
                renderSheetItems(category);
            });
        });
        
        // Swipe down to close
        let touchStartY = 0;
        bottomSheet.addEventListener('touchstart', function(e) {
            touchStartY = e.touches[0].clientY;
        });
        
        bottomSheet.addEventListener('touchmove', function(e) {
            const touchY = e.touches[0].clientY;
            const diff = touchY - touchStartY;
            if (diff > 100) {
                closeBottomSheet();
            }
        });
        
        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && bottomSheetOverlay.classList.contains('active')) {
                closeBottomSheet();
            }
        });
    }
    
    // Initialize calendar
    if (calendarGrid) {
        fetchCalendarEntries(currentYear, currentMonth);
    }
});
