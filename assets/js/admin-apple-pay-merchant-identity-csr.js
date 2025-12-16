/**
 * Admin Apple Pay Merchant Identity CSR Generation
 *
 * Handles Merchant Identity CSR generation from the WordPress admin interface.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $generateButton = $('#cko-generate-merchant-identity-csr-button');
        const $statusDiv = $('#cko-merchant-identity-csr-status');
        const $instructionsDiv = $('#cko-merchant-identity-csr-instructions');

        if (!$generateButton.length) {
            return; // Exit if not on the right page
        }

        $generateButton.on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(ckoMerchantIdentityCsrData.i18n.generating);
            $statusDiv.hide().removeClass('cko-status-message notice notice-success notice-error');

            // Make AJAX request
            $.ajax({
                url: ckoMerchantIdentityCsrData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cko_generate_apple_pay_merchant_identity_csr',
                    nonce: ckoMerchantIdentityCsrData.nonce
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Download CSR file
                        downloadFile(
                            atob(response.data.csr),
                            response.data.csr_filename || 'uploadMe.csr',
                            'text/plain'
                        );

                        // Download private key file
                        downloadFile(
                            atob(response.data.private_key),
                            response.data.private_key_filename || 'certificate_sandbox.key',
                            'text/plain'
                        );

                        // Show success message with server paths
                        let successMsg = '<p><strong>' + ckoMerchantIdentityCsrData.i18n.success + '</strong></p>';
                        if (response.data && response.data.key_file_path) {
                            successMsg += '<p><strong>Private Key saved to:</strong> <code>' + response.data.key_file_path + '</code></p>';
                            successMsg += '<p><small>Note: The CSR file (uploadMe.csr) has been downloaded. Upload it to Apple Developer to get your signed certificate.</small></p>';
                        }
                        
                        $statusDiv
                            .removeClass('notice notice-error')
                            .addClass('cko-status-message notice notice-success')
                            .html(successMsg)
                            .show();

                        // Show instructions
                        $instructionsDiv.slideDown();

                        // Reset button after 2 seconds
                        setTimeout(function() {
                            $button.prop('disabled', false).text(originalText);
                        }, 2000);
                    } else {
                        // Show error message
                        let errorMsg = '';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.data) {
                            errorMsg = JSON.stringify(response.data);
                        } else {
                            errorMsg = 'Unknown error';
                        }

                        $statusDiv
                            .removeClass('notice notice-success')
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + ckoMerchantIdentityCsrData.i18n.error + '</strong> ' + errorMsg + '</p>')
                            .show();

                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    $statusDiv
                        .removeClass('notice notice-success')
                        .addClass('cko-status-message notice notice-error')
                        .html('<p><strong>' + ckoMerchantIdentityCsrData.i18n.error + '</strong> ' + error + '</p>')
                        .show();

                    $button.prop('disabled', false).text(originalText);
                }
            });
        });

        /**
         * Download file
         *
         * @param {string} content File content
         * @param {string} filename Filename for download
         * @param {string} mimeType MIME type
         */
        function downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType || 'text/plain' });
            const url = window.URL.createObjectURL(blob);

            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();

            // Clean up
            setTimeout(function() {
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            }, 100);
        }
    });
})(jQuery);

