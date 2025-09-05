<?php
namespace Savjetnistvo\Core;


class Plugin {
  public static function init(){
    // i18n (učitavanje prijevoda)
    add_action('plugins_loaded', function(){
      load_plugin_textdomain('savjetnistvo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    });

    // CPT: Projekt
    require_once SAVJETNISTVO_DIR . 'src/CPT/ProjectCPT.php';
    \Savjetnistvo\CPT\ProjectCPT::init();

    // Shortcode portal
    require_once SAVJETNISTVO_DIR . 'src/Front/Shortcodes.php';
    \Savjetnistvo\Front\Shortcodes::init();

    // ROLE: Klijent / Savjetnik
    require_once SAVJETNISTVO_DIR . 'src/Users/Roles.php';
    \Savjetnistvo\Users\Roles::init();

    // USERMETA: Pseudonim, Telefon (u profilu korisnika)
    require_once SAVJETNISTVO_DIR . 'src/Users/ProfileFields.php';
    \Savjetnistvo\Users\ProfileFields::init();

    // ADMIN: Metabox "Postavke projekta"
    require_once SAVJETNISTVO_DIR . 'src/Admin/MetaboxProject.php';
    \Savjetnistvo\Admin\MetaboxProject::init();

    // ADMIN: Stranica "Klijenti"
    require_once SAVJETNISTVO_DIR . 'src/Admin/PageClients.php';
    \Savjetnistvo\Admin\PageClients::init();

    // ADMIN PAGES
    require_once SAVJETNISTVO_DIR . 'src/Admin/PageDashboard.php';
    \Savjetnistvo\Admin\PageDashboard::init();
    
    require_once SAVJETNISTVO_DIR . 'src/Admin/PageUsers.php';
    \Savjetnistvo\Admin\PageUsers::init();

    // ADMIN: Postavke
    require_once SAVJETNISTVO_DIR . 'src/Admin/Settings.php';
    \Savjetnistvo\Admin\Settings::init();
    // Mailer
    require_once SAVJETNISTVO_DIR . 'src/Front/Mailer.php';

    require_once SAVJETNISTVO_DIR . 'src/REST/Routes.php';
    \Savjetnistvo\REST\Routes::init();

    // Cron handler hook
    add_action('sv_cron_hourly', ['\\Savjetnistvo\\REST\\MeetingsController', 'cron_reminders']);
  }
}
add_action('wp_enqueue_scripts', function () {
    if (!is_user_logged_in()) return; // portal je samo za prijavljene
    // Ako želiš: ograniči enqueue samo na stranicu s kratkim kodom, no za MVP je ok ovako.

    wp_enqueue_style('sv-portal', SAVJETNISTVO_URL . 'assets/css/portal.css', [], SAVJETNISTVO_VER);

    wp_enqueue_script('sv-portal', SAVJETNISTVO_URL . 'assets/js/portal.js', [], SAVJETNISTVO_VER, true);

    // Proslijedi podatke u JS
    wp_localize_script('sv-portal', 'SV', [
        'nonce'  => wp_create_nonce('wp_rest'),
        'rest'   => esc_url_raw( rest_url('sv/v1/') ),
        'userId' => get_current_user_id(),
    ]);
});

// Dodaj type="module" samo ovoj skripti
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle === 'sv-portal') {
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}, 10, 3);

// Redirect clients away from wp-admin to the portal, unless AJAX/REST
add_action('admin_init', function(){
    if (!is_user_logged_in()) return;
    if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    $u = wp_get_current_user();
    if ($u && is_array($u->roles) && in_array('wpc_client', $u->roles, true)){
        wp_safe_redirect( sv_portal_url() );
        exit;
    }
});

// Hide admin bar for clients on the front-end
add_action('after_setup_theme', function(){
    if (!is_user_logged_in()) return;
    $u = wp_get_current_user();
    if ($u && is_array($u->roles) && in_array('wpc_client', $u->roles, true)){
        show_admin_bar(false);
    }
});

// Helper within this namespace: get the Portal URL (page containing [savjetnistvo_portal])
if (!function_exists(__NAMESPACE__ . '\\sv_portal_url')){
    function sv_portal_url(){
        $opts = get_option('sv_settings', []);
        if (!empty($opts['portal_url'])){
            return esc_url($opts['portal_url']);
        }
        $pages = get_posts([
            'post_type'      => 'page',
            'posts_per_page' => 1,
            's'              => '[savjetnistvo_portal]'
        ]);
        if (!empty($pages)){
            return get_permalink($pages[0]->ID);
        }
        return home_url('/portal');
    }
}
