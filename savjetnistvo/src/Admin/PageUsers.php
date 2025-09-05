<?php
namespace Savjetnistvo\Admin;

class PageUsers {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu(){
    // Klijenti (internal list – role__in: wpc_client, client)
    add_submenu_page(
      'savjetnistvo',
      __('Klijenti', 'savjetnistvo'),
      __('Klijenti', 'savjetnistvo'),
      'list_users',
      'sv-klijenti',
      [__CLASS__, 'render_clients_page']
    );

    // Savjetnici (internal list – role__in: wpc_coach, consultant)
    add_submenu_page(
      'savjetnistvo',
      __('Savjetnici', 'savjetnistvo'),
      __('Savjetnici', 'savjetnistvo'),
      'list_users',
      'sv-savjetnici',
      [__CLASS__, 'render_coaches_page']
    );
  }

  protected static function render_users_table($args){
    if (!current_user_can('list_users')) wp_die(__('Nemate dopuštenje.', 'savjetnistvo'));

    $role_in = (array)($args['role__in'] ?? []);
    $title   = (string)($args['title'] ?? '');
    $core_links = (array)($args['core_links'] ?? []);
    $normalize_key = (string)($args['normalize_key'] ?? ''); // 'clients'|'coaches'

    // Handle normalize POST
    $notice = '';
    if (isset($_POST['sv_normalize']) && isset($_POST['sv_norm_nonce']) && wp_verify_nonce($_POST['sv_norm_nonce'], 'sv_norm') && current_user_can('promote_users')){
      $updated = 0;
      if ($normalize_key === 'clients'){
        // add wpc_client to all users who have 'client' but not 'wpc_client'
        $uq = new \WP_User_Query(['role__in' => ['client'], 'number' => -1, 'fields' => ['ID', 'roles']]);
        foreach ((array)$uq->get_results() as $u){
          $user = get_userdata($u->ID);
          if ($user && !in_array('wpc_client', (array)$user->roles, true)){
            $user->add_role('wpc_client');
            $updated++;
          }
        }
      } elseif ($normalize_key === 'coaches'){
        $uq = new \WP_User_Query(['role__in' => ['consultant'], 'number' => -1, 'fields' => ['ID', 'roles']]);
        foreach ((array)$uq->get_results() as $u){
          $user = get_userdata($u->ID);
          if ($user && !in_array('wpc_coach', (array)$user->roles, true)){
            $user->add_role('wpc_coach');
            $updated++;
          }
        }
      }
      $notice = '<div class="notice notice-success"><p>' . sprintf(esc_html__('%d korisnika normalizirano.', 'savjetnistvo'), (int)$updated) . '</p></div>';
    }

    $paged  = max(1, (int)($_GET['paged'] ?? 1));
    $number = 50;
    $offset = ($paged - 1) * $number;

    $query = new \WP_User_Query([
      'role__in'    => $role_in,
      'number'      => $number,
      'offset'      => $offset,
      'count_total' => true,
      'orderby'     => 'ID',
      'order'       => 'ASC',
    ]);

    $total = (int) $query->get_total();
    $users = (array) $query->get_results();
    $total_pages = max(1, (int)ceil($total / $number));

    // Counts per role
    $count_a = (new \WP_User_Query(['role' => $role_in[0] ?? '', 'fields' => 'ID']))->get_total();
    $count_b = (new \WP_User_Query(['role' => $role_in[1] ?? '', 'fields' => 'ID']))->get_total();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html($title) . '</h1>';
    echo '<p>' . esc_html__('Prikaz uključuje i wpc_* i generičke role (kompatibilnost).', 'savjetnistvo') . '</p>';
    echo '<p>' . esc_html($role_in[0] ?? '') . ': ' . (int)$count_a . ', ' . esc_html($role_in[1] ?? '') . ': ' . (int)$count_b . '</p>';

    // Core links
    if (!empty($core_links)){
      echo '<p>';
      foreach ($core_links as $label => $href){
        echo '<a class="button button-secondary" style="margin-right:8px" href="' . esc_url(admin_url($href)) . '">' . esc_html($label) . '</a>';
      }
      echo '</p>';
    }

    // Normalize button
    if ($normalize_key){
      echo '<form method="post" style="margin:12px 0">';
      wp_nonce_field('sv_norm','sv_norm_nonce');
      echo '<input type="hidden" name="sv_normalize" value="1" />';
      echo '<button class="button" type="submit">' . esc_html__('Normalize roles', 'savjetnistvo') . '</button>';
      echo '</form>';
    }

    if ($notice) echo $notice;

    if (empty($users)){
      echo '<p><em>' . esc_html__('Nema korisnika za odabrane role.', 'savjetnistvo') . '</em></p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>' . esc_html__('Ime', 'savjetnistvo') . '</th><th>Email</th><th>' . esc_html__('Role', 'savjetnistvo') . '</th><th>' . esc_html__('Broj projekata', 'savjetnistvo') . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($users as $u){
      $edit = esc_url(admin_url('user-edit.php?user_id=' . $u->ID));
      $roles = implode(', ', array_map('esc_html', (array)$u->roles));
      // Count projects
      $projects_count = 0;
      if (in_array('wpc_client', (array)$u->roles, true) || in_array('client', (array)$u->roles, true)){
        $q = new \WP_Query([
          'post_type'      => 'writing_project',
          'posts_per_page' => -1,
          'fields'         => 'ids',
          'meta_query'     => [
            [ 'key' => 'sv_client_id', 'value' => $u->ID, 'compare' => '=' ],
          ],
        ]);
        $projects_count += count((array)$q->posts);
      }
      if (in_array('wpc_coach', (array)$u->roles, true) || in_array('consultant', (array)$u->roles, true)){
        $q2 = new \WP_Query([
          'post_type'      => 'writing_project',
          'posts_per_page' => -1,
          'fields'         => 'ids',
          'meta_query'     => [
            [ 'key' => 'sv_coach_id', 'value' => $u->ID, 'compare' => '=' ],
          ],
        ]);
        $projects_count += count((array)$q2->posts);
      }

      echo '<tr>';
      echo '<td>' . (int)$u->ID . '</td>';
      echo '<td><a href="' . $edit . '">' . esc_html($u->display_name ?: $u->user_login) . '</a></td>';
      echo '<td>' . esc_html($u->user_email) . '</td>';
      echo '<td>' . $roles . '</td>';
      echo '<td>' . (int)$projects_count . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';

    // Pagination
    if ($total_pages > 1){
      $base_url = remove_query_arg('paged');
      echo '<div class="tablenav"><div class="tablenav-pages">';
      for ($i=1; $i<=$total_pages; $i++){
        $url = esc_url(add_query_arg('paged', $i, $base_url));
        $class = $i === $paged ? ' class="page-numbers current"' : ' class="page-numbers"';
        echo '<a' . $class . ' href="' . $url . '">' . $i . '</a> ';
      }
      echo '</div></div>';
    }

    echo '</div>';
  }

  public static function render_clients_page(){
    self::render_users_table([
      'title'      => __('Klijenti', 'savjetnistvo'),
      'role__in'   => ['wpc_client','client'],
      'core_links' => [
        'Core Users: wpc_client' => 'users.php?role=wpc_client',
        'Core Users: client'     => 'users.php?role=client',
      ],
      'normalize_key' => 'clients',
    ]);
  }

  public static function render_coaches_page(){
    self::render_users_table([
      'title'      => __('Savjetnici', 'savjetnistvo'),
      'role__in'   => ['wpc_coach','consultant'],
      'core_links' => [
        'Core Users: wpc_coach'   => 'users.php?role=wpc_coach',
        'Core Users: consultant'  => 'users.php?role=consultant',
      ],
      'normalize_key' => 'coaches',
    ]);
  }

  public static function render(){
    if(!current_user_can('create_users')){
      wp_die(__('Nemate dopuštenje.', 'savjetnistvo'));
    }
    if($user_id = intval($_GET['user_id'] ?? 0)){
      self::render_user_card($user_id);
      return;
    }

    if(($_GET['action'] ?? '') === 'new'){
      self::render_add_user();
      return;
    }

    $users = get_users(['role' => 'wpc_client']);

    $add_link = esc_url(admin_url('admin.php?page=savjetnistvo-users&action=new'));
    echo '<div class="wrap"><h1>' . esc_html__('Korisnici', 'savjetnistvo') . ' <a href="' . $add_link . '" class="page-title-action">' . esc_html__('Dodaj novog', 'savjetnistvo') . '</a></h1>';

    echo '<h2>' . esc_html__('Postojeći korisnici', 'savjetnistvo') . '</h2>';
    echo '<table class="widefat"><thead><tr><th>' . esc_html__('Korisničko ime', 'savjetnistvo') . '</th><th>Email</th><th>' . esc_html__('Pseudonim', 'savjetnistvo') . '</th><th>' . esc_html__('Telefon', 'savjetnistvo') . '</th></tr></thead><tbody>';    if($users){
      foreach($users as $u){
        $link = esc_url(admin_url('admin.php?page=savjetnistvo-users&user_id=' . $u->ID));
        echo '<tr><td><a href="' . $link . '">' . esc_html($u->user_login) . '</a></td><td>' . esc_html($u->user_email) . '</td><td>' . esc_html(get_user_meta($u->ID, 'wpc_pseudonim', true)) . '</td><td>' . esc_html(get_user_meta($u->ID, 'wpc_phone', true)) . '</td></tr>';      }
    }else{
      echo '<tr><td colspan="4"><em>' . esc_html__('Nema korisnika.', 'savjetnistvo') . '</em></td></tr>';
    }
    echo '</tbody></table>';

    echo '</div>';
  }

  private static function render_add_user(){
    $notice = '';
    if(($_POST['savjetnistvo_action'] ?? '') === 'create_user'){
      check_admin_referer('savjetnistvo_create_client', 'savjetnistvo_nonce');
      $user_login = sanitize_user($_POST['user_login'] ?? '');
      $user_email = sanitize_email($_POST['user_email'] ?? '');
      $pseudonim  = sanitize_text_field($_POST['pseudonim'] ?? '');
      $phone      = sanitize_text_field($_POST['phone'] ?? '');
      $pass       = sanitize_text_field($_POST['user_pass'] ?? '');
      $pass       = $pass ?: wp_generate_password();

      $user_id = wp_insert_user([
        'user_login' => $user_login,
        'user_email' => $user_email,
        'user_pass'  => $pass,
        'role'       => 'wpc_client',
      ]);
      if(is_wp_error($user_id)){
        $notice = '<div class="notice notice-error"><p>' . esc_html($user_id->get_error_message()) . '</p></div>';
      }else{
        update_user_meta($user_id, 'wpc_pseudonim', $pseudonim);
        update_user_meta($user_id, 'wpc_phone', $phone);
        update_user_meta($user_id, 'wpc_pass', $pass);
        $notice = '<div class="notice notice-success"><p>' . esc_html__('Korisnik stvoren.', 'savjetnistvo') . '</p></div>';
      }
    }

    echo '<div class="wrap"><h1>' . esc_html__('Dodaj novog korisnika', 'savjetnistvo') . '</h1>';
    echo $notice;
    echo '<form method="post">';
    wp_nonce_field('savjetnistvo_create_client', 'savjetnistvo_nonce');
    echo '<input type="hidden" name="savjetnistvo_action" value="create_user" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label for="user_login">' . esc_html__('Korisničko ime', 'savjetnistvo') . '</label></th><td><input type="text" name="user_login" id="user_login" required></td></tr>';
    echo '<tr><th><label for="user_email">' . esc_html__('Email', 'savjetnistvo') . '</label></th><td><input type="email" name="user_email" id="user_email" required></td></tr>';
    echo '<tr><th><label for="pseudonim">' . esc_html__('Pseudonim', 'savjetnistvo') . '</label></th><td><input type="text" name="pseudonim" id="pseudonim"></td></tr>';
    echo '<tr><th><label for="phone">' . esc_html__('Telefon', 'savjetnistvo') . '</label></th><td><input type="text" name="phone" id="phone"></td></tr>';
    echo '<tr><th><label for="user_pass">' . esc_html__('Lozinka', 'savjetnistvo') . '</label></th><td><input type="text" name="user_pass" id="user_pass" placeholder="' . esc_attr__('Automatski ako prazno', 'savjetnistvo') . '"></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Stvori korisnika', 'savjetnistvo'));
    echo '</form>';
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=savjetnistvo-users')) . '">' . esc_html__('Natrag na korisnike', 'savjetnistvo') . '</a></p>';
    echo '</div>';
  }

  private static function render_user_card($user_id){
    $user = get_userdata($user_id);
    echo '<div class="wrap">';
    if(!$user){
      echo '<h1>' . esc_html__('Korisnik nije pronađen', 'savjetnistvo') . '</h1>';
      echo '<p><a href="' . esc_url(admin_url('admin.php?page=savjetnistvo-users')) . '">' . esc_html__('Natrag na korisnike', 'savjetnistvo') . '</a></p>';
      echo '</div>';
      return;
    }

    $notice = '';
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
      check_admin_referer('savjetnistvo_update_user', 'savjetnistvo_nonce');
      $action = $_POST['savjetnistvo_action'] ?? '';
      if($action === 'update_user'){
        $first = sanitize_text_field($_POST['first_name'] ?? '');
        $last  = sanitize_text_field($_POST['last_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $email = sanitize_email($_POST['user_email'] ?? '');
        $res = wp_update_user([
          'ID'         => $user_id,
          'first_name' => $first,
          'last_name'  => $last,
          'user_email' => $email,
        ]);
        if(is_wp_error($res)){
          $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
        }else{
          update_user_meta($user_id, 'wpc_phone', $phone);
          $notice = '<div class="notice notice-success"><p>' . esc_html__('Korisnik ažuriran.', 'savjetnistvo') . '</p></div>';
          $user = get_userdata($user_id);
        }
      }elseif($action === 'send_reset'){
        retrieve_password($user->user_login);
        $notice = '<div class="notice notice-success"><p>' . esc_html__('Poslan je email za reset lozinke.', 'savjetnistvo') . '</p></div>';
      }
    }

    $name = trim($user->first_name . ' ' . $user->last_name);
    $name = $name ?: $user->user_login;
    echo '<h1>' . sprintf(esc_html__('Uredi korisnika: %s', 'savjetnistvo'), esc_html($name)) . '</h1>';
    echo $notice;

    echo '<form method="post">';
    wp_nonce_field('savjetnistvo_update_user', 'savjetnistvo_nonce');
    echo '<h2>' . esc_html__('Osobni podaci', 'savjetnistvo') . '</h2>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>' . esc_html__('Korisničko ime', 'savjetnistvo') . '</th><td>' . esc_html($user->user_login) . '</td></tr>';
    echo '<tr><th><label for="first_name">' . esc_html__('Ime', 'savjetnistvo') . '</label></th><td><input type="text" name="first_name" id="first_name" value="' . esc_attr($user->first_name) . '"></td></tr>';
    echo '<tr><th><label for="last_name">' . esc_html__('Prezime', 'savjetnistvo') . '</label></th><td><input type="text" name="last_name" id="last_name" value="' . esc_attr($user->last_name) . '"></td></tr>';
    echo '<tr><th><label for="phone">' . esc_html__('Telefon', 'savjetnistvo') . '</label></th><td><input type="text" name="phone" id="phone" value="' . esc_attr(get_user_meta($user_id, 'wpc_phone', true)) . '"></td></tr>';
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Korisnički podaci', 'savjetnistvo') . '</h2>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label for="user_email">Email</label></th><td><input type="email" name="user_email" id="user_email" value="' . esc_attr($user->user_email) . '"></td></tr>';
    echo '<tr><th>' . esc_html__('Lozinka', 'savjetnistvo') . '</th><td>';
    submit_button(__('Pošalji email za reset lozinke', 'savjetnistvo'), 'secondary', 'savjetnistvo_action', false, ['value' => 'send_reset']);
    echo '</td></tr>';
    echo '</tbody></table>';
    submit_button(__('Spremi korisnika', 'savjetnistvo'), 'primary', 'savjetnistvo_action', false, ['value' => 'update_user']);
    echo '</form>';

    $projects = get_posts([
      'post_type'      => 'writing_project',
      'meta_key'       => 'sv_client_id',
      'meta_value'     => $user_id,
      'post_type' => 'writing_project',
      'author' => $user_id,
      'posts_per_page' => -1,
    ]);
    echo '<h2>' . esc_html__('Projekti', 'savjetnistvo') . '</h2>';
    if($projects){
      echo '<table class="widefat"><thead><tr><th>' . esc_html__('Projekt', 'savjetnistvo') . '</th><th>' . esc_html__('Program', 'savjetnistvo') . '</th><th>' . esc_html__('Popust', 'savjetnistvo') . '</th></tr></thead><tbody>';      foreach($projects as $p){
        $program  = get_post_meta($p->ID, 'sv_program', true);
        $discount = get_post_meta($p->ID, 'sv_discount', true);
        $info = '';
        if($program){
          $info .= esc_html($program);
        }
        if($discount !== ''){
          $info .= ($info ? ' - ' : '') . sprintf(__('Popust: %s%%', 'savjetnistvo'), esc_html($discount));
        }
        echo '<li>' . esc_html(get_the_title($p)) . ($info ? ' – ' . $info : '') . '</li>';
        $program  = get_post_meta($p->ID, 'sv_program', true);
        $discount = get_post_meta($p->ID, 'sv_discount', true);
        echo '<tr><td>' . esc_html(get_the_title($p)) . '</td><td>' . esc_html($program) . '</td><td>' . esc_html($discount) . '</td></tr>';      }
      echo '</tbody></table>';
    }else{
      echo '<p><em>' . esc_html__('Nema projekata.', 'savjetnistvo') . '</em></p>';
    }

    echo '<p><a href="' . esc_url(admin_url('admin.php?page=savjetnistvo-users')) . '">' . esc_html__('Natrag na korisnike', 'savjetnistvo') . '</a></p>';
    echo '</div>';
  }
}
