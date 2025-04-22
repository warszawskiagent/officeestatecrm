<?php
/**
 * Template Name: Admin Agents List
 * 
 * Szablon listy agentów w panelu administracyjnym
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
if (!current_user_can('manage_eo_agents')) {
    wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
}

// Pobranie aktualnej strony paginacji
$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;

// Pobranie parametrów filtrowania
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';

// Pobranie listy agentów
$agents_query = new EstateOffice\Queries\AgentsQuery([
    'search' => $search,
    'orderby' => $orderby,
    'order' => $order,
    'per_page' => $per_page,
    'paged' => $current_page
]);

$agents = $agents_query->get_results();
$total_agents = $agents_query->get_total();
$total_pages = ceil($total_agents / $per_page);

// Przygotowanie URL dla sortowania
$sort_url = add_query_arg([
    'page' => 'estateoffice-agents',
    'paged' => $current_page,
    'search' => $search
], admin_url('admin.php'));
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Agenci', 'estateoffice'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=estateoffice-agents&action=new')); ?>" class="page-title-action">
        <?php _e('Dodaj nowego', 'estateoffice'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Formularz wyszukiwania -->
    <form method="get" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="page" value="estateoffice-agents">
        <p class="search-box">
            <label class="screen-reader-text" for="agent-search-input">
                <?php _e('Szukaj agentów:', 'estateoffice'); ?>
            </label>
            <input type="search" 
                   id="agent-search-input" 
                   name="search" 
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Szukaj agenta...', 'estateoffice'); ?>">
            <input type="submit" 
                   id="search-submit" 
                   class="button" 
                   value="<?php esc_attr_e('Szukaj', 'estateoffice'); ?>">
        </p>
    </form>

    <!-- Tabela agentów -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-photo">
                    <?php _e('Zdjęcie', 'estateoffice'); ?>
                </th>
                <th scope="col" class="manage-column column-name sortable <?php echo ($orderby === 'last_name') ? $order : ''; ?>">
                    <a href="<?php echo esc_url(add_query_arg(['orderby' => 'last_name', 'order' => ($orderby === 'last_name' && $order === 'ASC') ? 'DESC' : 'ASC'], $sort_url)); ?>">
                        <span><?php _e('Imię i Nazwisko', 'estateoffice'); ?></span>
                        <span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-phone">
                    <?php _e('Telefon', 'estateoffice'); ?>
                </th>
                <th scope="col" class="manage-column column-email">
                    <?php _e('Email', 'estateoffice'); ?>
                </th>
                <th scope="col" class="manage-column column-active-offers sortable <?php echo ($orderby === 'active_offers') ? $order : ''; ?>">
                    <a href="<?php echo esc_url(add_query_arg(['orderby' => 'active_offers', 'order' => ($orderby === 'active_offers' && $order === 'ASC') ? 'DESC' : 'ASC'], $sort_url)); ?>">
                        <span><?php _e('Aktywne oferty', 'estateoffice'); ?></span>
                        <span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php _e('Akcje', 'estateoffice'); ?>
                </th>
            </tr>
        </thead>

        <tbody id="the-list">
            <?php if (!empty($agents)) : ?>
                <?php foreach ($agents as $agent) : ?>
                    <tr id="agent-<?php echo esc_attr($agent->ID); ?>">
                        <td class="column-photo">
                            <?php if ($agent->photo_url) : ?>
                                <img src="<?php echo esc_url($agent->photo_url); ?>" 
                                     alt="<?php echo esc_attr($agent->first_name . ' ' . $agent->last_name); ?>" 
                                     class="agent-photo" 
                                     width="50" 
                                     height="50">
                            <?php else : ?>
                                <img src="<?php echo esc_url(ESTATEOFFICE_PLUGIN_URL . 'assets/images/default-agent.png'); ?>" 
                                     alt="<?php esc_attr_e('Brak zdjęcia', 'estateoffice'); ?>" 
                                     class="agent-photo" 
                                     width="50" 
                                     height="50">
                            <?php endif; ?>
                        </td>
                        <td class="column-name">
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=estateoffice-agents&action=edit&agent_id=' . $agent->ID)); ?>" 
                                   class="row-title">
                                    <?php echo esc_html($agent->first_name . ' ' . $agent->last_name); ?>
                                </a>
                            </strong>
                        </td>
                        <td class="column-phone">
                            <?php echo esc_html($agent->phone); ?>
                        </td>
                        <td class="column-email">
                            <a href="mailto:<?php echo esc_attr($agent->email); ?>">
                                <?php echo esc_html($agent->email); ?>
                            </a>
                        </td>
                        <td class="column-active-offers">
                            <?php echo esc_html($agent->active_offers_count); ?>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=estateoffice-agents&action=edit&agent_id=' . $agent->ID)); ?>" 
                                       aria-label="<?php esc_attr_e('Edytuj tego agenta', 'estateoffice'); ?>">
                                        <?php _e('Edytuj', 'estateoffice'); ?>
                                    </a> |
                                </span>
                                <span class="view">
                                    <a href="<?php echo esc_url(get_author_posts_url($agent->user_id)); ?>" 
                                       target="_blank" 
                                       aria-label="<?php esc_attr_e('Zobacz profil publiczny', 'estateoffice'); ?>">
                                        <?php _e('Zobacz profil', 'estateoffice'); ?>
                                    </a> |
                                </span>
                                <span class="delete">
                                    <a href="#" 
                                       class="submitdelete delete-agent" 
                                       data-agent-id="<?php echo esc_attr($agent->ID); ?>" 
                                       data-nonce="<?php echo wp_create_nonce('delete_agent_' . $agent->ID); ?>" 
                                       aria-label="<?php esc_attr_e('Usuń tego agenta', 'estateoffice'); ?>">
                                        <?php _e('Usuń', 'estateoffice'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6" class="no-items">
                        <?php _e('Nie znaleziono agentów.', 'estateoffice'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>

        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-photo">
                    <?php _e('Zdjęcie', 'estateoffice'); ?>
                </th>
                <th scope="col" class="manage-column column-name sortable <?php echo ($orderby === 'last_name') ? $order : ''; ?>">
                    <a href="<?php echo esc_url(add_query_arg(['orderby' => 'last_name', 'order' => ($orderby === 'last_name' && $order === 'ASC') ? 'DESC' : 'ASC'], $sort_url)); ?>">
                        <span><?php _e('Imię i Nazwisko', 'estateoffice'); ?></span>
                        <span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-phone">
                    <?php _e('Telefon', 'estateoffice'); ?>
                </th>
                <th scope="col" class="manage-column column-email">
                    <?php _e('Email', 'estateoffice'); ?>
                </th>
                <th scope="col" class="manage-column column-active-offers sortable <?php echo ($orderby === 'active_offers') ? $order : ''; ?>">
                    <a href="<?php echo esc_url(add_query_arg(['orderby' => 'active_offers', 'order' => ($orderby === 'active_offers' && $order === 'ASC') ? 'DESC' : 'ASC'], $sort_url)); ?>">
                        <span><?php _e('Aktywne oferty', 'estateoffice'); ?></span>
                        <span class="sorting-indicator"></span>
                    </a>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php _e('Akcje', 'estateoffice'); ?>
                </th>
            </tr>
        </tfoot>
    </table>

    <!-- Paginacja -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        _n(
                            '%s agent', 
                            '%s agentów', 
                            $total_agents, 
                            'estateoffice'
                        ), 
                        number_format_i18n($total_agents)
                    ); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Template dla modalu potwierdzenia usunięcia -->
<div id="delete-agent-modal" class="eo-modal" style="display: none;">
    <div class="eo-modal-content">
        <h2><?php _e('Potwierdź usunięcie', 'estateoffice'); ?></h2>
        <p><?php _e('Czy na pewno chcesz usunąć tego agenta? Tej operacji nie można cofnąć.', 'estateoffice'); ?></p>
        <div class="eo-modal-footer">
            <button type="button" class="button cancel-delete">
                <?php _e('Anuluj', 'estateoffice'); ?>
            </button>
            <button type="button" class="button button-primary confirm-delete">
                <?php _e('Usuń', 'estateoffice'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Obsługa usuwania agenta
        $('.delete-agent').on('click', function(e) {
            e.preventDefault();
            var agentId = $(this).data('agent-id');
            var nonce = $(this).data('nonce');
            
            // Pokazanie modalu potwierdzenia
            $('#delete-agent-modal').show();
            
            // Obsługa przycisku potwierdzenia
            $('.confirm-delete').off('click').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'eo_delete_agent',
                        agent_id: agentId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Usunięcie wiersza z tabeli
                            $('#agent-' + agentId).fadeOut('fast', function() {
                                $(this).remove();
                                
                                // Aktualizacja licznika agentów
                                var displayingNum = $('.displaying-num');
                                var currentCount = parseInt($('.displaying-num').text());
                                displayingNum.text((currentCount - 1) + ' ' + 
                                    ((currentCount - 1) === 1 ? 
                                        eoAdminL10n.agentSingular : 
                                        eoAdminL10n.agentPlural)
                                );
                                
                                // Pokazanie powiadomienia
                                wp.notices.success(response.data.message);
                            });
                        } else {
                            wp.notices.error(response.data.message);
                        }
                    },
                    error: function() {
                        wp.notices.error(eoAdminL10n.deleteError);
                    },
                    complete: function() {
                        $('#delete-agent-modal').hide();
                    }
                });
            });
            
            // Obsługa przycisku anulowania
            $('.cancel-delete').on('click', function() {
                $('#delete-agent-modal').hide();
            });
        });
        
        // Zamknięcie modalu po kliknięciu poza nim
        $(window).on('click', function(e) {
            if ($(e.target).is('#delete-agent-modal')) {
                $('#delete-agent-modal').hide();
            }
        });
        
        // Obsługa sortowania
        $('.column-name.sortable a, .column-active-offers.sortable a').on('click', function(e) {
            e.preventDefault();
            window.location.href = $(this).attr('href');
        });
        
        // Inicjalizacja tooltipów
        $('.agent-photo').tooltip({
            items: 'img',
            content: function() {
                return $('<img/>')
                    .attr('src', $(this).attr('src'))
                    .css('max-width', '200px')
                    .css('max-height', '200px');
            }
        });
    });
</script>

<?php
// Dodanie styli inline dla modalu
?>
<style>
.eo-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.eo-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #ddd;
    width: 80%;
    max-width: 500px;
    border-radius: 4px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.eo-modal-footer {
    margin-top: 20px;
    text-align: right;
}

.eo-modal-footer .button {
    margin-left: 10px;
}

.agent-photo {
    border-radius: 50%;
    object-fit: cover;
}

.column-photo {
    width: 60px;
}

.column-actions {
    width: 150px;
}

.column-active-offers {
    width: 100px;
    text-align: center;
}

.row-actions {
    visibility: hidden;
}

tr:hover .row-actions {
    visibility: visible;
}

@media screen and (max-width: 782px) {
    .column-phone,
    .column-email {
        display: none;
    }
    
    .row-actions {
        visibility: visible;
    }
}
</style>