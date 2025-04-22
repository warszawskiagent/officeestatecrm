<?php
/**
 * Klasa zarządzająca bazą danych wtyczki EstateOffice
 *
 * @package EstateOffice
 * @subpackage Database
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

class Database {
    /**
     * Instancja wpdb
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Prefix tabel wtyczki
     *
     * @var string
     */
    private $table_prefix;

    /**
     * Lista wszystkich tabel wtyczki
     *
     * @var array
     */
    private $tables = array(
        'agents',
        'clients',
        'properties',
        'contracts',
        'contract_clients',
        'property_details',
        'property_media',
        'property_equipment',
        'contract_stages',
        'searches'
    );

    /**
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'eo_';
    }

    /**
     * Instalacja tabel w bazie danych
     *
     * @return void
     * @throws \Exception w przypadku błędu instalacji
     */
    public function install() {
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        $this->wpdb->hide_errors();
        $charset_collate = $this->wpdb->get_charset_collate();

        try {
            // Rozpoczęcie transakcji
            $this->wpdb->query('START TRANSACTION');

            // Tworzenie tabel
            $this->create_agents_table($charset_collate);
            $this->create_clients_table($charset_collate);
            $this->create_properties_table($charset_collate);
            $this->create_contracts_table($charset_collate);
            $this->create_relation_tables($charset_collate);
            $this->create_details_tables($charset_collate);

            // Zatwierdzenie transakcji
            $this->wpdb->query('COMMIT');

            // Aktualizacja wersji schematu w opcjach
            update_option('estateoffice_db_version', ESTATEOFFICE_VERSION);

        } catch (\Exception $e) {
            // Wycofanie zmian w przypadku błędu
            $this->wpdb->query('ROLLBACK');
            
            // Logowanie błędu
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EstateOffice Database Installation Error: ' . $e->getMessage());
            }
            
            throw new \Exception('Database installation failed: ' . $e->getMessage());
        }
    }

    /**
     * Sprawdzenie czy tabela istnieje
     *
     * @param string $table_name Nazwa tabeli bez prefixu
     * @return bool
     */
    public function table_exists($table_name) {
        $table = $this->get_table_name($table_name);
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        ) === $table;
    }

    /**
     * Pobranie pełnej nazwy tabeli
     *
     * @param string $table_name Nazwa tabeli bez prefixu
     * @return string
     */
    public function get_table_name($table_name) {
        return $this->table_prefix . $table_name;
    }

    /**
     * Sprawdzenie integralności bazy danych
     *
     * @return array Lista błędów lub pusta tablica jeśli wszystko jest ok
     */
    public function check_integrity() {
        $errors = array();

        foreach ($this->tables as $table) {
            if (!$this->table_exists($table)) {
                $errors[] = sprintf(
                    __('Brak tabeli %s w bazie danych.', 'estateoffice'),
                    $this->get_table_name($table)
                );
            }
        }

        return $errors;
    }
	/**
     * Tworzenie tabeli agentów
     *
     * @param string $charset_collate Kodowanie znaków
     * @throws \Exception
     */
    private function create_agents_table($charset_collate) {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('agents')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            phone varchar(20),
            mobile_phone varchar(20),
            description text,
            photo_url varchar(255),
            position varchar(100),
            languages varchar(255),
            office_id bigint(20),
            status enum('active', 'inactive', 'suspended') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY office_id (office_id)
        ) $charset_collate;";

        $this->execute_sql($sql);
    }

    /**
     * Tworzenie tabeli klientów
     *
     * @param string $charset_collate Kodowanie znaków
     * @throws \Exception
     */
    private function create_clients_table($charset_collate) {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('clients')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type enum('individual', 'company') NOT NULL,
            first_name varchar(100),
            last_name varchar(100),
            company_name varchar(255),
            representative_name varchar(255),
            pesel varchar(11),
            nip varchar(10),
            regon varchar(14),
            krs varchar(10),
            phone varchar(20),
            email varchar(100),
            website varchar(255),
            id_type enum('dowod', 'paszport', 'karta_pobytu'),
            id_number varchar(50),
            street varchar(255),
            street_number varchar(20),
            apartment_number varchar(20),
            postal_code varchar(10),
            city varchar(100),
            country varchar(100),
            correspondence_address text,
            agent_id bigint(20),
            status enum('active', 'inactive', 'archived') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY agent_id (agent_id),
            KEY email (email),
            KEY phone (phone)
        ) $charset_collate;";

        $this->execute_sql($sql);
    }

    /**
     * Tworzenie tabeli nieruchomości
     *
     * @param string $charset_collate Kodowanie znaków
     * @throws \Exception
     */
    private function create_properties_table($charset_collate) {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('properties')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            property_type enum('mieszkanie', 'dom', 'dzialka', 'lokal') NOT NULL,
            transaction_type enum('sprzedaz', 'wynajem') NOT NULL,
            price decimal(12,2),
            price_currency varchar(3) DEFAULT 'PLN',
            area decimal(10,2),
            rooms smallint,
            floor varchar(20),
            total_floors smallint,
            year_built year,
            street varchar(255),
            street_number varchar(20),
            apartment_number varchar(20),
            postal_code varchar(10),
            city varchar(100),
            district varchar(100),
            province varchar(100),
            land_area decimal(10,2),
            land_type varchar(100),
            property_status enum('available', 'reserved', 'sold', 'rented', 'inactive') DEFAULT 'available',
            agent_id bigint(20),
            kw_number varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY agent_id (agent_id),
            KEY property_status (property_status)
        ) $charset_collate;";

        $this->execute_sql($sql);
    }

    /**
     * Tworzenie tabeli umów
     *
     * @param string $charset_collate Kodowanie znaków
     * @throws \Exception
     */
    private function create_contracts_table($charset_collate) {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('contracts')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contract_number varchar(50) NOT NULL,
            transaction_type enum('sprzedaz', 'kupno', 'wynajem', 'najem') NOT NULL,
            agent_id bigint(20) NOT NULL,
            property_id bigint(20),
            start_date date NOT NULL,
            end_date date,
            is_indefinite tinyint(1) DEFAULT 0,
            commission_amount decimal(10,2),
            commission_currency varchar(3) DEFAULT 'PLN',
            commission_type enum('percentage', 'fixed') DEFAULT 'percentage',
            stage varchar(50) DEFAULT 'umowa_posrednictwa',
            status enum('active', 'completed', 'terminated', 'expired') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY contract_number (contract_number),
            KEY agent_id (agent_id),
            KEY property_id (property_id)
        ) $charset_collate;";

        $this->execute_sql($sql);
    }

    /**
     * Tworzenie tabel relacyjnych
     *
     * @param string $charset_collate Kodowanie znaków
     * @throws \Exception
     */
    private function create_relation_tables($charset_collate) {
        // Tabela powiązań umów z klientami
        $sql_contract_clients = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('contract_clients')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) NOT NULL,
            client_id bigint(20) NOT NULL,
            role enum('seller', 'buyer', 'landlord', 'tenant') NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY contract_client (contract_id, client_id),
            KEY client_id (client_id)
        ) $charset_collate;";

        $this->execute_sql($sql_contract_clients);

        // Tabela historii etapów umowy
        $sql_contract_stages = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('contract_stages')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) NOT NULL,
            stage varchar(50) NOT NULL,
            date datetime NOT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contract_id (contract_id)
        ) $charset_collate;";

        $this->execute_sql($sql_contract_stages);
    }

    /**
     * Wykonanie zapytania SQL z obsługą błędów
     *
     * @param string $sql Zapytanie SQL
     * @throws \Exception
     */
    private function execute_sql($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result = dbDelta($sql);
        
        if (!empty($this->wpdb->last_error)) {
            throw new \Exception(
                sprintf(
                    'SQL Error: %s for query: %s',
                    $this->wpdb->last_error,
                    $sql
                )
            );
        }
        
        return $result;
    }
	/**
     * Tworzenie tabel szczegółowych
     *
     * @param string $charset_collate Kodowanie znaków
     * @throws \Exception
     */
    private function create_details_tables($charset_collate) {
        // Tabela szczegółów nieruchomości
        $sql_property_details = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('property_details')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            finish_condition varchar(100),
            kitchen_type enum('aneks', 'oddzielna', 'z_salonem'),
            heating_type varchar(100),
            parking_spots_count smallint DEFAULT 0,
            garage_type varchar(100),
            balcony_count smallint DEFAULT 0,
            balcony_area decimal(10,2),
            terrace_count smallint DEFAULT 0,
            terrace_area decimal(10,2),
            basement_area decimal(10,2),
            storage_area decimal(10,2),
            garden_area decimal(10,2),
            has_elevator tinyint(1) DEFAULT 0,
            has_furniture tinyint(1) DEFAULT 0,
            has_ac tinyint(1) DEFAULT 0,
            has_security tinyint(1) DEFAULT 0,
            has_reception tinyint(1) DEFAULT 0,
            has_closed_territory tinyint(1) DEFAULT 0,
            has_intercom tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY property_id (property_id)
        ) $charset_collate;";

        $this->execute_sql($sql_property_details);

        // Tabela mediów nieruchomości
        $sql_property_media = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('property_media')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            type enum('photo', 'floor_plan_2d', 'floor_plan_3d', 'video', 'virtual_tour') NOT NULL,
            url varchar(255) NOT NULL,
            title varchar(255),
            description text,
            sort_order int DEFAULT 0,
            is_main tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id)
        ) $charset_collate;";

        $this->execute_sql($sql_property_media);

        // Tabela wyposażenia nieruchomości
        $sql_property_equipment = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('property_equipment')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            property_id bigint(20) NOT NULL,
            equipment_type varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY property_equipment (property_id, equipment_type)
        ) $charset_collate;";

        $this->execute_sql($sql_property_equipment);

        // Tabela wyszukiwań
        $sql_searches = "CREATE TABLE IF NOT EXISTS {$this->get_table_name('searches')} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            transaction_type enum('sprzedaz', 'kupno', 'wynajem', 'najem') NOT NULL,
            property_type varchar(100),
            price_min decimal(12,2),
            price_max decimal(12,2),
            area_min decimal(10,2),
            area_max decimal(10,2),
            rooms_min smallint,
            rooms_max smallint,
            location text,
            description text,
            criteria text,
            status enum('active', 'inactive', 'matched') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset_collate;";

        $this->execute_sql($sql_searches);
    }

    /**
     * Bezpieczne wstawianie rekordu do tabeli
     *
     * @param string $table_name Nazwa tabeli bez prefixu
     * @param array $data Dane do wstawienia
     * @return int|false ID wstawionego rekordu lub false w przypadku błędu
     */
    public function insert($table_name, $data) {
        try {
            $table = $this->get_table_name($table_name);
            $data = $this->sanitize_data($data);
            
            $result = $this->wpdb->insert($table, $data);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $this->wpdb->insert_id;
        } catch (\Exception $e) {
            $this->log_error('Insert Error', $e->getMessage(), $data);
            return false;
        }
    }

    /**
     * Bezpieczna aktualizacja rekordu w tabeli
     *
     * @param string $table_name Nazwa tabeli bez prefixu
     * @param array $data Dane do aktualizacji
     * @param array $where Warunki WHERE
     * @return int|false Liczba zaktualizowanych wierszy lub false w przypadku błędu
     */
    public function update($table_name, $data, $where) {
        try {
            $table = $this->get_table_name($table_name);
            $data = $this->sanitize_data($data);
            $where = $this->sanitize_data($where);
            
            $result = $this->wpdb->update($table, $data, $where);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->log_error('Update Error', $e->getMessage(), ['data' => $data, 'where' => $where]);
            return false;
        }
    }

    /**
     * Bezpieczne usuwanie rekordu z tabeli
     *
     * @param string $table_name Nazwa tabeli bez prefixu
     * @param array $where Warunki WHERE
     * @return int|false Liczba usuniętych wierszy lub false w przypadku błędu
     */
    public function delete($table_name, $where) {
        try {
            $table = $this->get_table_name($table_name);
            $where = $this->sanitize_data($where);
            
            $result = $this->wpdb->delete($table, $where);
            
            if ($result === false) {
                throw new \Exception($this->wpdb->last_error);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->log_error('Delete Error', $e->getMessage(), $where);
            return false;
        }
    }

    /**
     * Sanityzacja danych przed zapisem do bazy
     *
     * @param array $data Dane do sanityzacji
     * @return array Sanityzowane dane
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_data($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Logowanie błędów bazy danych
     *
     * @param string $type Typ błędu
     * @param string $message Wiadomość błędu
     * @param mixed $context Dodatkowy kontekst
     */
    private function log_error($type, $message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'EstateOffice Database Error [%s]: %s | Context: %s',
                $type,
                $message,
                print_r($context, true)
            ));
        }
    }

    /**
     * Czyszczenie tabel wtyczki
     *
     * @return bool
     */
    public function clean_tables() {
        try {
            foreach ($this->tables as $table) {
                $this->wpdb->query("TRUNCATE TABLE {$this->get_table_name($table)}");
            }
            return true;
        } catch (\Exception $e) {
            $this->log_error('Clean Tables Error', $e->getMessage());
            return false;
        }
    }
}