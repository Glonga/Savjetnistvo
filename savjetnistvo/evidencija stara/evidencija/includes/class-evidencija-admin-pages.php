<?php
/**
 * Plugin Name: Evidencija
 * Plugin URI: https://example.com/evidencija
 * Description: Plugin za vođenje evidencije korisnika i lokacija domova.
 * Version: 1.0.0
 * Author: Your Name/Company Name
 * Author URI: https://yourwebsite.com
 * License: GPL2
 */

// Spriječite direktan pristup datoteci
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Evidencija_Admin_Pages {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu_items' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_plugin_actions' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) ); // Registracija postavki
        add_action( 'wp_ajax_evidencija_get_rooms_by_location', array( $this, 'ajax_get_rooms_by_location' ) );
        add_action( 'wp_ajax_nopriv_evidencija_get_rooms_by_location', array( $this, 'ajax_get_rooms_by_location' ) );
        add_action( 'wp_ajax_evidencija_export_report', array( $this, 'ajax_export_report' ) );
        add_action( 'wp_ajax_nopriv_evidencija_export_report', array( $this, 'ajax_export_report' ) );
        add_action( 'wp_ajax_evidencija_get_room_details', array( $this, 'ajax_get_room_details' ) );    }

    /**
     * Obrađuje sve submit i delete akcije plugina na admin_init hooku.
     * Ovo osigurava da se preusmjeravanja događaju prije bilo kakvog HTML outputa.
     */
    public function handle_plugin_actions() {
        // Obrada spremanja/ažuriranja lokacija (sada uključuje sobe)
        if ( isset( $_POST['evidencija_submit_location'] ) ) {
            if ( wp_verify_nonce( $_POST['evidencija_location_nonce_field'], 'evidencija_add_edit_location_nonce' ) ) {
                $this->handle_location_submission();
            } else {
                $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-lokacije-add' ), __( 'Sigurnosna provjera nije uspjela za lokacije. Molimo pokušajte ponovo.', 'evidencija' ), 'error' );
            }
        }

        // Obrada brisanja lokacija
        if ( isset( $_GET['page'] ) && $_GET['page'] == 'evidencija-lokacije' && isset( $_GET['action'] ) && $_GET['action'] == 'delete_location' && isset( $_GET['id'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_location_' . absint( $_GET['id'] ) ) ) {
                $this->handle_delete_location( absint( $_GET['id'] ) );
            } else {
                $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-lokacije' ), __( 'Sigurnosna provjera za brisanje lokacije nije uspjela. Molimo pokušajte ponovo.', 'evidencija' ), 'error' );
            }
        }

        // Obrada spremanja/ažuriranja korisnika
        if ( isset( $_POST['evidencija_submit_user'] ) ) {
            if ( wp_verify_nonce( $_POST['evidencija_user_nonce_field'], 'evidencija_add_edit_user_nonce' ) ) {
                $this->handle_user_submission();
            } else {
                $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-korisnici-add' ), __( 'Sigurnosna provjera nije uspjela za korisnike. Molimo pokušajte ponovo.', 'evidencija' ), 'error' );
            }
        }

        // Obrada brisanja korisnika
        if ( isset( $_GET['page'] ) && $_GET['page'] == 'evidencija-korisnici' && isset( $_GET['action'] ) && $_GET['action'] == 'delete_user' && isset( $_GET['id'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_user_' . absint( $_GET['id'] ) ) ) {
                $this->handle_delete_user( absint( $_GET['id'] ) );
            } else {
                $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-korisnici' ), __( 'Sigurnosna provjera za brisanje korisnika nije uspjela. Molimo pokušajte ponovo.', 'evidencija' ), 'error' );
            }
        }
    }


    /**
     * Dodaje stavke u WordPress administratorski izbornik.
     */
    public function add_admin_menu_items() {
        // Glavna stavka izbornika
        add_menu_page(
            __( 'Evidencija', 'evidencija' ),
            __( 'Evidencija', 'evidencija' ),
            'manage_options', // Administrator (ili 'manage_evidencija_locations' za prvu stavku)
            'evidencija-lokacije', // Ovdje koristimo slug prve podstranice da bude primarna
            array( $this, 'render_locations_list_page' ),
            'dashicons-building',
            20
        );

        // Podstavke izbornika
        add_submenu_page(
            'evidencija-lokacije', // Roditeljski slug (treba biti isti kao glavni ili prvi podizbornik)
            __( 'Lokacije Domova', 'evidencija' ),
            __( 'Lokacije Domova', 'evidencija' ),
            'manage_evidencija_locations', // Provjera capabilityja
            'evidencija-lokacije', // Slug ove podstranice
            array( $this, 'render_locations_list_page' )
        );

        add_submenu_page(
            'evidencija-lokacije', // Roditeljski slug
            __( 'Dodaj Novu Lokaciju', 'evidencija' ),
            __( 'Dodaj Novu Lokaciju', 'evidencija' ),
            'manage_evidencija_locations', // Provjera capabilityja
            'evidencija-lokacije-add', // Slug za dodavanje/uređivanje lokacije
            array( $this, 'render_location_add_edit_page' )
        );


        add_submenu_page(
            'evidencija-lokacije', // Roditeljski slug
            __( 'Svi Korisnici', 'evidencija' ),
            __( 'Svi Korisnici', 'evidencija' ),
            'manage_evidencija_users', // Provjera capabilityja
            'evidencija-korisnici', // Slug za popis korisnika
            array( $this, 'render_users_list_page' )
        );

        add_submenu_page(
            'evidencija-lokacije', // Roditeljski slug
            __( 'Dodaj Novog Korisnika', 'evidencija' ),
            __( 'Dodaj Novog Korisnika', 'evidencija' ),
            'manage_evidencija_users', // Provjera capabilityja
            'evidencija-korisnici-add', // Slug za dodavanje/uređivanje korisnika
            array( $this, 'render_user_add_edit_page' )
        );

        add_submenu_page(
            'evidencija-lokacije', // Roditeljski slug
            __( 'Izvješća', 'evidencija' ),
            __( 'Izvješća', 'evidencija' ),
            'view_evidencija_reports', // Provjera capabilityja
            'evidencija-izvjesca',
            array( $this, 'render_reports_page' )
        );

        add_submenu_page(
            'evidencija-lokacije', // Roditeljski slug
            __( 'Postavke Evidencije', 'evidencija' ),
            __( 'Postavke', 'evidencija' ),
            'manage_evidencija_settings', // Provjera capabilityja
            'evidencija-postavke',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'evidencija-lokacije',
            __( 'Audit Log', 'evidencija' ),
            __( 'Audit Log', 'evidencija' ),
            'manage_evidencija_settings', // Pretpostavimo da samo admin može vidjeti logove
            'evidencija-audit-log',
            array( $this, 'render_audit_log_page' )
        );
    }

    /**
     * Učitava CSS i JS datoteke za administratorsko sučelje.
     */
    public function enqueue_admin_assets() {
        global $wpdb;
        $screen = get_current_screen();
        $user_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $kontakti_count = 0;
        $medicinski_count = 0;
        $room_count = 0;
        $selected_room_id = 0;


        // Učitaj assets samo na relevantnim stranicama plugina
        if ( in_array( $screen->id, array( 'toplevel_page_evidencija', 'evidencija_page_evidencija-lokacije', 'evidencija_page_evidencija-lokacije-add', 'evidencija_page_evidencija-korisnici', 'evidencija_page_evidencija-korisnici-add', 'evidencija_page_evidencija-izvjesca', 'evidencija_page_evidencija-postavke', 'evidencija_page_evidencija-audit-log' ) ) ) {

            // Dohvati brojeve za inicijalizaciju JS indeksa
            if ( $screen->id == 'evidencija_page_evidencija-korisnici-add' && $user_id > 0 ) {
                $kontakti_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}evidencija_kontakti_obitelji WHERE korisnik_id = %d", $user_id ) );
                $medicinski_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}evidencija_medicinski_podaci WHERE korisnik_id = %d", $user_id ) );
                // Dohvati trenutno odabranu sobu za korisnika
                $user_current_room_id = $wpdb->get_var( $wpdb->prepare( "SELECT soba_id FROM {$wpdb->prefix}evidencija_korisnici WHERE id = %d", $user_id ) );
                $selected_room_id = absint($user_current_room_id);
            }
            // Dohvati broj soba ako je stranica za uređivanje lokacije
            if ( $screen->id == 'evidencija_page_evidencija-lokacije-add' && isset($_GET['id']) ) {
                $location_id = absint($_GET['id']);
                $room_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}evidencija_sobe WHERE lokacija_id = %d", $location_id ) );
            }


            wp_enqueue_style( 'evidencija-admin-style', EVIDENCIJA_PLUGIN_URL . 'admin/css/admin-style.css', array(), '1.0.0' );
            wp_enqueue_style( 'jquery-ui-datepicker-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );

            wp_enqueue_script( 'evidencija-admin-script', EVIDENCIJA_PLUGIN_URL . 'admin/js/admin-scripts.js', array( 'jquery', 'jquery-ui-datepicker' ), '1.0.0', true );

            // KLJUČNO: OVDJE SE SADA ŠALJU SVI TEKSTOVI ZA PRIJEVOD U JAVASCRIPT
            wp_localize_script( 'evidencija-admin-script', 'evidencija_vars', array(
                'kontaktIndex'     => (int) $kontakti_count,
                'medicinskiIndex'  => (int) $medicinski_count,
                'roomIndex'        => (int) $room_count,
                'selectedRoomId'   => (int) $selected_room_id,
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'nonce_get_rooms'  => wp_create_nonce( 'evidencija_get_rooms_nonce' ),
                'nonce_get_room_details' => wp_create_nonce( 'evidencija_get_room_details_nonce' ),
                'nonce_export_report' => wp_create_nonce('evidencija_export_report_nonce'),
                'i18n' => array(
                    'ime'                  => esc_html__( 'Ime:', 'evidencija' ),
                    'prezime'              => esc_html__( 'Prezime:', 'evidencija' ),
                    'telefon'              => esc_html__( 'Telefon:', 'evidencija' ),
                    'email'                => esc_html__( 'E-mail:', 'evidencija' ),
                    'odnos_s_korisnikom'   => esc_html__( 'Odnos s korisnikom:', 'evidencija' ),
                    'ukloni_kontakt'       => esc_html__( 'Ukloni kontakt', 'evidencija' ),
                    'tip'                  => esc_html__( 'Tip:', 'evidencija' ),
                    'lijek'                => esc_html__( 'Lijek', 'evidencija' ),
                    'dijagnoza'            => esc_html__( 'Dijagnoza', 'evidencija' ),
                    'alergija'             => esc_html__( 'Alergija', 'evidencija' ),
                    'ostalo'               => esc_html__( 'Ostalo', 'evidencija' ),
                    'opis'                 => esc_html__( 'Opis:', 'evidencija' ),
                    'ukloni_medicinski_podatak' => esc_html__( 'Ukloni medicinski podatak', 'evidencija' ),
                    'datum_greska_format' => esc_html__( 'Neispravan format datuma. Koristite DD-MM-YYYY ili DD.MM.YYYY.', 'evidencija' ),
                    'provjeri_datume' => esc_html__( 'Molimo provjerite sva polja za datume. Neki datumi nisu u ispravnom formatu.', 'evidencija' ),
                    'odaberite_sobu' => esc_html__('Odaberite sobu', 'evidencija'),
                    'nema_soba' => esc_html__('Nema dostupnih soba za ovu lokaciju.', 'evidencija'),
                    'ukloni_sobu' => esc_html__('Ukloni sobu', 'evidencija'),
                    'naziv_sobe' => esc_html__('Naziv sobe:', 'evidencija'),
                    'kapacitet_sobe' => esc_html__('Kapacitet sobe:', 'evidencija'),
                    'potvrdi_brisanje_sobe' => esc_html__('Jeste li sigurni da želite obrisati ovu sobu? Svi korisnici vezani za ovu sobu će izgubiti dodjelu sobe.', 'evidencija'),
                    'soba_puna' => esc_html__('Odabrana soba je puna. Molimo odaberite drugu sobu.', 'evidencija'),
                    'potvrdi_brisanje_dokumenta' => esc_html__('Jeste li sigurni da želite ukloniti ovaj dokument?', 'evidencija'),
                    'popunjenost_sobe_format' => esc_html__('Trenutna popunjenost: %d/%d', 'evidencija'),
                    // Tekstovi za ispis kartona
                    'karton_korisnika' => esc_html__('Karton korisnika', 'evidencija'),
                    'datum_ispisa' => esc_html__('Datum ispisa', 'evidencija'),
                    'osnovni_podaci' => esc_html__('Osnovni podaci', 'evidencija'),
                    'podaci_o_smjestaju' => esc_html__('Podaci o smještaju', 'evidencija'),
                    'kontakt_podaci_obitelji_staratelja' => esc_html__('Kontakt podaci obitelji/staratelja', 'evidencija'),
                    'medicinski_podaci_print' => esc_html__('Medicinski podaci', 'evidencija'),
                    'prilozeni_dokumenti_print' => esc_html__('Priloženi dokumenti', 'evidencija'),
                    'opce_biljeske_print' => esc_html__('Opće bilješke', 'evidencija'),
                    'trenutni_dokumenti' => esc_html__('Trenutni dokumenti', 'evidencija'),
                    'lokacija' => esc_html__( 'Lokacija', 'evidencija'),
                    'soba' => esc_html__( 'Soba', 'evidencija'),
                    'broj_kreveta' => esc_html__( 'Broj kreveta', 'evidencija'),
                    'trenutni_korisnik' => esc_html__( '(trenutni korisnik)', 'evidencija'),
                ),
            ) );
        }
    }



    /**
     * Prikazuje administratorske obavijesti (koristeći $_GET umjesto settings_errors).
     */
    public function display_admin_notices() {
        if ( isset( $_GET['custom_message'] ) && isset( $_GET['message_type'] ) ) {
            $message = esc_html( wp_unslash( $_GET['custom_message'] ) );
            $type = sanitize_text_field( $_GET['message_type'] ); // 'success', 'error', 'info', 'warning'

            // Dodatna provjera za sigurnost i validne tipove poruka
            $allowed_types = ['success', 'error', 'info', 'warning'];
            if (!in_array($type, $allowed_types)) {
                $type = 'info'; // Fallback
            }

            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $message . '</p></div>';
        }
    }

    /**
     * Pomoćna funkcija za preusmjeravanje s porukom.
     *
     * @param string $redirect_url URL na koji se preusmjerava.
     * @param string $message Poruka za prikaz.
     * @param string $type Tip poruke ('success', 'error', 'info', 'warning').
     */
    private function redirect_with_message($redirect_url, $message, $type) {
        $redirect_url = add_query_arg('custom_message', urlencode($message), $redirect_url);
        $redirect_url = add_query_arg('message_type', $type, $redirect_url);
        wp_redirect($redirect_url);
        exit;
    }


    /**
     * Funkcija za logiranje akcija u Audit Log tablicu.
     *
     * @param string $action Tip akcije (npr. 'created_user', 'updated_location', 'deleted_document').
     * @param int|null $user_id ID korisnika na kojeg se akcija odnosi (ako je primjenjivo).
     * @param int|null $location_id ID lokacije na koju se akcija odnosi (ako je primjenjivo).
     * @param array $details Dodatni detalji o promjeni (npr. 'old_value', 'new_value').
     */
    private function log_audit_action($action, $user_id = null, $location_id = null, $details = array()) {
        global $wpdb;
        $table_name_audit_log = $wpdb->prefix . 'evidencija_audit_log';
        $current_wp_user_id = get_current_user_id();

        // Pokušaj dohvatiti lokacija_id ako je samo user_id poznat
        if (empty($location_id) && !empty($user_id)) {
            $user_location = $wpdb->get_var( $wpdb->prepare( "SELECT lokacija_id FROM {$wpdb->prefix}evidencija_korisnici WHERE id = %d", $user_id ) );
            if ($user_location) {
                $location_id = $user_location;
            }
        }

        $wpdb->insert(
            $table_name_audit_log,
            array(
                'korisnik_id'        => $user_id,
                'lokacija_id'        => $location_id,
                'datum_vrijeme'      => current_time('mysql', 1), // Trenutno UTC vrijeme
                'korisnik_id_akcije' => $current_wp_user_id,
                'akcija'             => $action,
                'detalji_promjene'   => !empty($details) ? json_encode($details) : null,
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%s' )
        );
    }


    /**
     * Funkcija za obradu spremanja/ažuriranja lokacija (sada uključuje sobe).
     */
    private function handle_location_submission() {
        if ( ! current_user_can( 'manage_evidencija_locations' ) ) {
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-lokacije' ), __( 'Nemate dozvolu za izvršavanje ove akcije.', 'evidencija' ), 'error' );
        }

        global $wpdb;
        $table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';

        $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
        $naziv_lokacije = sanitize_text_field( $_POST['naziv_lokacije'] );
        $adresa_lokacije = sanitize_text_field( $_POST['adresa_lokacije'] );
        $kontakt_telefon = sanitize_text_field( $_POST['kontakt_telefon'] );
        $kontakt_email = sanitize_email( $_POST['kontakt_email'] );
        $current_user_id = get_current_user_id();

        // Dohvati stare podatke lokacije za Audit Log ako je ažuriranje
        $old_location_data = null;
        if ($location_id) {
            $old_location_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name_lokacije WHERE id = %d", $location_id ), ARRAY_A );
        }


        if ( empty( $naziv_lokacije ) ) {
            $redirect_url = admin_url( 'admin.php?page=evidencija-lokacije-add' );
            if ($location_id) {
                $redirect_url = add_query_arg( 'id', $location_id, $redirect_url );
            }
            $this->redirect_with_message( $redirect_url, __( 'Naziv lokacije je obavezan.', 'evidencija' ), 'error' );
        }

        $success_message = '';
        $message_type = 'success';
        $location_redirect_url = admin_url( 'admin.php?page=evidencija-lokacije' ); // Default redirect

        $new_data_location = array(
            'naziv_lokacije'  => $naziv_lokacije,
            'adresa_lokacije' => $adresa_lokacije,
            'kontakt_telefon' => $kontakt_telefon,
            'kontakt_email'   => $kontakt_email,
        );

        if ( $location_id ) {
            $updated = $wpdb->update(
                $table_name_lokacije,
                $new_data_location,
                array( 'id' => $location_id ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            if ( false !== $updated ) { // Check for false (error) or 0 (no change)
                if ( $updated === 0 ) {
                    $success_message = __( 'Lokacija je ažurirana, ali nije bilo promjena podataka.', 'evidencija' );
                    $message_type = 'info';
                } else {
                    $success_message = __( 'Lokacija je uspješno ažurirana!', 'evidencija' );
                    $message_type = 'success';

                    // Audit Log: Ažurirana lokacija
                    $diff_data = array_diff_assoc($new_data_location, $old_location_data); // Pronađi samo promijenjene vrijednosti
                    if (!empty($diff_data)) {
                        $this->log_audit_action('updated_location', null, $location_id, array('old_data' => $old_location_data, 'new_data' => $new_data_location, 'diff' => $diff_data));
                    }
                }
            } else {
                $success_message = __( 'Došlo je do pogreške prilikom ažuriranja lokacije.', 'evidencija' );
                $message_type = 'error';
            }
        } else {
            $new_data_location['kreirao_korisnik_id'] = $current_user_id;
            $inserted = $wpdb->insert(
                $table_name_lokacije,
                $new_data_location,
                array( '%s', '%s', '%s', '%s', '%d' )
            );
            if ( $inserted ) { // $inserted je ID novog reda ako je uspješno
                $success_message = __( 'Nova lokacija je uspješno dodana!', 'evidencija' );
                $message_type = 'success';
                $location_id = $wpdb->insert_id; // Dohvati ID novo unesene lokacije

                // Audit Log: Kreirana lokacija
                $this->log_audit_action('created_location', null, $location_id, $new_data_location);
            } else {
                $success_message = __( 'Došlo je do pogreške prilikom dodavanja lokacije.', 'evidencija' );
                $message_type = 'error';
            }
        }

        // AKCIJE VEZANE ZA SOBE UNUTAR LOKACIJE
        if ( ($message_type == 'success' || $message_type == 'info' || $message_type == 'warning') && $location_id ) { // Nastavi s obradom soba samo ako je lokacija uspješno spremljena
            $sub_messages = [];
            $sub_message_type_rooms = 'success'; // Status za poruke iz soba

            $existing_rooms = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name_sobe} WHERE lokacija_id = %d", $location_id ), ARRAY_A );
            $submitted_room_ids = [];

            if ( ! empty( $_POST['sobe'] ) && is_array( $_POST['sobe'] ) ) {
                foreach ( $_POST['sobe'] as $room_data ) {
                    $room_id = isset( $room_data['id'] ) ? absint( $room_data['id'] ) : 0;
                    $naziv_sobe = isset( $room_data['naziv_sobe'] ) ? sanitize_text_field( $room_data['naziv_sobe'] ) : '';
                    $kapacitet_sobe = isset( $room_data['kapacitet_sobe'] ) ? absint( $room_data['kapacitet_sobe'] ) : 1;

                    if ( empty($naziv_sobe) || $kapacitet_sobe < 1 ) {
                        $sub_messages[] = __( 'Upozorenje: Neke sobe su prazne ili imaju neispravan kapacitet i nisu spremljene.', 'evidencija' );
                        if($sub_message_type_rooms != 'error') $sub_message_type_rooms = 'warning';
                        continue;
                    }

                    $new_room_data = array(
                        'lokacija_id'    => $location_id,
                        'naziv_sobe'     => $naziv_sobe,
                        'kapacitet_sobe' => $kapacitet_sobe,
                    );
                    $format_room = array( '%d', '%s', '%d' );

                    $old_room_data = null;
                    foreach ($existing_rooms as $er) {
                        if (isset($er['id']) && $er['id'] == $room_id) {
                            $old_room_data = $er;
                            break;
                        }
                    }

                    if ( $room_id && in_array( $room_id, array_column($existing_rooms, 'id') ) ) {
                        $updated_room = $wpdb->update( $table_name_sobe, $new_room_data, array( 'id' => $room_id ), $format_room, array( '%d' ) );
                        if ($updated_room === false) { $sub_messages[] = __( 'Pogreška pri ažuriranju sobe.', 'evidencija' ); $sub_message_type_rooms = 'error'; } else if ($updated_room > 0) {
                            $this->log_audit_action('updated_room', null, $lokacija_id, array('room_id' => $room_id, 'old_data' => $old_room_data, 'new_data' => $new_room_data, 'diff' => array_diff_assoc($new_room_data, $old_room_data)));
                        }
                        $submitted_room_ids[] = $room_id;
                    } else {
                        $new_room_data['kreirao_korisnik_id'] = $current_user_id;
                        $inserted_room = $wpdb->insert( $table_name_sobe, $new_room_data, array_merge( $format_room, ['%d'] ) );
                        if ($inserted_room === false) { $sub_messages[] = __( 'Pogreška pri dodavanju nove sobe.', 'evidencija' ); $sub_message_type_rooms = 'error'; }
                        if ($wpdb->insert_id) {
                            $submitted_room_ids[] = $wpdb->insert_id;
                            $this->log_audit_action('created_room', null, $lokacija_id, $new_room_data);
                        }
                    }
                }
            }

            $existing_room_ids_only = array_column($existing_rooms, 'id');
            $rooms_to_delete = array_diff( $existing_room_ids_only, $submitted_room_ids );
            if ( ! empty( $rooms_to_delete ) ) {
                foreach ($rooms_to_delete as $dr_id) {
                    $room_name_for_msg = '';
                    foreach($existing_rooms as $er_data) {
                        if($er_data['id'] == $dr_id) {
                            $room_name_for_msg = $er_data['naziv_sobe'];
                            break;
                        }
                    }

                    $has_users_in_room = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}evidencija_korisnici WHERE soba_id = %d", $dr_id ) );
                    if ($has_users_in_room > 0) {
                        $sub_messages[] = sprintf(__( 'Upozorenje: Soba "%s" nije obrisana jer ima vezane korisnike.', 'evidencija' ), esc_html($room_name_for_msg));
                        if($sub_message_type_rooms != 'error') $sub_message_type_rooms = 'warning';
                        continue;
                    }

                    $deleted_room_db = $wpdb->delete( $table_name_sobe, array( 'id' => $dr_id ), array( '%d' ) );
                    if ($deleted_room_db === false) { $sub_messages[] = __( 'Pogreška pri brisanju sobe iz baze.', 'evidencija' ); $sub_message_type_rooms = 'error'; } else if ($deleted_room_db > 0) {
                        $deleted_r_data = null;
                        foreach($existing_rooms as $er) { if (isset($er['id']) && $er['id'] == $dr_id) { $deleted_r_data = $er; break; } }
                        $this->log_audit_action('deleted_room', null, $lokacija_id, array('room_id' => $dr_id, 'deleted_data' => $deleted_r_data));
                    }
                }
            }
            
            // Ažuriraj glavni message_type ako je bilo problema sa sobama
            if ($sub_message_type_rooms == 'error' && $message_type != 'error') $message_type = 'error';
            if ($sub_message_type_rooms == 'warning' && $message_type == 'success') $message_type = 'warning';

            // Dodaj poruke o sobama na kraju glavne poruke
            if (!empty($sub_messages)) {
                $success_message .= ' ' . implode(' ', $sub_messages);
            }
        }

        $this->redirect_with_message( $location_redirect_url, $success_message, $message_type );
    }

    /**
     * Funkcija za obradu brisanja lokacije.
     */
    private function handle_delete_location( $location_id ) {
        if ( ! current_user_can( 'delete_evidencija_locations' ) ) {
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-lokacije' ), __( 'Nemate dozvolu za izvršavanje ove akcije.', 'evidencija' ), 'error' );
        }

        global $wpdb;
        $table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';

        $has_users = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}evidencija_korisnici WHERE lokacija_id = %d", $location_id ) );
        $has_rooms = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_sobe} WHERE lokacija_id = %d", $location_id ) );


        $delete_message = '';
        $message_type = 'success';

        // Dohvati podatke lokacije prije brisanja za Audit Log
        $deleted_location_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name_lokacije WHERE id = %d", $location_id ), ARRAY_A );


        if ( $has_users > 0 ) {
            $delete_message = __( 'Lokaciju nije moguće obrisati jer postoje korisnici vezani za nju. Molimo, prvo premjestite ili obrišite korisnike.', 'evidencija' );
            $message_type = 'error';
        } elseif ( $has_rooms > 0 ) {
            $delete_message = __( 'Lokaciju nije moguće obrisati jer postoje sobe vezane za nju. Molimo, prvo obrišite sobe.', 'evidencija' );
            $message_type = 'error';
        } else {
            $deleted = $wpdb->delete( $table_name_lokacije, array( 'id' => $location_id ), array( '%d' ) );
            if ( $deleted ) {
                $delete_message = __( 'Lokacija je uspješno obrisana!', 'evidencija' );
                $message_type = 'success';
                // Audit Log: Izbrisana lokacija
                $this->log_audit_action('deleted_location', null, $location_id, $deleted_location_data);

            } else {
                $delete_message = __( 'Došlo je do pogreške prilikom brisanja lokacije.', 'evidencija' );
                $message_type = 'error';
            }
        }
        $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-lokacije' ), $delete_message, $message_type );
    }

    /**
     * Pomoćna funkcija za parsiranje datuma iz različitih formata (DD-MM-YYYY, DD.MM.YYYY, Jamboree-MM-DD).
     * Vraća format 'YYYY-MM-DD' ili null ako parsiranje nije uspješno.
     *
     * @param string $date_string Datum u DD-MM-YYYY, DD.MM.YYYY ili Jamboree-MM-DD formatu.
     * @return string|null Formatirani datum za DB ili null.
     */
    private function parse_date_for_db( $date_string ) {
        if ( empty( $date_string ) ) {
            return null;
        }

        $date_obj = null;
        $formats_to_try = ['d-m-Y', 'd.m.Y.', 'Y-m-d']; // Prioriteti formata

        foreach ($formats_to_try as $format) {
            $temp_obj = DateTime::createFromFormat($format, $date_string);
            // Provjera da li formatirani string odgovara originalnom (stroža provjera)
            if ($temp_obj && $temp_obj->format($format) === $date_string) {
                $date_obj = $temp_obj;
                break; // Pronađen ispravan format, prekinuti petlju
            }
        }

        if ($date_obj) {
            return $date_obj->format('Y-m-d');
        }

        return null; // Parsiranje nije uspjelo
    }


    /**
     * Funkcija za obradu spremanja/ažuriranja korisnika.
     */
    public function handle_user_submission() { // Promijenjeno u public privremeno za debugging
        if ( ! current_user_can( 'manage_evidencija_users' ) ) {
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-korisnici' ), __( 'Nemate dozvolu za izvršavanje ove akcije.', 'evidencija' ), 'error' );
        }

        global $wpdb;
        $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
        $table_name_kontakti_obitelji = $wpdb->prefix . 'evidencija_kontakti_obitelji';
        $table_name_medicinski_podaci = $wpdb->prefix . 'evidencija_medicinski_podaci';
        $table_name_dokumenti = $wpdb->prefix . 'evidencija_dokumenti';
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';

        $user_id_before_save = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $current_user_id = get_current_user_id();

        // Dohvati i sanitiziraj sve sirove (raw) podatke iz POST-a
        $lokacija_id = isset( $_POST['lokacija_id'] ) ? absint( $_POST['lokacija_id'] ) : 0;
        $ime = isset( $_POST['ime'] ) ? sanitize_text_field( $_POST['ime'] ) : '';
        $prezime = isset( $_POST['prezime'] ) ? sanitize_text_field( $_POST['prezime'] ) : '';
        $datum_rodjenja_raw = isset( $_POST['datum_rodjenja'] ) ? sanitize_text_field( $_POST['datum_rodjenja'] ) : '';
        $spol = isset( $_POST['spol'] ) ? sanitize_text_field( $_POST['spol'] ) : '';
        $datum_dolaska_raw = isset( $_POST['datum_dolaska'] ) ? sanitize_text_field( $_POST['datum_dolaska'] ) : '';
        $datum_odlaska_raw = isset( $_POST['datum_odlaska'] ) ? sanitize_text_field( $_POST['datum_odlaska'] ) : '';
        $status_smjestaja = isset( $_POST['status_smjestaja'] ) ? sanitize_text_field( $_POST['status_smjestaja'] ) : '';
        $soba_id = isset( $_POST['soba_id'] ) ? absint( $_POST['soba_id'] ) : 0;
        $broj_kreveta = isset( $_POST['broj_kreveta'] ) ? sanitize_text_field( $_POST['broj_kreveta'] ) : '';
        $opce_biljeske = isset( $_POST['opce_biljeske'] ) ? sanitize_textarea_field( $_POST['opce_biljeske'] ) : '';
        $datum_zadnjeg_pregleda_raw = isset( $_POST['datum_zadnjeg_pregleda'] ) ? sanitize_text_field( $_POST['datum_zadnjeg_pregleda'] ) : '';
        $ime_lijecnika = isset( $_POST['ime_lijecnika'] ) ? sanitize_text_field( $_POST['ime_lijecnika'] ) : '';

        // Ključna validacija obaveznih polja
        $validation_errors = [];
        if ( empty( $lokacija_id ) ) { $validation_errors[] = __( 'Lokacija je obavezna.', 'evidencija' ); }
        if ( empty( $ime ) ) { $validation_errors[] = __( 'Ime je obavezno.', 'evidencija' ); }
        if ( empty( $prezime ) ) { $validation_errors[] = __( 'Prezime je obavezno.', 'evidencija' ); }
        if ( empty( $datum_rodjenja_raw ) ) { $validation_errors[] = __( 'Datum rođenja je obavezan.', 'evidencija' ); }
        if ( empty( $datum_dolaska_raw ) ) { $validation_errors[] = __( 'Datum dolaska je obavezan.', 'evidencija' ); }
        if ( empty( $status_smjestaja ) ) { $validation_errors[] = __( 'Status smještaja je obavezan.', 'evidencija' ); }
        
        // Validacija za sobu i krevet ako je status 'Trenutno smješten'
        if ( $status_smjestaja === 'Trenutno smješten' ) {
            if ( empty( $soba_id ) ) {
                 $validation_errors[] = __( 'Ako je status "Trenutno smješten", soba je obavezna.', 'evidencija' );
            } else {
                // Provjeri kapacitet sobe
                $room_data = Evidencija_Helpers::get_room_by_id($soba_id);
                if ($room_data) {
                    // Dohvati broj SVIH korisnika u ovoj sobi (uključujući onog koji se ažurira)
                    $total_occupied_in_room = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name_korisnici} WHERE soba_id = %d AND status_smjestaja = 'Trenutno smješten'", $soba_id ) );
                    
                    $final_occupied_count_for_validation = $total_occupied_in_room;

                    // Ako ažuriramo postojećeg korisnika
                    if ($user_id_before_save > 0) {
                        // Dohvati sobu u kojoj je korisnik bio PRIJE SPREMANJA (iz baze)
                        $user_original_room_id_db = $wpdb->get_var( $wpdb->prepare( "SELECT soba_id FROM {$table_name_korisnici} WHERE id = %d", $user_id_before_save ) );
                        
                        // Scenario 1: Korisnik ostaje u istoj sobi (ili se dodjeljuje sebi ako je soba_id bio NULL i sad se postavlja)
                        // U ovom slučaju, on ne zauzima "novo" mjesto, pa ga treba izuzeti iz brojača za validaciju
                        if ((int)$soba_id === (int)$user_original_room_id_db) {
                             $final_occupied_count_for_validation = $total_occupied_in_room - 1; // Smanji za 1 jer je trenutni korisnik već tu
                        }
                        // Scenario 2: Korisnik se prebacuje u DRUGU sobu
                        // U tom slučaju, on je "novi" zauzimač za TU sobu, pa se broji u $total_occupied_in_room
                        // $final_occupied_count_for_validation ostaje $total_occupied_in_room.
                    }

                    if ( $final_occupied_count_for_validation >= $room_data->kapacitet_sobe ) {
                        $validation_errors[] = sprintf(__( 'Soba "%s" je puna. Trenutna popunjenost: %d, Kapacitet: %d.', 'evidencija' ), esc_html($room_data->naziv_sobe), $final_occupied_count_for_validation, $room_data->kapacitet_sobe);
                    }
                } else {
                    $validation_errors[] = __( 'Odabrana soba ne postoji.', 'evidencija' );
                }
            }
        }


        // Konverzija datuma koristeći pomoćnu funkciju `parse_date_for_db`
        $datum_rodjenja_db = $this->parse_date_for_db($datum_rodjenja_raw);
        if (empty($datum_rodjenja_db) && !empty($datum_rodjenja_raw)) {
             $validation_errors[] = __( 'Datum rođenja nije u ispravnom formatu (DD-MM-YYYY, DD.MM.YYYY ili -MM-DD).', 'evidencija' );
        }

        $datum_dolaska_db = $this->parse_date_for_db($datum_dolaska_raw);
        if (empty($datum_dolaska_db) && !empty($datum_dolaska_raw)) {
             $validation_errors[] = __( 'Datum dolaska nije u ispravnom formatu (DD-MM-YYYY, DD.MM.YYYY ili -MM-DD).', 'evidencija' );
        }

        $datum_odlaska_db = $this->parse_date_for_db($datum_odlaska_raw); // Opcionalan datum
        if (empty($datum_odlaska_db) && !empty($datum_odlaska_raw)) {
             $validation_errors[] = __( 'Datum odlaska nije u ispravnom formatu (DD-MM-YYYY, DD.MM.YYYY ili -MM-DD).', 'evidencija' );
        }

        $datum_zadnjeg_pregleda_db = $this->parse_date_for_db($datum_zadnjeg_pregleda_raw); // Opcionalan datum
        if (empty($datum_zadnjeg_pregleda_db) && !empty($datum_zadnjeg_pregleda_raw)) {
             $validation_errors[] = __( 'Datum zadnjeg pregleda nije u ispravnom formatu (DD-MM-YYYY, DD.MM.YYYY ili -MM-DD).', 'evidencija' );
        }


        // Ako ima validacijskih grešaka, prikaži ih i preusmjeri nazad
        if ( ! empty( $validation_errors ) ) {
            $error_message = implode('<br>', $validation_errors); // Spoji sve greške u jednu poruku
            $redirect_url = admin_url( 'admin.php?page=evidencija-korisnici-add' );
            if ($user_id_before_save) {
                 $redirect_url = add_query_arg( 'id', $user_id_before_save, $redirect_url );
            }
            $redirect_url = add_query_arg( 'save_error', '1', $redirect_url ); // Signaliziraj da se forma popuni iz POST-a
            $this->redirect_with_message( $redirect_url, $error_message, 'error' );
        }

        $data_korisnik = array(
            'lokacija_id'            => $lokacija_id,
            'ime'                    => $ime,
            'prezime'                => $prezime,
            'datum_rodjenja'         => $datum_rodjenja_db,
            'spol'                   => $spol,
            'datum_dolaska'          => $datum_dolaska_db,
            'datum_odlaska'          => $datum_odlaska_db, // Null ako je prazno
            'status_smjestaja'       => $status_smjestaja,
            'soba_id'                => $soba_id > 0 ? $soba_id : null,
            'broj_kreveta'           => !empty($broj_kreveta) ? $broj_kreveta : null,
            'opce_biljeske'          => $opce_biljeske,
            'datum_zadnjeg_pregleda' => $datum_zadnjeg_pregleda_db, // Null ako je prazno
            'ime_lijecnika'          => $ime_lijecnika,
        );

        $format_korisnik = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );


        $success_message = '';
        $message_type = 'success';
        $user_id = $user_id_before_save; // Održavanje ID-a za redirect
        $redirect_to_user_page = admin_url( 'admin.php?page=evidencija-korisnici-add' );

        // Dohvati stare podatke korisnika za Audit Log ako je ažuriranje
        $old_user_data = null;
        if ($user_id_before_save) {
            $old_user_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name_korisnici} WHERE id = %d", $user_id_before_save ), ARRAY_A );
        }


        if ( $user_id_before_save ) { // Ažuriranje postojećeg korisnika
            $data_korisnik['zadnje_azurirao_korisnik_id'] = $current_user_id;
            $updated = $wpdb->update( $table_name_korisnici, $data_korisnik, array( 'id' => $user_id_before_save ), $format_korisnik, array( '%d' ) );
            if ( false !== $updated ) { // false za grešku, 0 za "nema promjena", >0 za uspjeh
                if ( $updated === 0 ) {
                    $success_message = __( 'Korisnik je ažuriran, ali nije bilo promjena podataka.', 'evidencija' );
                    $message_type = 'info';
                } else {
                    $success_message = __( 'Korisnik je uspješno ažuriran!', 'evidencija' );
                    $message_type = 'success';
                    // Audit Log: Ažuriran korisnik (glavni podaci)
                    $this->log_audit_action('updated_user', $user_id_before_save, $lokacija_id, array('old_data' => $old_user_data, 'new_data' => $data_korisnik, 'diff' => array_diff_assoc($data_korisnik, $old_user_data)));
                }
            } else {
                $success_message = __( 'Došlo je do pogreške prilikom ažuriranja korisnika.', 'evidencija' );
                $message_type = 'error';
            }
            $redirect_to_user_page = add_query_arg('id', $user_id_before_save, $redirect_to_user_page); // Ostani na istom kartonu
        } else { // Dodavanje novog korisnika
            $data_korisnik['kreirao_korisnik_id'] = $current_user_id;
            $inserted = $wpdb->insert( $table_name_korisnici, $data_korisnik, array_merge( $format_korisnik, ['%d'] ) );
            if ( $inserted ) { // $inserted je ID novog reda ako je uspješno
                $user_id = $wpdb->insert_id; // Dohvati ID novounesenog korisnika
                $success_message = __( 'Novi korisnik je uspješno dodan!', 'evidencija' );
                $message_type = 'success';
                $redirect_to_user_page = add_query_arg('id', $user_id, $redirect_to_user_page); // Preusmjeri na novi karton

                // Audit Log: Kreiran korisnik
                $this->log_audit_action('created_user', $user_id, $lokacija_id, $data_korisnik);
            } else {
                $success_message = __( 'Došlo je do pogreške prilikom dodavanja korisnika.', 'evidencija' );
                $message_type = 'error';
            }
        }

        // Dodatne poruke za podtablice
        $sub_messages = [];
        // Zbrajamo poruke iz podtablica samo ako je glavna operacija uspješna ili info
        if ( ($message_type == 'success' || $message_type == 'info') && $user_id ) {
            // Obrada kontakata obitelji
            $existing_contacts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_kontakti_obitelji WHERE korisnik_id = %d", $user_id ), ARRAY_A );
            $submitted_contact_ids = [];

            if ( ! empty( $_POST['kontakti_obitelji'] ) && is_array( $_POST['kontakti_obitelji'] ) ) {
                foreach ( $_POST['kontakti_obitelji'] as $kontakt_data ) {
                    $kontakt_id = isset( $kontakt_data['id'] ) ? absint( $kontakt_data['id'] ) : 0;
                    $ime_kontakt = isset( $kontakt_data['ime'] ) ? sanitize_text_field( $kontakt_data['ime'] ) : '';
                    $prezime_kontakt = isset( $kontakt_data['prezime'] ) ? sanitize_text_field( $kontakt_data['prezime'] ) : '';
                    $telefon_kontakt = isset( $kontakt_data['telefon'] ) ? sanitize_text_field( $kontakt_data['telefon'] ) : '';
                    $email_kontakt = isset( $kontakt_data['email'] ) ? sanitize_email( $kontakt_data['email'] ) : '';
                    $odnos_s_korisnikom_kontakt = isset( $kontakt_data['odnos_s_korisnikom'] ) ? sanitize_text_field( $kontakt_data['odnos_s_korisnikom'] ) : '';

                    if ( empty($ime_kontakt) || empty($prezime_kontakt) || empty($telefon_kontakt) || empty($odnos_s_korisnikom_kontakt) ) {
                        $sub_messages[] = __( 'Upozorenje: Neki kontakti obitelji su prazni i nisu spremljeni.', 'evidencija' );
                        if($message_type != 'error') $message_type = 'warning'; // Ako već nije error, postavi warning
                        continue;
                    }

                    $data_kontakt = array(
                        'korisnik_id'         => $user_id,
                        'ime'                 => $ime_kontakt,
                        'prezime'             => $prezime_kontakt,
                        'telefon'             => $telefon_kontakt,
                        'email'               => $email_kontakt,
                        'odnos_s_korisnikom'  => $odnos_s_korisnikom_kontakt,
                    );
                    $format_kontakt = array( '%d', '%s', '%s', '%s', '%s', '%s' );

                    // Pronađi stari kontakt za audit log
                    $old_kontakt_data = null;
                    foreach ($existing_contacts as $ek) {
                        if (isset($ek['id']) && $ek['id'] == $kontakt_id) {
                            $old_kontakt_data = $ek;
                            break;
                        }
                    }

                    if ( $kontakt_id && in_array( $kontakt_id, array_column($existing_contacts, 'id') ) ) { // Array_column for check
                        $updated_contact = $wpdb->update( $table_name_kontakti_obitelji, $data_kontakt, array( 'id' => $kontakt_id ), $format_kontakt, array( '%d' ) );
                        if ($updated_contact === false) { $sub_messages[] = __( 'Pogreška pri ažuriranju kontakta obitelji.', 'evidencija' ); $message_type = 'error'; } else if ($updated_contact > 0) {
                            $this->log_audit_action('updated_contact', $user_id, $lokacija_id, array('contact_id' => $kontakt_id, 'old_data' => $old_kontakt_data, 'new_data' => $data_kontakt, 'diff' => array_diff_assoc($data_kontakt, $old_kontakt_data)));
                        }
                        $submitted_contact_ids[] = $kontakt_id;
                    } else {
                        $data_kontakt['kreirao_korisnik_id'] = $current_user_id;
                        $inserted_contact = $wpdb->insert( $table_name_kontakti_obitelji, $data_kontakt, array_merge( $format_kontakt, ['%d'] ) );
                        if ($inserted_contact === false) { $sub_messages[] = __( 'Pogreška pri dodavanju novog kontakta obitelji.', 'evidencija' ); $message_type = 'error'; }
                        if ($wpdb->insert_id) {
                            $submitted_contact_ids[] = $wpdb->insert_id;
                            $this->log_audit_action('created_contact', $user_id, $lokacija_id, $data_kontakt);
                        }
                    }
                }
            }
            $existing_contact_ids_only = array_column($existing_contacts, 'id');
            $contacts_to_delete = array_diff( $existing_contact_ids_only, $submitted_contact_ids );
            if ( ! empty( $contacts_to_delete ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $contacts_to_delete ), '%d' ) );
                $deleted_contacts = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}evidencija_kontakti_obitelji WHERE id IN ($placeholders)", $contacts_to_delete ) );
                if ($deleted_contacts === false) { $sub_messages[] = __( 'Pogreška pri brisanju kontakta obitelji.', 'evidencija' ); $message_type = 'error'; } else if ($deleted_contacts > 0) {
                     foreach ($contacts_to_delete as $dc_id) {
                        // Dohvati podatke obrisanog kontakta za log
                        $deleted_c_data = null;
                        foreach($existing_contacts as $ec) { if (isset($ec['id']) && $ec['id'] == $dc_id) { $deleted_c_data = $ec; break; } }
                        $this->log_audit_action('deleted_contact', $user_id, $lokacija_id, array('contact_id' => $dc_id, 'deleted_data' => $deleted_c_data));
                     }
                }
            }

            // Obrada medicinskih podataka
            $existing_medical_data = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}evidencija_medicinski_podaci WHERE korisnik_id = %d", $user_id ), ARRAY_A );
            $submitted_medical_data_ids = [];

            if ( ! empty( $_POST['medicinski_podaci'] ) && is_array( $_POST['medicinski_podaci'] ) ) {
                foreach ( $_POST['medicinski_podaci'] as $medicinski_data ) {
                    $med_id = isset( $medicinski_data['id'] ) ? absint( $medicinski_data['id'] ) : 0;
                    $tip_podatka = isset( $medicinski_data['tip_podatka'] ) ? sanitize_text_field( $medicinski_data['tip_podatka'] ) : '';
                    $opis = isset( $medicinski_data['opis'] ) ? sanitize_textarea_field( $medicinski_data['opis'] ) : '';

                    if ( empty($opis) ) {
                        $sub_messages[] = __( 'Upozorenje: Neki medicinski podaci su prazni i nisu spremljeni.', 'evidencija' );
                        if($message_type != 'error') $message_type = 'warning';
                        continue;
                    }

                    $data_medicinski = array(
                        'korisnik_id' => $user_id,
                        'tip_podatka' => $tip_podatka,
                        'opis'        => $opis,
                    );
                    $format_medicinski = array( '%d', '%s', '%s' );

                    // Pronađi stari medicinski podatak za audit log
                    $old_med_data = null;
                    foreach ($existing_medical_data as $em) {
                        if (isset($em['id']) && $em['id'] == $med_id) {
                            $old_med_data = $em;
                            break;
                        }
                    }

                    if ( $med_id && in_array( $med_id, array_column($existing_medical_data, 'id') ) ) {
                        $updated_med = $wpdb->update( $table_name_medicinski_podaci, $data_medicinski, array( 'id' => $med_id ), $format_medicinski, array( '%d' ) );
                        if ($updated_med === false) { $sub_messages[] = __( 'Pogreška pri ažuriranju medicinskog podatka.', 'evidencija' ); $message_type = 'error'; } else if ($updated_med > 0) {
                            $this->log_audit_action('updated_medical_data', $user_id, $lokacija_id, array('med_id' => $med_id, 'old_data' => $old_med_data, 'new_data' => $data_medicinski, 'diff' => array_diff_assoc($data_medicinski, $old_med_data)));
                        }
                        $submitted_medical_data_ids[] = $med_id;
                    } else {
                        $data_medicinski['kreirao_korisnik_id'] = $current_user_id;
                        $inserted_med = $wpdb->insert( $table_name_medicinski_podaci, $data_medicinski, array_merge( $format_medicinski, ['%d'] ) );
                        if ($inserted_med === false) { $sub_messages[] = __( 'Pogreška pri dodavanju novog medicinskog podatka.', 'evidencija' ); $message_type = 'error'; }
                        if ($wpdb->insert_id) {
                            $submitted_medical_data_ids[] = $wpdb->insert_id;
                            $this->log_audit_action('created_medical_data', $user_id, $lokacija_id, $data_medicinski);
                        }
                    }
                }
            }
            $existing_medical_data_ids_only = array_column($existing_medical_data, 'id');
            $medical_data_to_delete = array_diff( $existing_medical_data_ids_only, $submitted_medical_data_ids );
            if ( ! empty( $medical_data_to_delete ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $medical_data_to_delete ), '%d' ) );
                $deleted_med = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}evidencija_medicinski_podaci WHERE id IN ($placeholders)", $medical_data_to_delete ) );
                if ($deleted_med === false) { $sub_messages[] = __( 'Pogreška pri brisanju medicinskog podatka.', 'evidencija' ); $message_type = 'error'; } else if ($deleted_med > 0) {
                    foreach ($medical_data_to_delete as $dm_id) {
                        $deleted_m_data = null;
                        foreach($existing_medical_data as $em) { if (isset($em['id']) && $em['id'] == $dm_id) { $deleted_m_data = $em; break; } }
                        $this->log_audit_action('deleted_medical_data', $user_id, $lokacija_id, array('med_id' => $dm_id, 'deleted_data' => $deleted_m_data));
                    }
                }
            }

            // Obrada uploadanih dokumenata
            if ( ! empty( $_FILES['novi_dokumenti']['name'][0] ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );

                $evidencija_docs_upload_dir = 'evidencija_docs';
                $upload_overrides = array( 'test_form' => false, 'unique_filename_callback' => null );

                add_filter( 'upload_dir', function( $dirs ) use ( $evidencija_docs_upload_dir ) {
                    $dirs['subdir'] = '/' . $evidencija_docs_upload_dir;
                    $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
                    $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
                    return $dirs;
                });

                foreach ( $_FILES['novi_dokumenti']['name'] as $key => $filename ) {
                    if ( isset($_FILES['novi_dokumenti']['error'][$key]) && $_FILES['novi_dokumenti']['error'][$key] == 0 ) {
                        $file_info = array(
                            'name'     => isset($_FILES['novi_dokumenti']['name'][$key]) ? $_FILES['novi_dokumenti']['name'][$key] : '',
                            'type'     => isset($_FILES['novi_dokumenti']['type'][$key]) ? $_FILES['novi_dokumenti']['type'][$key] : '',
                            'tmp_name' => isset($_FILES['novi_dokumenti']['tmp_name'][$key]) ? $_FILES['novi_dokumenti']['tmp_name'][$key] : '',
                            'error'    => isset($_FILES['novi_dokumenti']['error'][$key]) ? $_FILES['novi_dokumenti']['error'][$key] : UPLOAD_ERR_NO_FILE,
                            'size'     => isset($_FILES['novi_dokumenti']['size'][$key]) ? $_FILES['novi_dokumenti']['size'][$key] : 0,
                        );

                        $uploaded_file = wp_handle_upload( $file_info, $upload_overrides );

                        if ( isset( $uploaded_file['file'] ) ) {
                            $wpdb->insert(
                                $table_name_dokumenti,
                                array(
                                    'korisnik_id'       => $user_id,
                                    'naziv_dokumenta'   => sanitize_file_name( $filename ),
                                    'putanja_datoteke'  => $uploaded_file['file'],
                                    'tip_datoteke'      => $uploaded_file['type'],
                                    'velicina_datoteke' => $file_info['size'],
                                    'upload_korisnik_id' => $current_user_id,
                                ),
                                array( '%d', '%s', '%s', '%s', '%d', '%d' )
                            );
                            $this->log_audit_action('uploaded_document', $user_id, $lokacija_id, array('filename' => sanitize_file_name($filename), 'path' => $uploaded_file['file']));
                        } else {
                            $sub_messages[] = sprintf(__( 'Datoteka %s nije uspjela biti uploadana: %s', 'evidencija' ), esc_html( $filename ), esc_html( isset($uploaded_file['error']) ? $uploaded_file['error'] : 'Nepoznata pogreška.' ) );
                            if($message_type != 'error') $message_type = 'warning'; // Ako je glavna poruka success/info, ali upload faila, postavi na warning
                        }
                    }
                }
                remove_filter( 'upload_dir', function( $dirs ) use ( $evidencija_docs_upload_dir ) {
                    $dirs['subdir'] = '/' . $evidencija_docs_upload_dir;
                    $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
                    $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
                    return $dirs;
                });
            }

            // Obrada brisanja postojećih dokumenata
            $deleted_documents_ids_json = isset( $_POST['deleted_documents_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['deleted_documents_ids'] ) ) : '[]';
            $deleted_documents_ids = json_decode( $deleted_documents_ids_json );

            if ( is_array( $deleted_documents_ids ) && ! empty( $deleted_documents_ids ) ) {
                foreach ( $deleted_documents_ids as $doc_id ) {
                    $document_to_delete_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name_dokumenti} WHERE id = %d AND korisnik_id = %d", $doc_id, $user_id ), ARRAY_A );
                    if ( $document_to_delete_data && file_exists( $document_to_delete_data['putanja_datoteke'] ) ) {
                        unlink( $document_to_delete_data['putanja_datoteke'] );
                    }
                    $deleted_doc_db = $wpdb->delete( $table_name_dokumenti, array( 'id' => $doc_id, 'korisnik_id' => $user_id ), array( '%d', '%d' ) );
                    if ($deleted_doc_db === false) { $sub_messages[] = __( 'Pogreška pri brisanju dokumenta iz baze.', 'evidencija' ); $message_type = 'error'; } else if ($deleted_doc_db > 0) {
                        $this->log_audit_action('deleted_document', $user_id, $lokacija_id, array('document_id' => $doc_id, 'deleted_data' => $document_to_delete_data));
                    }
                }
            }
        }


        // Finaliziraj poruku i preusmjeri
        $final_message_text = $success_message;
        if (!empty($sub_messages)) {
            $final_message_text .= ' ' . implode(' ', $sub_messages); // Dodaj poruke iz podtablica
        }
        
        $this->redirect_with_message( $redirect_to_user_page, $final_message_text, $message_type );
    }

    /**
     * Funkcija za obradu brisanja korisnika.
     */
    public function handle_delete_user( $user_id ) {
        if ( ! current_user_can( 'delete_evidencija_users' ) ) {
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-korisnici' ), __( 'Nemate dozvolu za izvršavanje ove akcije.', 'evidencija' ), 'error' );
        }

        global $wpdb;

        // Dohvati podatke korisnika prije brisanja za Audit Log
        $deleted_user_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_korisnici WHERE id = %d", $user_id ), ARRAY_A );
        $deleted_user_location_id = isset($deleted_user_data['lokacija_id']) ? $deleted_user_data['lokacija_id'] : null;

        // Brisanje povezanih podataka i logiranje
        $contacts_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_kontakti_obitelji WHERE korisnik_id = %d", $user_id ), ARRAY_A );
        $deleted_contacts = $wpdb->delete( $wpdb->prefix . 'evidencija_kontakti_obitelji', array( 'korisnik_id' => $user_id ), array( '%d' ) );
        if ($deleted_contacts > 0) {
            $this->log_audit_action('deleted_contacts_for_user', $user_id, $deleted_user_location_id, array('count' => $deleted_contacts, 'details' => $contacts_data));
        }

        $medic_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_medicinski_podaci WHERE korisnik_id = %d", $user_id ), ARRAY_A );
        $deleted_medic = $wpdb->delete( $wpdb->prefix . 'evidencija_medicinski_podaci', array( 'korisnik_id' => $user_id ), array( '%d' ) );
        if ($deleted_medic > 0) {
            $this->log_audit_action('deleted_medical_data_for_user', $user_id, $deleted_user_location_id, array('count' => $deleted_medic, 'details' => $medic_data));
        }
        
        // Audit log za sam audit log se ne briše ovdje, ali se može bilježiti brisanje korisnika

        $documents = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_dokumenti WHERE korisnik_id = %d", $user_id ), ARRAY_A );
        if ( ! empty( $documents ) ) {
            foreach ( $documents as $doc ) {
                if ( file_exists( $doc['putanja_datoteke'] ) ) {
                    unlink( $doc['putanja_datoteke'] );
                }
            }
        }
        $documents_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}evidencija_dokumenti WHERE korisnik_id = %d", $user_id ), ARRAY_A );
        $deleted_docs_db = $wpdb->delete( $wpdb->prefix . 'evidencija_dokumenti', array( 'korisnik_id' => $user_id ), array( '%d' ) );
        if ($deleted_docs_db > 0) {
            $this->log_audit_action('deleted_documents_for_user', $user_id, $deleted_user_location_id, array('count' => $deleted_docs_db, 'details' => $documents_data));
        }

        $deleted_user_main = $wpdb->delete( $wpdb->prefix . 'evidencija_korisnici', array( 'id' => $user_id ), array( '%d' ) );

        if ( $deleted_user_main ) {
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-korisnici' ), __( 'Korisnik je uspješno obrisan!', 'evidencija' ), 'success' );
            // Audit Log: Izbrisan korisnik (glavni)
            $this->log_audit_action('deleted_user', $user_id, $deleted_user_location_id, $deleted_user_data);

        } else {
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-korisnici' ), __( 'Došlo je do pogreške prilikom brisanja korisnika.', 'evidencija' ), 'error' );
        }
    }


    /**
     * Funkcija za renderiranje stranice "Lokacije Domova".
     */
    public function render_locations_list_page() {
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/locations-list.php';
    }

    /**
     * Funkcija za renderiranje stranice "Dodaj Novu Lokaciju" / "Uredi Lokaciju".
     */
    public function render_location_add_edit_page() {
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/location-add-edit.php';
    }

    /**
     * Funkcija za renderiranje stranice "Svi Korisnici".
     */
    public function render_users_list_page() {
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/users-list.php';
    }

    /**
     * Funkcija za renderiranje stranice "Dodaj Novog Korisnika".
     */
    public function render_user_add_edit_page() {
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/user-add-edit.php';
    }

    /**
     * Funkcija za renderiranje stranice "Izvješća".
     */
    public function render_reports_page() {
        if ( ! current_user_can( 'view_evidencija_reports' ) ) {
            wp_die( __( 'Nemate dovoljno dozvola za pristup ovoj stranici.', 'evidencija' ) );
        }
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/reports.php';
    }

    /**
     * Funkcija za renderiranje stranice "Postavke".
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_evidencija_settings' ) ) {
            wp_die( __( 'Nemate dovoljno dozvola za pristup ovoj stranici.', 'evidencija' ) );
        }
        // Sada će stranica postavki koristiti svoju view datoteku
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Funkcija za renderiranje stranice "Audit Log".
     */
    public function render_audit_log_page() {
        if ( ! current_user_can( 'manage_evidencija_settings' ) ) {
            wp_die( __( 'Nemate dovoljno dozvola za pristup ovoj stranici.', 'evidencija' ) );
        }
        include EVIDENCIJA_PLUGIN_DIR . 'admin/views/audit-log.php';
    }

    /**
     * Registracija postavki plugina.
     */
    public function register_settings() {
        // Registriraj sekciju postavki
        add_settings_section(
            'evidencija_notifications_section', // ID sekcije
            __( 'Postavke Obavijesti', 'evidencija' ), // Naslov sekcije
            array( $this, 'evidencija_notifications_section_callback' ), // Callback funkcija
            'evidencija-postavke' // Slug stranice na kojoj će se prikazati
        );

        // Registriraj polje za E-mail adresu
        add_settings_field(
            'evidencija_notification_email', // ID polja
            __( 'E-mail za obavijesti', 'evidencija' ), // Naslov polja
            array( $this, 'evidencija_notification_email_callback' ), // Callback funkcija za render polja
            'evidencija-postavke', // Slug stranice
            'evidencija_notifications_section' // ID sekcije kojoj pripada
        );

        // Registriraj polje za dane ranije
        add_settings_field(
            'evidencija_notification_days_prior',
            __( 'Broj dana ranije za obavijest o dolasku', 'evidencija' ),
            array( $this, 'evidencija_notification_days_prior_callback' ),
            'evidencija-postavke',
            'evidencija_notifications_section'
        );

        // Registriraj postavku (option)
        register_setting(
            'evidencija_options_group', // Naziv grupe postavki
            'evidencija_notification_email', // Naziv opcije (option_name)
            array( 'sanitize_callback' => array($this, 'sanitize_multi_email') ) // <-- Prilagođena sanitizacija za više mailova
        );
        register_setting(
            'evidencija_options_group',
            'evidencija_notification_days_prior',
            array( 'sanitize_callback' => 'absint' ) // Funkcija za sanitizaciju (cijeli broj)
        );
    }

    // Callback funkcije za sekciju i polja postavki
    public function evidencija_notifications_section_callback() {
        echo '<p>' . esc_html__( 'Postavke za automatske e-mail obavijesti o dolascima pacijenata.', 'evidencija' ) . '</p>';
    }

    public function evidencija_notification_email_callback() {
        $email = get_option( 'evidencija_notification_email' );
        // Promijenjen input u textarea
        echo '<textarea name="evidencija_notification_email" id="evidencija_notification_email" rows="5" class="large-text code" placeholder="' . esc_attr__('Unesite e-mail adrese, odvojene zarezom ili novim redom', 'evidencija') . '">' . esc_textarea( $email ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'E-mail adrese na koje će se slati obavijesti o dolascima pacijenata. Unesite više adresa odvojenih zarezom (,) ili novim retkom.', 'evidencija' ) . '</p>';
    }

    public function evidencija_notification_days_prior_callback() {
        $days = get_option( 'evidencija_notification_days_prior', 2 ); // Default 2 dana
        echo '<input type="number" name="evidencija_notification_days_prior" value="' . esc_attr( $days ) . '" class="small-text" min="0">';
        echo '<p class="description">' . esc_html__( 'Broj dana prije datuma dolaska pacijenta kada će se poslati e-mail obavijest administratoru.', 'evidencija' ) . '</p>';
    }

    /**
     * Sanitizira višestruke e-mail adrese.
     *
     * @param string $emails Neobrađeni string e-mail adresa.
     * @return string Sanitizirane i filtrirane e-mail adrese, odvojene zarezom.
     */
    public function sanitize_multi_email($emails) {
        $emails = explode(',', str_replace(array("\n", "\r", "\r\n"), ',', $emails)); // Split by comma or new line
        $sanitized_emails = array();
        foreach ($emails as $email) {
            $email = trim($email);
            if (is_email($email)) { // Use WordPress's is_email validation
                $sanitized_emails[] = sanitize_email($email);
            }
        }
        return implode(',', $sanitized_emails); // Store as comma-separated string
    }

    /**
     * AJAX callback za dohvat soba po ID-u lokacije.
     */
    public function ajax_get_rooms_by_location() {
        // Ime actiona u check_ajax_referer mora se podudarati s action stringom u JS-u
        // i s nonce stringom iz wp_localize_script
        check_ajax_referer( 'evidencija_get_rooms_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_evidencija_users' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nemate dozvolu za pristup.', 'evidencija' ) ) );
        }

        $location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
        $rooms = Evidencija_Helpers::get_all_rooms( $location_id );

        $response_html = '';
        $has_rooms = false;
        if ( ! empty( $rooms ) ) {
            $has_rooms = true;
            foreach ( $rooms as $room ) {
                // Za AJAX response, šaljemo i kapacitet sobe da bi JS mogao prikazati "Trenutno popunjenost / Kapacitet"
                $occupied_spots = $this->get_occupied_spots_in_room( $room->id ); // Dohvati trenutnu popunjenost
                $room_capacity_text = sprintf( __( '%s (popunjeno: %d/%d)', 'evidencija' ), esc_html( $room->naziv_sobe ), $occupied_spots, $room->kapacitet_sobe );
                $response_html .= '<option value="' . esc_attr( $room->id ) . '" data-capacity="' . esc_attr( $room->kapacitet_sobe ) . '" data-occupied="' . esc_attr( $occupied_spots ) . '">' . $room_capacity_text . '</option>';
            }
        }

        wp_send_json_success(
            array(
                'html'      => $response_html,
                'has_rooms' => $has_rooms,
            )
        );
    }

    /**
     * Pomoćna funkcija za dohvat trenutne popunjenosti sobe.
     */
    public function get_occupied_spots_in_room($room_id) { // Promijenjeno u public da bude dostupno izvan klase ako treba
        global $wpdb;
        $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
        $occupied_spots = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name_korisnici} WHERE soba_id = %d AND status_smjestaja = 'Trenutno smješten'", $room_id ) );
        return absint($occupied_spots);
    }

    /**
     * AJAX callback za dohvat detalja sobe i korisnika u njoj.
     */
    public function ajax_get_room_details() {
        check_ajax_referer( 'evidencija_get_room_details_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_evidencija_users' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nemate dozvolu za pristup.', 'evidencija' ) ) );
        }

        $room_id = isset( $_POST['room_id'] ) ? absint( $_POST['room_id'] ) : 0;
        if ( ! $room_id ) {
            wp_send_json_error( array( 'message' => __( 'Neispravan ID sobe.', 'evidencija' ) ) );
        }

        global $wpdb;
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';
        $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';

        $room = $wpdb->get_row( $wpdb->prepare( "SELECT id, naziv_sobe, kapacitet_sobe FROM {$table_name_sobe} WHERE id = %d", $room_id ) );
        if ( ! $room ) {
            wp_send_json_error( array( 'message' => __( 'Soba ne postoji.', 'evidencija' ) ) );
        }

        $occupants_raw = $wpdb->get_results( $wpdb->prepare( "SELECT id, ime, prezime FROM {$table_name_korisnici} WHERE soba_id = %d AND status_smjestaja = 'Trenutno smješten'", $room_id ) );
        $occupants = array();
        foreach ( $occupants_raw as $o ) {
            $occupants[] = array(
                'id' => (int) $o->id,
                'name' => $o->ime . ' ' . $o->prezime,
            );
        }

        wp_send_json_success( array(
            'capacity'  => (int) $room->kapacitet_sobe,
            'occupied'  => count( $occupants ),
            'occupants' => $occupants,
            'room_name' => $room->naziv_sobe,
        ) );
    }

    /**
     * AJAX callback za export izvješća.
     * Generira CSV datoteku i šalje je pregledniku.
     */
    public function ajax_export_report() {
        check_ajax_referer( 'evidencija_export_report_nonce', 'nonce' );

        if ( ! current_user_can( 'view_evidencija_reports' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nemate dozvolu za export izvješća.', 'evidencija' ) ) );
        }

        global $wpdb;
        $report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( $_POST['report_type'] ) : '';
        $report_params = isset( $_POST['report_params'] ) ? json_decode( wp_unslash( $_POST['report_params'] ), true ) : array();

        $filename = '';
        $header_row = array();
        $data_rows = array();

        $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
        $table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';

        switch ( $report_type ) {
            case 'active_users':
                $filename = 'aktivni_korisnici.csv';
                $header_row = array( __('Lokacija', 'evidencija'), __('Broj aktivnih korisnika', 'evidencija') );
                $results = $wpdb->get_results("
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
                foreach ( $results as $row ) {
                    $data_rows[] = array( $row->naziv_lokacije, $row->active_users_count );
                }
                break;

            case 'occupancy':
                $filename = 'popunjenost_kapaciteta.csv';
                $header_row = array( __('Lokacija', 'evidencija'), __('Soba', 'evidencija'), __('Trenutna popunjenost', 'evidencija'), __('Kapacitet sobe', 'evidencija'), __('Slobodna mjesta', 'evidencija'), __('Postotak popunjenosti', 'evidencija') );
                $results = $wpdb->get_results("
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
                foreach ( $results as $row ) {
                    $free_spots = $row->kapacitet_sobe - $row->current_occupancy;
                    $occupancy_percentage = ($row->kapacitet_sobe > 0) ? round(($row->current_occupancy / $row->kapacitet_sobe) * 100, 2) : 0;
                    $data_rows[] = array( $row->naziv_lokacije, $row->naziv_sobe, $row->current_occupancy, $row->kapacitet_sobe, $free_spots, $occupancy_percentage . '%' );
                }
                break;

            case 'arrivals_departures':
                $filename = 'dolasci_odlasci.csv';
                $header_row = array( __('Ime i Prezime', 'evidencija'), __('Lokacija', 'evidencija'), __('Soba', 'evidencija'), __('Broj kreveta', 'evidencija'), __('Datum dolaska', 'evidencija'), __('Datum odlaska', 'evidencija'), __('Status', 'evidencija') );

                $selected_year = isset($report_params['report_year']) ? absint($report_params['report_year']) : date('Y');
                $selected_month = isset($report_params['report_month']) ? absint($report_params['report_month']) : 0;
                $selected_location_id = isset($report_params['report_location_id']) ? absint($report_params['report_location_id']) : 0;

                $where_clauses = [];
                $sql_params = [];

                $where_clauses[] = "(YEAR(k.datum_dolaska) = %d OR YEAR(k.datum_odlaska) = %d)";
                $sql_params[] = $selected_year;
                $sql_params[] = $selected_year;

                if ($selected_month > 0) {
                    $where_clauses[] = "(MONTH(k.datum_dolaska) = %d OR MONTH(k.datum_odlaska) = %d)";
                    $sql_params[] = $selected_month;
                    $sql_params[] = $selected_month;
                }
                if ($selected_location_id > 0) {
                    $where_clauses[] = "k.lokacija_id = %d";
                    $sql_params[] = $selected_location_id;
                }

                $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

                $results = $wpdb->get_results(
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
                foreach ( $results as $row ) {
                    $data_rows[] = array(
                        $row->ime . ' ' . $row->prezime,
                        $row->naziv_lokacije,
                        $row->naziv_sobe,
                        $row->broj_kreveta,
                        $row->datum_dolaska ? date_i18n('Y-m-d', strtotime($row->datum_dolaska)) : '',
                        $row->datum_odlaska ? date_i18n('Y-m-d', strtotime($row->datum_odlaska)) : '',
                        $row->status_smjestaja
                    );
                }
                break;

            case 'demographics':
                $filename = 'demografija.csv';
                $header_row = array( __('Spol', 'evidencija'), __('Broj korisnika', 'evidencija') ); // Opcionalno dodaj dobne kategorije
                
                $selected_location_id_demo = isset($report_params['report_location_id']) ? absint($report_params['report_location_id']) : 0;
                $where_clauses_demo = [];
                $sql_params_demo = [];

                if ($selected_location_id_demo > 0) {
                    $where_clauses_demo[] = "k.lokacija_id = %d";
                    $sql_params_demo[] = $selected_location_id_demo;
                }
                $where_sql_demo = !empty($where_clauses_demo) ? 'WHERE ' . implode(' AND ', $where_clauses_demo) : '';

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
                foreach ( $gender_stats as $stat ) {
                    $data_rows[] = array( $stat->spol ? $stat->spol : __('Nepoznato', 'evidencija'), $stat->count );
                }
                // Za dobne kategorije i prosjek, morat ćeš to malo modificirati da se eksportaju kao zasebni redovi ili u drugom formatu.
                // Trenutno eksportiramo samo spolnu strukturu.
                break;

            case 'medical_stats':
                $filename = 'medicinska_statistika.csv';
                $header_row = array( __('Tip podatka', 'evidencija'), __('Opis', 'evidencija'), __('Broj pojavljivanja', 'evidencija') );
                
                $selected_location_id_med = isset($report_params['report_location_id']) ? absint($report_params['report_location_id']) : 0;
                $where_clauses_med = [];
                $sql_params_med = [];

                if ($selected_location_id_med > 0) {
                    $where_clauses_med[] = "k.lokacija_id = %d";
                    $sql_params_med[] = $selected_location_id_med;
                }
                $where_sql_med = !empty($where_clauses_med) ? 'WHERE ' . implode(' AND ', $where_clauses_med) : '';

                $results = $wpdb->get_results(
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
                foreach ( $results as $row ) {
                    $data_rows[] = array( ucfirst($row->tip_podatka), $row->opis, $row->count );
                }
                break;

            default:
                wp_send_json_error( array( 'message' => __( 'Nepoznat tip izvješća.', 'evidencija' ) ) );
                break;
        }

        if ( empty( $data_rows ) ) {
            // Umjesto wp_send_json_error, preusmjeri s porukom
            $this->redirect_with_message( admin_url( 'admin.php?page=evidencija-izvjesca&report_type=' . $report_type ), __( 'Nema podataka za export s odabranim kriterijima.', 'evidencija' ), 'info' );
        }

        // Generiraj CSV
        // Postavi zaglavlja za preuzimanje datoteke
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );

        $output = fopen( 'php://output', 'w' );
        fputs( $output, $bom = ( chr(0xEF) . chr(0xBB) . chr(0xBF) ) ); // Dodaj UTF-8 BOM za ispravan prikaz u Excelu

        fputcsv( $output, $header_row );
        foreach ( $data_rows as $row ) {
            fputcsv( $output, $row );
        }
        fclose( $output );

        wp_die(); // Obavezno zaustavi izvršavanje nakon slanja datoteke
    }
}