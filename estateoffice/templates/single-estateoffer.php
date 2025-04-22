<?php
/**
 * Template for displaying single property
 *
 * @package EstateOffice
 * @since 0.5.5
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Pobierz dane nieruchomości
$property_id = get_post_meta(get_the_ID(), 'eo_property_id', true);
$property = new EstateOffice\Property($property_id);
$gallery = $property->get_gallery();
$details = $property->get_details();
$features = $property->get_features();
$agent = $property->get_agent();

// Przygotuj dane do Google Maps
$map_data = array(
    'lat' => get_post_meta(get_the_ID(), 'eo_property_lat', true),
    'lng' => get_post_meta(get_the_ID(), 'eo_property_lng', true),
    'zoom' => 15
);

?>

<div class="eo-single-property">
    <!-- Sekcja nagłówka oferty -->
    <header class="eo-property-header">
        <div class="eo-property-status">
            <?php echo $property->get_status_badge(); ?>
        </div>
        <h1 class="eo-property-title"><?php the_title(); ?></h1>
        <div class="eo-property-meta">
            <span class="eo-property-id">
                <?php printf(__('ID oferty: %s', 'estateoffice'), $property->get_offer_number()); ?>
            </span>
            <span class="eo-property-date">
                <?php printf(__('Dodano: %s', 'estateoffice'), get_the_date()); ?>
            </span>
            <span class="eo-property-views">
                <?php printf(__('Wyświetlenia: %s', 'estateoffice'), $property->get_views_count()); ?>
            </span>
        </div>
    </header>

    <!-- Główna zawartość oferty -->
    <div class="eo-property-content">
        <!-- Galeria zdjęć -->
        <div class="eo-property-gallery">
            <?php if (!empty($gallery)) : ?>
                <div class="eo-gallery-main">
                    <?php
                    foreach ($gallery as $image) {
                        printf(
                            '<div class="eo-gallery-item" data-full="%s">
                                <img src="%s" alt="%s" />
                            </div>',
                            esc_url($image['full']),
                            esc_url($image['thumb']),
                            esc_attr($image['alt'])
                        );
                    }
                    ?>
                </div>
                <div class="eo-gallery-thumbs">
                    <?php
                    foreach ($gallery as $image) {
                        printf(
                            '<div class="eo-thumb-item">
                                <img src="%s" alt="%s" />
                            </div>',
                            esc_url($image['thumb']),
                            esc_attr($image['alt'])
                        );
                    }
                    ?>
                </div>
            <?php else : ?>
                <div class="eo-no-gallery">
                    <?php _e('Brak zdjęć', 'estateoffice'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Szczegóły oferty -->
        <div class="eo-property-details">
            <div class="eo-price-section">
                <h2 class="eo-price">
                    <?php echo $property->get_formatted_price(); ?>
                </h2>
                <?php if ($property->has_price_per_meter()) : ?>
                    <span class="eo-price-per-meter">
                        <?php echo $property->get_formatted_price_per_meter(); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="eo-main-features">
                <?php if ($details['area']) : ?>
                    <div class="eo-feature">
                        <span class="eo-feature-icon area-icon"></span>
                        <span class="eo-feature-value"><?php echo esc_html($details['area']); ?> m²</span>
                        <span class="eo-feature-label"><?php _e('Powierzchnia', 'estateoffice'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($details['rooms']) : ?>
                    <div class="eo-feature">
                        <span class="eo-feature-icon rooms-icon"></span>
                        <span class="eo-feature-value"><?php echo esc_html($details['rooms']); ?></span>
                        <span class="eo-feature-label"><?php _e('Pokoje', 'estateoffice'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($details['floor']) : ?>
                    <div class="eo-feature">
                        <span class="eo-feature-icon floor-icon"></span>
                        <span class="eo-feature-value"><?php echo esc_html($details['floor']); ?></span>
                        <span class="eo-feature-label"><?php _e('Piętro', 'estateoffice'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
			<!-- Lokalizacja -->
            <div class="eo-location-section">
                <h3><?php _e('Lokalizacja', 'estateoffice'); ?></h3>
                <div class="eo-address">
                    <?php echo $property->get_formatted_address(); ?>
                </div>
                <?php if ($map_data['lat'] && $map_data['lng']) : ?>
                    <div id="eo-property-map" 
                         class="eo-map" 
                         data-lat="<?php echo esc_attr($map_data['lat']); ?>"
                         data-lng="<?php echo esc_attr($map_data['lng']); ?>"
                         data-zoom="<?php echo esc_attr($map_data['zoom']); ?>">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Opis nieruchomości -->
            <div class="eo-description-section">
                <h3><?php _e('Opis nieruchomości', 'estateoffice'); ?></h3>
                <div class="eo-description">
                    <?php the_content(); ?>
                </div>
            </div>

            <!-- Szczegółowe informacje -->
            <div class="eo-details-section">
                <h3><?php _e('Szczegółowe informacje', 'estateoffice'); ?></h3>
                <div class="eo-details-grid">
                    <?php foreach ($details as $key => $value) : ?>
                        <?php if (!empty($value)) : ?>
                            <div class="eo-detail-item">
                                <span class="eo-detail-label">
                                    <?php echo esc_html($property->get_detail_label($key)); ?>
                                </span>
                                <span class="eo-detail-value">
                                    <?php echo esc_html($value); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Wyposażenie i udogodnienia -->
            <?php if (!empty($features)) : ?>
                <div class="eo-features-section">
                    <h3><?php _e('Wyposażenie i udogodnienia', 'estateoffice'); ?></h3>
                    <div class="eo-features-grid">
                        <?php foreach ($features as $feature) : ?>
                            <div class="eo-feature-item">
                                <span class="eo-feature-icon <?php echo esc_attr($feature['icon']); ?>"></span>
                                <span class="eo-feature-name"><?php echo esc_html($feature['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dokumenty i plany -->
            <?php
            $floor_plans = $property->get_floor_plans();
            $documents = $property->get_documents();
            if (!empty($floor_plans) || !empty($documents)) : 
            ?>
                <div class="eo-documents-section">
                    <h3><?php _e('Dokumenty i plany', 'estateoffice'); ?></h3>
                    
                    <?php if (!empty($floor_plans)) : ?>
                        <div class="eo-floor-plans">
                            <h4><?php _e('Plany mieszkania', 'estateoffice'); ?></h4>
                            <div class="eo-plans-grid">
                                <?php foreach ($floor_plans as $plan) : ?>
                                    <a href="<?php echo esc_url($plan['url']); ?>" 
                                       class="eo-plan-item"
                                       data-fancybox="plans">
                                        <img src="<?php echo esc_url($plan['thumb']); ?>" 
                                             alt="<?php echo esc_attr($plan['title']); ?>" />
                                        <span class="eo-plan-title">
                                            <?php echo esc_html($plan['title']); ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Dane agenta -->
            <?php if ($agent) : ?>
                <div class="eo-agent-section">
                    <h3><?php _e('Agent prowadzący', 'estateoffice'); ?></h3>
                    <div class="eo-agent-card">
                        <div class="eo-agent-photo">
                            <img src="<?php echo esc_url($agent->get_photo_url()); ?>" 
                                 alt="<?php echo esc_attr($agent->get_full_name()); ?>" />
                        </div>
                        <div class="eo-agent-info">
                            <h4 class="eo-agent-name">
                                <?php echo esc_html($agent->get_full_name()); ?>
                            </h4>
                            <div class="eo-agent-position">
                                <?php echo esc_html($agent->get_position()); ?>
                            </div>
                            <div class="eo-agent-contact">
                                <a href="tel:<?php echo esc_attr($agent->get_phone()); ?>" 
                                   class="eo-agent-phone">
                                    <?php echo esc_html($agent->get_formatted_phone()); ?>
                                </a>
                                <a href="mailto:<?php echo esc_attr($agent->get_email()); ?>" 
                                   class="eo-agent-email">
                                    <?php echo esc_html($agent->get_email()); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formularz kontaktowy -->
            <div class="eo-contact-section">
                <h3><?php _e('Zapytaj o ofertę', 'estateoffice'); ?></h3>
                <?php echo do_shortcode('[eo_property_contact_form property_id="' . $property_id . '"]'); ?>
            </div>
        </div>
    </div>
</div>

<?php
// Skrypty i style
wp_enqueue_style('eo-property-single');
wp_enqueue_script('eo-property-gallery');
wp_enqueue_script('eo-google-maps');

// Footer
get_footer();