<?php
/**
 * Klasa zarządzająca klientami
 *
 * @package EstateOffice
 * @subpackage Includes
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class Clients {
    /**
     * Instancja klasy Security do walidacji i sanityzacji danych
     * @var Security
     */
    private $security;

    /**
     * Prefix tabeli klientów w bazie danych
     * @var string
     */
    private $table_name;

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'eo_clients';
        $this->security = new Security();
    }

    /**
     * Dodaje nowego klienta
     *
     * @param array $data Dane klienta
     * @return int|WP_Error ID nowego klienta lub błąd
     */
    public function add_client($data) {
        global $wpdb;

        try {
            // Sprawdzenie uprawnień
            if (!current_user_can('edit_eo_properties')) {
                return new \WP_Error('forbidden', __('Nie masz uprawnień do dodawania klientów.', 'estateoffice'));
            }

            // Walidacja danych
            $validated_data = $this->validate_client_data($data);
            if (is_wp_error($validated_data)) {
                return $validated_data;
            }

            // Sanityzacja danych
            $sanitized_data = $this->sanitize_client_data($validated_data);

            // Dodanie timestampów
            $sanitized_data['created_at'] = current_time('mysql');
            $sanitized_data['updated_at'] = current_time('mysql');

            // Próba dodania klienta do bazy
            $result = $wpdb->insert(
                $this->table_name,
                $sanitized_data,
                $this->get_data_format($sanitized_data)
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            $client_id = $wpdb->insert_id;

            // Logowanie operacji
            DebugLogger::log("Dodano nowego klienta (ID: {$client_id})", 'info');

            // Hook po dodaniu klienta
            do_action('estateoffice_after_add_client', $client_id, $sanitized_data);

            return $client_id;

        } catch (\Exception $e) {
            DebugLogger::log("Błąd podczas dodawania klienta: " . $e->getMessage(), 'error');
            return new \WP_Error('db_error', __('Nie udało się dodać klienta.', 'estateoffice'));
        }
    }

    /**
     * Pobiera dane klienta
     *
     * @param int $client_id ID klienta
     * @return object|WP_Error Dane klienta lub błąd
     */
    public function get_client($client_id) {
        global $wpdb;

        try {
            // Sprawdzenie uprawnień
            if (!current_user_can('read')) {
                return new \WP_Error('forbidden', __('Nie masz uprawnień do przeglądania klientów.', 'estateoffice'));
            }

            $client = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $client_id
                )
            );

            if (null === $client) {
                return new \WP_Error('not_found', __('Klient nie został znaleziony.', 'estateoffice'));
            }

            // Filtrowanie danych przed zwróceniem
            return $this->prepare_client_for_response($client);

        } catch (\Exception $e) {
            DebugLogger::log("Błąd podczas pobierania klienta: " . $e->getMessage(), 'error');
            return new \WP_Error('db_error', __('Nie udało się pobrać danych klienta.', 'estateoffice'));
        }
    }

    /**
     * Pobiera listę klientów z możliwością filtrowania i paginacji
     *
     * @param array $args Argumenty filtrowania i paginacji
     * @return array|WP_Error Lista klientów lub błąd
     */
    public function get_clients($args = array()) {
        global $wpdb;

        try {
            // Sprawdzenie uprawnień
            if (!current_user_can('read')) {
                return new \WP_Error('forbidden', __('Nie masz uprawnień do przeglądania klientów.', 'estateoffice'));
            }

            // Domyślne argumenty
            $default_args = array(
                'per_page' => 20,
                'page' => 1,
                'orderby' => 'id',
                'order' => 'DESC',
                'type' => '',
                'status' => 'active',
                'agent_id' => '',
                'search' => ''
            );

            $args = wp_parse_args($args, $default_args);
            $args = $this->sanitize_query_args($args);

            // Budowanie zapytania
            $query = "SELECT * FROM {$this->table_name} WHERE 1=1";
            $query_count = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
            $query_args = array();

            // Dodawanie warunków
            if (!empty($args['type'])) {
                $query .= " AND type = %s";
                $query_count .= " AND type = %s";
                $query_args[] = $args['type'];
            }

            if (!empty($args['status'])) {
                $query .= " AND status = %s";
                $query_count .= " AND status = %s";
                $query_args[] = $args['status'];
            }

            if (!empty($args['agent_id'])) {
                $query .= " AND agent_id = %d";
                $query_count .= " AND agent_id = %d";
                $query_args[] = $args['agent_id'];
            }

            if (!empty($args['search'])) {
                $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
                $query .= " AND (
                    first_name LIKE %s OR 
                    last_name LIKE %s OR 
                    company_name LIKE %s OR 
                    email LIKE %s OR 
                    phone LIKE %s
                )";
                $query_count .= " AND (
                    first_name LIKE %s OR 
                    last_name LIKE %s OR 
                    company_name LIKE %s OR 
                    email LIKE %s OR 
                    phone LIKE %s
                )";
                $query_args = array_merge($query_args, 
                    array($search_term, $search_term, $search_term, $search_term, $search_term)
                );
            }

            // Dodawanie sortowania
            $query .= $wpdb->prepare(" ORDER BY %s %s", 
                array($args['orderby'], $args['order'])
            );

            // Dodawanie paginacji
            $offset = ($args['page'] - 1) * $args['per_page'];
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", 
                array($args['per_page'], $offset)
            );

            // Wykonanie zapytań
            $total = $wpdb->get_var($wpdb->prepare($query_count, $query_args));
            $results = $wpdb->get_results($wpdb->prepare($query, $query_args));

            // Przygotowanie odpowiedzi
            $clients = array_map(array($this, 'prepare_client_for_response'), $results);

            return array(
                'items' => $clients,
                'total' => (int) $total,
                'pages' => ceil($total / $args['per_page']),
                'current_page' => (int) $args['page']
            );

        } catch (\Exception $e) {
            DebugLogger::log("Błąd podczas pobierania listy klientów: " . $e->getMessage(), 'error');
            return new \WP_Error('db_error', __('Nie udało się pobrać listy klientów.', 'estateoffice'));
        }
    }
	/**
     * Aktualizuje dane klienta
     *
     * @param int $client_id ID klienta
     * @param array $data Nowe dane klienta
     * @return bool|WP_Error True w przypadku sukcesu lub błąd
     */
    public function update_client($client_id, $data) {
        global $wpdb;

        try {
            // Sprawdzenie uprawnień
            if (!current_user_can('edit_eo_properties')) {
                return new \WP_Error('forbidden', __('Nie masz uprawnień do edycji klientów.', 'estateoffice'));
            }

            // Sprawdzenie czy klient istnieje
            $existing_client = $this->get_client($client_id);
            if (is_wp_error($existing_client)) {
                return $existing_client;
            }

            // Walidacja danych
            $validated_data = $this->validate_client_data($data, true);
            if (is_wp_error($validated_data)) {
                return $validated_data;
            }

            // Sanityzacja danych
            $sanitized_data = $this->sanitize_client_data($validated_data);
            
            // Aktualizacja timestamp
            $sanitized_data['updated_at'] = current_time('mysql');

            // Aktualizacja w bazie
            $result = $wpdb->update(
                $this->table_name,
                $sanitized_data,
                array('id' => $client_id),
                $this->get_data_format($sanitized_data),
                array('%d')
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Logowanie operacji
            DebugLogger::log("Zaktualizowano klienta (ID: {$client_id})", 'info');

            // Hook po aktualizacji
            do_action('estateoffice_after_update_client', $client_id, $sanitized_data);

            return true;

        } catch (\Exception $e) {
            DebugLogger::log("Błąd podczas aktualizacji klienta: " . $e->getMessage(), 'error');
            return new \WP_Error('db_error', __('Nie udało się zaktualizować klienta.', 'estateoffice'));
        }
    }

    /**
     * Usuwa klienta
     *
     * @param int $client_id ID klienta
     * @return bool|WP_Error True w przypadku sukcesu lub błąd
     */
    public function delete_client($client_id) {
        global $wpdb;

        try {
            // Sprawdzenie uprawnień
            if (!current_user_can('manage_options')) {
                return new \WP_Error('forbidden', __('Nie masz uprawnień do usuwania klientów.', 'estateoffice'));
            }

            // Sprawdzenie czy klient istnieje
            $client = $this->get_client($client_id);
            if (is_wp_error($client)) {
                return $client;
            }

            // Sprawdzenie powiązanych umów
            $related_contracts = $this->get_client_contracts($client_id);
            if (!empty($related_contracts)) {
                return new \WP_Error(
                    'has_relations',
                    __('Nie można usunąć klienta, który ma powiązane umowy.', 'estateoffice')
                );
            }

            // Usunięcie z bazy
            $result = $wpdb->delete(
                $this->table_name,
                array('id' => $client_id),
                array('%d')
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }

            // Logowanie operacji
            DebugLogger::log("Usunięto klienta (ID: {$client_id})", 'info');

            // Hook po usunięciu
            do_action('estateoffice_after_delete_client', $client_id);

            return true;

        } catch (\Exception $e) {
            DebugLogger::log("Błąd podczas usuwania klienta: " . $e->getMessage(), 'error');
            return new \WP_Error('db_error', __('Nie udało się usunąć klienta.', 'estateoffice'));
        }
    }

    /**
     * Pobiera umowy klienta
     *
     * @param int $client_id ID klienta
     * @return array|WP_Error Lista umów lub błąd
     */
    public function get_client_contracts($client_id) {
        global $wpdb;

        try {
            $contracts_table = $wpdb->prefix . 'eo_contract_clients';
            
            $contracts = $wpdb->get_results($wpdb->prepare(
                "SELECT c.* 
                FROM {$wpdb->prefix}eo_contracts c 
                JOIN {$contracts_table} cc ON c.id = cc.contract_id 
                WHERE cc.client_id = %d",
                $client_id
            ));

            return $contracts;

        } catch (\Exception $e) {
            DebugLogger::log("Błąd podczas pobierania umów klienta: " . $e->getMessage(), 'error');
            return new \WP_Error('db_error', __('Nie udało się pobrać umów klienta.', 'estateoffice'));
        }
    }

    /**
     * Waliduje dane klienta
     *
     * @param array $data Dane do walidacji
     * @param bool $is_update Czy to aktualizacja istniejącego klienta
     * @return array|WP_Error Zwalidowane dane lub błąd
     */
    private function validate_client_data($data, $is_update = false) {
        $errors = new \WP_Error();

        // Sprawdzenie wymaganych pól
        $required_fields = array(
            'type' => __('Typ klienta', 'estateoffice')
        );

        if ($data['type'] === 'individual') {
            $required_fields['first_name'] = __('Imię', 'estateoffice');
            $required_fields['last_name'] = __('Nazwisko', 'estateoffice');
        } else {
            $required_fields['company_name'] = __('Nazwa firmy', 'estateoffice');
            $required_fields['representative_name'] = __('Przedstawiciel', 'estateoffice');
        }

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors->add(
                    'required_field',
                    sprintf(__('Pole %s jest wymagane.', 'estateoffice'), $label)
                );
            }
        }

        // Walidacja email
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors->add('invalid_email', __('Podany adres email jest nieprawidłowy.', 'estateoffice'));
        }

        // Walidacja NIP dla firm
        if ($data['type'] === 'company' && !empty($data['nip'])) {
            if (!$this->validate_nip($data['nip'])) {
                $errors->add('invalid_nip', __('Podany numer NIP jest nieprawidłowy.', 'estateoffice'));
            }
        }

        // Walidacja PESEL dla osób fizycznych
        if ($data['type'] === 'individual' && !empty($data['pesel'])) {
            if (!$this->validate_pesel($data['pesel'])) {
                $errors->add('invalid_pesel', __('Podany numer PESEL jest nieprawidłowy.', 'estateoffice'));
            }
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return $data;
    }

    /**
     * Sanityzuje dane klienta
     *
     * @param array $data Dane do sanityzacji
     * @return array Sanityzowane dane
     */
    private function sanitize_client_data($data) {
        return array(
            'type' => sanitize_text_field($data['type']),
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            'company_name' => isset($data['company_name']) ? sanitize_text_field($data['company_name']) : '',
            'representative_name' => isset($data['representative_name']) ? sanitize_text_field($data['representative_name']) : '',
            'pesel' => isset($data['pesel']) ? sanitize_text_field($data['pesel']) : '',
            'nip' => isset($data['nip']) ? sanitize_text_field($data['nip']) : '',
            'regon' => isset($data['regon']) ? sanitize_text_field($data['regon']) : '',
            'krs' => isset($data['krs']) ? sanitize_text_field($data['krs']) : '',
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
            'website' => isset($data['website']) ? esc_url_raw($data['website']) : '',
            'street' => isset($data['street']) ? sanitize_text_field($data['street']) : '',
            'street_number' => isset($data['street_number']) ? sanitize_text_field($data['street_number']) : '',
            'apartment_number' => isset($data['apartment_number']) ? sanitize_text_field($data['apartment_number']) : '',
            'postal_code' => isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '',
            'city' => isset($data['city']) ? sanitize_text_field($data['city']) : '',
            'country' => isset($data['country']) ? sanitize_text_field($data['country']) : 'Polska',
            'correspondence_address' => isset($data['correspondence_address']) ? sanitize_textarea_field($data['correspondence_address']) : '',
            'agent_id' => isset($data['agent_id']) ? absint($data['agent_id']) : 0,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active'
        );
    }

    /**
     * Przygotowuje dane klienta do odpowiedzi API
     *
     * @param object $client Dane klienta z bazy
     * @return object Przygotowane dane
     */
    private function prepare_client_for_response($client) {
        // Ukrywanie wrażliwych danych
        unset($client->pesel);
        unset($client->id_number);

        // Formatowanie dat
        $client->created_at = mysql2date('c', $client->created_at);
        $client->updated_at = mysql2date('c', $client->updated_at);

        // Dodawanie danych agenta
        if (!empty($client->agent_id)) {
            $agent = new Agents();
            $client->agent = $agent->get_agent($client->agent_id);
        }

        return $client;
    }

    /**
     * Zwraca format danych dla wpdb
     *
     * @param array $data Dane
     * @return array Format danych
     */
    private function get_data_format($data) {
        $format = array();
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'id':
                case 'agent_id':
                    $format[] = '%d';
                    break;
                default:
                    $format[] = '%s';
            }
        }
        return $format;
    }

    /**
     * Waliduje numer NIP
     *
     * @param string $nip Numer NIP
     * @return bool Czy NIP jest prawidłowy
     */
    private function validate_nip($nip) {
        $nip = preg_replace('/[^0-9]/', '', $nip);
        
        if (strlen($nip) !== 10) {
            return false;
        }

        $weights = array(6, 5, 7, 2, 3, 4, 5, 6, 7);
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $sum += $nip[$i] * $weights[$i];
        }
        
        $checksum = $sum % 11;
        if ($checksum === 10) {
            $checksum = 0;
        }
        
        return $checksum == $nip[9];
    }

    /**
     * Waliduje numer PESEL
     *
     * @param string $pesel Numer PESEL
     * @return bool Czy PESEL jest prawidłowy
     */
    private function validate_pesel($pesel) {
        $pesel = preg_replace('/[^0-9]/', '', $pesel);
        
        if (strlen($pesel) !== 11) {
            return false;
        }

        $weights = array(1, 3, 7, 9, 1, 3, 7, 9, 1, 3);
        $sum = 0;
        
        for ($i = 0; $i < 10; $i++) {
            $sum += $pesel[$i] * $weights[$i];
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        
        return $checksum == $pesel[10];
    }
}