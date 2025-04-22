<?php
/**
 * Klasa odpowiedzialna za logowanie zdarzeń i błędów w systemie
 *
 * @package EstateOffice
 * @subpackage Debug
 * @since 0.5.5
 * @author Tomasz Obarski
 * @link http://warszawskiagent.pl
 */

namespace EstateOffice\Debug;

if (!defined('ABSPATH')) {
    exit;
}

class DebugLogger {
    /**
     * Ścieżka do pliku logów
     *
     * @var string
     */
    private static $log_file;

    /**
     * Maksymalny rozmiar pliku logów (5MB)
     *
     * @var int
     */
    private static $max_file_size = 5242880;

    /**
     * Dostępne poziomy logowania
     *
     * @var array
     */
    private static $log_levels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    ];

    /**
     * Inicjalizacja klasy logera
     *
     * @return void
     */
    public static function init() {
        self::$log_file = ESTATEOFFICE_PLUGIN_DIR . 'debug/logs/error.log';
        
        // Sprawdź czy katalog logów istnieje, jeśli nie - utwórz go
        if (!file_exists(dirname(self::$log_file))) {
            wp_mkdir_p(dirname(self::$log_file));
        }
        
        // Sprawdź czy plik logów istnieje, jeśli nie - utwórz go
        if (!file_exists(self::$log_file)) {
            touch(self::$log_file);
            chmod(self::$log_file, 0644);
        }
    }

    /**
     * Główna metoda logująca
     *
     * @param string $message Wiadomość do zalogowania
     * @param string $level Poziom logowania (emergency|alert|critical|error|warning|notice|info|debug)
     * @param array $context Dodatkowe dane kontekstowe
     * @return bool True jeśli logowanie się powiodło, false w przeciwnym razie
     */
    public static function log($message, $level = 'info', $context = []) {
        if (!WP_DEBUG) {
            return false;
        }

        self::init();

        // Walidacja poziomu logowania
        if (!array_key_exists($level, self::$log_levels)) {
            $level = 'info';
        }

        // Sprawdź rozmiar pliku i ewentualnie go zrotuj
        self::maybe_rotate_log_file();

        // Formatowanie wiadomości
        $timestamp = current_time('mysql');
        $formatted_message = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        // Zapis do pliku
        return error_log($formatted_message, 3, self::$log_file);
    }

    /**
     * Rotacja pliku logów jeśli przekracza maksymalny rozmiar
     *
     * @return void
     */
    private static function maybe_rotate_log_file() {
        if (!file_exists(self::$log_file)) {
            return;
        }

        if (filesize(self::$log_file) < self::$max_file_size) {
            return;
        }

        $backup_file = self::$log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename(self::$log_file, $backup_file);
        touch(self::$log_file);
        chmod(self::$log_file, 0644);
    }

    /**
     * Czyszczenie starych plików logów (starszych niż 30 dni)
     *
     * @return void
     */
    public static function cleanup_old_logs() {
        $log_dir = dirname(self::$log_file);
        if (!is_dir($log_dir)) {
            return;
        }

        $files = glob($log_dir . '/*.bak');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 30 * 24 * 60 * 60) { // 30 dni
                    unlink($file);
                }
            }
        }
    }

    /**
     * Metody pomocnicze dla różnych poziomów logowania
     */

    public static function emergency($message, $context = []) {
        self::log($message, 'emergency', $context);
    }

    public static function alert($message, $context = []) {
        self::log($message, 'alert', $context);
    }

    public static function critical($message, $context = []) {
        self::log($message, 'critical', $context);
    }

    public static function error($message, $context = []) {
        self::log($message, 'error', $context);
    }

    public static function warning($message, $context = []) {
        self::log($message, 'warning', $context);
    }

    public static function notice($message, $context = []) {
        self::log($message, 'notice', $context);
    }

    public static function info($message, $context = []) {
        self::log($message, 'info', $context);
    }

    public static function debug($message, $context = []) {
        self::log($message, 'debug', $context);
    }
}

// Dodaj akcję czyszczenia logów do crona WordPress
add_action('eo_cleanup_logs', ['EstateOffice\Debug\DebugLogger', 'cleanup_old_logs']);

// Zaplanuj czyszczenie logów raz dziennie
if (!wp_next_scheduled('eo_cleanup_logs')) {
    wp_schedule_event(time(), 'daily', 'eo_cleanup_logs');
}