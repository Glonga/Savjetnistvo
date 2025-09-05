<?php
namespace Savjetnistvo\Admin;

class PageClients {
  public static function init(){
    add_action('nothing_hook', [__CLASS__, 'menu']);
  }

  public static function menu(){
    // no-op to avoid duplicate menu entries
  }

  public static function remove_duplicate(){ /* no-op */ }

  public static function dashboard(){
    echo '<div class="wrap"><h1>'.esc_html__('Savjetništvo', 'savjetnistvo').'</h1></div>';
  }
  public static function render(){
    if(!current_user_can('list_users')) return;
    $users = get_users(['role' => 'wpc_client']);
    echo '<div class="wrap"><h1>'.esc_html__('Korisnici', 'savjetnistvo').'</h1>';
    echo '<table class="widefat fixed"><thead><tr>';
    echo '<th>ID</th><th>'.esc_html__('Ime', 'savjetnistvo').'</th><th>Email</th><th>'.esc_html__('Pseudonim', 'savjetnistvo').'</th><th>'.esc_html__('Telefon', 'savjetnistvo').'</th>';
    echo '</tr></thead><tbody>';
    if($users){
      foreach($users as $u){
        $edit_link = esc_url(admin_url('user-edit.php?user_id='.$u->ID));
        $pseud = get_user_meta($u->ID, 'wpc_pseudonim', true);
        $phone = get_user_meta($u->ID, 'wpc_phone', true);
        echo '<tr>';
        echo '<td>'.$u->ID.'</td>';
        echo '<td><a href="'.$edit_link.'">'.esc_html($u->display_name).'</a></td>';
        echo '<td>'.esc_html($u->user_email).'</td>';
        echo '<td>'.esc_html($pseud).'</td>';
        echo '<td>'.esc_html($phone).'</td>';
        echo '</tr>';
      }
    } else {
      echo '<tr><td colspan="5">'.esc_html__('Nema korisnika.', 'savjetnistvo').'</td></tr>';
    }
    echo '</tbody></table></div>';
  }
}
