<?php
/**
 * Klasa zarządzająca agentami w panelu administracyjnym
 *
 * @package EstateOffice
 * @subpackage Admin
 * @since 0.5.5
 * @author Tomasz Obarski
 * @link http://warszawskiagent.pl
 */

namespace EstateOffice\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminAgents {
    /**
     * @var string
     */
    private $capability = 'manage_eo_agents';

    /**
     * @var string
     */
    private $menu_slug = 'estateoffice-agents';

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Akcje menu
        add_action('admin_menu', array($this, 'add_agents_menu'));
        
        // Akcje AJAX
        add_action('wp_ajax_eo_add_agent', array($this, 'ajax_add_agent'));
        add_action('wp_ajax_eo_edit_agent', array($this, 'ajax_edit_agent'));
        add_action('wp_ajax_eo_delete_agent', array($this, 'ajax_delete_agent'));
        add_action('wp_ajax_eo_get_agent', array($this, 'ajax_get_agent'));
        
        // Akcje dla uploadów
        add_action('admin_post_eo_upload_agent_photo', array($this, 'handle_photo_upload'));
        
        // Filtry
        add_filter('user_row_actions', array($this, 'modify_user_actions'), 10, 2);
    }

    /**
     * Dodaje podmenu dla agentów w menu EstateOffice
     */
    public function add_agents_menu() {
        add_submenu_page(
            'estateoffice',
            __('Agenci', 'estateoffice'),
            __('Agenci', 'estateoffice'),
            $this->capability,
            $this->menu_slug,
            array($this, 'render_agents_page')
        );
    }

    /**
     * Renderuje główną stronę zarządzania agentami
     */
    public function render_agents_page() {
        if (!current_user_can($this->capability)) {
            wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
        }

        // Pobierz listę agentów
        $agents = $this->get_agents();

        // Załaduj template
        include ESTATEOFFICE_PLUGIN_DIR . 'admin/partials/agents-list.php';
    }

    /**
     * Pobiera listę wszystkich agentów
     *
     * @return array
     */
    private function get_agents() {
        $table_name = $this->wpdb->prefix . 'eo_agents';
        
        $query = "
            SELECT a.*, u.display_name, u.user_email
            FROM {$table_name} a
            LEFT JOIN {$this->wpdb->users} u ON a.user_id = u.ID
            ORDER BY u.display_name ASC
        ";

        return $this->wpdb->get_results($query);
    }

    /**
     * Obsługuje dodawanie nowego agenta przez AJAX
     */
    public function ajax_add_agent() {
        check_ajax_referer('eo_admin_nonce', 'nonce');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Brak uprawnień', 'estateoffice'));
        }

        $user_data = array(
            'user_login' => sanitize_user($_POST['username']),
            'user_pass' => wp_generate_password(),
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'role' => 'eo_agent'
        );

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        $agent_data = array(
            'user_id' => $user_id,
            'phone' => sanitize_text_field($_POST['phone']),
            'description' => wp_kses_post($_POST['description'])
        );

        $result = $this->insert_agent($agent_data);

        if ($result) {
            // Wyślij email z hasłem
            wp_new_user_notification($user_id, null, 'both');
            wp_send_json_success(__('Agent został dodany pomyślnie', 'estateoffice'));
        } else {
            wp_delete_user($user_id); // Cofnij utworzenie użytkownika
            wp_send_json_error(__('Wystąpił błąd podczas dodawania agenta', 'estateoffice'));
        }
    }

    /**
     * Wstawia nowego agenta do bazy danych
     *
     * @param array $data Dane agenta
     * @return bool|int
     */
    private function insert_agent($data) {
        $table_name = $this->wpdb->prefix . 'eo_agents';
        
        $result = $this->wpdb->insert(
            $table_name,
            array(
                'user_id' => $data['user_id'],
                'phone' => $data['phone'],
                'description' => $data['description'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Obsługuje edycję agenta przez AJAX
     */
    public function ajax_edit_agent() {
        check_ajax_referer('eo_admin_nonce', 'nonce');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Brak uprawnień', 'estateoffice'));
        }

        $agent_id = intval($_POST['agent_id']);
        $agent = $this->get_agent($agent_id);

        if (!$agent) {
            wp_send_json_error(__('Agent nie został znaleziony', 'estateoffice'));
        }

        // Aktualizuj dane użytkownika
        $user_data = array(
            'ID' => $agent->user_id,
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name'])
        );

        $user_update = wp_update_user($user_data);

        if (is_wp_error($user_update)) {
            wp_send_json_error($user_update->get_error_message());
        }

        // Aktualizuj dane agenta
        $result = $this->update_agent($agent_id, array(
            'phone' => sanitize_text_field($_POST['phone']),
            'description' => wp_kses_post($_POST['description'])
        ));

        if ($result) {
            wp_send_json_success(__('Dane agenta zostały zaktualizowane', 'estateoffice'));
        } else {
            wp_send_json_error(__('Wystąpił błąd podczas aktualizacji danych', 'estateoffice'));
        }
    }

    /**
     * Aktualizuje dane agenta
     *
     * @param int $agent_id ID agenta
     * @param array $data Dane do aktualizacji
     * @return bool
     */
    private function update_agent($agent_id, $data) {
        $table_name = $this->wpdb->prefix . 'eo_agents';
        
        $data['updated_at'] = current_time('mysql');

        return $this->wpdb->update(
            $table_name,
            $data,
            array('id' => $agent_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Obsługuje usuwanie agenta przez AJAX
     */
    public function ajax_delete_agent() {
        check_ajax_referer('eo_admin_nonce', 'nonce');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Brak uprawnień', 'estateoffice'));
        }

        $agent_id = intval($_POST['agent_id']);
        $agent = $this->get_agent($agent_id);

        if (!$agent) {
            wp_send_json_error(__('Agent nie został znaleziony', 'estateoffice'));
        }

        // Sprawdź czy agent ma aktywne umowy
        if ($this->agent_has_active_contracts($agent->user_id)) {
            wp_send_json_error(__('Nie można usunąć agenta z aktywnymi umowami', 'estateoffice'));
        }

        // Usuń agenta i użytkownika
        $this->delete_agent($agent_id);
        wp_delete_user($agent->user_id);

        wp_send_json_success(__('Agent został usunięty', 'estateoffice'));
    }

    /**
     * Sprawdza czy agent ma aktywne umowy
     *
     * @param int $user_id ID użytkownika
     * @return bool
     */
    private function agent_has_active_contracts($user_id) {
        $table_name = $this->wpdb->prefix . 'eo_contracts';
        
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE agent_id = %d AND status = 'active'",
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Usuwa agenta z bazy danych
     *
     * @param int $agent_id ID agenta
     * @return bool
     */
    private function delete_agent($agent_id) {
        $table_name = $this->wpdb->prefix . 'eo_agents';
        
        return $this->wpdb->delete(
            $table_name,
            array('id' => $agent_id),
            array('%d')
        );
    }

    /**
     * Pobiera dane agenta
     *
     * @param int $agent_id ID agenta
     * @return object|null
     */
    private function get_agent($agent_id) {
        $table_name = $this->wpdb->prefix . 'eo_agents';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT a.*, u.display_name, u.user_email, u.first_name, u.last_name
            FROM {$table_name} a
            LEFT JOIN {$this->wpdb->users} u ON a.user_id = u.ID
            WHERE a.id = %d",
            $agent_id
        ));
    }

    /**
     * Obsługuje upload zdjęcia agenta
     */
    public function handle_photo_upload() {
        check_admin_referer('eo_upload_agent_photo', 'nonce');
        
        if (!current_user_can($this->capability)) {
            wp_die(__('Brak uprawnień', 'estateoffice'));
        }

        if (!isset($_FILES['agent_photo'])) {
            wp_die(__('Nie przesłano pliku', 'estateoffice'));
        }

        $agent_id = intval($_POST['agent_id']);
        $agent = $this->get_agent($agent_id);

        if (!$agent) {
            wp_die(__('Agent nie został znaleziony', 'estateoffice'));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('agent_photo', 0);

        if (is_wp_error($attachment_id)) {
            wp_die($attachment_id->get_error_message());
        }

        // Usuń stare zdjęcie jeśli istnieje
        if (!empty($agent->photo_url)) {
            $old_attachment_id = attachment_url_to_postid($agent->photo_url);
            if ($old_attachment_id) {
                wp_delete_attachment($old_attachment_id, true);
            }
        }

        // Zaktualizuj URL zdjęcia w bazie
        $this->update_agent($agent_id, array(
            'photo_url' => wp_get_attachment_url($attachment_id)
        ));

        wp_redirect(admin_url('admin.php?page=' . $this->menu_slug . '&updated=true'));
        exit;
    }

    /**
     * Modyfikuje akcje na liście użytkowników
     *
     * @param array $actions Tablica akcji
     * @param WP_User $user Obiekt użytkownika
     * @return array
     */
    public function modify_user_actions($actions, $user) {
        if (in_array('eo_agent', $user->roles)) {
            $actions['edit_agent'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=' . $this->menu_slug . '&action=edit&agent_id=' . $this->get_agent_id_by_user($user->ID)),
                __('Edytuj profil agenta', 'estateoffice')
            );
        }
        return $actions;
    }

    /**
     * Pobiera ID agenta na podstawie ID użytkownika
     *
     * @param int $user_id ID użytkownika
     * @return int|null
     */
    private function get_agent_id_by_user($user_id) {
        $table_name = $this->wpdb->prefix . 'eo_agents';
        
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Pobiera statystyki agenta
     *
     * @param int $agent_id ID agenta
     * @return array
     */
    public function get_agent_stats($agent_id) {
        $agent = $this->get_agent($agent_id);
        if (!$agent) {
            return array();
        }

        $contracts_table = $this->wpdb->prefix . 'eo_contracts';
        $properties_table = $this->wpdb->prefix . 'eo_properties';

        // Aktywne umowy
        $active_contracts = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$contracts_table} 
            WHERE agent_id = %d AND status = 'active'",
            $agent->user_id
        ));

        // Zakończone umowy
        $completed_contracts = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$contracts_table} 
            WHERE agent_id = %d AND status = 'completed'",
            $agent->user_id
        ));

        // Aktywne oferty
        $active_properties = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$properties_table} 
            WHERE agent_id = %d AND status = 'active'",
            $agent->user_id
        ));

        // Suma prowizji
        $total_commission = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$contracts_table} 
            WHERE agent_id = %d AND status = 'completed'",
            $agent->user_id
        ));

        return array(
            'active_contracts' => (int)$active_contracts,
            'completed_contracts' => (int)$completed_contracts,
            'active_properties' => (int)$active_properties,
            'total_commission' => (float)$total_commission
        );
    }

    /**
     * Resetuje hasło agenta i wysyła je mailem
     *
     * @param int $agent_id ID agenta
     * @return bool
     */
    public function reset_agent_password($agent_id) {
        $agent = $this->get_agent($agent_id);
        if (!$agent) {
            return false;
        }

        $new_password = wp_generate_password(12, true, true);
        
        $result = wp_update_user(array(
            'ID' => $agent->user_id,
            'user_pass' => $new_password
        ));

        if (is_wp_error($result)) {
            return false;
        }

        // Wyślij email z nowym hasłem
        $to = $agent->user_email;
        $subject = __('Nowe hasło do konta agenta', 'estateoffice');
        $message = sprintf(
            __('Twoje nowe hasło to: %s', 'estateoffice'),
            $new_password
        );
        
        return wp_mail($to, $subject, $message);
    }

    /**
     * Sprawdza czy użytkownik jest agentem
     *
     * @param int $user_id ID użytkownika
     * @return bool
     */
    public static function is_agent($user_id) {
        $user = get_user_by('id', $user_id);
        return $user && in_array('eo_agent', $user->roles);
    }

    /**
     * Waliduje dane agenta
     *
     * @param array $data Dane do walidacji
     * @return array|WP_Error
     */
    private function validate_agent_data($data) {
        $errors = new \WP_Error();

        // Sprawdź wymagane pola
        $required_fields = array(
            'username' => __('Nazwa użytkownika', 'estateoffice'),
            'email' => __('Email', 'estateoffice'),
            'first_name' => __('Imię', 'estateoffice'),
            'last_name' => __('Nazwisko', 'estateoffice'),
            'phone' => __('Telefon', 'estateoffice')
        );

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors->add(
                    'required_' . $field,
                    sprintf(__('Pole %s jest wymagane', 'estateoffice'), $label)
                );
            }
        }

        // Walidacja emaila
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors->add(
                'invalid_email',
                __('Podany adres email jest nieprawidłowy', 'estateoffice')
            );
        }

        // Walidacja telefonu
        if (!empty($data['phone']) && !preg_match('/^[0-9\+\-\(\)\s]{9,20}$/', $data['phone'])) {
            $errors->add(
                'invalid_phone',
                __('Podany numer telefonu jest nieprawidłowy', 'estateoffice')
            );
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return $data;
    }
}