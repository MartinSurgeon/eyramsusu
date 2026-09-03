// assets/js/app.js - Interactive helpers for Eyram Susu

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live Deposit Calculator
    initDepositCalculator();

    // 2. Instant Customer Search Filter
    initCustomerFilter();

    // 3. Auto-dismiss Flash Toasts
    initFlashMessages();

    // 4. Form Validation & Loading States
    initFormValidation();

    // 5. Password Visibility Toggle
    initPasswordToggle();

    // 6. Expandable Helpers
    initExpandables();

    // 7. In-App Notification Center
    initNotificationCenter();
});

/**
 * Live Deposit Calculator Preview
 * Fulfills Tesler's Law & Hick's Law: Instant visual breakdown without mental math
 */
function initDepositCalculator() {
    const cashInput = document.getElementById('cash_paid');
    if (!cashInput) return;

    const dailyAmountEl = document.getElementById('daily_amount');
    const currentChangeEl = document.getElementById('current_change');
    const spacesFilledEl = document.getElementById('spaces_filled');
    const totalSpacesEl = document.getElementById('total_spaces');

    const previewContainer = document.getElementById('calculation_preview');
    const previewSpaces = document.getElementById('preview_spaces');
    const previewRange = document.getElementById('preview_range');
    const previewApplied = document.getElementById('preview_applied');
    const previewChange = document.getElementById('preview_change');
    const previewAlert = document.getElementById('preview_alert');

    function updatePreview() {
        const dailyAmount = parseFloat(dailyAmountEl ? dailyAmountEl.value : 0) || 0;
        const currentChange = parseFloat(currentChangeEl ? currentChangeEl.value : 0) || 0;
        const spacesFilled = parseInt(spacesFilledEl ? spacesFilledEl.value : 0) || 0;
        const totalSpaces = parseInt(totalSpacesEl ? totalSpacesEl.value : 31) || 31;
        const cashPaid = parseFloat(cashInput.value) || 0;
        const remainderNotice = document.getElementById('remainder_error_msg');
        const remainderText = document.getElementById('remainder_error_text');
        const submitBtn = document.querySelector('form button[type="submit"]');

        if (dailyAmount <= 0 || cashPaid <= 0) {
            if (previewContainer) previewContainer.classList.add('hidden');
            if (remainderNotice) remainderNotice.classList.add('hidden');
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        // Strict zero-remainder check
        const remainder = Math.round((cashPaid % dailyAmount) * 100) / 100;
        const isDivisible = (remainder < 0.01 || Math.abs(remainder - dailyAmount) < 0.01);

        if (!isDivisible) {
            const lowerMultiple = Math.floor(cashPaid / dailyAmount) * dailyAmount;
            const upperMultiple = Math.ceil(cashPaid / dailyAmount) * dailyAmount;
            if (remainderNotice && remainderText) {
                remainderNotice.classList.remove('hidden');
                if (lowerMultiple > 0) {
                    remainderText.textContent = `Please enter GH₵ ${lowerMultiple.toFixed(2)} or GH₵ ${upperMultiple.toFixed(2)}.`;
                } else {
                    remainderText.textContent = `Please enter at least GH₵ ${upperMultiple.toFixed(2)}.`;
                }
            }
            if (previewContainer) previewContainer.classList.add('hidden');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        const spacesRemaining = Math.max(0, totalSpaces - spacesFilled);
        const spacesToFill = Math.round(cashPaid / dailyAmount);

        if (spacesToFill > spacesRemaining) {
            const maxAllowed = (spacesRemaining * dailyAmount).toFixed(2);
            if (remainderNotice && remainderText) {
                remainderNotice.classList.remove('hidden');
                remainderText.textContent = `Only ${spacesRemaining} space(s) left on this card. Maximum allowed is GH₵ ${maxAllowed}.`;
            }
            if (previewContainer) previewContainer.classList.add('hidden');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        // All checks pass
        if (remainderNotice) remainderNotice.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = false;
        if (previewContainer) previewContainer.classList.remove('hidden');

        const moneyApplied = spacesToFill * dailyAmount;
        const newChange = currentChange;

        if (previewSpaces) {
            previewSpaces.textContent = spacesToFill + (spacesToFill === 1 ? ' space' : ' spaces');
            previewSpaces.className = 'px-2 py-0.5 rounded text-[11px] font-black ';
            if (spacesFilled + spacesToFill >= totalSpaces) {
                previewSpaces.className += 'bg-emerald-600 text-white'; // Card completes
            } else {
                previewSpaces.className += 'bg-steel_azure text-white'; // Normal
            }
        }

        if (previewRange) {
            if (spacesToFill > 0) {
                const start = spacesFilled + 1;
                const end = spacesFilled + spacesToFill;
                previewRange.textContent = start === end ? `(Space #${start})` : `(Spaces #${start} to #${end})`;
            }
        }

        if (previewApplied) {
            previewApplied.textContent = 'GH₵ ' + moneyApplied.toFixed(2);
        }

        if (previewChange) {
            previewChange.textContent = 'GH₵ ' + newChange.toFixed(2);
        }

        if (previewAlert) {
            if (spacesFilled + spacesToFill >= totalSpaces) {
                previewAlert.classList.remove('hidden');
                previewAlert.className = 'text-xs font-bold text-emerald-800 bg-emerald-100 p-2 rounded-lg mt-1';
                previewAlert.textContent = '🎉 This deposit will complete all 31 spaces on this Susu Card!';
            } else {
                previewAlert.classList.add('hidden');
            }
        }
    }

    cashInput.addEventListener('input', updatePreview);
    cashInput.addEventListener('change', updatePreview);

    // Preset buttons
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const multiplier = parseFloat(btn.getAttribute('data-mult')) || 1;
            const dailyAmount = parseFloat(dailyAmountEl ? dailyAmountEl.value : 0) || 0;
            if (dailyAmount > 0) {
                cashInput.value = (dailyAmount * multiplier).toFixed(2);
                document.querySelectorAll('.preset-btn').forEach(b => {
                    b.classList.remove('border-pumpkin_spice', 'bg-orange-50', 'text-pumpkin_spice');
                    b.classList.add('border-silver-600', 'bg-white', 'text-slate-700');
                });
                btn.classList.add('border-pumpkin_spice', 'bg-orange-50', 'text-pumpkin_spice');
                btn.classList.remove('border-silver-600', 'bg-white', 'text-slate-700');
                updatePreview();
            }
        });
    });

    // Run initial update in case field is pre-filled
    updatePreview();
}

/**
 * Real-time instant search filter for customer lists
 */
function initCustomerFilter() {
    const searchInput = document.getElementById('customer_search');
    if (!searchInput) return;

    const rows = document.querySelectorAll('.customer-row');
    const emptyNotice = document.getElementById('search_empty_notice');

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.getAttribute('data-search') || row.textContent.toLowerCase();
            if (text.toLowerCase().includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (emptyNotice) {
            if (visibleCount === 0 && rows.length > 0) {
                emptyNotice.classList.remove('hidden');
            } else {
                emptyNotice.classList.add('hidden');
            }
        }
    });
}

/**
 * Form Validation & Loading States
 * Shows clear error/success states on fields (HCI rule)
 * Prevents double-submit with loading spinner (Fitts's Law)
 */
function initFormValidation() {
    // Add field-level validation on blur
    document.querySelectorAll('form input[required], form select[required]').forEach(field => {
        field.addEventListener('blur', () => {
            validateField(field);
        });

        field.addEventListener('input', () => {
            // Clear error state when user starts typing
            if (field.classList.contains('field-error')) {
                field.classList.remove('field-error', 'field-shake');
                const errMsg = field.parentElement.querySelector('.field-error-msg');
                if (errMsg) errMsg.remove();
            }
        });
    });

    // Prevent double-submit with loading state
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Validate all required fields
            let hasError = false;
            form.querySelectorAll('input[required], select[required]').forEach(field => {
                if (!validateField(field)) {
                    hasError = true;
                }
            });

            if (hasError) {
                e.preventDefault();
                return;
            }

            // Apply loading state to submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('btn-loading')) {
                submitBtn.classList.add('btn-loading');
                
                // Store original text
                const originalText = submitBtn.innerHTML;
                submitBtn.setAttribute('data-original-text', originalText);

                // Defer disabling slightly to allow browser to dispatch native submit
                setTimeout(() => {
                    submitBtn.disabled = true;
                }, 50);

                // Safety: re-enable after 10 seconds in case of network issues
                setTimeout(() => {
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 10000);
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    const isSelect = field.tagName === 'SELECT';

    if (field.hasAttribute('required') && (!value || (isSelect && !value))) {
        field.classList.add('field-error', 'field-shake');
        field.classList.remove('field-success');

        // Remove existing error message
        const existingErr = field.parentElement.querySelector('.field-error-msg');
        if (existingErr) existingErr.remove();

        // Add error message
        const errEl = document.createElement('div');
        errEl.className = 'field-error-msg';
        errEl.innerHTML = '<span>⚠</span> This field is required';
        field.parentElement.appendChild(errEl);

        // Remove shake after animation completes
        setTimeout(() => field.classList.remove('field-shake'), 400);
        return false;
    } else if (value) {
        field.classList.remove('field-error');
        field.classList.add('field-success');
        const existingErr = field.parentElement.querySelector('.field-error-msg');
        if (existingErr) existingErr.remove();

        // Remove success ring after a moment
        setTimeout(() => field.classList.remove('field-success'), 1500);
    }

    return true;
}

/**
 * Password Visibility Toggle for Login Page
 */
function initPasswordToggle() {
    const passwordField = document.getElementById('password');
    if (!passwordField) return;

    const wrapper = passwordField.parentElement;
    wrapper.style.position = 'relative';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-toggle';
    toggle.setAttribute('aria-label', 'Toggle password visibility');
    toggle.innerHTML = `
        <svg class="eye-open" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <svg class="eye-closed" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        </svg>
    `;

    toggle.addEventListener('click', () => {
        const isPassword = passwordField.type === 'password';
        passwordField.type = isPassword ? 'text' : 'password';
        toggle.querySelector('.eye-open').style.display = isPassword ? 'none' : 'block';
        toggle.querySelector('.eye-closed').style.display = isPassword ? 'block' : 'none';
    });

    wrapper.appendChild(toggle);
}

/**
 * Expandable/Collapsible Helpers (Tesler's Law)
 */
function initExpandables() {
    document.querySelectorAll('.expandable-trigger').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const targetId = trigger.getAttribute('data-target');
            const content = document.getElementById(targetId);
            if (!content) return;

            const isOpen = content.classList.contains('open');
            content.classList.toggle('open');
            trigger.classList.toggle('open');
        });
    });
}

/**
 * Toast notifications
 */
function showToast(type, message) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-red-600' : 'bg-steel_azure';
    toast.className = 'toast text-white ' + bgClass;

    const icons = { success: '✓', error: '⚠️', info: 'ℹ️' };
    const icon = icons[type] || 'ℹ️';
    toast.innerHTML = `<span class="text-lg font-bold">${icon}</span> <div class="flex-1 font-medium">${message}</div>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(12px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function initFlashMessages() {
    const flashMessages = document.querySelectorAll('.flash-toast-data');
    flashMessages.forEach(el => {
        const type = el.getAttribute('data-type') || 'info';
        const msg = el.getAttribute('data-message') || '';
        if (msg) {
            showToast(type, msg);
        }
    });
}

/**
 * In-App Notification Center
 * Handles interactive drawer toggle, mark-as-read, and badge counts
 */
function initNotificationCenter() {
    const bellBtn = document.getElementById('notification_bell_btn');
    const dropdown = document.getElementById('notification_dropdown');
    const wrapper = document.getElementById('notification_dropdown_wrapper');
    const markAllBtn = document.getElementById('mark_all_read_btn');
    const badge = document.getElementById('notification_unread_badge');
    const drawerCount = document.getElementById('drawer_unread_count');

    const backdrop = document.getElementById('notification_backdrop');

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (wrapper && !wrapper.contains(e.target)) {
            dropdown.classList.remove('open');
            if (backdrop) backdrop.classList.remove('open');
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && dropdown.classList.contains('open')) {
            dropdown.classList.remove('open');
            if (backdrop) backdrop.classList.remove('open');
        }
    });

    // Mark single notification as read on click
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            const id = this.getAttribute('data-id');
            if (id && this.classList.contains('unread')) {
                this.classList.remove('unread');
                const formData = new FormData();
                formData.append('action', 'mark_read');
                formData.append('id', id);
                fetch('api_notifications.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            updateBadge(data.unread_count);
                        }
                    }).catch(() => {});
            }
        });
    });

    // Mark all as read button
    if (markAllBtn) {
        markAllBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            fetch('api_notifications.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        updateBadge(0);
                        markAllBtn.remove();
                    }
                }).catch(() => {});
        });
    }

    function updateBadge(count) {
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
        if (drawerCount) {
            drawerCount.textContent = count + ' unread';
            if (count === 0) {
                drawerCount.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600';
            }
        }
    }
}

