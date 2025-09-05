<?php
// Spriječite direktan pristup datoteci
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Evidencija_Database {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Tablica za Lokacije domova
        $table_name_lokacije = $wpdb->prefix . 'evidencija_lokacije';
        $sql_lokacije = "CREATE TABLE $table_name_lokacije (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            naziv_lokacije varchar(255) NOT NULL,
            adresa_lokacije varchar(255) NULL,
            kontakt_telefon varchar(50) NULL,
            kontakt_email varchar(100) NULL,
            datum_kreiranja datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            kreirao_korisnik_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_lokacije );

        // 2. Tablica za Sobe
        $table_name_sobe = $wpdb->prefix . 'evidencija_sobe';
        $sql_sobe = "CREATE TABLE $table_name_sobe (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lokacija_id bigint(20) unsigned NOT NULL,
            naziv_sobe varchar(100) NOT NULL,
            kapacitet_sobe int(11) DEFAULT 1 NOT NULL,
            datum_kreiranja datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            kreirao_korisnik_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            KEY lokacija_id (lokacija_id)
        ) $charset_collate;";
        dbDelta( $sql_sobe );

        // 3. Tablica za Korisnike
        $table_name_korisnici = $wpdb->prefix . 'evidencija_korisnici';
        $sql_korisnici = "CREATE TABLE $table_name_korisnici (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lokacija_id bigint(20) unsigned NOT NULL,
            ime varchar(100) NOT NULL,
            prezime varchar(100) NOT NULL,
            datum_rodjenja date NOT NULL,
            spol varchar(20) NULL,
            datum_dolaska date NOT NULL,
            datum_odlaska date NULL,
            status_smjestaja varchar(50) NOT NULL,
            soba_id bigint(20) unsigned NULL,
            broj_kreveta varchar(50) NULL,
            opce_biljeske text NULL,
            datum_zadnjeg_pregleda date NULL,
            ime_lijecnika varchar(100) NULL,
            datum_kreiranja datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            datum_azuriranja datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            kreirao_korisnik_id bigint(20) unsigned NULL,
            zadnje_azurirao_korisnik_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            KEY lokacija_id (lokacija_id),
            KEY soba_id (soba_id)
        ) $charset_collate;";
        dbDelta( $sql_korisnici );


        // 4. Tablica za Kontakt podatke obitelji/staratelja
        $table_name_kontakti_obitelji = $wpdb->prefix . 'evidencija_kontakti_obitelji';
        $sql_kontakti_obitelji = "CREATE TABLE $table_name_kontakti_obitelji (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            korisnik_id bigint(20) unsigned NOT NULL,
            ime varchar(100) NOT NULL,
            prezime varchar(100) NOT NULL,
            telefon varchar(50) NULL,
            email varchar(100) NULL,
            odnos_s_korisnikom varchar(100) NOT NULL,
            datum_kreiranja datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            kreirao_korisnik_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            KEY korisnik_id (korisnik_id)
        ) $charset_collate;";
        dbDelta( $sql_kontakti_obitelji );

        // 5. Tablica za Medicinske podatke
        $table_name_medicinski_podaci = $wpdb->prefix . 'evidencija_medicinski_podaci';
        $sql_medicinski_podaci = "CREATE TABLE $table_name_medicinski_podaci (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            korisnik_id bigint(20) unsigned NOT NULL,
            tip_podatka varchar(50) NOT NULL,
            opis text NOT NULL,
            datum_kreiranja datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            kreirao_korisnik_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            KEY korisnik_id (korisnik_id)
        ) $charset_collate;";
        dbDelta( $sql_medicinski_podaci );

        // 6. Tablica za Priložene dokumente
        $table_name_dokumenti = $wpdb->prefix . 'evidencija_dokumenti';
        $sql_dokumenti = "CREATE TABLE $table_name_dokumenti (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            korisnik_id bigint(20) unsigned NOT NULL,
            naziv_dokumenta varchar(255) NOT NULL,
            putanja_datoteke varchar(255) NOT NULL,
            tip_datoteke varchar(50) NULL,
            velicina_datoteke bigint(20) NULL,
            datum_uploada datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            upload_korisnik_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            KEY korisnik_id (korisnik_id)
        ) $charset_collate;";
        dbDelta( $sql_dokumenti );

        // 7. Tablica za Povijest promjena (Audit Log)
        $table_name_audit_log = $wpdb->prefix . 'evidencija_audit_log';
        $sql_audit_log = "CREATE TABLE $table_name_audit_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            korisnik_id bigint(20) unsigned NULL,
            lokacija_id bigint(20) unsigned NULL,
            datum_vrijeme datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            korisnik_id_akcije bigint(20) unsigned NOT NULL,
            akcija varchar(255) NOT NULL,
            detalji_promjene text NULL,
            PRIMARY KEY  (id),
            KEY korisnik_id (korisnik_id),
            KEY lokacija_id (lokacija_id)
        ) $charset_collate;";
        dbDelta( $sql_audit_log );
    }

    public static function drop_tables() {
        global $wpdb;

        $table_names = [
            $wpdb->prefix . 'evidencija_lokacije',
            $wpdb->prefix . 'evidencija_sobe',
            $wpdb->prefix . 'evidencija_korisnici',
            $wpdb->prefix . 'evidencija_kontakti_obitelji',
            $wpdb->prefix . 'evidencija_medicinski_podaci',
            $wpdb->prefix . 'evidencija_dokumenti',
            $wpdb->prefix . 'evidencija_audit_log',
        ];

        foreach ( $table_names as $table_name ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        }
    }
}