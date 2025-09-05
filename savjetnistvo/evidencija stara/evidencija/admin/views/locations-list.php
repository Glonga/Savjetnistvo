<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';

// Logika za brisanje lokacije je premještena u class-evidencija-admin-pages.php::handle_plugin_actions()
// i handle_delete_location() metodu.

// Dohvati sve lokacije iz baze podataka
$locations = Evidencija_Helpers::get_all_locations(); // Pobrini se da get_all_locations() dohvaća i 'kapacitet'
?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Lokacije Domova', 'evidencija' ); ?></h1>

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evidencija-lokacije-add' ) ); ?>" class="page-title-action">
        <?php echo esc_html__( 'Dodaj Novu Lokaciju', 'evidencija' ); ?>
    </a>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    // settings_errors( 'evidencija_messages' ); // UKLONJENO PRETHODNO
    ?>

    <?php if ( ! empty( $locations ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Naziv Lokacije', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Adresa', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Telefon', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'E-mail', 'evidencija' ); ?></th>
                    <th><?php echo esc_html__( 'Akcije', 'evidencija' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $locations as $location ) : ?>
                    <tr>
                        <td><?php echo esc_html( $location->naziv_lokacije ); ?></td>
                        <td><?php echo esc_html( $location->adresa_lokacije ); ?></td>
                        <td><?php echo esc_html( $location->kontakt_telefon ); ?></td>
                        <td><?php echo esc_html( $location->kontakt_email ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evidencija-lokacije-add&id=' . $location->id ) ); ?>">
                                <?php echo esc_html__( 'Uredi', 'evidencija' ); ?>
                            </a> |
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=evidencija-lokacije&action=delete_location&id=' . $location->id ), 'delete_location_' . $location->id ) ); ?>"
                               onclick="return confirm('Jeste li sigurni da želite obrisati ovu lokaciju? Ako postoje korisnici ili sobe vezani za ovu lokaciju, to može dovesti do pogrešaka!');">
                                <?php echo esc_html__( 'Obriši', 'evidencija' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo esc_html__( 'Nema unesenih lokacija domova. Molimo, dodajte prvu lokaciju.', 'evidencija' ); ?></p>
    <?php endif; ?>
</div>