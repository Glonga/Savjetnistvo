<?php
namespace Savjetnistvo\Users;

class Roles {
  public static function init(){
    add_action('init', [__CLASS__, 'add_roles']);
  }
  public static function add_roles(){
    // Legacy/plugin-specific roles
    add_role('wpc_client', __('Klijent','savjetnistvo'), ['read'=>true]);
    add_role('wpc_coach', __('Savjetnik','savjetnistvo'), ['read' => true]);

    // Generic roles as requested (aliases for filtering in Users screen)
    add_role('client', __('Klijent','savjetnistvo'), ['read'=>true]);
    add_role('consultant', __('Savjetnik','savjetnistvo'), ['read'=>true]);
    $coach = get_role('wpc_coach');
    if($coach){
      $coach->add_cap('create_users');
    }
  }
}
