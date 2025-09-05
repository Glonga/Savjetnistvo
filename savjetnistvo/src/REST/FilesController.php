<?php
namespace Savjetnistvo\REST;

class FilesController {
  protected static function find_meeting_by_attachment($type, $attach_id){
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    if ($type === 'client'){
      return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE client_upload_id = %d", $attach_id));
    } else {
      return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE coach_attachment_id = %d", $attach_id));
    }
  }

  public static function permission(\WP_REST_Request $req){
    if (!is_user_logged_in()) return false;
    $type = $req->get_param('type');
    $id = (int) $req->get_param('id');
    if (!in_array($type, ['client','coach'], true) || $id <= 0) return false;
    $row = self::find_meeting_by_attachment($type, $id);
    if (!$row) return false;
    $project_id = (int) $row->project_id;
    if (current_user_can('manage_options')) return true;
    require_once SAVJETNISTVO_DIR . 'src/Admin/Access.php';
    $uid = get_current_user_id();
    return (\Savjetnistvo\Admin\Access::is_project_client($uid, $project_id)
         || \Savjetnistvo\Admin\Access::is_project_coach($uid, $project_id));
  }

  public static function get_file(\WP_REST_Request $req){
    if (!self::permission($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    $attach_id = (int) $req->get_param('id');
    $file = get_attached_file($attach_id);
    if (!$file || !file_exists($file)){
      return new \WP_Error('not_found', __('Datoteka nije pronađena', 'savjetnistvo'), ['status' => 404]);
    }
    $filename = wp_basename($file);
    $ft = wp_check_filetype($file);
    $mime = $ft['type'] ?: 'application/octet-stream';

    // Send download response
    nocache_headers();
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
  }
}

