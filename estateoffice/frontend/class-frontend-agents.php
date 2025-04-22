<?php
/**
 * Klasa zarządzająca wyświetlaniem agentów w części frontendowej
 *
 * @package EstateOffice
 * @subpackage Frontend
 * @since 0.5.5
 */

namespace EstateOffice\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Agents {
    /**
     * @var object Instance klasy
     */
    private static $instance = null;

    /**
     * @var string Prefix dla metadanych agenta
     */
    private $meta_prefix = 'eo_agent_';

    /**
     * Konstruktor klasy
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków
     *
     * @return void
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'register_agent_endpoints'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'handle_agent_profile'));
    }

    /**
     * Zwraca instancję klasy (Singleton)
     *
     * @return Frontend_Agents
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Rejestracja assetów dla strony agenta
     *
     * @return void
     */
    public function enqueue_assets() {
        if ($this->is_agent_profile_page()) {
            wp_enqueue_style(
                'eo-agent-profile',
                ESTATEOFFICE_PLUGIN_URL . 'frontend/css/agent-profile.css',
                array(),
                ESTATEOFFICE_VERSION
            );

            wp_enqueue_script(
                'eo-agent-profile',
                ESTATEOFFICE_PLUGIN_URL . 'frontend/js/agent-profile.js',
                array('jquery'),
                ESTATEOFFICE_VERSION,
                true
            );

            wp_localize_script('eo-agent-profile', 'eoAgentProfile', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eo_agent_profile_nonce')
            ));
        }
    }

    /**
     * Rejestracja endpointów dla profili agentów
     *
     * @return void
     */
    public function register_agent_endpoints() {
        add_rewrite_rule(
            'agent/([^/]+)/?$',
            'index.php?eo_agent=$matches[1]',
            'top'
        );
        
        flush_rewrite_rules();
    }

    /**
     * Rejestracja zmiennych query
     *
     * @param array $vars
     * @return array
     */
    public function register_query_vars($vars) {
        $vars[] = 'eo_agent';
        return $vars;
    }

    /**
     * Obsługa wyświetlania profilu agenta
     *
     * @return void
     */
    public function handle_agent_profile() {
        $agent_slug = get_query_var('eo_agent');
        
        if ($agent_slug) {
            $agent = $this->get_agent_by_slug($agent_slug);
            
            if (!$agent) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                return;
            }

            add_filter('template_include', function($template) {
                return ESTATEOFFICE_PLUGIN_DIR . 'templates/agent-profile.php';
            });
        }
    }

    /**
     * Pobiera dane agenta na podstawie sluga
     *
     * @param string $slug
     * @return object|false
     */
    public function get_agent_by_slug($slug) {
        global $wpdb;
        
        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}eo_agents a 
            JOIN {$wpdb->users} u ON a.user_id = u.ID 
            WHERE u.user_nicename = %s",
            $slug
        ));

        if (!$agent) {
            return false;
        }

        // Pobierz metadane agenta
        $agent->meta = $this->get_agent_meta($agent->id);
        
        return $agent;
    }

    /**
     * Pobiera metadane agenta
     *
     * @param int $agent_id
     * @return array
     */
    private function get_agent_meta($agent_id) {
        global $wpdb;
        
        $meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value 
            FROM {$wpdb->prefix}eo_agentmeta 
            WHERE agent_id = %d",
            $agent_id
        ));

        $metadata = array();
        foreach ($meta as $row) {
            $key = str_replace($this->meta_prefix, '', $row->meta_key);
            $metadata[$key] = maybe_unserialize($row->meta_value);
        }

        return $metadata;
    }

    /**
     * Pobiera listę ofert agenta
     *
     * @param int $agent_id
     * @return array
     */
    public function get_agent_properties($agent_id) {
        $args = array(
            'post_type' => 'eo_property',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'eo_property_agent_id',
                    'value' => $agent_id,
                    'compare' => '='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Sprawdza czy aktualna strona to profil agenta
     *
     * @return boolean
     */
    private function is_agent_profile_page() {
        return get_query_var('eo_agent') ? true : false;
    }

    /**
     * Generuje publiczny URL profilu agenta
     *
     * @param int $agent_id
     * @return string
     */
    public function get_agent_profile_url($agent_id) {
        $agent = get_userdata($agent_id);
        if (!$agent) {
            return '';
        }
        
        return home_url('agent/' . $agent->user_nicename);
    }

    /**
     * Renderuje kartę agenta
     *
     * @param int $agent_id
     * @param array $args Dodatkowe parametry wyświetlania
     * @return string
     */
    public function render_agent_card($agent_id, $args = array()) {
        $defaults = array(
            'show_properties' => false,
            'show_contact' => true,
            'class' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        $agent = $this->get_agent_by_id($agent_id);
        
        if (!$agent) {
            return '';
        }

        ob_start();
        include ESTATEOFFICE_PLUGIN_DIR . 'frontend/partials/agent-card.php';
        return ob_get_clean();
    }

    /**
     * Pobiera dane agenta po ID
     *
     * @param int $agent_id
     * @return object|false
     */
    private function get_agent_by_id($agent_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}eo_agents a 
            JOIN {$wpdb->users} u ON a.user_id = u.ID 
            WHERE a.id = %d",
            $agent_id
        ));
    }
}