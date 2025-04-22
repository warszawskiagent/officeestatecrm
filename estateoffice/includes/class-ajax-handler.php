<?php
/**
 * Klasa obsługująca żądania AJAX
 *
 * @package EstateOffice
 * @subpackage Ajax
 * @since 0.5.5
 */

namespace EstateOffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AjaxHandler {

    /**
     * @var Security
     */
    private $security;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->security = new Security();
        $this->register_handlers();
    }

    /**
     * Rejestracja handlerów AJAX
     */
    private function register_handlers() {
        // Wyszukiwanie klientów
        add_action('wp_ajax_eo_search_clients', array($this, 'handle_client_search'));
        
        // Aktualizacja etapu umowy
        add_action('wp_ajax_eo_update_contract_stage', array($this, 'handle_contract_stage_update'));
        
        // Wyszukiwanie nieruchomości
        add_action('wp_ajax_eo_search_properties', array($this, 'handle_property_search'));
        
        // Dodawanie/edycja nieruchomości
        add_action('wp_ajax_eo_save_property', array($this, 'handle_property_save'));
        
        // Zapisywanie umowy
        add_action('wp_ajax_eo_save_contract', array($this, 'handle_contract_save'));
        
        // Pobieranie danych z Google Maps
        add_action('wp_ajax_eo_geocode_address', array($this, 'handle_geocode_address'));
        
        // Obsługa uploadu zdjęć
        add_action('wp_ajax_eo_upload_property_image', array($this, 'handle_image_upload'));
        
        // Sortowanie galerii
        add_action('wp_ajax_eo_sort_gallery', array($this, 'handle_gallery_sort'));
        
        // Usuwanie mediów
        add_action('wp_ajax_eo_delete_media', array($this, 'handle_media_delete'));
        
        // Aktualizacja statusu nieruchomości
        add_action('wp_ajax_eo_update_property_status', array($this, 'handle_property_status_update'));
    }

    /**
     * Obsługa wyszukiwania klientów
     */
    public function handle_client_search() {
        try {
            // Weryfikacja nonce
            check_ajax_referer('eo_search_clients', 'nonce');
            
            // Weryfikacja uprawnień
            $this->security->verify_agent_permissions();
            
            // Pobranie i sanityzacja parametrów
            $search_term = sanitize_text_field($_POST['search_term'] ?? '');
            $search_type = sanitize_text_field($_POST['search_type'] ?? 'all');
            
            // Walidacja danych wejściowych
            if (empty($search_term)) {
                wp_send_json_error(array(
                    'message' => __('Wprowadź tekst do wyszukania', 'estateoffice')
                ));
            }
            
            // Wykonanie wyszukiwania
            $clients = new Clients();
            $results = $clients->search($search_term, $search_type);
            
            wp_send_json_success(array(
                'results' => $results
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa aktualizacji etapu umowy
     */
    public function handle_contract_stage_update() {
        try {
            // Weryfikacja nonce
            check_ajax_referer('eo_update_contract_stage', 'nonce');
            
            // Weryfikacja uprawnień
            $this->security->verify_agent_permissions();
            
            // Pobranie i sanityzacja parametrów
            $contract_id = intval($_POST['contract_id'] ?? 0);
            $stage = sanitize_text_field($_POST['stage'] ?? '');
            $date = sanitize_text_field($_POST['date'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            
            // Walidacja danych
            if (!$contract_id || !$stage) {
                wp_send_json_error(array(
                    'message' => __('Brak wymaganych danych', 'estateoffice')
                ));
            }
            
            // Aktualizacja etapu
            $contracts = new Contracts();
            $result = $contracts->update_stage($contract_id, $stage, $date, $notes);
            
            wp_send_json_success(array(
                'message' => __('Etap umowy został zaktualizowany', 'estateoffice'),
                'stage' => $result
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa wyszukiwania nieruchomości
     */
    public function handle_property_search() {
        try {
            check_ajax_referer('eo_search_properties', 'nonce');
            $this->security->verify_agent_permissions();
            
            $search_params = $this->security->sanitize_property_search_params($_POST);
            
            $properties = new Properties();
            $results = $properties->search($search_params);
            
            wp_send_json_success(array(
                'results' => $results
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa zapisywania nieruchomości
     */
    public function handle_property_save() {
        try {
            check_ajax_referer('eo_save_property', 'nonce');
            $this->security->verify_agent_permissions();
            
            $property_data = $this->security->sanitize_property_data($_POST);
            
            $properties = new Properties();
            $property_id = $properties->save($property_data);
            
            wp_send_json_success(array(
                'property_id' => $property_id,
                'message' => __('Nieruchomość została zapisana', 'estateoffice')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa geokodowania adresu
     */
    public function handle_geocode_address() {
        try {
            check_ajax_referer('eo_geocode_address', 'nonce');
            $this->security->verify_agent_permissions();
            
            $address = sanitize_text_field($_POST['address'] ?? '');
            
            if (empty($address)) {
                wp_send_json_error(array(
                    'message' => __('Wprowadź adres', 'estateoffice')
                ));
            }
            
            $maps = new GoogleMaps();
            $coordinates = $maps->geocode_address($address);
            
            wp_send_json_success($coordinates);
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa uploadu zdjęć
     */
    public function handle_image_upload() {
        try {
            check_ajax_referer('eo_upload_image', 'nonce');
            $this->security->verify_agent_permissions();
            
            if (!isset($_FILES['file'])) {
                throw new \Exception(__('Nie wybrano pliku', 'estateoffice'));
            }
            
            $property_id = intval($_POST['property_id'] ?? 0);
            if (!$property_id) {
                throw new \Exception(__('Nieprawidłowy ID nieruchomości', 'estateoffice'));
            }
            
            $media = new PropertyMedia();
            $result = $media->upload_image($property_id, $_FILES['file']);
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa sortowania galerii
     */
    public function handle_gallery_sort() {
        try {
            check_ajax_referer('eo_sort_gallery', 'nonce');
            $this->security->verify_agent_permissions();
            
            $property_id = intval($_POST['property_id'] ?? 0);
            $order = array_map('intval', $_POST['order'] ?? array());
            
            if (!$property_id || empty($order)) {
                throw new \Exception(__('Nieprawidłowe dane sortowania', 'estateoffice'));
            }
            
            $media = new PropertyMedia();
            $media->update_sort_order($property_id, $order);
            
            wp_send_json_success(array(
                'message' => __('Kolejność zdjęć została zaktualizowana', 'estateoffice')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa usuwania mediów
     */
    public function handle_media_delete() {
        try {
            check_ajax_referer('eo_delete_media', 'nonce');
            $this->security->verify_agent_permissions();
            
            $media_id = intval($_POST['media_id'] ?? 0);
            if (!$media_id) {
                throw new \Exception(__('Nieprawidłowy ID medium', 'estateoffice'));
            }
            
            $media = new PropertyMedia();
            $media->delete($media_id);
            
            wp_send_json_success(array(
                'message' => __('Medium zostało usunięte', 'estateoffice')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Obsługa aktualizacji statusu nieruchomości
     */
    public function handle_property_status_update() {
        try {
            check_ajax_referer('eo_update_property_status', 'nonce');
            $this->security->verify_agent_permissions();
            
            $property_id = intval($_POST['property_id'] ?? 0);
            $status = sanitize_text_field($_POST['status'] ?? '');
            
            if (!$property_id || !$status) {
                throw new \Exception(__('Nieprawidłowe dane statusu', 'estateoffice'));
            }
            
            $properties = new Properties();
            $properties->update_status($property_id, $status);
            
            wp_send_json_success(array(
                'message' => __('Status nieruchomości został zaktualizowany', 'estateoffice')
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}