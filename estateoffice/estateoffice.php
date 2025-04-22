<?php
/**
 * Plugin Name: EstateOffice
 * Plugin URI: http://warszawskiagent.pl
 * Description: Zaawansowana wtyczka CRM dla biur nieruchomości
 * Version: 0.5.5
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Author: Tomasz Obarski
 * Author URI: http://warszawskiagent.pl
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: estateoffice
 * Domain Path: /languages
 *
 * @package EstateOffice
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Definicje stałych wtyczki
define('ESTATEOFFICE_VERSION', '0.5.5');
define('ESTATEOFFICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ESTATEOFFICE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ESTATEOFFICE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ESTATEOFFICE_MIN_WP_VERSION', '6.7');
define('ESTATEOFFICE_MIN_PHP_VERSION', '8.0');
define('ESTATEOFFICE_TEXT_DOMAIN', 'estateoffice');

/**
 * Autoloader dla klas wtyczki
 */
spl_autoload_register(function ($class) {
    $prefix = 'EstateOffice\\';
    $base_dir = ESTATEOFFICE_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Sprawdzenie wymagań systemowych podczas aktywacji
 */
function estateoffice_activation_check() {
    $requirements_met = true;
    $error_messages = array();

    // Sprawdzenie wersji PHP
    if (version_compare(PHP_VERSION, ESTATEOFFICE_MIN_PHP_VERSION, '<')) {
        $requirements_met = false;
        $error_messages[] = sprintf(
            __('EstateOffice wymaga PHP w wersji %s lub wyższej. Aktualnie używasz wersji %s.', 'estateoffice'),
            ESTATEOFFICE_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Sprawdzenie wersji WordPress
    global $wp_version;
    if (version_compare($wp_version, ESTATEOFFICE_MIN_WP_VERSION, '<')) {
        $requirements_met = false;
        $error_messages[] = sprintf(
            __('EstateOffice wymaga WordPress w wersji %s lub wyższej. Aktualnie używasz wersji %s.', 'estateoffice'),
            ESTATEOFFICE_MIN_WP_VERSION,
            $wp_version
        );
    }

    // Jeśli wymagania nie są spełnione, zatrzymaj aktywację
    if (!$requirements_met) {
        deactivate_plugins(ESTATEOFFICE_PLUGIN_BASENAME);
        wp_die(implode('<br>', $error_messages));
    }
}
register_activation_hook(__FILE__, 'estateoffice_activation_check');

/**
 * Inicjalizacja wtyczki
 */
function estateoffice_init() {
    // Ładowanie plików językowych
    load_plugin_textdomain(
        ESTATEOFFICE_TEXT_DOMAIN,
        false,
        dirname(ESTATEOFFICE_PLUGIN_BASENAME) . '/languages/'
    );

    // Inicjalizacja głównych klas
    try {
        // Inicjalizacja rdzenia wtyczki
        $core = new EstateOffice\Core();
        $core->init();

        // Inicjalizacja panelu administracyjnego
        if (is_admin()) {
            $admin = new EstateOffice\Admin\Main();
            $admin->init();
        }

        // Inicjalizacja części frontendowej
        $frontend = new EstateOffice\Frontend\Main();
        $frontend->init();

    } catch (Exception $e) {
        // Logowanie błędów inicjalizacji
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EstateOffice initialization error: ' . $e->getMessage());
        }
    }
}
add_action('plugins_loaded', 'estateoffice_init');

/**
 * Akcje wykonywane podczas aktywacji wtyczki
 */
function estateoffice_activate() {
    // Sprawdzenie wymagań
    estateoffice_activation_check();

    // Inicjalizacja bazy danych
    try {
        $database = new EstateOffice\Database();
        $database->install();
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EstateOffice database installation error: ' . $e->getMessage());
        }
        wp_die(
            __('Wystąpił błąd podczas instalacji bazy danych wtyczki EstateOffice.', 'estateoffice'),
            __('Błąd instalacji', 'estateoffice'),
            array('back_link' => true)
        );
    }

    // Utworzenie ról i uprawnień
    estateoffice_setup_roles_and_capabilities();

    // Wyczyszczenie cache przepisywania (rewrite rules)
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'estateoffice_activate');

/**
 * Konfiguracja ról i uprawnień
 */
function estateoffice_setup_roles_and_capabilities() {
    // Utworzenie roli agenta
    add_role('eo_agent', __('Agent nieruchomości', 'estateoffice'), array(
        'read' => true,
        'edit_eo_properties' => true,
        'edit_published_eo_properties' => true,
        'publish_eo_properties' => true,
        'read_private_eo_properties' => true,
    ));

    // Dodanie uprawnień dla administratora
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_eo_settings');
        $admin->add_cap('edit_eo_properties');
        $admin->add_cap('edit_others_eo_properties');
        $admin->add_cap('publish_eo_properties');
        $admin->add_cap('read_private_eo_properties');
        $admin->add_cap('delete_eo_properties');
    }
}

/**
 * Akcje wykonywane podczas deaktywacji wtyczki
 */
function estateoffice_deactivate() {
    // Wyczyszczenie cache przepisywania
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'estateoffice_deactivate');

/**
 * Dodanie linków w sekcji wtyczek
 */
function estateoffice_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=estateoffice-settings') . '">' . 
        __('Ustawienia', 'estateoffice') . '</a>'
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . ESTATEOFFICE_PLUGIN_BASENAME, 'estateoffice_plugin_action_links');

// Importowanie dodatkowych plików pomocniczych jeśli są potrzebne
if (is_admin()) {
    require_once ESTATEOFFICE_PLUGIN_DIR . 'admin/class-admin-notices.php';
}