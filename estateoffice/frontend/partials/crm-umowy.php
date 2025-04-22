<?php
/**
 * Szablon dla widoku umów w CRM
 *
 * @package EstateOffice
 * @subpackage Frontend
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('read_eo_contracts')) {
    wp_die(__('Nie masz uprawnień do przeglądania umów.', 'estateoffice'));
}

// Pobranie aktualnego agenta
$current_user_id = get_current_user_id();
$agent = new EstateOffice\Agents($current_user_id);

// Parametry paginacji
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Parametry filtrowania
$filter_type = isset($_GET['contract_type']) ? sanitize_text_field($_GET['contract_type']) : '';
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Pobranie umów z bazy danych z uwzględnieniem filtrów
$contracts = new EstateOffice\Contracts();
$contracts_data = $contracts->get_contracts([
    'agent_id' => $agent->is_admin() ? null : $current_user_id,
    'type' => $filter_type,
    'status' => $filter_status,
    'search' => $search_query,
    'per_page' => $per_page,
    'page' => $current_page
]);

$total_contracts = $contracts->get_total_count();
$total_pages = ceil($total_contracts / $per_page);
?>

<div class="eo-crm-wrapper">
    <!-- Nagłówek sekcji -->
    <div class="eo-crm-header">
        <h2><?php _e('Zarządzanie umowami', 'estateoffice'); ?></h2>
        <a href="<?php echo esc_url(admin_url('admin.php?page=eo-add-contract')); ?>" class="eo-button eo-button-primary">
            <?php _e('Dodaj nową umowę', 'estateoffice'); ?>
        </a>
    </div>

    <!-- Filtry i wyszukiwarka -->
    <div class="eo-crm-filters">
        <form method="get" action="" class="eo-filters-form">
            <input type="hidden" name="page" value="eo-contracts" />
            
            <!-- Filtr typu transakcji -->
            <select name="contract_type" class="eo-select">
                <option value=""><?php _e('Wszystkie typy', 'estateoffice'); ?></option>
                <option value="SPRZEDAZ" <?php selected($filter_type, 'SPRZEDAZ'); ?>><?php _e('Sprzedaż', 'estateoffice'); ?></option>
                <option value="KUPNO" <?php selected($filter_type, 'KUPNO'); ?>><?php _e('Kupno', 'estateoffice'); ?></option>
                <option value="WYNAJEM" <?php selected($filter_type, 'WYNAJEM'); ?>><?php _e('Wynajem', 'estateoffice'); ?></option>
                <option value="NAJEM" <?php selected($filter_type, 'NAJEM'); ?>><?php _e('Najem', 'estateoffice'); ?></option>
            </select>

            <!-- Filtr statusu -->
            <select name="status" class="eo-select">
                <option value=""><?php _e('Wszystkie statusy', 'estateoffice'); ?></option>
                <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Aktywne', 'estateoffice'); ?></option>
                <option value="completed" <?php selected($filter_status, 'completed'); ?>><?php _e('Zakończone', 'estateoffice'); ?></option>
                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>><?php _e('Anulowane', 'estateoffice'); ?></option>
            </select>

            <!-- Wyszukiwarka -->
            <div class="eo-search-box">
                <input type="text" name="search" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php _e('Szukaj umów...', 'estateoffice'); ?>" />
                <button type="submit" class="eo-button"><?php _e('Szukaj', 'estateoffice'); ?></button>
            </div>
        </form>
    </div>

    <!-- Tabela umów -->
    <div class="eo-table-wrapper">
        <table class="eo-table eo-contracts-table">
            <thead>
                <tr>
                    <th><?php _e('Numer umowy', 'estateoffice'); ?></th>
                    <th><?php _e('Typ transakcji', 'estateoffice'); ?></th>
                    <th><?php _e('Rodzaj nieruchomości', 'estateoffice'); ?></th>
                    <th><?php _e('Adres', 'estateoffice'); ?></th>
                    <th><?php _e('Data zawarcia', 'estateoffice'); ?></th>
                    <th><?php _e('Data zakończenia', 'estateoffice'); ?></th>
                    <th><?php _e('Aktualny etap', 'estateoffice'); ?></th>
                    <th><?php _e('Opiekun', 'estateoffice'); ?></th>
                    <th><?php _e('Akcje', 'estateoffice'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($contracts_data)) : ?>
                    <?php foreach ($contracts_data as $contract) : ?>
                        <tr data-contract-id="<?php echo esc_attr($contract->id); ?>">
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=eo-contract&id=' . $contract->id)); ?>">
                                    <?php echo esc_html($contract->contract_number); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($contract->get_transaction_type_label()); ?></td>
                            <td><?php echo esc_html($contract->get_property_type_label()); ?></td>
                            <td>
                                <?php if ($contract->property_id) : ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=eo-property&id=' . $contract->property_id)); ?>">
                                        <?php echo esc_html($contract->get_property_address()); ?>
                                    </a>
                                <?php else : ?>
                                    <?php _e('Brak nieruchomości', 'estateoffice'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($contract->start_date))); ?></td>
                            <td>
                                <?php if ($contract->end_date) : ?>
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($contract->end_date))); ?>
                                <?php else : ?>
                                    <?php _e('Bezterminowa', 'estateoffice'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="eo-stage-badge eo-stage-<?php echo esc_attr($contract->current_stage); ?>">
                                    <?php echo esc_html($contract->get_stage_label()); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $agent_data = $contract->get_agent_data();
                                echo esc_html($agent_data['display_name']);
                                ?>
                            </td>
                            <td class="eo-actions">
                                <div class="eo-action-buttons">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=eo-contract&id=' . $contract->id)); ?>" 
                                       class="eo-button eo-button-small" 
                                       title="<?php _e('Zobacz szczegóły', 'estateoffice'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    
                                    <?php if ($contract->can_edit()) : ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=eo-contract&action=edit&id=' . $contract->id)); ?>" 
                                           class="eo-button eo-button-small" 
                                           title="<?php _e('Edytuj', 'estateoffice'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($contract->can_delete()) : ?>
                                        <button type="button" 
                                                class="eo-button eo-button-small eo-button-danger eo-delete-contract" 
                                                data-contract-id="<?php echo esc_attr($contract->id); ?>"
                                                title="<?php _e('Usuń', 'estateoffice'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="9" class="eo-no-records">
                            <?php _e('Nie znaleziono żadnych umów.', 'estateoffice'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginacja -->
    <?php if ($total_pages > 1) : ?>
        <div class="eo-pagination">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo; Poprzednia', 'estateoffice'),
                'next_text' => __('Następna &raquo;', 'estateoffice'),
                'total' => $total_pages,
                'current' => $current_page,
                'type' => 'list'
            ));
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Template dla modalu potwierdzenia usunięcia -->
<div id="eo-delete-contract-modal" class="eo-modal" style="display: none;">
    <div class="eo-modal-content">
        <h3><?php _e('Potwierdzenie usunięcia', 'estateoffice'); ?></h3>
        <p><?php _e('Czy na pewno chcesz usunąć tę umowę? Tej operacji nie można cofnąć.', 'estateoffice'); ?></p>
        <div class="eo-modal-actions">
            <button type="button" class="eo-button eo-button-secondary eo-modal-cancel">
                <?php _e('Anuluj', 'estateoffice'); ?>
            </button>
            <button type="button" class="eo-button eo-button-danger eo-modal-confirm">
                <?php _e('Usuń', 'estateoffice'); ?>
            </button>
        </div>
    </div>
</div>

<?php
// Skrypt dla obsługi usuwania umów
wp_enqueue_script('eo-contracts-table', ESTATEOFFICE_PLUGIN_URL . 'frontend/js/contracts-table.js', array('jquery'), ESTATEOFFICE_VERSION, true);
wp_localize_script('eo-contracts-table', 'eoContracts', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('eo_delete_contract'),
    'deleteError' => __('Wystąpił błąd podczas usuwania umowy. Spróbuj ponownie.', 'estateoffice'),
    'deleteSuccess' => __('Umowa została pomyślnie usunięta.', 'estateoffice')
));