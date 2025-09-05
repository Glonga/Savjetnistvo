<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_evidencija_settings' ) ) { // Provjera capabilityja za pristup ovoj stranici
    wp_die( __( 'Nemate dovoljno dozvola za pristup ovoj stranici.', 'evidencija' ) );
}

global $wpdb;
$table_name_audit_log = $wpdb->prefix . 'evidencija_audit_log';
$table_name_users = $wpdb->users;
$table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
$table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';

// Filteri i pretraga
$filter_user_id = isset( $_GET['filter_user_id'] ) ? absint( $_GET['filter_user_id'] ) : 0;
$filter_location_id = isset( $_GET['filter_location_id'] ) ? absint( $_GET['filter_location_id'] ) : 0;
$filter_action = isset( $_GET['filter_action'] ) ? sanitize_text_field( $_GET['filter_action'] ) : '';
$search_term = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

$per_page = 20;
$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

$where_clauses = array();
$sql_params = array();

if ( $filter_user_id > 0 ) {
    $where_clauses[] = 'al.korisnik_id_akcije = %d';
    $sql_params[] = $filter_user_id;
}
if ( $filter_location_id > 0 ) {
    $where_clauses[] = 'al.lokacija_id = %d';
    $sql_params[] = $filter_location_id;
}
if ( ! empty( $filter_action ) ) {
    $where_clauses[] = 'al.akcija = %s';
    $sql_params[] = $filter_action;
}
if ( ! empty( $search_term ) ) {
    $search_pattern = '%' . $wpdb->esc_like( $search_term ) . '%';
    $where_clauses[] = '(al.akcija LIKE %s OR al.detalji_promjene LIKE %s)';
    $sql_params[] = $search_pattern;
    $sql_params[] = $search_pattern;
}

$where_sql = '';
if ( ! empty( $where_clauses ) ) {
    $where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );
}

// Dohvati sve logove
$sql_log_entries = "
    SELECT
        al.*,
        u.display_name AS user_display_name,
        k.ime AS korisnik_ime,
        k.prezime AS korisnik_prezime,
        l.naziv_lokacije AS lokacija_naziv
    FROM
        {$table_name_audit_log} al
    LEFT JOIN
        {$table_name_users} u ON al.korisnik_id_akcije = u.ID
    LEFT JOIN
        {$table_name_korisnici} k ON al.korisnik_id = k.id
    LEFT JOIN
        {$table_name_lokacije} l ON al.lokacija_id = l.id
    $where_sql
    ORDER BY
        al.datum_vrijeme DESC
    LIMIT %d OFFSET %d
";

$sql_count = "SELECT COUNT(*) FROM {$table_name_audit_log} al $where_sql";

// Kreiraj kopiju params arraya za count query
$sql_params_count = $sql_params;

array_push($sql_params, $per_page, $offset); // Dodaj parametre za LIMIT i OFFSET na kraj

if (!empty($sql_params)) {
    $log_entries = $wpdb->get_results( $wpdb->prepare( $sql_log_entries, $sql_params ) );
    // Za count query, koristiti originalne parametre bez limit/offset
    $total_items = $wpdb->get_var( $wpdb->prepare( $sql_count, $sql_params_count ) );
} else {
    $log_entries = $wpdb->get_results( $sql_log_entries ); // Bez prepare ako nema params
    $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_audit_log} al" );
}


$total_pages = ceil( $total_items / $per_page );

// Dohvati sve WordPress korisnike za filter
$wp_users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
// Dohvati sve lokacije za filter
$locations_for_filter = Evidencija_Helpers::get_all_locations();

// Dohvati sve jedinstvene akcije za filter
$unique_actions = $wpdb->get_col( "SELECT DISTINCT akcija FROM {$table_name_audit_log} ORDER BY akcija ASC" );

?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Audit Log', 'evidencija' ); ?></h1>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    ?>

    <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <input type="hidden" name="page" value="evidencija-audit-log">

        <p class="search-box">
            <label class="screen-reader-text" for="audit-log-search-input"><?php echo esc_html__( 'Pretraži logove:', 'evidencija' ); ?></label>
            <input type="search" id="audit-log-search-input" name="s" value="<?php echo esc_attr( $search_term ); ?>">
            <?php submit_button( __( 'Pretraži Log', 'evidencija' ), 'button', 'submit', false ); ?>
        </p>

        <div class="alignleft actions">
            <label for="filter-user" class="screen-reader-text"><?php echo esc_html__( 'Filtriraj po korisniku', 'evidencija' ); ?></label>
            <select name="filter_user_id" id="filter-user">
                <option value="0"><?php echo esc_html__( 'Svi korisnici WP-a', 'evidencija' ); ?></option>
                <?php foreach ( $wp_users as $wp_user ) : ?>
                    <option value="<?php echo esc_attr( $wp_user->ID ); ?>" <?php selected( $filter_user_id, $wp_user->ID ); ?>>
                        <?php echo esc_html( $wp_user->display_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="filter-location" class="screen-reader-text"><?php echo esc_html__( 'Filtriraj po lokaciji', 'evidencija' ); ?></label>
            <select name="filter_location_id" id="filter-location">
                <option value="0"><?php echo esc_html__( 'Sve lokacije', 'evidencija' ); ?></option>
                <?php foreach ( $locations_for_filter as $location_filter ) : ?>
                    <option value="<?php echo esc_attr( $location_filter->id ); ?>" <?php selected( $filter_location_id, $location_filter->id ); ?>>
                        <?php echo esc_html( $location_filter->naziv_lokacije ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="filter-action" class="screen-reader-text"><?php echo esc_html__( 'Filtriraj po akciji', 'evidencija' ); ?></label>
            <select name="filter_action" id="filter-action">
                <option value=""><?php echo esc_html__( 'Sve akcije', 'evidencija' ); ?></option>
                <?php foreach ( $unique_actions as $action_name ) : ?>
                    <option value="<?php echo esc_attr( $action_name ); ?>" <?php selected( $filter_action, $action_name ); ?>>
                        <?php echo esc_html( str_replace('_', ' ', ucfirst($action_name)) ); // Formatiraj akciju za prikaz ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button( __( 'Filtriraj', 'evidencija' ), 'button', 'filter_submit', false ); ?>
        </div>
        <br class="clear">
    </form>


    <?php if ( ! empty( $log_entries ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Datum i Vrijeme', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Akcija', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Tko je napravio (WP Korisnik)', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Korisnik (Doma)', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Lokacija', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Detalji Promjene', 'evidencija' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $log_entries as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->datum_vrijeme ) ) ); ?></td>
                        <td><?php echo esc_html( str_replace('_', ' ', ucfirst($entry->akcija)) ); ?></td>
                        <td><?php echo esc_html( $entry->user_display_name ? $entry->user_display_name : 'ID: ' . $entry->korisnik_id_akcije ); ?></td>
                        <td>
                            <?php
                                if ($entry->korisnik_id) {
                                    echo esc_html($entry->korisnik_ime . ' ' . $entry->korisnik_prezime);
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td>
                            <?php
                                if ($entry->lokacija_id) {
                                    echo esc_html($entry->lokacija_naziv);
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td>
                            <?php
                            // Korištenje nove pomoćne funkcije za formatiranje detalja
                            echo Evidencija_Helpers::format_audit_details($entry->akcija, $entry->detalji_promjene); // <-- PROMJENA OVDJE
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $current_page,
                    'type'      => 'plain',
                ) );
                ?>
            </div>
        </div>
    <?php else : ?>
        <p><?php echo esc_html__( 'Nema zabilježenih akcija u Audit Logu.', 'evidencija' ); ?></p>
    <?php endif; ?>
</div>