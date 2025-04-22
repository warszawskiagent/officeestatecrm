/**
 * Obsługa formularza dodawania umowy
 * 
 * @package EstateOffice
 * @subpackage Frontend/JS
 * @since 0.5.5
 */

(function($) {
    'use strict';

    // Główny obiekt formularza
    const EOContractForm = {
        init: function() {
            this.form = $('#eoAddContractForm');
            this.steps = $('.eo-form-step');
            this.progressSteps = $('.eo-progress-step');
            
            this.bindEvents();
            this.initGoogleMaps();
            this.initFileUploads();
            this.initAutoCalculations();
            this.initClientSearch();
        },

        bindEvents: function() {
            // Nawigacja między etapami
            this.form.on('click', '.eo-button-next', this.nextStep.bind(this));
            this.form.on('click', '.eo-button-prev', this.prevStep.bind(this));

            // Walidacja numeru umowy
            $('#contract_number').on('blur', this.validateContractNumber.bind(this));

            // Obsługa umowy bezterminowej
            $('#indefinite_contract').on('change', this.toggleEndDate.bind(this));

            // Zmiana typu klienta
            $('#client_type').on('change', this.toggleClientFields.bind(this));

            // Obsługa adresu korespondencyjnego
            $('#same_correspondence_address').on('change', this.toggleCorrespondenceAddress.bind(this));

            // Obsługa typu nieruchomości
            $('#property_type').on('change', this.togglePropertyFields.bind(this));

            // Obsługa kształtu działki
            $('#plot_shape').on('change', this.togglePlotDimensions.bind(this));

            // Obsługa powierzchni dodatkowych
            $('[id^=has_]').on('change', this.toggleAdditionalAreas.bind(this));

            // Obsługa wysyłki formularza
            this.form.on('submit', this.handleSubmit.bind(this));
        },

        // Nawigacja między etapami
        nextStep: function(e) {
            const currentStep = $(e.target).closest('.eo-form-step');
            const nextStepId = $(e.target).data('next');

            if (this.validateStep(currentStep)) {
                this.showStep(nextStepId);
                this.updateProgress();
            }
        },

        prevStep: function(e) {
            const prevStepId = $(e.target).data('prev');
            this.showStep(prevStepId);
            this.updateProgress();
        },

        showStep: function(stepId) {
            this.steps.removeClass('active');
            $(`#${stepId}`).addClass('active');
        },

        updateProgress: function() {
            const currentStepIndex = this.steps.filter('.active').index();
            this.progressSteps.removeClass('active completed');
            
            for (let i = 0; i <= currentStepIndex; i++) {
                if (i === currentStepIndex) {
                    $(this.progressSteps[i]).addClass('active');
                } else {
                    $(this.progressSteps[i]).addClass('completed');
                }
            }
        },

        // Walidacja
        validateStep: function(step) {
            let isValid = true;
            const requiredFields = step.find('[required]');

            requiredFields.each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('eo-error');
                    $(this).siblings('.eo-validation-message')
                        .text(eoFormData.i18n.errorRequired);
                } else {
                    $(this).removeClass('eo-error');
                    $(this).siblings('.eo-validation-message').text('');
                }
            });

            return isValid;
        },

        validateContractNumber: function() {
            const contractNumber = $('#contract_number').val();
            
            if (contractNumber) {
                $.ajax({
                    url: eoFormData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eo_validate_contract_number',
                        nonce: eoFormData.nonce,
                        contract_number: contractNumber
                    },
                    success: (response) => {
                        if (!response.success) {
                            $('#contract_number').addClass('eo-error');
                            $('#contract_number')
                                .siblings('.eo-validation-message')
                                .text(response.data.message);
                        }
                    }
                });
            }
        },

        // Obsługa Google Maps
        initGoogleMaps: function() {
            if (typeof google === 'undefined') return;

            this.map = new google.maps.Map(document.getElementById('google_map'), {
                zoom: 12,
                center: { lat: 52.2297, lng: 21.0122 } // Warszawa
            });

            this.marker = new google.maps.Marker({
                map: this.map,
                draggable: true
            });

            // Obsługa wyszukiwania adresu
            const addressInputs = [
                'property_street',
                'property_number',
                'property_city'
            ];

            addressInputs.forEach(input => {
                $(`#${input}`).on('change', this.updateMapFromAddress.bind(this));
            });

            // Aktualizacja współrzędnych przy przeciągnięciu markera
            google.maps.event.addListener(this.marker, 'dragend', () => {
                const position = this.marker.getPosition();
                $('#property_lat').val(position.lat());
                $('#property_lng').val(position.lng());
            });
        },

        updateMapFromAddress: function() {
            const street = $('#property_street').val();
            const number = $('#property_number').val();
            const city = $('#property_city').val();

            if (street && number && city) {
                const address = `${street} ${number}, ${city}`;
                const geocoder = new google.maps.Geocoder();

                geocoder.geocode({ address: address }, (results, status) => {
                    if (status === 'OK') {
                        const location = results[0].geometry.location;
                        this.map.setCenter(location);
                        this.marker.setPosition(location);
                        
                        $('#property_lat').val(location.lat());
                        $('#property_lng').val(location.lng());
                    }
                });
            }
        },

        // Obsługa przesyłania plików
        initFileUploads: function() {
            // Galeria zdjęć
            $('#property_images').on('change', (e) => {
                const files = e.target.files;
                const preview = $('.eo-gallery-preview');
                preview.empty();

                Array.from(files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        preview.append(`
                            <div class="eo-gallery-item">
                                <img src="${e.target.result}" alt="">
                                <button type="button" class="eo-gallery-remove">×</button>
                            </div>
                        `);
                    };
                    reader.readAsDataURL(file);
                });
            });

            // Usuwanie zdjęć
            $('.eo-gallery-preview').on('click', '.eo-gallery-remove', function() {
                $(this).closest('.eo-gallery-item').remove();
            });
        },

        // Automatyczne obliczenia
        initAutoCalculations: function() {
            $('#property_price, #property_area').on('input', () => {
                const price = parseFloat($('#property_price').val()) || 0;
                const area = parseFloat($('#property_area').val()) || 0;
                
                if (price && area) {
                    const pricePerM2 = (price / area).toFixed(2);
                    $('#property_price_per_m2').val(pricePerM2);
                }
            });
        },

        // Wyszukiwanie klientów
        initClientSearch: function() {
            let searchTimeout;

            $('#client_search').on('input', (e) => {
                clearTimeout(searchTimeout);
                const searchTerm = e.target.value;

                if (searchTerm.length < 3) {
                    $('#client_search_results').empty().hide();
                    return;
                }

                searchTimeout = setTimeout(() => {
                    $.ajax({
                        url: eoFormData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eo_search_clients',
                            nonce: eoFormData.nonce,
                            search: searchTerm
                        },
                        success: (response) => {
                            if (response.success) {
                                this.displaySearchResults(response.data);
                            }
                        }
                    });
                }, 300);
            });
        },

        displaySearchResults: function(clients) {
            const results = $('#client_search_results');
            results.empty();

            if (clients.length === 0) {
                results.append('<div class="eo-search-no-results">Brak wyników</div>');
            } else {
                clients.forEach(client => {
                    results.append(`
                        <div class="eo-search-result" data-client-id="${client.id}">
                            <strong>${client.name}</strong>
                            <span>${client.email}</span>
                        </div>
                    `);
                });
            }

            results.show();
        },

        // Wysyłka formularza
        handleSubmit: function(e) {
            e.preventDefault();

            if (!this.validateAllSteps()) {
                return;
            }

            const formData = new FormData(this.form[0]);

            $.ajax({
                url: eoFormData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },

        validateAllSteps: function() {
            let isValid = true;
            this.steps.each((_, step) => {
                if (!this.validateStep($(step))) {
                    isValid = false;
                }
            });
            return isValid;
        }
    };

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(() => {
        EOContractForm.init();
    });

})(jQuery);