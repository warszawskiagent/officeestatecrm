/**
 * EstateOffice Frontend Main JavaScript
 * 
 * Główny plik JavaScript dla frontendu CRM
 * Obsługuje:
 * - Sortowanie i filtrowanie tabel
 * - Wyszukiwanie we wszystkich tabelach
 * - Obsługę formularzy
 * - Interakcje użytkownika
 * 
 * @package EstateOffice
 * @since 0.5.5
 */

// Namespace dla całej aplikacji
const EstateOffice = {
    // Konfiguracja
    config: {
        ajaxUrl: eoData.ajaxUrl,
        nonce: eoData.nonce,
        debug: eoData.debug || false,
        tableSelectors: {
            properties: '#eo-properties-table',
            contracts: '#eo-contracts-table',
            clients: '#eo-clients-table',
            searches: '#eo-searches-table'
        },
        searchDelay: 500 // Opóźnienie dla wyszukiwania (ms)
    },

    // Inicjalizacja wszystkich funkcjonalności
    init: function() {
        this.initTables();
        this.initSearch();
        this.initForms();
        this.initEventListeners();
        this.debug('EstateOffice Frontend initialized');
    },

    // Inicjalizacja tabel
    initTables: function() {
        const tables = document.querySelectorAll('[data-eo-table]');
        
        tables.forEach(table => {
            this.initTableSort(table);
            this.initTableFilters(table);
        });
    },

    // Inicjalizacja sortowania tabeli
    initTableSort: function(table) {
        const headers = table.querySelectorAll('th[data-sortable]');
        
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.column;
                const currentOrder = header.dataset.order || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                
                // Resetuj wszystkie nagłówki
                headers.forEach(h => {
                    h.dataset.order = '';
                    h.classList.remove('eo-sort-asc', 'eo-sort-desc');
                });
                
                // Ustaw nowy porządek
                header.dataset.order = newOrder;
                header.classList.add(`eo-sort-${newOrder}`);
                
                this.sortTable(table, column, newOrder);
            });
        });
    },

    // Sortowanie tabeli
    sortTable: function(table, column, order) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        const sortedRows = rows.sort((a, b) => {
            const aVal = a.querySelector(`td[data-column="${column}"]`).textContent;
            const bVal = b.querySelector(`td[data-column="${column}"]`).textContent;
            
            if (this.isNumeric(aVal) && this.isNumeric(bVal)) {
                return order === 'asc' ? 
                    parseFloat(aVal) - parseFloat(bVal) : 
                    parseFloat(bVal) - parseFloat(aVal);
            }
            
            return order === 'asc' ? 
                aVal.localeCompare(bVal) : 
                bVal.localeCompare(aVal);
        });
        
        // Wyczyść tabelę i dodaj posortowane wiersze
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }
        
        sortedRows.forEach(row => tbody.appendChild(row));
    },

    // Inicjalizacja wyszukiwania
    initSearch: function() {
        const searchInputs = document.querySelectorAll('.eo-search-input');
        
        searchInputs.forEach(input => {
            let debounceTimer;
            
            input.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                
                debounceTimer = setTimeout(() => {
                    const searchTerm = e.target.value;
                    const tableId = input.dataset.table;
                    
                    this.performSearch(tableId, searchTerm);
                }, this.config.searchDelay);
            });
        });
    },

    // Wykonanie wyszukiwania
    performSearch: function(tableId, searchTerm) {
        const table = document.querySelector(this.config.tableSelectors[tableId]);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const match = text.includes(searchTerm.toLowerCase());
            
            row.style.display = match ? '' : 'none';
        });
        
        this.updateNoResultsMessage(table, rows);
    },

    // Inicjalizacja formularzy
    initForms: function() {
        const forms = document.querySelectorAll('.eo-form');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
            
            // Inicjalizacja walidacji pól
            this.initFormValidation(form);
        });
    },

    // Obsługa wysyłania formularza
    handleFormSubmit: function(form) {
        if (!this.validateForm(form)) {
            return;
        }
        
        const formData = new FormData(form);
        formData.append('action', form.dataset.action);
        formData.append('nonce', this.config.nonce);
        
        this.showLoader(form);
        
        fetch(this.config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoader(form);
            
            if (data.success) {
                this.showMessage(form, data.message, 'success');
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
                if (data.reload) {
                    window.location.reload();
                }
            } else {
                this.showMessage(form, data.message || 'Wystąpił błąd', 'error');
            }
        })
        .catch(error => {
            this.hideLoader(form);
            this.showMessage(form, 'Wystąpił błąd połączenia', 'error');
            this.debug('Form submission error:', error);
        });
    },

    // Walidacja formularza
    validateForm: function(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                this.showFieldError(field, 'To pole jest wymagane');
            } else {
                this.clearFieldError(field);
            }
        });
        
        return isValid;
    },

    // Inicjalizacja nasłuchiwania zdarzeń
    initEventListeners: function() {
        // Obsługa przycisku "Dodaj nową Umowę"
        document.querySelectorAll('.eo-add-contract-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                window.location.href = eoData.addContractUrl;
            });
        });

        // Obsługa dynamicznych pól formularza
        this.initDynamicFormFields();
    },

    // Inicjalizacja dynamicznych pól formularza
    initDynamicFormFields: function() {
        // Obsługa zależnych pól select
        document.querySelectorAll('[data-depends-on]').forEach(field => {
            const parentField = document.getElementById(field.dataset.dependsOn);
            if (parentField) {
                parentField.addEventListener('change', () => {
                    this.updateDependentField(field, parentField);
                });
            }
        });
    },

    // Aktualizacja zależnego pola
    updateDependentField: function(field, parentField) {
        const dependencyMap = JSON.parse(field.dataset.dependencyMap);
        const parentValue = parentField.value;
        
        // Wyczyść obecne opcje
        while (field.options.length > 1) {
            field.remove(1);
        }
        
        // Dodaj nowe opcje
        if (dependencyMap[parentValue]) {
            dependencyMap[parentValue].forEach(option => {
                const opt = new Option(option.label, option.value);
                field.add(opt);
            });
            field.disabled = false;
        } else {
            field.disabled = true;
        }
    },

    // Pomocnicze funkcje
    isNumeric: function(str) {
        return !isNaN(str) && !isNaN(parseFloat(str));
    },

    showLoader: function(element) {
        const loader = element.querySelector('.eo-loader') || 
            this.createLoader();
        element.appendChild(loader);
    },

    hideLoader: function(element) {
        const loader = element.querySelector('.eo-loader');
        if (loader) {
            loader.remove();
        }
    },

    createLoader: function() {
        const loader = document.createElement('div');
        loader.className = 'eo-loader';
        return loader;
    },

    showMessage: function(element, message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `eo-message eo-message-${type}`;
        messageDiv.textContent = message;
        
        const existingMessage = element.querySelector('.eo-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        element.insertAdjacentElement('afterbegin', messageDiv);
        
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    },

    showFieldError: function(field, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'eo-field-error';
        errorDiv.textContent = message;
        
        const existingError = field.parentNode.querySelector('.eo-field-error');
        if (existingError) {
            existingError.remove();
        }
        
        field.parentNode.appendChild(errorDiv);
        field.classList.add('eo-field-invalid');
    },

    clearFieldError: function(field) {
        const errorDiv = field.parentNode.querySelector('.eo-field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        field.classList.remove('eo-field-invalid');
    },

    debug: function(...args) {
        if (this.config.debug) {
            console.log('[EstateOffice]', ...args);
        }
    },

    updateNoResultsMessage: function(table, rows) {
        let visibleRows = 0;
        rows.forEach(row => {
            if (row.style.display !== 'none') visibleRows++;
        });
        
        let noResults = table.querySelector('.eo-no-results');
        if (visibleRows === 0) {
            if (!noResults) {
                noResults = document.createElement('tr');
                noResults.className = 'eo-no-results';
                noResults.innerHTML = '<td colspan="100%">Brak wyników</td>';
                table.querySelector('tbody').appendChild(noResults);
            }
        } else if (noResults) {
            noResults.remove();
        }
    }
};

// Inicjalizacja po załadowaniu dokumentu
document.addEventListener('DOMContentLoaded', () => {
    EstateOffice.init();
});