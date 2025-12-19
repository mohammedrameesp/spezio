/**
 * Spezio Apartments - Booking System JavaScript
 */

(function() {
    'use strict';

    // API Base URL - Update this for production
    const API_BASE = 'api';

    // State
    let state = {
        rooms: [],
        selectedRoom: null,
        checkIn: null,
        checkOut: null,
        nights: 0,
        pricing: null,
        coupon: null,
        bookedDates: [],
        currentStep: 1,
        extraBedCount: 0, // Number of extra beds (0, 1, or 2)
        extraBedPrice: 600, // Default per bed, will be loaded from API
        csrfToken: null // CSRF token for secure requests
    };

    // =====================================================
    // CSRF TOKEN MANAGEMENT
    // =====================================================

    async function fetchCSRFToken() {
        try {
            const response = await fetch(`${API_BASE}/get-csrf-token.php`, {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && data.csrf_token) {
                state.csrfToken = data.csrf_token;
            }
        } catch (error) {
            console.error('Failed to fetch CSRF token:', error);
        }
    }

    // =====================================================
    // CUSTOM MODAL SYSTEM (replaces alert/confirm)
    // =====================================================

    // Store for modal callbacks
    let alertModalCallback = null;
    let confirmModalCallback = null;

    /**
     * Show custom alert modal (replaces browser alert())
     * @param {string} message - Message to display
     * @param {string} type - 'warning', 'error', or 'info'
     * @param {string} title - Optional title
     * @returns {Promise} Resolves when user clicks OK
     */
    function showAlert(message, type = 'warning', title = null) {
        return new Promise((resolve) => {
            const modal = document.getElementById('alertModal');
            const titleEl = document.getElementById('alertModalTitle');
            const messageEl = document.getElementById('alertModalMessage');
            const iconEl = document.getElementById('alertModalIcon');
            const okBtn = document.getElementById('alertModalOk');

            // Set content
            titleEl.textContent = title || (type === 'error' ? 'Error' : type === 'info' ? 'Information' : 'Alert');
            messageEl.textContent = message;

            // Set icon type
            iconEl.className = type;

            // Show modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Focus OK button for accessibility
            setTimeout(() => okBtn.focus(), 100);

            // Handle OK click
            alertModalCallback = () => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                resolve();
            };
        });
    }

    /**
     * Show custom confirm modal (replaces browser confirm())
     * @param {string} message - Message to display
     * @param {string} title - Optional title
     * @returns {Promise<boolean>} Resolves with true (confirm) or false (cancel)
     */
    function showConfirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmModal');
            const titleEl = document.getElementById('confirmModalTitle');
            const messageEl = document.getElementById('confirmModalMessage');
            const okBtn = document.getElementById('confirmModalOk');
            const cancelBtn = document.getElementById('confirmModalCancel');

            // Set content
            titleEl.textContent = title;
            messageEl.textContent = message;

            // Show modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Focus cancel button (safer default)
            setTimeout(() => cancelBtn.focus(), 100);

            // Store callbacks
            confirmModalCallback = (result) => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                resolve(result);
            };
        });
    }

    // Initialize modal event listeners
    function initModals() {
        // Alert modal OK button
        const alertOk = document.getElementById('alertModalOk');
        if (alertOk) {
            alertOk.addEventListener('click', () => {
                if (alertModalCallback) alertModalCallback();
            });
        }

        // Confirm modal buttons
        const confirmOk = document.getElementById('confirmModalOk');
        const confirmCancel = document.getElementById('confirmModalCancel');
        if (confirmOk) {
            confirmOk.addEventListener('click', () => {
                if (confirmModalCallback) confirmModalCallback(true);
            });
        }
        if (confirmCancel) {
            confirmCancel.addEventListener('click', () => {
                if (confirmModalCallback) confirmModalCallback(false);
            });
        }

        // Close modals on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const alertModal = document.getElementById('alertModal');
                const confirmModal = document.getElementById('confirmModal');
                if (alertModal.classList.contains('show') && alertModalCallback) {
                    alertModalCallback();
                }
                if (confirmModal.classList.contains('show') && confirmModalCallback) {
                    confirmModalCallback(false);
                }
            }
        });

        // Close alert modal on backdrop click
        const alertModal = document.getElementById('alertModal');
        if (alertModal) {
            alertModal.addEventListener('click', (e) => {
                if (e.target === alertModal && alertModalCallback) {
                    alertModalCallback();
                }
            });
        }

        // Close confirm modal on backdrop click (as cancel)
        const confirmModal = document.getElementById('confirmModal');
        if (confirmModal) {
            confirmModal.addEventListener('click', (e) => {
                if (e.target === confirmModal && confirmModalCallback) {
                    confirmModalCallback(false);
                }
            });
        }
    }

    // DOM Elements
    const elements = {
        form: document.getElementById('bookingForm'),
        roomOptions: document.getElementById('roomOptions'),
        roomId: document.getElementById('roomId'),
        dateRange: document.getElementById('dateRange'),
        nightsDisplay: document.getElementById('nightsDisplay'),
        checkIn: document.getElementById('checkIn'),
        checkOut: document.getElementById('checkOut'),
        numAdults: document.getElementById('numAdults'),
        numChildren: document.getElementById('numChildren'),
        extraBedCount: document.getElementById('extraBedCount'),
        extraBedGroup: document.getElementById('extraBedGroup'),
        extraBedStatus: document.getElementById('extraBedStatus'),
        bedMinus: document.getElementById('bedMinus'),
        bedPlus: document.getElementById('bedPlus'),
        extraBedPrice: document.getElementById('extraBedPrice'),
        guestName: document.getElementById('guestName'),
        guestEmail: document.getElementById('guestEmail'),
        guestPhone: document.getElementById('guestPhone'),
        specialRequests: document.getElementById('specialRequests'),
        couponCode: document.getElementById('couponCode'),
        applyCoupon: document.getElementById('applyCoupon'),
        couponMessage: document.getElementById('couponMessage'),
        availabilityMessage: document.getElementById('availabilityMessage'),
        bookingSummary: document.getElementById('bookingSummary'),
        priceBreakdown: document.getElementById('priceBreakdown'),
        payNowBtn: document.getElementById('payNowBtn'),
        payBtnText: document.getElementById('payBtnText'),
        payBtnLoader: document.getElementById('payBtnLoader'),
        successModal: document.getElementById('successModal'),
        confirmationDetails: document.getElementById('confirmationDetails')
    };

    // Date picker
    let dateRangePicker;

    // Duration discount tiers (matching database)
    const discountTiers = [
        { minNights: 3, percent: 5, label: '3+ Days' },
        { minNights: 5, percent: 7.5, label: '5+ Days' },
        { minNights: 7, percent: 10, label: '7+ Days' },
        { minNights: 15, percent: 35, label: '15+ Days' },
        { minNights: 30, percent: 53, label: '30+ Days' }
    ];

    // Get applicable discount for nights
    function getDiscountForNights(nights) {
        let applicable = null;
        for (const tier of discountTiers) {
            if (nights >= tier.minNights) {
                applicable = tier;
            }
        }
        return applicable;
    }

    // Update nights display with discount info
    function updateNightsDisplay(nights) {
        const discount = getDiscountForNights(nights);
        let html = `<span class="nights-badge">${nights} night${nights > 1 ? 's' : ''}</span>`;

        if (discount) {
            html += `
                <div class="discount-banner">
                    <span class="discount-icon">🎉</span>
                    <span class="discount-text">Congrats! You get <strong>${discount.percent}% off</strong> on your stay!</span>
                </div>`;
        }

        elements.nightsDisplay.innerHTML = html;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', init);

    async function init() {
        // Initialize modals first
        initModals();

        // Fetch CSRF token for secure requests
        await fetchCSRFToken();

        // Initialize accessibility features
        initAccessibility();

        loadRooms();
        loadSettings();
        initDatePickers();
        initStepNavigation();
        initCouponHandler();
        initPaymentHandler();
        initExtraBedHandler();

        // Check URL params for pre-selected room
        const urlParams = new URLSearchParams(window.location.search);
        const roomSlug = urlParams.get('room');
        if (roomSlug) {
            state.preSelectedRoom = roomSlug;
        }
    }

    // =====================================================
    // ACCESSIBILITY FEATURES
    // =====================================================

    function initAccessibility() {
        // Add keyboard navigation for room cards
        document.addEventListener('keydown', (e) => {
            // Handle Enter/Space on room cards
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('room-card')) {
                e.preventDefault();
                e.target.click();
            }
        });

        // Announce step changes to screen readers
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        announcer.id = 'stepAnnouncer';
        document.body.appendChild(announcer);
    }

    function announceToScreenReader(message) {
        const announcer = document.getElementById('stepAnnouncer');
        if (announcer) {
            announcer.textContent = message;
            // Clear after announcement
            setTimeout(() => { announcer.textContent = ''; }, 1000);
        }
    }

    // Load settings (extra bed price, etc.)
    async function loadSettings() {
        try {
            const response = await fetch(`${API_BASE}/get-settings.php`);
            const data = await response.json();
            if (data.success && data.extra_bed_price) {
                state.extraBedPrice = parseFloat(data.extra_bed_price);
                if (elements.extraBedPrice) {
                    elements.extraBedPrice.textContent = formatNumber(state.extraBedPrice);
                }
            }
        } catch (error) {
            // Using default extra bed price
        }
    }

    // Initialize extra bed handler
    function initExtraBedHandler() {
        const maxBeds = 2;

        function updateExtraBedUI() {
            const count = state.extraBedCount;

            // Update input display
            if (elements.extraBedCount) {
                elements.extraBedCount.value = count;
            }

            // Update buttons state
            if (elements.bedMinus) {
                elements.bedMinus.disabled = count <= 0;
            }
            if (elements.bedPlus) {
                elements.bedPlus.disabled = count >= maxBeds;
            }

            // Update group styling
            if (elements.extraBedGroup) {
                elements.extraBedGroup.classList.toggle('has-beds', count > 0);
            }

            // Update status badge
            if (elements.extraBedStatus) {
                if (count > 0) {
                    const totalCost = count * state.extraBedPrice;
                    elements.extraBedStatus.innerHTML = `<span class="bed-badge">✓ ${count} Extra Bed${count > 1 ? 's' : ''} Added (+₹${formatNumber(totalCost)}/night)</span>`;
                } else {
                    elements.extraBedStatus.innerHTML = '';
                }
            }

            // Recalculate price if dates selected
            if (state.checkIn && state.checkOut && state.selectedRoom) {
                calculatePrice();
            }
        }

        // Plus button
        if (elements.bedPlus) {
            elements.bedPlus.addEventListener('click', () => {
                if (state.extraBedCount < maxBeds) {
                    state.extraBedCount++;
                    updateExtraBedUI();
                    announceToScreenReader(`${state.extraBedCount} extra bed${state.extraBedCount > 1 ? 's' : ''} selected`);
                }
            });
        }

        // Minus button
        if (elements.bedMinus) {
            elements.bedMinus.addEventListener('click', () => {
                if (state.extraBedCount > 0) {
                    state.extraBedCount--;
                    updateExtraBedUI();
                    if (state.extraBedCount === 0) {
                        announceToScreenReader('No extra beds selected');
                    } else {
                        announceToScreenReader(`${state.extraBedCount} extra bed${state.extraBedCount > 1 ? 's' : ''} selected`);
                    }
                }
            });
        }

        // Initialize UI
        updateExtraBedUI();
    }

    // Load rooms from API
    async function loadRooms() {
        try {
            const response = await fetch(`${API_BASE}/get-rooms.php`);
            const data = await response.json();

            if (data.success && data.rooms) {
                state.rooms = data.rooms;
                renderRoomOptions();

                // Pre-select room if specified in URL
                if (state.preSelectedRoom) {
                    const room = state.rooms.find(r => r.slug === state.preSelectedRoom);
                    if (room) {
                        selectRoom(room.id);
                    }
                }
            } else {
                showError('Failed to load rooms. Please refresh the page.');
            }
        } catch (error) {
            console.error('Error loading rooms:', error);
            showError('Failed to load rooms. Please refresh the page.');
        }
    }

    // Render room selection cards
    function renderRoomOptions() {
        elements.roomOptions.innerHTML = state.rooms.map((room, index) => {
            // Use room images from database, with fallbacks
            let image;
            if (room.name.toLowerCase().includes('1bhk') || room.name.toLowerCase().includes('1 bhk')) {
                image = 'images/rooms/spezio-1bhk-apartment-preview.webp';
            } else if (room.name.toLowerCase().includes('2bhk') || room.name.toLowerCase().includes('2 bhk')) {
                image = 'images/rooms/spezio-2bhk-apartment-preview.webp';
            } else {
                const images = room.images || [];
                image = images[0] || 'images/rooms/spezio-1bhk-apartment-preview.webp';
            }

            return `
                <div class="room-card" data-room-id="${room.id}" onclick="window.selectRoom(${room.id})"
                     role="radio" aria-checked="false" tabindex="0"
                     aria-label="${room.name}, up to ${room.max_guests} guests, ${formatNumber(room.price_daily)} rupees per night">
                    <img src="${image}" alt="" class="room-card-image" aria-hidden="true">
                    <div class="room-card-info">
                        <h4>${room.name}</h4>
                        <p>Up to ${room.max_guests} guests</p>
                    </div>
                    <div class="room-card-pricing">
                        <div class="price">₹${formatNumber(room.price_daily)}</div>
                        <div class="price-label">per night</div>
                    </div>
                    <div class="room-card-check" aria-hidden="true"></div>
                </div>
            `;
        }).join('');
    }

    // Select room
    window.selectRoom = function(roomId) {
        state.selectedRoom = state.rooms.find(r => r.id === roomId);
        elements.roomId.value = roomId;

        // Update UI and accessibility attributes
        document.querySelectorAll('.room-card').forEach(card => {
            const isSelected = parseInt(card.dataset.roomId) === roomId;
            card.classList.toggle('selected', isSelected);
            card.setAttribute('aria-checked', isSelected ? 'true' : 'false');
        });

        // Announce selection to screen readers
        if (state.selectedRoom) {
            announceToScreenReader(`${state.selectedRoom.name} selected`);
        }

        // Update max guests option
        updateGuestsOptions();

        // Load booked dates for this room
        loadBookedDates(roomId);

        // Check availability if dates are selected
        if (state.checkIn && state.checkOut) {
            checkAvailability();
        }
    };

    // Track if adults change listener is already attached
    let adultsListenerAttached = false;

    // Update guest options based on room
    function updateGuestsOptions() {
        if (!state.selectedRoom) return;

        // Determine max adults and children based on room type
        const roomName = state.selectedRoom.name.toLowerCase();
        let maxAdults = 2; // Default for 1BHK
        let maxChildren = 2; // Default for 1BHK

        if (roomName.includes('2bhk') || roomName.includes('2 bhk')) {
            maxAdults = 4;
            maxChildren = 4;
        }

        // Store for use in updateChildrenOptions
        state.maxChildren = maxChildren;

        const currentAdults = parseInt(elements.numAdults.value) || 2;

        // Update adults dropdown
        elements.numAdults.innerHTML = '';
        for (let i = 1; i <= maxAdults; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = i === 1 ? '1 Adult' : `${i} Adults`;
            elements.numAdults.appendChild(option);
        }

        // Restore or set default
        elements.numAdults.value = currentAdults <= maxAdults ? currentAdults : Math.min(2, maxAdults);

        // Update children dropdown
        updateChildrenOptions();

        // Add event listener for adults change (only once)
        if (!adultsListenerAttached) {
            elements.numAdults.addEventListener('change', updateChildrenOptions);
            adultsListenerAttached = true;
        }
    }

    // Update children options based on room type
    function updateChildrenOptions() {
        if (!state.selectedRoom) return;

        const maxChildren = state.maxChildren || 2;
        const currentChildren = parseInt(elements.numChildren.value) || 0;

        elements.numChildren.innerHTML = '';

        // Always add "No Children" option
        const noChildOption = document.createElement('option');
        noChildOption.value = 0;
        noChildOption.textContent = 'No Children';
        elements.numChildren.appendChild(noChildOption);

        // Add children options up to max
        for (let i = 1; i <= maxChildren; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = i === 1 ? '1 Child' : `${i} Children`;
            elements.numChildren.appendChild(option);
        }

        // Restore or reset
        elements.numChildren.value = currentChildren <= maxChildren ? currentChildren : 0;
    }

    // Load booked dates for calendar
    async function loadBookedDates(roomId) {
        try {
            const response = await fetch(`${API_BASE}/check-availability.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_id: roomId,
                    check_in: new Date().toISOString().split('T')[0],
                    check_out: new Date(Date.now() + 180 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                    get_dates: true
                })
            });
            const data = await response.json();

            if (data.booked_dates) {
                state.bookedDates = data.booked_dates;
                updateDatePickerDisabledDates();
            }
        } catch (error) {
            console.error('Error loading booked dates:', error);
        }
    }

    // Initialize date picker with range mode
    function initDatePickers() {
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 180); // 6 months ahead

        dateRangePicker = flatpickr(elements.dateRange, {
            mode: 'range',
            minDate: 'today',
            maxDate: maxDate,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'M j, Y',
            disableMobile: false,
            showMonths: 2,
            onChange: function(selectedDates, dateStr) {
                // Clear any previous availability message when user starts selecting
                hideAvailabilityMessage();

                if (selectedDates.length === 2) {
                    // Both dates selected - format dates in local timezone
                    const checkInDate = selectedDates[0];
                    const checkOutDate = selectedDates[1];

                    const checkIn = checkInDate.getFullYear() + '-' +
                        String(checkInDate.getMonth() + 1).padStart(2, '0') + '-' +
                        String(checkInDate.getDate()).padStart(2, '0');
                    const checkOut = checkOutDate.getFullYear() + '-' +
                        String(checkOutDate.getMonth() + 1).padStart(2, '0') + '-' +
                        String(checkOutDate.getDate()).padStart(2, '0');

                    state.checkIn = checkIn;
                    state.checkOut = checkOut;
                    elements.checkIn.value = checkIn;
                    elements.checkOut.value = checkOut;

                    // Calculate and display nights with discount info
                    const nights = Math.round((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    updateNightsDisplay(nights);

                    checkAvailability();
                } else {
                    // Only one date selected, waiting for second
                    state.checkIn = null;
                    state.checkOut = null;
                    elements.checkIn.value = '';
                    elements.checkOut.value = '';
                    elements.nightsDisplay.innerHTML = '';
                }
            }
        });
    }

    // Update date picker with disabled dates
    function updateDatePickerDisabledDates() {
        if (dateRangePicker && state.bookedDates.length) {
            dateRangePicker.set('disable', state.bookedDates);
        }
    }

    // Check availability
    async function checkAvailability() {
        if (!state.selectedRoom || !state.checkIn || !state.checkOut) {
            hideAvailabilityMessage();
            return;
        }

        showAvailabilityMessage('checking', 'Checking availability...');

        try {
            const response = await fetch(`${API_BASE}/check-availability.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_id: state.selectedRoom.id,
                    check_in: state.checkIn,
                    check_out: state.checkOut
                })
            });
            const data = await response.json();

            if (data.success && data.available) {
                state.nights = data.nights;
                showAvailabilityMessage('available', `Available! ${data.nights} night(s) selected.`);
                enableNextStep();
                calculatePrice();
            } else {
                showAvailabilityMessage('unavailable', data.reason || data.error || 'Room not available for selected dates.');
                disableNextStep();
            }
        } catch (error) {
            console.error('Error checking availability:', error);
            showAvailabilityMessage('unavailable', 'Error checking availability. Please try again.');
            disableNextStep();
        }
    }

    // Calculate price
    async function calculatePrice() {
        if (!state.selectedRoom || !state.checkIn || !state.checkOut) return;

        try {
            const response = await fetch(`${API_BASE}/calculate-price.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_id: state.selectedRoom.id,
                    check_in: state.checkIn,
                    check_out: state.checkOut,
                    coupon_code: state.coupon?.coupon_code || null,
                    extra_bed_count: state.extraBedCount
                })
            });
            const data = await response.json();

            if (data.success) {
                state.pricing = data;
                updatePriceDisplay();
            }
        } catch (error) {
            console.error('Error calculating price:', error);
        }
    }

    // Update price display
    function updatePriceDisplay() {
        if (!state.pricing) return;

        const p = state.pricing;
        const numAdults = parseInt(elements.numAdults.value) || 1;
        const numChildren = parseInt(elements.numChildren.value) || 0;

        // Build guests string
        let guestsStr = `${numAdults} Adult${numAdults > 1 ? 's' : ''}`;
        if (numChildren > 0) {
            guestsStr += `, ${numChildren} Child${numChildren > 1 ? 'ren' : ''}`;
        }

        // Update summary
        let summaryHtml = `
            <div class="summary-row">
                <span class="label">Room</span>
                <span class="value">${p.room_name}</span>
            </div>
            <div class="summary-row">
                <span class="label">Check-in</span>
                <span class="value">${formatDate(p.check_in)}</span>
            </div>
            <div class="summary-row">
                <span class="label">Check-out</span>
                <span class="value">${formatDate(p.check_out)}</span>
            </div>
            <div class="summary-row">
                <span class="label">Duration</span>
                <span class="value">${p.nights} night(s)</span>
            </div>
            <div class="summary-row">
                <span class="label">Guests</span>
                <span class="value">${guestsStr}</span>
            </div>
            <div class="summary-row">
                <span class="label">Rate Type</span>
                <span class="value">${p.pricing.tier_label}</span>
            </div>
        `;

        if (state.extraBedCount > 0) {
            summaryHtml += `
            <div class="summary-row">
                <span class="label">Extra Beds</span>
                <span class="value">${state.extraBedCount}</span>
            </div>
            `;
        }

        elements.bookingSummary.innerHTML = summaryHtml;

        // Update price breakdown
        let breakdownHtml = '';
        p.breakdown.forEach(item => {
            let rowClass = '';
            if (item.type === 'discount') rowClass = 'discount';
            if (item.type === 'total') rowClass = 'total';

            breakdownHtml += `
                <div class="price-row ${rowClass}">
                    <span class="label">${item.label}</span>
                    <span class="amount">${item.amount < 0 ? '-' : ''}${p.currency_symbol}${formatNumber(Math.abs(item.amount))}</span>
                </div>
            `;
        });
        elements.priceBreakdown.innerHTML = breakdownHtml;

        // Update pay button
        elements.payBtnText.textContent = `Pay ${p.currency_symbol}${formatNumber(p.total_amount)}`;
    }

    // Availability message helpers
    function showAvailabilityMessage(type, message) {
        elements.availabilityMessage.className = `availability-message show ${type}`;
        elements.availabilityMessage.textContent = message;
    }

    function hideAvailabilityMessage() {
        elements.availabilityMessage.className = 'availability-message';
    }

    // Step navigation
    function initStepNavigation() {
        // Next buttons
        document.querySelectorAll('.next-step').forEach(btn => {
            btn.addEventListener('click', async () => {
                const nextStep = parseInt(btn.dataset.next);
                if (await validateStep(state.currentStep)) {
                    goToStep(nextStep);
                }
            });
        });

        // Previous buttons
        document.querySelectorAll('.prev-step').forEach(btn => {
            btn.addEventListener('click', () => {
                const prevStep = parseInt(btn.dataset.prev);
                goToStep(prevStep);
            });
        });
    }

    function goToStep(step) {
        // Update form steps
        document.querySelectorAll('.form-step').forEach(s => {
            s.classList.toggle('active', parseInt(s.dataset.step) === step);
        });

        // Update progress
        document.querySelectorAll('.progress-step').forEach(s => {
            const stepNum = parseInt(s.dataset.step);
            s.classList.remove('active', 'completed');
            if (stepNum === step) {
                s.classList.add('active');
            } else if (stepNum < step) {
                s.classList.add('completed');
            }
        });

        state.currentStep = step;

        // Recalculate price when reaching payment step
        if (step === 3) {
            calculatePrice();
        }

        // Announce step change to screen readers
        const stepTitles = ['', 'Room & Dates', 'Guest Details', 'Review & Payment'];
        announceToScreenReader(`Step ${step} of 3: ${stepTitles[step]}`);

        // Scroll to top of form
        elements.form.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Focus the first focusable element in the new step
        setTimeout(() => {
            const activeStep = document.querySelector('.form-step.active');
            if (activeStep) {
                const firstInput = activeStep.querySelector('input:not([type="hidden"]), select, button:not([disabled])');
                if (firstInput) firstInput.focus();
            }
        }, 300);
    }

    async function validateStep(step) {
        switch (step) {
            case 1:
                if (!state.selectedRoom) {
                    await showAlert('Please select a room.', 'warning', 'Room Required');
                    return false;
                }
                if (!state.checkIn || !state.checkOut) {
                    await showAlert('Please select check-in and check-out dates.', 'warning', 'Dates Required');
                    return false;
                }
                return true;

            case 2:
                const name = elements.guestName.value.trim();
                const email = elements.guestEmail.value.trim();
                const phone = elements.guestPhone.value.trim();

                if (!name) {
                    await showAlert('Please enter your name.', 'warning', 'Name Required');
                    elements.guestName.focus();
                    return false;
                }
                if (!email || !isValidEmail(email)) {
                    await showAlert('Please enter a valid email address.', 'warning', 'Invalid Email');
                    elements.guestEmail.focus();
                    return false;
                }
                if (!phone || phone.length < 10) {
                    await showAlert('Please enter a valid phone number.', 'warning', 'Invalid Phone');
                    elements.guestPhone.focus();
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    function enableNextStep() {
        const btn = document.querySelector('.form-step[data-step="1"] .next-step');
        if (btn) {
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'false');
        }
    }

    function disableNextStep() {
        const btn = document.querySelector('.form-step[data-step="1"] .next-step');
        if (btn) {
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
        }
    }

    // Coupon handler
    function initCouponHandler() {
        elements.applyCoupon.addEventListener('click', applyCoupon);
        elements.couponCode.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyCoupon();
            }
        });
    }

    async function applyCoupon() {
        const code = elements.couponCode.value.trim().toUpperCase();

        if (!code) {
            showCouponMessage('error', 'Please enter a coupon code.');
            return;
        }

        if (!state.pricing) {
            showCouponMessage('error', 'Please select dates first.');
            return;
        }

        elements.applyCoupon.disabled = true;
        elements.applyCoupon.textContent = 'Applying...';

        try {
            const response = await fetch(`${API_BASE}/validate-coupon.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    code: code,
                    room_id: state.selectedRoom.id,
                    subtotal: state.pricing.pricing.subtotal,
                    nights: state.pricing.nights,
                    csrf_token: state.csrfToken
                })
            });
            const data = await response.json();

            if (data.success && data.valid) {
                state.coupon = data;
                showCouponMessage('success', `Coupon applied! You save ₹${formatNumber(data.discount_amount)}`);
                calculatePrice(); // Recalculate with coupon
            } else {
                state.coupon = null;
                showCouponMessage('error', data.error || 'Invalid coupon code.');
            }
        } catch (error) {
            console.error('Error applying coupon:', error);
            showCouponMessage('error', 'Error applying coupon. Please try again.');
        } finally {
            elements.applyCoupon.disabled = false;
            elements.applyCoupon.textContent = 'Apply';
        }
    }

    function showCouponMessage(type, message) {
        elements.couponMessage.className = `coupon-message show ${type}`;
        elements.couponMessage.textContent = message;
    }

    // Payment handler
    function initPaymentHandler() {
        elements.payNowBtn.addEventListener('click', initiatePayment);
    }

    async function initiatePayment() {
        if (!await validateStep(2)) {
            goToStep(2);
            return;
        }

        // Show loading
        elements.payNowBtn.disabled = true;
        elements.payBtnText.textContent = 'Processing...';
        elements.payBtnLoader.style.display = 'inline-block';

        try {
            // Create order with CSRF token
            const orderData = {
                room_id: state.selectedRoom.id,
                check_in: state.checkIn,
                check_out: state.checkOut,
                guest_name: elements.guestName.value.trim(),
                guest_email: elements.guestEmail.value.trim(),
                guest_phone: elements.guestPhone.value.trim(),
                num_adults: parseInt(elements.numAdults.value),
                num_children: parseInt(elements.numChildren.value) || 0,
                extra_bed_count: state.extraBedCount,
                coupon_code: state.coupon?.coupon_code || null,
                special_requests: elements.specialRequests.value.trim(),
                csrf_token: state.csrfToken
            };

            const response = await fetch(`${API_BASE}/create-order.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(orderData)
            });
            const data = await response.json();

            if (data.success) {
                // Check if it's a free booking (100% discount)
                if (data.free_booking) {
                    showSuccessModal(data);
                } else {
                    openRazorpayCheckout(data);
                }
            } else {
                await showAlert(data.error || 'Failed to create order. Please try again.', 'error', 'Order Error');
                resetPayButton();
            }
        } catch (error) {
            console.error('Error creating order:', error);
            await showAlert('Error processing payment. Please try again.', 'error', 'Payment Error');
            resetPayButton();
        }
    }

    function openRazorpayCheckout(orderData) {
        // Check if Razorpay is loaded
        if (typeof Razorpay === 'undefined') {
            showPaymentError('Payment gateway is not available. Please refresh the page and try again. If the problem persists, please contact support.');
            resetPayButton();
            return;
        }

        const options = {
            key: orderData.razorpay_key_id,
            amount: orderData.amount,
            currency: orderData.currency,
            name: 'Spezio Apartments',
            description: `Booking: ${state.selectedRoom.name}`,
            order_id: orderData.razorpay_order_id,
            prefill: orderData.prefill,
            notes: orderData.notes,
            theme: {
                color: '#00443F'
            },
            handler: function(response) {
                verifyPayment(response, orderData.booking_id);
            },
            modal: {
                ondismiss: function() {
                    resetPayButton();
                }
            }
        };

        try {
            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function(response) {
                showPaymentError('Payment failed: ' + response.error.description);
                resetPayButton();
            });
            rzp.open();
        } catch (error) {
            console.error('Razorpay initialization error:', error);
            showPaymentError('Failed to initialize payment. Please try again.');
            resetPayButton();
        }
    }

    // Show payment error in a user-friendly way
    function showPaymentError(message) {
        // Create error modal or use existing availability message area
        const errorDiv = document.createElement('div');
        errorDiv.className = 'payment-error-modal';
        errorDiv.innerHTML = `
            <div class="payment-error-content">
                <div class="payment-error-icon">!</div>
                <h3>Payment Error</h3>
                <p>${message}</p>
                <button onclick="this.parentElement.parentElement.remove()" class="btn btn-primary">OK</button>
            </div>
        `;
        document.body.appendChild(errorDiv);
    }

    async function verifyPayment(paymentResponse, bookingId) {
        try {
            const response = await fetch(`${API_BASE}/verify-payment.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    razorpay_order_id: paymentResponse.razorpay_order_id,
                    razorpay_payment_id: paymentResponse.razorpay_payment_id,
                    razorpay_signature: paymentResponse.razorpay_signature,
                    booking_id: bookingId,
                    csrf_token: state.csrfToken
                })
            });
            const data = await response.json();

            if (data.success) {
                showSuccessModal(data);
            } else {
                await showAlert(data.error || 'Payment verification failed. Please contact support.', 'error', 'Verification Failed');
                resetPayButton();
            }
        } catch (error) {
            console.error('Error verifying payment:', error);
            await showAlert('Error verifying payment. Please contact support with your booking ID: ' + bookingId, 'error', 'Verification Error');
            resetPayButton();
        }
    }

    function showSuccessModal(data) {
        const booking = data.booking;

        elements.confirmationDetails.innerHTML = `
            <div class="confirmation-row">
                <span>Booking ID</span>
                <strong>${data.booking_id}</strong>
            </div>
            <div class="confirmation-row">
                <span>Room</span>
                <strong>${booking.room_name}</strong>
            </div>
            <div class="confirmation-row">
                <span>Check-in</span>
                <strong>${formatDate(booking.check_in)}</strong>
            </div>
            <div class="confirmation-row">
                <span>Check-out</span>
                <strong>${formatDate(booking.check_out)}</strong>
            </div>
            <div class="confirmation-row">
                <span>Duration</span>
                <strong>${booking.total_nights} night(s)</strong>
            </div>
            <div class="confirmation-row">
                <span>Amount Paid</span>
                <strong>₹${formatNumber(booking.total_amount)}</strong>
            </div>
        `;

        elements.successModal.classList.add('show');
        resetPayButton();
    }

    function resetPayButton() {
        elements.payNowBtn.disabled = false;
        elements.payBtnText.textContent = state.pricing ?
            `Pay ₹${formatNumber(state.pricing.total_amount)}` : 'Pay Now';
        elements.payBtnLoader.style.display = 'none';
    }

    // Helper functions
    function formatNumber(num) {
        return new Intl.NumberFormat('en-IN').format(num);
    }

    function formatDate(dateStr) {
        // Parse date explicitly to avoid timezone issues
        // Date strings in YYYY-MM-DD format are parsed as UTC by default
        const parts = dateStr.split('-');
        const date = new Date(parts[0], parts[1] - 1, parts[2]); // Local time
        return date.toLocaleDateString('en-IN', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showError(message) {
        elements.roomOptions.innerHTML = `
            <div class="error-message" style="color: #c62828; padding: 1rem; text-align: center;">
                ${message}
            </div>
        `;
    }

})();
