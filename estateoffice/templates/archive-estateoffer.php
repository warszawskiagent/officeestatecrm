<?php
/**
 * Template dla archiwum ofert nieruchomości
 *
 * @package EstateOffice
 * @since 0.5.5
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

get_header();

// Pobierz aktualne parametry filtrowania
$current_transaction = get_query_var('transaction_type', '');
$current_property_type = get_query_var('property_type', '');
$current_city = get_query_var('city', '');
$current_district = get_query_var('district', '');

// Przygotuj parametry dla filtrów
$transaction_types = get_terms([
    'taxonomy' => 'eo_transaction_type',
    'hide_empty' => true
]);

$property_types = get_terms([
    'taxonomy' => 'eo_property_type',
    'hide_empty' => true
]);

$cities = get_terms([
    'taxonomy' => 'eo_city',
    'hide_empty' => true
]);

$districts = get_terms([
    'taxonomy' => 'eo_district',
    'hide_empty' => true
]);
?>

<div class="eo-archive-wrapper">
    <div class="eo-filters">
        <form method="get" id="eo-filter-form" class="eo-filter-form">
            <!-- Typ transakcji -->
            <div class="eo-filter-group">
                <label for="transaction_type"><?php _e('Typ transakcji', 'estateoffice'); ?></label>
                <select name="transaction_type" id="transaction_type">
                    <option value=""><?php _e('Wszystkie', 'estateoffice'); ?></option>
                    <?php foreach ($transaction_types as $type): ?>
                        <option value="<?php echo esc_attr($type->slug); ?>" 
                                <?php selected($current_transaction, $type->slug); ?>>
                            <?php echo esc_html($type->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Rodzaj nieruchomości -->
            <div class="eo-filter-group">
                <label for="property_type"><?php _e('Rodzaj nieruchomości', 'estateoffice'); ?></label>
                <select name="property_type" id="property_type">
                    <option value=""><?php _e('Wszystkie', 'estateoffice'); ?></option>
                    <?php foreach ($property_types as $type): ?>
                        <option value="<?php echo esc_attr($type->slug); ?>"
                                <?php selected($current_property_type, $type->slug); ?>>
                            <?php echo esc_html($type->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Miasto -->
            <div class="eo-filter-group">
                <label for="city"><?php _e('Miasto', 'estateoffice'); ?></label>
                <select name="city" id="city">
                    <option value=""><?php _e('Wszystkie', 'estateoffice'); ?></option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo esc_attr($city->slug); ?>"
                                <?php selected($current_city, $city->slug); ?>>
                            <?php echo esc_html($city->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dzielnica (dynamicznie aktualizowana przez AJAX) -->
            <div class="eo-filter-group">
                <label for="district"><?php _e('Dzielnica', 'estateoffice'); ?></label>
                <select name="district" id="district">
                    <option value=""><?php _e('Wszystkie', 'estateoffice'); ?></option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?php echo esc_attr($district->slug); ?>"
                                <?php selected($current_district, $district->slug); ?>>
                            <?php echo esc_html($district->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="eo-filter-submit">
                <?php _e('Filtruj', 'estateoffice'); ?>
            </button>
        </form>
    </div>

    <div class="eo-archive-content">
        <?php if (have_posts()): ?>
            <div class="eo-properties-grid">
                <?php while (have_posts()): the_post(); 
                    // Pobierz dane nieruchomości
                    $property_id = get_post_meta(get_the_ID(), '_eo_property_id', true);
                    $property_data = EstateOffice\Properties::get_property_data($property_id);
                ?>
                    <article id="property-<?php the_ID(); ?>" <?php post_class('eo-property-card'); ?>>
                        <div class="eo-property-thumbnail">
                            <?php if (has_post_thumbnail()): ?>
                                <?php the_post_thumbnail('eo-property-thumbnail'); ?>
                            <?php else: ?>
                                <img src="<?php echo ESTATEOFFICE_PLUGIN_URL; ?>assets/images/no-image.png" 
                                     alt="<?php _e('Brak zdjęcia', 'estateoffice'); ?>" />
                            <?php endif; ?>
                            
                            <?php if ($property_data['is_new']): ?>
                                <span class="eo-property-badge eo-badge-new">
                                    <?php _e('Nowa oferta', 'estateoffice'); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($property_data['is_exclusive']): ?>
                                <span class="eo-property-badge eo-badge-exclusive">
                                    <?php _e('Na wyłączność', 'estateoffice'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="eo-property-content">
                            <h2 class="eo-property-title">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h2>

                            <div class="eo-property-meta">
                                <?php if (!empty($property_data['price'])): ?>
                                    <div class="eo-property-price">
                                        <?php echo esc_html(number_format($property_data['price'], 2)); ?> 
                                        <?php echo esc_html($property_data['price_currency']); ?>
                                        <?php if ($property_data['transaction_type'] === 'wynajem'): ?>
                                            <span class="eo-price-period">/<?php _e('miesiąc', 'estateoffice'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="eo-property-details">
                                    <?php if (!empty($property_data['area'])): ?>
                                        <span class="eo-property-area">
                                            <?php echo esc_html($property_data['area']); ?> m²
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($property_data['rooms'])): ?>
                                        <span class="eo-property-rooms">
                                            <?php echo esc_html($property_data['rooms']); ?> 
                                            <?php _e('pokoje', 'estateoffice'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($property_data['floor'])): ?>
                                        <span class="eo-property-floor">
                                            <?php echo esc_html($property_data['floor']); ?> 
                                            <?php _e('piętro', 'estateoffice'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="eo-property-location">
                                    <?php if (!empty($property_data['city'])): ?>
                                        <span class="eo-property-city">
                                            <?php echo esc_html($property_data['city']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($property_data['district'])): ?>
                                        <span class="eo-property-district">
                                            <?php echo esc_html($property_data['district']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            // Paginacja
            the_posts_pagination(array(
                'mid_size' => 2,
                'prev_text' => __('Poprzednia strona', 'estateoffice'),
                'next_text' => __('Następna strona', 'estateoffice')
            ));
            ?>

        <?php else: ?>
            <div class="eo-no-properties">
                <p><?php _e('Nie znaleziono ofert spełniających podane kryteria.', 'estateoffice'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Aktualizacja dzielnic po zmianie miasta
    $('#city').on('change', function() {
        var city = $(this).val();
        var district = $('#district');
        
        district.prop('disabled', true);
        
        $.ajax({
            url: eoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'eo_get_districts',
                city: city,
                nonce: eoAjax.nonce
            },
            success: function(response) {
                district.empty().append($('<option>', {
                    value: '',
                    text: '<?php _e('Wszystkie', 'estateoffice'); ?>'
                }));
                
                if (response.success && response.data) {
                    $.each(response.data, function(i, item) {
                        district.append($('<option>', {
                            value: item.slug,
                            text: item.name
                        }));
                    });
                }
                
                district.prop('disabled', false);
            },
            error: function() {
                district.prop('disabled', false);
            }
        });
    });
});
</script>

<?php get_footer(); ?>