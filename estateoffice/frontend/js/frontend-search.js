/**
 * EstateOffice Frontend Search
 * 
 * Obsługa wyszukiwania w tabelach CRM (nieruchomości, umowy, klienci, poszukiwania)
 * 
 * @package EstateOffice
 * @since 0.5.5
 */

(function($) {
    'use strict';

    // Konfiguracja wyszukiwania dla różnych typów tabel
    const searchConfig = {
        properties: {
            tableId: '#eo-properties-table',
            searchInput: '#eo-properties-search',
            columns: ['offer_number', 'address', 'price', 'price_per_meter', 'area', 'rooms', 'agent']
        },
        contracts: {
            tableId: '#eo-contracts-table',
            searchInput: '#eo-contracts-search',
            columns: ['contract_number', 'transaction_type', 'property_type', 'address', 'start_date', 'end_date', 'stage', 'agent']
        },
        clients: {
            tableId: '#eo-clients-table',
            searchInput: '#eo-clients-search',
            columns: ['name', 'address', 'phone', 'email', 'agent']
        },
        searches: {
            tableId: '#eo-searches-table',
            searchInput: '#eo-searches-search',
            columns: ['search_number', 'property_type', 'budget', 'location', 'transaction_type']
        }
    };

    // Klasa obsługująca wyszukiwanie
    class EOSearch {
        constructor(config) {
            this.config = config;
            this.table = $(config.tableId);
            this.searchInput = $(config.searchInput);
            this.rows = this.table.find('tbody tr');
            this.debounceTimeout = null;
            this.initializeSearch();
        }

        /**
         * Inicjalizacja nasłuchiwania na zmiany w polu wyszukiwania
         */
        initializeSearch() {
            this.searchInput.on('input', (e) => {
                clearTimeout(this.debounceTimeout);
                this.debounceTimeout = setTimeout(() => {
                    this.performSearch(e.target.value.toLowerCase());
                }, 300);
            });
        }

        /**
         * Wykonanie wyszukiwania w tabeli
         * @param {string} searchTerm - Fraza do wyszukania
         */
        performSearch(searchTerm) {
            if (!searchTerm) {
                this.rows.show();
                return;
            }

            this.rows.each((index, row) => {
                const $row = $(row);
                let found = false;

                // Przeszukiwanie wszystkich kolumn zdefiniowanych w konfiguracji
                this.config.columns.forEach(column => {
                    const cellContent = $row.find(`[data-column="${column}"]`).text().toLowerCase();
                    if (cellContent.includes(searchTerm)) {
                        found = true;
                    }
                });

                $row.toggle(found);
            });

            // Emitowanie eventu o zakończeniu wyszukiwania
            this.table.trigger('eo:searchComplete', {
                term: searchTerm,
                visibleRows: this.getVisibleRowsCount()
            });
        }

        /**
         * Pobranie liczby widocznych wierszy
         * @return {number} Liczba widocznych wierszy
         */
        getVisibleRowsCount() {
            return this.rows.filter(':visible').length;
        }

        /**
         * Resetowanie wyszukiwania
         */
        reset() {
            this.searchInput.val('');
            this.rows.show();
        }
    }

    // Inicjalizacja wyszukiwania dla wszystkich tabel
    function initializeSearchForAllTables() {
        // Sprawdzenie czy jesteśmy na stronie CRM
        if (!document.querySelector('.eo-crm-wrapper')) {
            return;
        }

        // Inicjalizacja wyszukiwania dla każdej tabeli
        Object.values(searchConfig).forEach(config => {
            if ($(config.tableId).length) {
                new EOSearch(config);
            }
        });

        // Nasłuchiwanie na zdarzenie przeładowania tabeli przez AJAX
        $(document).on('eo:tableReloaded', function(e, tableId) {
            const config = Object.values(searchConfig).find(c => c.tableId === tableId);
            if (config) {
                new EOSearch(config);
            }
        });
    }

    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        initializeSearchForAllTables();
    });

    // Eksport do globalnego obiektu
    window.EOSearch = EOSearch;

})(jQuery);

// Dodatkowe handlery dla specyficznych przypadków wyszukiwania

/**
 * Handler dla zaawansowanego wyszukiwania z filtrowaniem
 */
function handleAdvancedSearch() {
    const advancedSearchForm = document.querySelector('.eo-advanced-search-form');
    if (!advancedSearchForm) return;

    advancedSearchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const searchParams = new URLSearchParams(formData);

        // Wywołanie AJAX do backendu
        jQuery.ajax({
            url: eoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'eo_advanced_search',
                nonce: eoAdmin.nonce,
                params: searchParams.toString()
            },
            success: function(response) {
                if (response.success) {
                    updateTableWithResults(response.data);
                } else {
                    showError(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Błąd wyszukiwania:', error);
                showError('Wystąpił błąd podczas wyszukiwania. Spróbuj ponownie.');
            }
        });
    });
}

/**
 * Aktualizacja tabeli wynikami wyszukiwania
 * @param {Object} results - Wyniki wyszukiwania
 */
function updateTableWithResults(results) {
    const tableBody = document.querySelector('.eo-search-results tbody');
    if (!tableBody) return;

    tableBody.innerHTML = '';

    results.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = createRowHTML(item);
        tableBody.appendChild(row);
    });

    // Aktualizacja licznika wyników
    const resultsCounter = document.querySelector('.eo-results-counter');
    if (resultsCounter) {
        resultsCounter.textContent = `Znaleziono: ${results.length}`;
    }
}

/**
 * Wyświetlenie komunikatu o błędzie
 * @param {string} message - Treść komunikatu
 */
function showError(message) {
    const errorContainer = document.querySelector('.eo-search-error');
    if (errorContainer) {
        errorContainer.textContent = message;
        errorContainer.style.display = 'block';
        setTimeout(() => {
            errorContainer.style.display = 'none';
        }, 5000);
    }
}

// Inicjalizacja dodatkowych handlerów
document.addEventListener('DOMContentLoaded', function() {
    handleAdvancedSearch();
});