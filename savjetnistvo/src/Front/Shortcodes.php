<?php
namespace Savjetnistvo\Front;

class Shortcodes {
  public static function init(){
    add_shortcode('savjetnistvo_portal', [__CLASS__, 'portal']);
    add_shortcode('savjetnistvo_register', [__CLASS__, 'register']);
    add_shortcode('sv_login', [__CLASS__, 'login']);
    add_shortcode('sv_logout', [__CLASS__, 'logout']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
  }

  public static function assets(){
    wp_register_style('savjetnistvo-portal', SAVJETNISTVO_URL . 'assets/css/portal.css', [], SAVJETNISTVO_VER);
  }

  public static function portal($atts){
    if (!is_user_logged_in()){
      ob_start();
      wp_login_form([
        'redirect' => get_permalink(),
      ]);
      echo '<p><a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__('Zaboravljena lozinka?', 'savjetnistvo') . '</a></p>';
      return ob_get_clean();
    }
    wp_enqueue_style('savjetnistvo-portal');

    ob_start(); ?>
    <div class="sv-portal">
      <div class="sv-tabs">
        <button class="sv-tab sv-tab-active" data-tab="projects"><?php echo esc_html__('Projekti','savjetnistvo'); ?></button>
        <button class="sv-tab" data-tab="meetings"><?php echo esc_html__('Susreti','savjetnistvo'); ?></button>
        <button class="sv-tab" data-tab="payments"><?php echo esc_html__('Plaćanja','savjetnistvo'); ?></button>
        <button class="sv-tab" data-tab="profile"><?php echo esc_html__('Moji podaci','savjetnistvo'); ?></button>
      </div>

      <div class="sv-panels">
        <section data-panel="projects"></section>
        <section class="hidden" data-panel="meetings"></section>
        <section class="hidden" data-panel="payments"></section>
        <section class="hidden" data-panel="profile">
          <h3><?php echo esc_html__('Moji podaci', 'savjetnistvo'); ?></h3>
          <div class="sv-body">
            <div><strong><?php echo esc_html__('Ime', 'savjetnistvo'); ?>:</strong> <span data-me="display">—</span></div>
            <div><strong>Email:</strong> <span data-me="email">—</span></div>
            <div><strong><?php echo esc_html__('Pseudonim', 'savjetnistvo'); ?>:</strong> <span data-me="pseudonim">—</span></div>
            <div><strong><?php echo esc_html__('Telefon', 'savjetnistvo'); ?>:</strong> <span data-me="phone">—</span></div>
          </div>
        </section>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }
  
  public static function register($atts){
    if(is_user_logged_in()){
      return '<p>' . esc_html__('Već ste prijavljeni.', 'savjetnistvo') . '</p>';
    }
    $message = '';
    if('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['sv_reg_nonce']) && wp_verify_nonce($_POST['sv_reg_nonce'], 'sv_reg')){
      $userdata = [
        'user_login' => sanitize_user($_POST['sv_user_login'] ?? ''),
        'user_email' => sanitize_email($_POST['sv_user_email'] ?? ''),
        'user_pass'  => $_POST['sv_user_pass'] ?? '',
        'role'       => 'wpc_client',
      ];
      $user_id = wp_insert_user($userdata);
      if(!is_wp_error($user_id)){
        update_user_meta($user_id, 'wpc_pseudonim', sanitize_text_field($_POST['sv_pseudonim'] ?? ''));
        update_user_meta($user_id, 'wpc_phone', sanitize_text_field($_POST['sv_phone'] ?? ''));
        return '<p>' . esc_html__('Registracija uspješna. Možete se prijaviti.', 'savjetnistvo') . '</p>';
      }else{
        $message = '<p class="sv-error">' . esc_html($user_id->get_error_message()) . '</p>';
      }
    }
    ob_start();
    echo $message;
    ?>
    <form method="post" class="sv-register-form">
      <p><label><?php echo esc_html__('Korisničko ime', 'savjetnistvo'); ?><br>
        <input type="text" name="sv_user_login" required></label></p>
      <p><label>Email<br>
        <input type="email" name="sv_user_email" required></label></p>
      <p><label><?php echo esc_html__('Lozinka', 'savjetnistvo'); ?><br>
        <input type="password" name="sv_user_pass" required></label></p>
      <p><label><?php echo esc_html__('Pseudonim', 'savjetnistvo'); ?><br>
        <input type="text" name="sv_pseudonim"></label></p>
      <p><label><?php echo esc_html__('Telefon', 'savjetnistvo'); ?><br>
        <input type="text" name="sv_phone"></label></p>
      <?php wp_nonce_field('sv_reg','sv_reg_nonce'); ?>
      <p><input type="submit" value="<?php echo esc_attr__('Registriraj se', 'savjetnistvo'); ?>"></p>
    </form>
    <?php
    return ob_get_clean();
  }

  protected static function get_portal_url(){
    // Try to find a page containing [savjetnistvo_portal]
    $portal = get_pages([
      'number' => 1,
      's' => '[savjetnistvo_portal]'
    ]);
    if (!empty($portal)){
      return get_permalink($portal[0]->ID);
    }
    // Fallback
    return home_url('/portal');
  }

  public static function login($atts){
    $portal_url = self::get_portal_url();
    if (is_user_logged_in()){
      $out  = '<p>' . esc_html__('Već ste prijavljeni.', 'savjetnistvo') . '</p>';
      $out .= '<p><a class="button" href="' . esc_url($portal_url) . '">' . esc_html__('Idi na portal', 'savjetnistvo') . '</a></p>';
      return $out;
    }
    ob_start();
    wp_login_form([
      'redirect' => $portal_url,
    ]);
    echo '<p><a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__('Zaboravljena lozinka?', 'savjetnistvo') . '</a></p>';
    return ob_get_clean();
  }

  public static function logout($atts){
    $login_url = home_url('/prijava');
    $url = wp_logout_url($login_url);
    return '<a href="' . esc_url($url) . '">' . esc_html__('Odjava', 'savjetnistvo') . '</a>';
  }
}

