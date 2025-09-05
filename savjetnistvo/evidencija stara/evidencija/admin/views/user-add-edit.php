<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user_id = isset( $_GET['id'] ) ? absint( $_GET['id' ] ) : 0;
$page_title = __( 'Dodaj Novog Korisnika', 'evidencija' );

// Podaci koji će popuniti formu
$form_data = array();
$kontakti_obitelji = array();
$medicinski_podaci = array();
$dokumenti = array();
$audit_log_entries = array();

// Ako je bio redirect zbog greške pri spremanju, koristimo POST podatke
if ( isset( $_GET['save_error'] ) && $_GET['save_error'] == '1' ) {
    // Popunjavanje forme iz POST podataka
    $form_data['lokacija_id'] = isset($_POST['lokacija_id']) ? absint($_POST['lokacija_id']) : 0;
    $form_data['ime'] = isset($_POST['ime']) ? sanitize_text_field($_POST['ime']) : '';
    $form_data['prezime'] = isset($_POST['prezime']) ? sanitize_text_field($_POST['prezime']) : '';
    $form_data['datum_rodjenja'] = isset($_POST['datum_rodjenja']) ? sanitize_text_field($_POST['datum_rodjenja']) : '';
    $form_data['spol'] = isset($_POST['spol']) ? sanitize_text_field($_POST['spol']) : '';
    $form_data['datum_dolaska'] = isset($_POST['datum_dolaska']) ? sanitize_text_field($_POST['datum_dolaska']) : '';
    $form_data['datum_odlaska'] = isset($_POST['datum_odlaska']) ? sanitize_text_field($_POST['datum_odlaska']) : '';
    $form_data['status_smjestaja'] = isset($_POST['status_smjestaja']) ? sanitize_text_field($_POST['status_smjestaja']) : 'Trenutno smješten';
    $form_data['soba_id'] = isset($_POST['soba_id']) ? absint($_POST['soba_id']) : 0;
    $form_data['broj_kreveta'] = isset($_POST['broj_kreveta']) ? sanitize_text_field($_POST['broj_kreveta']) : '';
    $form_data['opce_biljeske'] = isset($_POST['opce_biljeske']) ? sanitize_textarea_field($_POST['opce_biljeske']) : '';
    $form_data['datum_zadnjeg_pregleda'] = isset($_POST['datum_zadnjeg_pregleda']) ? sanitize_text_field($_POST['datum_zadnjeg_pregleda']) : '';
    $form_data['ime_lijecnika'] = isset($_POST['ime_lijecnika']) ? sanitize_text_field($_POST['ime_lijecnika']) : '';

    // Za dinamička polja, popuni ih iz POST-a (mogu doći kao arrayevi, ne objekti)
    if (isset($_POST['kontakti_obitelji']) && is_array($_POST['kontakti_obitelji'])) {
        foreach ($_POST['kontakti_obitelji'] as $k_data) {
            $sanitized_k_data = array();
            foreach ($k_data as $key => $value) {
                $sanitized_k_data[$key] = is_string($value) ? sanitize_text_field($value) : '';
            }
            $kontakti_obitelji[] = (object) $sanitized_k_data;
        }
    }
    if (isset($_POST['medicinski_podaci']) && is_array($_POST['medicinski_podaci'])) {
        foreach ($_POST['medicinski_podaci'] as $m_data) {
            $sanitized_m_data = array();
            foreach ($m_data as $key => $value) {
                $sanitized_m_data[$key] = is_string($value) ? sanitize_text_field($value) : '';
            }
            $medicinski_podaci[] = (object) $sanitized_m_data;
        }
    }

} elseif ( $user_id ) {
    global $wpdb;
    $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
    $user_data_from_db = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name_korisnici WHERE id = %d", $user_id ) );

    if ( $user_data_from_db ) {
        $page_title = __( 'Uredi Korisnika', 'evidencija' ) . ': ' . esc_html( $user_data_from_db->ime ) . ' ' . esc_html( $user_data_from_db->prezime );

        // Popunjavanje forme iz podataka iz baze (konverzija za prikaz)
        $form_data['lokacija_id'] = $user_data_from_db->lokacija_id;
        $form_data['ime'] = $user_data_from_db->ime;
        $form_data['prezime'] = $user_data_from_db->prezime;
        $form_data['datum_rodjenja'] = $user_data_from_db->datum_rodjenja ? DateTime::createFromFormat('Y-m-d', $user_data_from_db->datum_rodjenja)->format('d-m-Y') : '';
        $form_data['spol'] = $user_data_from_db->spol;
        $form_data['datum_dolaska'] = $user_data_from_db->datum_dolaska ? DateTime::createFromFormat('Y-m-d', $user_data_from_db->datum_dolaska)->format('d-m-Y') : '';
        $form_data['datum_odlaska'] = $user_data_from_db->datum_odlaska ? DateTime::createFromFormat('Y-m-d', $user_data_from_db->datum_odlaska)->format('d-m-Y') : '';
        $form_data['status_smjestaja'] = $user_data_from_db->status_smjestaja;
        $form_data['soba_id'] = $user_data_from_db->soba_id;
        $form_data['broj_kreveta'] = $user_data_from_db->broj_kreveta;
        $form_data['opce_biljeske'] = $user_data_from_db->opce_biljeske;
        $form_data['datum_zadnjeg_pregleda'] = $user_data_from_db->datum_zadnjeg_pregleda ? DateTime::createFromFormat('Y-m-d', $user_data_from_db->datum_zadnjeg_pregleda)->format('d-m-Y') : '';
        $form_data['ime_lijecnika'] = $user_data_from_db->ime_lijecnika;

        // Dohvati dodatne podatke (kontakti, medicinski, dokumenti)
        $kontakti_obitelji = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_kontakti_obitelji WHERE korisnik_id = %d", $user_id ) );
        $medicinski_podaci = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_medicinski_podaci WHERE korisnik_id = %d", $user_id ) );
        $dokumenti = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_dokumenti WHERE korisnik_id = %d", $user_id ) );

        // Dohvati Audit Log za ovog korisnika
        $audit_log_entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT al.*, u.display_name AS user_display_name FROM {$wpdb->prefix}evidencija_audit_log al LEFT JOIN {$wpdb->users} u ON al.korisnik_id_akcije = u.ID WHERE al.korisnik_id = %d ORDER BY al.datum_vrijeme DESC",
                $user_id
            )
        );

    } else {
        $user_id = 0; // Korisnik nije pronađen, vraćamo se na dodavanje novog
    }
}

// Inicijaliziraj prazne vrijednosti ako nisu već postavljene iz POST-a ili baze
$form_data = wp_parse_args( $form_data, array(
    'lokacija_id'            => 0,
    'ime'                    => '',
    'prezime'                => '',
    'datum_rodjenja'         => '',
    'spol'                   => '',
    'datum_dolaska'          => '',
    'datum_odlaska'          => '',
    'status_smjestaja'       => 'Trenutno smješten', // Default status za novog korisnika
    'soba_id'                => 0,
    'broj_kreveta'           => '',
    'opce_biljeske'          => '',
    'datum_zadnjeg_pregleda' => '',
    'ime_lijecnika'          => '',
));

// Dohvati sve lokacije za dropdown
$locations = Evidencija_Helpers::get_all_locations();
?>

<div class="wrap">
    <h1><?php echo esc_html( $page_title ); ?></h1>

    <?php
    // Poruke će se prikazati automatski preko display_admin_notices() funkcije u klasi
    ?>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field( 'evidencija_add_edit_user_nonce', 'evidencija_user_nonce_field' ); ?>
        <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <div id="postbox-basic-data" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Osnovni podaci', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="lokacija_id"><?php echo esc_html__( 'Lokacija Doma', 'evidencija' ); ?></label></th>
                                        <td>
                                            <select name="lokacija_id" id="lokacija_id" required>
                                                <option value=""><?php echo esc_html__( 'Odaberite lokaciju', 'evidencija' ); ?></option>
                                                <?php foreach ( $locations as $location ) : ?>
                                                    <option value="<?php echo esc_attr( $location->id ); ?>"
                                                        <?php selected( $form_data['lokacija_id'], $location->id ); ?>>
                                                        <?php echo esc_html( $location->naziv_lokacije ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="ime"><?php echo esc_html__( 'Ime', 'evidencija' ); ?></label></th>
                                        <td><input type="text" name="ime" id="ime" class="regular-text" value="<?php echo esc_attr( $form_data['ime'] ); ?>" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prezime"><?php echo esc_html__( 'Prezime', 'evidencija' ); ?></label></th>
                                        <td><input type="text" name="prezime" id="prezime" class="regular-text" value="<?php echo esc_attr( $form_data['prezime'] ); ?>" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="datum_rodjenja"><?php echo esc_html__( 'Datum rođenja', 'evidencija' ); ?></label></th>
                                        <td><input type="text" name="datum_rodjenja" id="datum_rodjenja" class="regular-text evidencija-datepicker" value="<?php echo esc_attr( $form_data['datum_rodjenja'] ); ?>" placeholder="DD-MM-YYYY" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__( 'Spol', 'evidencija' ); ?></th>
                                        <td>
                                            <label><input type="radio" name="spol" value="Muško" <?php checked( $form_data['spol'], 'Muško' ); ?>> <?php echo esc_html__( 'Muško', 'evidencija' ); ?></label> &nbsp;
                                            <label><input type="radio" name="spol" value="Žensko" <?php checked( $form_data['spol'], 'Žensko' ); ?>> <?php echo esc_html__( 'Žensko', 'evidencija' ); ?></label> &nbsp;
                                            <label><input type="radio" name="spol" value="Ostalo" <?php checked( $form_data['spol'], 'Ostalo' ); ?>> <?php echo esc_html__( 'Ostalo', 'evidencija' ); ?></label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="postbox-accommodation-data" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Podaci o smještaju', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="datum_dolaska"><?php echo esc_html__( 'Datum dolaska', 'evidencija' ); ?></label></th>
                                        <td><input type="text" name="datum_dolaska" id="datum_dolaska" class="regular-text evidencija-datepicker" value="<?php echo esc_attr( $form_data['datum_dolaska'] ); ?>" placeholder="DD-MM-YYYY" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="datum_odlaska"><?php echo esc_html__( 'Datum odlaska', 'evidencija' ); ?></label></th>
                                        <td><input type="text" name="datum_odlaska" id="datum_odlaska" class="regular-text evidencija-datepicker" value="<?php echo esc_attr( $form_data['datum_odlaska'] ); ?>" placeholder="DD-MM-YYYY"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status_smjestaja"><?php echo esc_html__( 'Status smještaja', 'evidencija' ); ?></label></th>
                                        <td>
                                            <select name="status_smjestaja" id="status_smjestaja" required>
                                                <option value="Trenutno smješten" <?php selected( $form_data['status_smjestaja'], 'Trenutno smješten' ); ?>><?php echo esc_html__( 'Trenutno smješten', 'evidencija' ); ?></option>
                                                <option value="Očekuje dolazak" <?php selected( $form_data['status_smjestaja'], 'Očekuje dolazak' ); ?>><?php echo esc_html__( 'Očekuje dolazak', 'evidencija' ); ?></option>
                                                <option value="Otišao" <?php selected( $form_data['status_smjestaja'], 'Otišao' ); ?>><?php echo esc_html__( 'Otišao', 'evidencija' ); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="room-selection-row" style="<?php echo ($form_data['status_smjestaja'] === 'Trenutno smješten') ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="soba_id"><?php echo esc_html__( 'Soba', 'evidencija' ); ?></label></th>
                                        <td>
                                            <select name="soba_id" id="soba_id" <?php echo ($form_data['status_smjestaja'] === 'Trenutno smješten') ? 'required' : ''; ?>>
                                                <option value=""><?php echo esc_html__( 'Odaberite sobu', 'evidencija' ); ?></option>
                                                <?php
                                                // Ako je lokacija već odabrana (kod uređivanja ili greške)
                                                if (!empty($form_data['lokacija_id'])) {
                                                    $rooms_for_location = Evidencija_Helpers::get_all_rooms($form_data['lokacija_id']);
                                                    foreach ($rooms_for_location as $room) {
                                                        $occupied_spots_display = $this->get_occupied_spots_in_room($room->id);
                                                        $room_capacity_text = sprintf(__( '%s (popunjeno: %d/%d)', 'evidencija' ), esc_html($room->naziv_sobe), $occupied_spots_display, $room->kapacitet_sobe);
                                                        echo '<option value="' . esc_attr($room->id) . '" data-capacity="' . esc_attr($room->kapacitet_sobe) . '" data-occupied="' . esc_attr($occupied_spots_display) . '" ' . selected($form_data['soba_id'], $room->id, false) . '>' . $room_capacity_text . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <p id="room-occupancy-info" class="description" style="margin-top: 5px;"></p>
                                            <ul id="room-occupants-list" class="description" style="margin-top:5px;"></ul>                                       </td>
                                    </tr>
                                    <tr id="bed-number-row" style="<?php echo ($form_data['status_smjestaja'] === 'Trenutno smješten' && !empty($form_data['soba_id'])) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="broj_kreveta"><?php echo esc_html__( 'Broj kreveta (opcionalno)', 'evidencija' ); ?></label></th>
                                        <td><input type="text" name="broj_kreveta" id="broj_kreveta" class="regular-text" value="<?php echo esc_attr( $form_data['broj_kreveta'] ); ?>">
                                        <p class="description"><?php echo esc_html__( 'Oznaka kreveta unutar sobe (npr. A, B, 1, 2).', 'evidencija' ); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="postbox-contact-data" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Kontakt podaci obitelji/staratelja', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <div id="kontakti-obitelji-wrapper">
                                <?php if ( ! empty( $kontakti_obitelji ) ) : ?>
                                    <?php foreach ( $kontakti_obitelji as $i => $kontakt ) :
                                        $kontakt_ime = isset($kontakt->ime) ? $kontakt->ime : '';
                                        $kontakt_prezime = isset($kontakt->prezime) ? $kontakt->prezime : '';
                                        $kontakt_telefon = isset($kontakt->telefon) ? $kontakt->telefon : '';
                                        $kontakt_email = isset($kontakt->email) ? $kontakt->email : '';
                                        $kontakt_odnos = isset($kontakt->odnos_s_korisnikom) ? $kontakt->odnos_s_korisnikom : '';
                                        $kontakt_id_val = isset($kontakt->id) ? $kontakt->id : '0';
                                    ?>
                                        <div class="kontakt-obitelji-item evidencija-card">
                                            <input type="hidden" name="kontakti_obitelji[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $kontakt_id_val ); ?>">
                                            <label><?php echo esc_html__( 'Ime:', 'evidencija' ); ?> <input type="text" name="kontakti_obitelji[<?php echo $i; ?>][ime]" class="regular-text" value="<?php echo esc_attr( $kontakt_ime ); ?>" required></label><br>
                                            <label><?php echo esc_html__( 'Prezime:', 'evidencija' ); ?> <input type="text" name="kontakti_obitelji[<?php echo $i; ?>][prezime]" class="regular-text" value="<?php echo esc_attr( $kontakt_prezime ); ?>" required></label><br>
                                            <label><?php echo esc_html__( 'Telefon:', 'evidencija' ); ?> <input type="text" name="kontakti_obitelji[<?php echo $i; ?>][telefon]" class="regular-text" value="<?php echo esc_attr( $kontakt_telefon ); ?>" required></label><br>
                                            <label><?php echo esc_html__( 'E-mail:', 'evidencija' ); ?> <input type="email" name="kontakti_obitelji[<?php echo $i; ?>][email]" class="regular-text" value="<?php echo esc_attr( $kontakt_email ); ?>"></label><br>
                                            <label><?php echo esc_html__( 'Odnos s korisnikom:', 'evidencija' ); ?> <input type="text" name="kontakti_obitelji[<?php echo $i; ?>][odnos_s_korisnikom]" class="regular-text" value="<?php echo esc_attr( $kontakt_odnos ); ?>" required></label><br>
                                            <button type="button" class="button button-secondary remove-kontakt"><?php echo esc_html__( 'Ukloni kontakt', 'evidencija' ); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-kontakt-obitelji" class="button button-primary"><?php echo esc_html__( 'Dodaj novi kontakt', 'evidencija' ); ?></button>
                        </div>
                    </div>

                    <div id="postbox-medical-data" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Medicinski podaci', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <label for="ime_lijecnika"><?php echo esc_html__( 'Ime liječnika:', 'evidencija' ); ?></label>
                            <input type="text" name="ime_lijecnika" id="ime_lijecnika" class="regular-text" value="<?php echo esc_attr( $form_data['ime_lijecnika'] ); ?>"><br><br>

                            <label for="datum_zadnjeg_pregleda"><?php echo esc_html__( 'Datum zadnjeg pregleda:', 'evidencija' ); ?></label>
                            <input type="text" name="datum_zadnjeg_pregleda" id="datum_zadnjeg_pregleda" class="regular-text evidencija-datepicker" value="<?php echo esc_attr( $form_data['datum_zadnjeg_pregleda'] ); ?>" placeholder="DD-MM-YYYY"><br><br>

                            <p><?php echo esc_html__( 'Lijekovi, Dijagnoze, Alergije (svaki unos u novom redu):', 'evidencija' ); ?></p>
                            <div id="medicinski-podaci-wrapper">
                                <?php if ( ! empty( $medicinski_podaci ) ) : ?>
                                    <?php foreach ( $medicinski_podaci as $i => $med_data ) :
                                        $med_tip = isset($med_data->tip_podatka) ? $med_data->tip_podatka : 'lijek';
                                        $med_opis = isset($med_data->opis) ? $med_data->opis : '';
                                        $med_id_val = isset($med_data->id) ? $med_data->id : '0';
                                    ?>
                                        <div class="medicinski-podatak-item evidencija-card">
                                            <input type="hidden" name="medicinski_podaci[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $med_id_val ); ?>">
                                            <label><?php echo esc_html__( 'Tip:', 'evidencija' ); ?>
                                                <select name="medicinski_podaci[<?php echo $i; ?>][tip_podatka]" required>
                                                    <option value="lijek" <?php selected( $med_tip, 'lijek' ); ?>><?php echo esc_html__( 'Lijek', 'evidencija' ); ?></option>
                                                    <option value="dijagnoza" <?php selected( $med_tip, 'dijagnoza' ); ?>><?php echo esc_html__( 'Dijagnoza', 'evidencija' ); ?></option>
                                                    <option value="alergija" <?php selected( $med_tip, 'alergija' ); ?>><?php echo esc_html__( 'Alergija', 'evidencija' ); ?></option>
                                                    <option value="ostalo" <?php selected( $med_tip, 'ostalo' ); ?>><?php echo esc_html__( 'Ostalo', 'evidencija' ); ?></option>
                                                </select>
                                            </label><br>
                                            <label><?php echo esc_html__( 'Opis:', 'evidencija' ); ?> <textarea name="medicinski_podaci[<?php echo $i; ?>][opis]" class="large-text" required><?php echo esc_textarea( $med_opis ); ?></textarea></label><br>
                                            <button type="button" class="button button-secondary remove-medicinski-podatak"><?php echo esc_html__( 'Ukloni medicinski podatak', 'evidencija' ); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-medicinski-podatak" class="button button-primary"><?php echo esc_html__( 'Dodaj medicinski podatak', 'evidencija' ); ?></button>
                        </div>
                    </div>

                    <div id="postbox-documents" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Priloženi dokumenti', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <p><?php echo esc_html__( 'Trenutni dokumenti:', 'evidencija' ); ?></p>
                            <ul id="current-documents-list">
                                <?php if ( ! empty( $dokumenti ) ) : ?>
                                    <?php foreach ( $dokumenti as $document ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( wp_upload_dir()['baseurl'] . '/evidencija_docs/' . basename( $document->putanja_datoteke ) ); ?>" target="_blank">
                                                <?php echo esc_html( $document->naziv_dokumenta ); ?> (<?php echo esc_html( $document->tip_datoteke ); ?>)
                                            </a>
                                            <button type="button" class="button button-secondary remove-document" data-document-id="<?php echo esc_attr( $document->id ); ?>"><?php echo esc_html__( 'Ukloni', 'evidencija' ); ?></button>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <li><?php echo esc_html__( 'Nema priloženih dokumenata.', 'evidencija' ); ?></li>
                                <?php endif; ?>
                            </ul>
                            <p><?php echo esc_html__( 'Dodaj nove dokumente:', 'evidencija' ); ?></p>
                            <input type="file" name="novi_dokumenti[]" multiple="multiple">
                            <p class="description"><?php echo esc_html__( 'Možete odabrati više datoteka odjednom.', 'evidencija' ); ?></p>
                            <input type="hidden" name="deleted_documents_ids" id="deleted-documents-ids" value="">
                        </div>
                    </div>

                    <div id="postbox-general-notes" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Opće bilješke', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <textarea name="opce_biljeske" id="opce_biljeske" class="large-text" rows="5"><?php echo esc_textarea( $form_data['opce_biljeske'] ); ?></textarea>
                        </div>
                    </div>

                    <?php if ( $user_id && current_user_can( 'manage_evidencija_settings' ) ) : // Prikaz audit loga samo za admina i kad je korisnik već spremljen ?>
                        <div id="postbox-audit-log" class="postbox"> <h2 class="hndle"><span><?php echo esc_html__( 'Povijest promjena (Audit Log)', 'evidencija' ); ?></span></h2>
                            <div class="inside">
                                <?php if ( ! empty( $audit_log_entries ) ) : ?>
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html__( 'Datum i Vrijeme', 'evidencija' ); ?></th>
                                                <th><?php echo esc_html__( 'Akcija', 'evidencija' ); ?></th>
                                                <th><?php echo esc_html__( 'Korisnik', 'evidencija' ); ?></th>
                                                <th><?php echo esc_html__( 'Detalji', 'evidencija' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $audit_log_entries as $log_entry ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log_entry->datum_vrijeme ) ) ); ?></td>
                                                    <td><?php echo esc_html( str_replace('_', ' ', ucfirst($log_entry->akcija)) ); ?></td>
                                                    <td><?php echo esc_html( $log_entry->user_display_name ? $log_entry->user_display_name : 'ID: ' . $log_entry->korisnik_id_akcije ); ?></td>
                                                    <td>
                                                        <?php
                                                        echo Evidencija_Helpers::format_audit_details($log_entry->akcija, $log_entry->detalji_promjene);
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else : ?>
                                    <p><?php echo esc_html__( 'Nema zabilježenih promjena za ovog korisnika.', 'evidencija' ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><div id="postbox-container-1" class="postbox-container">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php echo esc_html__( 'Akcije', 'evidencija' ); ?></span></h2>
                        <div class="inside">
                            <?php submit_button( __( 'Spremi Korisnika', 'evidencija' ), 'primary large', 'evidencija_submit_user', false ); ?>
                            <?php if ( $user_id ) : // Gumb za ispis samo ako je korisnik vec spremljen ?>
                                <button type="button" id="print-karton-button" class="button button-secondary button-large">
                                    <?php echo esc_html__('Ispiši Karton (PDF)', 'evidencija'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div></div></div></form>
</div>

<template id="kontakt-obitelji-template">
    <div class="kontakt-obitelji-item evidencija-card">
        <input type="hidden" name="kontakti_obitelji[__INDEX__][id]" value="0">
        <label><span class="label-text"></span> <input type="text" name="kontakti_obitelji[__INDEX__][ime]" class="regular-text" required></label><br>
        <label><span class="label-text"></span> <input type="text" name="kontakti_obitelji[__INDEX__][prezime]" class="regular-text" required></label><br>
        <label><span class="label-text"></span> <input type="text" name="kontakti_obitelji[__INDEX__][telefon]" class="regular-text" required></label><br>
        <label><span class="label-text"></span> <input type="email" name="kontakti_obitelji[__INDEX__][email]" class="regular-text"></label><br>
        <label><span class="label-text"></span> <input type="text" name="kontakti_obitelji[__INDEX__][odnos_s_korisnikom]" class="regular-text" required></label><br>
        <button type="button" class="button button-secondary remove-kontakt"></button>
    </div>
</template>

<template id="medicinski-podatak-template">
    <div class="medicinski-podatak-item evidencija-card">
        <input type="hidden" name="medicinski_podaci[__INDEX__][id]" value="0">
        <label><span class="label-text"></span>
            <select name="medicinski_podaci[__INDEX__][tip_podatka]" required>
                <option value="lijek"></option>
                <option value="dijagnoza"></option>
                <option value="alergija"></option>
                <option value="ostalo"></option>
            </select>
        </label><br>
        <label><span class="label-text"></span> <textarea name="medicinski_podaci[__INDEX__][opis]" class="large-text" required></textarea></label><br>
        <button type="button" class="button button-secondary remove-medicinski-podatak"></button>
    </div>
</template>