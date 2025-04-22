<?php
/**
 * Formularz dodawania nowej umowy
 *
 * @package EstateOffice
 * @subpackage Frontend/Partials
 * @since 0.5.5
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Sprawdzenie uprawnień użytkownika
if (!current_user_can('edit_eo_contracts')) {
    wp_die(__('Nie masz uprawnień do wykonania tej operacji', 'estateoffice'));
}

// Generowanie unikalnego tokenu dla formularza
$nonce = wp_create_nonce('eo_add_contract_nonce');

?>

<div class="eo-add-contract-wrapper">
    <form id="eoAddContractForm" class="eo-form" method="post">
        <?php wp_nonce_field('eo_add_contract', 'eo_contract_nonce'); ?>
        
        <!-- Wskaźnik postępu -->
        <div class="eo-progress-bar">
            <div class="eo-progress-step active" data-step="1">
                <?php _e('Nowa Umowa', 'estateoffice'); ?>
            </div>
            <div class="eo-progress-step" data-step="2">
                <?php _e('Dodawanie Klienta', 'estateoffice'); ?>
            </div>
            <div class="eo-progress-step" data-step="3">
                <?php _e('Nieruchomość/Poszukiwanie', 'estateoffice'); ?>
            </div>
        </div>

        <!-- Etap 1: Nowa Umowa -->
        <div class="eo-form-step active" id="step1">
            <h2><?php _e('Etap 1: Nowa Umowa', 'estateoffice'); ?></h2>
            
            <!-- Numer umowy -->
            <div class="eo-form-group">
                <label for="contract_number" class="required">
                    <?php _e('Numer umowy', 'estateoffice'); ?>
                </label>
                <input type="text" 
                       id="contract_number" 
                       name="contract_number" 
                       class="eo-input" 
                       required 
                       data-validate="unique_contract"
                       placeholder="<?php _e('Wprowadź numer umowy', 'estateoffice'); ?>"
                >
                <div class="eo-validation-message"></div>
            </div>

            <!-- Typ transakcji -->
            <div class="eo-form-group">
                <label for="transaction_type" class="required">
                    <?php _e('Typ transakcji', 'estateoffice'); ?>
                </label>
                <select id="transaction_type" 
                        name="transaction_type" 
                        class="eo-select" 
                        required
                >
                    <option value=""><?php _e('Wybierz typ transakcji', 'estateoffice'); ?></option>
                    <option value="SPRZEDAZ"><?php _e('SPRZEDAŻ', 'estateoffice'); ?></option>
                    <option value="KUPNO"><?php _e('KUPNO', 'estateoffice'); ?></option>
                    <option value="WYNAJEM"><?php _e('WYNAJEM', 'estateoffice'); ?></option>
                    <option value="NAJEM"><?php _e('NAJEM', 'estateoffice'); ?></option>
                </select>
            </div>

            <!-- Daty -->
            <div class="eo-form-row">
                <div class="eo-form-group eo-col-6">
                    <label for="start_date" class="required">
                        <?php _e('Data zawarcia', 'estateoffice'); ?>
                    </label>
                    <input type="date" 
                           id="start_date" 
                           name="start_date" 
                           class="eo-input" 
                           required
                    >
                </div>
                <div class="eo-form-group eo-col-6">
                    <label for="end_date" id="end_date_label">
                        <?php _e('Data zakończenia', 'estateoffice'); ?>
                    </label>
                    <input type="date" 
                           id="end_date" 
                           name="end_date" 
                           class="eo-input"
                    >
                </div>
            </div>

            <!-- Umowa bezterminowa -->
            <div class="eo-form-group">
                <label class="eo-checkbox-label">
                    <input type="checkbox" 
                           id="indefinite_contract" 
                           name="indefinite_contract" 
                           class="eo-checkbox"
                    >
                    <?php _e('Umowa bezterminowa', 'estateoffice'); ?>
                </label>
            </div>

            <!-- Prowizja -->
            <div class="eo-form-row">
                <div class="eo-form-group eo-col-6">
                    <label for="commission_amount" class="required">
                        <?php _e('Wysokość prowizji', 'estateoffice'); ?>
                    </label>
                    <input type="number" 
                           id="commission_amount" 
                           name="commission_amount" 
                           class="eo-input" 
                           step="0.01" 
                           required
                    >
                </div>
                <div class="eo-form-group eo-col-6">
                    <label for="commission_currency" class="required">
                        <?php _e('Jednostka', 'estateoffice'); ?>
                    </label>
                    <select id="commission_currency" 
                            name="commission_currency" 
                            class="eo-select" 
                            required
                    >
                        <option value="%">%</option>
                        <option value="PLN">PLN</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>

            <!-- Przyciski nawigacji -->
            <div class="eo-form-navigation">
                <button type="button" 
                        class="eo-button eo-button-next" 
                        data-next="step2"
                >
                    <?php _e('DALEJ', 'estateoffice'); ?>
                </button>
            </div>
        </div>
		<!-- Etap 2: Dodawanie Klienta -->
        <div class="eo-form-step" id="step2">
            <h2><?php _e('Etap 2: Dodawanie Klienta', 'estateoffice'); ?></h2>

            <!-- Wyszukiwarka klientów -->
            <div class="eo-form-group">
                <label for="client_search">
                    <?php _e('Wyszukaj istniejącego klienta', 'estateoffice'); ?>
                </label>
                <div class="eo-search-wrapper">
                    <input type="text" 
                           id="client_search" 
                           class="eo-input eo-search-input" 
                           placeholder="<?php _e('Wyszukaj po imieniu, nazwisku, telefonie lub e-mail', 'estateoffice'); ?>"
                    >
                    <div id="client_search_results" class="eo-search-results"></div>
                </div>
            </div>

            <!-- Separator -->
            <div class="eo-separator">
                <span><?php _e('lub dodaj nowego klienta', 'estateoffice'); ?></span>
            </div>

            <!-- Typ klienta -->
            <div class="eo-form-group">
                <label for="client_type" class="required">
                    <?php _e('Typ klienta', 'estateoffice'); ?>
                </label>
                <select id="client_type" 
                        name="client_type" 
                        class="eo-select" 
                        required
                >
                    <option value=""><?php _e('Wybierz typ klienta', 'estateoffice'); ?></option>
                    <option value="individual"><?php _e('Osoba fizyczna', 'estateoffice'); ?></option>
                    <option value="company"><?php _e('Firma', 'estateoffice'); ?></option>
                </select>
            </div>

            <!-- Dane podstawowe - Osoba fizyczna -->
            <div class="eo-client-type-fields individual-fields" style="display: none;">
                <div class="eo-form-row">
                    <div class="eo-form-group eo-col-6">
                        <label for="individual_first_name" class="required">
                            <?php _e('Imię', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="individual_first_name" 
                               name="individual_first_name" 
                               class="eo-input"
                        >
                    </div>
                    <div class="eo-form-group eo-col-6">
                        <label for="individual_last_name" class="required">
                            <?php _e('Nazwisko', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="individual_last_name" 
                               name="individual_last_name" 
                               class="eo-input"
                        >
                    </div>
                </div>
            </div>

            <!-- Dane podstawowe - Firma -->
            <div class="eo-client-type-fields company-fields" style="display: none;">
                <div class="eo-form-group">
                    <label for="company_name" class="required">
                        <?php _e('Nazwa firmy', 'estateoffice'); ?>
                    </label>
                    <input type="text" 
                           id="company_name" 
                           name="company_name" 
                           class="eo-input"
                    >
                </div>
                <div class="eo-form-group">
                    <label for="company_representative" class="required">
                        <?php _e('Imię i nazwisko reprezentanta', 'estateoffice'); ?>
                    </label>
                    <input type="text" 
                           id="company_representative" 
                           name="company_representative" 
                           class="eo-input"
                    >
                </div>
            </div>

            <!-- Dane kontaktowe -->
            <div class="eo-form-section">
                <h3><?php _e('Dane kontaktowe', 'estateoffice'); ?></h3>
                <div class="eo-form-row">
                    <div class="eo-form-group eo-col-6">
                        <label for="client_phone" class="required">
                            <?php _e('Numer telefonu', 'estateoffice'); ?>
                        </label>
                        <input type="tel" 
                               id="client_phone" 
                               name="client_phone" 
                               class="eo-input" 
                               pattern="[0-9]{9}"
                        >
                    </div>
                    <div class="eo-form-group eo-col-6">
                        <label for="client_email" class="required">
                            <?php _e('Adres e-mail', 'estateoffice'); ?>
                        </label>
                        <input type="email" 
                               id="client_email" 
                               name="client_email" 
                               class="eo-input"
                        >
                    </div>
                </div>
                <div class="eo-client-type-fields company-fields" style="display: none;">
                    <div class="eo-form-group">
                        <label for="company_website">
                            <?php _e('Strona WWW', 'estateoffice'); ?>
                        </label>
                        <input type="url" 
                               id="company_website" 
                               name="company_website" 
                               class="eo-input"
                        >
                    </div>
                </div>
            </div>

            <!-- Dane identyfikacyjne -->
            <div class="eo-form-section">
                <h3><?php _e('Dane identyfikacyjne', 'estateoffice'); ?></h3>
                
                <!-- Dane dla osoby fizycznej -->
                <div class="eo-client-type-fields individual-fields" style="display: none;">
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-4">
                            <label for="individual_pesel">
                                <?php _e('PESEL', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="individual_pesel" 
                                   name="individual_pesel" 
                                   class="eo-input" 
                                   pattern="[0-9]{11}"
                            >
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label for="individual_document_type">
                                <?php _e('Rodzaj dokumentu', 'estateoffice'); ?>
                            </label>
                            <select id="individual_document_type" 
                                    name="individual_document_type" 
                                    class="eo-select"
                            >
                                <option value="id_card"><?php _e('Dowód osobisty', 'estateoffice'); ?></option>
                                <option value="passport"><?php _e('Paszport', 'estateoffice'); ?></option>
                                <option value="residence_card"><?php _e('Karta pobytu', 'estateoffice'); ?></option>
                            </select>
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label for="individual_document_number">
                                <?php _e('Numer dokumentu', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="individual_document_number" 
                                   name="individual_document_number" 
                                   class="eo-input"
                            >
                        </div>
                    </div>
                </div>

                <!-- Dane dla firmy -->
                <div class="eo-client-type-fields company-fields" style="display: none;">
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-4">
                            <label for="company_nip" class="required">
                                <?php _e('NIP', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="company_nip" 
                                   name="company_nip" 
                                   class="eo-input" 
                                   pattern="[0-9]{10}"
                            >
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label for="company_krs">
                                <?php _e('KRS', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="company_krs" 
                                   name="company_krs" 
                                   class="eo-input"
                            >
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label for="company_regon">
                                <?php _e('REGON', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="company_regon" 
                                   name="company_regon" 
                                   class="eo-input"
                            >
                        </div>
                    </div>
                </div>
            </div>
			<!-- Adres zamieszkania/rejestrowy -->
            <div class="eo-form-section">
                <h3><?php _e('Adres zamieszkania/rejestrowy', 'estateoffice'); ?></h3>
                <div class="eo-form-row">
                    <div class="eo-form-group eo-col-8">
                        <label for="address_street" class="required">
                            <?php _e('Ulica', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="address_street" 
                               name="address_street" 
                               class="eo-input"
                        >
                    </div>
                    <div class="eo-form-group eo-col-2">
                        <label for="address_number" class="required">
                            <?php _e('Numer', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="address_number" 
                               name="address_number" 
                               class="eo-input"
                        >
                    </div>
                    <div class="eo-form-group eo-col-2">
                        <label for="address_apartment">
                            <?php _e('Lokal', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="address_apartment" 
                               name="address_apartment" 
                               class="eo-input"
                        >
                    </div>
                </div>
                <div class="eo-form-row">
                    <div class="eo-form-group eo-col-4">
                        <label for="address_postal_code" class="required">
                            <?php _e('Kod pocztowy', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="address_postal_code" 
                               name="address_postal_code" 
                               class="eo-input" 
                               pattern="[0-9]{2}-[0-9]{3}"
                        >
                    </div>
                    <div class="eo-form-group eo-col-4">
                        <label for="address_city" class="required">
                            <?php _e('Miasto', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="address_city" 
                               name="address_city" 
                               class="eo-input"
                        >
                    </div>
                    <div class="eo-form-group eo-col-4">
                        <label for="address_country" class="required">
                            <?php _e('Kraj', 'estateoffice'); ?>
                        </label>
                        <input type="text" 
                               id="address_country" 
                               name="address_country" 
                               class="eo-input" 
                               value="Polska"
                        >
                    </div>
                </div>
            </div>

            <!-- Adres korespondencyjny -->
            <div class="eo-form-section">
                <h3><?php _e('Adres korespondencyjny', 'estateoffice'); ?></h3>
                <div class="eo-form-group">
                    <label class="eo-checkbox-label">
                        <input type="checkbox" 
                               id="same_correspondence_address" 
                               name="same_correspondence_address" 
                               class="eo-checkbox" 
                               checked
                        >
                        <?php _e('Adres korespondencyjny taki sam', 'estateoffice'); ?>
                    </label>
                </div>

                <!-- Pola adresu korespondencyjnego (domyślnie ukryte) -->
                <div id="correspondence_address_fields" style="display: none;">
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-8">
                            <label for="correspondence_street">
                                <?php _e('Ulica', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="correspondence_street" 
                                   name="correspondence_street" 
                                   class="eo-input"
                            >
                        </div>
                        <div class="eo-form-group eo-col-2">
                            <label for="correspondence_number">
                                <?php _e('Numer', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="correspondence_number" 
                                   name="correspondence_number" 
                                   class="eo-input"
                            >
                        </div>
                        <div class="eo-form-group eo-col-2">
                            <label for="correspondence_apartment">
                                <?php _e('Lokal', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="correspondence_apartment" 
                                   name="correspondence_apartment" 
                                   class="eo-input"
                            >
                        </div>
                    </div>
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-4">
                            <label for="correspondence_postal_code">
                                <?php _e('Kod pocztowy', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="correspondence_postal_code" 
                                   name="correspondence_postal_code" 
                                   class="eo-input" 
                                   pattern="[0-9]{2}-[0-9]{3}"
                            >
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label for="correspondence_city">
                                <?php _e('Miasto', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="correspondence_city" 
                                   name="correspondence_city" 
                                   class="eo-input"
                            >
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label for="correspondence_country">
                                <?php _e('Kraj', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="correspondence_country" 
                                   name="correspondence_country" 
                                   class="eo-input" 
                                   value="Polska"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Przyciski nawigacji dla etapu 2 -->
            <div class="eo-form-navigation">
                <button type="button" 
                        class="eo-button eo-button-prev" 
                        data-prev="step1"
                >
                    <?php _e('WSTECZ', 'estateoffice'); ?>
                </button>
                <button type="button" 
                        class="eo-button eo-button-next" 
                        data-next="step3"
                >
                    <?php _e('DALEJ', 'estateoffice'); ?>
                </button>
            </div>

            <!-- Pytanie o dodanie kolejnego klienta -->
            <div id="add_another_client_dialog" class="eo-dialog" style="display: none;">
                <div class="eo-dialog-content">
                    <h3><?php _e('Czy chcesz dodać kolejnego klienta?', 'estateoffice'); ?></h3>
                    <div class="eo-dialog-buttons">
                        <button type="button" 
                                class="eo-button" 
                                id="add_another_client_yes"
                        >
                            <?php _e('TAK', 'estateoffice'); ?>
                        </button>
                        <button type="button" 
                                class="eo-button" 
                                id="add_another_client_no"
                        >
                            <?php _e('NIE', 'estateoffice'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
		<!-- Etap 3: Nieruchomość/Poszukiwanie -->
        <div class="eo-form-step" id="step3">
            <!-- Dynamiczny nagłówek -->
            <h2 id="step3_header">
                <?php _e('Etap 3: ', 'estateoffice'); ?>
                <span class="property-form-header" style="display: none;">
                    <?php _e('Dodawanie Nieruchomości', 'estateoffice'); ?>
                </span>
                <span class="search-form-header" style="display: none;">
                    <?php _e('Dodawanie Poszukiwania', 'estateoffice'); ?>
                </span>
            </h2>

            <!-- Typ transakcji (readonly, wypełniane automatycznie) -->
            <div class="eo-form-group">
                <label for="step3_transaction_type">
                    <?php _e('Typ transakcji', 'estateoffice'); ?>
                </label>
                <input type="text" 
                       id="step3_transaction_type" 
                       class="eo-input" 
                       readonly
                >
            </div>

            <!-- Rodzaj nieruchomości -->
            <div class="eo-form-group">
                <label for="property_type" class="required">
                    <?php _e('Rodzaj nieruchomości', 'estateoffice'); ?>
                </label>
                <select id="property_type" 
                        name="property_type" 
                        class="eo-select" 
                        required
                >
                    <option value=""><?php _e('Wybierz rodzaj nieruchomości', 'estateoffice'); ?></option>
                    <option value="MIESZKANIE"><?php _e('MIESZKANIE', 'estateoffice'); ?></option>
                    <option value="DOM"><?php _e('DOM', 'estateoffice'); ?></option>
                    <option value="DZIALKA"><?php _e('DZIAŁKA', 'estateoffice'); ?></option>
                    <option value="LOKAL"><?php _e('LOKAL H/U', 'estateoffice'); ?></option>
                </select>
            </div>

            <!-- Formularz nieruchomości (dla SPRZEDAŻ/WYNAJEM) -->
            <div id="property_form" class="eo-conditional-form" style="display: none;">
                <!-- Dane adresowe -->
                <div class="eo-form-section">
                    <h3><?php _e('Dane Adresowe', 'estateoffice'); ?></h3>
                    
                    <!-- Pola dla MIESZKANIA lub LOKALU H/U -->
                    <div class="property-type-fields apartment-commercial-fields" style="display: none;">
                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-6">
                                <label for="property_street" class="required">
                                    <?php _e('Ulica', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_street" 
                                       name="property_street" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-3">
                                <label for="property_number" class="required">
                                    <?php _e('Numer', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_number" 
                                       name="property_number" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-3">
                                <label for="property_apartment">
                                    <?php _e('Lokal', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_apartment" 
                                       name="property_apartment" 
                                       class="eo-input"
                                >
                            </div>
                        </div>
                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-4">
                                <label for="property_postal_code" class="required">
                                    <?php _e('Kod pocztowy', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_postal_code" 
                                       name="property_postal_code" 
                                       class="eo-input" 
                                       pattern="[0-9]{2}-[0-9]{3}"
                                >
                            </div>
                            <div class="eo-form-group eo-col-4">
                                <label for="property_district">
                                    <?php _e('Dzielnica', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_district" 
                                       name="property_district" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-4">
                                <label for="property_city" class="required">
                                    <?php _e('Miasto', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_city" 
                                       name="property_city" 
                                       class="eo-input"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Pola dla DOMU lub DZIAŁKI -->
                    <div class="property-type-fields house-plot-fields" style="display: none;">
                        <!-- Typ domu (tylko dla DOMU) -->
                        <div class="house-only-fields" style="display: none;">
                            <div class="eo-form-group">
                                <label for="house_type" class="required">
                                    <?php _e('Typ domu', 'estateoffice'); ?>
                                </label>
                                <select id="house_type" 
                                        name="house_type" 
                                        class="eo-select"
                                >
                                    <option value="WOLNOSTOJACY"><?php _e('WOLNOSTOJĄCY', 'estateoffice'); ?></option>
                                    <option value="BLIZNIAK"><?php _e('BLIŹNIAK', 'estateoffice'); ?></option>
                                    <option value="SZEREGOWIEC"><?php _e('SZEREGOWIEC', 'estateoffice'); ?></option>
                                    <option value="WIELORODZINNY"><?php _e('WIELORODZINNY', 'estateoffice'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-6">
                                <label for="property_street">
                                    <?php _e('Ulica', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_street" 
                                       name="property_street" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-3">
                                <label for="property_number">
                                    <?php _e('Numer', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_number" 
                                       name="property_number" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-3 house-non-detached" style="display: none;">
                                <label for="property_apartment">
                                    <?php _e('Lokal', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_apartment" 
                                       name="property_apartment" 
                                       class="eo-input"
                                >
                            </div>
                        </div>

                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-4">
                                <label for="property_county">
                                    <?php _e('Powiat', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_county" 
                                       name="property_county" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-4">
                                <label for="property_district">
                                    <?php _e('Obręb', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_district" 
                                       name="property_district" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-4">
                                <label for="property_plot_number">
                                    <?php _e('Numer działki', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_plot_number" 
                                       name="property_plot_number" 
                                       class="eo-input"
                                >
                            </div>
                        </div>
						<div class="eo-form-row">
                            <div class="eo-form-group eo-col-4">
                                <label for="property_postal_code">
                                    <?php _e('Kod pocztowy', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_postal_code" 
                                       name="property_postal_code" 
                                       class="eo-input" 
                                       pattern="[0-9]{2}-[0-9]{3}"
                                >
                            </div>
                            <div class="eo-form-group eo-col-8">
                                <label for="property_city">
                                    <?php _e('Miasto', 'estateoffice'); ?>
                                </label>
                                <input type="text" 
                                       id="property_city" 
                                       name="property_city" 
                                       class="eo-input"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Księga Wieczysta i Stan Prawny -->
                <div class="eo-form-section">
                    <h3><?php _e('Księga Wieczysta i Stan Prawny', 'estateoffice'); ?></h3>
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-8">
                            <label for="property_kw">
                                <?php _e('Numer Księgi Wieczystej', 'estateoffice'); ?>
                            </label>
                            <input type="text" 
                                   id="property_kw" 
                                   name="property_kw" 
                                   class="eo-input"
                                   <?php echo ($has_kw ? '' : 'disabled'); ?>
                            >
                        </div>
                        <div class="eo-form-group eo-col-4">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" 
                                       id="no_kw" 
                                       name="no_kw" 
                                       class="eo-checkbox"
                                >
                                <?php _e('Brak KW', 'estateoffice'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="eo-form-group">
                        <label for="legal_status" class="required">
                            <?php _e('Stan prawny', 'estateoffice'); ?>
                        </label>
                        <select id="legal_status" 
                                name="legal_status" 
                                class="eo-select" 
                                required
                        >
                            <option value="ownership"><?php _e('Własność', 'estateoffice'); ?></option>
                            <option value="co-ownership"><?php _e('Współwłasność', 'estateoffice'); ?></option>
                            <option value="cooperative"><?php _e('Spółdzielcze Własnościowe Prawo do Lokalu', 'estateoffice'); ?></option>
                            <option value="lease"><?php _e('Dzierżawa', 'estateoffice'); ?></option>
                            <option value="other"><?php _e('Inne', 'estateoffice'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Mapa Google -->
                <div class="eo-form-section">
                    <h3><?php _e('Lokalizacja na mapie', 'estateoffice'); ?></h3>
                    <div class="eo-form-group">
                        <button type="button" 
                                id="mark_on_map" 
                                class="eo-button eo-button-secondary"
                        >
                            <?php _e('Zaznacz na mapie', 'estateoffice'); ?>
                        </button>
                    </div>
                    <div id="google_map" class="eo-map" style="height: 400px;"></div>
                    <input type="hidden" id="property_lat" name="property_lat">
                    <input type="hidden" id="property_lng" name="property_lng">
                </div>

                <!-- Dane Nieruchomości -->
                <div class="eo-form-section">
                    <h3><?php _e('Dane Nieruchomości', 'estateoffice'); ?></h3>
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-6">
                            <label for="property_price" class="required">
                                <?php _e('Cena', 'estateoffice'); ?>
                                <span class="rental-note" style="display: none;"><?php _e('(miesięcznie)', 'estateoffice'); ?></span>
                            </label>
                            <input type="number" 
                                   id="property_price" 
                                   name="property_price" 
                                   class="eo-input" 
                                   step="0.01" 
                                   required
                            >
                        </div>
                        <div class="eo-form-group eo-col-6">
                            <label for="property_admin_fee">
                                <?php _e('Czynsz administracyjny', 'estateoffice'); ?>
                            </label>
                            <input type="number" 
                                   id="property_admin_fee" 
                                   name="property_admin_fee" 
                                   class="eo-input" 
                                   step="0.01"
                            >
                        </div>
                    </div>

                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-6">
                            <label for="property_area" class="required">
                                <?php _e('Powierzchnia w m²', 'estateoffice'); ?>
                            </label>
                            <input type="number" 
                                   id="property_area" 
                                   name="property_area" 
                                   class="eo-input" 
                                   step="0.01" 
                                   required
                            >
                        </div>
                        <div class="eo-form-group eo-col-6">
                            <label for="property_price_per_m2">
                                <?php _e('Cena za m²', 'estateoffice'); ?>
                            </label>
                            <input type="number" 
                                   id="property_price_per_m2" 
                                   name="property_price_per_m2" 
                                   class="eo-input" 
                                   readonly
                            >
                        </div>
                    </div>

                    <!-- Pola niedotyczące DZIAŁKI -->
                    <div class="non-plot-fields">
                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-3">
                                <label for="property_year">
                                    <?php _e('Rok budowy', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_year" 
                                       name="property_year" 
                                       class="eo-input" 
                                       min="1800" 
                                       max="<?php echo date('Y'); ?>"
                                >
                            </div>
							<div class="eo-form-group eo-col-3 apartment-commercial-fields" style="display: none;">
                                <label for="property_floor">
                                    <?php _e('Piętro', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_floor" 
                                       name="property_floor" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-3">
                                <label for="property_total_floors">
                                    <?php _e('Liczba pięter', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_total_floors" 
                                       name="property_total_floors" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-3">
                                <label for="property_rooms">
                                    <?php _e('Liczba pokoi', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_rooms" 
                                       name="property_rooms" 
                                       class="eo-input"
                                >
                            </div>
                        </div>

                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-4">
                                <label for="property_bedrooms">
                                    <?php _e('Liczba sypialni', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_bedrooms" 
                                       name="property_bedrooms" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-4">
                                <label for="property_bathrooms">
                                    <?php _e('Liczba łazienek', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_bathrooms" 
                                       name="property_bathrooms" 
                                       class="eo-input"
                                >
                            </div>
                            <div class="eo-form-group eo-col-4">
                                <label for="property_toilets">
                                    <?php _e('Liczba toalet', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="property_toilets" 
                                       name="property_toilets" 
                                       class="eo-input"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Pola tylko dla DZIAŁKI -->
                    <div class="plot-fields" style="display: none;">
                        <div class="eo-form-group">
                            <label for="plot_shape" class="required">
                                <?php _e('Kształt działki', 'estateoffice'); ?>
                            </label>
                            <select id="plot_shape" 
                                    name="plot_shape" 
                                    class="eo-select"
                            >
                                <option value="regular"><?php _e('Regularny', 'estateoffice'); ?></option>
                                <option value="irregular"><?php _e('Nieregularny', 'estateoffice'); ?></option>
                            </select>
                        </div>

                        <div id="plot_dimensions_regular" class="eo-form-row">
                            <div class="eo-form-group eo-col-6">
                                <label for="plot_length">
                                    <?php _e('Długość (m)', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="plot_length" 
                                       name="plot_length" 
                                       class="eo-input" 
                                       step="0.01"
                                >
                            </div>
                            <div class="eo-form-group eo-col-6">
                                <label for="plot_width">
                                    <?php _e('Szerokość (m)', 'estateoffice'); ?>
                                </label>
                                <input type="number" 
                                       id="plot_width" 
                                       name="plot_width" 
                                       class="eo-input" 
                                       step="0.01"
                                >
                            </div>
                        </div>

                        <div id="plot_dimensions_irregular" class="eo-form-group" style="display: none;">
                            <label for="plot_dimensions">
                                <?php _e('Wymiary działki', 'estateoffice'); ?>
                            </label>
                            <textarea id="plot_dimensions" 
                                      name="plot_dimensions" 
                                      class="eo-textarea"
                                      placeholder="<?php _e('Wpisz wymiary działki...', 'estateoffice'); ?>"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Opis Nieruchomości -->
                <div class="eo-form-section">
                    <h3><?php _e('Opis Nieruchomości', 'estateoffice'); ?></h3>
                    <div class="eo-form-group">
                        <?php 
                            wp_editor('', 'property_description', array(
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                                'teeny' => true,
                                'quicktags' => false
                            )); 
                        ?>
                    </div>
                </div>

                <!-- Szczegóły Nieruchomości (nie dotyczy DZIAŁKI) -->
                <div class="eo-form-section non-plot-fields">
                    <h3><?php _e('Szczegóły Nieruchomości', 'estateoffice'); ?></h3>

                    <!-- Budynek -->
                    <div class="eo-form-subsection">
                        <h4><?php _e('Budynek', 'estateoffice'); ?></h4>
                        
                        <div class="eo-form-group">
                            <label for="building_condition">
                                <?php _e('Stan wykończenia', 'estateoffice'); ?>
                            </label>
                            <select id="building_condition" 
                                    name="building_condition" 
                                    class="eo-select"
                            >
                                <option value=""><?php _e('Wybierz stan', 'estateoffice'); ?></option>
                                <option value="new"><?php _e('Nowy', 'estateoffice'); ?></option>
                                <option value="very_good"><?php _e('Bardzo dobry', 'estateoffice'); ?></option>
                                <option value="good"><?php _e('Dobry', 'estateoffice'); ?></option>
                                <option value="to_renovation"><?php _e('Do remontu', 'estateoffice'); ?></option>
                            </select>
                        </div>

                        <div class="eo-form-group">
                            <label><?php _e('Ekspozycja', 'estateoffice'); ?></label>
                            <div class="eo-checkbox-group">
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="exposure[]" value="N"> <?php _e('Północ', 'estateoffice'); ?>
                                </label>
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="exposure[]" value="S"> <?php _e('Południe', 'estateoffice'); ?>
                                </label>
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="exposure[]" value="E"> <?php _e('Wschód', 'estateoffice'); ?>
                                </label>
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="exposure[]" value="W"> <?php _e('Zachód', 'estateoffice'); ?>
                                </label>
                            </div>
                        </div>
						<!-- Dodatkowe cechy budynku -->
                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-6">
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="has_attic" class="eo-checkbox">
                                    <?php _e('Poddasze', 'estateoffice'); ?>
                                </label>
                            </div>
                            <div class="eo-form-group eo-col-6 apartment-commercial-fields">
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="is_multilevel" class="eo-checkbox">
                                    <?php _e('Wielopoziomowe', 'estateoffice'); ?>
                                </label>
                            </div>
                        </div>

                        <!-- Kuchnia i parking -->
                        <div class="eo-form-group">
                            <label for="kitchen_type"><?php _e('Kuchnia', 'estateoffice'); ?></label>
                            <select id="kitchen_type" name="kitchen_type" class="eo-select">
                                <option value="annex"><?php _e('Aneks', 'estateoffice'); ?></option>
                                <option value="separate"><?php _e('Oddzielna', 'estateoffice'); ?></option>
                                <option value="with_living_room"><?php _e('Z salonem', 'estateoffice'); ?></option>
                            </select>
                        </div>

                        <div class="eo-form-group">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" id="has_parking" name="has_parking" class="eo-checkbox">
                                <?php _e('Miejsce parkingowe', 'estateoffice'); ?>
                            </label>
                            
                            <div id="parking_details" class="eo-nested-form" style="display: none;">
                                <div class="eo-form-row">
                                    <div class="eo-form-group eo-col-4">
                                        <label for="parking_rental">
                                            <?php _e('Najemne', 'estateoffice'); ?>
                                        </label>
                                        <input type="number" id="parking_rental" name="parking_rental" class="eo-input" min="0">
                                    </div>
                                    <div class="eo-form-group eo-col-4">
                                        <label for="parking_underground">
                                            <?php _e('Podziemne', 'estateoffice'); ?>
                                        </label>
                                        <input type="number" id="parking_underground" name="parking_underground" class="eo-input" min="0">
                                    </div>
                                    <div class="eo-form-group eo-col-4">
                                        <label for="parking_garage">
                                            <?php _e('Garaż wolnostojący/przylegający', 'estateoffice'); ?>
                                        </label>
                                        <input type="number" id="parking_garage" name="parking_garage" class="eo-input" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Media -->
                    <div class="eo-form-subsection">
                        <h4><?php _e('Media', 'estateoffice'); ?></h4>
                        
                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-6">
                                <label for="heating_type"><?php _e('Ogrzewanie', 'estateoffice'); ?></label>
                                <select id="heating_type" name="heating_type" class="eo-select">
                                    <option value="municipal"><?php _e('Miejskie', 'estateoffice'); ?></option>
                                    <option value="gas"><?php _e('Gazowe', 'estateoffice'); ?></option>
                                    <option value="electric"><?php _e('Elektryczne', 'estateoffice'); ?></option>
                                    <option value="solid_fuel"><?php _e('Na paliwo stałe', 'estateoffice'); ?></option>
                                    <option value="other"><?php _e('Inne', 'estateoffice'); ?></option>
                                </select>
                            </div>
                            <div class="eo-form-group eo-col-6">
                                <label for="water_source"><?php _e('Woda', 'estateoffice'); ?></label>
                                <select id="water_source" name="water_source" class="eo-select">
                                    <option value="municipal"><?php _e('Miejska', 'estateoffice'); ?></option>
                                    <option value="well"><?php _e('Studnia', 'estateoffice'); ?></option>
                                    <option value="other"><?php _e('Inne', 'estateoffice'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="eo-form-row">
                            <div class="eo-form-group eo-col-6">
                                <label for="sewage_type"><?php _e('Kanalizacja', 'estateoffice'); ?></label>
                                <select id="sewage_type" name="sewage_type" class="eo-select">
                                    <option value="municipal"><?php _e('Miejska', 'estateoffice'); ?></option>
                                    <option value="septic"><?php _e('Szambo', 'estateoffice'); ?></option>
                                    <option value="treatment_plant"><?php _e('Oczyszczalnia', 'estateoffice'); ?></option>
                                </select>
                            </div>
                            <div class="eo-form-group eo-col-6">
                                <label class="eo-checkbox-label">
                                    <input type="checkbox" name="has_gas" class="eo-checkbox">
                                    <?php _e('Gaz', 'estateoffice'); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Udogodnienia -->
                    <div class="eo-form-subsection">
                        <h4><?php _e('Udogodnienia', 'estateoffice'); ?></h4>
                        <div class="eo-checkbox-grid">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="elevator">
                                <?php _e('Winda', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="furnished">
                                <?php _e('Umeblowanie', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="air_conditioning">
                                <?php _e('Klimatyzacja', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="monitoring">
                                <?php _e('Monitoring/Ochrona', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="reception">
                                <?php _e('Recepcja', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="closed_area">
                                <?php _e('Teren zamknięty', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="amenities[]" value="intercom">
                                <?php _e('Domofon', 'estateoffice'); ?>
                            </label>
                        </div>
                    </div>
					<!-- Wyposażenie -->
                    <div class="eo-form-subsection">
                        <h4><?php _e('Wyposażenie', 'estateoffice'); ?></h4>
                        <div class="eo-checkbox-grid">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="washing_machine">
                                <?php _e('Pralka', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="dishwasher">
                                <?php _e('Zmywarka', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="fridge">
                                <?php _e('Lodówka', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="stove">
                                <?php _e('Kuchenka', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="oven">
                                <?php _e('Piekarnik', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="tv">
                                <?php _e('Telewizor', 'estateoffice'); ?>
                            </label>
                            <label class="eo-checkbox-label">
                                <input type="checkbox" name="equipment[]" value="microwave">
                                <?php _e('Mikrofala', 'estateoffice'); ?>
                            </label>
                        </div>
                    </div>

                    <!-- Powierzchnie dodatkowe -->
                    <div class="eo-form-subsection">
                        <h4><?php _e('Powierzchnie dodatkowe', 'estateoffice'); ?></h4>
                        
                        <!-- Balkon -->
                        <div class="eo-form-group">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" id="has_balcony" name="has_balcony" class="eo-checkbox">
                                <?php _e('Balkon', 'estateoffice'); ?>
                            </label>
                            <div id="balcony_details" class="eo-nested-form" style="display: none;">
                                <div class="eo-form-row">
                                    <div class="eo-form-group eo-col-6">
                                        <label for="balcony_count"><?php _e('Ilość', 'estateoffice'); ?></label>
                                        <input type="number" id="balcony_count" name="balcony_count" class="eo-input" min="1">
                                    </div>
                                    <div class="eo-form-group eo-col-6">
                                        <label for="balcony_area"><?php _e('Powierzchnia (m²)', 'estateoffice'); ?></label>
                                        <input type="number" id="balcony_area" name="balcony_area" class="eo-input" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Taras -->
                        <div class="eo-form-group">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" id="has_terrace" name="has_terrace" class="eo-checkbox">
                                <?php _e('Taras', 'estateoffice'); ?>
                            </label>
                            <div id="terrace_details" class="eo-nested-form" style="display: none;">
                                <div class="eo-form-row">
                                    <div class="eo-form-group eo-col-6">
                                        <label for="terrace_count"><?php _e('Ilość', 'estateoffice'); ?></label>
                                        <input type="number" id="terrace_count" name="terrace_count" class="eo-input" min="1">
                                    </div>
                                    <div class="eo-form-group eo-col-6">
                                        <label for="terrace_area"><?php _e('Powierzchnia (m²)', 'estateoffice'); ?></label>
                                        <input type="number" id="terrace_area" name="terrace_area" class="eo-input" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Piwnica -->
                        <div class="eo-form-group">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" id="has_basement" name="has_basement" class="eo-checkbox">
                                <?php _e('Piwnica', 'estateoffice'); ?>
                            </label>
                            <div id="basement_details" class="eo-nested-form" style="display: none;">
                                <div class="eo-form-group">
                                    <label for="basement_area"><?php _e('Powierzchnia (m²)', 'estateoffice'); ?></label>
                                    <input type="number" id="basement_area" name="basement_area" class="eo-input" step="0.01">
                                </div>
                            </div>
                        </div>

                        <!-- Komórka lokatorska -->
                        <div class="eo-form-group">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" id="has_storage" name="has_storage" class="eo-checkbox">
                                <?php _e('Komórka lokatorska', 'estateoffice'); ?>
                            </label>
                            <div id="storage_details" class="eo-nested-form" style="display: none;">
                                <div class="eo-form-group">
                                    <label for="storage_area"><?php _e('Powierzchnia (m²)', 'estateoffice'); ?></label>
                                    <input type="number" id="storage_area" name="storage_area" class="eo-input" step="0.01">
                                </div>
                            </div>
                        </div>

                        <!-- Ogródek -->
                        <div class="eo-form-group">
                            <label class="eo-checkbox-label">
                                <input type="checkbox" id="has_garden" name="has_garden" class="eo-checkbox">
                                <?php _e('Ogródek', 'estateoffice'); ?>
                            </label>
                            <div id="garden_details" class="eo-nested-form" style="display: none;">
                                <div class="eo-form-group">
                                    <label for="garden_area"><?php _e('Powierzchnia (m²)', 'estateoffice'); ?></label>
                                    <input type="number" id="garden_area" name="garden_area" class="eo-input" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Galeria i Rzuty -->
                <div class="eo-form-section">
                    <h3><?php _e('Galeria i Rzuty', 'estateoffice'); ?></h3>
                    
                    <!-- Zdjęcia nieruchomości -->
                    <div class="eo-form-group">
                        <label><?php _e('Zdjęcia nieruchomości', 'estateoffice'); ?></label>
                        <div id="property_gallery" class="eo-gallery-uploader">
                            <input type="file" 
                                   id="property_images" 
                                   name="property_images[]" 
                                   class="eo-file-input" 
                                   multiple 
                                   accept="image/*"
                            >
                            <div class="eo-gallery-preview"></div>
                        </div>
                    </div>

                    <!-- Rzuty -->
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-6">
                            <label><?php _e('Rzut 2D', 'estateoffice'); ?></label>
                            <input type="file" 
                                   id="floor_plan_2d" 
                                   name="floor_plan_2d" 
                                   class="eo-file-input" 
                                   accept="image/*"
                            >
                        </div>
                        <div class="eo-form-group eo-col-6">
                            <label><?php _e('Rzut 3D', 'estateoffice'); ?></label>
                            <input type="file" 
                                   id="floor_plan_3d" 
                                   name="floor_plan_3d" 
                                   class="eo-file-input" 
                                   accept="image/*"
                            >
                        </div>
                    </div>

                    <!-- Linki do multimediów -->
                    <div class="eo-form-row">
                        <div class="eo-form-group eo-col-6">
                            <label for="video_url"><?php _e('Link do filmu', 'estateoffice'); ?></label>
                            <input type="url" id="video_url" name="video_url" class="eo-input">
                        </div>
                        <div class="eo-form-group eo-col-6">
                            <label for="virtual_tour_url"><?php _e('Link do wirtualnego spaceru', 'estateoffice'); ?></label>
                            <input type="url" id="virtual_tour_url" name="virtual_tour_url" class="eo-input">
                        </div>
                    </div>
                </div>
				<!-- Znaczniki -->
                <div class="eo-form-section">
                    <h3><?php _e('Znaczniki', 'estateoffice'); ?></h3>
                    <div class="eo-checkbox-grid">
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="new_offer">
                            <?php _e('Nowa oferta', 'estateoffice'); ?>
                            <small><?php _e('(automatyczne odznaczenie po 7 dniach)', 'estateoffice'); ?></small>
                        </label>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="exclusive">
                            <?php _e('Wyłączność', 'estateoffice'); ?>
                        </label>
                        <?php if ($transaction_type === 'SPRZEDAZ'): ?>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="sold">
                            <?php _e('Sprzedane', 'estateoffice'); ?>
                        </label>
                        <?php endif; ?>
                        <?php if ($transaction_type === 'WYNAJEM'): ?>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="rented">
                            <?php _e('Wynajęte', 'estateoffice'); ?>
                        </label>
                        <?php endif; ?>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="new_price">
                            <?php _e('Nowa cena', 'estateoffice'); ?>
                        </label>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="no_commission">
                            <?php _e('Bez prowizji', 'estateoffice'); ?>
                        </label>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="mls">
                            <?php _e('Oferta MLS', 'estateoffice'); ?>
                        </label>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="premium">
                            <?php _e('Premium', 'estateoffice'); ?>
                        </label>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="export_www">
                            <?php _e('Eksport na WWW', 'estateoffice'); ?>
                        </label>
                        <label class="eo-checkbox-label">
                            <input type="checkbox" name="tags[]" value="export_portals" disabled>
                            <?php _e('Eksport na Portale', 'estateoffice'); ?>
                            <small><?php _e('(funkcjonalność w przygotowaniu)', 'estateoffice'); ?></small>
                        </label>
                    </div>
                </div>

                <!-- Przyciski formularza -->
                <div class="eo-form-navigation">
                    <button type="button" class="eo-button eo-button-prev" data-prev="step2">
                        <?php _e('WSTECZ', 'estateoffice'); ?>
                    </button>
                    <button type="submit" class="eo-button eo-button-primary">
                        <?php _e('DODAJ NIERUCHOMOŚĆ', 'estateoffice'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// Dodanie niezbędnych skryptów i styli
wp_enqueue_script('eo-form-add-contract', 
    ESTATEOFFICE_PLUGIN_URL . 'frontend/js/form-add-contract.js',
    array('jquery', 'wp-editor'),
    ESTATEOFFICE_VERSION,
    true
);

wp_enqueue_style('eo-form-add-contract',
    ESTATEOFFICE_PLUGIN_URL . 'frontend/css/form-add-contract.css',
    array(),
    ESTATEOFFICE_VERSION
);

// Przekazanie danych do JavaScript
wp_localize_script('eo-form-add-contract', 'eoFormData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('eo_form_add_contract'),
    'i18n' => array(
        'errorRequired' => __('To pole jest wymagane', 'estateoffice'),
        'errorInvalid' => __('Wprowadź poprawną wartość', 'estateoffice'),
        'confirmDelete' => __('Czy na pewno chcesz usunąć?', 'estateoffice'),
        'uploadError' => __('Wystąpił błąd podczas przesyłania pliku', 'estateoffice'),
    )
));