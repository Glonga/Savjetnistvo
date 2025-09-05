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

// Definiranje konstante za putanju do glavnog direktorija plugina
if ( ! defined( 'EVIDENCIJA_PLUGIN_DIR' ) ) {
    define( 'EVIDENCIJA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Definiranje konstante za URL do glavnog direktorija plugina
if ( ! defined( 'EVIDENCIJA_PLUGIN_URL' ) ) {
    define( 'EVIDENCIJA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Uključivanje klase za bazu podataka
require_once EVIDENCIJA_PLUGIN_DIR . 'includes/class-evidencija-database.php';
// Uključivanje klase za pomoćne funkcije
require_once EVIDENCIJA_PLUGIN_DIR . 'includes/class-evidencija-helpers.php';
// Uključivanje klase za administracijske stranice
require_once EVIDENCIJA_PLUGIN_DIR . 'includes/class-evidencija-admin-pages.php';
// Uključivanje klase za obavijesti <-- NOVO
require_once EVIDENCIJA_PLUGIN_DIR . 'includes/class-evidencija-notifications.php';


/**
 * Funkcija za registraciju prilagođenih korisničkih uloga i capabilities.
 * Pokreće se samo pri aktivaciji plugina.
 */
function evidencija_register_custom_roles() {
    // Uloga: Administracija Doma
    add_role(
        'dom_administracija',
        __( 'Administracija Doma', 'evidencija' ),
        array(
            'read'                          => true,
            'edit_posts'                    => true, // Mogu uređivati objave ako je potrebno
            'upload_files'                  => true,

            'manage_evidencija_locations'   => true,
            'manage_evidencija_users'       => true,
            'delete_evidencija_users'       => false, // Ne može brisati korisnike
            'delete_evidencija_locations'   => false, // Ne može brisati lokacije
            'view_evidencija_reports'       => true,
            'manage_evidencija_settings'    => false, // Ne može mijenjati postavke plugina
        )
    );

    // Uloga: Medicinsko Osoblje
    add_role(
        'dom_medicinsko_osoblje',
        __( 'Medicinsko Osoblje', 'evidencija' ),
        array(
            'read'                          => true,
            'edit_posts'                    => false,
            'upload_files'                  => true,

            'manage_evidencija_locations'   => false, // Ne može upravljati lokacijama
            'manage_evidencija_users'       => true,  // Može upravljati korisnicima
            'delete_evidencija_users'       => false,
            'delete_evidencija_locations'   => false,
            'view_evidencija_reports'       => true,
            'manage_evidencija_settings'    => false,
            // Detaljnije dozvole za medicinske podatke mogu se rješavati u code-u, ne u capabilities.
        )
    );

    // Dodajte custom capabilities Administratoru
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $admin_role->add_cap( 'manage_evidencija_locations' );
        $admin_role->add_cap( 'manage_evidencija_users' );
        $admin_role->add_cap( 'delete_evidencija_users' );
        $admin_role->add_cap( 'delete_evidencija_locations' );
        $admin_role->add_cap( 'view_evidencija_reports' );
        $admin_role->add_cap( 'manage_evidencija_settings' );
    }
}

/**
 * Funkcija za uklanjanje prilagođenih korisničkih uloga prilikom deinstalacije.
 */
function evidencija_unregister_custom_roles() {
    remove_role( 'dom_administracija' );
    remove_role( 'dom_medicinsko_osoblje' );

    // Uklonite custom capabilities s Administratora prilikom deinstalacije
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $admin_role->remove_cap( 'manage_evidencija_locations' );
        $admin_role->remove_cap( 'manage_evidencija_users' );
        $admin_role->remove_cap( 'delete_evidencija_users' );
        $admin_role->remove_cap( 'delete_evidencija_locations' );
        $admin_role->remove_cap( 'view_evidencija_reports' );
        $admin_role->remove_cap( 'manage_evidencija_settings' );
    }
}


/**
 * Funkcija koja se poziva prilikom aktivacije plugina.
 * Kreira potrebne tablice u bazi podataka i registrira uloge.
 */
function evidencija_activate() {
    Evidencija_Database::create_tables();
    evidencija_register_custom_roles(); // Registriraj uloge pri aktivaciji

    // Zakazivanje cron joba za obavijesti prilikom aktivacije plugina
    if ( ! wp_next_scheduled( 'evidencija_daily_notifications' ) ) {
        wp_schedule_event( time(), 'daily', 'evidencija_daily_notifications' ); // Pokreće se jednom dnevno
    }
}
register_activation_hook( __FILE__, 'evidencija_activate' );

/**
 * Funkcija koja se poziva prilikom deinstalacije plugina.
 * Uklanja sve tablice i podatke plugina iz baze podataka i deregistrira uloge.
 * BUDITE OPREZNI: Ovo trajno briše sve podatke.
 */
function evidencija_uninstall() {
    Evidencija_Database::drop_tables();
    evidencija_unregister_custom_roles(); // Ukloni uloge pri deinstalaciji

    // Očisti zakazani cron job prilikom deinstalacije
    $timestamp = wp_next_scheduled( 'evidencija_daily_notifications' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'daily', 'evidencija_daily_notifications' );
    }
}
register_uninstall_hook( __FILE__, 'evidencija_uninstall' );


// Inicijalizacija glavnih klasa plugina
function evidencija_init() {
    if ( is_admin() ) {
        // Inicijaliziramo klase tek nakon što su svi plugini učitani
        new Evidencija_Admin_Pages();
    }
}
add_action( 'plugins_loaded', 'evidencija_init' );

// Dodavanje custom intervala za WP-Cron (ako želimo npr. 'hourly')
// function evidencija_add_cron_schedules( $schedules ) {
//     $schedules['every_five_minutes'] = array(
//         'interval' => 300, // 5 minuta * 60 sekundi
//         'display'  => __( 'Every 5 Minutes', 'evidencija' ),
//     );
//     return $schedules;
// }
// add_filter( 'cron_schedules', 'evidencija_add_cron_schedules' );


// Registracija funkcije koja će se pozivati putem cron joba
add_action( 'evidencija_daily_notifications', 'Evidencija_Notifications::send_arrival_notifications' ); // <-- NOVO