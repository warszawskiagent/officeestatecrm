/**
 * Frontend Offers Handler
 * 
 * Obsługa ofert nieruchomości na frontendzie:
 * - Eksport ofert na WWW
 * - Filtrowanie i sortowanie ofert
 * - Zarządzanie widokiem ofert
 * - Obsługa galerii zdjęć
 * 
 * @package EstateOffice
 * @since 0.5.5
 */

(function($) {
    'use strict';

    // Główny obiekt obsługujący oferty
    const EstateOfficeOffers = {
        // Konfiguracja
        config: {
            offerSelector: '.eo-offer',
            filterFormSelector: '#eo-filter-form',
            sortSelector: '#eo-sort-select',
            gallerySelector: '.eo-offer-gallery',
            mapSelector: '#eo-offers-map',
            exportButtonSelector: '.eo-export-www',
            ajaxUrl: eoData.ajaxUrl,
            nonce: eoData.nonce
        },

        // Inicjalizacja
        init: function() {
            this.bindEvents();
            this.initializeGalleries();
            if ($(this.config.mapSelector).length) {
                this.initializeMap();
            }
        },

        // Podpięcie eventów
        bindEvents: function() {
            const self = this;

            // Obsługa filtrowania
            $(this.config.filterFormSelector).on('submit', function(e) {
                e.preventDefault();
                self.handleFiltering($(this));
            });

            // Obsługa sortowania
            $(this.config.sortSelector).on('change', function() {
                self.handleSorting($(this).val());
            });

            // Obsługa eksportu na WWW
            $(this.config.exportButtonSelector).on('click', function(e) {
                e.preventDefault();
                self.handleExport($(this).data('offer-id'));
            });

            // Lazy loading dla zdjęć
            this.initializeLazyLoading();
        },

        // Obsługa filtrowania ofert
        handleFiltering: function($form) {
            const self = this;
            const filterData = $form.serialize();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_filter_offers',
                    nonce: this.config.nonce,
                    filters: filterData
                },
                beforeSend: function() {
                    self.showLoader();
                },
                success: function(response) {
                    if (response.success) {
                        self.updateOffersList(response.data.offers);
                        self.updateMap(response.data.markers);
                        self.updateUrl(filterData);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError('Wystąpił błąd podczas filtrowania ofert.');
                },
                complete: function() {
                    self.hideLoader();
                }
            });
        },

        // Obsługa sortowania ofert
        handleSorting: function(sortType) {
            const $offers = $(this.config.offerSelector);
            const $container = $offers.parent();

            $offers.sort(function(a, b) {
                const aVal = $(a).data(sortType);
                const bVal = $(b).data(sortType);
                
                if (sortType === 'price' || sortType === 'area') {
                    return parseFloat(aVal) - parseFloat(bVal);
                }
                return aVal.localeCompare(bVal);
            });

            $offers.detach().appendTo($container);
        },

        // Obsługa eksportu oferty na WWW
        handleExport: function(offerId) {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eo_export_offer',
                    nonce: this.config.nonce,
                    offer_id: offerId
                },
                beforeSend: function() {
                    self.showLoader();
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Oferta została wyeksportowana na stronę.');
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError('Wystąpił błąd podczas eksportu oferty.');
                },
                complete: function() {
                    self.hideLoader();
                }
            });
        },

        // Inicjalizacja galerii zdjęć
        initializeGalleries: function() {
            $(this.config.gallerySelector).each(function() {
                const $gallery = $(this);
                
                $gallery.slick({
                    dots: true,
                    arrows: true,
                    infinite: true,
                    speed: 500,
                    fade: true,
                    cssEase: 'linear',
                    adaptiveHeight: true,
                    prevArrow: '<button type="button" class="slick-prev">Poprzednie</button>',
                    nextArrow: '<button type="button" class="slick-next">Następne</button>'
                });
            });
        },

        // Inicjalizacja mapy Google
        initializeMap: function() {
            if (typeof google === 'undefined') {
                console.error('Google Maps API nie jest załadowane.');
                return;
            }

            const $map = $(this.config.mapSelector);
            const mapOptions = {
                zoom: parseInt($map.data('zoom')) || 12,
                center: {
                    lat: parseFloat($map.data('lat')) || 52.229676,
                    lng: parseFloat($map.data('lng')) || 21.012229
                }
            };

            const map = new google.maps.Map($map[0], mapOptions);
            this.addMapMarkers(map);
        },

        // Dodawanie markerów na mapie
        addMapMarkers: function(map) {
            const self = this;
            const bounds = new google.maps.LatLngBounds();

            $(this.config.offerSelector).each(function() {
                const $offer = $(this);
                const position = {
                    lat: parseFloat($offer.data('lat')),
                    lng: parseFloat($offer.data('lng'))
                };

                if (position.lat && position.lng) {
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: $offer.data('title')
                    });

                    bounds.extend(position);

                    // Info window dla markera
                    const infoWindow = new google.maps.InfoWindow({
                        content: self.createInfoWindowContent($offer)
                    });

                    marker.addListener('click', function() {
                        infoWindow.open(map, marker);
                    });
                }
            });

            if (!bounds.isEmpty()) {
                map.fitBounds(bounds);
            }
        },

        // Tworzenie zawartości info window
        createInfoWindowContent: function($offer) {
            return `
                <div class="eo-map-info">
                    <h3>${$offer.data('title')}</h3>
                    <p>Cena: ${$offer.data('price')} PLN</p>
                    <p>Powierzchnia: ${$offer.data('area')} m²</p>
                    <a href="${$offer.data('url')}" class="eo-map-link">Zobacz szczegóły</a>
                </div>
            `;
        },

        // Inicjalizacja lazy loading dla zdjęć
        initializeLazyLoading: function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img.lazy').forEach(img => {
                observer.observe(img);
            });
        },

        // Aktualizacja listy ofert
        updateOffersList: function(offers) {
            const $container = $(this.config.offerSelector).parent();
            $container.html(offers);
            this.initializeGalleries();
            this.initializeLazyLoading();
        },

        // Aktualizacja mapy
        updateMap: function(markers) {
            if (typeof google !== 'undefined' && $(this.config.mapSelector).length) {
                const map = new google.maps.Map($(this.config.mapSelector)[0], {
                    zoom: 12,
                    center: markers[0]?.position || { lat: 52.229676, lng: 21.012229 }
                });

                markers.forEach(markerData => {
                    new google.maps.Marker({
                        position: markerData.position,
                        map: map,
                        title: markerData.title
                    });
                });
            }
        },

        // Aktualizacja URL z parametrami filtrowania
        updateUrl: function(filterData) {
            if (history.pushState) {
                const newUrl = window.location.protocol + "//" + window.location.host + 
                             window.location.pathname + '?' + filterData;
                window.history.pushState({path: newUrl}, '', newUrl);
            }
        },

        // Pokazywanie loadera
        showLoader: function() {
            $('<div class="eo-loader">Ładowanie...</div>').appendTo('body');
        },

        // Ukrywanie loadera
        hideLoader: function() {
            $('.eo-loader').remove();
        },

        // Pokazywanie komunikatu o sukcesie
        showSuccess: function(message) {
            const $alert = $('<div class="eo-alert eo-alert-success"></div>')
                .text(message)
                .appendTo('body');

            setTimeout(() => {
                $alert.fadeOut(() => $alert.remove());
            }, 3000);
        },

        // Pokazywanie komunikatu o błędzie
        showError: function(message) {
            const $alert = $('<div class="eo-alert eo-alert-error"></div>')
                .text(message)
                .appendTo('body');

            setTimeout(() => {
                $alert.fadeOut(() => $alert.remove());
            }, 3000);
        }
    };

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        EstateOfficeOffers.init();
    });

})(jQuery);