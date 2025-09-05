<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
$table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';
$table_name_sobe = $wpdb->prefix . 'evidencija_sobe'; // <-- NOVO: Tablica za sobe

// Logika za brisanje korisnika je premještena u class-evidencija-admin-pages.php::handle_plugin_actions()
// i handle_delete_user() metodu.

// Logika za filtriranje i pretragu
$current_location_id = isset( $_GET['location_id'] ) ? absint( $_GET['location_id'] ) : 0;
$current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : ''; // <-- NOVO: Filter po statusu
$search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

$where_clauses = array();
$sql_params = array();

if ( $current_location_id > 0 ) {
    $where_clauses[] = 'k.lokacija_id = %d';
    $sql_params[] = $current_location_id;
}

// NOVO: Dodaj filter po statusu
if ( ! empty( $current_status ) ) {
    $where_clauses[] = 'k.status_smjestaja = %s';
    $sql_params[] = $current_status;
}

if ( ! empty( $search_query ) ) {
    $search_pattern = '%' . $wpdb->esc_like( $search_query ) . '%';
    $where_clauses[] = '(k.ime LIKE %s OR k.prezime LIKE %s)';
    $sql_params[] = $search_pattern;
    $sql_params[] = $search_pattern;
}

$where_sql = '';
if ( ! empty( $where_clauses ) ) {
    $where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );
}

// Dohvati sve korisnike s podacima o lokaciji i sobi
$sql_users = "SELECT
                k.*,
                l.naziv_lokacije,
                s.naziv_sobe, -- <-- NOVO: Naziv sobe
                s.kapacitet_sobe -- <-- NOVO: Kapacitet sobe
            FROM
                {$table_name_korisnici} k
            LEFT JOIN
                {$table_name_lokacije} l ON k.lokacija_id = l.id
            LEFT JOIN
                {$table_name_sobe} s ON k.soba_id = s.id -- <-- NOVO: JOIN na tablicu soba
            $where_sql
            ORDER BY
                k.prezime ASC, k.ime ASC";

if ( ! empty( $sql_params ) ) {
    $users = $wpdb->get_results( $wpdb->prepare( $sql_users, $sql_params ) );
} else {
    $users = $wpdb->get_results( $sql_users );
}

// Dohvati sve lokacije za filter dropdown
$locations = Evidencija_Helpers::get_all_locations();

// Definicija statusa za filter dropdown
$status_options = [
    'Trenutno smješten' => __( 'Trenutno smješten', 'evidencija' ),
    'Očekuje dolazak' => __( 'Očekuje dolazak', 'evidencija' ),
    'Otišao' => __( 'Otišao', 'evidencija' ),
];
?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Svi Korisnici', 'evidencija' ); ?></h1>

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evidencija-korisnici-add' ) ); ?>" class="page-title-action">
        <?php echo esc_html__( 'Dodaj Novog Korisnika', 'evidencija' ); ?>
    </a>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    ?>

    <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <input type="hidden" name="page" value="evidencija-korisnici">
        <p class="search-box">
            <label class="screen-reader-text" for="user-search-input"><?php echo esc_html__( 'Pretraži Korisnike:', 'evidencija' ); ?></label>
            <input type="search" id="user-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>">
            <?php submit_button( __( 'Pretraži Korisnike', 'evidencija' ), 'button', 'submit', false ); ?>
        </p>

        <div class="alignleft actions">
            <label for="filter-by-location" class="screen-reader-text"><?php echo esc_html__( 'Filtriraj po lokaciji', 'evidencija' ); ?></label>
            <select name="location_id" id="filter-by-location">
                <option value="0"><?php echo esc_html__( 'Sve lokacije', 'evidencija' ); ?></option>
                <?php foreach ( $locations as $location ) : ?>
                    <option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( $current_location_id, $location->id ); ?>>
                        <?php echo esc_html( $location->naziv_lokacije ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="filter-by-status" class="screen-reader-text"><?php echo esc_html__( 'Filtriraj po statusu', 'evidencija' ); ?></label>
            <select name="status" id="filter-by-status">
                <option value=""><?php echo esc_html__( 'Svi statusi', 'evidencija' ); ?></option>
                <?php foreach ( $status_options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button( __( 'Filtriraj', 'evidencija' ), 'button', 'filter_action', false ); ?>
        </div>
        <br class="clear">
    </form>

    <?php if ( ! empty( $users ) ) : ?>
        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Lokacija', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Ime i Prezime', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Datum rođenja', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Datum dolaska', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Status', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Soba / Krevet', 'evidencija' ); ?></th> <th><?php echo esc_html__( 'Akcije', 'evidencija' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $users as $user ) : ?>
                    <tr>
                        <td><?php echo esc_html( $user->naziv_lokacije ? $user->naziv_lokacije : __( 'Nepoznato', 'evidencija' ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evidencija-korisnici-add&id=' . $user->id ) ); ?>">
                                <strong><?php echo esc_html( $user->ime . ' ' . $user->prezime ); ?></strong>
                            </a>
                        </td>
                        <td><?php echo esc_html( $user->datum_rodjenja ? DateTime::createFromFormat('Y-m-d', $user->datum_rodjenja)->format('d-m-Y') : '' ); ?></td>
                        <td><?php echo esc_html( $user->datum_dolaska ? DateTime::createFromFormat('Y-m-d', $user->datum_dolaska)->format('d-m-Y') : '' ); ?></td>
                        <td><?php echo esc_html( $user->status_smjestaja ); ?></td>
                        <td>
                            <?php
                            // NOVO: Prikaz naziva sobe i broja kreveta
                            if ($user->naziv_sobe) {
                                echo esc_html($user->naziv_sobe);
                                if ($user->broj_kreveta) {
                                    echo ' / ' . esc_html($user->broj_kreveta);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evidencija-korisnici-add&id=' . $user->id ) ); ?>">
                                <?php echo esc_html__( 'Uredi Karton', 'evidencija' ); ?>
                            </a> |
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=evidencija-korisnici&action=delete_user&id=' . $user->id ), 'delete_user_' . $user->id ) ); ?>"
                               onclick="return confirm('Jeste li sigurni da želite obrisati ovog korisnika i sve njegove povezane podatke? Ova akcija se ne može poništiti!');">
                                <?php echo esc_html__( 'Obriši', 'evidencija' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo esc_html__( 'Nema unesenih korisnika. Molimo, dodajte prvog korisnika.', 'evidencija' ); ?></p>
    <?php endif; ?>
</div>