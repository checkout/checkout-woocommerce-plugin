/**
 * Admin Apple Pay Domain Association Upload
 *
 * Handles domain association file upload from the WordPress admin interface.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $uploadButton = $('#cko-upload-domain-association-button');
        const $fileInput = $('#cko-domain-association-upload');
        const $statusDiv = $('#cko-domain-association-status');

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
                    .html('<p><strong>' + ckoDomainAssociationData.i18n.error + '</strong> ' + ckoDomainAssociationData.i18n.noFile + '</p>')
                    .show();
                return;
            }

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.txt')) {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoDomainAssociationData.i18n.error + '</strong> Please select a valid .txt domain association file.' + '</p>')
                    .show();
                return;
            }

            const $button = $(this);
            const originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text(ckoDomainAssociationData.i18n.uploading);
            $statusDiv.hide().removeClass('notice notice-success notice-error');

            // Read file as text
            const reader = new FileReader();
            reader.onload = function(e) {
                const fileContent = e.target.result;

                // Make AJAX request
                $.ajax({
                    url: ckoDomainAssociationData.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cko_upload_apple_pay_domain_association',
                        nonce: ckoDomainAssociationData.nonce,
                        file_content: fileContent,
                        filename: file.name
                    },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message with file location
                            let successMsg = '<p><strong>' + ckoDomainAssociationData.i18n.success + '</strong></p>';
                            if (response.data && response.data.file_url) {
                                successMsg += '<p><strong>File URL:</strong> <a href="' + response.data.file_url + '" target="_blank">' + response.data.file_url + '</a></p>';
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
                                .html('<p><strong>' + ckoDomainAssociationData.i18n.error + '</strong> ' + errorMsg + '</p>')
                                .show();

                            $button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        $statusDiv
                            .removeClass('notice-success')
                            .addClass('cko-status-message notice notice-error')
                            .html('<p><strong>' + ckoDomainAssociationData.i18n.error + '</strong> ' + error + '</p>')
                            .show();

                        $button.prop('disabled', false).text(originalText);
                    }
                });
            };

            reader.onerror = function() {
                $statusDiv
                    .removeClass('notice-success')
                    .addClass('cko-status-message notice notice-error')
                    .html('<p><strong>' + ckoDomainAssociationData.i18n.error + '</strong> Failed to read domain association file.' + '</p>')
                    .show();

                $button.prop('disabled', false).text(originalText);
            };

            // Read file as text
            reader.readAsText(file);
        });
    });
})(jQuery);





