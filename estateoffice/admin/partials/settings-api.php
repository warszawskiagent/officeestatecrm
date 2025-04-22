<?php
/**
 * Szablon dla strony ustawień API wtyczki EstateOffice
 *
 * @package EstateOffice
 * @subpackage Admin/Partials
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem do pliku
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('manage_options')) {
    wp_die(__('Nie masz wystarczających uprawnień aby uzyskać dostęp do tej strony.', 'estateoffice'));
}

// Pobranie zapisanych wartości
$google_maps_api_key = get_option('eo_google_maps_api_key', '');
$watermark_id = get_option('eo_watermark_image_id', 0);
$logo_id = get_option('eo_company_logo_id', 0);

// Pobranie URL obrazów
$watermark_url = $watermark_id ? wp_get_attachment_image_url($watermark_id, 'medium') : '';
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

// Obsługa zapisywania formularza
if (isset($_POST['eo_save_settings'])) {
    if (!isset($_POST['eo_settings_nonce']) || !wp_verify_nonce($_POST['eo_settings_nonce'], 'eo_save_settings')) {
        wp_die(__('Błąd weryfikacji zabezpieczeń.', 'estateoffice'));
    }

    // Sanityzacja i zapis klucza API
    if (isset($_POST['eo_google_maps_api_key'])) {
        $api_key = sanitize_text_field($_POST['eo_google_maps_api_key']);
        update_option('eo_google_maps_api_key', $api_key);
    }

    // Obsługa przesyłania znaku wodnego
    if (isset($_POST['eo_watermark_image_id'])) {
        $watermark_id = absint($_POST['eo_watermark_image_id']);
        update_option('eo_watermark_image_id', $watermark_id);
    }

    // Obsługa przesyłania logo
    if (isset($_POST['eo_company_logo_id'])) {
        $logo_id = absint($_POST['eo_company_logo_id']);
        update_option('eo_company_logo_id', $logo_id);
    }

    // Komunikat o zapisaniu ustawień
    add_settings_error(
        'eo_settings',
        'eo_settings_updated',
        __('Ustawienia zostały zapisane.', 'estateoffice'),
        'updated'
    );
}

// Wyświetlenie komunikatów
settings_errors('eo_settings');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('eo_save_settings', 'eo_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="eo_google_maps_api_key">
                        <?php _e('Klucz API Google Maps', 'estateoffice'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="eo_google_maps_api_key" 
                           name="eo_google_maps_api_key" 
                           value="<?php echo esc_attr($google_maps_api_key); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Wprowadź klucz API Google Maps do obsługi map w ofertach nieruchomości.', 'estateoffice'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="eo_watermark_image">
                        <?php _e('Znak wodny', 'estateoffice'); ?>
                    </label>
                </th>
                <td>
                    <div class="eo-media-upload-container">
                        <input type="hidden" 
                               name="eo_watermark_image_id" 
                               id="eo_watermark_image_id" 
                               value="<?php echo esc_attr($watermark_id); ?>">
                        
                        <div class="eo-image-preview" 
                             id="eo_watermark_preview" 
                             style="<?php echo $watermark_url ? '' : 'display: none;'; ?>">
                            <?php if ($watermark_url): ?>
                                <img src="<?php echo esc_url($watermark_url); ?>" alt="Znak wodny">
                            <?php endif; ?>
                        </div>

                        <button type="button" 
                                class="button eo-upload-button" 
                                id="eo_upload_watermark_button" 
                                data-uploader-title="<?php _e('Wybierz znak wodny', 'estateoffice'); ?>" 
                                data-uploader-button-text="<?php _e('Użyj jako znak wodny', 'estateoffice'); ?>">
                            <?php _e('Wybierz obraz', 'estateoffice'); ?>
                        </button>

                        <button type="button" 
                                class="button eo-remove-button" 
                                id="eo_remove_watermark_button" 
                                style="<?php echo $watermark_url ? '' : 'display: none;'; ?>">
                            <?php _e('Usuń znak wodny', 'estateoffice'); ?>
                        </button>

                        <p class="description">
                            <?php _e('Wybierz obraz, który będzie używany jako znak wodny na zdjęciach nieruchomości.', 'estateoffice'); ?>
                        </p>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="eo_company_logo">
                        <?php _e('Logo biura', 'estateoffice'); ?>
                    </label>
                </th>
                <td>
                    <div class="eo-media-upload-container">
                        <input type="hidden" 
                               name="eo_company_logo_id" 
                               id="eo_company_logo_id" 
                               value="<?php echo esc_attr($logo_id); ?>">
                        
                        <div class="eo-image-preview" 
                             id="eo_logo_preview" 
                             style="<?php echo $logo_url ? '' : 'display: none;'; ?>">
                            <?php if ($logo_url): ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo biura">
                            <?php endif; ?>
                        </div>

                        <button type="button" 
                                class="button eo-upload-button" 
                                id="eo_upload_logo_button" 
                                data-uploader-title="<?php _e('Wybierz logo', 'estateoffice'); ?>" 
                                data-uploader-button-text="<?php _e('Użyj jako logo', 'estateoffice'); ?>">
                            <?php _e('Wybierz logo', 'estateoffice'); ?>
                        </button>

                        <button type="button" 
                                class="button eo-remove-button" 
                                id="eo_remove_logo_button" 
                                style="<?php echo $logo_url ? '' : 'display: none;'; ?>">
                            <?php _e('Usuń logo', 'estateoffice'); ?>
                        </button>

                        <p class="description">
                            <?php _e('Wybierz logo, które będzie wyświetlane na stronie i w materiałach biura.', 'estateoffice'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" 
                   name="eo_save_settings" 
                   class="button button-primary" 
                   value="<?php _e('Zapisz ustawienia', 'estateoffice'); ?>">
        </p>
    </form>
</div>

<!-- Skrypt inicjalizujący media uploader -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Funkcja inicjalizująca media uploader dla danego przycisku
    function initMediaUploader(uploadButton, removeButton, previewContainer, inputField) {
        var mediaUploader;

        uploadButton.on('click', function(e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: uploadButton.data('uploader-title'),
                button: {
                    text: uploadButton.data('uploader-button-text')
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                inputField.val(attachment.id);
                previewContainer.find('img').remove();
                previewContainer.append('<img src="' + attachment.url + '" alt="">');
                previewContainer.show();
                removeButton.show();
            });

            mediaUploader.open();
        });

        removeButton.on('click', function(e) {
            e.preventDefault();
            inputField.val('');
            previewContainer.hide().find('img').remove();
            removeButton.hide();
        });
    }

    // Inicjalizacja dla znaku wodnego
    initMediaUploader(
        $('#eo_upload_watermark_button'),
        $('#eo_remove_watermark_button'),
        $('#eo_watermark_preview'),
        $('#eo_watermark_image_id')
    );

    // Inicjalizacja dla logo
    initMediaUploader(
        $('#eo_upload_logo_button'),
        $('#eo_remove_logo_button'),
        $('#eo_logo_preview'),
        $('#eo_company_logo_id')
    );
});
</script>