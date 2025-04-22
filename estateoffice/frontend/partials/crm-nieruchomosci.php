<?php
/**
 * Szablon tabeli nieruchomości w CRM
 *
 * @package EstateOffice
 * @subpackage Frontend
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('edit_eo_properties')) {
    wp_die(__('Nie masz uprawnień do przeglądania tej strony', 'estateoffice'));
}

// Pobieranie parametrów filtrowania i sortowania
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$order_by = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

// Liczba nieruchomości na stronę
$per_page = 20;

// Przygotowanie argumentów zapytania
$args = array(
    'post_type' => 'eo_property',
    'posts_per_page' => $per_page,
    'paged' => $current_page,
    'orderby' => $order_by,
    'order' => $order,
    'meta_query' => array()
);

// Dodanie wyszukiwania
if (!empty($search_query)) {
    $args['s'] = $search_query;
}

// Pobranie nieruchomości
$properties_query = new WP_Query($args);
?>

<div class="eo-crm-container">
    <!-- Nagłówek sekcji -->
    <div class="eo-crm-header">
        <h2><?php _e('Zarządzanie nieruchomościami', 'estateoffice'); ?></h2>
        <div class="eo-crm-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=eo-contracts&action=new')); ?>" class="button button-primary">
                <?php _e('Dodaj nową Umowę', 'estateoffice'); ?>
            </a>
        </div>
    </div>

    <!-- Formularz wyszukiwania -->
    <form method="get" class="eo-search-form">
        <input type="hidden" name="page" value="eo-properties" />
        <div class="eo-search-box">
            <input type="text" 
                   name="s" 
                   value="<?php echo esc_attr($search_query); ?>" 
                   placeholder="<?php _e('Szukaj nieruchomości...', 'estateoffice'); ?>" 
                   class="eo-search-input" />
            <button type="submit" class="button">
                <?php _e('Szukaj', 'estateoffice'); ?>
            </button>
        </div>
    </form>

    <?php if ($properties_query->have_posts()) : ?>
        <!-- Tabela nieruchomości -->
        <table class="eo-properties-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-number sortable <?php echo $order_by === 'ID' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'ID', 'order' => $order_by === 'ID' && $order === 'ASC' ? 'DESC' : 'ASC'))); ?>">
                            <?php _e('Numer oferty', 'estateoffice'); ?>
                        </a>
                    </th>
                    <th class="column-address">
                        <?php _e('Adres', 'estateoffice'); ?>
                    </th>
                    <th class="column-price sortable <?php echo $order_by === 'price' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'price', 'order' => $order_by === 'price' && $order === 'ASC' ? 'DESC' : 'ASC'))); ?>">
                            <?php _e('Cena', 'estateoffice'); ?>
                        </a>
                    </th>
                    <th class="column-price-per-meter">
                        <?php _e('Cena za m²', 'estateoffice'); ?>
                    </th>
                    <th class="column-area sortable <?php echo $order_by === 'area' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'area', 'order' => $order_by === 'area' && $order === 'ASC' ? 'DESC' : 'ASC'))); ?>">
                            <?php _e('Metraż', 'estateoffice'); ?>
                        </a>
                    </th>
                    <th class="column-rooms">
                        <?php _e('Liczba pokoi', 'estateoffice'); ?>
                    </th>
                    <th class="column-agent">
                        <?php _e('Opiekun', 'estateoffice'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php while ($properties_query->have_posts()) : $properties_query->the_post(); 
                    $property_id = get_the_ID();
                    $price = get_post_meta($property_id, 'eo_price', true);
                    $area = get_post_meta($property_id, 'eo_area', true);
                    $rooms = get_post_meta($property_id, 'eo_rooms', true);
                    $agent_id = get_post_meta($property_id, 'eo_agent_id', true);
                    $price_per_meter = $area > 0 ? $price / $area : 0;
                    
                    // Pobieranie danych adresowych
                    $address = array(
                        'street' => get_post_meta($property_id, 'eo_street', true),
                        'number' => get_post_meta($property_id, 'eo_number', true),
                        'city' => get_post_meta($property_id, 'eo_city', true)
                    );
                ?>
                    <tr>
                        <td class="column-number">
                            <a href="<?php echo esc_url(get_edit_post_link($property_id)); ?>">
                                <?php echo esc_html(get_post_meta($property_id, 'eo_property_number', true)); ?>
                            </a>
                        </td>
                        <td class="column-address">
                            <?php echo esc_html(implode(' ', array_filter($address))); ?>
                        </td>
                        <td class="column-price">
                            <?php echo number_format($price, 2, ',', ' ') . ' PLN'; ?>
                        </td>
                        <td class="column-price-per-meter">
                            <?php echo number_format($price_per_meter, 2, ',', ' ') . ' PLN/m²'; ?>
                        </td>
                        <td class="column-area">
                            <?php echo esc_html($area) . ' m²'; ?>
                        </td>
                        <td class="column-rooms">
                            <?php echo esc_html($rooms); ?>
                        </td>
                        <td class="column-agent">
                            <?php 
                            if ($agent_id) {
                                $agent = get_userdata($agent_id);
                                echo esc_html($agent ? $agent->display_name : '');
                            }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Paginacja -->
        <?php
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo; Poprzednia', 'estateoffice'),
            'next_text' => __('Następna &raquo;', 'estateoffice'),
            'total' => $properties_query->max_num_pages,
            'current' => $current_page
        ));
        ?>

    <?php else : ?>
        <div class="eo-no-results">
            <?php _e('Nie znaleziono nieruchomości.', 'estateoffice'); ?>
        </div>
    <?php endif; ?>

    <?php wp_reset_postdata(); ?>
</div>

<!-- Skrypty JavaScript dla obsługi dynamicznych funkcji -->
<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Obsługa sortowania kolumn
        $('.eo-properties-table th.sortable a').on('click', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            window.location.href = url;
        });

        // Obsługa wyszukiwania
        var searchTimer;
        $('.eo-search-input').on('input', function() {
            clearTimeout(searchTimer);
            var $input = $(this);
            searchTimer = setTimeout(function() {
                $input.closest('form').submit();
            }, 500);
        });
    });
</script> 