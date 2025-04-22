<?php
/**
 * Plik odpowiedzialny za czyszczenie danych po odinstalowaniu wtyczki EstateOffice
 *
 * Ten plik jest wywoływany automatycznie przez WordPress podczas procesu odinstalowywania wtyczki
 * (nie podczas deaktywacji). Usuwa wszystkie dane utworzone przez wtyczkę z bazy danych
 * oraz zapisane pliki, jeśli użytkownik wybrał taką opcję w ustawieniach.
 *
 * @package EstateOffice
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access not allowed.');
}

// Pobranie opcji dotyczącej usuwania danych
$delete_data = get_option('eo_delete_data_on_uninstall', false);

if ($delete_data) {
    global $wpdb;
    
    // Rozpoczęcie transakcji
    $wpdb->query('START TRANSACTION');
    
    try {
        // Lista tabel do usunięcia
        $tables = array(
            'eo_agents',
            'eo_clients',
            'eo_properties',
            'eo_contracts',
            'eo_contract_clients',
            'eo_property_details',
            'eo_property_media',
            'eo_property_equipment',
            'eo_contract_stages',
            'eo_searches'
        );

        // Usunięcie tabel wtyczki
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        // Usunięcie wszystkich postów typu 'eo_property'
        $wpdb->query(
            $wpdb->prepare(
                "DELETE posts, pm, tr
                FROM {$wpdb->posts} posts
                LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = posts.ID
                LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = posts.ID
                WHERE posts.post_type = %s",
                'eo_property'
            )
        );

        // Usunięcie taksonomii
        $taxonomies = array(
            'eo_transaction_type',
            'eo_property_type',
            'eo_city',
            'eo_district'
        );

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ));

            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    wp_delete_term($term->term_id, $taxonomy);
                }
            }
        }

        // Usunięcie wszystkich opcji wtyczki
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'eo_%'
            )
        );

        // Usunięcie roli agenta
        remove_role('eo_agent');

        // Usunięcie uprawnień z roli administratora
        $admin = get_role('administrator');
        if ($admin) {
            $capabilities = array(
                'manage_eo_settings',
                'edit_eo_properties',
                'edit_others_eo_properties',
                'publish_eo_properties',
                'read_private_eo_properties',
                'delete_eo_properties'
            );
            
            foreach ($capabilities as $cap) {
                $admin->remove_cap($cap);
            }
        }

        // Usunięcie plików mediów
        $upload_dir = wp_upload_dir();
        $eo_upload_dir = $upload_dir['basedir'] . '/estateoffice';
        
        if (is_dir($eo_upload_dir)) {
            // Rekursywne usuwanie katalogu z plikami
            estateoffice_recursive_rmdir($eo_upload_dir);
        }

        // Usunięcie wpisów z dziennika debugowania
        $debug_log = WP_CONTENT_DIR . '/debug/logs/estateoffice-error.log';
        if (file_exists($debug_log)) {
            @unlink($debug_log);
        }

        // Zatwierdzenie transakcji
        $wpdb->query('COMMIT');

    } catch (Exception $e) {
        // W przypadku błędu, cofnięcie zmian
        $wpdb->query('ROLLBACK');

        // Logowanie błędu jeśli włączone jest debugowanie
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EstateOffice uninstall error: ' . $e->getMessage());
        }
    }
}

/**
 * Rekursywne usuwanie katalogu i jego zawartości
 *
 * @param string $dir Ścieżka do katalogu
 * @return bool
 */
function estateoffice_recursive_rmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            estateoffice_recursive_rmdir($path);
        } else {
            @unlink($path);
        }
    }
    
    return @rmdir($dir);
}