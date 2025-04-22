/**
 * EstateOffice Admin AJAX Handler
 * 
 * Obsługa wszystkich operacji AJAX w panelu administracyjnym
 * 
 * @package EstateOffice
 * @version 0.5.5
 * @author Tomasz Obarski
 * @link http://warszawskiagent.pl
 */

(function($) {
    'use strict';

    // Obiekt zawierający wszystkie funkcje AJAX
    const EstateOfficeAdmin = {
        
        /**
         * Inicjalizacja wszystkich handlerów zdarzeń
         */
        init: function() {
            this.initClientSearch();
            this.initContractStageUpdate();
            this.initAgentStatusUpdate();
            this.initPropertyFieldsUpdate();
            this.initGoogleMapsAPI();
            this.initMediaUpload();
        },

        /**
         * Wyszukiwanie klientów
         */
        initClientSearch: function() {
            const searchInput = $('#eo-client-search');
            let searchTimeout;

            searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val();

                if (searchTerm.length < 3) return;

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: eoAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'eo_search_clients',
                            nonce: eoAdmin.nonce,
                            search_term: searchTerm
                        },
                        beforeSend: function() {
                            $('#eo-client-search-results').html('<div class="eo-loading">Wyszukiwanie...</div>');
                        },
                        success: function(response) {
                            if (response.success) {
                                EstateOfficeAdmin.renderClientResults(response.data);
                            } else {
                                EstateOfficeAdmin.showError(response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            EstateOfficeAdmin.showError('Wystąpił błąd podczas wyszukiwania: ' + error);
                        }
                    });
                }, 500);
            });
        },

        /**
         * Aktualizacja etapu umowy
         */
        initContractStageUpdate: function() {
            $('.eo-contract-stage-select').on('change', function() {
                const contractId = $(this).data('contract-id');
                const newStage = $(this).val();
                const stageDate = $('#eo-stage-date-' + contractId).val();

                $.ajax({
                    url: eoAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eo_update_contract_stage',
                        nonce: eoAdmin.nonce,
                        contract_id: contractId,
                        stage: newStage,
                        date: stageDate
                    },
                    beforeSend: function() {
                        EstateOfficeAdmin.showLoading('Aktualizacja etapu umowy...');
                    },
                    success: function(response) {
                        if (response.success) {
                            EstateOfficeAdmin.showSuccess('Etap umowy został zaktualizowany');
                            EstateOfficeAdmin.updateContractHistory(contractId, response.data.history);
                        } else {
                            EstateOfficeAdmin.showError(response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        EstateOfficeAdmin.showError('Wystąpił błąd podczas aktualizacji: ' + error);
                    }
                });
            });
        },

        /**
         * Aktualizacja statusu agenta
         */
        initAgentStatusUpdate: function() {
            $('.eo-agent-status-toggle').on('click', function(e) {
                e.preventDefault();
                const agentId = $(this).data('agent-id');
                const newStatus = $(this).data('new-status');

                $.ajax({
                    url: eoAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eo_update_agent_status',
                        nonce: eoAdmin.nonce,
                        agent_id: agentId,
                        status: newStatus
                    },
                    beforeSend: function() {
                        EstateOfficeAdmin.showLoading('Aktualizacja statusu agenta...');
                    },
                    success: function(response) {
                        if (response.success) {
                            EstateOfficeAdmin.showSuccess('Status agenta został zaktualizowany');
                            EstateOfficeAdmin.updateAgentInterface(agentId, newStatus);
                        } else {
                            EstateOfficeAdmin.showError(response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        EstateOfficeAdmin.showError('Wystąpił błąd podczas aktualizacji: ' + error);
                    }
                });
            });
        },

        /**
         * Aktualizacja pól nieruchomości
         */
        initPropertyFieldsUpdate: function() {
            $('#eo-property-fields-form').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'eo_update_property_fields');
                formData.append('nonce', eoAdmin.nonce);

                $.ajax({
                    url: eoAdmin.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        EstateOfficeAdmin.showLoading('Aktualizacja pól nieruchomości...');
                    },
                    success: function(response) {
                        if (response.success) {
                            EstateOfficeAdmin.showSuccess('Pola nieruchomości zostały zaktualizowane');
                            EstateOfficeAdmin.refreshPropertyFields(response.data.fields);
                        } else {
                            EstateOfficeAdmin.showError(response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        EstateOfficeAdmin.showError('Wystąpił błąd podczas aktualizacji: ' + error);
                    }
                });
            });
        },

        /**
         * Inicjalizacja Google Maps API
         */
        initGoogleMapsAPI: function() {
            $('#eo-google-maps-api-form').on('submit', function(e) {
                e.preventDefault();
                const apiKey = $('#eo-google-maps-api-key').val();

                $.ajax({
                    url: eoAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eo_update_google_maps_api',
                        nonce: eoAdmin.nonce,
                        api_key: apiKey
                    },
                    beforeSend: function() {
                        EstateOfficeAdmin.showLoading('Aktualizacja klucza API...');
                    },
                    success: function(response) {
                        if (response.success) {
                            EstateOfficeAdmin.showSuccess('Klucz API został zaktualizowany');
                            EstateOfficeAdmin.testGoogleMapsConnection();
                        } else {
                            EstateOfficeAdmin.showError(response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        EstateOfficeAdmin.showError('Wystąpił błąd podczas aktualizacji: ' + error);
                    }
                });
            });
        },

        /**
         * Inicjalizacja uploadu mediów
         */
        initMediaUpload: function() {
            $('.eo-media-upload').on('click', function(e) {
                e.preventDefault();
                const button = $(this);
                const uploadType = button.data('upload-type');
                
                const frame = wp.media({
                    title: 'Wybierz plik',
                    multiple: uploadType === 'gallery',
                    library: {
                        type: 'image'
                    },
                    button: {
                        text: 'Użyj wybranych plików'
                    }
                });

                frame.on('select', function() {
                    const attachments = frame.state().get('selection').toJSON();
                    EstateOfficeAdmin.handleMediaUpload(attachments, uploadType, button);
                });

                frame.open();
            });
        },

        // Funkcje pomocnicze

        /**
         * Wyświetlanie wyników wyszukiwania klientów
         */
        renderClientResults: function(clients) {
            const container = $('#eo-client-search-results');
            let html = '';

            if (clients.length === 0) {
                html = '<div class="eo-no-results">Brak wyników</div>';
            } else {
                html = '<ul class="eo-client-list">';
                clients.forEach(function(client) {
                    html += `
                        <li class="eo-client-item" data-client-id="${client.id}">
                            <strong>${client.name}</strong>
                            <span>${client.email}</span>
                            <span>${client.phone}</span>
                            <button class="eo-select-client" data-client-id="${client.id}">
                                Wybierz
                            </button>
                        </li>
                    `;
                });
                html += '</ul>';
            }

            container.html(html);
        },

        /**
         * Aktualizacja historii umowy
         */
        updateContractHistory: function(contractId, history) {
            const container = $('#eo-contract-history-' + contractId);
            let html = '<ul class="eo-contract-history">';
            
            history.forEach(function(entry) {
                html += `
                    <li class="eo-history-entry">
                        <span class="eo-history-date">${entry.date}</span>
                        <span class="eo-history-stage">${entry.stage}</span>
                    </li>
                `;
            });
            
            html += '</ul>';
            container.html(html);
        },

        /**
         * Aktualizacja interfejsu agenta
         */
        updateAgentInterface: function(agentId, newStatus) {
            const statusCell = $('#eo-agent-status-' + agentId);
            const toggleButton = $('#eo-agent-toggle-' + agentId);
            
            statusCell.text(newStatus === 'active' ? 'Aktywny' : 'Nieaktywny');
            toggleButton
                .data('new-status', newStatus === 'active' ? 'inactive' : 'active')
                .text(newStatus === 'active' ? 'Dezaktywuj' : 'Aktywuj');
        },

        /**
         * Odświeżenie pól nieruchomości
         */
        refreshPropertyFields: function(fields) {
            const container = $('#eo-property-fields-container');
            let html = '';

            fields.forEach(function(field) {
                html += `
                    <div class="eo-field-row" data-field-id="${field.id}">
                        <input type="text" value="${field.name}" class="eo-field-name" />
                        <select class="eo-field-type">
                            <option value="text" ${field.type === 'text' ? 'selected' : ''}>Tekst</option>
                            <option value="number" ${field.type === 'number' ? 'selected' : ''}>Liczba</option>
                            <option value="select" ${field.type === 'select' ? 'selected' : ''}>Lista</option>
                        </select>
                        <button class="eo-remove-field">Usuń</button>
                    </div>
                `;
            });

            container.html(html);
        },

        /**
         * Obsługa uploadu mediów
         */
        handleMediaUpload: function(attachments, uploadType, button) {
            const targetInput = $('#' + button.data('target'));
            const previewContainer = $('#' + button.data('preview'));

            if (uploadType === 'gallery') {
                const ids = attachments.map(att => att.id);
                targetInput.val(ids.join(','));
                
                let previewHtml = '';
                attachments.forEach(function(att) {
                    previewHtml += `
                        <div class="eo-image-preview">
                            <img src="${att.url}" alt="${att.title}" />
                            <button class="eo-remove-image" data-image-id="${att.id}">×</button>
                        </div>
                    `;
                });
                previewContainer.html(previewHtml);
            } else {
                targetInput.val(attachments[0].id);
                previewContainer.html(`
                    <div class="eo-image-preview">
                        <img src="${attachments[0].url}" alt="${attachments[0].title}" />
                        <button class="eo-remove-image" data-image-id="${attachments[0].id}">×</button>
                    </div>
                `);
            }
        },

        /**
         * Wyświetlanie komunikatu ładowania
         */
        showLoading: function(message) {
            const notification = $('<div class="eo-notification eo-loading"></div>')
                .text(message)
                .appendTo('body');
                
            setTimeout(function() {
                notification.remove();
            }, 3000);
        },

        /**
         * Wyświetlanie komunikatu sukcesu
         */
        showSuccess: function(message) {
            const notification = $('<div class="eo-notification eo-success"></div>')
                .text(message)
                .appendTo('body');
                
            setTimeout(function() {
                notification.remove();
            }, 3000);
        },

        /**
         * Wyświetlanie komunikatu błędu
         */
        showError: function(message) {
            const notification = $('<div class="eo-notification eo-error"></div>')
                .text(message)
                .appendTo('body');
                
            setTimeout(function() {
                notification.remove();
            }, 5000);
        },

        /**
         * Test połączenia z Google Maps
         */
        testGoogleMapsConnection: function() {
            const testMap = new google.maps.Map(
                document.getElementById('eo-map-test-container'),
                {
                    center: { lat: 52.229676, lng: 21.012229 },
                    zoom: 8
                }
            );

            if (testMap) {
                EstateOfficeAdmin.showSuccess('Połączenie z Google Maps działa poprawnie');
            } else {
                EstateOfficeAdmin.showError('Problem z połączeniem Google Maps');
            }
        },

        /**
         * Walidacja danych formularza
         */
        validateForm: function(formData) {
            const errors = [];
            
            // Sprawdzanie wymaganych pól
            if (!formData.get('title')) {
                errors.push('Tytuł jest wymagany');
            }
            
            if (!formData.get('price')) {
                errors.push('Cena jest wymagana');
            }
            
            // Walidacja ceny
            const price = parseFloat(formData.get('price'));
            if (isNaN(price) || price <= 0) {
                errors.push('Cena musi być większa od 0');
            }
            
            // Walidacja powierzchni
            const area = parseFloat(formData.get('area'));
            if (isNaN(area) || area <= 0) {
                errors.push('Powierzchnia musi być większa od 0');
            }
            
            return errors;
        },

        /**
         * Obsługa znaków wodnych na zdjęciach
         */
        handleWatermark: function(imageId) {
            $.ajax({
                url: eoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_apply_watermark',
                    nonce: eoAdmin.nonce,
                    image_id: imageId
                },
                beforeSend: function() {
                    EstateOfficeAdmin.showLoading('Dodawanie znaku wodnego...');
                },
                success: function(response) {
                    if (response.success) {
                        EstateOfficeAdmin.showSuccess('Znak wodny został dodany');
                        // Odśwież podgląd obrazka
                        $(`img[data-image-id="${imageId}"]`).attr('src', response.data.url + '?v=' + Date.now());
                    } else {
                        EstateOfficeAdmin.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    EstateOfficeAdmin.showError('Wystąpił błąd podczas dodawania znaku wodnego: ' + error);
                }
            });
        },

        /**
         * Eksport ofert na portale zewnętrzne
         */
        initExportToPortals: function() {
            $('.eo-export-to-portal').on('click', function(e) {
                e.preventDefault();
                const propertyId = $(this).data('property-id');
                const portalId = $(this).data('portal-id');

                $.ajax({
                    url: eoAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'eo_export_to_portal',
                        nonce: eoAdmin.nonce,
                        property_id: propertyId,
                        portal_id: portalId
                    },
                    beforeSend: function() {
                        EstateOfficeAdmin.showLoading('Eksport oferty...');
                    },
                    success: function(response) {
                        if (response.success) {
                            EstateOfficeAdmin.showSuccess('Oferta została wyeksportowana');
                            EstateOfficeAdmin.updateExportStatus(propertyId, portalId, response.data.status);
                        } else {
                            EstateOfficeAdmin.showError(response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        EstateOfficeAdmin.showError('Wystąpił błąd podczas eksportu: ' + error);
                    }
                });
            });
        },

        /**
         * Aktualizacja statusu eksportu
         */
        updateExportStatus: function(propertyId, portalId, status) {
            const statusCell = $(`#eo-export-status-${propertyId}-${portalId}`);
            const statusButton = $(`#eo-export-button-${propertyId}-${portalId}`);
            
            statusCell.text(status);
            
            if (status === 'exported') {
                statusButton.text('Aktualizuj ofertę');
            } else {
                statusButton.text('Eksportuj ofertę');
            }
        }
    };

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        EstateOfficeAdmin.init();
    });

})(jQuery);