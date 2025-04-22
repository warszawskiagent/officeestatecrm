<?php
/**
 * Template dla strony zarządzania polami w ustawieniach
 *
 * @package EstateOffice
 * @subpackage Admin
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('manage_options')) {
    wp_die(__('Nie masz wystarczających uprawnień do dostępu do tej strony.', 'estateoffice'));
}

// Pobranie aktualnych ustawień pól
$property_fields = get_option('eo_property_fields', array());
$contract_fields = get_option('eo_contract_fields', array());
$client_fields = get_option('eo_client_fields', array());

// Obsługa zapisywania formularza
if (isset($_POST['eo_save_fields']) && check_admin_referer('eo_save_fields_nonce')) {
    // Sanityzacja i zapisywanie pól nieruchomości
    if (isset($_POST['property_fields'])) {
        $new_property_fields = array_map('sanitize_text_field', $_POST['property_fields']);
        update_option('eo_property_fields', $new_property_fields);
    }
    
    // Sanityzacja i zapisywanie pól umów
    if (isset($_POST['contract_fields'])) {
        $new_contract_fields = array_map('sanitize_text_field', $_POST['contract_fields']);
        update_option('eo_contract_fields', $new_contract_fields);
    }
    
    // Sanityzacja i zapisywanie pól klientów
    if (isset($_POST['client_fields'])) {
        $new_client_fields = array_map('sanitize_text_field', $_POST['client_fields']);
        update_option('eo_client_fields', $new_client_fields);
    }
    
    // Komunikat o sukcesie
    add_settings_error(
        'eo_fields_messages',
        'eo_fields_updated',
        __('Ustawienia pól zostały zaktualizowane.', 'estateoffice'),
        'updated'
    );
}

// Wyświetlenie komunikatów
settings_errors('eo_fields_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="" id="eo-fields-form">
        <?php wp_nonce_field('eo_save_fields_nonce'); ?>
        
        <div class="nav-tab-wrapper">
            <a href="#property-fields" class="nav-tab nav-tab-active"><?php _e('Pola nieruchomości', 'estateoffice'); ?></a>
            <a href="#contract-fields" class="nav-tab"><?php _e('Pola umów', 'estateoffice'); ?></a>
            <a href="#client-fields" class="nav-tab"><?php _e('Pola klientów', 'estateoffice'); ?></a>
        </div>
        
        <!-- Sekcja pól nieruchomości -->
        <div id="property-fields" class="tab-content active">
            <h2><?php _e('Zarządzanie polami nieruchomości', 'estateoffice'); ?></h2>
            <table class="widefat" id="property-fields-table">
                <thead>
                    <tr>
                        <th><?php _e('Nazwa pola', 'estateoffice'); ?></th>
                        <th><?php _e('Typ pola', 'estateoffice'); ?></th>
                        <th><?php _e('Wymagane', 'estateoffice'); ?></th>
                        <th><?php _e('Akcje', 'estateoffice'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($property_fields as $field): ?>
                    <tr>
                        <td>
                            <input type="text" 
                                   name="property_fields[<?php echo esc_attr($field['id']); ?>][name]" 
                                   value="<?php echo esc_attr($field['name']); ?>" 
                                   class="regular-text">
                        </td>
                        <td>
                            <select name="property_fields[<?php echo esc_attr($field['id']); ?>][type]">
                                <option value="text" <?php selected($field['type'], 'text'); ?>><?php _e('Tekst', 'estateoffice'); ?></option>
                                <option value="number" <?php selected($field['type'], 'number'); ?>><?php _e('Liczba', 'estateoffice'); ?></option>
                                <option value="select" <?php selected($field['type'], 'select'); ?>><?php _e('Lista wyboru', 'estateoffice'); ?></option>
                                <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>><?php _e('Pole wyboru', 'estateoffice'); ?></option>
                            </select>
                        </td>
                        <td>
                            <input type="checkbox" 
                                   name="property_fields[<?php echo esc_attr($field['id']); ?>][required]" 
                                   <?php checked($field['required'], true); ?>>
                        </td>
                        <td>
                            <button type="button" class="button remove-field"><?php _e('Usuń', 'estateoffice'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="button add-field" data-target="property-fields-table">
                                <?php _e('Dodaj nowe pole', 'estateoffice'); ?>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Sekcja pól umów -->
        <div id="contract-fields" class="tab-content" style="display: none;">
            <h2><?php _e('Zarządzanie polami umów', 'estateoffice'); ?></h2>
            <table class="widefat" id="contract-fields-table">
                <!-- Analogiczna struktura jak dla pól nieruchomości -->
            </table>
        </div>
        
        <!-- Sekcja pól klientów -->
        <div id="client-fields" class="tab-content" style="display: none;">
            <h2><?php _e('Zarządzanie polami klientów', 'estateoffice'); ?></h2>
            <table class="widefat" id="client-fields-table">
                <!-- Analogiczna struktura jak dla pól nieruchomości -->
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" 
                   name="eo_save_fields" 
                   id="submit" 
                   class="button button-primary" 
                   value="<?php _e('Zapisz zmiany', 'estateoffice'); ?>">
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Obsługa zakładek
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        // Aktualizacja klas zakładek
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Pokazanie właściwej zawartości
        $('.tab-content').hide();
        $(target).show();
    });
    
    // Dodawanie nowego pola
    $('.add-field').on('click', function() {
        var tableId = $(this).data('target');
        var tbody = $('#' + tableId + ' tbody');
        var newRow = `
            <tr>
                <td><input type="text" name="new_field[name][]" class="regular-text"></td>
                <td>
                    <select name="new_field[type][]">
                        <option value="text"><?php _e('Tekst', 'estateoffice'); ?></option>
                        <option value="number"><?php _e('Liczba', 'estateoffice'); ?></option>
                        <option value="select"><?php _e('Lista wyboru', 'estateoffice'); ?></option>
                        <option value="checkbox"><?php _e('Pole wyboru', 'estateoffice'); ?></option>
                    </select>
                </td>
                <td><input type="checkbox" name="new_field[required][]"></td>
                <td><button type="button" class="button remove-field"><?php _e('Usuń', 'estateoffice'); ?></button></td>
            </tr>
        `;
        tbody.append(newRow);
    });
    
    // Usuwanie pola
    $(document).on('click', '.remove-field', function() {
        if (confirm('<?php _e('Czy na pewno chcesz usunąć to pole?', 'estateoffice'); ?>')) {
            $(this).closest('tr').remove();
        }
    });
});
</script>