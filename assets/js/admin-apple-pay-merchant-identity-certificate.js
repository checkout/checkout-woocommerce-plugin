/**
 * Admin Apple Pay Merchant Identity Certificate Upload
 *
 * Handles merchant identity certificate upload and conversion from the WordPress admin interface.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $uploadButton = $('#cko-upload-merchant-identity-certificate-button');
        const $fileInput = $('#cko-merchant-identity-certificate-upload');
        const $statusDiv = $('#cko-merchant-identity-certificate-status');

        if (!$uploadButton.length) {
            return; // Exit if not on the right page
        }

        $uploadButton.on('click', function(e) {
            e.preventDefault();

            const file = $fileInput[0].files[0];
            
            if (!file) {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoMerchantIdentityCertificateData.i18n.error + '</strong> ' + ckoMerchantIdentityCertificateData.i18n.noFile + '</p>')
                    .show();
                return;
            }

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.cer')) {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoMerchantIdentityCertificateData.i18n.error + '</strong> Please select a valid .cer certificate file.' + '</p>')
                    .show();
                return;
            }

            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(ckoMerchantIdentityCertificateData.i18n.uploading);
            $statusDiv.hide().removeClass('notice notice-success notice-error');

            // Read file as base64
            const reader = new FileReader();
            reader.onload = function(e) {
                // Convert to base64
                const base64Content = e.target.result.split(',')[1]; // Remove data:application/x-x509-ca-cert;base64, prefix

                // Make AJAX request
                $.ajax({
                    url: ckoMerchantIdentityCertificateData.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cko_upload_apple_pay_merchant_identity_certificate',
                        nonce: ckoMerchantIdentityCertificateData.nonce,
                        certificate: base64Content,
                        filename: file.name
                    },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message with absolute paths
                            let successMsg = '<p><strong>' + ckoMerchantIdentityCertificateData.i18n.success + '</strong></p>';
                            if (response.data && response.data.certificate_path) {
                                successMsg += '<p><strong>Certificate (.pem) saved to:</strong> <code>' + response.data.certificate_path + '</code></p>';
                            }
                            if (response.data && response.data.key_path) {
                                successMsg += '<p><strong>Private Key (.key) saved to:</strong> <code>' + response.data.key_path + '</code></p>';
                                successMsg += '<p><strong>Configure these paths:</strong></p>';
                                successMsg += '<ul style="margin-left: 20px;">';
                                successMsg += '<li><strong>Merchant Certificate Path:</strong> <code>' + response.data.certificate_path + '</code></li>';
                                successMsg += '<li><strong>Merchant Certificate Key Path:</strong> <code>' + response.data.key_path + '</code></li>';
                                successMsg += '</ul>';
                            } else {
                                successMsg += '<p><strong>Certificate (.pem) saved to:</strong> <code>' + response.data.certificate_path + '</code></p>';
                                successMsg += '<p><small>Note: Make sure the key file (certificate_sandbox.key) from Step 1 is also saved on your server.</small></p>';
                            }
                            
                            $statusDiv
                                .removeClass('notice-error')
                                .addClass('cko-status-message notice notice-success')
                                .html(successMsg)
                                .show();

                            // Reset button and file input after 2 seconds
                            setTimeout(function() {
                                $button.prop('disabled', false).text(originalText);
                                $fileInput.val('');
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
                                .removeClass('notice-success')
                                .addClass('cko-status-message notice notice-error')
                                .html('<p><strong>' + ckoMerchantIdentityCertificateData.i18n.error + '</strong> ' + errorMsg + '</p>')
                                .show();

                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        $statusDiv
                            .removeClass('notice-success')
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + ckoMerchantIdentityCertificateData.i18n.error + '</strong> ' + error + '</p>')
                            .show();

                        $button.prop('disabled', false).text(originalText);
                    }
                });
            };

            reader.onerror = function() {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoMerchantIdentityCertificateData.i18n.error + '</strong> Failed to read certificate file.' + '</p>')
                    .show();

                $button.prop('disabled', false).text(originalText);
            };

            // Read file as data URL (base64)
            reader.readAsDataURL(file);
        });
    });
})(jQuery);

