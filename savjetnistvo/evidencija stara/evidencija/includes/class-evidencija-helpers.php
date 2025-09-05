<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Evidencija_Helpers {

    /**
     * Dohvaća sve lokacije domova iz baze podataka.
     *
     * @return array Popis lokacija, svaki element je objekt (sa svim stupcima).
     */
    public static function get_all_locations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evidencija_lokacije';
        // UKLONJENO: kapacitet iz selecta jer je premješten u sobe
        $locations = $wpdb->get_results( "SELECT id, naziv_lokacije, adresa_lokacije, kontakt_telefon, kontakt_email FROM $table_name ORDER BY naziv_lokacije ASC" );
        return $locations;
    }

    /**
     * Dohvaća detalje određene lokacije po ID-u.
     *
     * @param int $location_id ID lokacije.
     * @return object|null Detalji lokacije ili null ako ne postoji.
     */
    public static function get_location_by_id( $location_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evidencija_lokacije';
        $location = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $location_id ) );
        return $location;
    }

    /**
     * Dohvaća sve sobe iz baze podataka (opcionalno filtrirano po lokaciji). <-- NOVO
     *
     * @param int|null $location_id ID lokacije za filtriranje soba.
     * @return array Popis soba.
     */
    public static function get_all_rooms($location_id = null) {
        global $wpdb;
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';
        $table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';

        $sql = "SELECT s.*, l.naziv_lokacije FROM {$table_name_sobe} s LEFT JOIN {$table_name_lokacije} l ON s.lokacija_id = l.id";
        $sql_params = [];

        if (!empty($location_id)) {
            $sql .= " WHERE s.lokacija_id = %d";
            $sql_params[] = $location_id;
        }

        $sql .= " ORDER BY l.naziv_lokacije ASC, s.naziv_sobe ASC";

        if (!empty($sql_params)) {
            $rooms = $wpdb->get_results($wpdb->prepare($sql, ...$sql_params));
        } else {
            $rooms = $wpdb->get_results($sql);
        }
        return $rooms;
    }

    /**
     * Dohvaća detalje određene sobe po ID-u. <-- NOVO
     *
     * @param int $room_id ID sobe.
     * @return object|null Detalji sobe ili null ako ne postoji.
     */
    public static function get_room_by_id($room_id) {
        global $wpdb;
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_sobe WHERE id = %d", $room_id));
        return $room;
    }

    /**
     * Pomoćna funkcija za formatiranje detalja audit loga u čitljiviji tekst.
     *
     * @param string $action Tip akcije (npr. 'updated_user', 'created_location').
     * @param string $json_details JSON string s detaljima promjene.
     * @return string Formatirani tekstualni prikaz detalja.
     */
    public static function format_audit_details($action, $json_details) {
        $details = json_decode($json_details, true);
        if (!is_array($details)) {
            return esc_html($json_details); // Vrati sirovi JSON ako se ne može dekodirati
        }

        $formatted_details = [];

        switch ($action) {
            case 'created_user':
                $formatted_details[] = __('Novi korisnik kreiran.', 'evidencija');
                if (isset($details['ime']) && isset($details['prezime'])) {
                    $formatted_details[] = sprintf(__('Ime: %s %s', 'evidencija'), esc_html($details['ime']), esc_html($details['prezime']));
                }
                break;
            case 'updated_user':
                $formatted_details[] = __('Korisnički podaci ažurirani.', 'evidencija');
                if (isset($details['diff']) && is_array($details['diff'])) {
                    foreach ($details['diff'] as $field => $new_value) {
                        $old_value = isset($details['old_data'][$field]) ? $details['old_data'][$field] : __('(prethodno prazno)', 'evidencija');
                        // Posebno rukovanje datumima i soba_id/broj_kreveta
                        if (strpos($field, 'datum_') === 0 && !empty($old_value)) {
                            $old_value_display = !empty($old_value) ? date_i18n(get_option('date_format'), strtotime($old_value)) : '';
                            $new_value_display = !empty($new_value) ? date_i18n(get_option('date_format'), strtotime($new_value)) : '';
                            $formatted_details[] = sprintf(__('Polje "%s" promijenjeno iz "%s" u "%s".', 'evidencija'), esc_html(str_replace('_', ' ', $field)), esc_html($old_value_display), esc_html($new_value_display));
                        } elseif ($field === 'soba_id') {
                             $old_room_name = !empty($old_value) ? self::get_room_name_by_id($old_value) : __('(nema sobe)', 'evidencija');
                             $new_room_name = !empty($new_value) ? self::get_room_name_by_id($new_value) : __('(nema sobe)', 'evidencija');
                             $formatted_details[] = sprintf(__('Soba promijenjena iz "%s" u "%s".', 'evidencija'), esc_html($old_room_name), esc_html($new_room_name));
                        }
                        else {
                            $formatted_details[] = sprintf(__('Polje "%s" promijenjeno iz "%s" u "%s".', 'evidencija'), esc_html(str_replace('_', ' ', $field)), esc_html($old_value), esc_html($new_value));
                        }
                    }
                }
                break;
            case 'deleted_user':
                $formatted_details[] = __('Korisnik obrisan.', 'evidencija');
                if (isset($details['ime']) && isset($details['prezime'])) {
                    $formatted_details[] = sprintf(__('Ime: %s %s', 'evidencija'), esc_html($details['ime']), esc_html($details['prezime']));
                }
                break;
            case 'created_location':
                $formatted_details[] = __('Nova lokacija kreirana.', 'evidencija');
                if (isset($details['naziv_lokacije'])) {
                    $formatted_details[] = sprintf(__('Naziv: %s', 'evidencija'), esc_html($details['naziv_lokacije']));
                }
                break;
            case 'updated_location':
                $formatted_details[] = __('Podaci lokacije ažurirani.', 'evidencija');
                if (isset($details['diff']) && is_array($details['diff'])) {
                    foreach ($details['diff'] as $field => $new_value) {
                        $old_value = isset($details['old_data'][$field]) ? $details['old_data'][$field] : __('(prethodno prazno)', 'evidencija');
                        $formatted_details[] = sprintf(__('Polje "%s" promijenjeno iz "%s" u "%s".', 'evidencija'), esc_html(str_replace('_', ' ', $field)), esc_html($old_value), esc_html($new_value));
                    }
                }
                break;
            case 'deleted_location':
                $formatted_details[] = __('Lokacija obrisana.', 'evidencija');
                if (isset($details['naziv_lokacije'])) {
                    $formatted_details[] = sprintf(__('Naziv: %s', 'evidencija'), esc_html($details['naziv_lokacije']));
                }
                break;
            case 'created_room': // <-- NOVO
                $formatted_details[] = __('Nova soba kreirana.', 'evidencija');
                if (isset($details['naziv_sobe'])) {
                    $formatted_details[] = sprintf(__('Soba: %s (Kapacitet: %d)', 'evidencija'), esc_html($details['naziv_sobe']), esc_html($details['kapacitet_sobe']));
                }
                break;
            case 'updated_room': // <-- NOVO
                $formatted_details[] = __('Podaci sobe ažurirani.', 'evidencija');
                if (isset($details['diff']) && is_array($details['diff'])) {
                    foreach ($details['diff'] as $field => $new_value) {
                        $old_value = isset($details['old_data'][$field]) ? $details['old_data'][$field] : __('(prethodno prazno)', 'evidencija');
                        $formatted_details[] = sprintf(__('Polje "%s" promijenjeno iz "%s" u "%s".', 'evidencija'), esc_html(str_replace('_', ' ', $field)), esc_html($old_value), esc_html($new_value));
                    }
                }
                break;
            case 'deleted_room': // <-- NOVO
                $formatted_details[] = __('Soba obrisana.', 'evidencija');
                if (isset($details['deleted_data']['naziv_sobe'])) {
                    $formatted_details[] = sprintf(__('Soba: %s', 'evidencija'), esc_html($details['deleted_data']['naziv_sobe']));
                }
                break;
            case 'created_contact':
                $formatted_details[] = __('Novi kontakt obitelji dodan.', 'evidencija');
                if (isset($details['ime']) && isset($details['prezime'])) {
                    $formatted_details[] = sprintf(__('Kontakt: %s %s (%s)', 'evidencija'), esc_html($details['ime']), esc_html($details['prezime']), esc_html($details['odnos_s_korisnikom']));
                }
                break;
            case 'updated_contact':
                 $formatted_details[] = __('Kontakt obitelji ažuriran.', 'evidencija');
                 if (isset($details['diff']) && is_array($details['diff'])) {
                    foreach ($details['diff'] as $field => $new_value) {
                        $old_value = isset($details['old_data'][$field]) ? $details['old_data'][$field] : __('(prethodno prazno)', 'evidencija');
                        $formatted_details[] = sprintf(__('Polje "%s" promijenjeno iz "%s" u "%s".', 'evidencija'), esc_html(str_replace('_', ' ', $field)), esc_html($old_value), esc_html($new_value));
                    }
                }
                break;
            case 'deleted_contact':
                $formatted_details[] = __('Kontakt obitelji obrisan.', 'evidencija');
                if (isset($details['deleted_data']['ime']) && isset($details['deleted_data']['prezime'])) {
                    $formatted_details[] = sprintf(__('Kontakt: %s %s', 'evidencija'), esc_html($details['deleted_data']['ime']), esc_html($details['deleted_data']['prezime']));
                }
                break;
            case 'created_medical_data':
                $formatted_details[] = __('Novi medicinski podatak dodan.', 'evidencija');
                if (isset($details['tip_podatka'])) {
                    $formatted_details[] = sprintf(__('Tip: %s, Opis: %s', 'evidencija'), esc_html($details['tip_podatka']), esc_html($details['opis']));
                }
                break;
            case 'updated_medical_data':
                $formatted_details[] = __('Medicinski podaci ažurirani.', 'evidencija');
                 if (isset($details['diff']) && is_array($details['diff'])) {
                    foreach ($details['diff'] as $field => $new_value) {
                        $old_value = isset($details['old_data'][$field]) ? $details['old_data'][$field] : __('(prethodno prazno)', 'evidencija');
                        $formatted_details[] = sprintf(__('Polje "%s" promijenjeno iz "%s" u "%s".', 'evidencija'), esc_html(str_replace('_', ' ', $field)), esc_html($old_value), esc_html($new_value));
                    }
                }
                break;
            case 'deleted_medical_data':
                $formatted_details[] = __('Medicinski podatak obrisan.', 'evidencija');
                if (isset($details['deleted_data']['opis'])) {
                    $formatted_details[] = sprintf(__('Opis: %s', 'evidencija'), esc_html($details['deleted_data']['opis']));
                }
                break;
            case 'uploaded_document':
                $formatted_details[] = __('Dokument uploadan.', 'evidencija');
                if (isset($details['filename'])) {
                    $formatted_details[] = sprintf(__('Datoteka: %s', 'evidencija'), esc_html($details['filename']));
                }
                break;
            case 'deleted_document':
                $formatted_details[] = __('Dokument obrisan.', 'evidencija');
                if (isset($details['deleted_data']['naziv_dokumenta'])) {
                    $formatted_details[] = sprintf(__('Datoteka: %s', 'evidencija'), esc_html($details['deleted_data']['naziv_dokumenta']));
                }
                break;
            case 'deleted_contacts_for_user':
                $formatted_details[] = sprintf(__('Svi kontakti obitelji obrisani za korisnika (ukupno %d).', 'evidencija'), isset($details['count']) ? $details['count'] : 0);
                break;
            case 'deleted_medical_data_for_user':
                $formatted_details[] = sprintf(__('Svi medicinski podaci obrisani za korisnika (ukupno %d).', 'evidencija'), isset($details['count']) ? $details['count'] : 0);
                break;
            case 'deleted_documents_for_user':
                $formatted_details[] = sprintf(__('Svi dokumenti obrisani za korisnika (ukupno %d).', 'evidencija'), isset($details['count']) ? $details['count'] : 0);
                break;
            default:
                $formatted_details[] = __('Nepoznata akcija.', 'evidencija');
                $formatted_details[] = esc_html(json_encode($details)); // Fallback na sirovi JSON
                break;
        }

        return implode('<br>', $formatted_details);
    }

    /**
     * Pomoćna funkcija za dohvat naziva sobe po ID-u sobe. <-- NOVO
     * Koristi se za formatiranje Audit Loga.
     */
    public static function get_room_name_by_id($room_id) {
        global $wpdb;
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';
        $room_name = $wpdb->get_var($wpdb->prepare("SELECT naziv_sobe FROM {$table_name_sobe} WHERE id = %d", $room_id));
        return $room_name ? $room_name : __('Nepoznata soba', 'evidencija');
    }
}