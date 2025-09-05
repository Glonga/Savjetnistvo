<?php
namespace Savjetnistvo\CPT;

class ProjectCPT {
  public static function init(){
    add_action('init', [__CLASS__, 'register']);
    add_action('pre_get_posts', [__CLASS__, 'limit_coach_projects']);
    add_filter('map_meta_cap', [__CLASS__, 'project_caps'], 10, 4);
  }

  public static function register(){
    $labels = [
      'name' => __('Projekti', 'savjetnistvo'),
      'singular_name' => __('Projekt', 'savjetnistvo'),
      'add_new' => __('Dodaj novi', 'savjetnistvo'),
      'add_new_item' => __('Dodaj novi projekt', 'savjetnistvo'),
      'edit_item' => __('Uredi projekt', 'savjetnistvo'),
      'new_item' => __('Novi projekt', 'savjetnistvo'),
      'view_item' => __('Pogledaj projekt', 'savjetnistvo'),
      'search_items' => __('Pretraži projekte', 'savjetnistvo'),
      'not_found' => __('Nema projekata', 'savjetnistvo'),
      'menu_name' => __('Projekti pisanja', 'savjetnistvo')
    ];

    register_post_type('writing_project', [
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'savjetnistvo',
      'menu_icon' => 'dashicons-welcome-write-blog',
      'supports' => ['title', 'editor'],
      'capability_type' => 'post',
      'show_in_rest' => false,
    ]);
  }

  public static function limit_coach_projects($query){
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'writing_project') return;
    if (current_user_can('manage_options')) return;

    $user = wp_get_current_user();
    if (!$user || !in_array('wpc_coach', (array)$user->roles, true)) return;

    // Find project IDs where this user is in sv_coach_ids (JSON array)
    $uid = (int) $user->ID;
    $ids = get_posts([
      'post_type'      => 'writing_project',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_query'     => [
        [
          'key'     => 'sv_coach_ids',
          'value'   => '"' . $uid . '"',
          'compare' => 'LIKE',
        ]
      ],
    ]);
    $ids = array_map('intval', (array) $ids);
    // If none found, ensure no results
    if (empty($ids)) $ids = [0];
    $query->set('post__in', $ids);
  }

  public static function project_caps($caps, $cap, $user_id, $args){
    if (!in_array($cap, ['edit_post','read_post','delete_post'], true)) return $caps;
    $post_id = isset($args[0]) ? (int) $args[0] : 0;
    if ($post_id <= 0) return $caps;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'writing_project') return $caps;

    if (user_can($user_id, 'manage_options')){
      return ['exist'];
    }

    // Allow only project coach; deny others (clients excluded from admin)
    require_once SAVJETNISTVO_DIR . 'src/Admin/Access.php';
    if (\Savjetnistvo\Admin\Access::is_project_coach($user_id, $post_id)){
      if ($cap === 'read_post') return ['exist'];
      if ($cap === 'edit_post') return ['edit_posts'];
      if ($cap === 'delete_post') return ['delete_posts'];
    }

    // Default: block
    return ['do_not_allow'];
  }
}
