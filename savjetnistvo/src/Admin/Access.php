<?php
namespace Savjetnistvo\Admin;

class Access {
  public static function is_project_coach($user_id, $project_id){
    $user_id = (int) $user_id; $project_id = (int) $project_id;
    if ($user_id <= 0 || $project_id <= 0) return false;
    // sv_coach_ids stored as JSON array (e.g., [1,2,3])
    $raw = get_post_meta($project_id, 'sv_coach_ids', true);
    if (is_array($raw)) {
      $ids = array_map('intval', $raw);
    } else {
      $decoded = json_decode((string)$raw, true);
      $ids = is_array($decoded) ? array_map('intval', $decoded) : [];
    }
    return in_array($user_id, $ids, true);
  }

  public static function is_project_client($user_id, $project_id){
    $user_id = (int) $user_id; $project_id = (int) $project_id;
    if ($user_id <= 0 || $project_id <= 0) return false;
    // sv_client_id may be multiple meta values
    $client_ids = array_map('intval', (array) get_post_meta($project_id,'sv_client_id', false));
    return in_array($user_id, $client_ids, true);
  }
}

