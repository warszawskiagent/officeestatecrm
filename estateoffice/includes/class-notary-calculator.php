<?php
/**
 * Kalkulator kosztów notarialnych
 *
 * @package EstateOffice
 * @subpackage Calculators
 * @since 0.5.5
 */

namespace EstateOffice;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Klasa NotaryCalculator - odpowiada za obliczanie kosztów notarialnych
 */
class NotaryCalculator {
    /**
     * Stawki taksy notarialnej (zgodnie z Rozporządzeniem Ministra Sprawiedliwości)
     * @var array
     */
    private $notary_fees = [
        10000 => 100,
        30000 => 300,
        60000 => 600,
        1000000 => 1000,
        2000000 => 2000,
        PHP_FLOAT_MAX => 3000
    ];

    /**
     * Stawka VAT dla usług notarialnych
     * @var float
     */
    private $vat_rate = 0.23;

    /**
     * Taksa notarialna od czynności dodatkowych
     * @var array
     */
    private $additional_fees = [
        'wypis_pierwszy' => 100,
        'wypis_kolejny' => 50,
        'wniosek_ksiega_wieczysta' => 200
    ];

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        add_action('wp_ajax_eo_calculate_notary_costs', [$this, 'ajax_calculate_costs']);
        add_action('wp_ajax_nopriv_eo_calculate_notary_costs', [$this, 'ajax_calculate_costs']);
    }

    /**
     * Oblicza podstawową taksę notarialną
     *
     * @param float $property_value Wartość nieruchomości
     * @return float Wysokość taksy notarialnej
     */
    public function calculate_base_fee($property_value) {
        $fee = 0;
        $remaining_value = $property_value;

        foreach ($this->notary_fees as $threshold => $rate) {
            if ($remaining_value <= 0) {
                break;
            }

            $chunk = min($threshold, $remaining_value);
            $fee += ($chunk * $rate) / 10000;
            $remaining_value -= $chunk;
        }

        return round($fee, 2);
    }

    /**
     * Oblicza podatek PCC
     *
     * @param float $property_value Wartość nieruchomości
     * @param string $transaction_type Typ transakcji (pierwotny/wtorny)
     * @return float Wysokość podatku PCC
     */
    public function calculate_pcc($property_value, $transaction_type = 'wtorny') {
        if ($transaction_type === 'pierwotny') {
            return 0;
        }
        return round($property_value * 0.02, 2); // 2% dla rynku wtórnego
    }

    /**
     * Oblicza opłatę sądową
     *
     * @param float $property_value Wartość nieruchomości
     * @return float Wysokość opłaty sądowej
     */
    public function calculate_court_fee($property_value) {
        $base_fee = 200; // Podstawowa opłata za wpis
        return $base_fee;
    }

    /**
     * Oblicza wszystkie koszty notarialne
     *
     * @param array $params Parametry kalkulacji
     * @return array Zestawienie wszystkich kosztów
     */
    public function calculate_total_costs($params) {
        $this->validate_params($params);

        $property_value = floatval($params['property_value']);
        $transaction_type = sanitize_text_field($params['transaction_type']);
        $additional_copies = intval($params['additional_copies'] ?? 0);

        $base_fee = $this->calculate_base_fee($property_value);
        $vat = $base_fee * $this->vat_rate;
        $pcc = $this->calculate_pcc($property_value, $transaction_type);
        $court_fee = $this->calculate_court_fee($property_value);
        
        $additional_costs = $this->calculate_additional_costs($additional_copies);

        return [
            'base_fee' => $base_fee,
            'vat' => round($vat, 2),
            'pcc' => $pcc,
            'court_fee' => $court_fee,
            'additional_costs' => $additional_costs,
            'total' => round($base_fee + $vat + $pcc + $court_fee + $additional_costs, 2)
        ];
    }

    /**
     * Oblicza koszty dodatkowe
     *
     * @param int $additional_copies Liczba dodatkowych wypisów
     * @return float Suma kosztów dodatkowych
     */
    private function calculate_additional_costs($additional_copies) {
        $costs = $this->additional_fees['wypis_pierwszy'];
        $costs += $additional_copies * $this->additional_fees['wypis_kolejny'];
        $costs += $this->additional_fees['wniosek_ksiega_wieczysta'];
        
        return $costs;
    }

    /**
     * Waliduje parametry wejściowe
     *
     * @param array $params Parametry do walidacji
     * @throws \InvalidArgumentException
     */
    private function validate_params($params) {
        if (!isset($params['property_value']) || !is_numeric($params['property_value'])) {
            throw new \InvalidArgumentException('Nieprawidłowa wartość nieruchomości');
        }

        if ($params['property_value'] <= 0) {
            throw new \InvalidArgumentException('Wartość nieruchomości musi być większa od 0');
        }

        if (!isset($params['transaction_type']) || 
            !in_array($params['transaction_type'], ['pierwotny', 'wtorny'])) {
            throw new \InvalidArgumentException('Nieprawidłowy typ transakcji');
        }
    }

    /**
     * Obsługuje żądanie AJAX do obliczenia kosztów
     */
    public function ajax_calculate_costs() {
        check_ajax_referer('eo_notary_calculator', 'nonce');

        try {
            $params = [
                'property_value' => floatval($_POST['property_value']),
                'transaction_type' => sanitize_text_field($_POST['transaction_type']),
                'additional_copies' => intval($_POST['additional_copies'] ?? 0)
            ];

            $costs = $this->calculate_total_costs($params);
            wp_send_json_success($costs);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Formatuje kwotę do wyświetlenia
     *
     * @param float $amount Kwota do sformatowania
     * @return string Sformatowana kwota
     */
    public function format_amount($amount) {
        return number_format($amount, 2, ',', ' ') . ' zł';
    }

    /**
     * Zwraca opis kalkulatora
     *
     * @return string Opis kalkulatora
     */
    public function get_calculator_description() {
        return __(
            'Kalkulator notarialny pozwala oszacować koszty związane z zawarciem umowy ' .
            'notarialnej przy zakupie nieruchomości. Uwzględnia taksę notarialną, VAT, ' .
            'podatek PCC oraz opłaty sądowe.',
            'estateoffice'
        );
    }
}