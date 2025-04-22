<?php
/**
 * Klasa zarządzająca licencjami wtyczki EstateOffice
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

/**
 * Klasa AdminLicense
 * 
 * Zarządza systemem licencjonowania wtyczki EstateOffice.
 * Odpowiada za weryfikację, aktywację i dezaktywację licencji.
 */
class AdminLicense {

    /**
     * @var string Klucz opcji w bazie danych przechowujący licencję
     */
    private $license_key_option = 'eo_license_key';

    /**
     * @var string Klucz opcji przechowujący status licencji
     */
    private $license_status_option = 'eo_license_status';

    /**
     * @var string URL serwera licencji
     */
    private $license_server_url = 'https://estateoffice.pl/api/license';

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_license_settings'));
        add_action('admin_menu', array($this, 'add_license_menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_eo_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_eo_deactivate_license', array($this, 'ajax_deactivate_license'));
    }

    /**
     * Rejestruje ustawienia licencji
     */
    public function register_license_settings() {
        register_setting(
            'eo_license_settings',
            $this->license_key_option,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_license_key'),
                'default' => ''
            )
        );

        register_setting(
            'eo_license_settings',
            $this->license_status_option,
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'inactive'
            )
        );
    }

    /**
     * Dodaje podmenu licencji do menu EstateOffice
     */
    public function add_license_menu() {
        add_submenu_page(
            'estateoffice',
            __('Licencja EstateOffice', 'estateoffice'),
            __('Licencja', 'estateoffice'),
            'manage_options',
            'eo-license',
            array($this, 'render_license_page')
        );
    }

    /**
     * Ładuje assety na stronie licencji
     * 
     * @param string $hook Hook aktualnej strony admin
     */
    public function enqueue_assets($hook) {
        if ('estateoffice_page_eo-license' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'eo-admin-license',
            ESTATEOFFICE_PLUGIN_URL . 'admin/css/admin-license.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_enqueue_script(
            'eo-admin-license',
            ESTATEOFFICE_PLUGIN_URL . 'admin/js/admin-license.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_localize_script('eo-admin-license', 'eoLicense', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eo_license_nonce'),
            'activating' => __('Aktywowanie licencji...', 'estateoffice'),
            'deactivating' => __('Dezaktywowanie licencji...', 'estateoffice'),
            'error' => __('Wystąpił błąd. Spróbuj ponownie.', 'estateoffice')
        ));
    }

    /**
     * Renderuje stronę licencji
     */
    public function render_license_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $license_key = get_option($this->license_key_option, '');
        $license_status = get_option($this->license_status_option, 'inactive');

        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/license-page.php';
    }

    /**
     * Obsługuje aktywację licencji przez AJAX
     */
    public function ajax_activate_license() {
        check_ajax_referer('eo_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'estateoffice'));
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        
        if (empty($license_key)) {
            wp_send_json_error(__('Klucz licencji jest wymagany.', 'estateoffice'));
        }

        $response = $this->verify_license($license_key);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        update_option($this->license_key_option, $license_key);
        update_option($this->license_status_option, 'active');

        wp_send_json_success(array(
            'message' => __('Licencja została pomyślnie aktywowana.', 'estateoffice')
        ));
    }

    /**
     * Obsługuje dezaktywację licencji przez AJAX
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('eo_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień.', 'estateoffice'));
        }

        $license_key = get_option($this->license_key_option);
        
        if (empty($license_key)) {
            wp_send_json_error(__('Brak aktywnej licencji.', 'estateoffice'));
        }

        $response = $this->deactivate_license($license_key);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        delete_option($this->license_key_option);
        update_option($this->license_status_option, 'inactive');

        wp_send_json_success(array(
            'message' => __('Licencja została dezaktywowana.', 'estateoffice')
        ));
    }

    /**
     * Weryfikuje klucz licencji z serwerem
     * 
     * @param string $license_key Klucz licencji do weryfikacji
     * @return bool|\WP_Error True jeśli weryfikacja się powiodła, WP_Error w przypadku błędu
     */
    private function verify_license($license_key) {
        $response = wp_remote_post($this->license_server_url . '/verify', array(
            'timeout' => 15,
            'body' => array(
                'license_key' => $license_key,
                'site_url' => home_url(),
                'plugin_version' => ESTATEOFFICE_VERSION
            )
        ));

        if (is_wp_error($response)) {
            return new \WP_Error('license_verification_failed', 
                __('Nie udało się połączyć z serwerem licencji.', 'estateoffice'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['success'])) {
            return new \WP_Error('invalid_response', 
                __('Otrzymano nieprawidłową odpowiedź z serwera.', 'estateoffice'));
        }

        if (!$data['success']) {
            return new \WP_Error('license_invalid', 
                isset($data['message']) ? $data['message'] : __('Nieprawidłowy klucz licencji.', 'estateoffice'));
        }

        return true;
    }

    /**
     * Dezaktywuje licencję na serwerze
     * 
     * @param string $license_key Klucz licencji do dezaktywacji
     * @return bool|\WP_Error True jeśli dezaktywacja się powiodła, WP_Error w przypadku błędu
     */
    private function deactivate_license($license_key) {
        $response = wp_remote_post($this->license_server_url . '/deactivate', array(
            'timeout' => 15,
            'body' => array(
                'license_key' => $license_key,
                'site_url' => home_url()
            )
        ));

        if (is_wp_error($response)) {
            return new \WP_Error('license_deactivation_failed', 
                __('Nie udało się połączyć z serwerem licencji.', 'estateoffice'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['success'])) {
            return new \WP_Error('invalid_response', 
                __('Otrzymano nieprawidłową odpowiedź z serwera.', 'estateoffice'));
        }

        if (!$data['success']) {
            return new \WP_Error('deactivation_failed', 
                isset($data['message']) ? $data['message'] : __('Nie udało się dezaktywować licencji.', 'estateoffice'));
        }

        return true;
    }

    /**
     * Sanityzuje klucz licencji
     * 
     * @param string $key Klucz licencji do sanityzacji
     * @return string Sanityzowany klucz licencji
     */
    public function sanitize_license_key($key) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    }

    /**
     * Sprawdza status licencji
     * 
     * @return bool True jeśli licencja jest aktywna
     */
    public function is_license_active() {
        $status = get_option($this->license_status_option, 'inactive');
        return $status === 'active';
    }

    /**
     * Pobiera aktualny klucz licencji
     * 
     * @return string Aktualny klucz licencji lub pusty string
     */
    public function get_license_key() {
        return get_option($this->license_key_option, '');
    }
}