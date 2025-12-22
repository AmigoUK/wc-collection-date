/**
 * Calendar Admin JavaScript
 *
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/assets/js
 * @since      1.2.0
 */

(function($) {
    'use strict';

    /**
     * Calendar Selection Manager
     * Handles multi-date selection functionality
     */
    var CalendarSelectionManager = {

        selectedDates: [],
        lastSelectedDate: null,
        isSelecting: false,

        /**
         * Initialize selection manager
         */
        init: function() {
            this.bindKeyboardEvents();
        },

        /**
         * Toggle date selection
         * @param {string} date Date in YYYY-MM-DD format
         * @param {boolean} isMultiSelect Whether this is a multi-select operation
         * @param {boolean} isRangeSelect Whether this is a range selection operation
         */
        toggleDateSelection: function(date, isMultiSelect, isRangeSelect) {
            if (isRangeSelect && this.lastSelectedDate) {
                this.selectRange(this.lastSelectedDate, date);
            } else if (isMultiSelect) {
                if (this.isDateSelected(date)) {
                    this.deselectDate(date);
                } else {
                    this.selectDate(date);
                }
            } else {
                this.clearSelection();
                this.selectDate(date);
            }

            this.lastSelectedDate = date;
            this.updateVisualStates();
        },

        /**
         * Select a single date
         * @param {string} date Date in YYYY-MM-DD format
         */
        selectDate: function(date) {
            if (!this.isDateSelected(date)) {
                this.selectedDates.push(date);
                this.selectedDates.sort(); // Keep dates sorted
            }
        },

        /**
         * Deselect a single date
         * @param {string} date Date in YYYY-MM-DD format
         */
        deselectDate: function(date) {
            var index = this.selectedDates.indexOf(date);
            if (index > -1) {
                this.selectedDates.splice(index, 1);
            }
        },

        /**
         * Select a range of dates
         * @param {string} startDate Start date in YYYY-MM-DD format
         * @param {string} endDate End date in YYYY-MM-DD format
         */
        selectRange: function(startDate, endDate) {
            this.clearSelection();

            var start = new Date(startDate);
            var end = new Date(endDate);

            if (start > end) {
                var temp = start;
                start = end;
                end = temp;
            }

            var currentDate = new Date(start);
            while (currentDate <= end) {
                var dateStr = this.formatDateForStorage(currentDate);
                this.selectedDates.push(dateStr);
                currentDate.setDate(currentDate.getDate() + 1);
            }

            this.selectedDates.sort();
        },

        /**
         * Clear all selections
         */
        clearSelection: function() {
            this.selectedDates = [];
            this.lastSelectedDate = null;
        },

        /**
         * Check if a date is selected
         * @param {string} date Date in YYYY-MM-DD format
         * @return {boolean} Whether the date is selected
         */
        isDateSelected: function(date) {
            return this.selectedDates.indexOf(date) !== -1;
        },

        /**
         * Get all selected dates
         * @return {Array} Array of selected dates
         */
        getSelectedDates: function() {
            return this.selectedDates.slice(); // Return copy
        },

        /**
         * Update visual states for selected dates
         */
        updateVisualStates: function() {
            var self = this;

            // Remove all selection classes first
            $('.calendar-day').removeClass('selected selected-range-start selected-range-end selected-range');

            // Add selection classes to selected dates
            $.each(this.selectedDates, function(index, date) {
                var $dayElement = $('.calendar-day[data-date="' + date + '"]');
                if ($dayElement.length > 0) {
                    $dayElement.addClass('selected');

                    // Add range-specific classes if more than one date is selected
                    if (self.selectedDates.length > 1) {
                        if (index === 0) {
                            $dayElement.addClass('selected-range-start');
                        } else if (index === self.selectedDates.length - 1) {
                            $dayElement.addClass('selected-range-end');
                        } else {
                            $dayElement.addClass('selected-range');
                        }
                    }
                }
            });

            // Update bulk action button state
            var $bulkButton = $('#bulk-set-capacity');
            if (this.selectedDates.length > 0) {
                $bulkButton.prop('disabled', false);
                $bulkButton.text('Apply to ' + this.selectedDates.length + ' Selected Date' + (this.selectedDates.length > 1 ? 's' : ''));
            } else {
                $bulkButton.prop('disabled', true);
                $bulkButton.text('Apply to Selected');
            }
        },

        /**
         * Format date for storage
         * @param {Date} date Date object
         * @return {string} Formatted date string
         */
        formatDateForStorage: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },

        /**
         * Bind keyboard events
         */
        bindKeyboardEvents: function() {
            var self = this;

            $(document).on('keydown', function(e) {
                // Only handle keyboard events when calendar is active
                if (!$('#calendar-grid').is(':visible')) {
                    return;
                }

                var $focusedDay = $('.calendar-day:focus');

                switch(e.keyCode) {
                    case 27: // Escape - clear selection
                        e.preventDefault();
                        self.clearSelection();
                        self.updateVisualStates();
                        break;

                    case 32: // Space - select/deselect focused day
                    case 13: // Enter - select/deselect focused day
                        if ($focusedDay.length > 0) {
                            e.preventDefault();
                            var date = $focusedDay.attr('data-date');
                            var isMultiSelect = e.ctrlKey || e.metaKey;
                            var isRangeSelect = e.shiftKey;
                            self.toggleDateSelection(date, isMultiSelect, isRangeSelect);
                        }
                        break;

                    case 37: // Left arrow - navigate to previous day
                    case 38: // Up arrow - navigate to previous week
                    case 39: // Right arrow - navigate to next day
                    case 40: // Down arrow - navigate to next week
                        if ($focusedDay.length > 0) {
                            e.preventDefault();
                            self.navigateWithArrows(e.keyCode, $focusedDay);
                        }
                        break;
                }
            });
        },

        /**
         * Navigate with arrow keys
         * @param {number} keyCode Key code of arrow key
         * @param {jQuery} $currentDay Currently focused day element
         */
        navigateWithArrows: function(keyCode, $currentDay) {
            var $days = $('.calendar-day:not(.other-month)');
            var currentIndex = $days.index($currentDay);
            var newIndex = currentIndex;

            switch(keyCode) {
                case 37: // Left
                    newIndex = currentIndex - 1;
                    break;
                case 38: // Up
                    newIndex = currentIndex - 7;
                    break;
                case 39: // Right
                    newIndex = currentIndex + 1;
                    break;
                case 40: // Down
                    newIndex = currentIndex + 7;
                    break;
            }

            if (newIndex >= 0 && newIndex < $days.length) {
                $days.eq(newIndex).focus();
            }
        }
    };

    // Calendar controller
    var WCCollectionCalendar = {

        currentYear: 0,
        currentMonth: 0,
        calendarData: [],
        selectedDates: [],
        isLoading: false,

        // Initialize calendar
        init: function() {
            this.currentYear = parseInt(wcCollectionCalendar.today.substring(0, 4));
            this.currentMonth = parseInt(wcCollectionCalendar.today.substring(5, 7));

            // Initialize selection manager
            CalendarSelectionManager.init();

            this.bindEvents();
            this.loadCalendarData();
        },

        // Bind event handlers
        bindEvents: function() {
            var self = this;

            // Navigation controls
            $('#calendar-prev-month').on('click', function() {
                self.previousMonth();
            });

            $('#calendar-next-month').on('click', function() {
                self.nextMonth();
            });

            $('#calendar-today').on('click', function() {
                self.goToToday();
            });

            $('#calendar-refresh').on('click', function() {
                self.loadCalendarData();
            });

            // Modal controls
            $(document).on('click', '.modal-close', function() {
                self.closeModal();
            });

            $(document).on('click', '.calendar-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Escape key to close modal
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    self.closeModal();
                }
            });

            // Capacity update
            $('#modal-update-capacity').on('click', function() {
                self.updateCapacityFromModal();
            });

            // Enter key in capacity input
            $('#modal-capacity-input').on('keyup', function(e) {
                if (e.keyCode === 13) {
                    self.updateCapacityFromModal();
                }
            });

            // Bulk capacity update
            $('#bulk-set-capacity').on('click', function() {
                self.bulkUpdateCapacity();
            });

            // Export calendar
            $('#export-calendar').on('click', function() {
                self.exportCalendarData();
            });
        },

        // Load calendar data via AJAX
        loadCalendarData: function() {
            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            this.showLoading();

            var self = this;

            $.ajax({
                url: wcCollectionCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_collection_date_get_calendar_data',
                    nonce: wcCollectionCalendar.nonce,
                    year: self.currentYear,
                    month: self.currentMonth
                },
                success: function(response) {
                    self.isLoading = false;
                    self.hideLoading();

                    if (response.success) {
                        self.calendarData = response.data.calendar_data;
                        self.renderCalendar(response.data);
                        self.updateStatistics(response.data.statistics);
                    } else {
                        self.showError(wcCollectionCalendar.texts.error);
                    }
                },
                error: function() {
                    self.isLoading = false;
                    self.hideLoading();
                    self.showError(wcCollectionCalendar.texts.error);
                }
            });
        },

        // Show loading state
        showLoading: function() {
            $('#calendar-loading').show();
            $('#calendar-grid').hide();
        },

        // Hide loading state
        hideLoading: function() {
            $('#calendar-loading').hide();
            $('#calendar-grid').show();
        },

        // Render calendar
        renderCalendar: function(data) {
            this.updateMonthDisplay();
            this.renderCalendarGrid(data.calendar_data);
            // Update selection visual states after rendering
            CalendarSelectionManager.updateVisualStates();
        },

        // Update month display
        updateMonthDisplay: function() {
            var monthName = wcCollectionCalendar.monthNames[this.currentMonth - 1];
            $('#current-month-year').text(monthName + ' ' + this.currentYear);
        },

        // Render calendar grid
        renderCalendarGrid: function(calendarData) {
            var $grid = $('#calendar-grid').empty();

            // Create header days
            var $header = $('<div class="calendar-header-days"></div>');
            for (var i = 0; i < 7; i++) {
                var dayIndex = (wcCollectionCalendar.firstDay + i) % 7;
                $header.append('<div class="calendar-header-day">' + wcCollectionCalendar.dayNamesMin[dayIndex] + '</div>');
            }
            $grid.append($header);

            // Calculate starting position
            var firstDay = new Date(this.currentYear, this.currentMonth - 1, 1);
            var startDay = (firstDay.getDay() - wcCollectionCalendar.firstDay + 7) % 7;

            // Create days grid container
            var $daysGrid = $('<div class="calendar-days-grid"></div>');

            // Add empty cells for days before month starts
            for (var i = 0; i < startDay; i++) {
                $daysGrid.append('<div class="calendar-day other-month"></div>');
            }

            // Add days of the month
            var self = this;
            $.each(calendarData, function(index, dayData) {
                var $day = self.createDayElement(dayData);
                $daysGrid.append($day);
            });

            // Add empty cells for days after month ends
            var totalCells = startDay + calendarData.length;
            var remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (var i = 0; i < remainingCells; i++) {
                $daysGrid.append('<div class="calendar-day other-month"></div>');
            }

            $grid.append($daysGrid);
        },

        // Create day element
        createDayElement: function(dayData) {
            var $day = $('<div class="calendar-day"></div>');

            // Add classes
            $day.addClass('status-' + dayData.status);
            if (dayData.is_today) {
                $day.addClass('today');
            }

            // Date attribute for click handling
            $day.attr('data-date', dayData.date);

            // Accessibility attributes
            $day.attr('tabindex', '0');
            $day.attr('role', 'gridcell');
            $day.attr('aria-label', this.formatDateForAccessibility(dayData));
            $day.attr('aria-selected', 'false');

            // Check if date is already selected
            if (CalendarSelectionManager.isDateSelected(dayData.date)) {
                $day.addClass('selected');
                $day.attr('aria-selected', 'true');
            }

            // Day number
            $day.append('<div class="calendar-day-number">' + dayData.day + '</div>');

            // Day stats
            if (!['excluded', 'past'].includes(dayData.status)) {
                var statsHtml = '<div class="calendar-day-stats">';
                statsHtml += '<div class="calendar-day-stat">';
                statsHtml += '<span class="calendar-day-stat-label">' + wcCollectionCalendar.texts.capacity + ':</span>';
                statsHtml += '<span class="calendar-day-stat-value">' + dayData.capacity + '</span>';
                statsHtml += '</div>';
                statsHtml += '<div class="calendar-day-stat">';
                statsHtml += '<span class="calendar-day-stat-label">' + wcCollectionCalendar.texts.booked + ':</span>';
                statsHtml += '<span class="calendar-day-stat-value">' + dayData.booked + '</span>';
                statsHtml += '</div>';
                statsHtml += '<div class="calendar-day-stat">';
                statsHtml += '<span class="calendar-day-stat-label">' + wcCollectionCalendar.texts.available + ':</span>';
                statsHtml += '<span class="calendar-day-stat-value">' + dayData.available + '</span>';
                statsHtml += '</div>';
                statsHtml += '</div>';
                $day.append(statsHtml);
            }

            // Tooltip
            if (dayData.is_excluded) {
                var tooltip = wcCollectionCalendar.texts.excluded + ': ' + dayData.exclusion_reason;
                $day.append('<div class="calendar-day-tooltip" data-tooltip="' + tooltip + '"></div>');
            } else if (!dayData.is_collection_day) {
                var tooltip = wcCollectionCalendar.texts.unavailable;
                $day.append('<div class="calendar-day-tooltip" data-tooltip="' + tooltip + '"></div>');
            }

            // Click handler for selection and modal
            if (!['excluded', 'past'].includes(dayData.status)) {
                $day.on('click', function(e) {
                    // Handle selection with modifier keys
                    var isMultiSelect = e.ctrlKey || e.metaKey;
                    var isRangeSelect = e.shiftKey;

                    // If it's a selection operation, handle selection first
                    if (isMultiSelect || isRangeSelect) {
                        CalendarSelectionManager.toggleDateSelection(dayData.date, isMultiSelect, isRangeSelect);
                    } else {
                        // Check if date is already selected for single click
                        if (CalendarSelectionManager.isDateSelected(dayData.date)) {
                            // If already selected, show modal (double-click behavior)
                            this.showDayDetails(dayData);
                        } else {
                            // Single click for selection
                            CalendarSelectionManager.toggleDateSelection(dayData.date, false, false);
                        }
                    }
                }.bind(this));

                // Double click for modal (alternative to single click on selected date)
                $day.on('dblclick', function() {
                    this.showDayDetails(dayData);
                }.bind(this));
            }

            return $day;
        },

        // Show day details modal
        showDayDetails: function(dayData) {
            $('#modal-date-title').text(dayData.date);
            $('#modal-date-display').text(this.formatDate(dayData.date));
            $('#modal-day-display').text(this.getDayName(dayData.day_of_week));
            $('#modal-current-capacity').text(wcCollectionCalendar.texts.capacity + ': ' + dayData.capacity);
            $('#modal-booked-count').text(wcCollectionCalendar.texts.booked + ': ' + dayData.booked);
            $('#modal-available-count').text(wcCollectionCalendar.texts.available + ': ' + dayData.available);
            $('#modal-capacity-input').val(dayData.capacity);

            $('#day-details-modal').show();
            this.loadDayBookings(dayData.date);
        },

        // Load day bookings
        loadDayBookings: function(date) {
            var self = this;
            var $bookingsList = $('#modal-bookings-list');

            // Show loading
            $bookingsList.html(
                '<div class="loading-bookings">' +
                '<span class="spinner is-active"></span>' +
                '<span>' + wcCollectionCalendar.texts.loading + '</span>' +
                '</div>'
            );

            $.ajax({
                url: wcCollectionCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_collection_date_get_day_bookings',
                    nonce: wcCollectionCalendar.nonce,
                    date: date
                },
                success: function(response) {
                    if (response.success) {
                        self.renderBookings(response.data.bookings);
                    } else {
                        $bookingsList.html('<div class="no-bookings">' + wcCollectionCalendar.texts.noBookings + '</div>');
                    }
                },
                error: function() {
                    $bookingsList.html('<div class="no-bookings">' + wcCollectionCalendar.texts.noBookings + '</div>');
                }
            });
        },

        // Render bookings in modal
        renderBookings: function(bookings) {
            var $bookingsList = $('#modal-bookings-list');

            if (!bookings || bookings.length === 0) {
                $bookingsList.html('<div class="no-bookings">' + wcCollectionCalendar.texts.noBookings + '</div>');
                return;
            }

            var html = '';
            $.each(bookings, function(index, booking) {
                html += '<div class="booking-item">';
                html += '<div class="booking-info">';
                html += '<div class="booking-order">Order #' + booking.order_number + '</div>';
                html += '<div class="booking-customer">' + booking.customer + '</div>';
                html += '<div class="booking-meta">';
                html += '<span>' + booking.total + '</span>';
                html += '<span class="status-indicator status-' + booking.status + '"></span>';
                html += '<span>' + booking.date_created + '</span>';
                html += '</div>';
                html += '</div>';
                html += '<div class="booking-actions">';
                html += '<a href="' + booking.edit_url + '" class="button button-small">' + wcCollectionCalendar.texts.view + '</a>';
                html += '</div>';
                html += '</div>';
            });

            $bookingsList.html(html);
        },

        // Update capacity from modal
        updateCapacityFromModal: function() {
            var date = $('#modal-date-title').text();
            var capacity = parseInt($('#modal-capacity-input').val());

            if (!capacity || capacity < 1 || capacity > 999) {
                alert('Please enter a valid capacity between 1 and 999');
                return;
            }

            if (!confirm(wcCollectionCalendar.texts.confirmCapacity)) {
                return;
            }

            this.updateCapacity(date, capacity);
        },

        // Update capacity
        updateCapacity: function(date, capacity) {
            var self = this;

            $.ajax({
                url: wcCollectionCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_collection_date_update_capacity',
                    nonce: wcCollectionCalendar.nonce,
                    date: date,
                    capacity: capacity
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(wcCollectionCalendar.texts.capacityUpdated);
                        self.closeModal();
                        self.loadCalendarData();
                    } else {
                        self.showError(response.data.message || wcCollectionCalendar.texts.error);
                    }
                },
                error: function() {
                    self.showError(wcCollectionCalendar.texts.error);
                }
            });
        },

        // Close modal
        closeModal: function() {
            $('#day-details-modal').hide();
        },

        // Navigation methods
        previousMonth: function() {
            this.currentMonth--;
            if (this.currentMonth < 1) {
                this.currentMonth = 12;
                this.currentYear--;
            }
            this.loadCalendarData();
        },

        nextMonth: function() {
            this.currentMonth++;
            if (this.currentMonth > 12) {
                this.currentMonth = 1;
                this.currentYear++;
            }
            this.loadCalendarData();
        },

        goToToday: function() {
            var today = wcCollectionCalendar.today.split('-');
            this.currentYear = parseInt(today[0]);
            this.currentMonth = parseInt(today[1]);
            this.loadCalendarData();
        },

        // Update statistics
        updateStatistics: function(stats) {
            $('#total-bookings').text(stats.total_bookings.toLocaleString());
            $('#total-capacity').text(stats.total_capacity.toLocaleString());
            $('#utilization-rate').text(stats.utilization_rate + '%');
            $('#available-days').text(stats.available_days.toLocaleString());
        },

        // Bulk update capacity
        bulkUpdateCapacity: function() {
            var selectedDates = CalendarSelectionManager.getSelectedDates();
            if (selectedDates.length === 0) {
                alert('Please select dates to update');
                return;
            }

            var capacity = parseInt($('#bulk-capacity').val());
            if (!capacity || capacity < 1 || capacity > 999) {
                alert('Please enter a valid capacity between 1 and 999');
                return;
            }

            var self = this;
            var updateCount = 0;
            var completedCount = 0;

            function showProgress() {
                completedCount++;
                if (completedCount === updateCount) {
                    self.showSuccess('Capacity updated for ' + updateCount + ' dates');
                    CalendarSelectionManager.clearSelection();
                    CalendarSelectionManager.updateVisualStates();
                    self.loadCalendarData();
                }
            }

            // Update capacity for each selected date
            $.each(selectedDates, function(index, date) {
                updateCount++;
                self.updateCapacitySilently(date, capacity, showProgress);
            });
        },

        // Update capacity silently (without refreshing calendar)
        updateCapacitySilently: function(date, capacity, callback) {
            $.ajax({
                url: wcCollectionCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_collection_date_update_capacity',
                    nonce: wcCollectionCalendar.nonce,
                    date: date,
                    capacity: capacity
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback();
                    }
                },
                error: function() {
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        },

        // Export calendar data
        exportCalendarData: function() {
            var data = {
                year: this.currentYear,
                month: this.currentMonth,
                calendar_data: this.calendarData
            };

            var csvContent = this.generateCSV(data);
            this.downloadCSV(csvContent, 'calendar-' + this.currentYear + '-' + this.currentMonth + '.csv');
        },

        // Generate CSV
        generateCSV: function(data) {
            var csv = 'Date,Day,Capacity,Booked,Available,Status\n';

            $.each(data.calendar_data, function(index, dayData) {
                csv += dayData.date + ',';
                csv += dayData.day_of_week + ',';
                csv += dayData.capacity + ',';
                csv += dayData.booked + ',';
                csv += dayData.available + ',';
                csv += dayData.status + '\n';
            });

            return csv;
        },

        // Download CSV
        downloadCSV: function(csvContent, filename) {
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');

            if (link.download !== undefined) {
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        },

        // Utility methods
        formatDate: function(dateStr) {
            var date = new Date(dateStr);
            return date.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        },

        // Format date for accessibility
        formatDateForAccessibility: function(dayData) {
            var date = new Date(dayData.date);
            var formattedDate = date.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            var statusText = '';
            switch(dayData.status) {
                case 'available':
                    statusText = 'Available';
                    break;
                case 'moderate':
                    statusText = 'Moderate availability';
                    break;
                case 'high':
                    statusText = 'High demand';
                    break;
                case 'full':
                    statusText = 'Fully booked';
                    break;
                case 'excluded':
                    statusText = 'Excluded';
                    break;
                case 'past':
                    statusText = 'Past date';
                    break;
                default:
                    statusText = 'Unknown status';
            }

            return formattedDate + ', ' + statusText +
                   (dayData.booked > 0 ? ', ' + dayData.booked + ' booked' : '') +
                   (dayData.available > 0 ? ', ' + dayData.available + ' available' : '') +
                   (dayData.is_today ? ', Today' : '');
        },

        getDayName: function(dayIndex) {
            var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            return days[dayIndex];
        },

        showSuccess: function(message) {
            // You could replace this with a nicer notification system
            alert(message);
        },

        showError: function(message) {
            // You could replace this with a nicer error notification system
            alert('Error: ' + message);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCCollectionCalendar.init();
    });

})(jQuery);