/**
 * EstateOffice Frontend Maps Module
 * 
 * Moduł odpowiedzialny za obsługę map Google na frontendzie
 * Obsługuje wyświetlanie i interakcję z mapami dla nieruchomości
 * 
 * @package EstateOffice
 * @since 0.5.5
 */

(function($) {
    'use strict';

    // Główna klasa obsługująca mapy
    class EstateOfficeMaps {
        constructor() {
            // Konfiguracja domyślna map
            this.defaultMapConfig = {
                zoom: 13,
                center: { lat: 52.2297, lng: 21.0122 }, // Warszawa jako domyślna lokalizacja
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            };

            // Przechowywanie instancji map
            this.maps = new Map();
            
            // Przechowywanie markerów
            this.markers = new Map();

            // Inicjalizacja listenera dla formularzy
            this.initFormListeners();
            
            // Inicjalizacja map na stronie
            this.initMaps();
        }

        /**
         * Inicjalizacja map na stronie
         */
        initMaps() {
            const mapContainers = document.querySelectorAll('.eo-property-map');
            
            mapContainers.forEach(container => {
                const propertyId = container.dataset.propertyId;
                const lat = parseFloat(container.dataset.lat);
                const lng = parseFloat(container.dataset.lng);

                if (lat && lng) {
                    this.createMap(container, propertyId, { lat, lng });
                }
            });
        }

        /**
         * Tworzenie nowej mapy
         * @param {HTMLElement} container - Kontener mapy
         * @param {string} propertyId - ID nieruchomości
         * @param {Object} position - Pozycja początkowa {lat, lng}
         */
        createMap(container, propertyId, position) {
            const mapConfig = {
                ...this.defaultMapConfig,
                center: position
            };

            const map = new google.maps.Map(container, mapConfig);
            this.maps.set(propertyId, map);

            // Dodanie markera
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                draggable: container.dataset.editable === 'true',
                animation: google.maps.Animation.DROP
            });

            this.markers.set(propertyId, marker);

            // Jeśli mapa jest edytowalna, dodaj obsługę przeciągania markera
            if (container.dataset.editable === 'true') {
                this.setupMarkerDragEvents(marker, propertyId);
            }

            // Dodanie InfoWindow z podstawowymi informacjami
            if (container.dataset.propertyTitle) {
                const infoWindow = new google.maps.InfoWindow({
                    content: `<div class="eo-map-info">
                        <h4>${container.dataset.propertyTitle}</h4>
                        <p>${container.dataset.propertyAddress || ''}</p>
                    </div>`
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
            }
        }

        /**
         * Konfiguracja zdarzeń dla przeciągania markera
         * @param {google.maps.Marker} marker - Marker
         * @param {string} propertyId - ID nieruchomości
         */
        setupMarkerDragEvents(marker, propertyId) {
            marker.addListener('dragend', (event) => {
                const position = marker.getPosition();
                this.updatePropertyLocation(propertyId, {
                    lat: position.lat(),
                    lng: position.lng()
                });
            });
        }

        /**
         * Aktualizacja lokalizacji nieruchomości
         * @param {string} propertyId - ID nieruchomości
         * @param {Object} position - Nowa pozycja {lat, lng}
         */
        updatePropertyLocation(propertyId, position) {
            $.ajax({
                url: eoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_update_property_location',
                    nonce: eoAdmin.nonce,
                    property_id: propertyId,
                    lat: position.lat,
                    lng: position.lng
                },
                success: (response) => {
                    if (response.success) {
                        // Aktualizacja pól formularza, jeśli istnieją
                        const latInput = document.querySelector(`#eo-property-lat-${propertyId}`);
                        const lngInput = document.querySelector(`#eo-property-lng-${propertyId}`);
                        
                        if (latInput) latInput.value = position.lat;
                        if (lngInput) lngInput.value = position.lng;
                    } else {
                        console.error('Błąd aktualizacji lokalizacji:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Błąd AJAX:', error);
                }
            });
        }

        /**
         * Inicjalizacja listenerów dla formularzy
         */
        initFormListeners() {
            // Obsługa przycisku "Zaznacz na mapie"
            $(document).on('click', '.eo-mark-on-map', (e) => {
                e.preventDefault();
                const propertyId = e.target.dataset.propertyId;
                const addressInput = document.querySelector(`#eo-property-address-${propertyId}`);

                if (addressInput && addressInput.value) {
                    this.geocodeAddress(addressInput.value, propertyId);
                }
            });

            // Obsługa wyszukiwania adresu
            this.initAddressAutocomplete();
        }

        /**
         * Inicjalizacja autouzupełniania adresu
         */
        initAddressAutocomplete() {
            const addressInputs = document.querySelectorAll('.eo-property-address');
            
            addressInputs.forEach(input => {
                const autocomplete = new google.maps.places.Autocomplete(input, {
                    types: ['address'],
                    componentRestrictions: { country: 'pl' }
                });

                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    const propertyId = input.dataset.propertyId;

                    if (place.geometry) {
                        const position = {
                            lat: place.geometry.location.lat(),
                            lng: place.geometry.location.lng()
                        };

                        // Aktualizacja mapy i markera
                        const map = this.maps.get(propertyId);
                        const marker = this.markers.get(propertyId);

                        if (map && marker) {
                            map.setCenter(position);
                            marker.setPosition(position);
                            this.updatePropertyLocation(propertyId, position);
                        }
                    }
                });
            });
        }

        /**
         * Geokodowanie adresu
         * @param {string} address - Adres do geokodowania
         * @param {string} propertyId - ID nieruchomości
         */
        geocodeAddress(address, propertyId) {
            const geocoder = new google.maps.Geocoder();
            
            geocoder.geocode({ address: address }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const position = {
                        lat: results[0].geometry.location.lat(),
                        lng: results[0].geometry.location.lng()
                    };

                    // Aktualizacja mapy i markera
                    const map = this.maps.get(propertyId);
                    const marker = this.markers.get(propertyId);

                    if (map && marker) {
                        map.setCenter(position);
                        marker.setPosition(position);
                        this.updatePropertyLocation(propertyId, position);
                    }
                } else {
                    console.error('Błąd geokodowania:', status);
                }
            });
        }
    }

    // Inicjalizacja po załadowaniu DOM
    $(document).ready(() => {
        // Sprawdzenie czy Google Maps API jest załadowane
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            window.estateOfficeMaps = new EstateOfficeMaps();
        } else {
            console.error('Google Maps API nie zostało załadowane');
        }
    });

})(jQuery);