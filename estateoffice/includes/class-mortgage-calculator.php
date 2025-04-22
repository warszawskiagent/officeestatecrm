<?php
/**
 * Kalkulator kredytowy dla wtyczki EstateOffice
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
 * Klasa kalkulatora kredytowego
 * 
 * Oblicza raty kredytu, całkowity koszt kredytu, harmonogram spłat
 * oraz inne parametry związane z kredytem hipotecznym.
 *
 * @package EstateOffice
 * @since 0.5.5
 */
class MortgageCalculator {

    /**
     * Przechowuje instancję kalkulatora (Singleton)
     *
     * @var MortgageCalculator
     */
    private static $instance = null;

    /**
     * Stała określająca liczbę miesięcy w roku
     */
    const MONTHS_IN_YEAR = 12;

    /**
     * Konstruktor prywatny (Singleton)
     */
    private function __construct() {
        add_action('wp_ajax_eo_calculate_mortgage', array($this, 'ajax_calculate_mortgage'));
        add_action('wp_ajax_nopriv_eo_calculate_mortgage', array($this, 'ajax_calculate_mortgage'));
    }

    /**
     * Zwraca instancję kalkulatora (Singleton)
     *
     * @return MortgageCalculator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Oblicza miesięczną ratę kredytu (równe raty)
     *
     * @param float $loan_amount Kwota kredytu
     * @param float $annual_interest_rate Roczna stopa procentowa (w %)
     * @param int $loan_term_years Okres kredytowania w latach
     * @return float Miesięczna rata
     */
    public function calculate_monthly_payment($loan_amount, $annual_interest_rate, $loan_term_years) {
        // Konwersja stopy procentowej na miesięczną
        $monthly_interest_rate = ($annual_interest_rate / 100) / self::MONTHS_IN_YEAR;
        
        // Całkowita liczba rat
        $number_of_payments = $loan_term_years * self::MONTHS_IN_YEAR;
        
        // Wzór na ratę równą: PMT = P[r(1+r)^n]/[(1+r)^n-1]
        $monthly_payment = $loan_amount * 
            ($monthly_interest_rate * pow(1 + $monthly_interest_rate, $number_of_payments)) / 
            (pow(1 + $monthly_interest_rate, $number_of_payments) - 1);
            
        return round($monthly_payment, 2);
    }

    /**
     * Oblicza całkowity koszt kredytu
     *
     * @param float $monthly_payment Miesięczna rata
     * @param int $loan_term_years Okres kredytowania w latach
     * @param float $loan_amount Kwota kredytu
     * @return array Tablica z kosztami
     */
    public function calculate_total_cost($monthly_payment, $loan_term_years, $loan_amount) {
        $total_payment = $monthly_payment * $loan_term_years * self::MONTHS_IN_YEAR;
        $total_interest = $total_payment - $loan_amount;

        return array(
            'total_payment' => round($total_payment, 2),
            'total_interest' => round($total_interest, 2)
        );
    }

    /**
     * Generuje harmonogram spłat kredytu
     *
     * @param float $loan_amount Kwota kredytu
     * @param float $annual_interest_rate Roczna stopa procentowa
     * @param int $loan_term_years Okres kredytowania w latach
     * @return array Tablica z harmonogramem spłat
     */
    public function generate_payment_schedule($loan_amount, $annual_interest_rate, $loan_term_years) {
        $monthly_interest_rate = ($annual_interest_rate / 100) / self::MONTHS_IN_YEAR;
        $monthly_payment = $this->calculate_monthly_payment($loan_amount, $annual_interest_rate, $loan_term_years);
        $remaining_balance = $loan_amount;
        $schedule = array();

        for ($month = 1; $month <= $loan_term_years * self::MONTHS_IN_YEAR; $month++) {
            $interest_payment = $remaining_balance * $monthly_interest_rate;
            $principal_payment = $monthly_payment - $interest_payment;
            $remaining_balance -= $principal_payment;

            $schedule[] = array(
                'month' => $month,
                'payment' => round($monthly_payment, 2),
                'principal' => round($principal_payment, 2),
                'interest' => round($interest_payment, 2),
                'balance' => round($remaining_balance, 2)
            );
        }

        return $schedule;
    }

    /**
     * Oblicza maksymalną zdolność kredytową
     *
     * @param float $monthly_income Miesięczny dochód
     * @param float $monthly_expenses Miesięczne wydatki
     * @param float $annual_interest_rate Roczna stopa procentowa
     * @param int $loan_term_years Okres kredytowania w latach
     * @return float Maksymalna kwota kredytu
     */
    public function calculate_max_loan_amount($monthly_income, $monthly_expenses, $annual_interest_rate, $loan_term_years) {
        // Przyjmujemy, że rata nie może przekroczyć 50% dochodów rozporządzalnych
        $max_monthly_payment = ($monthly_income - $monthly_expenses) * 0.5;
        
        // Konwersja stopy procentowej na miesięczną
        $monthly_interest_rate = ($annual_interest_rate / 100) / self::MONTHS_IN_YEAR;
        
        // Całkowita liczba rat
        $number_of_payments = $loan_term_years * self::MONTHS_IN_YEAR;
        
        // Wzór odwrotny do raty równej: PV = PMT[(1+r)^n-1]/[r(1+r)^n]
        $max_loan = $max_monthly_payment * 
            (pow(1 + $monthly_interest_rate, $number_of_payments) - 1) / 
            ($monthly_interest_rate * pow(1 + $monthly_interest_rate, $number_of_payments));
            
        return round($max_loan, 2);
    }

    /**
     * Obsługuje żądanie AJAX do kalkulacji kredytu
     */
    public function ajax_calculate_mortgage() {
        check_ajax_referer('eo_mortgage_calculator', 'nonce');

        $loan_amount = filter_input(INPUT_POST, 'loan_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $annual_interest_rate = filter_input(INPUT_POST, 'annual_interest_rate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $loan_term_years = filter_input(INPUT_POST, 'loan_term_years', FILTER_SANITIZE_NUMBER_INT);

        if (!$loan_amount || !$annual_interest_rate || !$loan_term_years) {
            wp_send_json_error(array('message' => __('Nieprawidłowe parametry', 'estateoffice')));
            return;
        }

        $monthly_payment = $this->calculate_monthly_payment($loan_amount, $annual_interest_rate, $loan_term_years);
        $total_cost = $this->calculate_total_cost($monthly_payment, $loan_term_years, $loan_amount);
        $schedule = $this->generate_payment_schedule($loan_amount, $annual_interest_rate, $loan_term_years);

        wp_send_json_success(array(
            'monthly_payment' => $monthly_payment,
            'total_cost' => $total_cost,
            'schedule' => $schedule
        ));
    }

    /**
     * Renderuje formularz kalkulatora kredytowego
     *
     * @return string HTML formularza
     */
    public function render_calculator_form() {
        ob_start();
        ?>
        <form id="eo-mortgage-calculator" class="eo-calculator-form">
            <?php wp_nonce_field('eo_mortgage_calculator', 'eo_mortgage_calculator_nonce'); ?>
            
            <div class="eo-form-row">
                <label for="loan_amount"><?php _e('Kwota kredytu', 'estateoffice'); ?></label>
                <input type="number" id="loan_amount" name="loan_amount" step="1000" required>
            </div>

            <div class="eo-form-row">
                <label for="annual_interest_rate"><?php _e('Oprocentowanie roczne (%)', 'estateoffice'); ?></label>
                <input type="number" id="annual_interest_rate" name="annual_interest_rate" step="0.1" required>
            </div>

            <div class="eo-form-row">
                <label for="loan_term_years"><?php _e('Okres kredytowania (lata)', 'estateoffice'); ?></label>
                <input type="number" id="loan_term_years" name="loan_term_years" min="1" max="35" required>
            </div>

            <button type="submit" class="eo-button eo-button-primary">
                <?php _e('Oblicz', 'estateoffice'); ?>
            </button>
        </form>

        <div id="eo-calculator-results" class="eo-calculator-results" style="display: none;">
            <!-- Wyniki będą wstawiane tutaj przez JavaScript -->
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Inicjalizuje skrypty i style kalkulatora
     */
    public function enqueue_calculator_assets() {
        wp_enqueue_style(
            'eo-calculator',
            ESTATEOFFICE_PLUGIN_URL . 'assets/css/calculator.css',
            array(),
            ESTATEOFFICE_VERSION
        );

        wp_enqueue_script(
            'eo-calculator',
            ESTATEOFFICE_PLUGIN_URL . 'assets/js/calculator.js',
            array('jquery'),
            ESTATEOFFICE_VERSION,
            true
        );

        wp_localize_script('eo-calculator', 'eoCalculator', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eo_mortgage_calculator'),
            'i18n' => array(
                'monthlyPayment' => __('Miesięczna rata', 'estateoffice'),
                'totalCost' => __('Całkowity koszt kredytu', 'estateoffice'),
                'totalInterest' => __('Całkowity koszt odsetek', 'estateoffice'),
                'error' => __('Wystąpił błąd podczas obliczeń', 'estateoffice')
            )
        ));
    }

    /**
     * Blokuje klonowanie obiektu (Singleton)
     */
    private function __clone() {}

    /**
     * Blokuje deserializację obiektu (Singleton)
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}