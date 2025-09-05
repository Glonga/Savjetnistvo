<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Evidencija_Notifications {

    /**
     * Šalje e-mail obavijesti za očekivane dolaske pacijenata.
     * Ova funkcija se poziva putem WP-Cron joba.
     */
    public static function send_arrival_notifications() {
        global $wpdb;
        $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
        $table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';

        // Dohvati postavke obavijesti
        $notification_emails_raw = get_option( 'evidencija_notification_email' ); // Dohvati string e-mailova
        $days_prior = get_option( 'evidencija_notification_days_prior', 2 ); // Default 2 dana

        // Razdvoji e-mail adrese
        // is_email() se ne može koristiti ovdje za validaciju, jer je to za jednu adresu
        // A ovdje nam treba razdvajanje i trimanje stringa
        $notification_emails = array_filter( array_map( 'trim', explode( ',', $notification_emails_raw ) ) );

        // Ako nije postavljena e-mail adresa ili su dani 0, ne šalji obavijesti
        if ( empty( $notification_emails ) || $days_prior <= 0 ) {
            error_log('Evidencija_Notifications: Postavke obavijesti nisu konfigurirane (email adrese ili dani ranije). Obavijesti nisu poslane.');
            return;
        }

        // Izračunaj datum za provjeru (npr. "danas + 2 dana")
        $notification_date = date( 'Y-m-d', strtotime( "+{$days_prior} days", current_time('timestamp', 1) ) ); // current_time('timestamp', 1) za UTC

        // Dohvati korisnike čiji je datum dolaska na notification_date
        $users_for_notification = $wpdb->get_results( $wpdb->prepare( "
            SELECT
                k.ime,
                k.prezime,
                k.datum_dolaska,
                k.soba_id,
                k.broj_kreveta,
                l.naziv_lokacije,
                s.naziv_sobe
            FROM
                {$table_name_korisnici} k
            LEFT JOIN
                {$table_name_lokacije} l ON k.lokacija_id = l.id
            LEFT JOIN
                {$wpdb->prefix}evidencija_sobe s ON k.soba_id = s.id
            WHERE
                k.status_smjestaja = 'Očekuje dolazak' AND k.datum_dolaska = %s
        ", $notification_date ) );

        if ( empty( $users_for_notification ) ) {
            error_log('Evidencija_Notifications: Nema korisnika za obavijest na datum ' . $notification_date);
            return;
        }

        $subject = __( 'Obavijest: Očekivani dolasci pacijenata u Dom Evidencija', 'evidencija' );
        $body = __( 'Poštovani administratore,', 'evidencija' ) . "\n\n";
        $body .= __( 'Imate očekivane dolaske pacijenata:', 'evidencija' ) . "\n\n";

        foreach ( $users_for_notification as $user ) {
            $body .= sprintf(
                __( 'Ime i Prezime: %s %s', 'evidencija' ) . "\n",
                esc_html( $user->ime ),
                esc_html( $user->prezime )
            );
            $body .= sprintf(
                __( 'Lokacija: %s', 'evidencija' ) . "\n",
                esc_html( $user->naziv_lokacije ? $user->naziv_lokacije : 'N/A' )
            );
            $body .= sprintf(
                __( 'Soba: %s', 'evidencija' ) . "\n",
                esc_html( $user->naziv_sobe ? $user->naziv_sobe : 'N/A' )
            );
             if ( ! empty( $user->broj_kreveta ) ) {
                $body .= sprintf(
                    __( 'Broj kreveta: %s', 'evidencija' ) . "\n",
                    esc_html( $user->broj_kreveta )
                );
            }
            $body .= sprintf(
                __( 'Datum dolaska: %s', 'evidencija' ) . "\n",
                esc_html( date_i18n( get_option('date_format'), strtotime($user->datum_dolaska) ) )
            );
            $body .= "--------------------\n";
        }

        $body .= "\n" . __( 'Molimo provjerite sustav Evidencije za više detalja.', 'evidencija' ) . "\n";
        $body .= __( 'S poštovanjem,', 'evidencija' ) . "\n";
        $body .= __( 'Sustav Evidencije', 'evidencija' ) . "\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

        // Šalji e-mail na SVE adrese
        $mail_sent = wp_mail( $notification_emails, $subject, $body, $headers ); // <-- KLJUČNO: Prvi argument je array

        if ( $mail_sent ) {
            error_log('Evidencija_Notifications: Uspješno poslan e-mail obavijesti na ' . implode(', ', $notification_emails) . ' za ' . count($users_for_notification) . ' korisnika.');
        } else {
            error_log('Evidencija_Notifications: NEUSPJELO slanje e-mail obavijesti na ' . implode(', ', $notification_emails) . '.');
        }
    }
}