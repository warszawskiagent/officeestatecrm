<?php
/**
 * Klasa rdzenia wtyczki EstateOffice
 *
 * Główna klasa odpowiedzialna za inicjalizację podstawowych funkcjonalności wtyczki,
 * rejestrację typów postów, taksonomii oraz zarządzanie hookami WordPress.
 *
 * @package EstateOffice
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class Core {
    /**
     * @var object Instance klasy Database
     */
    private $database;

    /**
     * @var object Instance klasy Security
     */
    private $security;

    /**
     * @var array Konfiguracja typów nieruchomości
     */
    private $property_types = array(
        'mieszkanie' => array(
            'name' => 'mieszkanie',
            'label' => 'Mieszkanie',
            'icon' => 'apartment'
        ),
        'dom' => array(
            'name' => 'dom',
            'label' => 'Dom',
            'icon' => 'house'
        ),
        'dzialka' => array(
            'name' => 'dzialka',
            'label' => 'Działka',
            'icon' => 'land'
        ),
        'lokal' => array(
            'name' => 'lokal',
            'label' => 'Lokal usługowy',
            'icon' => 'commercial'
        )
    );

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        $this->database = new Database();
        $this->security = new Security();
    }

    /**
     * Inicjalizacja klasy i rejestracja hooków
     *
     * @return void
     */
    public function init() {
        // Inicjalizacja post types i taksonomii
        add_action('init', array($this, 'register_post_types'), 0);
        add_action('init', array($this, 'register_taxonomies'), 0);

        // Hooki dla menu
        add_action('admin_menu', array($this, 'register_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Hooki dla API i AJAX
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_ajax_eo_load_property_data', array($this, 'ajax_load_property_data'));
        add_action('wp_ajax_nopriv_eo_load_property_data', array($this, 'ajax_load_property_data'));

        // Filtry dla szablonów
        add_filter('single_template', array($this, 'load_property_template'));
        add_filter('archive_template', array($this, 'load_properties_archive_template'));

        // Inicjalizacja modułów
        $this->init_modules();
    }

    /**
     * Inicjalizacja dodatkowych modułów wtyczki
     *
     * @return void
     */
    private function init_modules() {
        // Moduł Google Maps
        if ($this->is_google_maps_enabled()) {
            $maps = new GoogleMaps();
            $maps->init();
        }

        // Moduł eksportu/importu
        $import_export = new ImportExport();
        $import_export->init();

        // Moduł powiadomień
        $notifications = new Notifications();
        $notifications->init();
    }

    /**
     * Sprawdza czy integracja z Google Maps jest włączona
     *
     * @return bool
     */
    private function is_google_maps_enabled() {
        $api_key = get_option('eo_google_maps_api_key');
        return !empty($api_key);
    }

    /**
     * Rejestracja menu administracyjnego
     *
     * @return void
     */
    public function register_admin_menus() {
        add_menu_page(
            __('EstateOffice', 'estateoffice'),
            __('EstateOffice', 'estateoffice'),
            'manage_options',
            'estateoffice',
            array($this, 'render_dashboard_page'),
            'dashicons-building',
            30
        );

        // Podmenu
        add_submenu_page(
            'estateoffice',
            __('Pulpit', 'estateoffice'),
            __('Pulpit', 'estateoffice'),
            'manage_options',
            'estateoffice',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'estateoffice',
            __('Ustawienia', 'estateoffice'),
            __('Ustawienia', 'estateoffice'),
            'manage_options',
            'estateoffice-settings',
            array($this, 'render_settings_page')
        );

        // Pozostałe podmenu...
    }

    /**
     * Ładowanie assetów dla panelu administracyjnego
     *
     * @param string $hook_suffix Aktualny hook strony admin
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'estateoffice') === false) {
            return;
        }

        wp_enqueue_style(
            'eo-admin-main',
            ESTATEOFFICE_PLUGIN_URL . 'admin/css/admin-main.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_enqueue_script(
            'eo-admin-main',
            ESTATEOFFICE_PLUGIN_URL . 'admin/js/admin-main.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_localize_script('eo-admin-main', 'eoAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eo_admin_nonce'),
            'propertyTypes' => $this->property_types,
            'restUrl' => get_rest_url(null, 'estateoffice/v1'),
            'strings' => array(
                'confirmDelete' => __('Czy na pewno chcesz usunąć?', 'estateoffice'),
                'saving' => __('Zapisywanie...', 'estateoffice'),
                'saved' => __('Zapisano pomyślnie', 'estateoffice'),
                'error' => __('Wystąpił błąd', 'estateoffice')
            )
        ));
    }
	/**
     * Rejestracja Custom Post Types
     *
     * @return void
     */
    public function register_post_types() {
        // Rejestracja CPT dla nieruchomości
        register_post_type('eo_property', array(
            'labels' => array(
                'name'                  => _x('Nieruchomości', 'Post type general name', 'estateoffice'),
                'singular_name'         => _x('Nieruchomość', 'Post type singular name', 'estateoffice'),
                'menu_name'             => _x('Nieruchomości', 'Admin Menu text', 'estateoffice'),
                'name_admin_bar'        => _x('Nieruchomość', 'Add New on Toolbar', 'estateoffice'),
                'add_new'              => _x('Dodaj nową', 'property', 'estateoffice'),
                'add_new_item'         => __('Dodaj nową nieruchomość', 'estateoffice'),
                'new_item'             => __('Nowa nieruchomość', 'estateoffice'),
                'edit_item'            => __('Edytuj nieruchomość', 'estateoffice'),
                'view_item'            => __('Zobacz nieruchomość', 'estateoffice'),
                'all_items'            => __('Wszystkie nieruchomości', 'estateoffice'),
                'search_items'         => __('Szukaj nieruchomości', 'estateoffice'),
                'not_found'            => __('Nie znaleziono nieruchomości', 'estateoffice'),
                'not_found_in_trash'   => __('Nie znaleziono nieruchomości w koszu', 'estateoffice'),
                'featured_image'       => __('Zdjęcie główne nieruchomości', 'estateoffice'),
                'set_featured_image'   => __('Ustaw zdjęcie główne', 'estateoffice'),
                'remove_featured_image' => __('Usuń zdjęcie główne', 'estateoffice'),
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => true,
            'rest_base'           => 'properties',
            'capability_type'     => array('eo_property', 'eo_properties'),
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'rewrite'            => array(
                'slug'          => 'nieruchomosci',
                'with_front'    => false,
                'hierarchical'  => false,
            ),
            'supports'           => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions'
            ),
            'has_archive'        => true,
            'can_export'         => true,
            'delete_with_user'   => false,
        ));

        // Rejestracja CPT dla umów
        register_post_type('eo_contract', array(
            'labels' => array(
                'name'                  => _x('Umowy', 'Post type general name', 'estateoffice'),
                'singular_name'         => _x('Umowa', 'Post type singular name', 'estateoffice'),
                'menu_name'             => _x('Umowy', 'Admin Menu text', 'estateoffice'),
                'add_new'              => _x('Dodaj nową', 'contract', 'estateoffice'),
                'add_new_item'         => __('Dodaj nową umowę', 'estateoffice'),
                'edit_item'            => __('Edytuj umowę', 'estateoffice'),
                'view_item'            => __('Zobacz umowę', 'estateoffice'),
                'all_items'            => __('Wszystkie umowy', 'estateoffice'),
                'search_items'         => __('Szukaj umów', 'estateoffice'),
                'not_found'            => __('Nie znaleziono umów', 'estateoffice'),
            ),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'capability_type'     => array('eo_contract', 'eo_contracts'),
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'           => array(
                'title',
                'custom-fields',
                'revisions'
            ),
            'can_export'         => true,
        ));

        // Rejestracja CPT dla wyszukiwań
        register_post_type('eo_search', array(
            'labels' => array(
                'name'                  => _x('Wyszukiwania', 'Post type general name', 'estateoffice'),
                'singular_name'         => _x('Wyszukiwanie', 'Post type singular name', 'estateoffice'),
                'menu_name'             => _x('Wyszukiwania', 'Admin Menu text', 'estateoffice'),
                'add_new'              => _x('Dodaj nowe', 'search', 'estateoffice'),
                'add_new_item'         => __('Dodaj nowe wyszukiwanie', 'estateoffice'),
                'edit_item'            => __('Edytuj wyszukiwanie', 'estateoffice'),
                'view_item'            => __('Zobacz wyszukiwanie', 'estateoffice'),
                'all_items'            => __('Wszystkie wyszukiwania', 'estateoffice'),
            ),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'capability_type'     => array('eo_search', 'eo_searches'),
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'           => array(
                'title',
                'custom-fields'
            ),
            'can_export'         => true,
        ));
    }

    /**
     * Rejestracja taksonomii
     *
     * @return void
     */
    public function register_taxonomies() {
        // Typ transakcji
        register_taxonomy('eo_transaction_type', array('eo_property'), array(
            'labels' => array(
                'name'              => _x('Typy transakcji', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Typ transakcji', 'taxonomy singular name', 'estateoffice'),
                'search_items'      => __('Szukaj typów transakcji', 'estateoffice'),
                'all_items'         => __('Wszystkie typy transakcji', 'estateoffice'),
                'edit_item'         => __('Edytuj typ transakcji', 'estateoffice'),
                'update_item'       => __('Aktualizuj typ transakcji', 'estateoffice'),
                'add_new_item'      => __('Dodaj nowy typ transakcji', 'estateoffice'),
                'new_item_name'     => __('Nowy typ transakcji', 'estateoffice'),
                'menu_name'         => __('Typy transakcji', 'estateoffice'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'typ-transakcji'),
            'show_in_rest'      => true,
        ));

        // Rodzaj nieruchomości
        register_taxonomy('eo_property_type', array('eo_property'), array(
            'labels' => array(
                'name'              => _x('Rodzaje nieruchomości', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Rodzaj nieruchomości', 'taxonomy singular name', 'estateoffice'),
                'search_items'      => __('Szukaj rodzajów nieruchomości', 'estateoffice'),
                'all_items'         => __('Wszystkie rodzaje nieruchomości', 'estateoffice'),
                'edit_item'         => __('Edytuj rodzaj nieruchomości', 'estateoffice'),
                'update_item'       => __('Aktualizuj rodzaj nieruchomości', 'estateoffice'),
                'add_new_item'      => __('Dodaj nowy rodzaj nieruchomości', 'estateoffice'),
                'new_item_name'     => __('Nowy rodzaj nieruchomości', 'estateoffice'),
                'menu_name'         => __('Rodzaje nieruchomości', 'estateoffice'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'rodzaj'),
            'show_in_rest'      => true,
        ));

        // Lokalizacja (miasto)
        register_taxonomy('eo_city', array('eo_property'), array(
            'labels' => array(
                'name'              => _x('Miasta', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Miasto', 'taxonomy singular name', 'estateoffice'),
                'search_items'      => __('Szukaj miast', 'estateoffice'),
                'all_items'         => __('Wszystkie miasta', 'estateoffice'),
                'edit_item'         => __('Edytuj miasto', 'estateoffice'),
                'update_item'       => __('Aktualizuj miasto', 'estateoffice'),
                'add_new_item'      => __('Dodaj nowe miasto', 'estateoffice'),
                'new_item_name'     => __('Nowe miasto', 'estateoffice'),
                'menu_name'         => __('Miasta', 'estateoffice'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'miasto'),
            'show_in_rest'      => true,
        ));

        // Dzielnica
        register_taxonomy('eo_district', array('eo_property'), array(
            'labels' => array(
                'name'              => _x('Dzielnice', 'taxonomy general name', 'estateoffice'),
                'singular_name'     => _x('Dzielnica', 'taxonomy singular name', 'estateoffice'),
                'search_items'      => __('Szukaj dzielnic', 'estateoffice'),
                'all_items'         => __('Wszystkie dzielnice', 'estateoffice'),
                'edit_item'         => __('Edytuj dzielnicę', 'estateoffice'),
                'update_item'       => __('Aktualizuj dzielnicę', 'estateoffice'),
                'add_new_item'      => __('Dodaj nową dzielnicę', 'estateoffice'),
                'new_item_name'     => __('Nowa dzielnica', 'estateoffice'),
                'menu_name'         => __('Dzielnice', 'estateoffice'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'dzielnica'),
            'show_in_rest'      => true,
        ));
    }
	/**
     * Ładowanie szablonu dla pojedynczej nieruchomości
     *
     * @param string $template Ścieżka do aktualnego szablonu
     * @return string Ścieżka do szablonu
     */
    public function load_property_template($template) {
        if (is_singular('eo_property')) {
            $custom_template = ESTATEOFFICE_PLUGIN_DIR . 'templates/single-estateoffer.php';
            return file_exists($custom_template) ? $custom_template : $template;
        }
        return $template;
    }

    /**
     * Ładowanie szablonu dla archiwum nieruchomości
     *
     * @param string $template Ścieżka do aktualnego szablonu
     * @return string Ścieżka do szablonu
     */
    public function load_properties_archive_template($template) {
        if (is_post_type_archive('eo_property')) {
            $custom_template = ESTATEOFFICE_PLUGIN_DIR . 'templates/archive-estateoffer.php';
            return file_exists($custom_template) ? $custom_template : $template;
        }
        return $template;
    }

    /**
     * Renderowanie strony pulpitu
     *
     * @return void
     */
    public function render_dashboard_page() {
        // Sprawdzenie uprawnień
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
        }

        // Pobranie danych dla dashboardu
        $dashboard_data = $this->get_dashboard_data();

        // Renderowanie widoku
        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Renderowanie strony ustawień
     *
     * @return void
     */
    public function render_settings_page() {
        // Sprawdzenie uprawnień
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
        }

        // Obsługa zapisywania ustawień
        if (isset($_POST['eo_save_settings']) && check_admin_referer('eo_settings_nonce')) {
            $this->save_settings($_POST);
            add_settings_error(
                'eo_messages',
                'eo_message',
                __('Ustawienia zostały zapisane.', 'estateoffice'),
                'updated'
            );
        }

        // Renderowanie widoku
        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    /**
     * Pobranie danych dla pulpitu
     *
     * @return array Dane dla pulpitu
     */
    private function get_dashboard_data() {
        return array(
            'active_agents' => $this->get_most_active_agents(),
            'sale_offers' => $this->count_active_offers('sprzedaz'),
            'rent_offers' => $this->count_active_offers('wynajem'),
            'active_searches' => $this->count_active_searches()
        );
    }

    /**
     * Pobranie najbardziej aktywnych agentów
     *
     * @param int $limit Limit agentów do pobrania
     * @return array Lista agentów
     */
    private function get_most_active_agents($limit = 3) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT a.*, 
                COUNT(DISTINCT c.id) as clients_count,
                COUNT(DISTINCT p.id) as properties_count
            FROM {$wpdb->prefix}eo_agents a
            LEFT JOIN {$wpdb->prefix}eo_clients c ON c.agent_id = a.id
            LEFT JOIN {$wpdb->prefix}eo_properties p ON p.agent_id = a.id
            WHERE a.status = 'active'
            GROUP BY a.id
            ORDER BY clients_count DESC
            LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query);
    }

    /**
     * Liczenie aktywnych ofert danego typu
     *
     * @param string $type Typ transakcji (sprzedaz/wynajem)
     * @return int Liczba ofert
     */
    private function count_active_offers($type) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->prefix}eo_properties
            WHERE transaction_type = %s
            AND property_status = 'available'",
            $type
        ));
    }

    /**
     * Liczenie aktywnych wyszukiwań
     *
     * @return int Liczba wyszukiwań
     */
    private function count_active_searches() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->prefix}eo_searches
            WHERE status = 'active'"
        );
    }

    /**
     * Zapisywanie ustawień
     *
     * @param array $data Dane z formularza
     * @return void
     */
    private function save_settings($data) {
        // Zapisz klucz API Google Maps
        if (isset($data['eo_google_maps_api_key'])) {
            update_option(
                'eo_google_maps_api_key',
                sanitize_text_field($data['eo_google_maps_api_key'])
            );
        }

        // Zapisz znak wodny
        if (isset($data['eo_watermark_id'])) {
            update_option(
                'eo_watermark_id',
                absint($data['eo_watermark_id'])
            );
        }

        // Zapisz logo
        if (isset($data['eo_logo_id'])) {
            update_option(
                'eo_logo_id',
                absint($data['eo_logo_id'])
            );
        }

        // Zapisz pola niestandardowe
        if (isset($data['eo_custom_fields'])) {
            update_option(
                'eo_custom_fields',
                $this->sanitize_custom_fields($data['eo_custom_fields'])
            );
        }

        do_action('estateoffice_after_save_settings', $data);
    }

    /**
     * Sanityzacja pól niestandardowych
     *
     * @param array $fields Pola do sanityzacji
     * @return array Sanityzowane pola
     */
    private function sanitize_custom_fields($fields) {
        if (!is_array($fields)) {
            return array();
        }

        return array_map(function($field) {
            return array(
                'name' => sanitize_key($field['name']),
                'label' => sanitize_text_field($field['label']),
                'type' => sanitize_key($field['type']),
                'required' => isset($field['required']) ? (bool)$field['required'] : false,
                'options' => isset($field['options']) ? array_map('sanitize_text_field', $field['options']) : array()
            );
        }, $fields);
    }

    /**
     * Obsługa AJAX dla ładowania danych nieruchomości
     *
     * @return void
     */
    public function ajax_load_property_data() {
        check_ajax_referer('eo_ajax_nonce', 'nonce');

        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        if (!$property_id) {
            wp_send_json_error(__('Nieprawidłowe ID nieruchomości', 'estateoffice'));
        }

        $property_data = $this->get_property_data($property_id);
        if (!$property_data) {
            wp_send_json_error(__('Nie znaleziono nieruchomości', 'estateoffice'));
        }

        wp_send_json_success($property_data);
    }

    /**
     * Pobranie pełnych danych nieruchomości
     *
     * @param int $property_id ID nieruchomości
     * @return array|false Dane nieruchomości lub false
     */
    private function get_property_data($property_id) {
        global $wpdb;

        // Pobierz podstawowe dane
        $property = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eo_properties WHERE id = %d",
            $property_id
        ), ARRAY_A);

        if (!$property) {
            return false;
        }

        // Dodaj szczegóły
        $property['details'] = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eo_property_details WHERE property_id = %d",
            $property_id
        ), ARRAY_A);

        // Dodaj media
        $property['media'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eo_property_media 
            WHERE property_id = %d 
            ORDER BY sort_order ASC",
            $property_id
        ), ARRAY_A);

        // Dodaj wyposażenie
        $property['equipment'] = $wpdb->get_col($wpdb->prepare(
            "SELECT equipment_type 
            FROM {$wpdb->prefix}eo_property_equipment 
            WHERE property_id = %d",
            $property_id
        ));

        return $property;
    }
}