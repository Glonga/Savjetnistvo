<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_evidencija_settings' ) ) { // Provjera capabilityja
    wp_die( __( 'Nemate dovoljno dozvola za pristup ovoj stranici.', 'evidencija' ) );
}
?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Postavke Evidencije', 'evidencija' ); ?></h1>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'evidencija_options_group' ); // Naziv grupe postavki
        do_settings_sections( 'evidencija-postavke' ); // Slug stranice na kojoj su sekcije
        submit_button();
        ?>
    </form>
</div>