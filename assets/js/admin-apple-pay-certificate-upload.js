/**
 * Admin Apple Pay Certificate Upload
 *
 * Handles certificate upload from the WordPress admin interface.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $uploadButton = $('#cko-upload-certificate-button');
        const $fileInput = $('#cko-certificate-upload');
        const $statusDiv = $('#cko-certificate-status');

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
                    .html('<p><strong>' + ckoCertificateData.i18n.error + '</strong> ' + ckoCertificateData.i18n.noFile + '</p>')
                    .show();
                return;
            }

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.cer')) {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoCertificateData.i18n.error + '</strong> ' + 'Please select a valid .cer certificate file.' + '</p>')
                    .show();
                return;
            }

            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(ckoCertificateData.i18n.uploading);
            $statusDiv.hide().removeClass('notice notice-success notice-error');

            // Read file as base64
            const reader = new FileReader();
            reader.onload = function(e) {
                // Convert to base64
                const base64Content = e.target.result.split(',')[1]; // Remove data:application/x-x509-ca-cert;base64, prefix

                // Make AJAX request
                $.ajax({
                    url: ckoCertificateData.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cko_upload_apple_pay_certificate',
                        nonce: ckoCertificateData.nonce,
                        certificate: base64Content,
                        filename: file.name
                    },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $statusDiv
                                .removeClass('notice-error')
                                .addClass('cko-status-message notice notice-success notice-success')
                                .html('<p><strong>' + ckoCertificateData.i18n.success + '</strong></p>')
                                .show();

                            // Reset button and file input after 2 seconds
                            setTimeout(function() {
                                $button.prop('disabled', false).text(originalText);
                                $fileInput.val('');
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

                            // Show error data if available
                            let errorDetails = '';
                            if (response.data && response.data.error_data) {
                                const errorData = response.data.error_data;
                                if (errorData.error_codes && Array.isArray(errorData.error_codes)) {
                                    errorDetails += '<br><strong>Error Codes:</strong> ' + errorData.error_codes.join(', ');
                                }
                                if (errorData.error_type) {
                                    errorDetails += '<br><strong>Error Type:</strong> ' + errorData.error_type;
                                }
                                if (errorData.details) {
                                    errorDetails += '<br><strong>Details:</strong> ' + (typeof errorData.details === 'string' ? errorData.details : JSON.stringify(errorData.details));
                                }
                            }

                            // Show debug info if available
                            let debugInfo = '';
                            if (response.data && response.data.debug_info) {
                                debugInfo = '<br><small style="color: #666;">Debug: ' + 
                                    'URL: ' + response.data.debug_info.url + ', ' +
                                    'Account Type: ' + response.data.debug_info.account_type + ', ' +
                                    'Environment: ' + response.data.debug_info.environment;
                                if (response.data.debug_info.certificate_length) {
                                    debugInfo += ', Certificate Length: ' + response.data.debug_info.certificate_length;
                                }
                                if (response.data.debug_info.certificate_format) {
                                    debugInfo += ', Format: ' + response.data.debug_info.certificate_format;
                                }
                                debugInfo += '</small>';
                            }

                            // Show full response details if available
                            if (response.data && response.data.details) {
                                errorDetails += '<br><small style="color: #666;">Full Response: ' + response.data.details + '</small>';
                            }

                            $statusDiv
                                .removeClass('notice-success')
                                .addClass('cko-status-message notice notice-error')
                                .html('<p><strong>' + ckoCertificateData.i18n.error + '</strong> ' + errorMsg + errorDetails + debugInfo + '</p>')
                                .show();

                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        $statusDiv
                            .removeClass('notice-success')
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + ckoCertificateData.i18n.error + '</strong> ' + error + '</p>')
                            .show();

                        $button.prop('disabled', false).text(originalText);
                    }
                });
            };

            reader.onerror = function() {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoCertificateData.i18n.error + '</strong> ' + 'Failed to read certificate file.' + '</p>')
                    .show();

                $button.prop('disabled', false).text(originalText);
            };

            // Read file as data URL (base64)
            reader.readAsDataURL(file);
        });
    });
})(jQuery);

