<?php
/**
 * Główna klasa frontendu EstateOffice
 *
 * @package EstateOffice
 * @subpackage Frontend
 * @since 0.5.5
 */

namespace EstateOffice\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Main {
    /**
     * @var object Instance klasy Security
     */
    private $security;

    /**
     * @var object Instance klasy Properties
     */
    private $properties;

    /**
     * @var object Instance klasy Contracts
     */
    private $contracts;

    /**
     * @var object Instance klasy Clients
     */
    private $clients;

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        $this->security = new \EstateOffice\Security();
        $this->properties = new \EstateOffice\Properties();
        $this->contracts = new \EstateOffice\Contracts();
        $this->clients = new \EstateOffice\Clients();

        $this->init_hooks();
    }

    /**
     * Inicjalizacja hooków WordPress
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'register_frontend_endpoints'));
        add_filter('template_include', array($this, 'load_crm_template'));
        add_action('wp', array($this, 'verify_user_access'));
    }

    /**
     * Ładowanie assetów frontendu
     */
    public function enqueue_assets() {
        if (!$this->is_crm_page()) {
            return;
        }

        wp_enqueue_style(
            'eo-frontend-main',
            ESTATEOFFICE_PLUGIN_URL . 'frontend/css/frontend-main.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_enqueue_style(
            'eo-frontend-tables',
            ESTATEOFFICE_PLUGIN_URL . 'frontend/css/frontend-tables.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_enqueue_script(
            'eo-frontend-main',
            ESTATEOFFICE_PLUGIN_URL . 'frontend/js/frontend-main.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_enqueue_script(
            'eo-frontend-search',
            ESTATEOFFICE_PLUGIN_URL . 'frontend/js/frontend-search.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_localize_script('eo-frontend-main', 'eoFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eo_frontend_nonce'),
            'messages' => array(
                'deleteConfirm' => __('Czy na pewno chcesz usunąć ten element?', 'estateoffice'),
                'savingChanges' => __('Zapisywanie zmian...', 'estateoffice'),
                'changesSaved' => __('Zmiany zostały zapisane', 'estateoffice'),
                'error' => __('Wystąpił błąd', 'estateoffice')
            )
        ));
    }

    /**
     * Rejestracja endpointów dla stron CRM
     */
    public function register_frontend_endpoints() {
        add_rewrite_endpoint('crm', EP_ROOT);
        add_rewrite_endpoint('nieruchomosci', EP_ROOT);
        add_rewrite_endpoint('umowy', EP_ROOT);
        add_rewrite_endpoint('klienci', EP_ROOT);
        add_rewrite_endpoint('poszukiwania', EP_ROOT);
    }

    /**
     * Ładowanie odpowiedniego szablonu CRM
     *
     * @param string $template Ścieżka do szablonu
     * @return string Zmodyfikowana ścieżka do szablonu
     */
    public function load_crm_template($template) {
        if (!$this->is_crm_page()) {
            return $template;
        }

        $this->verify_user_access();

        $current_endpoint = $this->get_current_endpoint();
        $template_path = ESTATEOFFICE_PLUGIN_DIR . 'frontend/partials/';

        switch ($current_endpoint) {
            case 'nieruchomosci':
                return $template_path . 'crm-nieruchomosci.php';
            case 'umowy':
                return $template_path . 'crm-umowy.php';
            case 'klienci':
                return $template_path . 'crm-klienci.php';
            case 'poszukiwania':
                return $template_path . 'crm-poszukiwania.php';
            default:
                return $template_path . 'crm-dashboard.php';
        }
    }

    /**
     * Sprawdzanie czy użytkownik ma dostęp do CRM
     */
    public function verify_user_access() {
        if (!$this->is_crm_page()) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }

        if (!current_user_can('access_eo_crm')) {
            wp_die(
                __('Nie masz uprawnień do dostępu do tej sekcji.', 'estateoffice'),
                __('Brak dostępu', 'estateoffice'),
                array('response' => 403)
            );
        }
    }

    /**
     * Sprawdzanie czy jesteśmy na stronie CRM
     *
     * @return boolean
     */
    private function is_crm_page() {
        global $wp_query;
        return isset($wp_query->query_vars['crm']);
    }

    /**
     * Pobieranie aktualnego endpointu CRM
     *
     * @return string|null
     */
    private function get_current_endpoint() {
        global $wp_query;
        
        $endpoints = array('nieruchomosci', 'umowy', 'klienci', 'poszukiwania');
        
        foreach ($endpoints as $endpoint) {
            if (isset($wp_query->query_vars[$endpoint])) {
                return $endpoint;
            }
        }

        return null;
    }

    /**
     * Renderowanie menu nawigacyjnego CRM
     *
     * @return string HTML menu
     */
    public function render_crm_navigation() {
        $current = $this->get_current_endpoint();
        $base_url = home_url('/crm/');

        $menu_items = array(
            'nieruchomosci' => __('Nieruchomości', 'estateoffice'),
            'poszukiwania' => __('Poszukiwania', 'estateoffice'),
            'umowy' => __('Umowy', 'estateoffice'),
            'klienci' => __('Klienci', 'estateoffice')
        );

        ob_start();
        ?>
        <nav class="eo-crm-nav">
            <ul>
                <?php foreach ($menu_items as $endpoint => $label) : ?>
                    <li class="<?php echo $current === $endpoint ? 'active' : ''; ?>">
                        <a href="<?php echo esc_url($base_url . $endpoint); ?>">
                            <?php echo esc_html($label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button class="eo-add-contract-btn" id="eoAddContract">
                <?php _e('Dodaj nową Umowę', 'estateoffice'); ?>
            </button>
        </nav>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderowanie formularza wyszukiwania
     *
     * @param string $type Typ wyszukiwania (nieruchomosci|umowy|klienci|poszukiwania)
     * @return string HTML formularza
     */
    public function render_search_form($type) {
        ob_start();
        ?>
        <form class="eo-search-form" data-type="<?php echo esc_attr($type); ?>">
            <input type="text" 
                   name="search" 
                   class="eo-search-input" 
                   placeholder="<?php esc_attr_e('Wyszukaj...', 'estateoffice'); ?>" 
            />
            <?php wp_nonce_field('eo_search_' . $type, 'eo_search_nonce'); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}