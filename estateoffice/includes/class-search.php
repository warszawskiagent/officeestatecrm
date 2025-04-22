<?php
/**
 * Klasa odpowiedzialna za funkcjonalność wyszukiwania
 *
 * @package EstateOffice
 * @subpackage Search
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit;
}

class Search {
    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var Security
     */
    private $security;

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->security = new Security();
    }

    /**
     * Wyszukiwanie nieruchomości według kryteriów
     *
     * @param array $criteria Kryteria wyszukiwania
     * @return array|WP_Error Wyniki wyszukiwania lub obiekt błędu
     */
    public function search_properties($criteria) {
        try {
            $this->security->verify_agent_permissions();
            
            $sanitized_criteria = $this->sanitize_property_criteria($criteria);
            
            $args = array(
                'post_type' => 'eo_property',
                'posts_per_page' => 20,
                'meta_query' => array('relation' => 'AND'),
                'tax_query' => array('relation' => 'AND')
            );

            // Typ transakcji
            if (!empty($sanitized_criteria['transaction_type'])) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'eo_transaction_type',
                    'field' => 'slug',
                    'terms' => $sanitized_criteria['transaction_type']
                );
            }

            // Rodzaj nieruchomości
            if (!empty($sanitized_criteria['property_type'])) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'eo_property_type',
                    'field' => 'slug',
                    'terms' => $sanitized_criteria['property_type']
                );
            }

            // Zakres cenowy
            if (!empty($sanitized_criteria['price_min']) || !empty($sanitized_criteria['price_max'])) {
                $price_query = array('key' => '_eo_property_price');
                
                if (!empty($sanitized_criteria['price_min'])) {
                    $price_query['value'] = array($sanitized_criteria['price_min'], PHP_FLOAT_MAX);
                    $price_query['compare'] = 'BETWEEN';
                    $price_query['type'] = 'NUMERIC';
                }
                
                if (!empty($sanitized_criteria['price_max'])) {
                    $price_query['value'] = array(0, $sanitized_criteria['price_max']);
                }
                
                $args['meta_query'][] = $price_query;
            }

            // Lokalizacja
            if (!empty($sanitized_criteria['city'])) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'eo_city',
                    'field' => 'slug',
                    'terms' => $sanitized_criteria['city']
                );

                if (!empty($sanitized_criteria['district'])) {
                    $args['tax_query'][] = array(
                        'taxonomy' => 'eo_district',
                        'field' => 'slug',
                        'terms' => $sanitized_criteria['district']
                    );
                }
            }

            // Metraż
            if (!empty($sanitized_criteria['area_min']) || !empty($sanitized_criteria['area_max'])) {
                $area_query = array('key' => '_eo_property_area');
                
                if (!empty($sanitized_criteria['area_min'])) {
                    $area_query['value'] = array($sanitized_criteria['area_min'], PHP_FLOAT_MAX);
                    $area_query['compare'] = 'BETWEEN';
                    $area_query['type'] = 'NUMERIC';
                }
                
                if (!empty($sanitized_criteria['area_max'])) {
                    $area_query['value'] = array(0, $sanitized_criteria['area_max']);
                }
                
                $args['meta_query'][] = $area_query;
            }

            $query = new \WP_Query($args);
            
            return array(
                'total' => $query->found_posts,
                'results' => $this->format_property_results($query->posts)
            );

        } catch (\Exception $e) {
            DebugLogger::log('Błąd wyszukiwania nieruchomości: ' . $e->getMessage(), 'error');
            return new \WP_Error('search_error', __('Wystąpił błąd podczas wyszukiwania nieruchomości', 'estateoffice'));
        }
    }

    /**
     * Wyszukiwanie klientów
     *
     * @param string $term Fraza wyszukiwania
     * @return array|WP_Error Wyniki wyszukiwania lub obiekt błędu
     */
    public function search_clients($term) {
        try {
            $this->security->verify_agent_permissions();
            
            $sanitized_term = $this->wpdb->esc_like(sanitize_text_field($term));
            
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}eo_clients 
                WHERE (name LIKE %s 
                OR email LIKE %s 
                OR phone LIKE %s) 
                AND status = 'active'
                LIMIT 20",
                "%{$sanitized_term}%",
                "%{$sanitized_term}%",
                "%{$sanitized_term}%"
            );

            $results = $this->wpdb->get_results($sql);
            
            return $this->format_client_results($results);

        } catch (\Exception $e) {
            DebugLogger::log('Błąd wyszukiwania klientów: ' . $e->getMessage(), 'error');
            return new \WP_Error('search_error', __('Wystąpił błąd podczas wyszukiwania klientów', 'estateoffice'));
        }
    }

    /**
     * Wyszukiwanie umów
     *
     * @param array $criteria Kryteria wyszukiwania
     * @return array|WP_Error Wyniki wyszukiwania lub obiekt błędu
     */
    public function search_contracts($criteria) {
        try {
            $this->security->verify_agent_permissions();
            
            $sanitized_criteria = $this->sanitize_contract_criteria($criteria);
            
            $where_conditions = array('1=1');
            $where_values = array();

            if (!empty($sanitized_criteria['contract_number'])) {
                $where_conditions[] = 'contract_number LIKE %s';
                $where_values[] = '%' . $this->wpdb->esc_like($sanitized_criteria['contract_number']) . '%';
            }

            if (!empty($sanitized_criteria['transaction_type'])) {
                $where_conditions[] = 'transaction_type = %s';
                $where_values[] = $sanitized_criteria['transaction_type'];
            }

            if (!empty($sanitized_criteria['date_from'])) {
                $where_conditions[] = 'start_date >= %s';
                $where_values[] = $sanitized_criteria['date_from'];
            }

            if (!empty($sanitized_criteria['date_to'])) {
                $where_conditions[] = 'end_date <= %s';
                $where_values[] = $sanitized_criteria['date_to'];
            }

            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}eo_contracts 
                WHERE " . implode(' AND ', $where_conditions) . "
                ORDER BY created_at DESC
                LIMIT 20",
                $where_values
            );

            $results = $this->wpdb->get_results($sql);
            
            return $this->format_contract_results($results);

        } catch (\Exception $e) {
            DebugLogger::log('Błąd wyszukiwania umów: ' . $e->getMessage(), 'error');
            return new \WP_Error('search_error', __('Wystąpił błąd podczas wyszukiwania umów', 'estateoffice'));
        }
    }

    /**
     * Sanityzacja kryteriów wyszukiwania nieruchomości
     *
     * @param array $criteria Kryteria wyszukiwania
     * @return array Sanityzowane kryteria
     */
    private function sanitize_property_criteria($criteria) {
        return array(
            'transaction_type' => isset($criteria['transaction_type']) ? sanitize_text_field($criteria['transaction_type']) : '',
            'property_type' => isset($criteria['property_type']) ? sanitize_text_field($criteria['property_type']) : '',
            'price_min' => isset($criteria['price_min']) ? floatval($criteria['price_min']) : null,
            'price_max' => isset($criteria['price_max']) ? floatval($criteria['price_max']) : null,
            'area_min' => isset($criteria['area_min']) ? floatval($criteria['area_min']) : null,
            'area_max' => isset($criteria['area_max']) ? floatval($criteria['area_max']) : null,
            'city' => isset($criteria['city']) ? sanitize_text_field($criteria['city']) : '',
            'district' => isset($criteria['district']) ? sanitize_text_field($criteria['district']) : ''
        );
    }

    /**
     * Sanityzacja kryteriów wyszukiwania umów
     *
     * @param array $criteria Kryteria wyszukiwania
     * @return array Sanityzowane kryteria
     */
    private function sanitize_contract_criteria($criteria) {
        return array(
            'contract_number' => isset($criteria['contract_number']) ? sanitize_text_field($criteria['contract_number']) : '',
            'transaction_type' => isset($criteria['transaction_type']) ? sanitize_text_field($criteria['transaction_type']) : '',
            'date_from' => isset($criteria['date_from']) ? sanitize_text_field($criteria['date_from']) : '',
            'date_to' => isset($criteria['date_to']) ? sanitize_text_field($criteria['date_to']) : ''
        );
    }

    /**
     * Formatowanie wyników wyszukiwania nieruchomości
     *
     * @param array $posts Wyniki zapytania WP_Query
     * @return array Sformatowane wyniki
     */
    private function format_property_results($posts) {
        $results = array();
        
        foreach ($posts as $post) {
            $results[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post),
                'price' => get_post_meta($post->ID, '_eo_property_price', true),
                'area' => get_post_meta($post->ID, '_eo_property_area', true),
                'address' => get_post_meta($post->ID, '_eo_property_address', true),
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
                'permalink' => get_permalink($post->ID)
            );
        }
        
        return $results;
    }

    /**
     * Formatowanie wyników wyszukiwania klientów
     *
     * @param array $results Wyniki zapytania bazy danych
     * @return array Sformatowane wyniki
     */
    private function format_client_results($results) {
        $formatted = array();
        
        foreach ($results as $result) {
            $formatted[] = array(
                'id' => $result->id,
                'name' => $result->name,
                'email' => $result->email,
                'phone' => $result->phone,
                'type' => $result->client_type,
                'address' => $result->address
            );
        }
        
        return $formatted;
    }

    /**
     * Formatowanie wyników wyszukiwania umów
     *
     * @param array $results Wyniki zapytania bazy danych
     * @return array Sformatowane wyniki
     */
    private function format_contract_results($results) {
        $formatted = array();
        
        foreach ($results as $result) {
            $formatted[] = array(
                'id' => $result->id,
                'contract_number' => $result->contract_number,
                'transaction_type' => $result->transaction_type,
                'start_date' => $result->start_date,
                'end_date' => $result->end_date,
                'status' => $result->status,
                'agent_id' => $result->agent_id
            );
        }
        
        return $formatted;
    }
}