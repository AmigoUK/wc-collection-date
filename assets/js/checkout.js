/**
 * WooCommerce Collection Date - Checkout Integration
 *
 * @package WC_Collection_Date
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Collection Date Picker Handler
     */
    const CollectionDatePicker = {

        /**
         * Available dates cache
         */
        availableDates: [],

        /**
         * Flatpickr instance
         */
        flatpickrInstance: null,

        /**
         * Currently selected collection date
         */
        selectedDate: null,

        /**
         * Initialize
         */
        init: function() {
            if (typeof flatpickr === 'undefined') {
                console.error('Flatpickr library not loaded');
                return;
            }

            this.loadAvailableDates();
            this.bindEvents();
        },

        /**
         * Load available dates from API
         */
        loadAvailableDates: function() {
            const self = this;

            console.log('Loading available dates from:', wcCollectionDate.restUrl + '/dates');

            $.ajax({
                url: wcCollectionDate.restUrl + '/dates',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wcCollectionDate.restNonce);
                },
                success: function(response) {
                    console.log('API Response:', response);

                    if (response.success && response.dates) {
                        self.availableDates = response.dates;
                        console.log('Loaded ' + response.dates.length + ' available dates');
                        console.log('Date range:', response.dates[0], 'to', response.dates[response.dates.length - 1]);
                        self.initializeDatePicker();
                    } else {
                        console.error('API returned no dates or success=false');
                        self.showError(wcCollectionDate.i18n.noDate);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading dates:', error);
                    console.error('XHR:', xhr);
                    console.error('Status:', status);
                    self.showError(wcCollectionDate.i18n.noDate);
                }
            });
        },

        /**
         * Initialize Flatpickr date picker
         */
        initializeDatePicker: function() {
            const self = this;
            const input = document.getElementById('collection_date');

            if (!input) {
                console.error('Collection date input not found');
                return;
            }

            // Destroy existing instance if any
            if (this.flatpickrInstance) {
                this.flatpickrInstance.destroy();
            }

            // Convert available dates to Date objects for better compatibility
            const enableDates = this.availableDates.map(function(dateStr) {
                return new Date(dateStr + 'T00:00:00');
            });

            console.log('Enable dates (first 5):', enableDates.slice(0, 5));

            // Initialize Flatpickr with inline calendar
            this.flatpickrInstance = flatpickr(input, {
                inline: true,
                dateFormat: 'Y-m-d',
                minDate: this.availableDates[0],
                maxDate: this.availableDates[this.availableDates.length - 1],
                defaultDate: this.availableDates[0],
                disableMobile: false,
                allowInput: false,
                static: true,
                monthSelectorType: 'dropdown',
                locale: {
                    firstDayOfWeek: 1
                },
                showMonths: 1,
                // Use disable function instead of enable array
                disable: [
                    function(date) {
                        // Format date as Y-m-d
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        const dateStr = year + '-' + month + '-' + day;

                        // Return true to DISABLE, false to ENABLE
                        const isAvailable = self.availableDates.indexOf(dateStr) !== -1;

                        return !isAvailable; // Disable if NOT available
                    }
                ],
                onReady: function(selectedDates, dateStr, instance) {
                    instance.calendarContainer.classList.add('wc-collection-date-calendar');

                    console.log('Available dates loaded:', self.availableDates.length, 'dates');
                    console.log('First 10 dates:', self.availableDates.slice(0, 10));
                    console.log('Last 5 dates:', self.availableDates.slice(-5));
                    console.log('Calendar initialized on month:', instance.currentMonth, 'year:', instance.currentYear);

                    // Jump to first available date's month
                    instance.jumpToDate(self.availableDates[0]);

                    // Set default date if none selected
                    if (!self.selectedDate && self.availableDates.length > 0) {
                        self.selectedDate = self.availableDates[0];
                        console.log('Default collection date set:', self.selectedDate);

                        // CRITICAL: Also set hidden field for Block Checkout
                        const $checkoutForm = $('form.wc-block-checkout__form, form.checkout');
                        if ($checkoutForm.length > 0) {
                            $checkoutForm.find('input[name="wc_collection_date"]').remove();
                            $checkoutForm.append('<input type="hidden" name="wc_collection_date" value="' + self.selectedDate + '" />');
                            console.log('Added default hidden field wc_collection_date with value:', self.selectedDate);
                        }
                    }
                },
                onChange: function(selectedDates, dateStr, instance) {
                    console.log('Date selected:', dateStr);
                    self.onDateChange(dateStr);
                },
                onMonthChange: function(selectedDates, dateStr, instance) {
                    console.log('Month changed to:', instance.currentMonth + 1, 'year:', instance.currentYear);
                },
                onYearChange: function(selectedDates, dateStr, instance) {
                    console.log('Year changed to:', instance.currentYear);
                }
            });

            // Remove readonly attribute to allow Flatpickr to work
            $(input).prop('readonly', false);

            // Mark as initialized
            $(input).closest('.wc-collection-date-wrapper').addClass('initialized');
        },

        /**
         * Get Flatpickr date format
         */
        getFlatpickrFormat: function() {
            return wcCollectionDate.dateFormat || 'Y-m-d';
        },

        /**
         * Handle date change
         */
        onDateChange: function(dateStr) {
            // Store selected date in memory
            this.selectedDate = dateStr;
            console.log('Collection date stored in memory:', dateStr);

            const $input = $('#collection_date');

            // Update input value if it exists
            if ($input.length > 0) {
                $input.val(dateStr);
                console.log('Collection date updated in input field:', dateStr);

                // Remove error styling if present
                $input.closest('.form-row').removeClass('woocommerce-invalid');
                $input.closest('.form-row').find('.woocommerce-error').remove();
            } else {
                console.log('Input field not found - date stored in memory only');
            }

            // CRITICAL: Also update any hidden fields in the checkout form for Block Checkout
            const $checkoutForm = $('form.wc-block-checkout__form, form.checkout');
            if ($checkoutForm.length > 0) {
                // Remove any existing hidden field
                $checkoutForm.find('input[name="wc_collection_date"]').remove();

                // Add new hidden field with the selected date
                $checkoutForm.append('<input type="hidden" name="wc_collection_date" value="' + dateStr + '" />');
                console.log('Added hidden field wc_collection_date with value:', dateStr);
            }

            // Trigger WooCommerce update
            $(document.body).trigger('update_checkout');

            // Trigger custom event
            $(document.body).trigger('wc_collection_date_changed', [dateStr]);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const $wrapper = $('.wc-collection-date-wrapper');

            if ($wrapper.length) {
                const $error = $('<div class="wc-collection-date-error"></div>').text(message);
                $wrapper.append($error);
            }

            console.error('Collection Date Error:', message);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Reinitialize on checkout update
            $(document.body).on('updated_checkout', function() {
                if (!$('.wc-collection-date-wrapper').hasClass('initialized')) {
                    self.loadAvailableDates();
                }
            });

            // Handle checkout validation
            $(document.body).on('checkout_error', function() {
                const $field = $('#collection_date_field');

                if ($field.find('.woocommerce-error').length) {
                    // Scroll to error
                    $('html, body').animate({
                        scrollTop: $field.offset().top - 100
                    }, 500);

                    // Focus on input
                    $('#collection_date').trigger('focus');
                }
            });

            // Prevent manual input
            $('#collection_date').on('keydown paste', function(e) {
                e.preventDefault();
                return false;
            });

            // Block Checkout: Add collection date to checkout data
            const registerBlockCheckoutHooks = function() {
                console.log('Checking for WooCommerce Blocks...', {
                    hasWp: typeof window.wp !== 'undefined',
                    hasWpData: typeof window.wp?.data !== 'undefined',
                    hasWpHooks: typeof window.wp?.hooks !== 'undefined',
                    hasAddFilter: typeof window.wp?.hooks?.addFilter !== 'undefined'
                });

                if (!window.wp || !window.wp.hooks || !window.wp.hooks.addFilter) {
                    console.log('WooCommerce Blocks hooks not ready yet, will retry...');
                    return false;
                }

                console.log('WooCommerce Blocks detected - setting up Store API integration');

                // Hook into WooCommerce Blocks checkout validation
                wp.hooks.addFilter(
                    'woocommerce_store_api_validate_checkout',
                    'wc-collection-date',
                    function(errors) {
                        // Check memory first, fallback to DOM
                        const collectionDate = CollectionDatePicker.selectedDate || $('#collection_date').val() || '';

                        console.log('Block Checkout: Validating collection date from memory:', CollectionDatePicker.selectedDate);
                        console.log('Block Checkout: Validating collection date from DOM:', $('#collection_date').val());
                        console.log('Block Checkout: Using collection date for validation:', collectionDate);

                        if (!collectionDate || collectionDate.trim() === '') {
                            console.error('Block Checkout: Collection date is required!');
                            errors.push({
                                message: wcCollectionDate.i18n.selectDate || 'Please select a collection date',
                                field: 'collection_date'
                            });
                        }

                        return errors;
                    }
                );

                // Hook into WooCommerce Blocks checkout data
                wp.hooks.addFilter(
                    'woocommerce_store_api_checkout_data',
                    'wc-collection-date',
                    function(data) {
                        // Try to get from memory first, fallback to DOM
                        const collectionDate = CollectionDatePicker.selectedDate || $('#collection_date').val() || '';

                        console.log('Block Checkout: Collection date from memory:', CollectionDatePicker.selectedDate);
                        console.log('Block Checkout: Collection date from DOM:', $('#collection_date').val());
                        console.log('Block Checkout: Using collection date:', collectionDate);

                        if (!data.extensions) {
                            data.extensions = {};
                        }
                        if (!data.extensions['wc-collection-date']) {
                            data.extensions['wc-collection-date'] = {};
                        }

                        // Always add the field, even if empty, so we can validate server-side
                        data.extensions['wc-collection-date'].collection_date = collectionDate;

                        console.log('Block Checkout: Final data with collection date:', data);
                        return data;
                    }
                );

                return true;
            };

            // Try to register hooks immediately
            if (!registerBlockCheckoutHooks()) {
                // If failed, retry every 500ms for up to 5 seconds
                let retries = 0;
                const maxRetries = 10;
                const retryInterval = setInterval(function() {
                    retries++;
                    console.log('Retry attempt', retries, 'of', maxRetries);

                    if (registerBlockCheckoutHooks()) {
                        console.log('Successfully registered Block Checkout hooks');
                        clearInterval(retryInterval);
                    } else if (retries >= maxRetries) {
                        console.error('Failed to register Block Checkout hooks after', maxRetries, 'attempts');
                        clearInterval(retryInterval);
                    }
                }, 500);
            }

            // CRITICAL: Intercept fetch() API requests to Store API
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                let [url, config] = args;

                // Check if this is a Store API checkout request
                if (url && url.includes('/wc/store/v1/checkout')) {
                    console.log('🎯 Intercepted fetch() call to Store API checkout');
                    console.log('Collection date in memory:', CollectionDatePicker.selectedDate);

                    // Modify the request body to include collection date
                    if (config && config.body && CollectionDatePicker.selectedDate) {
                        try {
                            const body = JSON.parse(config.body);

                            if (!body.extensions) {
                                body.extensions = {};
                            }
                            if (!body.extensions['wc-collection-date']) {
                                body.extensions['wc-collection-date'] = {};
                            }

                            body.extensions['wc-collection-date'].collection_date = CollectionDatePicker.selectedDate;
                            config.body = JSON.stringify(body);

                            console.log('✅ Modified checkout request with collection date:', CollectionDatePicker.selectedDate);
                            console.log('Modified body:', body);
                        } catch (error) {
                            console.error('❌ Failed to modify checkout request:', error);
                        }
                    } else {
                        console.warn('⚠️ Could not modify request - missing config.body or selectedDate');
                    }
                }

                // Call original fetch
                return originalFetch.apply(this, args);
            };

            console.log('✅ Fetch interception installed');
        },

        /**
         * Validate selected date
         */
        validateDate: function(date) {
            return this.availableDates.indexOf(date) !== -1;
        },

        /**
         * Get selected date
         */
        getSelectedDate: function() {
            return this.selectedDate || $('#collection_date').val();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Wait for WooCommerce checkout to be ready
        if (typeof wc_checkout_params !== 'undefined') {
            CollectionDatePicker.init();
        } else {
            // Fallback if checkout params not loaded
            setTimeout(function() {
                CollectionDatePicker.init();
            }, 500);
        }
    });

    // Expose to global scope for debugging
    window.wcCollectionDatePicker = CollectionDatePicker;

})(jQuery);
