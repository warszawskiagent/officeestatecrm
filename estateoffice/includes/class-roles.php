<?php
/**
 * Klasa zarządzająca rolami i uprawnieniami wtyczki EstateOffice
 *
 * @package EstateOffice
 * @subpackage Roles
 * @since 0.5.5
 * @author Tomasz Obarski
 * @link http://warszawskiagent.pl
 */

namespace EstateOffice;

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

class Roles {
    /**
     * Lista własnych uprawnień wtyczki
     *
     * @var array
     */
    private $capabilities = array(
        // Nieruchomości
        'read_eo_property',
        'read_private_eo_properties',
        'edit_eo_property',
        'edit_eo_properties',
        'edit_private_eo_properties',
        'edit_published_eo_properties',
        'publish_eo_properties',
        'delete_eo_property',
        'delete_eo_properties',
        
        // Umowy
        'read_eo_contract',
        'read_private_eo_contracts',
        'edit_eo_contract',
        'edit_eo_contracts',
        'edit_private_eo_contracts',
        'edit_published_eo_contracts',
        'publish_eo_contracts',
        'delete_eo_contract',
        'delete_eo_contracts',
        
        // Klienci
        'read_eo_client',
        'read_private_eo_clients',
        'edit_eo_client',
        'edit_eo_clients',
        'edit_private_eo_clients',
        'edit_published_eo_clients',
        'publish_eo_clients',
        'delete_eo_client',
        'delete_eo_clients',
        
        // Ustawienia
        'manage_eo_settings'
    );

    /**
     * Inicjalizacja systemu ról
     */
    public function init() {
        $this->add_roles_and_capabilities();
    }

    /**
     * Dodawanie ról i uprawnień
     */
    public function add_roles_and_capabilities() {
        // Dodanie roli agenta
        add_role(
            'eo_agent',
            __('Agent nieruchomości', 'estateoffice'),
            array(
                // WordPress core capabilities
                'read' => true,
                'upload_files' => true,
                
                // EstateOffice capabilities - tylko odczyt i edycja
                'read_eo_property' => true,
                'read_private_eo_properties' => true,
                'edit_eo_property' => true,
                'edit_eo_properties' => true,
                'edit_private_eo_properties' => true,
                'edit_published_eo_properties' => true,
                'publish_eo_properties' => true,
                
                'read_eo_contract' => true,
                'read_private_eo_contracts' => true,
                'edit_eo_contract' => true,
                'edit_eo_contracts' => true,
                'edit_private_eo_contracts' => true,
                'edit_published_eo_contracts' => true,
                'publish_eo_contracts' => true,
                
                'read_eo_client' => true,
                'read_private_eo_clients' => true,
                'edit_eo_client' => true,
                'edit_eo_clients' => true,
                'edit_private_eo_clients' => true,
                'edit_published_eo_clients' => true,
                'publish_eo_clients' => true
            )
        );

        // Dodanie uprawnień dla administratora
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($this->capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    /**
     * Usuwanie ról i uprawnień przy deaktywacji
     */
    public static function remove_roles_and_capabilities() {
        // Usunięcie roli agenta
        remove_role('eo_agent');

        // Usunięcie uprawnień administratora
        $admin = get_role('administrator');
        if ($admin) {
            $roles = new self();
            foreach ($roles->capabilities as $cap) {
                $admin->remove_cap($cap);
            }
        }
    }
}