/**
 * Główny plik JavaScript dla panelu administracyjnego EstateOffice
 * 
 * @package EstateOffice
 * @since 0.5.5
 */

(function($) {
    'use strict';

    // Główny obiekt aplikacji
    const EstateOffice = {
        // Inicjalizacja wszystkich funkcji
        init: function() {
            this.initDashboard();
            this.initPropertyForm();
            this.initContractForm();
            this.initClientSearch();
            this.initDatepickers();
            this.initMediaUploader();
            this.initGoogleMaps();
            this.setupAjaxDefaults();
            this.bindEvents();
        },

        // Ustawienia domyślne dla wszystkich zapytań AJAX
        setupAjaxDefaults: function() {
            $.ajaxSetup({
                headers: {
                    'X-WP-Nonce': eoAdmin.nonce
                },
                error: function(xhr, status, error) {
                    EstateOffice.showNotification('error', 'Wystąpił błąd podczas operacji: ' + error);
                }
            });
        },

        // Inicjalizacja dashboardu
        initDashboard: function() {
            if (!$('#eo-dashboard').length) return;

            // Pobieranie statystyk
            this.loadDashboardStats();
            
            // Odświeżanie co 5 minut
            setInterval(this.loadDashboardStats, 300000);
        },

        // Ładowanie statystyk dashboardu
        loadDashboardStats: function() {
            $.ajax({
                url: eoAdmin.ajaxUrl,
                data: {
                    action: 'eo_get_dashboard_stats',
                    nonce: eoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EstateOffice.updateDashboardStats(response.data);
                    }
                }
            });
        },

        // Aktualizacja statystyk na dashboardzie
        updateDashboardStats: function(data) {
            $('#eo-active-sale-offers').text(data.activeSaleOffers);
            $('#eo-active-rent-offers').text(data.activeRentOffers);
            $('#eo-active-searches').text(data.activeSearches);
            
            // Aktualizacja listy najaktywniejszych agentów
            const agentsList = $('#eo-top-agents');
            agentsList.empty();
            
            data.topAgents.forEach(function(agent) {
                agentsList.append(`
                    <li class="eo-agent-item">
                        <img src="${agent.photo}" alt="${agent.name}" class="eo-agent-photo">
                        <span class="eo-agent-name">${agent.name}</span>
                        <span class="eo-agent-clients">${agent.clientCount} klientów</span>
                    </li>
                `);
            });
        },

        // Inicjalizacja formularza nieruchomości
        initPropertyForm: function() {
            if (!$('#eo-property-form').length) return;

            // Inicjalizacja kalkulatora ceny za m²
            $('#eo-property-price, #eo-property-area').on('input', function() {
                const price = parseFloat($('#eo-property-price').val()) || 0;
                const area = parseFloat($('#eo-property-area').val()) || 0;
                
                if (area > 0) {
                    const pricePerMeter = (price / area).toFixed(2);
                    $('#eo-price-per-meter').val(pricePerMeter);
                }
            });

            // Obsługa zależnych pól formularza
            this.handleDependentFields();
        },

        // Obsługa pól zależnych w formularzu
        handleDependentFields: function() {
            // Pola zależne od rodzaju nieruchomości
            $('#eo-property-type').on('change', function() {
                const type = $(this).val();
                
                $('.eo-field-group').hide();
                $(`.eo-field-group.eo-type-${type}`).show();
                
                // Specjalna logika dla różnych typów
                if (type === 'house') {
                    $('#eo-house-type-group').show();
                }
            }).trigger('change');

            // Obsługa checkboxa "Adres korespondencyjny taki sam"
            $('#eo-same-address').on('change', function() {
                $('#eo-correspondence-address').toggle(!this.checked);
            }).trigger('change');
        },

        // Inicjalizacja formularza umowy
        initContractForm: function() {
            if (!$('#eo-contract-form').length) return;

            // Obsługa etapów umowy
            this.handleContractStages();
            
            // Inicjalizacja walidacji
            this.initContractValidation();
        },

        // Obsługa etapów umowy
        handleContractStages: function() {
            $('.eo-contract-stage').on('click', function() {
                const stageId = $(this).data('stage-id');
                const contractId = $('#eo-contract-id').val();
                
                EstateOffice.updateContractStage(contractId, stageId);
            });
        },

        // Aktualizacja etapu umowy
        updateContractStage: function(contractId, stageId) {
            $.ajax({
                url: eoAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'eo_update_contract_stage',
                    contract_id: contractId,
                    stage_id: stageId,
                    nonce: eoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EstateOffice.showNotification('success', 'Etap umowy został zaktualizowany');
                        EstateOffice.updateStageVisuals(stageId);
                    }
                }
            });
        },

        // Inicjalizacja wyszukiwania klientów
        initClientSearch: function() {
            const searchInput = $('#eo-client-search');
            if (!searchInput.length) return;

            let searchTimeout;

            searchInput.on('input', function() {
                const query = $(this).val();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 3) return;
                
                searchTimeout = setTimeout(function() {
                    EstateOffice.performClientSearch(query);
                }, 500);
            });
        },

        // Wykonanie wyszukiwania klientów
        performClientSearch: function(query) {
            $.ajax({
                url: eoAdmin.ajaxUrl,
                data: {
                    action: 'eo_search_clients',
                    query: query,
                    nonce: eoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EstateOffice.displaySearchResults(response.data);
                    }
                }
            });
        },

        // Wyświetlenie wyników wyszukiwania
        displaySearchResults: function(results) {
            const resultsContainer = $('#eo-search-results');
            resultsContainer.empty();

            if (results.length === 0) {
                resultsContainer.append('<p class="eo-no-results">Brak wyników</p>');
                return;
            }

            const resultsList = $('<ul class="eo-results-list"></ul>');
            
            results.forEach(function(client) {
                resultsList.append(`
                    <li class="eo-result-item" data-client-id="${client.id}">
                        <strong>${client.name}</strong>
                        <span>${client.email}</span>
                        <span>${client.phone}</span>
                    </li>
                `);
            });

            resultsContainer.append(resultsList);
        },

        // Inicjalizacja datepickerów
        initDatepickers: function() {
            $('.eo-datepicker').each(function() {
                $(this).datepicker({
                    dateFormat: 'yy-mm-dd',
                    firstDay: 1,
                    showOtherMonths: true,
                    selectOtherMonths: true,
                    changeMonth: true,
                    changeYear: true
                });
            });
        },

        // Inicjalizacja uploadera mediów
        initMediaUploader: function() {
            $('.eo-media-upload').each(function() {
                const button = $(this);
                const previewContainer = button.siblings('.eo-media-preview');
                const hiddenInput = button.siblings('input[type="hidden"]');

                button.on('click', function(e) {
                    e.preventDefault();

                    const uploader = wp.media({
                        title: 'Wybierz lub prześlij plik',
                        button: {
                            text: 'Użyj tego pliku'
                        },
                        multiple: false
                    });

                    uploader.on('select', function() {
                        const attachment = uploader.state().get('selection').first().toJSON();
                        
                        if (attachment.type === 'image') {
                            previewContainer.html(`<img src="${attachment.url}" alt="Preview">`);
                        } else {
                            previewContainer.html(`<span>${attachment.filename}</span>`);
                        }
                        
                        hiddenInput.val(attachment.id);
                    });

                    uploader.open();
                });
            });
        },

        // Inicjalizacja Google Maps
        initGoogleMaps: function() {
            const mapContainer = $('#eo-property-map');
            if (!mapContainer.length) return;

            const map = new google.maps.Map(mapContainer[0], {
                zoom: 12,
                center: { lat: 52.229676, lng: 21.012229 } // Warszawa
            });

            // Inicjalizacja markera i wyszukiwarki
            this.initMapMarker(map);
            this.initMapSearch(map);
        },

        // Inicjalizacja markera na mapie
        initMapMarker: function(map) {
            let marker = null;
            
            map.addListener('click', function(e) {
                const latLng = e.latLng;
                
                if (marker) {
                    marker.setPosition(latLng);
                } else {
                    marker = new google.maps.Marker({
                        position: latLng,
                        map: map,
                        draggable: true
                    });
                }
                
                EstateOffice.updateLocationFields(latLng);
            });
        },

        // Inicjalizacja wyszukiwarki na mapie
        initMapSearch: function(map) {
            const input = $('#eo-location-search')[0];
            const searchBox = new google.maps.places.SearchBox(input);
            
            map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);
            
            searchBox.addListener('places_changed', function() {
                const places = searchBox.getPlaces();
                
                if (places.length === 0) return;
                
                const bounds = new google.maps.LatLngBounds();
                
                places.forEach(function(place) {
                    if (!place.geometry) return;
                    
                    if (place.geometry.viewport) {
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }
                });
                
                map.fitBounds(bounds);
            });
        },

        // Aktualizacja pól lokalizacji
        updateLocationFields: function(latLng) {
            $('#eo-property-lat').val(latLng.lat());
            $('#eo-property-lng').val(latLng.lng());
        },

        // Wyświetlanie powiadomień
        showNotification: function(type, message) {
            const notification = $(`
                <div class="eo-notification eo-notification-${type}">
                    <span class="eo-notification-message">${message}</span>
                    <button class="eo-notification-close">&times;</button>
                </div>
            `);

            $('.eo-notifications-container').append(notification);

            setTimeout(function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            notification.find('.eo-notification-close').on('click', function() {
                notification.remove();
            });
        },

        // Bindowanie wszystkich zdarzeń
        bindEvents: function() {
            // Ogólne zdarzenia interfejsu
            $('.eo-tab-trigger').on('click', function(e) {
                e.preventDefault();
                const targetTab = $(this).data('tab');
                EstateOffice.switchTab(targetTab);
            });

            // Obsługa formularzy
            $('.eo-form').on('submit', function(e) {
                e.preventDefault();
                EstateOffice.handleFormSubmit($(this));
            });

            // Obsługa usuwania elementów
            $('.eo-delete-item').on('click', function(e) {
                e.preventDefault();
                if (confirm('Czy na pewno chcesz usunąć ten element?')) {
                    EstateOffice.handleItemDeletion($(this));
                }
            });
        },

        // Przełączanie zakładek
        switchTab: function(tabId) {
            $('.eo-tab-content').hide();
            $('.eo-tab-trigger').removeClass('active');
            
            $(`#${tabId}`).show();
            $(`.eo-tab-trigger[data-tab="${tabId}"]`).addClass('active');
        },

        // Obsługa wysyłania formularzy
        handleFormSubmit: function($form) {
            const formData = new FormData($form[0]);
            
            $.ajax({
                url: eoAdmin.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        EstateOffice.showNotification('success', 'Operacja zakończona pomyślnie');
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    }
                }
            });
        },

        // Obsługa usuwania elementów
        handleItemDeletion: function($trigger) {
            const itemId = $trigger.data('item-id');
            const itemType = $trigger.data('item-type');
            
            $.ajax({
                url: eoAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'eo_delete_item',
                    item_id: itemId,
                    item_type: itemType,
                    nonce: eoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $trigger.closest('.eo-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                        EstateOffice.showNotification('success', 'Element został usunięty');
                    }
                }
            });
        },

        // Walidacja formularza umowy
        initContractValidation: function() {
            $('#eo-contract-form').on('submit', function(e) {
                const requiredFields = $(this).find('[required]');
                let isValid = true;

                requiredFields.each(function() {
                    if (!$(this).val()) {
                        isValid = false;
                        $(this).addClass('eo-error');
                    } else {
                        $(this).removeClass('eo-error');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    EstateOffice.showNotification('error', 'Proszę wypełnić wszystkie wymagane pola');
                }
            });
        },

        // Aktualizacja wizualna etapów
        updateStageVisuals: function(currentStageId) {
            $('.eo-contract-stage').removeClass('active completed');
            
            $('.eo-contract-stage').each(function() {
                const stageId = $(this).data('stage-id');
                
                if (stageId < currentStageId) {
                    $(this).addClass('completed');
                } else if (stageId === currentStageId) {
                    $(this).addClass('active');
                }
            });
        }
    };

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        EstateOffice.init();
    });

})(jQuery);