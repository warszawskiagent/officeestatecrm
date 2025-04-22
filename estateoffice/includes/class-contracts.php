<?php
/**
 * Klasa obsługująca umowy w systemie EstateOffice
 *
 * @package EstateOffice
 * @subpackage Contracts
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class Contracts {
    /**
     * @var \wpdb Instancja klasy WordPress Database
     */
    private $db;

    /**
     * @var string Nazwa tabeli umów
     */
    private $table_contracts;

    /**
     * @var string Nazwa tabeli etapów umów
     */
    private $table_contract_stages;

    /**
     * @var string Nazwa tabeli powiązań umów z klientami
     */
    private $table_contract_clients;

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_contracts = $wpdb->prefix . 'eo_contracts';
        $this->table_contract_stages = $wpdb->prefix . 'eo_contract_stages';
        $this->table_contract_clients = $wpdb->prefix . 'eo_contract_clients';
    }

    /**
     * Pobiera pojedynczą umowę
     *
     * @param int $contract_id ID umowy
     * @return object|null Dane umowy lub null
     */
    public function get_contract($contract_id) {
        $contract = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_contracts} WHERE id = %d",
                $contract_id
            )
        );

        if ($contract) {
            $contract->clients = $this->get_contract_clients($contract_id);
            $contract->stages = $this->get_contract_stages($contract_id);
            return $contract;
        }

        return null;
    }

    /**
     * Dodaje nową umowę
     *
     * @param array $contract_data Dane umowy
     * @return int|false ID nowej umowy lub false w przypadku błędu
     */
    public function add_contract($contract_data) {
        try {
            $this->db->query('START TRANSACTION');

            // Walidacja danych
            $validated_data = $this->validate_contract_data($contract_data);
            if (is_wp_error($validated_data)) {
                throw new \Exception($validated_data->get_error_message());
            }

            // Generowanie unikalnego numeru umowy
            $validated_data['contract_number'] = $this->generate_contract_number();

            // Wstawienie umowy
            $result = $this->db->insert(
                $this->table_contracts,
                $validated_data,
                array(
                    '%s', // contract_number
                    '%s', // transaction_type
                    '%d', // agent_id
                    '%d', // property_id
                    '%s', // start_date
                    '%s', // end_date
                    '%d', // is_indefinite
                    '%f', // commission_amount
                    '%s', // commission_currency
                    '%s', // commission_type
                    '%s', // stage
                    '%s'  // status
                )
            );

            if ($result === false) {
                throw new \Exception('Błąd podczas dodawania umowy');
            }

            $contract_id = $this->db->insert_id;

            // Dodanie pierwszego etapu umowy
            $this->add_contract_stage($contract_id, 'umowa_posrednictwa', null);

            // Powiązanie klientów z umową
            if (!empty($contract_data['clients'])) {
                foreach ($contract_data['clients'] as $client) {
                    $this->add_contract_client($contract_id, $client['client_id'], $client['role']);
                }
            }

            $this->db->query('COMMIT');
            return $contract_id;

        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Contract Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Aktualizuje umowę
     *
     * @param int $contract_id ID umowy
     * @param array $contract_data Dane do aktualizacji
     * @return bool Status aktualizacji
     */
    public function update_contract($contract_id, $contract_data) {
        try {
            $this->db->query('START TRANSACTION');

            // Walidacja danych
            $validated_data = $this->validate_contract_data($contract_data, true);
            if (is_wp_error($validated_data)) {
                throw new \Exception($validated_data->get_error_message());
            }

            // Aktualizacja umowy
            $result = $this->db->update(
                $this->table_contracts,
                $validated_data,
                array('id' => $contract_id),
                array(
                    '%s', // transaction_type
                    '%d', // agent_id
                    '%d', // property_id
                    '%s', // start_date
                    '%s', // end_date
                    '%d', // is_indefinite
                    '%f', // commission_amount
                    '%s', // commission_currency
                    '%s', // commission_type
                    '%s', // stage
                    '%s'  // status
                ),
                array('%d')
            );

            if ($result === false) {
                throw new \Exception('Błąd podczas aktualizacji umowy');
            }

            // Aktualizacja powiązań z klientami
            if (isset($contract_data['clients'])) {
                $this->update_contract_clients($contract_id, $contract_data['clients']);
            }

            $this->db->query('COMMIT');
            return true;

        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Contract Update Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Waliduje dane umowy
     *
     * @param array $data Dane do walidacji
     * @param bool $is_update Czy to aktualizacja
     * @return array|\WP_Error Zwalidowane dane lub obiekt błędu
     */
    private function validate_contract_data($data, $is_update = false) {
        $errors = new \WP_Error();
        $validated = array();

        // Wymagane pola
        $required_fields = array(
            'transaction_type' => __('Typ transakcji', 'estateoffice'),
            'agent_id' => __('Agent', 'estateoffice'),
            'start_date' => __('Data rozpoczęcia', 'estateoffice')
        );

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors->add(
                    'required_field',
                    sprintf(__('Pole %s jest wymagane', 'estateoffice'), $label)
                );
            }
        }

        // Walidacja typu transakcji
        if (!empty($data['transaction_type'])) {
            $allowed_types = array('sprzedaz', 'kupno', 'wynajem', 'najem');
            if (!in_array($data['transaction_type'], $allowed_types)) {
                $errors->add(
                    'invalid_transaction_type',
                    __('Nieprawidłowy typ transakcji', 'estateoffice')
                );
            }
            $validated['transaction_type'] = $data['transaction_type'];
        }

        // Walidacja dat
        if (!empty($data['start_date'])) {
            if (!$this->validate_date($data['start_date'])) {
                $errors->add(
                    'invalid_start_date',
                    __('Nieprawidłowa data rozpoczęcia', 'estateoffice')
                );
            }
            $validated['start_date'] = $data['start_date'];
        }

        if (!empty($data['end_date'])) {
            if (!$this->validate_date($data['end_date'])) {
                $errors->add(
                    'invalid_end_date',
                    __('Nieprawidłowa data zakończenia', 'estateoffice')
                );
            }
            $validated['end_date'] = $data['end_date'];
        }

        // Pozostałe pola...
        $validated['agent_id'] = absint($data['agent_id']);
        $validated['property_id'] = !empty($data['property_id']) ? absint($data['property_id']) : null;
        $validated['is_indefinite'] = isset($data['is_indefinite']) ? 1 : 0;
        $validated['commission_amount'] = !empty($data['commission_amount']) ? 
            floatval($data['commission_amount']) : 0.00;
        $validated['commission_currency'] = !empty($data['commission_currency']) ? 
            sanitize_text_field($data['commission_currency']) : 'PLN';
        $validated['commission_type'] = !empty($data['commission_type']) ? 
            sanitize_text_field($data['commission_type']) : 'percentage';
        $validated['status'] = !empty($data['status']) ? 
            sanitize_text_field($data['status']) : 'active';

        if ($errors->has_errors()) {
            return $errors;
        }

        return $validated;
    }
	/**
     * Pobiera etapy umowy
     *
     * @param int $contract_id ID umowy
     * @return array Lista etapów
     */
    public function get_contract_stages($contract_id) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table_contract_stages} 
                WHERE contract_id = %d 
                ORDER BY date DESC",
                $contract_id
            )
        );
    }

    /**
     * Dodaje nowy etap umowy
     *
     * @param int $contract_id ID umowy
     * @param string $stage Nazwa etapu
     * @param string|null $notes Notatki
     * @return bool Status operacji
     */
    public function add_contract_stage($contract_id, $stage, $notes = null) {
        try {
            // Walidacja etapu
            if (!$this->validate_contract_stage($stage)) {
                throw new \Exception(__('Nieprawidłowy etap umowy', 'estateoffice'));
            }

            // Dodanie etapu
            $result = $this->db->insert(
                $this->table_contract_stages,
                array(
                    'contract_id' => $contract_id,
                    'stage' => $stage,
                    'date' => current_time('mysql'),
                    'notes' => $notes
                ),
                array('%d', '%s', '%s', '%s')
            );

            if ($result === false) {
                throw new \Exception(__('Błąd podczas dodawania etapu umowy', 'estateoffice'));
            }

            // Aktualizacja aktualnego etapu w umowie
            $this->db->update(
                $this->table_contracts,
                array('stage' => $stage),
                array('id' => $contract_id),
                array('%s'),
                array('%d')
            );

            return true;

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Contract Stage Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Waliduje etap umowy
     *
     * @param string $stage Nazwa etapu
     * @return bool Czy etap jest prawidłowy
     */
    private function validate_contract_stage($stage) {
        $allowed_stages = array(
            'umowa_posrednictwa',
            'publikacja_mls',
            'przygotowanie_oferty',
            'publikacja_oferty',
            'marketing_prezentacje',
            'oferta_kupna',
            'negocjacje',
            'umowa_przedwstepna',
            'umowa_przyrzeczona',
            'przekazanie_lokalu',
            'umowa_zakonczona'
        );

        return in_array($stage, $allowed_stages);
    }

    /**
     * Pobiera klientów powiązanych z umową
     *
     * @param int $contract_id ID umowy
     * @return array Lista klientów
     */
    public function get_contract_clients($contract_id) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT cc.*, c.* 
                FROM {$this->table_contract_clients} cc
                JOIN {$this->db->prefix}eo_clients c ON cc.client_id = c.id
                WHERE cc.contract_id = %d",
                $contract_id
            )
        );
    }

    /**
     * Dodaje powiązanie klienta z umową
     *
     * @param int $contract_id ID umowy
     * @param int $client_id ID klienta
     * @param string $role Rola klienta
     * @return bool Status operacji
     */
    public function add_contract_client($contract_id, $client_id, $role) {
        try {
            if (!$this->validate_client_role($role)) {
                throw new \Exception(__('Nieprawidłowa rola klienta', 'estateoffice'));
            }

            $result = $this->db->insert(
                $this->table_contract_clients,
                array(
                    'contract_id' => $contract_id,
                    'client_id' => $client_id,
                    'role' => $role
                ),
                array('%d', '%d', '%s')
            );

            if ($result === false) {
                throw new \Exception(__('Błąd podczas dodawania klienta do umowy', 'estateoffice'));
            }

            return true;

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Contract Client Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Aktualizuje powiązania klientów z umową
     *
     * @param int $contract_id ID umowy
     * @param array $clients Lista klientów
     * @return bool Status operacji
     */
    public function update_contract_clients($contract_id, $clients) {
        try {
            $this->db->query('START TRANSACTION');

            // Usunięcie obecnych powiązań
            $this->db->delete(
                $this->table_contract_clients,
                array('contract_id' => $contract_id),
                array('%d')
            );

            // Dodanie nowych powiązań
            foreach ($clients as $client) {
                $this->add_contract_client($contract_id, $client['client_id'], $client['role']);
            }

            $this->db->query('COMMIT');
            return true;

        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Contract Clients Update Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Waliduje rolę klienta
     *
     * @param string $role Rola do sprawdzenia
     * @return bool Czy rola jest prawidłowa
     */
    private function validate_client_role($role) {
        $allowed_roles = array('seller', 'buyer', 'landlord', 'tenant');
        return in_array($role, $allowed_roles);
    }

    /**
     * Generuje unikalny numer umowy
     *
     * @return string Numer umowy
     */
    private function generate_contract_number() {
        $prefix = date('Y/m/');
        $last_number = $this->db->get_var(
            $this->db->prepare(
                "SELECT contract_number 
                FROM {$this->table_contracts} 
                WHERE contract_number LIKE %s 
                ORDER BY id DESC 
                LIMIT 1",
                $this->db->esc_like($prefix) . '%'
            )
        );

        if ($last_number) {
            $number = intval(substr($last_number, -3));
            $number++;
        } else {
            $number = 1;
        }

        return $prefix . sprintf('%03d', $number);
    }

    /**
     * Waliduje format daty
     *
     * @param string $date Data do sprawdzenia
     * @return bool Czy data jest prawidłowa
     */
    private function validate_date($date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Wyszukuje umowy według kryteriów
     *
     * @param array $args Kryteria wyszukiwania
     * @return array Lista umów
     */
    public function search_contracts($args = array()) {
        $defaults = array(
            'transaction_type' => '',
            'agent_id' => 0,
            'property_id' => 0,
            'client_id' => 0,
            'stage' => '',
            'status' => 'active',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);
        $where = array('1=1');
        $params = array();

        // Budowanie zapytania
        if (!empty($args['transaction_type'])) {
            $where[] = 'transaction_type = %s';
            $params[] = $args['transaction_type'];
        }

        if (!empty($args['agent_id'])) {
            $where[] = 'agent_id = %d';
            $params[] = $args['agent_id'];
        }

        if (!empty($args['property_id'])) {
            $where[] = 'property_id = %d';
            $params[] = $args['property_id'];
        }

        if (!empty($args['stage'])) {
            $where[] = 'stage = %s';
            $params[] = $args['stage'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'start_date >= %s';
            $params[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'start_date <= %s';
            $params[] = $args['date_to'];
        }

        // Klient
        if (!empty($args['client_id'])) {
            $where[] = "id IN (
                SELECT contract_id 
                FROM {$this->table_contract_clients} 
                WHERE client_id = %d
            )";
            $params[] = $args['client_id'];
        }

        $query = "SELECT * FROM {$this->table_contracts} WHERE " . 
                 implode(' AND ', $where) . 
                 " ORDER BY {$args['orderby']} {$args['order']} " .
                 "LIMIT %d OFFSET %d";

        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $this->db->get_results(
            $this->db->prepare($query, $params)
        );
    }

    /**
     * Usuwa umowę
     *
     * @param int $contract_id ID umowy
     * @return bool Status operacji
     */
    public function delete_contract($contract_id) {
        try {
            $this->db->query('START TRANSACTION');

            // Usuwanie powiązań z klientami
            $this->db->delete(
                $this->table_contract_clients,
                array('contract_id' => $contract_id),
                array('%d')
            );

            // Usuwanie etapów
            $this->db->delete(
                $this->table_contract_stages,
                array('contract_id' => $contract_id),
                array('%d')
            );

            // Usuwanie umowy
            $result = $this->db->delete(
                $this->table_contracts,
                array('id' => $contract_id),
                array('%d')
            );

            if ($result === false) {
                throw new \Exception(__('Błąd podczas usuwania umowy', 'estateoffice'));
            }

            $this->db->query('COMMIT');
            return true;

        } catch (\Exception $e) {
            $this->db->query('ROLLBACK');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Contract Delete Error: ' . $e->getMessage());
            }
            return false;
        }
    }
}