<?php
namespace Savjetnistvo\Users;

class ProfileFields {
  public static function init(){
    add_action('show_user_profile', [__CLASS__,'render']);
    add_action('edit_user_profile', [__CLASS__,'render']);
    add_action('personal_options_update', [__CLASS__,'save']);
    add_action('edit_user_profile_update', [__CLASS__,'save']);
  }
  public static function render($user){
    if (!current_user_can('edit_user',$user->ID)) return;
    $pseud = get_user_meta($user->ID,'wpc_pseudonim',true);
    $phone = get_user_meta($user->ID,'wpc_phone',true); ?>
    <h2><?php _e('Savjetništvo — osobni podaci','savjetnistvo'); ?></h2>
    <table class="form-table">
      <tr><th><label for="wpc_pseudonim"><?php _e('Pseudonim','savjetnistvo'); ?></label></th>
          <td><input name="wpc_pseudonim" id="wpc_pseudonim" class="regular-text" value="<?php echo esc_attr($pseud); ?>"></td></tr>
      <tr><th><label for="wpc_phone"><?php _e('Telefon','savjetnistvo'); ?></label></th>
          <td><input name="wpc_phone" id="wpc_phone" class="regular-text" value="<?php echo esc_attr($phone); ?>"></td></tr>
    </table><?php
  }
  public static function save($user_id){
    if (!current_user_can('edit_user',$user_id)) return;
    update_user_meta($user_id,'wpc_pseudonim', sanitize_text_field($_POST['wpc_pseudonim'] ?? ''));
    update_user_meta($user_id,'wpc_phone', sanitize_text_field($_POST['wpc_phone'] ?? ''));
  }
}
