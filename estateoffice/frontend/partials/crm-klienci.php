<?php
/**
 * Widok listy klientów w systemie CRM
 *
 * @package EstateOffice
 * @subpackage Frontend/Partials
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('read_eo_clients')) {
    wp_die(__('Nie masz uprawnień do przeglądania tej strony.', 'estateoffice'));
}

// Pobranie aktualnego użytkownika
$current_user = wp_get_current_user();
$is_agent = in_array('eo_agent', (array) $current_user->roles);
$is_admin = current_user_can('manage_options');

// Inicjalizacja klasy klientów
$clients = new EstateOffice\Clients();

// Parametry wyszukiwania i sortowania
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'name';
$sort_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
$page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;

// Pobranie listy klientów z uwzględnieniem filtrów
$clients_list = $clients->get_clients_list([
    'search' => $search_query,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order,
    'page' => $page,
    'per_page' => $per_page,
    'agent_id' => $is_agent ? $current_user->ID : null
]);

$total_clients = $clients->get_total_count($search_query, $is_agent ? $current_user->ID : null);
$total_pages = ceil($total_clients / $per_page);

// Przygotowanie parametrów sortowania
function get_sort_url($column) {
    global $sort_by, $sort_order, $search_query;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'DESC' : 'ASC';
    $params = array(
        'sort' => $column,
        'order' => $new_order
    );
    if (!empty($search_query)) {
        $params['search'] = $search_query;
    }
    return add_query_arg($params);
}

// Przygotowanie ikony sortowania
function get_sort_icon($column) {
    global $sort_by, $sort_order;
    if ($sort_by !== $column) {
        return '';
    }
    return $sort_order === 'ASC' ? '↑' : '↓';
}
?>

<div class="wrap eo-crm-clients">
    <h1 class="wp-heading-inline"><?php _e('Klienci', 'estateoffice'); ?></h1>
    
    <!-- Przycisk dodawania nowej umowy -->
    <a href="<?php echo esc_url(admin_url('admin.php?page=eo-contracts&action=add')); ?>" class="page-title-action">
        <?php _e('Dodaj nową Umowę', 'estateoffice'); ?>
    </a>

    <!-- Formularz wyszukiwania -->
    <form method="get" class="eo-search-form">
        <input type="hidden" name="page" value="eo-clients">
        <p class="search-box">
            <label class="screen-reader-text" for="client-search"><?php _e('Szukaj klientów:', 'estateoffice'); ?></label>
            <input type="search" id="client-search" name="search" value="<?php echo esc_attr($search_query); ?>" 
                   placeholder="<?php _e('Szukaj po nazwie, telefonie, email...', 'estateoffice'); ?>">
            <input type="submit" class="button" value="<?php _e('Szukaj', 'estateoffice'); ?>">
        </p>
    </form>

    <!-- Tabela klientów -->
    <table class="wp-list-table widefat fixed striped eo-clients-table">
        <thead>
            <tr>
                <th class="column-name">
                    <a href="<?php echo esc_url(get_sort_url('name')); ?>">
                        <?php _e('Imię i nazwisko/Nazwa', 'estateoffice'); ?>
                        <?php echo get_sort_icon('name'); ?>
                    </a>
                </th>
                <th class="column-address">
                    <a href="<?php echo esc_url(get_sort_url('address')); ?>">
                        <?php _e('Adres', 'estateoffice'); ?>
                        <?php echo get_sort_icon('address'); ?>
                    </a>
                </th>
                <th class="column-phone">
                    <a href="<?php echo esc_url(get_sort_url('phone')); ?>">
                        <?php _e('Telefon', 'estateoffice'); ?>
                        <?php echo get_sort_icon('phone'); ?>
                    </a>
                </th>
                <th class="column-email">
                    <a href="<?php echo esc_url(get_sort_url('email')); ?>">
                        <?php _e('E-mail', 'estateoffice'); ?>
                        <?php echo get_sort_icon('email'); ?>
                    </a>
                </th>
                <th class="column-agent">
                    <a href="<?php echo esc_url(get_sort_url('agent')); ?>">
                        <?php _e('Opiekun', 'estateoffice'); ?>
                        <?php echo get_sort_icon('agent'); ?>
                    </a>
                </th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($clients_list)) : ?>
                <tr>
                    <td colspan="5" class="eo-no-items">
                        <?php _e('Nie znaleziono klientów.', 'estateoffice'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($clients_list as $client) : ?>
                    <tr>
                        <td class="column-name">
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'eo-clients', 'action' => 'edit', 'id' => $client->id])); ?>" 
                               class="row-title">
                                <?php echo esc_html($client->is_company ? $client->company_name : $client->first_name . ' ' . $client->last_name); ?>
                            </a>
                        </td>
                        <td class="column-address">
                            <?php if (!empty($client->property_id)) : ?>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'eo-properties', 'action' => 'edit', 'id' => $client->property_id])); ?>">
                                    <?php echo esc_html($client->address); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($client->address); ?>
                            <?php endif; ?>
                        </td>
                        <td class="column-phone">
                            <?php echo esc_html($client->phone); ?>
                        </td>
                        <td class="column-email">
                            <a href="mailto:<?php echo esc_attr($client->email); ?>">
                                <?php echo esc_html($client->email); ?>
                            </a>
                        </td>
                        <td class="column-agent">
                            <?php echo esc_html($client->agent_name); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Paginacja -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page,
                    'type' => 'list'
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Skrypty JavaScript -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Obsługa wyszukiwania na żywo
    var searchTimer;
    $('#client-search').on('keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            $('.eo-search-form').submit();
        }, 500);
    });

    // Inicjalizacja tooltipów
    $('.eo-clients-table [title]').tooltip();
});
</script>