<?php
/**
 * Template Name: Profil Agenta
 * 
 * @package EstateOffice
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

get_header();

// Pobierz ID agenta z URL
$agent_id = get_query_var('agent_id');

// Pobierz dane agenta
$agent = new EstateOffice\Models\Agent($agent_id);
$agent_data = $agent->get_data();

if (!$agent_data) {
    ?>
    <div class="eo-error-message">
        <h2><?php _e('Agent nie został znaleziony', 'estateoffice'); ?></h2>
        <p><?php _e('Przepraszamy, ale szukany agent nie istnieje lub został usunięty.', 'estateoffice'); ?></p>
    </div>
    <?php
    get_footer();
    return;
}

// Pobierz aktywne oferty agenta
$properties = new EstateOffice\Models\Properties();
$agent_properties = $properties->get_agent_properties($agent_id, array(
    'status' => 'available',
    'limit' => 6,
    'orderby' => 'created_at',
    'order' => 'DESC'
));

?>

<div class="eo-agent-profile">
    <!-- Sekcja nagłówkowa profilu agenta -->
    <div class="eo-agent-header">
        <div class="eo-agent-photo">
            <?php if ($agent_data->photo_url) : ?>
                <img src="<?php echo esc_url($agent_data->photo_url); ?>" 
                     alt="<?php echo esc_attr(sprintf(__('Zdjęcie agenta %s', 'estateoffice'), 
                           $agent_data->first_name . ' ' . $agent_data->last_name)); ?>" />
            <?php else : ?>
                <img src="<?php echo ESTATEOFFICE_PLUGIN_URL; ?>assets/images/default-agent.png" 
                     alt="<?php _e('Domyślne zdjęcie agenta', 'estateoffice'); ?>" />
            <?php endif; ?>
        </div>

        <div class="eo-agent-info">
            <h1 class="eo-agent-name">
                <?php echo esc_html($agent_data->first_name . ' ' . $agent_data->last_name); ?>
            </h1>
            
            <?php if ($agent_data->position) : ?>
                <div class="eo-agent-position">
                    <?php echo esc_html($agent_data->position); ?>
                </div>
            <?php endif; ?>

            <div class="eo-agent-contact">
                <?php if ($agent_data->phone) : ?>
                    <div class="eo-agent-phone">
                        <i class="eo-icon eo-icon-phone"></i>
                        <a href="tel:<?php echo esc_attr($agent_data->phone); ?>">
                            <?php echo esc_html($agent_data->phone); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($agent_data->mobile_phone) : ?>
                    <div class="eo-agent-mobile">
                        <i class="eo-icon eo-icon-mobile"></i>
                        <a href="tel:<?php echo esc_attr($agent_data->mobile_phone); ?>">
                            <?php echo esc_html($agent_data->mobile_phone); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php 
                $user_data = get_userdata($agent_data->user_id);
                if ($user_data && $user_data->user_email) : 
                ?>
                    <div class="eo-agent-email">
                        <i class="eo-icon eo-icon-email"></i>
                        <a href="mailto:<?php echo esc_attr($user_data->user_email); ?>">
                            <?php echo esc_html($user_data->user_email); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($agent_data->languages) : ?>
                <div class="eo-agent-languages">
                    <strong><?php _e('Języki:', 'estateoffice'); ?></strong>
                    <?php echo esc_html($agent_data->languages); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Opis agenta -->
    <?php if ($agent_data->description) : ?>
        <div class="eo-agent-description">
            <?php echo wp_kses_post($agent_data->description); ?>
        </div>
    <?php endif; ?>

    <!-- Aktywne oferty agenta -->
    <?php if ($agent_properties) : ?>
        <div class="eo-agent-properties">
            <h2><?php _e('Aktualne oferty', 'estateoffice'); ?></h2>
            
            <div class="eo-properties-grid">
                <?php foreach ($agent_properties as $property) : ?>
                    <div class="eo-property-card">
                        <?php estateoffice_get_template('property-card.php', array('property' => $property)); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($agent_properties) >= 6) : ?>
                <div class="eo-more-properties">
                    <a href="<?php echo esc_url(add_query_arg('agent', $agent_id, get_post_type_archive_link('eo_property'))); ?>" 
                       class="eo-button">
                        <?php _e('Zobacz wszystkie oferty', 'estateoffice'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Formularz kontaktowy -->
    <div class="eo-agent-contact-form">
        <h2><?php _e('Skontaktuj się z agentem', 'estateoffice'); ?></h2>
        <?php 
        $contact_form = new EstateOffice\Frontend\ContactForm();
        $contact_form->display(array(
            'agent_id' => $agent_id,
            'recipient_email' => $user_data->user_email,
            'recipient_name' => $agent_data->first_name . ' ' . $agent_data->last_name
        ));
        ?>
    </div>
</div>

<?php 
// Dodaj skrypty i style specyficzne dla profilu agenta
wp_enqueue_style('eo-agent-profile');
wp_enqueue_script('eo-agent-profile');

get_footer();