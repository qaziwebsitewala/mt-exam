/**
 * Admin ID Column Script
 * Handles copy-to-clipboard functionality for ID badges
 */

(function($) {
    'use strict';

    /**
     * ID Column Copy Handler
     */
    const EMIDColumn = {
        /**
         * Initialize the ID column functionality
         */
        init: function() {
            this.bindEvents();
            this.checkClipboardAPI();
        },

        /**
         * Bind click events to ID badges
         */
        bindEvents: function() {
            $(document).on('click', '.em-id-badge', this.copyToClipboard.bind(this));
        },

        /**
         * Check if Clipboard API is available
         */
        checkClipboardAPI: function() {
            if (!navigator.clipboard) {
                console.warn('Clipboard API not available. Copy functionality may not work.');
            }
        },

        /**
         * Copy ID to clipboard
         * @param {Event} e - Click event
         */
        copyToClipboard: function(e) {
            e.preventDefault();
            
            const $badge = $(e.currentTarget);
            const id = $badge.data('id');
            const $copiedMsg = $badge.siblings('.em-id-copied');

            // Try modern Clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                this.copyWithClipboardAPI(id, $copiedMsg, $badge);
            } else {
                // Fallback to older method
                this.copyWithFallback(id, $copiedMsg, $badge);
            }
        },

        /**
         * Copy using modern Clipboard API
         * @param {string} id - ID to copy
         * @param {jQuery} $copiedMsg - Copied message element
         * @param {jQuery} $badge - Badge element
         */
        copyWithClipboardAPI: function(id, $copiedMsg, $badge) {
            navigator.clipboard.writeText(id)
                .then(() => {
                    this.showCopiedMessage($copiedMsg, $badge);
                })
                .catch((err) => {
                    console.error('Failed to copy ID:', err);
                    this.showErrorMessage($badge);
                });
        },

        /**
         * Copy using fallback method (document.execCommand)
         * @param {string} id - ID to copy
         * @param {jQuery} $copiedMsg - Copied message element
         * @param {jQuery} $badge - Badge element
         */
        copyWithFallback: function(id, $copiedMsg, $badge) {
            // Create temporary input element
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(id).select();

            try {
                const successful = document.execCommand('copy');
                
                if (successful) {
                    this.showCopiedMessage($copiedMsg, $badge);
                } else {
                    this.showErrorMessage($badge);
                }
            } catch (err) {
                console.error('Failed to copy ID:', err);
                this.showErrorMessage($badge);
            }

            // Remove temporary input
            $temp.remove();
        },

        /**
         * Show "Copied!" confirmation message
         * @param {jQuery} $copiedMsg - Copied message element
         * @param {jQuery} $badge - Badge element
         */
        showCopiedMessage: function($copiedMsg, $badge) {
            // Show message with animation
            $copiedMsg.addClass('show').show();

            // Add visual feedback to badge
            $badge.css('background', '#46b450');

            // Hide message and restore badge color after 2 seconds
            setTimeout(() => {
                $copiedMsg.removeClass('show').fadeOut();
                $badge.css('background', '');
            }, 2000);
        },

        /**
         * Show error message if copy fails
         * @param {jQuery} $badge - Badge element
         */
        showErrorMessage: function($badge) {
            const originalText = $badge.text();
            
            $badge
                .text('Error!')
                .css('background', '#dc3232');

            setTimeout(() => {
                $badge
                    .text(originalText)
                    .css('background', '');
            }, 2000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        EMIDColumn.init();
    });

})(jQuery);