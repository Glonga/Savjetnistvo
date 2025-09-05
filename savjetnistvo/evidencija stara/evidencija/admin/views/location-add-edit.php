<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$location_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$page_title = __( 'Dodaj Novu Lokaciju', 'evidencija' );

$form_data = array();
$rooms_data = array(); // <-- NOVO: Podaci o sobama

// Ako je bio redirect zbog greške pri spremanju, koristimo POST podatke
if ( isset( $_GET['save_error'] ) && $_GET['save_error'] == '1' ) {
    $form_data['naziv_lokacije'] = isset($_POST['naziv_lokacije']) ? sanitize_text_field($_POST['naziv_lokacije']) : '';
    $form_data['adresa_lokacije'] = isset($_POST['adresa_lokacije']) ? sanitize_text_field($_POST['adresa_lokacije']) : '';
    $form_data['kontakt_telefon'] = isset($_POST['kontakt_telefon']) ? sanitize_text_field($_POST['kontakt_telefon']) : '';
    $form_data['kontakt_email'] = isset($_POST['kontakt_email']) ? sanitize_email($_POST['kontakt_email']) : '';

    // Popunjavanje soba iz POST-a
    if (isset($_POST['sobe']) && is_array($_POST['sobe'])) {
        foreach ($_POST['sobe'] as $r_data) {
            $sanitized_r_data = array();
            foreach ($r_data as $key => $value) {
                $sanitized_r_data[$key] = is_string($value) ? sanitize_text_field($value) : absint($value);
            }
            $rooms_data[] = (object) $sanitized_r_data;
        }
    }

} elseif ( $location_id ) {
    $location_data = Evidencija_Helpers::get_location_by_id( $location_id );
    if ( $location_data ) {
        $page_title = __( 'Uredi Lokaciju', 'evidencija' ) . ': ' . esc_html( $location_data->naziv_lokacije );
        $form_data['naziv_lokacije'] = $location_data->naziv_lokacije;
        $form_data['adresa_lokacije'] = $location_data->adresa_lokacije;
        $form_data['kontakt_telefon'] = $location_data->kontakt_telefon;
        $form_data['kontakt_email'] = $location_data->kontakt_email;
        // Nema vise 'kapacitet' polja ovdje.

        // Dohvati sobe vezane za ovu lokaciju
        $rooms_data = Evidencija_Helpers::get_all_rooms($location_id);

    } else {
        $location_id = 0; // Lokacija nije pronađena, vratimo na dodavanje nove
    }
}

// Inicijaliziraj prazne vrijednosti ako nisu već postavljene
$form_data = wp_parse_args( $form_data, array(
    'naziv_lokacije'  => '',
    'adresa_lokacije' => '',
    'kontakt_telefon' => '',
    'kontakt_email'   => '',
));
?>

<div class="wrap">
    <h1><?php echo esc_html( $page_title ); ?></h1>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=evidencija-lokacije' ) ); ?>">
        <?php wp_nonce_field( 'evidencija_add_edit_location_nonce', 'evidencija_location_nonce_field' ); ?>
        <input type="hidden" name="location_id" value="<?php echo esc_attr( $location_id ); ?>">

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">

                    <div class="postbox">
                        <h2 class="hndle"><span><?php echo esc_html__( 'Osnovni podaci lokacije', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="naziv_lokacije"><?php echo esc_html__( 'Naziv Lokacije', 'evidencija' ); ?></label></th>
                                        <td>
                                            <input type="text" name="naziv_lokacije" id="naziv_lokacije" class="regular-text"
                                                   value="<?php echo esc_attr( $form_data['naziv_lokacije'] ); ?>" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="adresa_lokacije"><?php echo esc_html__( 'Adresa Lokacije', 'evidencija' ); ?></label></th>
                                        <td>
                                            <input type="text" name="adresa_lokacije" id="adresa_lokacije" class="regular-text"
                                                   value="<?php echo esc_attr( $form_data['adresa_lokacije'] ); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="kontakt_telefon"><?php echo esc_html__( 'Kontakt Telefon', 'evidencija' ); ?></label></th>
                                        <td>
                                            <input type="text" name="kontakt_telefon" id="kontakt_telefon" class="regular-text"
                                                   value="<?php echo esc_attr( $form_data['kontakt_telefon'] ); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="kontakt_email"><?php echo esc_html__( 'Kontakt E-mail', 'evidencija' ); ?></label></th>
                                        <td>
                                            <input type="email" name="kontakt_email" id="kontakt_email" class="regular-text"
                                                   value="<?php echo esc_attr( $form_data['kontakt_email'] ); ?>">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ( $location_id ) : // Prikazi upravljanje sobama samo za postojece lokacije ?>
                        <div class="postbox">
                            <h2 class="hndle"><span><?php echo esc_html__( 'Sobe na ovoj lokaciji', 'evidencija' ); ?></span></h2>
                            <div class="inside">
                                <p class="description"><?php echo esc_html__('Ovdje možete dodati, urediti ili obrisati sobe za ovu lokaciju. Svaka soba ima svoj naziv i kapacitet (broj kreveta).', 'evidencija'); ?></p>
                                <div id="rooms-wrapper">
                                    <?php if ( ! empty( $rooms_data ) ) : ?>
                                        <?php foreach ( $rooms_data as $i => $room ) : ?>
                                            <div class="room-item" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
                                                <input type="hidden" name="sobe[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $room->id ); ?>">
                                                <label><?php echo esc_html__( 'Naziv sobe:', 'evidencija' ); ?> <input type="text" name="sobe[<?php echo $i; ?>][naziv_sobe]" class="regular-text" value="<?php echo esc_attr( $room->naziv_sobe ); ?>" required></label><br>
                                                <label><?php echo esc_html__( 'Kapacitet sobe:', 'evidencija' ); ?> <input type="number" name="sobe[<?php echo $i; ?>][kapacitet_sobe]" class="small-text" value="<?php echo esc_attr( $room->kapacitet_sobe ); ?>" min="1" required></label><br>
                                                <button type="button" class="button button-secondary remove-room"><?php echo esc_html__( 'Ukloni sobu', 'evidencija' ); ?></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-room" class="button button-primary"><?php echo esc_html__( 'Dodaj novu sobu', 'evidencija' ); ?></button>
                                <input type="hidden" name="deleted_rooms_ids" id="deleted-rooms-ids" value="">
                            </div>
                        </div>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html__('Sobe možete dodati nakon što spremite ovu lokaciju.', 'evidencija'); ?></p>
                    <?php endif; ?>

                </div><div id="postbox-container-1" class="postbox-container">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php echo esc_html__( 'Spremi / Ažuriraj Lokaciju', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <?php submit_button( __( 'Spremi Lokaciju', 'evidencija' ), 'primary large', 'evidencija_submit_location', false ); ?>
                        </div>
                    </div>
                </div></div></div></form>
</div>

<template id="room-template">
    <div class="room-item" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
        <input type="hidden" name="sobe[__INDEX__][id]" value="0">
        <label><span class="label-text-room-name"></span> <input type="text" name="sobe[__INDEX__][naziv_sobe]" class="regular-text" required></label><br>
        <label><span class="label-text-room-capacity"></span> <input type="number" name="sobe[__INDEX__][kapacitet_sobe]" class="small-text" value="1" min="1" required></label><br>
        <button type="button" class="button button-secondary remove-room"></button>
    </div>
</template>