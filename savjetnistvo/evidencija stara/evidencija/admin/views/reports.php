<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'view_evidencija_reports' ) ) { // Provjera capabilityja
    wp_die( __( 'Nemate dovoljno dozvola za pristup ovoj stranici.', 'evidencija' ) );
}

global $wpdb;
// Pomoćne tablice
$table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
$table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';
$table_name_sobe = $wpdb->prefix . 'evidencija_sobe';
$current_year = date('Y'); // Trenutna godina za filter

// Odabir izvješća za prikaz
$report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'active_users'; // Default izvješće

// Dohvati sve lokacije za filtere
$locations_for_filter = Evidencija_Helpers::get_all_locations();

?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Izvješća Doma', 'evidencija' ); ?></h1>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url( admin_url('admin.php?page=evidencija-izvjesca&report_type=active_users') ); ?>" class="nav-tab <?php echo ($report_type == 'active_users') ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Aktivni korisnici', 'evidencija'); ?></a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=evidencija-izvjesca&report_type=occupancy') ); ?>" class="nav-tab <?php echo ($report_type == 'occupancy') ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Popunjenost kapaciteta', 'evidencija'); ?></a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=evidencija-izvjesca&report_type=arrivals_departures') ); ?>" class="nav-tab <?php echo ($report_type == 'arrivals_departures') ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Dolasci/Odlasci', 'evidencija'); ?></a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=evidencija-izvjesca&report_type=demographics') ); ?>" class="nav-tab <?php echo ($report_type == 'demographics') ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Demografija', 'evidencija'); ?></a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=evidencija-izvjesca&report_type=medical_stats') ); ?>" class="nav-tab <?php echo ($report_type == 'medical_stats') ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Medicinska statistika', 'evidencija'); ?></a>
    </h2>

    <div class="tab-content">
        <?php if ($report_type == 'active_users') : ?>
            <div class="report-header">
                <h3><?php echo esc_html__( 'Pregled aktivnih korisnika po lokaciji', 'evidencija' ); ?></h3>
                <button type="button" class="button button-secondary export-report-button" data-report-type="active_users">
                    <?php echo esc_html__('Export u CSV', 'evidencija'); ?>
                </button>
            </div>
            <?php
                $active_users_by_location = $wpdb->get_results("
                    SELECT
                        l.naziv_lokacije,
                        COUNT(k.id) AS active_users_count
                    FROM
                        {$table_name_korisnici} k
                    LEFT JOIN
                        {$table_name_lokacije} l ON k.lokacija_id = l.id
                    WHERE
                        k.status_smjestaja = 'Trenutno smješten'
                    GROUP BY
                        l.naziv_lokacije
                    ORDER BY
                        l.naziv_lokacije ASC
                ");

                if (!empty($active_users_by_location)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>' . esc_html__('Lokacija', 'evidencija') . '</th><th>' . esc_html__('Broj aktivnih korisnika', 'evidencija') . '</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($active_users_by_location as $row) {
                        echo '<tr>';
                        echo '<td>' . esc_html($row->naziv_lokacije) . '</td>';
                        echo '<td>' . esc_html($row->active_users_count) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo '<p>' . esc_html__('Nema aktivnih korisnika za prikaz u izvješću.', 'evidencija') . '</p>';
                }
            ?>
        <?php elseif ($report_type == 'occupancy') : ?>
            <div class="report-header">
                <h3><?php echo esc_html__( 'Popunjenost kapaciteta po sobama', 'evidencija' ); ?></h3>
                 <button type="button" class="button button-secondary export-report-button" data-report-type="occupancy">
                    <?php echo esc_html__('Export u CSV', 'evidencija'); ?>
                </button>
            </div>
            <?php
                $occupancy_report = $wpdb->get_results("
                    SELECT
                        l.naziv_lokacije,
                        s.naziv_sobe,
                        s.kapacitet_sobe,
                        (SELECT COUNT(k.id) FROM {$table_name_korisnici} k WHERE k.soba_id = s.id AND k.status_smjestaja = 'Trenutno smješten') AS current_occupancy
                    FROM
                        {$table_name_sobe} s
                    LEFT JOIN
                        {$table_name_lokacije} l ON s.lokacija_id = l.id
                    ORDER BY
                        l.naziv_lokacije ASC, s.naziv_sobe ASC
                ");

                if (!empty($occupancy_report)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>' . esc_html__('Lokacija', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Soba', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Trenutna popunjenost', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Kapacitet sobe', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Slobodna mjesta', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Postotak popunjenosti', 'evidencija') . '</th>';
                    echo '</tr></thead><tbody>';
                    foreach ($occupancy_report as $row) {
                        $free_spots = $row->kapacitet_sobe - $row->current_occupancy;
                        $occupancy_percentage = ($row->kapacitet_sobe > 0) ? round(($row->current_occupancy / $row->kapacitet_sobe) * 100, 2) : 0;

                        echo '<tr>';
                        echo '<td>' . esc_html($row->naziv_lokacije) . '</td>';
                        echo '<td>' . esc_html($row->naziv_sobe) . '</td>';
                        echo '<td>' . esc_html($row->current_occupancy) . '</td>';
                        echo '<td>' . esc_html($row->kapacitet_sobe) . '</td>';
                        echo '<td>' . esc_html($free_spots) . '</td>';
                        echo '<td>' . esc_html($occupancy_percentage) . '%</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>' . esc_html__('Nema podataka o popunjenosti kapaciteta za prikaz. Dodajte lokacije i sobe.', 'evidencija') . '</p>';
                }
            ?>
        <?php elseif ($report_type == 'arrivals_departures') : ?>
            <div class="report-header">
                <h3><?php echo esc_html__( 'Pregled dolazaka i odlazaka', 'evidencija' ); ?></h3>
                <button type="button" class="button button-secondary export-report-button" data-report-type="arrivals_departures" data-report-params='<?php echo json_encode([
                    'report_year' => isset($_GET['report_year']) ? $_GET['report_year'] : $current_year,
                    'report_month' => isset($_GET['report_month']) ? $_GET['report_month'] : 0,
                    'report_location_id' => isset($_GET['report_location_id']) ? $_GET['report_location_id'] : 0,
                ]); ?>'>
                    <?php echo esc_html__('Export u CSV', 'evidencija'); ?>
                </button>
            </div>
            <form method="get" action="<?php echo esc_url( admin_url('admin.php') ); ?>">
                <input type="hidden" name="page" value="evidencija-izvjesca">
                <input type="hidden" name="report_type" value="arrivals_departures">
                <label for="report_year_ad"><?php echo esc_html__('Godina:', 'evidencija'); ?></label>
                <select name="report_year" id="report_year_ad">
                    <?php for ($year = $current_year; $year >= $current_year - 10; $year--) : ?>
                        <option value="<?php echo esc_attr($year); ?>" <?php selected(isset($_GET['report_year']) ? $_GET['report_year'] : $current_year, $year); ?>><?php echo esc_html($year); ?></option>
                    <?php endfor; ?>
                </select>
                <label for="report_month_ad"><?php echo esc_html__('Mjesec:', 'evidencija'); ?></label>
                <select name="report_month" id="report_month_ad">
                    <option value="0"><?php echo esc_html__('Svi mjeseci', 'evidencija'); ?></option>
                    <?php for ($month = 1; $month <= 12; $month++) : ?>
                        <option value="<?php echo esc_attr($month); ?>" <?php selected(isset($_GET['report_month']) ? $_GET['report_month'] : 0, $month); ?>><?php echo esc_html(date_i18n('F', mktime(0, 0, 0, $month, 1))); ?></option>
                    <?php endfor; ?>
                </select>
                <label for="report_location_ad"><?php echo esc_html__('Lokacija:', 'evidencija'); ?></label>
                <select name="report_location_id" id="report_location_ad">
                    <option value="0"><?php echo esc_html__('Sve lokacije', 'evidencija'); ?></option>
                    <?php foreach ($locations_for_filter as $location) : ?>
                        <option value="<?php echo esc_attr($location->id); ?>" <?php selected(isset($_GET['report_location_id']) ? $_GET['report_location_id'] : 0, $location->id); ?>><?php echo esc_html($location->naziv_lokacije); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Prikaži izvješće', 'evidencija'), 'button', 'show_report_ad', false); ?>
            </form>
            <?php
                $selected_year = isset($_GET['report_year']) ? absint($_GET['report_year']) : $current_year;
                $selected_month = isset($_GET['report_month']) ? absint($_GET['report_month']) : 0;
                $selected_location_id = isset($_GET['report_location_id']) ? absint($_GET['report_location_id']) : 0;

                $where_clauses = [];
                $sql_params = [];

                // Filter by year for both arrival and departure dates
                $where_clauses[] = "(YEAR(k.datum_dolaska) = %d OR YEAR(k.datum_odlaska) = %d)";
                $sql_params[] = $selected_year;
                $sql_params[] = $selected_year;

                // Filter by month for both arrival and departure dates, if a specific month is selected
                if ($selected_month > 0) {
                    $where_clauses[] = "(MONTH(k.datum_dolaska) = %d OR MONTH(k.datum_odlaska) = %d)";
                    $sql_params[] = $selected_month;
                    $sql_params[] = $selected_month;
                }

                // Filter by location, if a specific location is selected
                if ($selected_location_id > 0) {
                    $where_clauses[] = "k.lokacija_id = %d";
                    $sql_params[] = $selected_location_id;
                }

                $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

                $arrivals_departures = $wpdb->get_results(
                    $wpdb->prepare("
                        SELECT
                            k.ime,
                            k.prezime,
                            k.datum_dolaska,
                            k.datum_odlaska,
                            k.status_smjestaja,
                            l.naziv_lokacije,
                            s.naziv_sobe,
                            k.broj_kreveta
                        FROM
                            {$table_name_korisnici} k
                        LEFT JOIN
                            {$table_name_lokacije} l ON k.lokacija_id = l.id
                        LEFT JOIN
                            {$table_name_sobe} s ON k.soba_id = s.id
                        $where_sql
                        ORDER BY
                            k.datum_dolaska DESC
                    ", ...$sql_params)
                );

                if (!empty($arrivals_departures)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>' . esc_html__('Ime i Prezime', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Lokacija', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Datum dolaska', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Datum odlaska', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Status', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Soba', 'evidencija') . '</th>';
                    echo '<th>' . esc_html__('Broj kreveta', 'evidencija') . '</th>';
                    echo '</tr></thead><tbody>';
                    foreach ($arrivals_departures as $entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($entry->ime . ' ' . $entry->prezime) . '</td>';
                        echo '<td>' . esc_html($entry->naziv_lokacije) . '</td>';
                        echo '<td>' . esc_html($entry->datum_dolaska ? date_i18n(get_option('date_format'), strtotime($entry->datum_dolaska)) : '-') . '</td>';
                        echo '<td>' . esc_html($entry->datum_odlaska ? date_i18n(get_option('date_format'), strtotime($entry->datum_odlaska)) : '-') . '</td>';
                        echo '<td>' . esc_html($entry->status_smjestaja) . '</td>';
                        echo '<td>' . esc_html($entry->naziv_sobe ? $entry->naziv_sobe : '-') . '</td>';
                        echo '<td>' . esc_html($entry->broj_kreveta ? $entry->broj_kreveta : '-') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>' . esc_html__('Nema dolazaka/odlazaka za prikaz s odabranim filterima.', 'evidencija') . '</p>';
                }
            ?>
        <?php elseif ($report_type == 'demographics') : ?>
            <div class="report-header">
                <h3><?php echo esc_html__( 'Demografska izvješća (Spol i Dob)', 'evidencija' ); ?></h3>
                <button type="button" class="button button-secondary export-report-button" data-report-type="demographics" data-report-params='<?php echo json_encode([
                    'report_location_id' => isset($_GET['report_location_id']) ? $_GET['report_location_id'] : 0,
                ]); ?>'>
                    <?php echo esc_html__('Export u CSV', 'evidencija'); ?>
                </button>
            </div>
            <form method="get" action="<?php echo esc_url( admin_url('admin.php') ); ?>">
                <input type="hidden" name="page" value="evidencija-izvjesca">
                <input type="hidden" name="report_type" value="demographics">
                <label for="report_location_demo"><?php echo esc_html__('Lokacija:', 'evidencija'); ?></label>
                <select name="report_location_id" id="report_location_demo">
                    <option value="0"><?php echo esc_html__('Sve lokacije', 'evidencija'); ?></option>
                    <?php foreach ($locations_for_filter as $location) : ?>
                        <option value="<?php echo esc_attr($location->id); ?>" <?php selected(isset($_GET['report_location_id']) ? $_GET['report_location_id'] : 0, $location->id); ?>><?php echo esc_html($location->naziv_lokacije); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Prikaži izvješće', 'evidencija'), 'button', 'show_report_demo', false); ?>
            </form>
            <?php
                $selected_location_id_demo = isset($_GET['report_location_id']) ? absint($_GET['report_location_id']) : 0;
                $where_clauses_demo = [];
                $sql_params_demo = [];

                if ($selected_location_id_demo > 0) {
                    $where_clauses_demo[] = "k.lokacija_id = %d";
                    $sql_params_demo[] = $selected_location_id_demo;
                }
                $where_sql_demo = !empty($where_clauses_demo) ? 'WHERE ' . implode(' AND ', $where_clauses_demo) : '';

                // Spolna struktura
                $gender_stats = $wpdb->get_results(
                    $wpdb->prepare("
                        SELECT
                            k.spol,
                            COUNT(k.id) AS count
                        FROM
                            {$table_name_korisnici} k
                        $where_sql_demo
                        GROUP BY
                            k.spol
                        ORDER BY
                            k.spol ASC
                    ", ...$sql_params_demo)
                );

                if (!empty($gender_stats)) {
                    echo '<h4>' . esc_html__('Spolna struktura', 'evidencija') . '</h4>';
                    echo '<table class="wp-list-table widefat fixed striped" style="width:auto;">';
                    echo '<thead><tr><th>' . esc_html__('Spol', 'evidencija') . '</th><th>' . esc_html__('Broj korisnika', 'evidencija') . '</th></tr></thead><tbody>';
                    foreach ($gender_stats as $stat) {
                        echo '<tr><td>' . esc_html($stat->spol ? $stat->spol : __('Nepoznato', 'evidencija')) . '</td><td>' . esc_html($stat->count) . '</td></tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>' . esc_html__('Nema podataka o spolu za prikaz.', 'evidencija') . '</p>';
                }

                // Dobna struktura (grubo - po kategorijama)
                echo '<h4>' . esc_html__('Dobna struktura (prema kategorijama)', 'evidencija') . '</h4>';
                $age_ranges = [
                    '0-17' => ['min' => 0, 'max' => 17, 'label' => '0-17 ' . __('godina', 'evidencija')],
                    '18-35' => ['min' => 18, 'max' => 35, 'label' => '18-35 ' . __('godina', 'evidencija')],
                    '36-55' => ['min' => 36, 'max' => 55, 'label' => '36-55 ' . __('godina', 'evidencija')],
                    '56-75' => ['min' => 56, 'max' => 75, 'label' => '56-75 ' . __('godina', 'evidencija')],
                    '76+' => ['min' => 76, 'max' => 999, 'label' => '76+ ' . __('godina', 'evidencija')],
                ];

                $age_distribution = [];
                foreach ($age_ranges as $key => $range) {
                    $age_distribution[$key] = 0;
                }

                $all_birth_dates_query = "
                    SELECT datum_rodjenja
                    FROM {$table_name_korisnici} k
                    $where_sql_demo
                    WHERE datum_rodjenja IS NOT NULL AND datum_rodjenja != '0000-00-00'
                ";
                $all_birth_dates = $wpdb->get_results(
                    $wpdb->prepare($all_birth_dates_query, ...$sql_params_demo)
                );

                if (!empty($all_birth_dates)) {
                    foreach ($all_birth_dates as $user_date) {
                        if (!empty($user_date->datum_rodjenja)) {
                            try {
                                $birth_date_obj = new DateTime($user_date->datum_rodjenja);
                                $today = new DateTime();
                                $age = $today->diff($birth_date_obj)->y;

                                foreach ($age_ranges as $key => $range) {
                                    if ($age >= $range['min'] && $age <= $range['max']) {
                                        $age_distribution[$key]++;
                                        break;
                                    }
                                }
                            } catch (Exception $e) {
                                // Greška pri parsiranju datuma, preskoči
                            }
                        }
                    }

                    echo '<table class="wp-list-table widefat fixed striped" style="width:auto;">';
                    echo '<thead><tr><th>' . esc_html__('Dobna kategorija', 'evidencija') . '</th><th>' . esc_html__('Broj korisnika', 'evidencija') . '</th></tr></thead><tbody>';
                    foreach ($age_ranges as $key => $range) {
                        echo '<tr><td>' . esc_html($range['label']) . '</td><td>' . esc_html($age_distribution[$key]) . '</td></tr>';
                    }
                    echo '</tbody></table>';

                } else {
                    echo '<p>' . esc_html__('Nema dovoljno podataka za prikaz dobne strukture.', 'evidencija') . '</p>';
                }


                // Prosječna dob
                $average_age_query = "
                    SELECT
                        AVG(YEAR(CURRENT_DATE) - YEAR(datum_rodjenja) - (DATE_FORMAT(CURRENT_DATE, '%m%d') < DATE_FORMAT(datum_rodjenja, '%m%d')))
                    FROM
                        {$table_name_korisnici} k
                    $where_sql_demo
                    WHERE datum_rodjenja IS NOT NULL AND datum_rodjenja != '0000-00-00'
                ";
                $average_age_result = $wpdb->get_var( $wpdb->prepare($average_age_query, ...$sql_params_demo) );


                if ($average_age_result) {
                    echo '<p>' . sprintf(__('Prosječna dob je: %s godina.', 'evidencija'), round($average_age_result, 2)) . '</p>';
                } else {
                    echo '<p>' . esc_html__('Nema dovoljno podataka za izračun prosječne dobi.', 'evidencija') . '</p>';
                }
            ?>
        <?php elseif ($report_type == 'medical_stats') : ?>
            <div class="report-header">
                <h3><?php echo esc_html__( 'Medicinska statistika (Dijagnoze i Alergije)', 'evidencija' ); ?></h3>
                <button type="button" class="button button-secondary export-report-button" data-report-type="medical_stats" data-report-params='<?php echo json_encode([
                    'report_location_id' => isset($_GET['report_location_id']) ? $_GET['report_location_id'] : 0,
                ]); ?>'>
                    <?php echo esc_html__('Export u CSV', 'evidencija'); ?>
                </button>
            </div>
            <form method="get" action="<?php echo esc_url( admin_url('admin.php') ); ?>">
                <input type="hidden" name="page" value="evidencija-izvjesca">
                <input type="hidden" name="report_type" value="medical_stats">
                <label for="report_location_med"><?php echo esc_html__('Lokacija:', 'evidencija'); ?></label>
                <select name="report_location_id" id="report_location_med">
                    <option value="0"><?php echo esc_html__('Sve lokacije', 'evidencija'); ?></option>
                    <?php foreach ($locations_for_filter as $location) : ?>
                        <option value="<?php echo esc_attr($location->id); ?>" <?php selected(isset($_GET['report_location_id']) ? $_GET['report_location_id'] : 0, $location->id); ?>><?php echo esc_html($location->naziv_lokacije); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Prikaži izvješće', 'evidencija'), 'button', 'show_report_med', false); ?>
            </form>
            <?php
                $selected_location_id_med = isset($_GET['report_location_id']) ? absint($_GET['report_location_id']) : 0;
                $where_clauses_med = [];
                $sql_params_med = [];

                if ($selected_location_id_med > 0) {
                    $where_clauses_med[] = "k.lokacija_id = %d";
                    $sql_params_med[] = $selected_location_id_med;
                }
                $where_sql_med = !empty($where_clauses_med) ? 'WHERE ' . implode(' AND ', $where_clauses_med) : '';

                $medical_stats = $wpdb->get_results(
                    $wpdb->prepare("
                        SELECT
                            mp.tip_podatka,
                            mp.opis,
                            COUNT(mp.id) AS count
                        FROM
                            {$wpdb->prefix}evidencija_medicinski_podaci mp
                        LEFT JOIN
                            {$table_name_korisnici} k ON mp.korisnik_id = k.id
                        $where_sql_med
                        GROUP BY
                            mp.tip_podatka, mp.opis
                        ORDER BY
                            mp.tip_podatka ASC, mp.opis ASC
                    ", ...$sql_params_med)
                );

                if (!empty($medical_stats)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>' . esc_html__('Tip podatka', 'evidencija') . '</th><th>' . esc_html__('Opis (Dijagnoza/Alergija/Lijek)', 'evidencija') . '</th><th>' . esc_html__('Broj pojavljivanja', 'evidencija') . '</th></tr></thead><tbody>';
                    foreach ($medical_stats as $stat) {
                        echo '<tr>';
                        echo '<td>' . esc_html(ucfirst($stat->tip_podatka)) . '</td>';
                        echo '<td>' . esc_html($stat->opis) . '</td>';
                        echo '<td>' . esc_html($stat->count) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>' . esc_html__('Nema medicinskih podataka za prikaz u izvješću.', 'evidencija') . '</p>';
                }
            ?>
        <?php endif; ?>
    </div></div>