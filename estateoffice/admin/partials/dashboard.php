<?php
/**
 * Szablon pulpitu administracyjnego EstateOffice
 *
 * @package EstateOffice
 * @subpackage Admin/Partials
 * @version 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('manage_options') && !current_user_can('edit_eo_properties')) {
    wp_die(__('Nie masz wystarczających uprawnień do wyświetlenia tej strony.', 'estateoffice'));
}

// Pobranie danych statystycznych
$stats = new EstateOffice\Admin\Statistics();
$active_agents = $stats->get_most_active_agents(3);
$sale_offers_count = $stats->get_active_offers_count('sale');
$rent_offers_count = $stats->get_active_offers_count('rent');
$search_requests_count = $stats->get_active_search_requests_count();
?>

<div class="wrap eo-dashboard">
    <h1><?php echo esc_html__('EstateOffice - Pulpit', 'estateoffice'); ?></h1>

    <div class="eo-dashboard-grid">
        <!-- Sekcja najaktywniejszych agentów -->
        <div class="eo-dashboard-card">
            <h2><?php echo esc_html__('Najbardziej aktywni Agenci', 'estateoffice'); ?></h2>
            <div class="eo-agents-list">
                <?php if (!empty($active_agents)) : ?>
                    <?php foreach ($active_agents as $agent) : ?>
                        <div class="eo-agent-item">
                            <div class="eo-agent-avatar">
                                <?php echo get_avatar($agent->user_id, 50); ?>
                            </div>
                            <div class="eo-agent-info">
                                <h3><?php echo esc_html($agent->display_name); ?></h3>
                                <p>
                                    <?php 
                                    echo sprintf(
                                        esc_html__('Aktywni klienci: %d', 'estateoffice'),
                                        $agent->active_clients_count
                                    ); 
                                    ?>
                                </p>
                                <p>
                                    <?php 
                                    echo sprintf(
                                        esc_html__('Aktywne oferty: %d', 'estateoffice'),
                                        $agent->active_offers_count
                                    ); 
                                    ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="eo-no-data">
                        <?php echo esc_html__('Brak aktywnych agentów', 'estateoffice'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statystyki ofert -->
        <div class="eo-stats-grid">
            <!-- Oferty na sprzedaż -->
            <div class="eo-dashboard-card eo-stat-card">
                <h2><?php echo esc_html__('Oferty na Sprzedaż', 'estateoffice'); ?></h2>
                <div class="eo-stat-number">
                    <?php echo esc_html($sale_offers_count); ?>
                </div>
                <div class="eo-stat-label">
                    <?php echo esc_html__('aktywnych ofert', 'estateoffice'); ?>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eo-properties&type=sale')); ?>" class="eo-stat-link">
                    <?php echo esc_html__('Zobacz wszystkie', 'estateoffice'); ?> →
                </a>
            </div>

            <!-- Oferty na wynajem -->
            <div class="eo-dashboard-card eo-stat-card">
                <h2><?php echo esc_html__('Oferty na Wynajem', 'estateoffice'); ?></h2>
                <div class="eo-stat-number">
                    <?php echo esc_html($rent_offers_count); ?>
                </div>
                <div class="eo-stat-label">
                    <?php echo esc_html__('aktywnych ofert', 'estateoffice'); ?>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eo-properties&type=rent')); ?>" class="eo-stat-link">
                    <?php echo esc_html__('Zobacz wszystkie', 'estateoffice'); ?> →
                </a>
            </div>

            <!-- Aktywne poszukiwania -->
            <div class="eo-dashboard-card eo-stat-card">
                <h2><?php echo esc_html__('Aktywne Poszukiwania', 'estateoffice'); ?></h2>
                <div class="eo-stat-number">
                    <?php echo esc_html($search_requests_count); ?>
                </div>
                <div class="eo-stat-label">
                    <?php echo esc_html__('aktywnych poszukiwań', 'estateoffice'); ?>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eo-search-requests')); ?>" class="eo-stat-link">
                    <?php echo esc_html__('Zobacz wszystkie', 'estateoffice'); ?> →
                </a>
            </div>
        </div>
    </div>

    <!-- Sekcja szybkich akcji -->
    <div class="eo-quick-actions">
        <h2><?php echo esc_html__('Szybkie akcje', 'estateoffice'); ?></h2>
        <div class="eo-actions-grid">
            <a href="<?php echo esc_url(admin_url('admin.php?page=eo-properties&action=new')); ?>" class="eo-action-button">
                <span class="dashicons dashicons-plus"></span>
                <?php echo esc_html__('Dodaj nową nieruchomość', 'estateoffice'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=eo-contracts&action=new')); ?>" class="eo-action-button">
                <span class="dashicons dashicons-media-document"></span>
                <?php echo esc_html__('Utwórz nową umowę', 'estateoffice'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=eo-clients&action=new')); ?>" class="eo-action-button">
                <span class="dashicons dashicons-admin-users"></span>
                <?php echo esc_html__('Dodaj nowego klienta', 'estateoffice'); ?>
            </a>
        </div>
    </div>
</div>