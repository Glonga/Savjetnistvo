<?php
namespace Savjetnistvo\Admin;

class PageDashboard {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu(){
    add_menu_page(
      __('Savjetništvo', 'savjetnistvo'),
      __('Savjetništvo', 'savjetnistvo'),
      'create_users',
      'savjetnistvo',
      [__CLASS__, 'render'],
      'dashicons-groups'
    );
  }

  public static function render(){
    if(!current_user_can('create_users')){
      wp_die(__('Nemate dopuštenje.', 'savjetnistvo'));
    }
    echo '<div class="wrap"><h1>' . esc_html__('Savjetništvo', 'savjetnistvo') . '</h1></div>';
  }
}