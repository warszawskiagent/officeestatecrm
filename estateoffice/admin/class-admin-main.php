<?php
/**
 * Główna klasa panelu administracyjnego wtyczki EstateOffice
 *
 * Odpowiada za inicjalizację i konfigurację panelu administracyjnego,
 * w tym menu, podmenu i podstawowych funkcjonalności.
 *
 * @package EstateOffice
 * @subpackage Admin
 * @since 0.5.5
 * @author Tomasz Obarski
 * @link http://warszawskiagent.pl
 */

namespace EstateOffice\Admin;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AdminMain {
    /**
     * Instance of AdminSettings
     *
     * @var AdminSettings
     */
    private $settings;

    /**
     * Instance of AdminAgents
     *
     * @var AdminAgents
     */
    private $agents;

    /**
     * Prefix for plugin menu items
     *
     * @var string
     */
    private $menu_prefix = 'estateoffice';

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new AdminSettings();
        $this->agents = new AdminAgents();

        // Hooks initialization
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Admin menu hooks
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // AJAX handlers
        add_action('wp_ajax_eo_get_dashboard_stats', array($this, 'handle_dashboard_stats'));
    }

    /**
     * Register admin menu and submenu items
     *
     * @return void
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __('EstateOffice', 'estateoffice'),
            __('EstateOffice', 'estateoffice'),
            'manage_options',
            $this->menu_prefix,
            array($this, 'render_dashboard'),
            'dashicons-building',
            30
        );

        // Submenu - Dashboard (alias for main menu)
        add_submenu_page(
            $this->menu_prefix,
            __('Pulpit', 'estateoffice'),
            __('Pulpit', 'estateoffice'),
            'manage_options',
            $this->menu_prefix,
            array($this, 'render_dashboard')
        );

        // Submenu - Settings
        add_submenu_page(
            $this->menu_prefix,
            __('Ustawienia', 'estateoffice'),
            __('Ustawienia', 'estateoffice'),
            'manage_options',
            $this->menu_prefix . '-settings',
            array($this->settings, 'render_settings_page')
        );

        // Submenu - License
        add_submenu_page(
            $this->menu_prefix,
            __('Licencja', 'estateoffice'),
            __('Licencja', 'estateoffice'),
            'manage_options',
            $this->menu_prefix . '-license',
            array($this, 'render_license_page')
        );

        // Submenu - About
        add_submenu_page(
            $this->menu_prefix,
            __('O wtyczce', 'estateoffice'),
            __('O wtyczce', 'estateoffice'),
            'manage_options',
            $this->menu_prefix . '-about',
            array($this, 'render_about_page')
        );

        // Agents menu
        add_menu_page(
            __('Agenci', 'estateoffice'),
            __('Agenci', 'estateoffice'),
            'manage_options',
            $this->menu_prefix . '-agents',
            array($this->agents, 'render_agents_page'),
            'dashicons-groups',
            31
        );
    }

    /**
     * Enqueue admin assets (CSS & JS)
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, $this->menu_prefix) === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'eo-admin-main',
            ESTATEOFFICE_PLUGIN_URL . 'admin/css/admin-main.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_enqueue_style(
            'eo-admin-dashboard',
            ESTATEOFFICE_PLUGIN_URL . 'admin/css/admin-dashboard.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'eo-admin-main',
            ESTATEOFFICE_PLUGIN_URL . 'admin/js/admin-main.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        // Localize script
        wp_localize_script('eo-admin-main', 'eoAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eo_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Czy na pewno chcesz usunąć ten element?', 'estateoffice'),
                'errorLoading' => __('Błąd podczas ładowania danych', 'estateoffice'),
            )
        ));
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
        }

        // Get dashboard data
        $active_agents = $this->get_most_active_agents();
        $active_sale_offers = $this->get_active_offers_count('sale');
        $active_rent_offers = $this->get_active_offers_count('rent');
        $active_searches = $this->get_active_searches_count();

        // Include dashboard template
        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Get most active agents
     *
     * @return array
     */
    private function get_most_active_agents() {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT a.*, COUNT(c.id) as client_count 
            FROM {$wpdb->prefix}eo_agents a 
            LEFT JOIN {$wpdb->prefix}eo_contracts c ON a.id = c.agent_id 
            WHERE c.status = %s 
            GROUP BY a.id 
            ORDER BY client_count DESC 
            LIMIT 3",
            'active'
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get count of active offers by type
     *
     * @param string $type Type of offer ('sale' or 'rent')
     * @return int
     */
    private function get_active_offers_count($type) {
        $args = array(
            'post_type' => 'eo_property',
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'eo_transaction_type',
                    'field' => 'slug',
                    'terms' => $type
                )
            ),
            'posts_per_page' => -1
        );

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get count of active searches
     *
     * @return int
     */
    private function get_active_searches_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}eo_searches WHERE status = 'active'"
        );
    }

    /**
     * Add dashboard widgets
     *
     * @return void
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'eo_dashboard_stats',
            __('EstateOffice - Statystyki', 'estateoffice'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Render dashboard widget
     *
     * @return void
     */
    public function render_dashboard_widget() {
        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/dashboard-widget.php';
    }

    /**
     * Handle AJAX request for dashboard stats
     *
     * @return void
     */
    public function handle_dashboard_stats() {
        check_ajax_referer('eo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'estateoffice'));
        }

        $stats = array(
            'active_agents' => $this->get_most_active_agents(),
            'sale_offers' => $this->get_active_offers_count('sale'),
            'rent_offers' => $this->get_active_offers_count('rent'),
            'searches' => $this->get_active_searches_count()
        );

        wp_send_json_success($stats);
    }

    /**
     * Render license page
     *
     * @return void
     */
    public function render_license_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
        }

        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/license-page.php';
    }

    /**
     * Render about page
     *
     * @return void
     */
    public function render_about_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
        }

        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/about-page.php';
    }
}