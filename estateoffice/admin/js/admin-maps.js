/**
 * EstateOffice Admin Maps Integration
 * 
 * Handles all Google Maps functionality in the admin panel
 * Version: 0.5.5
 * Author: Tomasz Obarski
 * Website: http://warszawskiagent.pl
 */

/* global google, eoAdmin */

(function($) {
    'use strict';

    // Main map object
    let map = null;
    let marker = null;
    let geocoder = null;
    let addressInput = null;
    let latitudeInput = null;
    let longitudeInput = null;

    /**
     * Map initialization class
     */
    class EOAdminMap {
        constructor() {
            this.mapElement = document.getElementById('eo-property-map');
            this.searchInput = document.getElementById('eo-address-search');
            this.initializeElements();
            this.setupEventListeners();
        }

        /**
         * Initialize map elements and inputs
         */
        initializeElements() {
            // Initialize inputs
            addressInput = $('#eo-property-address');
            latitudeInput = $('#eo-property-latitude');
            longitudeInput = $('#eo-property-longitude');

            // Initialize geocoder
            geocoder = new google.maps.Geocoder();

            // Only proceed if we have a map element
            if (this.mapElement) {
                this.initializeMap();
                this.initializeSearchAutocomplete();
            }
        }

        /**
         * Initialize the Google Map
         */
        initializeMap() {
            const defaultLocation = {
                lat: 52.2297, // Warsaw default coordinates
                lng: 21.0122
            };

            // Get saved coordinates if they exist
            const savedLat = latitudeInput.val();
            const savedLng = longitudeInput.val();
            
            const mapOptions = {
                zoom: 13,
                center: savedLat && savedLng ? 
                    { lat: parseFloat(savedLat), lng: parseFloat(savedLng) } : 
                    defaultLocation,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            };

            // Create the map
            map = new google.maps.Map(this.mapElement, mapOptions);

            // Add marker if we have saved coordinates
            if (savedLat && savedLng) {
                this.addMarker({ lat: parseFloat(savedLat), lng: parseFloat(savedLng) });
            }

            // Add click listener to map
            map.addListener('click', (event) => {
                this.handleMapClick(event);
            });
        }

        /**
         * Initialize Google Places Autocomplete
         */
        initializeSearchAutocomplete() {
            if (this.searchInput) {
                const autocomplete = new google.maps.places.Autocomplete(this.searchInput, {
                    types: ['address'],
                    componentRestrictions: { country: 'pl' }
                });

                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    if (place.geometry) {
                        this.handlePlaceSelect(place);
                    }
                });
            }
        }

        /**
         * Handle map click event
         * @param {Object} event - Google Maps click event
         */
        handleMapClick(event) {
            const latLng = event.latLng;
            this.updateMarkerPosition(latLng);
            this.reverseGeocode(latLng);
        }

        /**
         * Handle place selection from autocomplete
         * @param {Object} place - Google Places result
         */
        handlePlaceSelect(place) {
            const location = place.geometry.location;
            
            // Update map
            map.setCenter(location);
            this.updateMarkerPosition(location);

            // Update form fields
            this.updateFormFields(place);
        }

        /**
         * Add or update marker position
         * @param {Object} location - LatLng object
         */
        updateMarkerPosition(location) {
            if (marker) {
                marker.setPosition(location);
            } else {
                this.addMarker(location);
            }

            // Update hidden inputs
            latitudeInput.val(location.lat());
            longitudeInput.val(location.lng());
        }

        /**
         * Add marker to map
         * @param {Object} location - LatLng object
         */
        addMarker(location) {
            marker = new google.maps.Marker({
                position: location,
                map: map,
                draggable: true,
                animation: google.maps.Animation.DROP
            });

            // Add drag end listener
            marker.addListener('dragend', (event) => {
                const latLng = event.latLng;
                this.updateMarkerPosition(latLng);
                this.reverseGeocode(latLng);
            });
        }

        /**
         * Reverse geocode coordinates to address
         * @param {Object} latLng - LatLng object
         */
        reverseGeocode(latLng) {
            geocoder.geocode({ location: latLng }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    this.updateFormFields(results[0]);
                } else {
                    console.error('Geocoder failed due to: ' + status);
                }
            });
        }

        /**
         * Update form fields with place data
         * @param {Object} place - Google Places/Geocoder result
         */
        updateFormFields(place) {
            let streetNumber = '';
            let route = '';
            let locality = '';
            let postalCode = '';

            // Extract address components
            place.address_components.forEach((component) => {
                const type = component.types[0];
                switch (type) {
                    case 'street_number':
                        streetNumber = component.long_name;
                        break;
                    case 'route':
                        route = component.long_name;
                        break;
                    case 'locality':
                        locality = component.long_name;
                        break;
                    case 'postal_code':
                        postalCode = component.long_name;
                        break;
                }
            });

            // Update form fields
            $('#eo-property-street').val(route);
            $('#eo-property-number').val(streetNumber);
            $('#eo-property-city').val(locality);
            $('#eo-property-postal-code').val(postalCode);
            
            // Update full address field
            addressInput.val(place.formatted_address);
        }

        /**
         * Setup additional event listeners
         */
        setupEventListeners() {
            // Handle manual address field updates
            $('.eo-address-field').on('change', () => {
                this.geocodeAddress();
            });

            // Handle "Zaznacz na mapie" button
            $('#eo-mark-on-map').on('click', (e) => {
                e.preventDefault();
                this.geocodeAddress();
            });
        }

        /**
         * Geocode address from form fields
         */
        geocodeAddress() {
            const street = $('#eo-property-street').val();
            const number = $('#eo-property-number').val();
            const city = $('#eo-property-city').val();
            const postalCode = $('#eo-property-postal-code').val();

            const address = `${street} ${number}, ${postalCode} ${city}, Poland`;

            geocoder.geocode({ address: address }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const location = results[0].geometry.location;
                    
                    // Update map
                    map.setCenter(location);
                    this.updateMarkerPosition(location);
                    
                    // Update address field with formatted address
                    addressInput.val(results[0].formatted_address);
                } else {
                    console.error('Geocode was not successful for the following reason: ' + status);
                }
            });
        }
    }

    // Initialize map when document is ready
    $(document).ready(() => {
        // Check if we're on a property edit page
        if ($('#eo-property-map').length) {
            new EOAdminMap();
        }
    });

})(jQuery);