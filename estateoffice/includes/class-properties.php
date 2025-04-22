<?php
/**
 * Klasa zarządzająca nieruchomościami
 *
 * @package EstateOffice
 * @subpackage Includes
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit;
}

class Properties {
    /**
     * @var string Nazwa typu postu dla nieruchomości
     */
    private $post_type = 'eo_property';

    /**
     * @var array Dozwolone typy transakcji
     */
    private $transaction_types = array('SPRZEDAZ', 'WYNAJEM');

    /**
     * @var array Dozwolone rodzaje nieruchomości
     */
    private $property_types = array('MIESZKANIE', 'DOM', 'DZIALKA', 'LOKAL_HU');

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('save_post_' . $this->post_type, array($this, 'save_property_meta'), 10, 3);
        add_filter('manage_' . $this->post_type . '_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_' . $this->post_type . '_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
    }

    /**
     * Rejestracja typu postu dla nieruchomości
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Nieruchomości', 'Post type general name', 'estateoffice'),
            'singular_name'         => _x('Nieruchomość', 'Post type singular name', 'estateoffice'),
            'menu_name'            => __('Nieruchomości', 'estateoffice'),
            'add_new'              => __('Dodaj nową', 'estateoffice'),
            'add_new_item'         => __('Dodaj nową nieruchomość', 'estateoffice'),
            'edit_item'            => __('Edytuj nieruchomość', 'estateoffice'),
            'new_item'             => __('Nowa nieruchomość', 'estateoffice'),
            'view_item'            => __('Zobacz nieruchomość', 'estateoffice'),
            'search_items'         => __('Szukaj nieruchomości', 'estateoffice'),
            'not_found'            => __('Nie znaleziono nieruchomości', 'estateoffice'),
            'not_found_in_trash'   => __('Nie znaleziono nieruchomości w koszu', 'estateoffice')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'nieruchomosci'),
            'capability_type'    => array('eo_property', 'eo_properties'),
            'map_meta_cap'       => true,
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields')
        );

        register_post_type($this->post_type, $args);
    }

    /**
     * Rejestracja taksonomii dla nieruchomości
     */
    public function register_taxonomies() {
        // Typ transakcji
        register_taxonomy('eo_transaction_type', array($this->post_type), array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'              => _x('Typy transakcji', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Typ transakcji', 'taxonomy singular name', 'estateoffice'),
                'menu_name'         => __('Typy transakcji', 'estateoffice')
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'typ-transakcji')
        ));

        // Rodzaj nieruchomości
        register_taxonomy('eo_property_type', array($this->post_type), array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'              => _x('Rodzaje nieruchomości', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Rodzaj nieruchomości', 'taxonomy singular name', 'estateoffice'),
                'menu_name'         => __('Rodzaje nieruchomości', 'estateoffice')
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'rodzaj-nieruchomosci')
        ));

        // Miasto
        register_taxonomy('eo_city', array($this->post_type), array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'              => _x('Miasta', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Miasto', 'taxonomy singular name', 'estateoffice'),
                'menu_name'         => __('Miasta', 'estateoffice')
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'miasto')
        ));

        // Dzielnica
        register_taxonomy('eo_district', array($this->post_type), array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'              => _x('Dzielnice', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Dzielnica', 'taxonomy singular name', 'estateoffice'),
                'menu_name'         => __('Dzielnice', 'estateoffice')
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'dzielnica')
        ));
    }

    /**
     * Dodaje nową nieruchomość
     *
     * @param array $data Dane nieruchomości
     * @return int|WP_Error ID nowej nieruchomości lub obiekt błędu
     */
    public function add_property($data) {
        // Weryfikacja uprawnień
        if (!current_user_can('publish_eo_properties')) {
            return new \WP_Error('permission_denied', __('Nie masz uprawnień do dodawania nieruchomości', 'estateoffice'));
        }

        // Walidacja podstawowych danych
        if (empty($data['title']) || empty($data['transaction_type']) || empty($data['property_type'])) {
            return new \WP_Error('missing_data', __('Brak wymaganych danych', 'estateoffice'));
        }

        // Sanityzacja danych
        $sanitized_data = $this->sanitize_property_data($data);

        // Tworzenie nieruchomości
        $post_data = array(
            'post_title'    => $sanitized_data['title'],
            'post_content'  => $sanitized_data['description'] ?? '',
            'post_status'   => 'publish',
            'post_type'     => $this->post_type,
            'post_author'   => get_current_user_id()
        );

        // Wstawienie postu
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Dodanie taksonomii
        wp_set_object_terms($post_id, $sanitized_data['transaction_type'], 'eo_transaction_type');
        wp_set_object_terms($post_id, $sanitized_data['property_type'], 'eo_property_type');
        
        if (!empty($sanitized_data['city'])) {
            wp_set_object_terms($post_id, $sanitized_data['city'], 'eo_city');
        }
        
        if (!empty($sanitized_data['district'])) {
            wp_set_object_terms($post_id, $sanitized_data['district'], 'eo_district');
        }

        // Zapisanie meta danych
        $this->save_property_metadata($post_id, $sanitized_data);

        return $post_id;
    }

    /**
     * Sanityzacja danych nieruchomości
     *
     * @param array $data Surowe dane
     * @return array Oczyszczone dane
     */
    private function sanitize_property_data($data) {
        $sanitized = array();

        // Podstawowe dane
        $sanitized['title'] = sanitize_text_field($data['title']);
        $sanitized['description'] = wp_kses_post($data['description']);
        $sanitized['transaction_type'] = sanitize_text_field($data['transaction_type']);
        $sanitized['property_type'] = sanitize_text_field($data['property_type']);

        // Dane adresowe
        $sanitized['address'] = array(
            'street' => sanitize_text_field($data['address']['street'] ?? ''),
            'number' => sanitize_text_field($data['address']['number'] ?? ''),
            'apartment' => sanitize_text_field($data['address']['apartment'] ?? ''),
            'postal_code' => sanitize_text_field($data['address']['postal_code'] ?? ''),
            'city' => sanitize_text_field($data['address']['city'] ?? ''),
            'district' => sanitize_text_field($data['address']['district'] ?? '')
        );

        // Dane cenowe
        $sanitized['price'] = floatval($data['price']);
        $sanitized['price_per_m2'] = floatval($data['price_per_m2']);
        $sanitized['admin_fee'] = floatval($data['admin_fee']);

        // Parametry nieruchomości
        $sanitized['area'] = floatval($data['area']);
        $sanitized['rooms'] = intval($data['rooms']);
        $sanitized['floor'] = intval($data['floor']);
        $sanitized['total_floors'] = intval($data['total_floors']);
        $sanitized['year_built'] = intval($data['year_built']);

        return $sanitized;
    }

    /**
     * Zapisuje meta dane nieruchomości
     *
     * @param int $post_id ID nieruchomości
     * @param array $data Dane do zapisania
     */
    private function save_property_metadata($post_id, $data) {
        // Dane adresowe
        update_post_meta($post_id, '_eo_address', $data['address']);
        
        // Dane cenowe
        update_post_meta($post_id, '_eo_price', $data['price']);
        update_post_meta($post_id, '_eo_price_per_m2', $data['price_per_m2']);
        update_post_meta($post_id, '_eo_admin_fee', $data['admin_fee']);
        
        // Parametry nieruchomości
        update_post_meta($post_id, '_eo_area', $data['area']);
        update_post_meta($post_id, '_eo_rooms', $data['rooms']);
        update_post_meta($post_id, '_eo_floor', $data['floor']);
        update_post_meta($post_id, '_eo_total_floors', $data['total_floors']);
        update_post_meta($post_id, '_eo_year_built', $data['year_built']);

        // Znaczniki
        if (!empty($data['flags'])) {
            foreach ($data['flags'] as $flag => $value) {
                update_post_meta($post_id, '_eo_flag_' . $flag, $value);
            }
        }

        // Zapisz koordynaty dla Google Maps
        if (!empty($data['coordinates'])) {
            update_post_meta($post_id, '_eo_coordinates', $data['coordinates']);
        }
    }

    /**
     * Definiuje kolumny w widoku listy nieruchomości
     *
     * @param array $columns Istniejące kolumny
     * @return array Zmodyfikowane kolumny
     */
    public function set_custom_columns($columns) {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Tytuł', 'estateoffice'),
            'address' => __('Adres', 'estateoffice'),
            'price' => __('Cena', 'estateoffice'),
            'price_per_m2' => __('Cena za m²', 'estateoffice'),
            'area' => __('Metraż', 'estateoffice'),
            'rooms' => __('Liczba pokoi', 'estateoffice'),
            'agent' => __('Opiekun', 'estateoffice'),
            'date' => __('Data', 'estateoffice')
        );
        
        return $columns;
    }

    /**
     * Wypełnia zawartość kolumn w widoku listy nieruchomości
     *
     * @param string $column Nazwa kolumny
     * @param int $post_id ID nieruchomości
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'address':
                $address = get_post_meta($post_id, '_eo_address', true);
                if ($address) {
                    echo esc_html($address['street'] . ' ' . $address['number']);
                }
                break;

            case 'price':
                $price = get_post_meta($post_id, '_eo_price', true);
                echo esc_html(number_format($price, 2) . ' PLN');
                break;

            case 'price_per_m2':
                $price_per_m2 = get_post_meta($post_id, '_eo_price_per_m2', true);
                echo esc_html(number_format($price_per_m2, 2) . ' PLN/m²');
                break;

            case 'area':
                $area = get_post_meta($post_id, '_eo_area', true);
                echo esc_html($area . ' m²');
                break;

            case 'rooms':
                $rooms = get_post_meta($post_id, '_eo_rooms', true);
                echo esc_html($rooms);
                break;

            case 'agent':
                $agent_id = get_post_meta($post_id, '_eo_agent_id', true);
                if ($agent_id) {
                    $agent = get_userdata($agent_id);
                    if ($agent) {
                        echo esc_html($agent->display_name);
                    }
                }
                break;
        }
    }

    /**
     * Aktualizuje dane nieruchomości
     *
     * @param int $property_id ID nieruchomości
     * @param array $data Nowe dane
     * @return int|WP_Error ID zaktualizowanej nieruchomości lub obiekt błędu
     */
    public function update_property($property_id, $data) {
        // Weryfikacja uprawnień
        if (!current_user_can('edit_eo_property', $property_id)) {
            return new \WP_Error('permission_denied', __('Nie masz uprawnień do edycji tej nieruchomości', 'estateoffice'));
        }

        // Sprawdzenie czy nieruchomość istnieje
        $property = get_post($property_id);
        if (!$property || $property->post_type !== $this->post_type) {
            return new \WP_Error('invalid_property', __('Nieprawidłowa nieruchomość', 'estateoffice'));
        }

        // Sanityzacja danych
        $sanitized_data = $this->sanitize_property_data($data);

        // Aktualizacja podstawowych danych
        $post_data = array(
            'ID'            => $property_id,
            'post_title'    => $sanitized_data['title'],
            'post_content'  => $sanitized_data['description'] ?? $property->post_content
        );

        // Aktualizacja postu
        $updated = wp_update_post($post_data, true);
        if (is_wp_error($updated)) {
            return $updated;
        }

        // Aktualizacja taksonomii
        if (isset($sanitized_data['transaction_type'])) {
            wp_set_object_terms($property_id, $sanitized_data['transaction_type'], 'eo_transaction_type');
        }
        if (isset($sanitized_data['property_type'])) {
            wp_set_object_terms($property_id, $sanitized_data['property_type'], 'eo_property_type');
        }
        if (isset($sanitized_data['city'])) {
            wp_set_object_terms($property_id, $sanitized_data['city'], 'eo_city');
        }
        if (isset($sanitized_data['district'])) {
            wp_set_object_terms($property_id, $sanitized_data['district'], 'eo_district');
        }

        // Aktualizacja meta danych
        $this->save_property_metadata($property_id, $sanitized_data);

        do_action('eo_property_updated', $property_id, $sanitized_data);

        return $property_id;
    }

    /**
     * Usuwa nieruchomość
     *
     * @param int $property_id ID nieruchomości
     * @return bool|WP_Error True w przypadku sukcesu, WP_Error w przypadku błędu
     */
    public function delete_property($property_id) {
        // Weryfikacja uprawnień
        if (!current_user_can('delete_eo_property', $property_id)) {
            return new \WP_Error('permission_denied', __('Nie masz uprawnień do usunięcia tej nieruchomości', 'estateoffice'));
        }

        // Sprawdzenie czy nieruchomość istnieje
        $property = get_post($property_id);
        if (!$property || $property->post_type !== $this->post_type) {
            return new \WP_Error('invalid_property', __('Nieprawidłowa nieruchomość', 'estateoffice'));
        }

        // Usunięcie powiązanych metadanych
        $meta_keys = array(
            '_eo_address',
            '_eo_price',
            '_eo_price_per_m2',
            '_eo_admin_fee',
            '_eo_area',
            '_eo_rooms',
            '_eo_floor',
            '_eo_total_floors',
            '_eo_year_built',
            '_eo_coordinates',
            '_eo_agent_id'
        );

        foreach ($meta_keys as $key) {
            delete_post_meta($property_id, $key);
        }

        // Usunięcie znaczników
        $flags = get_post_meta($property_id);
        foreach ($flags as $key => $value) {
            if (strpos($key, '_eo_flag_') === 0) {
                delete_post_meta($property_id, $key);
            }
        }

        // Usunięcie taksonomii
        wp_delete_object_term_relationships($property_id, array(
            'eo_transaction_type',
            'eo_property_type',
            'eo_city',
            'eo_district'
        ));

        // Usunięcie nieruchomości
        $deleted = wp_delete_post($property_id, true);

        if (!$deleted) {
            return new \WP_Error('delete_failed', __('Nie udało się usunąć nieruchomości', 'estateoffice'));
        }

        do_action('eo_property_deleted', $property_id);

        return true;
    }

    /**
     * Wyszukuje nieruchomości według zadanych kryteriów
     *
     * @param array $args Kryteria wyszukiwania
     * @return array Lista nieruchomości spełniających kryteria
     */
    public function search_properties($args = array()) {
        $default_args = array(
            'post_type'      => $this->post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1
        );

        $args = wp_parse_args($args, $default_args);

        // Dodanie warunków wyszukiwania
        $meta_query = array();
        $tax_query = array();

        // Zakres cenowy
        if (!empty($args['price_min']) || !empty($args['price_max'])) {
            $price_query = array('key' => '_eo_price');
            
            if (!empty($args['price_min'])) {
                $price_query['value'] = floatval($args['price_min']);
                $price_query['compare'] = '>=';
                $price_query['type'] = 'NUMERIC';
            }
            
            if (!empty($args['price_max'])) {
                $price_query['value'] = floatval($args['price_max']);
                $price_query['compare'] = '<=';
                $price_query['type'] = 'NUMERIC';
            }

            $meta_query[] = $price_query;
        }

        // Metraż
        if (!empty($args['area_min']) || !empty($args['area_max'])) {
            $area_query = array('key' => '_eo_area');
            
            if (!empty($args['area_min'])) {
                $area_query['value'] = floatval($args['area_min']);
                $area_query['compare'] = '>=';
                $area_query['type'] = 'NUMERIC';
            }
            
            if (!empty($args['area_max'])) {
                $area_query['value'] = floatval($args['area_max']);
                $area_query['compare'] = '<=';
                $area_query['type'] = 'NUMERIC';
            }

            $meta_query[] = $area_query;
        }

        // Liczba pokoi
        if (!empty($args['rooms'])) {
            $meta_query[] = array(
                'key'     => '_eo_rooms',
                'value'   => intval($args['rooms']),
                'compare' => '=',
                'type'    => 'NUMERIC'
            );
        }

        // Typ transakcji
        if (!empty($args['transaction_type'])) {
            $tax_query[] = array(
                'taxonomy' => 'eo_transaction_type',
                'field'    => 'slug',
                'terms'    => $args['transaction_type']
            );
        }

        // Rodzaj nieruchomości
        if (!empty($args['property_type'])) {
            $tax_query[] = array(
                'taxonomy' => 'eo_property_type',
                'field'    => 'slug',
                'terms'    => $args['property_type']
            );
        }

        // Miasto
        if (!empty($args['city'])) {
            $tax_query[] = array(
                'taxonomy' => 'eo_city',
                'field'    => 'slug',
                'terms'    => $args['city']
            );
        }

        // Dzielnica
        if (!empty($args['district'])) {
            $tax_query[] = array(
                'taxonomy' => 'eo_district',
                'field'    => 'slug',
                'terms'    => $args['district']
            );
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        return get_posts($args);
    }
}