/**
 * Admin script for generating Apple Pay merchant certificate and private key.
 *
 * @package wc_checkout_com
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle Generate Certificate and Key button click
        $('#cko-generate-merchant-certificate-button').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $statusDiv = $('#cko-merchant-certificate-status');
            const originalButtonText = $button.text();

            // Disable button and show loading
            $button.prop('disabled', true).text(ckoMerchantCertificateData.i18n.generating);
            $statusDiv.hide().removeClass('cko-status-message notice notice-success notice-error notice-success');

            // Make AJAX request
            $.ajax({
                url: ckoMerchantCertificateData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cko_generate_apple_pay_merchant_certificate',
                    nonce: ckoMerchantCertificateData.nonce
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Decode base64 certificate and key
                        const certificate = atob(response.data.certificate);
                        const privateKey = atob(response.data.private_key);

                        // Create download links
                        const certificateBlob = new Blob([certificate], { type: 'application/x-pem-file' });
                        const privateKeyBlob = new Blob([privateKey], { type: 'application/x-pem-file' });

                        const certificateUrl = URL.createObjectURL(certificateBlob);
                        const privateKeyUrl = URL.createObjectURL(privateKeyBlob);

                        // Create download links
                        const certificateLink = $('<a>')
                            .attr('href', certificateUrl)
                            .attr('download', response.data.certificate_filename)
                            .text('Download Certificate')
                            .addClass('button button-secondary')
                            .css('margin-right', '10px');

                        const privateKeyLink = $('<a>')
                            .attr('href', privateKeyUrl)
                            .attr('download', response.data.private_key_filename)
                            .text('Download Private Key')
                            .addClass('button button-secondary');

                        // Show success message with download links
                        $statusDiv
                            .addClass('cko-status-message notice notice-success notice-success')
                            .html('<p><strong>' + response.data.message + '</strong></p>' +
                                  '<p>' + certificateLink[0].outerHTML + privateKeyLink[0].outerHTML + '</p>')
                            .show();

                        // Trigger downloads after a short delay
                        setTimeout(function() {
                            certificateLink[0].click();
                            setTimeout(function() {
                                privateKeyLink[0].click();
                            }, 500);
                        }, 100);

                        // Clean up object URLs after downloads
                        setTimeout(function() {
                            URL.revokeObjectURL(certificateUrl);
                            URL.revokeObjectURL(privateKeyUrl);
                        }, 1000);
                    } else {
                        // Show error message
                        let errorMessage = ckoMerchantCertificateData.i18n.error + ' ';
                        if (response.data && response.data.message) {
                            errorMessage += response.data.message;
                        } else {
                            errorMessage += 'Unknown error occurred.';
                        }

                        // Show detailed error if available
                        if (response.data && response.data.error_codes) {
                            errorMessage += '<br>Error codes: ' + response.data.error_codes.join(', ');
                        }
                        if (response.data && response.data.error_type) {
                            errorMessage += '<br>Error type: ' + response.data.error_type;
                        }
                        if (response.data && response.data.debug_info) {
                            errorMessage += '<br>Debug: ' + JSON.stringify(response.data.debug_info);
                        }

                        $statusDiv
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + errorMessage + '</strong></p>')
                            .show();
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = ckoMerchantCertificateData.i18n.error + ' ';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage += xhr.responseJSON.data.message;
                    } else {
                        errorMessage += error || 'Network error occurred.';
                    }

                    $statusDiv
                        .addClass('notice notice-error')
                        .html('<p><strong>' + errorMessage + '</strong></p>')
                        .show();
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text(originalButtonText);
                }
            });
        });
    });
})(jQuery);
