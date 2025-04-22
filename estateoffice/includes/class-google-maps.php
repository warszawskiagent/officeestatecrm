<?php
/**
 * Klasa odpowiedzialna za integrację z Google Maps
 *
 * @package EstateOffice
 * @subpackage Includes
 * @since 0.5.5
 */

namespace EstateOffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GoogleMaps {
    /**
     * Klucz API Google Maps
     *
     * @var string
     */
    private $api_key;

    /**
     * Wersja skryptów Google Maps
     *
     * @var string
     */
    private $version = '0.5.5';

    /**
     * Flaga określająca czy skrypty zostały już zarejestrowane
     *
     * @var boolean
     */
    private $scripts_registered = false;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->api_key = get_option('eo_google_maps_api_key');
        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków WordPress
     *
     * @return void
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));
        add_action('wp_ajax_eo_geocode_address', array($this, 'handle_geocoding'));
        add_action('wp_ajax_eo_save_map_location', array($this, 'handle_save_location'));
    }

    /**
     * Rejestracja skryptów dla frontendu
     *
     * @return void
     */
    public function register_frontend_scripts() {
        if (!$this->should_load_maps()) {
            return;
        }

        $this->register_common_scripts();
        
        wp_enqueue_script(
            'eo-maps-frontend',
            ESTATEOFFICE_PLUGIN_URL . 'frontend/js/frontend-maps.js',
            array('eo-google-maps'),
            $this->version,
            true
        );

        wp_localize_script('eo-maps-frontend', 'eoMapsData', $this->get_maps_localize_data());
    }

    /**
     * Rejestracja skryptów dla panelu admina
     *
     * @return void
     */
    public function register_admin_scripts() {
        $screen = get_current_screen();
        if (!$screen || !$this->is_estate_office_admin_page($screen)) {
            return;
        }

        $this->register_common_scripts();

        wp_enqueue_script(
            'eo-maps-admin',
            ESTATEOFFICE_PLUGIN_URL . 'admin/js/admin-maps.js',
            array('eo-google-maps'),
            $this->version,
            true
        );

        wp_localize_script('eo-maps-admin', 'eoMapsData', $this->get_maps_localize_data());
    }

    /**
     * Rejestracja wspólnych skryptów dla Google Maps
     *
     * @return void
     */
    private function register_common_scripts() {
        if ($this->scripts_registered || !$this->api_key) {
            return;
        }

        wp_register_script(
            'eo-google-maps',
            "https://maps.googleapis.com/maps/api/js?key={$this->api_key}&libraries=places,drawing&callback=eoInitMaps",
            array(),
            null,
            true
        );

        $this->scripts_registered = true;
    }

    /**
     * Sprawdza czy aktualna strona wymaga załadowania map
     *
     * @return boolean
     */
    private function should_load_maps() {
        return is_singular('eo_property') || 
               is_post_type_archive('eo_property') ||
               is_tax('eo_property_location');
    }

    /**
     * Sprawdza czy jesteśmy na stronie admina EstateOffice
     *
     * @param \WP_Screen $screen
     * @return boolean
     */
    private function is_estate_office_admin_page($screen) {
        return strpos($screen->id, 'eo_') !== false;
    }

    /**
     * Przygotowuje dane do lokalizacji skryptów
     *
     * @return array
     */
    private function get_maps_localize_data() {
        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eo_maps_nonce'),
            'defaultLat' => get_option('eo_default_map_lat', '52.2297'),
            'defaultLng' => get_option('eo_default_map_lng', '21.0122'),
            'defaultZoom' => get_option('eo_default_map_zoom', '12'),
            'markers' => $this->get_property_markers(),
            'translations' => array(
                'geocodingError' => __('Błąd podczas geokodowania adresu', 'estateoffice'),
                'locationSaved' => __('Lokalizacja została zapisana', 'estateoffice'),
                'locationError' => __('Błąd podczas zapisywania lokalizacji', 'estateoffice')
            )
        );
    }

    /**
     * Pobiera markery dla wszystkich nieruchomości
     *
     * @return array
     */
    private function get_property_markers() {
        $markers = array();
        
        $properties = get_posts(array(
            'post_type' => 'eo_property',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_eo_property_lat',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_eo_property_lng',
                    'compare' => 'EXISTS'
                )
            )
        ));

        foreach ($properties as $property) {
            $lat = get_post_meta($property->ID, '_eo_property_lat', true);
            $lng = get_post_meta($property->ID, '_eo_property_lng', true);
            
            if ($lat && $lng) {
                $markers[] = array(
                    'id' => $property->ID,
                    'lat' => $lat,
                    'lng' => $lng,
                    'title' => get_the_title($property),
                    'link' => get_permalink($property),
                    'price' => get_post_meta($property->ID, '_eo_property_price', true),
                    'thumbnail' => get_the_post_thumbnail_url($property, 'thumbnail')
                );
            }
        }

        return $markers;
    }

    /**
     * Obsługa geokodowania adresu przez AJAX
     *
     * @return void
     */
    public function handle_geocoding() {
        check_ajax_referer('eo_maps_nonce', 'nonce');
        
        if (!current_user_can('edit_eo_properties')) {
            wp_send_json_error(__('Brak uprawnień', 'estateoffice'));
        }

        $address = sanitize_text_field($_POST['address']);
        if (empty($address)) {
            wp_send_json_error(__('Adres jest wymagany', 'estateoffice'));
        }

        $cached_result = get_transient('eo_geocode_' . md5($address));
        if ($cached_result) {
            wp_send_json_success($cached_result);
        }

        $result = $this->geocode_address($address);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        set_transient('eo_geocode_' . md5($address), $result, WEEK_IN_SECONDS);
        wp_send_json_success($result);
    }

    /**
     * Geokodowanie adresu
     *
     * @param string $address
     * @return array|\WP_Error
     */
    private function geocode_address($address) {
        $url = add_query_arg(
            array(
                'address' => urlencode($address),
                'key' => $this->api_key
            ),
            'https://maps.googleapis.com/maps/api/geocode/json'
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] !== 'OK') {
            return new \WP_Error(
                'geocoding_error',
                sprintf(
                    __('Błąd geokodowania: %s', 'estateoffice'),
                    $data['status']
                )
            );
        }

        return array(
            'lat' => $data['results'][0]['geometry']['location']['lat'],
            'lng' => $data['results'][0]['geometry']['location']['lng'],
            'formatted_address' => $data['results'][0]['formatted_address']
        );
    }

    /**
     * Obsługa zapisywania lokalizacji przez AJAX
     *
     * @return void
     */
    public function handle_save_location() {
        check_ajax_referer('eo_maps_nonce', 'nonce');
        
        if (!current_user_can('edit_eo_properties')) {
            wp_send_json_error(__('Brak uprawnień', 'estateoffice'));
        }

        $property_id = absint($_POST['property_id']);
        $lat = (float) $_POST['lat'];
        $lng = (float) $_POST['lng'];

        if (!$property_id || !$lat || !$lng) {
            wp_send_json_error(__('Nieprawidłowe dane', 'estateoffice'));
        }

        update_post_meta($property_id, '_eo_property_lat', $lat);
        update_post_meta($property_id, '_eo_property_lng', $lng);

        wp_send_json_success(__('Lokalizacja została zapisana', 'estateoffice'));
    }

    /**
     * Sprawdza czy klucz API jest poprawny
     *
     * @return boolean
     */
    public function is_api_key_valid() {
        if (!$this->api_key) {
            return false;
        }

        $cached_result = get_transient('eo_maps_api_key_valid');
        if ($cached_result !== false) {
            return $cached_result;
        }

        $test_url = add_query_arg(
            array(
                'key' => $this->api_key
            ),
            'https://maps.googleapis.com/maps/api/staticmap'
        );

        $response = wp_remote_get($test_url);
        $is_valid = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;

        set_transient('eo_maps_api_key_valid', $is_valid, DAY_IN_SECONDS);
        return $is_valid;
    }
}