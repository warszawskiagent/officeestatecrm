<?php
/**
 * Klasa odpowiedzialna za generowanie i zarządzanie ofertami na froncie
 *
 * @package EstateOffice
 * @subpackage Frontend
 * @since 0.5.5
 */

namespace EstateOffice\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class FrontendOffers {
    /**
     * @var string Prefix dla metadanych
     */
    private $meta_prefix = 'eo_';

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        add_action('init', array($this, 'register_offer_post_type'));
        add_action('template_redirect', array($this, 'handle_offer_display'));
        add_filter('single_template', array($this, 'load_offer_template'));
        add_filter('archive_template', array($this, 'load_archive_template'));
        add_action('pre_get_posts', array($this, 'modify_offers_query'));
    }

    /**
     * Rejestruje typ postu dla ofert
     */
    public function register_offer_post_type() {
        $labels = array(
            'name'               => __('Oferty', 'estateoffice'),
            'singular_name'      => __('Oferta', 'estateoffice'),
            'menu_name'          => __('Oferty', 'estateoffice'),
            'all_items'          => __('Wszystkie oferty', 'estateoffice'),
            'add_new'           => __('Dodaj nową', 'estateoffice'),
            'add_new_item'      => __('Dodaj nową ofertę', 'estateoffice'),
            'edit_item'         => __('Edytuj ofertę', 'estateoffice'),
            'new_item'          => __('Nowa oferta', 'estateoffice'),
            'view_item'         => __('Zobacz ofertę', 'estateoffice'),
            'search_items'      => __('Szukaj ofert', 'estateoffice'),
            'not_found'         => __('Nie znaleziono ofert', 'estateoffice'),
            'not_found_in_trash'=> __('Nie znaleziono ofert w koszu', 'estateoffice')
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'oferty'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array('title', 'editor', 'thumbnail', 'excerpt')
        );

        register_post_type('eo_offer', $args);
    }

    /**
     * Obsługuje wyświetlanie ofert na froncie
     */
    public function handle_offer_display() {
        if (!is_singular('eo_offer') && !is_post_type_archive('eo_offer')) {
            return;
        }

        // Sprawdź czy oferta ma być eksportowana na WWW
        if (is_singular('eo_offer')) {
            $post_id = get_the_ID();
            $export_to_www = get_post_meta($post_id, $this->meta_prefix . 'export_to_www', true);
            
            if (!$export_to_www) {
                wp_redirect(home_url());
                exit;
            }
        }
    }

    /**
     * Ładuje szablon dla pojedynczej oferty
     *
     * @param string $template Ścieżka do szablonu
     * @return string
     */
    public function load_offer_template($template) {
        if (is_singular('eo_offer')) {
            $new_template = ESTATEOFFICE_PLUGIN_DIR . 'templates/single-estateoffer.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Ładuje szablon dla archiwum ofert
     *
     * @param string $template Ścieżka do szablonu
     * @return string
     */
    public function load_archive_template($template) {
        if (is_post_type_archive('eo_offer')) {
            $new_template = ESTATEOFFICE_PLUGIN_DIR . 'templates/archive-estateoffer.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Modyfikuje zapytanie dla ofert
     *
     * @param \WP_Query $query Obiekt zapytania
     */
    public function modify_offers_query($query) {
        if (!is_admin() && $query->is_main_query() && 
            (is_post_type_archive('eo_offer') || 
             is_tax('eo_transaction_type') || 
             is_tax('eo_property_type') || 
             is_tax('eo_city') || 
             is_tax('eo_district'))) {
            
            // Pokaż tylko oferty z zaznaczonym eksportem na WWW
            $meta_query = array(
                array(
                    'key'     => $this->meta_prefix . 'export_to_www',
                    'value'   => '1',
                    'compare' => '='
                )
            );

            $query->set('meta_query', $meta_query);
            
            // Sortowanie domyślne
            if (!$query->get('orderby')) {
                $query->set('orderby', 'date');
                $query->set('order', 'DESC');
            }

            // Liczba ofert na stronie
            $query->set('posts_per_page', 12);
        }
    }

    /**
     * Generuje kod HTML dla galerii zdjęć oferty
     *
     * @param int $post_id ID oferty
     * @return string
     */
    public function get_offer_gallery($post_id) {
        $gallery_html = '';
        $gallery_images = get_post_meta($post_id, $this->meta_prefix . 'gallery_images', true);
        
        if (!empty($gallery_images)) {
            $gallery_html .= '<div class="eo-offer-gallery">';
            foreach ($gallery_images as $image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'large');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                
                $gallery_html .= sprintf(
                    '<div class="eo-gallery-item"><img src="%s" alt="%s" /></div>',
                    esc_url($image_url),
                    esc_attr($image_alt)
                );
            }
            $gallery_html .= '</div>';
        }
        
        return $gallery_html;
    }

    /**
     * Generuje szczegóły oferty
     *
     * @param int $post_id ID oferty
     * @return string
     */
    public function get_offer_details($post_id) {
        $details_html = '<div class="eo-offer-details">';
        
        // Podstawowe informacje
        $price = get_post_meta($post_id, $this->meta_prefix . 'price', true);
        $area = get_post_meta($post_id, $this->meta_prefix . 'area', true);
        $rooms = get_post_meta($post_id, $this->meta_prefix . 'rooms', true);
        
        // Lokalizacja
        $location = array(
            'street' => get_post_meta($post_id, $this->meta_prefix . 'street', true),
            'city' => get_post_meta($post_id, $this->meta_prefix . 'city', true),
            'district' => get_post_meta($post_id, $this->meta_prefix . 'district', true)
        );
        
        // Generowanie HTML dla szczegółów
        $details_html .= sprintf(
            '<div class="eo-price">%s PLN</div>',
            number_format($price, 2, ',', ' ')
        );
        
        $details_html .= sprintf(
            '<div class="eo-area">%s m²</div>',
            number_format($area, 2, ',', ' ')
        );
        
        if ($rooms) {
            $details_html .= sprintf(
                '<div class="eo-rooms">%d %s</div>',
                $rooms,
                _n('pokój', 'pokoje', $rooms, 'estateoffice')
            );
        }
        
        // Lokalizacja
        $details_html .= '<div class="eo-location">';
        if ($location['street']) {
            $details_html .= sprintf(
                '<div class="eo-street">%s</div>',
                esc_html($location['street'])
            );
        }
        if ($location['district']) {
            $details_html .= sprintf(
                '<div class="eo-district">%s</div>',
                esc_html($location['district'])
            );
        }
        if ($location['city']) {
            $details_html .= sprintf(
                '<div class="eo-city">%s</div>',
                esc_html($location['city'])
            );
        }
        $details_html .= '</div>';
        
        $details_html .= '</div>';
        
        return $details_html;
    }

    /**
     * Generuje dane agenta przypisanego do oferty
     *
     * @param int $post_id ID oferty
     * @return string
     */
    public function get_agent_card($post_id) {
        $agent_id = get_post_meta($post_id, $this->meta_prefix . 'agent_id', true);
        if (!$agent_id) {
            return '';
        }

        $agent = get_userdata($agent_id);
        if (!$agent) {
            return '';
        }

        $agent_html = '<div class="eo-agent-card">';
        
        // Zdjęcie agenta
        $photo_url = get_user_meta($agent_id, $this->meta_prefix . 'photo_url', true);
        if ($photo_url) {
            $agent_html .= sprintf(
                '<div class="eo-agent-photo"><img src="%s" alt="%s" /></div>',
                esc_url($photo_url),
                esc_attr($agent->display_name)
            );
        }
        
        // Dane agenta
        $agent_html .= sprintf(
            '<div class="eo-agent-name">%s</div>',
            esc_html($agent->display_name)
        );
        
        $agent_phone = get_user_meta($agent_id, $this->meta_prefix . 'phone', true);
        if ($agent_phone) {
            $agent_html .= sprintf(
                '<div class="eo-agent-phone"><a href="tel:%s">%s</a></div>',
                esc_attr($agent_phone),
                esc_html($agent_phone)
            );
        }
        
        $agent_html .= sprintf(
            '<div class="eo-agent-email"><a href="mailto:%s">%s</a></div>',
            esc_attr($agent->user_email),
            esc_html($agent->user_email)
        );
        
        $agent_html .= '</div>';
        
        return $agent_html;
    }
}