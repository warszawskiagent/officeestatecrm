<?php
/**
 * Klasa odpowiedzialna za zarządzanie ustawieniami wtyczki w panelu administracyjnym
 *
 * @package EstateOffice
 * @subpackage Admin
 * @since 0.5.5
 */

namespace EstateOffice\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminSettings {
    /**
     * Prefix używany dla opcji w bazie danych
     *
     * @var string
     */
    private $option_prefix = 'eo_';

    /**
     * Przechowuje grupy pól ustawień
     *
     * @var array
     */
    private $setting_sections = array();

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Inicjalizacja ustawień
     */
    public function init_settings() {
        // Rejestracja sekcji ustawień
        $this->register_setting_sections();
        
        // Rejestracja pól dla każdej sekcji
        foreach ($this->setting_sections as $section) {
            $this->register_settings_fields($section);
        }

        // Rejestracja ustawień w WordPress
        register_setting(
            'estateoffice_settings',
            $this->option_prefix . 'settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Rejestracja sekcji ustawień
     */
    private function register_setting_sections() {
        $this->setting_sections = array(
            'api' => array(
                'id' => 'api',
                'title' => __('Ustawienia API', 'estateoffice'),
                'callback' => array($this, 'render_api_section'),
                'page' => 'estateoffice_settings'
            ),
            'branding' => array(
                'id' => 'branding',
                'title' => __('Branding', 'estateoffice'),
                'callback' => array($this, 'render_branding_section'),
                'page' => 'estateoffice_settings'
            ),
            'fields' => array(
                'id' => 'fields',
                'title' => __('Pola formularzy', 'estateoffice'),
                'callback' => array($this, 'render_fields_section'),
                'page' => 'estateoffice_settings'
            )
        );

        foreach ($this->setting_sections as $section) {
            add_settings_section(
                $section['id'],
                $section['title'],
                $section['callback'],
                $section['page']
            );
        }
    }

    /**
     * Rejestracja pól dla sekcji ustawień
     *
     * @param array $section Dane sekcji
     */
    private function register_settings_fields($section) {
        switch ($section['id']) {
            case 'api':
                add_settings_field(
                    'google_maps_api_key',
                    __('Klucz API Google Maps', 'estateoffice'),
                    array($this, 'render_text_field'),
                    $section['page'],
                    $section['id'],
                    array(
                        'label_for' => 'google_maps_api_key',
                        'description' => __('Wprowadź klucz API dla integracji z mapami Google', 'estateoffice')
                    )
                );
                break;

            case 'branding':
                // Pole dla znaku wodnego
                add_settings_field(
                    'watermark',
                    __('Znak wodny', 'estateoffice'),
                    array($this, 'render_image_upload_field'),
                    $section['page'],
                    $section['id'],
                    array(
                        'label_for' => 'watermark',
                        'description' => __('Wybierz obraz znaku wodnego', 'estateoffice')
                    )
                );

                // Pole dla logo
                add_settings_field(
                    'logo',
                    __('Logo biura', 'estateoffice'),
                    array($this, 'render_image_upload_field'),
                    $section['page'],
                    $section['id'],
                    array(
                        'label_for' => 'logo',
                        'description' => __('Wybierz logo biura', 'estateoffice')
                    )
                );
                break;

            case 'fields':
                // Pola dla nieruchomości
                add_settings_field(
                    'property_fields',
                    __('Pola nieruchomości', 'estateoffice'),
                    array($this, 'render_dynamic_fields'),
                    $section['page'],
                    $section['id'],
                    array(
                        'label_for' => 'property_fields',
                        'field_type' => 'property'
                    )
                );

                // Pola dla umów
                add_settings_field(
                    'contract_fields',
                    __('Pola umów', 'estateoffice'),
                    array($this, 'render_dynamic_fields'),
                    $section['page'],
                    $section['id'],
                    array(
                        'label_for' => 'contract_fields',
                        'field_type' => 'contract'
                    )
                );

                // Pola dla klientów
                add_settings_field(
                    'client_fields',
                    __('Pola klientów', 'estateoffice'),
                    array($this, 'render_dynamic_fields'),
                    $section['page'],
                    $section['id'],
                    array(
                        'label_for' => 'client_fields',
                        'field_type' => 'client'
                    )
                );
                break;
        }
    }

    /**
     * Dodanie strony ustawień do menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'estateoffice',
            __('Ustawienia EstateOffice', 'estateoffice'),
            __('Ustawienia', 'estateoffice'),
            'manage_options',
            'estateoffice-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Renderowanie strony ustawień
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień do dostępu do tej strony.', 'estateoffice'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('estateoffice_settings');
                do_settings_sections('estateoffice_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderowanie pola tekstowego
     *
     * @param array $args Argumenty pola
     */
    public function render_text_field($args) {
        $options = get_option($this->option_prefix . 'settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <input type="text" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($this->option_prefix . 'settings[' . $args['label_for'] . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description">
            <?php echo esc_html($args['description']); ?>
        </p>
        <?php
    }

    /**
     * Renderowanie pola upload obrazu
     *
     * @param array $args Argumenty pola
     */
    public function render_image_upload_field($args) {
        $options = get_option($this->option_prefix . 'settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <div class="image-upload-field">
            <input type="hidden"
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="<?php echo esc_attr($this->option_prefix . 'settings[' . $args['label_for'] . ']'); ?>"
                   value="<?php echo esc_attr($value); ?>">
            
            <div class="image-preview">
                <?php if ($value): ?>
                    <img src="<?php echo esc_url(wp_get_attachment_url($value)); ?>" alt="">
                <?php endif; ?>
            </div>

            <button type="button" class="button upload-image">
                <?php _e('Wybierz obraz', 'estateoffice'); ?>
            </button>
            
            <button type="button" class="button remove-image" <?php echo empty($value) ? 'style="display:none;"' : ''; ?>>
                <?php _e('Usuń obraz', 'estateoffice'); ?>
            </button>

            <p class="description">
                <?php echo esc_html($args['description']); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Renderowanie dynamicznych pól formularza
     *
     * @param array $args Argumenty pola
     */
    public function render_dynamic_fields($args) {
        $options = get_option($this->option_prefix . 'settings');
        $fields = isset($options[$args['label_for']]) ? $options[$args['label_for']] : array();
        ?>
        <div class="dynamic-fields" data-field-type="<?php echo esc_attr($args['field_type']); ?>">
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Nazwa pola', 'estateoffice'); ?></th>
                        <th><?php _e('Typ pola', 'estateoffice'); ?></th>
                        <th><?php _e('Wymagane', 'estateoffice'); ?></th>
                        <th><?php _e('Akcje', 'estateoffice'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field): ?>
                    <tr>
                        <td>
                            <input type="text" 
                                   name="<?php echo esc_attr($this->option_prefix . 'settings[' . $args['label_for'] . '][name][]'); ?>"
                                   value="<?php echo esc_attr($field['name']); ?>">
                        </td>
                        <td>
                            <select name="<?php echo esc_attr($this->option_prefix . 'settings[' . $args['label_for'] . '][type][]'); ?>">
                                <option value="text" <?php selected($field['type'], 'text'); ?>>
                                    <?php _e('Tekst', 'estateoffice'); ?>
                                </option>
                                <option value="number" <?php selected($field['type'], 'number'); ?>>
                                    <?php _e('Liczba', 'estateoffice'); ?>
                                </option>
                                <option value="select" <?php selected($field['type'], 'select'); ?>>
                                    <?php _e('Lista wyboru', 'estateoffice'); ?>
                                </option>
                            </select>
                        </td>
                        <td>
                            <input type="checkbox" 
                                   name="<?php echo esc_attr($this->option_prefix . 'settings[' . $args['label_for'] . '][required][]'); ?>"
                                   <?php checked($field['required'], true); ?>>
                        </td>
                        <td>
                            <button type="button" class="button remove-field">
                                <?php _e('Usuń', 'estateoffice'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="button add-field">
                <?php _e('Dodaj pole', 'estateoffice'); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Sanityzacja ustawień przed zapisem
     *
     * @param array $input Dane wejściowe
     * @return array Sanityzowane dane
     */
    public function sanitize_settings($input) {
        $sanitized_input = array();

        // Sanityzacja klucza API
        if (isset($input['google_maps_api_key'])) {
            $sanitized_input['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);
        }

        // Sanityzacja ID obrazów
        foreach (array('watermark', 'logo') as $image_field) {
            if (isset($input[$image_field])) {
                $sanitized_input[$image_field] = absint($input[$image_field]);
            }
        }

        // Sanityzacja pól dynamicznych
        foreach (array('property_fields', 'contract_fields', 'client_fields') as $field_type) {
            if (isset($input[$field_type]) && is_array($input[$field_type])) {
                $sanitized_input[$field_type] = array();
                foreach ($input[$field_type] as $field) {
                    $sanitized_input[$field_type][] = array(
                        'name' => sanitize_text_field($field['name']),
                        'type' => sanitize_text_field($field['type']),
                        'required' => isset($field['required']) ? (bool) $field['required'] : false
                    );
                }
            }
        }

        return $sanitized_input;
    }

    /**
     * Załadowanie potrzebnych skryptów i styli
     *
     * @param string $hook Aktualny hook strony administracyjnej
     */
    public function enqueue_assets($hook) {
        if ('estateoffice_page_estateoffice-settings' !== $hook) {
            return;
        }

        // Style
        wp_enqueue_style(
            'eo-admin-settings',
            ESTATEOFFICE_PLUGIN_URL . 'admin/css/admin-settings.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        // Media Uploader
        wp_enqueue_media();

        // Scripts
        wp_enqueue_script(
            'eo-admin-settings',
            ESTATEOFFICE_PLUGIN_URL . 'admin/js/admin-settings.js',
            array('jquery', 'jquery-ui-sortable'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_localize_script('eo-admin-settings', 'eoSettings', array(
            'confirmDelete' => __('Czy na pewno chcesz usunąć to pole?', 'estateoffice'),
            'nonce' => wp_create_nonce('eo_settings_nonce'),
            'i18n' => array(
                'selectImage' => __('Wybierz obraz', 'estateoffice'),
                'useImage' => __('Użyj tego obrazu', 'estateoffice'),
                'newField' => __('Nowe pole', 'estateoffice'),
                'text' => __('Tekst', 'estateoffice'),
                'number' => __('Liczba', 'estateoffice'),
                'select' => __('Lista wyboru', 'estateoffice'),
                'remove' => __('Usuń', 'estateoffice')
            )
        ));
    }

    /**
     * Renderowanie sekcji API
     */
    public function render_api_section() {
        echo '<p>' . __('Skonfiguruj integrację z zewnętrznymi API.', 'estateoffice') . '</p>';
    }

    /**
     * Renderowanie sekcji brandingu
     */
    public function render_branding_section() {
        echo '<p>' . __('Dostosuj wygląd wtyczki do swojej marki.', 'estateoffice') . '</p>';
    }

    /**
     * Renderowanie sekcji pól
     */
    public function render_fields_section() {
        echo '<p>' . __('Zarządzaj polami formularzy w całej wtyczce.', 'estateoffice') . '</p>';
    }

    /**
     * Pobieranie zapisanych ustawień
     *
     * @param string $key Klucz ustawienia
     * @param mixed $default Wartość domyślna
     * @return mixed
     */
    public function get_setting($key, $default = false) {
        $options = get_option($this->option_prefix . 'settings');
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Aktualizacja pojedynczego ustawienia
     *
     * @param string $key Klucz ustawienia
     * @param mixed $value Nowa wartość
     * @return bool
     */
    public function update_setting($key, $value) {
        $options = get_option($this->option_prefix . 'settings', array());
        $options[$key] = $value;
        return update_option($this->option_prefix . 'settings', $options);
    }

    /**
     * Usuwanie pojedynczego ustawienia
     *
     * @param string $key Klucz ustawienia
     * @return bool
     */
    public function delete_setting($key) {
        $options = get_option($this->option_prefix . 'settings', array());
        if (isset($options[$key])) {
            unset($options[$key]);
            return update_option($this->option_prefix . 'settings', $options);
        }
        return false;
    }
}