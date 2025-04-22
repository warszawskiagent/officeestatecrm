<?php
/**
 * Klasa odpowiedzialna za bezpieczeństwo wtyczki EstateOffice
 *
 * @package EstateOffice
 * @subpackage Security
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit;
}

class Security {
    
    /**
     * Inicjalizacja hooków związanych z bezpieczeństwem
     *
     * @since 0.5.5
     * @return void
     */
    public function __construct() {
        add_action('init', array($this, 'init_nonces'));
    }

    /**
     * Inicjalizacja nonców używanych w wtyczce
     *
     * @since 0.5.5
     * @return void
     */
    public function init_nonces() {
        add_action('admin_head', array($this, 'output_nonce_fields'));
        add_action('wp_head', array($this, 'output_nonce_fields'));
    }

    /**
     * Generowanie pól nonce dla formularzy
     *
     * @since 0.5.5
     * @return void
     */
    public function output_nonce_fields() {
        if ($this->is_estateoffice_page()) {
            wp_nonce_field('eo_security_nonce', 'eo_nonce');
        }
    }

    /**
     * Sprawdzenie czy jesteśmy na stronie wtyczki
     *
     * @since 0.5.5
     * @return boolean
     */
    private function is_estateoffice_page() {
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        return strpos($page, 'eo_') === 0 || is_singular('eo_property');
    }

    /**
     * Sanityzacja danych nieruchomości
     *
     * @since 0.5.5
     * @param array $data Surowe dane nieruchomości
     * @return array Sanityzowane dane
     */
    public static function sanitize_property_data($data) {
        return array(
            'title' => sanitize_text_field($data['title'] ?? ''),
            'price' => filter_var($data['price'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'address' => self::sanitize_address_data($data['address'] ?? array()),
            'features' => self::sanitize_features_data($data['features'] ?? array()),
            'meta' => self::sanitize_meta_data($data['meta'] ?? array())
        );
    }

    /**
     * Sanityzacja danych adresowych
     *
     * @since 0.5.5
     * @param array $address Dane adresowe
     * @return array Sanityzowane dane
     */
    private static function sanitize_address_data($address) {
        return array(
            'street' => sanitize_text_field($address['street'] ?? ''),
            'number' => sanitize_text_field($address['number'] ?? ''),
            'apartment' => sanitize_text_field($address['apartment'] ?? ''),
            'postal_code' => sanitize_text_field($address['postal_code'] ?? ''),
            'city' => sanitize_text_field($address['city'] ?? ''),
            'district' => sanitize_text_field($address['district'] ?? '')
        );
    }

    /**
     * Sanityzacja danych o cechach nieruchomości
     *
     * @since 0.5.5
     * @param array $features Cechy nieruchomości
     * @return array Sanityzowane dane
     */
    private static function sanitize_features_data($features) {
        return array(
            'area' => filter_var($features['area'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'rooms' => absint($features['rooms'] ?? 0),
            'floor' => absint($features['floor'] ?? 0),
            'total_floors' => absint($features['total_floors'] ?? 0),
            'year_built' => absint($features['year_built'] ?? 0),
            'parking_spots' => absint($features['parking_spots'] ?? 0)
        );
    }

    /**
     * Sanityzacja metadanych
     *
     * @since 0.5.5
     * @param array $meta Metadane
     * @return array Sanityzowane dane
     */
    private static function sanitize_meta_data($meta) {
        $sanitized_meta = array();
        foreach ($meta as $key => $value) {
            $sanitized_meta[sanitize_key($key)] = sanitize_text_field($value);
        }
        return $sanitized_meta;
    }

    /**
     * Sanityzacja danych klienta
     *
     * @since 0.5.5
     * @param array $data Dane klienta
     * @return array Sanityzowane dane
     */
    public static function sanitize_client_data($data) {
        return array(
            'type' => in_array($data['type'], array('person', 'company')) ? $data['type'] : 'person',
            'personal' => array(
                'first_name' => sanitize_text_field($data['personal']['first_name'] ?? ''),
                'last_name' => sanitize_text_field($data['personal']['last_name'] ?? ''),
                'email' => sanitize_email($data['personal']['email'] ?? ''),
                'phone' => sanitize_text_field($data['personal']['phone'] ?? '')
            ),
            'company' => array(
                'name' => sanitize_text_field($data['company']['name'] ?? ''),
                'nip' => sanitize_text_field($data['company']['nip'] ?? ''),
                'regon' => sanitize_text_field($data['company']['regon'] ?? ''),
                'krs' => sanitize_text_field($data['company']['krs'] ?? '')
            )
        );
    }

    /**
     * Sanityzacja danych umowy
     *
     * @since 0.5.5
     * @param array $data Dane umowy
     * @return array Sanityzowane dane
     */
    public static function sanitize_contract_data($data) {
        return array(
            'number' => sanitize_text_field($data['number'] ?? ''),
            'type' => self::validate_contract_type($data['type'] ?? ''),
            'start_date' => sanitize_text_field($data['start_date'] ?? ''),
            'end_date' => sanitize_text_field($data['end_date'] ?? ''),
            'commission' => array(
                'amount' => filter_var($data['commission']['amount'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                'currency' => self::validate_currency($data['commission']['currency'] ?? 'PLN')
            )
        );
    }

    /**
     * Walidacja typu umowy
     *
     * @since 0.5.5
     * @param string $type Typ umowy
     * @return string Zwalidowany typ
     */
    private static function validate_contract_type($type) {
        $allowed_types = array('SPRZEDAZ', 'KUPNO', 'WYNAJEM', 'NAJEM');
        return in_array($type, $allowed_types) ? $type : 'SPRZEDAZ';
    }

    /**
     * Walidacja waluty
     *
     * @since 0.5.5
     * @param string $currency Waluta
     * @return string Zwalidowana waluta
     */
    private static function validate_currency($currency) {
        $allowed_currencies = array('PLN', 'EUR', 'USD');
        return in_array($currency, $allowed_currencies) ? $currency : 'PLN';
    }

    /**
     * Weryfikacja uprawnień agenta
     *
     * @since 0.5.5
     * @param string $capability Uprawnienie do sprawdzenia
     * @return void
     */
    public static function verify_agent_permissions($capability = 'edit_eo_properties') {
        if (!current_user_can($capability)) {
            wp_die(
                __('Nie masz uprawnień do wykonania tej operacji.', 'estateoffice'),
                __('Błąd uprawnień', 'estateoffice'),
                array('response' => 403)
            );
        }
    }

    /**
     * Weryfikacja nonce
     *
     * @since 0.5.5
     * @param string $nonce Wartość nonce
     * @param string $action Akcja nonce
     * @return void
     */
    public static function verify_nonce($nonce, $action) {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die(
                __('Błąd weryfikacji bezpieczeństwa.', 'estateoffice'),
                __('Błąd bezpieczeństwa', 'estateoffice'),
                array('response' => 403)
            );
        }
    }

    /**
     * Logowanie błędów bezpieczeństwa
     *
     * @since 0.5.5
     * @param string $message Wiadomość błędu
     * @param array $context Kontekst błędu
     * @return void
     */
    public static function log_security_issue($message, $context = array()) {
        if (class_exists('EstateOffice\DebugLogger')) {
            DebugLogger::log(
                sprintf('[Security] %s | Context: %s', 
                    $message, 
                    json_encode($context)
                ),
                'security'
            );
        }
    }
}