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
    const modal = document.getElementById('outfit-modal');
    const modalClose = document.getElementById('modal-close');
    const modalDateTitle = document.getElementById('modal-date-title');
    const modalDateInput = document.getElementById('modal-date');
    const outfitSelect = document.getElementById('outfit-select');
    const outfitNotes = document.getElementById('outfit-notes');
    const assignForm = document.getElementById('outfit-assign-form');
    const removeBtn = document.getElementById('remove-outfit-btn');
    const tooltip = document.getElementById('outfit-tooltip');
    const tooltipContent = document.getElementById('tooltip-content');
    
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth() + 1;
    let calendarEntries = [];
    
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
            const primaryItem = items[0];
            dayEl.classList.add('has-outfit');
            
            if (primaryItem && primaryItem.image_path && primaryItem.image_path.length > 0) {
                const img = document.createElement('img');
                img.className = 'outfit-thumbnail';
                img.src = primaryItem.image_path;
                img.alt = primaryItem.name;
                img.loading = 'lazy';
                dayEl.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'outfit-thumbnail-placeholder';
                placeholder.textContent = '👕';
                dayEl.appendChild(placeholder);
            }

            const countBadge = document.createElement('div');
            countBadge.className = 'outfit-count-badge';
            countBadge.textContent = '×' + (items.length || 1);
            dayEl.appendChild(countBadge);
            
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
            dayEl.classList.add('empty-day');
            const addButton = document.createElement('div');
            addButton.className = 'calendar-add-button';
            addButton.setAttribute('aria-label', 'Add outfit for ' + dateStr);
            addButton.textContent = '+';
            dayEl.appendChild(addButton);
        }
        
        if (dateStr && !isOtherMonth) {
            dayEl.addEventListener('click', function() {
                openModal(dateStr, entry);
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
    
    function openModal(dateStr, existingEntry) {
        modalDateInput.value = dateStr;
        const dateObj = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        modalDateTitle.textContent = dateObj.toLocaleDateString('en-US', options);
        
        // Reset form
        outfitSelect.value = '';
        outfitNotes.value = '';
        
        if (existingEntry) {
            if (existingEntry.outfit_history_id) {
                outfitSelect.value = existingEntry.outfit_history_id;
            }
            outfitNotes.value = existingEntry.notes || '';
            removeBtn.style.display = 'inline-block';
        } else {
            removeBtn.style.display = 'none';
        }
        
        modal.classList.add('active');
        outfitSelect.focus();
    }
    
    function closeModal() {
        modal.classList.remove('active');
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
    
    async function assignOutfit(date, outfitHistoryId, notes) {
        try {
            const formData = new FormData();
            formData.append('action', 'assign_outfit');
            formData.append('date', date);
            formData.append('outfit_history_id', outfitHistoryId || '');
            formData.append('notes', notes);
            
            const response = await fetch('api/calendar.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                if (data.repeat_warning) {
                    showMessage(data.repeat_warning, 'warning');
                }
                await fetchCalendarEntries(currentYear, currentMonth);
                closeModal();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            alert('Failed to assign outfit. Please try again.');
        }
    }
    
    async function removeOutfit(date) {
        if (!confirm('Remove the outfit assignment for this date?')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'remove_outfit');
            formData.append('date', date);
            
            const response = await fetch('api/calendar.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                await fetchCalendarEntries(currentYear, currentMonth);
                closeModal();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            alert('Failed to remove outfit. Please try again.');
        }
    }
    
    function showMessage(text, type) {
        // Simple message display - could be enhanced with a toast notification
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:1rem 2rem;border-radius:8px;color:white;font-weight:500;z-index:5000;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:slideDown 0.3s ease;';
        if (type === 'warning') {
            messageDiv.style.backgroundColor = '#f39c12';
        } else {
            messageDiv.style.backgroundColor = '#27ae60';
        }
        messageDiv.textContent = text;
        document.body.appendChild(messageDiv);
        
        setTimeout(function() {
            messageDiv.style.opacity = '0';
            messageDiv.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                document.body.removeChild(messageDiv);
            }, 300);
        }, 3000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Event Listeners
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
        
        modalClose.addEventListener('click', closeModal);
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        assignForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const date = modalDateInput.value;
            const outfitId = outfitSelect.value;
            const notes = outfitNotes.value.trim();
            assignOutfit(date, outfitId, notes);
        });
        
        removeBtn.addEventListener('click', function() {
            const date = modalDateInput.value;
            removeOutfit(date);
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    }
    
    // Initialize calendar
    if (calendarGrid) {
        fetchCalendarEntries(currentYear, currentMonth);
    }
});
