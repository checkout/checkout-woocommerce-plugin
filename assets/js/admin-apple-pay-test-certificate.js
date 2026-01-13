/**
 * Admin Apple Pay Test Certificate
 *
 * Handles certificate and key testing from the WordPress admin interface.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $testButton = $('#cko-test-certificate-button');
        const $statusDiv = $('#cko-test-certificate-status');

        if (!$testButton.length) {
            return; // Exit if not on the right page
        }

        $testButton.on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(ckoTestCertificateData.i18n.testing);
            $statusDiv.hide().removeClass('cko-status-message notice notice-success notice-error');

            // Make AJAX request
            $.ajax({
                url: ckoTestCertificateData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cko_test_apple_pay_certificate',
                    nonce: ckoTestCertificateData.nonce
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                },
                success: function(response) {
                    if (response.success) {
                        // Show simplified success message
                        let successMsg = '<p><strong>' + ckoTestCertificateData.i18n.success + '</strong></p>';
                        successMsg += '<p>' + ckoTestCertificateData.i18n.successDetails + '</p>';
                        
                        $statusDiv
                            .removeClass('notice notice-error')
                            .addClass('cko-status-message notice notice-success')
                            .html(successMsg)
                            .show();

                        // Reset button after 3 seconds
                        setTimeout(function() {
                            $button.prop('disabled', false).text(originalText);
                        }, 3000);
                    } else {
                        // Show simplified error message
                        let errorMsg = '';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else {
                            errorMsg = ckoTestCertificateData.i18n.errorDefault;
                        }

                        $statusDiv
                            .removeClass('notice notice-success')
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + ckoTestCertificateData.i18n.error + '</strong> ' + errorMsg + '</p>')
                            .show();

                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    $statusDiv
                        .removeClass('notice notice-success')
                        .addClass('cko-status-message notice notice-error')
                        .html('<p><strong>' + ckoTestCertificateData.i18n.error + '</strong> ' + error + '</p>')
                        .show();

                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });
})(jQuery);





