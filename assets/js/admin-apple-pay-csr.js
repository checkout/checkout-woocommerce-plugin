/**
 * Admin Apple Pay CSR Generation
 *
 * Handles CSR generation from the WordPress admin interface.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $generateButton = $('#cko-generate-csr-button');
        const $statusDiv = $('#cko-csr-status');
        const $instructionsDiv = $('#cko-csr-instructions');

        if (!$generateButton.length) {
            return; // Exit if not on the right page
        }

        $generateButton.on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(ckoCsrData.i18n.generating);
            $statusDiv.hide().removeClass('cko-status-message notice notice-success notice-error notice-success');

            // Make AJAX request
            $.ajax({
                url: ckoCsrData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cko_generate_apple_pay_csr',
                    nonce: ckoCsrData.nonce,
                    protocol_version: 'ec_v1'
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Download the CSR file
                        downloadCSR(response.data.csr_content, response.data.filename);

                        // Show success message
                        $statusDiv
                            .removeClass('notice notice-error')
                            .addClass('cko-status-message notice notice-success notice-success')
                            .html('<p><strong>' + ckoCsrData.i18n.success + '</strong></p>')
                            .show();

                        // Show instructions
                        $instructionsDiv.slideDown();

                        // Reset button after 2 seconds
                        setTimeout(function() {
                            $button.prop('disabled', false).text(originalText);
                        }, 2000);
                    } else {
                        // Show error message with full details
                        let errorMsg = '';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.data) {
                            errorMsg = JSON.stringify(response.data);
                        } else {
                            errorMsg = 'Unknown error';
                        }

                        // If there's a details field, show it
                        let errorDetails = '';
                        if (response.data && response.data.details) {
                            try {
                                const details = typeof response.data.details === 'string' 
                                    ? JSON.parse(response.data.details) 
                                    : response.data.details;
                                if (details.error_codes) {
                                    errorDetails = '<br><strong>Error Codes:</strong> ' + details.error_codes.join(', ');
                                }
                                if (details.error_type) {
                                    errorDetails = '<br><strong>Error Type:</strong> ' + details.error_type + errorDetails;
                                }
                                if (details.message && details.message !== errorMsg) {
                                    errorDetails = '<br><strong>Details:</strong> ' + details.message + errorDetails;
                                }
                            } catch (e) {
                                errorDetails = '<br><strong>Raw Response:</strong> ' + response.data.details;
                            }
                        }

                        // Show debug info if available
                        let debugInfo = '';
                        if (response.data && response.data.debug_info) {
                            debugInfo = '<br><small style="color: #666;">Debug: ' + 
                                'URL: ' + response.data.debug_info.url + ', ' +
                                'Account Type: ' + response.data.debug_info.account_type + ', ' +
                                'Environment: ' + response.data.debug_info.environment +
                                '</small>';
                        }

                        $statusDiv
                            .removeClass('notice notice-success notice-success')
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + ckoCsrData.i18n.error + '</strong> ' + errorMsg + errorDetails + debugInfo + '</p>')
                            .show();

                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    $statusDiv
                        .removeClass('notice notice-success notice-success')
                        .addClass('cko-status-message notice notice-error')
                        .html('<p><strong>' + ckoCsrData.i18n.error + '</strong> ' + error + '</p>')
                        .show();

                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        /**
         * Download CSR file
         *
         * @param {string} content CSR file content
         * @param {string} filename Filename for download
         */
        function downloadCSR(content, filename) {
            // Create a blob with the CSR content
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);

            // Create a temporary anchor element and trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = filename || 'cko.csr';
            document.body.appendChild(link);
            link.click();

            // Clean up
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }
    });
})(jQuery);

