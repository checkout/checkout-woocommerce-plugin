/**
 * Apple Pay Settings UX Enhancements
 * 
 * Features:
 * - Progress tracking and status updates
 * - Card state management
 * - Setup completion detection
 * - Visual feedback for user actions
 *
 * @package wc_checkout_com
 */

(function($) {
    'use strict';

    /**
     * Apple Pay Settings UX Manager
     */
    const ApplePaySettingsUX = {
        
    /**
     * Initialize UX enhancements
     */
    init: function() {
        this.updateCardStates();
        this.setupProgressTracking();
        this.enhanceFileUploads();
        this.setupStatusUpdates();
        this.setupCollapsibleSections();
        this.addSecurityWarnings();
    },

        /**
         * Update card states based on field completion
         */
        updateCardStates: function() {
            $('.cko-settings-card').each(function() {
                const $card = $(this);
                const cardId = $card.data('card-id');
                
                if (!cardId) return;

                let isCompleted = false;
                let hasError = false;
                let isInProgress = false;

                // Check completion based on card type
                switch(cardId) {
                    case 'payment-processing-certificate':
                        isCompleted = ApplePaySettingsUX.checkPaymentProcessingCertificate();
                        break;
                    case 'domain-association':
                        isCompleted = ApplePaySettingsUX.checkDomainAssociation();
                        break;
                    case 'merchant-identity-certificate':
                        isCompleted = ApplePaySettingsUX.checkMerchantIdentityCertificate();
                        break;
                    case 'apple-pay-configuration':
                        isCompleted = ApplePaySettingsUX.checkApplePayConfiguration();
                        break;
                    case 'express-checkout':
                        isCompleted = ApplePaySettingsUX.checkExpressCheckout();
                        break;
                }

                // Update card classes
                $card.removeClass('completed in-progress error required');
                
                if (hasError) {
                    $card.addClass('error');
                } else if (isCompleted) {
                    $card.addClass('completed');
                } else if (isInProgress) {
                    $card.addClass('in-progress');
                } else {
                    $card.addClass('required');
                }

                // Update status badge
                const $badge = $card.find('.cko-status-badge');
                if ($badge.length) {
                    $badge.removeClass('completed in-progress required error');
                    if (hasError) {
                        $badge.addClass('error').text('Error');
                    } else if (isCompleted) {
                        $badge.addClass('completed').text('Completed');
                    } else if (isInProgress) {
                        $badge.addClass('in-progress').text('In Progress');
                    } else {
                        $badge.addClass('required').text('Required');
                    }
                }
            });
        },

        /**
         * Check if payment processing certificate is set up
         */
        checkPaymentProcessingCertificate: function() {
            // Check if certificate has been uploaded
            const certificateUploaded = $('#cko-certificate-status').hasClass('notice-success') ||
                                       $('input[name="apple_pay_certificate"]').val() !== '';
            return certificateUploaded;
        },

        /**
         * Check if domain association is set up
         */
        checkDomainAssociation: function() {
            // Check if domain association file has been uploaded
            const domainFileUploaded = $('#cko-domain-association-status').hasClass('notice-success') ||
                                      $('input[name="apple_pay_domain_association"]').val() !== '';
            return domainFileUploaded;
        },

        /**
         * Check if merchant identity certificate is set up
         */
        checkMerchantIdentityCertificate: function() {
            // Check if merchant identity certificate and key paths are configured
            const certPath = $('input[name="ckocom_apple_certificate"]').val();
            const keyPath = $('input[name="ckocom_apple_key"]').val();
            return certPath !== '' && keyPath !== '';
        },

        /**
         * Check if Apple Pay configuration is complete
         */
        checkApplePayConfiguration: function() {
            const merchantId = $('input[name="ckocom_apple_mercahnt_id"]').val();
            const domainName = $('input[name="apple_pay_domain_name"]').val();
            const displayName = $('input[name="apple_pay_display_name"]').val();
            const certPath = $('input[name="ckocom_apple_certificate"]').val();
            const keyPath = $('input[name="ckocom_apple_key"]').val();
            
            return merchantId !== '' && 
                   domainName !== '' && 
                   displayName !== '' && 
                   certPath !== '' && 
                   keyPath !== '';
        },

        /**
         * Check if express checkout is configured
         */
        checkExpressCheckout: function() {
            const expressEnabled = $('input[name="apple_pay_express"]').is(':checked');
            return expressEnabled;
        },

        /**
         * Setup progress tracking
         */
        setupProgressTracking: function() {
            // Update progress steps based on completion
            $('.cko-progress-step').each(function(index) {
                const $step = $(this);
                const stepNumber = index + 1;
                
                // Check if this step is completed
                let isCompleted = false;
                let isActive = false;

                switch(stepNumber) {
                    case 1:
                        isCompleted = ApplePaySettingsUX.checkPaymentProcessingCertificate();
                        isActive = !isCompleted;
                        break;
                    case 2:
                        isCompleted = ApplePaySettingsUX.checkDomainAssociation();
                        isActive = !isCompleted && ApplePaySettingsUX.checkPaymentProcessingCertificate();
                        break;
                    case 3:
                        isCompleted = ApplePaySettingsUX.checkMerchantIdentityCertificate();
                        isActive = !isCompleted && 
                                  ApplePaySettingsUX.checkPaymentProcessingCertificate() &&
                                  ApplePaySettingsUX.checkDomainAssociation();
                        break;
                }

                $step.removeClass('completed active');
                if (isCompleted) {
                    $step.addClass('completed');
                } else if (isActive) {
                    $step.addClass('active');
                }
            });
        },

        /**
         * Enhance file upload areas with drag and drop
         */
        enhanceFileUploads: function() {
            $('.cko-file-upload-area').each(function() {
                const $area = $(this);
                const $input = $area.find('input[type="file"]');

                // Drag and drop handlers
                $area.on('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $area.addClass('drag-over');
                });

                $area.on('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $area.removeClass('drag-over');
                });

                $area.on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $area.removeClass('drag-over');
                    
                    const files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $input[0].files = files;
                        $input.trigger('change');
                    }
                });

                // File input change handler
                $input.on('change', function() {
                    const fileName = $(this).val().split('\\').pop();
                    if (fileName) {
                        $area.find('.file-name').remove();
                        $area.append('<div class="file-name" style="margin-top: 12px; font-size: 13px; color: #646970;"><strong>Selected:</strong> ' + fileName + '</div>');
                    }
                });
            });
        },

        /**
         * Setup status update listeners
         */
        setupStatusUpdates: function() {
            // Listen for AJAX success/error events
            $(document).on('ajaxSuccess', function(event, xhr, settings) {
                if (settings.url && settings.url.indexOf('cko_upload') !== -1) {
                    setTimeout(function() {
                        ApplePaySettingsUX.updateCardStates();
                        ApplePaySettingsUX.setupProgressTracking();
                    }, 500);
                }
            });

            // Listen for field changes
            $('input[type="text"], input[type="checkbox"], select').on('change blur', function() {
                setTimeout(function() {
                    ApplePaySettingsUX.updateCardStates();
                    ApplePaySettingsUX.setupProgressTracking();
                }, 300);
            });

            // Listen for certificate generation
            $(document).on('cko:certificate:generated', function() {
                setTimeout(function() {
                    ApplePaySettingsUX.updateCardStates();
                    ApplePaySettingsUX.setupProgressTracking();
                }, 500);
            });
        },

        /**
         * Show completion animation
         */
        showCompletionAnimation: function($card) {
            $card.addClass('completed');
            $card.find('.cko-card-title-icon').html('✓');
            
            // Add a subtle animation
            $card.css({
                'animation': 'pulse 0.5s ease'
            });
            
            setTimeout(function() {
                $card.css('animation', '');
            }, 500);
        },

    /**
     * Show error state
     */
    showErrorState: function($card, message) {
        $card.addClass('error');
        const $status = $card.find('.cko-status-message');
        if ($status.length) {
            $status.removeClass('notice-success notice-warning')
                   .addClass('notice-error')
                   .html('<p><strong>Error:</strong> ' + message + '</p>')
                   .show();
        }
    },

    /**
     * Setup collapsible sections for existing configuration
     */
    setupCollapsibleSections: function() {
        // Check if existing config banner is present or if sections have data-collapsible attribute
        const $existingBanner = $('.cko-existing-config-banner');
        const $collapsibleSections = $('.cko-settings-card[data-collapsible="true"]');
        
        // Setup toggle functionality for collapsible sections
        $collapsibleSections.each(function() {
            const $section = $(this);
            const $content = $section.find('.cko-collapsible-content');
            const $toggleButton = $section.find('.cko-toggle-section');
            
            // Only setup if content exists and toggle button exists
            if ($content.length && $toggleButton.length) {
                // Toggle functionality
                $toggleButton.on('click', function(e) {
                    e.preventDefault();
                    const $toggleText = $(this).find('.toggle-text');
                    const $toggleIcon = $(this).find('.toggle-icon');
                    
                    if ($content.is(':visible')) {
                        $content.slideUp(200);
                        $toggleText.text('Show');
                        $toggleIcon.text('▼');
                    } else {
                        $content.slideDown(200);
                        $toggleText.text('Hide');
                        $toggleIcon.text('▲');
                    }
                });
            }
        });
    },

    /**
     * Add security warnings for private key field
     */
    addSecurityWarnings: function() {
        // Find the private key field
        const $keyField = $('input[name="woocommerce_wc_checkout_com_apple_pay[ckocom_apple_key]"]');
        
        if ($keyField.length) {
            // Add security warning after the field
            const $fieldRow = $keyField.closest('tr');
            const $description = $fieldRow.find('.description');
            
            if ($description.length) {
                // Add security warning after description
                const $warning = $('<div class="cko-security-warning notice notice-warning inline" style="margin: 12px 0; padding: 12px 16px; border-left: 4px solid #f0b849; background: #fff8e5;">' +
                                  '<p style="margin: 0; font-size: 13px; color: #1d2327;">' +
                                  '<strong>⚠️ Security Warning:</strong> ' +
                                  'The private key (.key) file must be stored outside of your website\'s public access folder (web root). ' +
                                  'Never place it in a publicly accessible directory.</p>' +
                                  '</div>');
                
                $description.after($warning);
            }
        }
    }
};

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Only initialize on Apple Pay settings page
        if ($('.woocommerce-settings-form').length && 
            window.location.href.indexOf('wc_checkout_com_apple_pay') !== -1) {
            ApplePaySettingsUX.init();
        }
    });

    // Add pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    `;
    document.head.appendChild(style);

})(jQuery);

