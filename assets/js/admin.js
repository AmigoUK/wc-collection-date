/**
 * Admin JavaScript for WooCommerce Collection Date
 *
 * @package WC_Collection_Date
 */

(function($) {
    'use strict';

    /**
     * Admin module
     */
    const WCCollectionDateAdmin = {

        /**
         * Initialize
         */
        init() {
            console.log('WC Collection Date Admin loaded');

            this.initDatePicker();
            this.initAddExclusion();
            this.initRemoveExclusion();
            this.initFormValidation();
        },

        /**
         * Initialize date picker
         */
        initDatePicker() {
            $('.exclusion-date-picker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                maxDate: '+2y',
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                beforeShowDay(date) {
                    const excluded = WCCollectionDateAdmin.getExcludedDates();
                    const dateString = $.datepicker.formatDate('yy-mm-dd', date);

                    if (excluded.includes(dateString)) {
                        return [false, 'excluded-date', 'Already excluded'];
                    }

                    return [true, '', ''];
                },
                onSelect(dateText) {
                    $(this).val(dateText);
                    $('#exclusion-reason').focus();
                }
            });
        },

        /**
         * Get currently excluded dates
         *
         * @return {Array} Array of date strings
         */
        getExcludedDates() {
            const dates = [];
            $('#exclusions-list tr[data-date]').each(function() {
                dates.push($(this).data('date'));
            });
            return dates;
        },

        /**
         * Initialize add exclusion handler
         */
        initAddExclusion() {
            $('#add-exclusion').on('click', function(e) {
                e.preventDefault();
                WCCollectionDateAdmin.addExclusion();
            });

            $('#exclusion-date, #exclusion-reason').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    WCCollectionDateAdmin.addExclusion();
                }
            });
        },

        /**
         * Add exclusion via AJAX
         */
        addExclusion() {
            const $button = $('#add-exclusion');
            const date = $('#exclusion-date').val().trim();
            const reason = $('#exclusion-reason').val().trim();

            if (!date) {
                alert(wcCollectionDateAdmin.i18n.invalid_date);
                $('#exclusion-date').focus();
                return;
            }

            if (!this.validateDate(date)) {
                alert(wcCollectionDateAdmin.i18n.invalid_date);
                $('#exclusion-date').val('').focus();
                return;
            }

            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: wcCollectionDateAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_collection_date_add_exclusion',
                    nonce: wcCollectionDateAdmin.nonce,
                    date: date,
                    reason: reason
                },
                success(response) {
                    if (response.success) {
                        WCCollectionDateAdmin.displaySuccess(response.data.message);
                        WCCollectionDateAdmin.addExclusionRow(
                            response.data.date,
                            response.data.formatted_date,
                            response.data.reason
                        );
                        $('#exclusion-date, #exclusion-reason').val('');
                        $('#exclusion-date').datepicker('refresh');
                    } else {
                        WCCollectionDateAdmin.displayError(response.data.message);
                    }
                },
                error(xhr) {
                    const message = xhr.responseJSON?.data?.message ||
                                  'An error occurred. Please try again.';
                    WCCollectionDateAdmin.displayError(message);
                },
                complete() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Add exclusion row to table
         *
         * @param {string} date Date in YYYY-MM-DD format
         * @param {string} formattedDate Localized date string
         * @param {string} reason Exclusion reason
         */
        addExclusionRow(date, formattedDate, reason) {
            const $tbody = $('#exclusions-list');

            $tbody.find('.no-items').remove();

            const reasonDisplay = reason || '—';

            const $row = $('<tr></tr>')
                .attr('data-date', date)
                .html(`
                    <td class="column-date">${this.escapeHtml(formattedDate)}</td>
                    <td class="column-reason">${this.escapeHtml(reasonDisplay)}</td>
                    <td class="column-actions">
                        <button type="button"
                                class="button button-link-delete remove-exclusion"
                                data-date="${this.escapeAttr(date)}">
                            Delete
                        </button>
                    </td>
                `);

            const rows = $tbody.find('tr[data-date]').toArray();
            rows.push($row[0]);

            rows.sort((a, b) => {
                const dateA = $(a).data('date');
                const dateB = $(b).data('date');
                return dateA.localeCompare(dateB);
            });

            $tbody.empty().append(rows);

            $row.hide().fadeIn(300);
        },

        /**
         * Initialize remove exclusion handler
         */
        initRemoveExclusion() {
            $(document).on('click', '.remove-exclusion', function(e) {
                e.preventDefault();

                if (!confirm(wcCollectionDateAdmin.i18n.confirm_delete)) {
                    return;
                }

                const date = $(this).data('date');
                WCCollectionDateAdmin.removeExclusion(date, $(this).closest('tr'));
            });
        },

        /**
         * Remove exclusion via AJAX
         *
         * @param {string} date Date to remove
         * @param {jQuery} $row Table row element
         */
        removeExclusion(date, $row) {
            $row.css('opacity', '0.5');

            $.ajax({
                url: wcCollectionDateAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_collection_date_remove_exclusion',
                    nonce: wcCollectionDateAdmin.nonce,
                    date: date
                },
                success(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();

                            if ($('#exclusions-list tr[data-date]').length === 0) {
                                $('#exclusions-list').html(`
                                    <tr class="no-items">
                                        <td colspan="3" class="colspanchange">
                                            No excluded dates.
                                        </td>
                                    </tr>
                                `);
                            }
                        });

                        WCCollectionDateAdmin.displaySuccess(response.data.message);
                        $('#exclusion-date').datepicker('refresh');
                    } else {
                        $row.css('opacity', '1');
                        WCCollectionDateAdmin.displayError(response.data.message);
                    }
                },
                error(xhr) {
                    $row.css('opacity', '1');
                    const message = xhr.responseJSON?.data?.message ||
                                  'An error occurred. Please try again.';
                    WCCollectionDateAdmin.displayError(message);
                }
            });
        },

        /**
         * Initialize form validation
         */
        initFormValidation() {
            $('form').on('submit', function() {
                const workingDays = $('input[name="wc_collection_date_working_days[]"]:checked').length;

                if (workingDays === 0) {
                    alert('Please select at least one working day.');
                    return false;
                }

                const leadTime = parseInt($('#wc_collection_date_lead_time').val(), 10);
                if (isNaN(leadTime) || leadTime < 0 || leadTime > 365) {
                    alert('Lead time must be between 0 and 365 days.');
                    $('#wc_collection_date_lead_time').focus();
                    return false;
                }

                const maxBooking = parseInt($('#wc_collection_date_max_booking_days').val(), 10);
                if (isNaN(maxBooking) || maxBooking < 1 || maxBooking > 365) {
                    alert('Maximum booking days must be between 1 and 365.');
                    $('#wc_collection_date_max_booking_days').focus();
                    return false;
                }

                return true;
            });
        },

        /**
         * Validate date format
         *
         * @param {string} date Date string to validate
         * @return {boolean} True if valid
         */
        validateDate(date) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                return false;
            }

            const dateObj = new Date(date);
            const [year, month, day] = date.split('-').map(Number);

            return dateObj.getFullYear() === year &&
                   dateObj.getMonth() === month - 1 &&
                   dateObj.getDate() === day;
        },

        /**
         * Display success message
         *
         * @param {string} message Message text
         */
        displaySuccess(message) {
            this.displayNotice(message, 'success');
        },

        /**
         * Display error message
         *
         * @param {string} message Message text
         */
        displayError(message) {
            this.displayNotice(message, 'error');
        },

        /**
         * Display notice
         *
         * @param {string} message Message text
         * @param {string} type Notice type (success/error/warning/info)
         */
        displayNotice(message, type = 'info') {
            const $notice = $('<div></div>')
                .addClass(`notice notice-${type} is-dismissible`)
                .html(`<p>${this.escapeHtml(message)}</p>`);

            $('.wc-collection-date-settings h1').after($notice);

            $notice.hide().fadeIn(300);

            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            $(document).trigger('wp-updates-notice-added');
        },

        /**
         * Escape HTML
         *
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Escape attribute value
         *
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeAttr(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if (typeof wcCollectionDateAdmin !== 'undefined') {
            WCCollectionDateAdmin.init();
        }
    });

})(jQuery);
