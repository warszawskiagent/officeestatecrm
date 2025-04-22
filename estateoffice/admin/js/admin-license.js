/**
 * EstateOffice License Management
 * 
 * @package EstateOffice
 * @since 0.5.5
 * @author Tomasz Obarski
 * @link http://warszawskiagent.pl
 */

(function($) {
    'use strict';

    // Main license handler object
    const EstateOfficeLicense = {
        /**
         * Initialize license handling
         */
        init: function() {
            this.bindEvents();
            this.checkLicenseStatus();
        },

        /**
         * Bind all necessary events
         */
        bindEvents: function() {
            $('#eo-activate-license').on('click', this.handleActivation.bind(this));
            $('#eo-deactivate-license').on('click', this.handleDeactivation.bind(this));
            $('#eo-license-key').on('input', this.validateLicenseKey);
        },

        /**
         * Validate license key format
         * @param {Event} e - Input event
         */
        validateLicenseKey: function(e) {
            const licenseKey = $(e.target).val();
            const isValid = /^[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}$/.test(licenseKey);
            
            $('#eo-activate-license').prop('disabled', !isValid);
            
            if (licenseKey && !isValid) {
                $('#eo-license-status').html(
                    '<span class="eo-error">Format klucza licencji jest nieprawidłowy</span>'
                );
            } else {
                $('#eo-license-status').empty();
            }
        },

        /**
         * Handle license activation
         * @param {Event} e - Click event
         */
        handleActivation: function(e) {
            e.preventDefault();
            const licenseKey = $('#eo-license-key').val();

            if (!licenseKey) {
                this.showError('Wprowadź klucz licencji');
                return;
            }

            this.setLoading(true);

            $.ajax({
                url: eoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_activate_license',
                    license_key: licenseKey,
                    nonce: eoAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Licencja została aktywowana pomyślnie');
                        this.updateLicenseStatus(response.data);
                    } else {
                        this.showError(response.data.message || 'Wystąpił błąd podczas aktywacji licencji');
                    }
                },
                error: () => {
                    this.showError('Błąd połączenia z serwerem');
                },
                complete: () => {
                    this.setLoading(false);
                }
            });
        },

        /**
         * Handle license deactivation
         * @param {Event} e - Click event
         */
        handleDeactivation: function(e) {
            e.preventDefault();

            if (!confirm('Czy na pewno chcesz dezaktywować licencję?')) {
                return;
            }

            this.setLoading(true);

            $.ajax({
                url: eoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_deactivate_license',
                    nonce: eoAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Licencja została dezaktywowana');
                        this.resetLicenseForm();
                    } else {
                        this.showError(response.data.message || 'Wystąpił błąd podczas dezaktywacji licencji');
                    }
                },
                error: () => {
                    this.showError('Błąd połączenia z serwerem');
                },
                complete: () => {
                    this.setLoading(false);
                }
            });
        },

        /**
         * Check current license status
         */
        checkLicenseStatus: function() {
            $.ajax({
                url: eoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_check_license',
                    nonce: eoAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateLicenseStatus(response.data);
                    }
                }
            });
        },

        /**
         * Update license status in UI
         * @param {Object} data - License status data
         */
        updateLicenseStatus: function(data) {
            const statusElement = $('#eo-license-status');
            const activateBtn = $('#eo-activate-license');
            const deactivateBtn = $('#eo-deactivate-license');
            const licenseKeyInput = $('#eo-license-key');

            if (data.is_active) {
                statusElement.html(`
                    <span class="eo-success">
                        Licencja aktywna do: ${data.expires_at}
                        ${data.type ? `<br>Typ licencji: ${data.type}` : ''}
                    </span>
                `);
                activateBtn.hide();
                deactivateBtn.show();
                licenseKeyInput.prop('disabled', true);
            } else {
                statusElement.html('<span class="eo-warning">Licencja nieaktywna</span>');
                activateBtn.show();
                deactivateBtn.hide();
                licenseKeyInput.prop('disabled', false);
            }
        },

        /**
         * Reset license form to initial state
         */
        resetLicenseForm: function() {
            $('#eo-license-key').val('').prop('disabled', false);
            $('#eo-license-status').html('<span class="eo-warning">Licencja nieaktywna</span>');
            $('#eo-activate-license').show();
            $('#eo-deactivate-license').hide();
        },

        /**
         * Show error message
         * @param {string} message - Error message
         */
        showError: function(message) {
            $('#eo-license-message')
                .removeClass('eo-success-message')
                .addClass('eo-error-message')
                .text(message)
                .fadeIn();

            setTimeout(() => {
                $('#eo-license-message').fadeOut();
            }, 5000);
        },

        /**
         * Show success message
         * @param {string} message - Success message
         */
        showSuccess: function(message) {
            $('#eo-license-message')
                .removeClass('eo-error-message')
                .addClass('eo-success-message')
                .text(message)
                .fadeIn();

            setTimeout(() => {
                $('#eo-license-message').fadeOut();
            }, 5000);
        },

        /**
         * Set loading state
         * @param {boolean} isLoading - Loading state
         */
        setLoading: function(isLoading) {
            const buttons = $('#eo-activate-license, #eo-deactivate-license');
            buttons.prop('disabled', isLoading);
            
            if (isLoading) {
                buttons.append('<span class="eo-spinner"></span>');
            } else {
                $('.eo-spinner').remove();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EstateOfficeLicense.init();
    });

})(jQuery);