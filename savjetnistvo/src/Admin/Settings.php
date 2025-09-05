<?php
namespace Savjetnistvo\Admin;

class Settings {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_init', [__CLASS__, 'register']);
  }

  public static function menu(){
    add_submenu_page(
      'savjetnistvo',
      __('Postavke', 'savjetnistvo'),
      __('Postavke', 'savjetnistvo'),
      'manage_options',
      'savjetnistvo-settings',
      [__CLASS__, 'render']
    );
  }

  public static function register(){
    register_setting('sv_settings_group', 'sv_settings', [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize'],
      'default' => [ 'meetings_cutoff_hours' => 72 ],
    ]);

    add_settings_section(
      'sv_settings_general',
      __('Općenito', 'savjetnistvo'),
      function(){ echo '<p>' . esc_html__('Opće postavke portala.', 'savjetnistvo') . '</p>'; },
      'savjetnistvo-settings'
    );
    add_settings_field(
      'portal_url',
      __('Portal URL', 'savjetnistvo'),
      [__CLASS__, 'field_portal_url'],
      'savjetnistvo-settings',
      'sv_settings_general'
    );

    add_settings_section(
      'sv_settings_meetings',
      __('Susreti', 'savjetnistvo'),
      function(){ echo '<p>' . esc_html__('Postavke za predaju dokumenata i susrete.', 'savjetnistvo') . '</p>'; },
      'savjetnistvo-settings'
    );

    add_settings_field(
      'meetings_cutoff_hours',
      __('Cutoff sati za predaju (.docx)', 'savjetnistvo'),
      [__CLASS__, 'field_cutoff'],
      'savjetnistvo-settings',
      'sv_settings_meetings'
    );

    // Email settings section
    add_settings_section(
      'sv_settings_emails',
      __('E-mail postavke', 'savjetnistvo'),
      function(){ echo '<p>' . esc_html__('Polja i predlošci za system e-mailove.', 'savjetnistvo') . '</p>'; },
      'savjetnistvo-settings'
    );
    add_settings_field('email_from_name', __('From ime','savjetnistvo'), [__CLASS__,'field_from_name'], 'savjetnistvo-settings', 'sv_settings_emails');
    add_settings_field('email_from', __('From e-mail','savjetnistvo'), [__CLASS__,'field_from_email'], 'savjetnistvo-settings', 'sv_settings_emails');
    add_settings_field('email_toggles', __('Uključene poruke','savjetnistvo'), [__CLASS__,'field_toggles'], 'savjetnistvo-settings', 'sv_settings_emails');
    add_settings_field('email_tpl_zakazan', __('Predložak: Zakazan','savjetnistvo'), [__CLASS__,'field_tpl_zakazan'], 'savjetnistvo-settings', 'sv_settings_emails');
    add_settings_field('email_tpl_reminder', __('Predložak: Podsjetnik 96h','savjetnistvo'), [__CLASS__,'field_tpl_reminder'], 'savjetnistvo-settings', 'sv_settings_emails');
    add_settings_field('email_tpl_predan', __('Predložak: Predan dokument','savjetnistvo'), [__CLASS__,'field_tpl_predan'], 'savjetnistvo-settings', 'sv_settings_emails');
  }

  public static function sanitize($input){
    $out = is_array($input) ? $input : [];
    $hours = isset($out['meetings_cutoff_hours']) ? (int) $out['meetings_cutoff_hours'] : 72;
    if ($hours <= 0) $hours = 72;
    $out['meetings_cutoff_hours'] = $hours;
    $out['portal_url'] = !empty($out['portal_url']) ? esc_url_raw($out['portal_url']) : '';
    $out['email_from_name'] = sanitize_text_field($out['email_from_name'] ?? '');
    $out['email_from'] = sanitize_email($out['email_from'] ?? '');
    $out['email_toggle_zakazan'] = !empty($out['email_toggle_zakazan']) ? 1 : 0;
    $out['email_toggle_reminder'] = !empty($out['email_toggle_reminder']) ? 1 : 0;
    $out['email_toggle_predan'] = !empty($out['email_toggle_predan']) ? 1 : 0;
    // subjects/bodies are free text
    foreach (['zakazan','reminder','predan'] as $k){
      $out['email_tpl_'.$k.'_subject'] = wp_kses_post($out['email_tpl_'.$k.'_subject'] ?? '');
      $out['email_tpl_'.$k.'_body']    = wp_kses_post($out['email_tpl_'.$k.'_body'] ?? '');
    }
    return $out;
  }

  public static function field_cutoff(){
    $opts = get_option('sv_settings', []);
    $val = isset($opts['meetings_cutoff_hours']) ? (int)$opts['meetings_cutoff_hours'] : 72;
    echo '<input type="number" min="1" step="1" name="sv_settings[meetings_cutoff_hours]" value="' . esc_attr($val) . '" class="small-text" />';
    echo ' <span class="description">' . esc_html__('Default 72', 'savjetnistvo') . '</span>';
  }

  public static function render(){
    if (!current_user_can('manage_options')){
      wp_die(__('Nemate dopuštenje.', 'savjetnistvo'));
    }
    // Handle test email
    $notice = '';
    if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['sv_send_test_mail']) && check_admin_referer('sv_test_mail','sv_test_mail_nonce')){
      $to = sanitize_email($_POST['sv_test_email'] ?? '');
      if ($to){
        require_once SAVJETNISTVO_DIR . 'src/Front/Mailer.php';
        $ok = \Savjetnistvo\Front\Mailer::send($to, __('Test Savjetništvo', 'savjetnistvo'), __('Ovo je test poruka…', 'savjetnistvo'));
        if ($ok){
          $notice = '<div class="notice notice-success"><p>' . esc_html__('Test e-mail je poslan.', 'savjetnistvo') . '</p></div>';
        } else {
          $notice = '<div class="notice notice-error"><p>' . esc_html__('Slanje testa nije uspjelo.', 'savjetnistvo') . '</p></div>';
        }
      } else {
        $notice = '<div class="notice notice-error"><p>' . esc_html__('Unesite valjanu e-mail adresu.', 'savjetnistvo') . '</p></div>';
      }
    }

    echo '<div class="wrap">';
    if ($notice) echo $notice;
    echo '<h1>' . esc_html__('Savjetništvo – Postavke', 'savjetnistvo') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('sv_settings_group');
    do_settings_sections('savjetnistvo-settings');
    submit_button();
    echo '</form>';

    // Test email form
    echo '<h2>' . esc_html__('Test e-mail', 'savjetnistvo') . '</h2>';
    echo '<form method="post">';
    wp_nonce_field('sv_test_mail','sv_test_mail_nonce');
    echo '<p><input type="email" name="sv_test_email" class="regular-text" placeholder="you@example.com" required> ';
    echo '<button class="button button-primary" type="submit" name="sv_send_test_mail" value="1">' . esc_html__('Pošalji test', 'savjetnistvo') . '</button></p>';
    echo '</form>';
    echo '</div>';
  }

  // Fields
  public static function field_from_name(){
    $opts = get_option('sv_settings', []);
    $val = $opts['email_from_name'] ?? '';
    echo '<input type="text" name="sv_settings[email_from_name]" value="' . esc_attr($val) . '" class="regular-text" />';
  }
  public static function field_from_email(){
    $opts = get_option('sv_settings', []);
    $val = $opts['email_from'] ?? '';
    echo '<input type="email" name="sv_settings[email_from]" value="' . esc_attr($val) . '" class="regular-text" />';
  }
  public static function field_portal_url(){
    $opts = get_option('sv_settings', []);
    $val = $opts['portal_url'] ?? '';
    echo '<input type="url" name="sv_settings[portal_url]" value="' . esc_attr($val) . '" class="regular-text" placeholder="https://example.com/portal" />';
    echo '<p class="description">' . esc_html__('Ostavite prazno za automatsko pronalaženje stranice s kratkim kodom ili /portal.', 'savjetnistvo') . '</p>';
  }
  public static function field_toggles(){
    $o = get_option('sv_settings', []);
    $z = !empty($o['email_toggle_zakazan']);
    $r = !empty($o['email_toggle_reminder']);
    $p = !empty($o['email_toggle_predan']);
    echo '<label><input type="checkbox" name="sv_settings[email_toggle_zakazan]" '.checked($z,true,false).' /> ' . esc_html__('Zakazan', 'savjetnistvo') . '</label><br>';
    echo '<label><input type="checkbox" name="sv_settings[email_toggle_reminder]" '.checked($r,true,false).' /> ' . esc_html__('Podsjetnik 96h', 'savjetnistvo') . '</label><br>';
    echo '<label><input type="checkbox" name="sv_settings[email_toggle_predan]" '.checked($p,true,false).' /> ' . esc_html__('Predan dokument', 'savjetnistvo') . '</label>';
  }
  public static function field_tpl_zakazan(){ self::tpl_fields('zakazan'); }
  public static function field_tpl_reminder(){ self::tpl_fields('reminder'); }
  public static function field_tpl_predan(){ self::tpl_fields('predan'); }
  protected static function tpl_fields($key){
    $o = get_option('sv_settings', []);
    $sub = $o['email_tpl_'.$key.'_subject'] ?? '';
    $bod = $o['email_tpl_'.$key.'_body'] ?? '';
    echo '<p><label>Subject<br><input type="text" name="sv_settings[email_tpl_'.$key.'_subject]" value="'.esc_attr($sub).'" class="regular-text" /></label></p>';
    echo '<p><label>Body<br><textarea name="sv_settings[email_tpl_'.$key.'_body]" rows="5" class="large-text">'.esc_textarea($bod).'</textarea></label></p>';
    echo '<p class="description">'.esc_html__('Placeholders: {client_name}, {project_title}, {meeting_datetime}, {upload_deadline}, {portal_url}', 'savjetnistvo').'</p>';
  }
}
