<?php
namespace Savjetnistvo\REST;

class MeetingsController {
  protected static function has_project_access(int $project_id): bool {
    if (!is_user_logged_in()) return false;
    if ($project_id <= 0) return false;
    if (current_user_can('manage_options')) return true;
    $post = get_post($project_id);
    if (!$post || $post->post_type !== 'writing_project') return false;
    $uid = get_current_user_id();
    $client_ids = array_map('intval', (array) get_post_meta($project_id, 'sv_client_id', false));
    $coach_id   = (int) get_post_meta($project_id, 'sv_coach_id', true);
    if (in_array($uid, $client_ids, true)) return true;
    if ($uid === $coach_id) return true;
    return false;
  }

  public static function permission(\WP_REST_Request $req){
    if (!is_user_logged_in()) return false;
    $project_id = (int) ($req->get_param('project_id') ?? 0);
    return self::has_project_access($project_id);
  }

  protected static function tz(){
    if (function_exists('wp_timezone')) return \wp_timezone();
    $tz = get_option('timezone_string');
    if ($tz) return new \DateTimeZone($tz);
    $offset = (float) get_option('gmt_offset');
    $hours = (int) $offset;
    $mins = (abs($offset - $hours) > 0) ? 30 : 0;
    $sign = $offset >= 0 ? '+' : '-';
    $name = sprintf('%s%02d:%02d', $sign, abs($hours), $mins);
    return new \DateTimeZone($name);
  }

  protected static function iso(?string $mysql_datetime){
    if (!$mysql_datetime) return null;
    try {
      $dt = new \DateTime($mysql_datetime, new \DateTimeZone('UTC'));
      return $dt->format(DATE_ATOM);
    } catch (\Exception $e) {
      return null;
    }
  }

  protected static function filename_from_attachment($attach_id){
    $file = get_attached_file((int)$attach_id);
    if (!$file) return null;
    return wp_basename($file);
  }

  protected static function parse_iso_to_utc_datetime($iso){
    if (!$iso) return null;
    try {
      $dt = new \DateTimeImmutable($iso);
      $dt = $dt->setTimezone(new \DateTimeZone('UTC'));
      return $dt->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
      return null;
    }
  }

  public static function permission_manage(\WP_REST_Request $req){
    if (!is_user_logged_in()) return false;
    $project_id = (int) ($req->get_param('project_id') ?? 0);
    if ($project_id <= 0) return false;
    if (current_user_can('manage_options')) return true;
    $coach_id = (int) get_post_meta($project_id, 'sv_coach_id', true);
    $uid = get_current_user_id();
    if ($uid === $coach_id) return true;
    require_once SAVJETNISTVO_DIR . 'src/Admin/Access.php';
    return \Savjetnistvo\Admin\Access::is_project_coach($uid, $project_id);
  }

  public static function permission_item_manage(\WP_REST_Request $req){
    if (!is_user_logged_in()) return false;
    $id = (int) $req->get_param('id');
    if ($id <= 0) return false;
    global $wpdb; $table = $wpdb->prefix . 'wpc_meetings';
    $project_id = (int) $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$table} WHERE id=%d", $id));
    if ($project_id <= 0) return false;
    if (current_user_can('manage_options')) return true;
    $coach_id = (int) get_post_meta($project_id, 'sv_coach_id', true);
    $uid = get_current_user_id();
    if ($uid === $coach_id) return true;
    require_once SAVJETNISTVO_DIR . 'src/Admin/Access.php';
    return \Savjetnistvo\Admin\Access::is_project_coach($uid, $project_id);
  }

  protected static function map_row($r){
    $settings = get_option('sv_settings', []);
    $hours = (int) ($settings['meetings_cutoff_hours'] ?? 72);
    if ($hours <= 0) $hours = 72;
    $now = new \DateTime('now', new \DateTimeZone('UTC'));

    try { $meetingDt = new \DateTime((string)$r->meeting_at, new \DateTimeZone('UTC')); $deadline = (clone $meetingDt)->modify("-{$hours} hours"); }
    catch (\Exception $e) { $deadline = null; }

    $client_upload = null;
    if (!empty($r->client_upload_id)){
      $client_upload = [
        'id' => (int) $r->client_upload_id,
        'filename' => self::filename_from_attachment($r->client_upload_id),
        'submitted_at' => self::iso($r->client_upload_at),
      ];
    }
    $coach_attachment = null;
    if (!empty($r->coach_attachment_id)){
      $coach_attachment = [
        'id' => (int) $r->coach_attachment_id,
        'filename' => self::filename_from_attachment($r->coach_attachment_id),
      ];
    }
    return [
      'id' => (int) $r->id,
      'project_id' => (int) $r->project_id,
      'meeting_at' => self::iso($r->meeting_at),
      'status' => (string) $r->status,
      'coach_notes' => (string) ($r->coach_notes ?? ''),
      'client_notes' => (string) ($r->client_notes ?? ''),
      'client_upload' => $client_upload,
      'coach_attachment' => $coach_attachment,
      'can_upload' => ($deadline && $now <= $deadline),
      'upload_deadline_at' => $deadline ? $deadline->format(DATE_ATOM) : null,
    ];
  }

  public static function create_meeting(\WP_REST_Request $req){
    $project_id = (int) $req->get_param('project_id');
    if ($project_id <= 0) return new \WP_Error('invalid_project', __('Neispravan projekt', 'savjetnistvo'), ['status'=>400]);
    $meeting_iso = (string) ($req->get_param('meeting_at') ?? '');
    $meeting_sql = self::parse_iso_to_utc_datetime($meeting_iso);
    if (!$meeting_sql) return new \WP_Error('invalid_meeting_at', __('Neispravan datum/vrijeme', 'savjetnistvo'), ['status'=>400]);
    $status = sanitize_text_field($req->get_param('status') ?? 'zakazano');
    $map = ['zakazano'=>'zakazan', 'odrzano'=>'odrzan', 'otkazano'=>'otkazan'];
    if (isset($map[$status])) $status = $map[$status];
    $allowed = ['zakazan','odrzan','otkazan'];
    if (!in_array($status, $allowed, true)) $status = 'zakazan';
    $coach_notes = wp_kses_post($req->get_param('coach_notes') ?? '');

    global $wpdb; $table = $wpdb->prefix . 'wpc_meetings';
    $wpdb->insert($table, [
      'project_id' => $project_id,
      'meeting_at' => $meeting_sql,
      'status'     => $status,
      'coach_notes'=> $coach_notes,
      'created_by' => get_current_user_id(),
      'created_at' => current_time('mysql', true),
      'updated_at' => current_time('mysql', true),
    ], ['%d','%s','%s','%s','%d','%s','%s']);
    if (!$wpdb->insert_id) return new \WP_Error('db_error', __('Greška pri spremanju', 'savjetnistvo'), ['status'=>500]);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", (int)$wpdb->insert_id));
    return self::map_row($row);
  }

  public static function update_meeting(\WP_REST_Request $req){
    $id = (int) $req->get_param('id');
    if ($id <= 0) return new \WP_Error('invalid_id', __('Neispravan ID', 'savjetnistvo'), ['status'=>400]);
    global $wpdb; $table = $wpdb->prefix . 'wpc_meetings';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
    if (!$row) return new \WP_Error('not_found', __('Nije pronađeno', 'savjetnistvo'), ['status'=>404]);

    $data = [];$formats = [];
    if ($req->get_param('meeting_at') !== null){
      $meeting_sql = self::parse_iso_to_utc_datetime((string)$req->get_param('meeting_at'));
      if (!$meeting_sql) return new \WP_Error('invalid_meeting_at', __('Neispravan datum/vrijeme', 'savjetnistvo'), ['status'=>400]);
      $data['meeting_at'] = $meeting_sql; $formats[] = '%s';
    }
    if ($req->get_param('status') !== null){
      $status = sanitize_text_field($req->get_param('status'));
      $map = ['zakazano'=>'zakazan', 'odrzano'=>'odrzan', 'otkazano'=>'otkazan']; if (isset($map[$status])) $status = $map[$status];
      $allowed = ['zakazan','odrzan','otkazan']; if (!in_array($status, $allowed, true)) $status = 'zakazan';
      $data['status'] = $status; $formats[] = '%s';
    }
    if ($req->get_param('coach_notes') !== null){
      $data['coach_notes'] = wp_kses_post($req->get_param('coach_notes')); $formats[] = '%s';
    }
    if (empty($data)) return self::map_row($row);
    $data['updated_at'] = current_time('mysql', true); $formats[] = '%s';
    $wpdb->update($table, $data, ['id'=>$id], $formats, ['%d']);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
    return self::map_row($row);
  }

  public static function delete_meeting(\WP_REST_Request $req){
    $id = (int) $req->get_param('id');
    if ($id <= 0) return new \WP_Error('invalid_id', __('Neispravan ID', 'savjetnistvo'), ['status'=>400]);
    global $wpdb; $table = $wpdb->prefix . 'wpc_meetings';
    $wpdb->delete($table, ['id'=>$id], ['%d']);
    return ['deleted'=>true];
  }

  public static function get_meetings(\WP_REST_Request $req){
    if (!self::permission($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }

    global $wpdb;
    $project_id = (int) $req->get_param('project_id');
    $table = $wpdb->prefix . 'wpc_meetings';
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$table} WHERE project_id = %d ORDER BY meeting_at DESC",
      $project_id
    ));

    $settings = get_option('sv_settings', []);
    $hours = (int) ($settings['meetings_cutoff_hours'] ?? 72);
    if ($hours <= 0) $hours = 72;
    $now = new \DateTime('now', new \DateTimeZone('UTC'));

    $out = [];
    foreach ((array)$rows as $r){
      // Compute deadline
      try {
        $meetingDt = new \DateTime((string)$r->meeting_at, new \DateTimeZone('UTC'));
        $deadline = (clone $meetingDt)->modify("-{$hours} hours");
      } catch (\Exception $e) {
        $meetingDt = null; $deadline = null;
      }

      $client_upload = null;
      if (!empty($r->client_upload_id)){
        $client_upload = [
          'id' => (int) $r->client_upload_id,
          'filename' => self::filename_from_attachment($r->client_upload_id),
          'submitted_at' => self::iso($r->client_upload_at),
        ];
      }

      $coach_attachment = null;
      if (!empty($r->coach_attachment_id)){
        $coach_attachment = [
          'id' => (int) $r->coach_attachment_id,
          'filename' => self::filename_from_attachment($r->coach_attachment_id),
        ];
      }

      $out[] = [
        'id' => (int) $r->id,
        'project_id' => (int) $r->project_id,
        'meeting_at' => self::iso($r->meeting_at),
        'status' => (string) $r->status,
        'coach_notes' => (string) ($r->coach_notes ?? ''),
        'client_notes' => (string) ($r->client_notes ?? ''),
        'client_upload' => $client_upload,
        'coach_attachment' => $coach_attachment,
        'can_upload' => ($deadline && $now <= $deadline),
        'upload_deadline_at' => $deadline ? $deadline->format(DATE_ATOM) : null,
      ];
    }

    return $out;
  }

  public static function permission_meeting(\WP_REST_Request $req){
    if (!is_user_logged_in()) return false;
    $id = (int) $req->get_param('id');
    if ($id <= 0) return false;
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    $project_id = (int) $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$table} WHERE id = %d", $id));
    if ($project_id <= 0) return false;
    return self::has_project_access($project_id);
  }

  public static function post_client_notes(\WP_REST_Request $req){
    if (!self::permission_meeting($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    $id = (int) $req->get_param('id');
    $json = $req->get_json_params();
    $notes = isset($json['notes']) ? sanitize_textarea_field($json['notes']) : '';
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    $updated = $wpdb->update(
      $table,
      [
        'client_notes' => $notes,
        'updated_at'   => current_time('mysql'),
      ],
      [ 'id' => $id ],
      [ '%s', '%s' ],
      [ '%d' ]
    );

    if ($updated === false) {
      return new \WP_Error('db_error', __('Greška pri spremanju bilješki', 'savjetnistvo'), ['status' => 500]);
    }

    return [ 'ok' => true, 'message' => __('Bilješke su spremljene.', 'savjetnistvo') ];
  }

  protected static function get_meeting_project_id(int $meeting_id): int {
    if ($meeting_id <= 0) return 0;
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$table} WHERE id = %d", $meeting_id));
  }

  protected static function is_project_client(int $project_id, int $user_id): bool {
    $client_ids = array_map('intval', (array) get_post_meta($project_id, 'sv_client_id', false));
    return in_array($user_id, $client_ids, true);
  }

  public static function permission_upload(\WP_REST_Request $req){
    if (!is_user_logged_in()) return false;
    $id = (int) $req->get_param('id');
    $project_id = self::get_meeting_project_id($id);
    if ($project_id <= 0) return false;
    $uid = get_current_user_id();
    // Only project clients may upload
    return self::is_project_client($project_id, $uid);
  }

  public static function post_upload(\WP_REST_Request $req){
    if (!self::permission_upload($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    $id = (int) $req->get_param('id');

    // Load meeting data
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    if (!$row) {
      return new \WP_Error('not_found', __('Susret nije pronađen', 'savjetnistvo'), ['status' => 404]);
    }

    // Check 72h deadline (or option)
    $settings = get_option('sv_settings', []);
    $hours = (int) ($settings['meetings_cutoff_hours'] ?? 72);
    if ($hours <= 0) $hours = 72;
    $tz = new \DateTimeZone('UTC');
    try {
      $meetingDt = new \DateTime((string)$row->meeting_at, $tz);
      $deadline = (clone $meetingDt)->modify("-{$hours} hours");
    } catch (\Exception $e) {
      $deadline = null;
    }
    $now = new \DateTime('now', $tz);
    if (!$deadline || $now > $deadline) {
      return new \WP_Error('deadline_passed', __('Rok za predaju je istekao.', 'savjetnistvo'), ['status' => 422]);
    }

    // Validate uploaded file
    if (empty($_FILES) || empty($_FILES['file']) || empty($_FILES['file']['name'])) {
      return new \WP_Error('no_file', __('Datoteka nije poslana.', 'savjetnistvo'), ['status' => 400]);
    }
    $file = $_FILES['file'];
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    $max = 20 * 1024 * 1024; // 20MB
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['doc','docx','pdf','txt','rtf'];
    if (!in_array($ext, $allowed, true)) {
      return new \WP_Error('unsupported_type', __('Dopuštene su datoteke: .doc, .docx, .pdf, .txt, .rtf', 'savjetnistvo'), ['status' => 415]);
    }
    if ($size <= 0 || $size > $max) {
      return new \WP_Error('file_too_large', __('Datoteka je prevelika (max 10MB).', 'savjetnistvo'), ['status' => 415]);
    }

    // Handle upload and create attachment
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $overrides = [ 'test_form' => false ];
    $moved = wp_handle_upload($file, $overrides);
    if (!$moved || !empty($moved['error'])) {
      $msg = is_array($moved) && !empty($moved['error']) ? $moved['error'] : __('Greška pri uploadu.', 'savjetnistvo');
      return new \WP_Error('upload_error', $msg, ['status' => 500]);
    }

    $filetype = wp_check_filetype($moved['file']);
    $attachment = [
      'post_mime_type' => $filetype['type'] ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'post_title'     => sanitize_file_name(basename($moved['file'])),
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $moved['file'], (int) $row->project_id);
    if (is_wp_error($attach_id)) {
      return new \WP_Error('attach_error', __('Greška pri spremanju privitka.', 'savjetnistvo'), ['status' => 500]);
    }
    $meta = wp_generate_attachment_metadata($attach_id, $moved['file']);
    wp_update_attachment_metadata($attach_id, $meta);

    // Update meeting row
    $now_mysql = current_time('mysql');
    $wpdb->update(
      $table,
      [
        'client_upload_id' => (int) $attach_id,
        'client_upload_at' => $now_mysql,
        'updated_at'       => $now_mysql,
      ],
      [ 'id' => $id ],
      [ '%d', '%s', '%s' ],
      [ '%d' ]
    );

    // Notify coaches and admin if enabled
    $settings = get_option('sv_settings', []);
    if (!empty($settings['email_toggle_predan'])){
      require_once SAVJETNISTVO_DIR . 'src/Front/Mailer.php';
      $project_id = (int) $row->project_id;
      $vars = [
        'project_title'    => get_the_title($project_id),
        'meeting_datetime' => self::iso($row->meeting_at),
        'upload_deadline'  => '',
        'portal_url'       => \Savjetnistvo\Core\sv_portal_url(),
      ];
      list($sub,$bod) = \Savjetnistvo\Front\Mailer::tpl('predan', $vars);
      // coaches from sv_coach_ids JSON
      $raw = get_post_meta($project_id, 'sv_coach_ids', true);
      $coach_ids = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
      $coach_ids = array_map('intval', (array)$coach_ids);
      foreach ($coach_ids as $cid){
        $u = get_userdata($cid); if ($u) { \Savjetnistvo\Front\Mailer::send($u->user_email, $sub, $bod); }
      }
      $admin = get_option('admin_email');
      if ($admin) { \Savjetnistvo\Front\Mailer::send($admin, $sub, $bod); }
    }

    return [
      'ok' => true,
      'attachment_id' => (int) $attach_id,
      'submitted_at' => self::iso($now_mysql),
    ];
  }

  // Cron handler: send 96h reminders (window 72-96h before meeting)
  public static function cron_reminders(){
    $settings = get_option('sv_settings', []);
    if (empty($settings['email_toggle_reminder'])) return;
    $hours = (int) ($settings['meetings_cutoff_hours'] ?? 72);
    if ($hours <= 0) $hours = 72;
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $lower = (clone $now)->modify('+72 hours')->format('Y-m-d H:i:s');
    $upper = (clone $now)->modify('+96 hours')->format('Y-m-d H:i:s');

    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$table} WHERE meeting_at BETWEEN %s AND %s",
      $lower, $upper
    ));
    if (empty($rows)) return;
    require_once SAVJETNISTVO_DIR . 'src/Front/Mailer.php';
    foreach ($rows as $r){
      $project_id = (int) $r->project_id;
      $flag_key = 'sv_reminder_sent_' . (int)$r->id;
      if (get_post_meta($project_id, $flag_key, true)) continue;
      // Build vars
      $meeting_iso = self::iso($r->meeting_at);
      $deadline_iso = null;
      try {
        $mdt = new \DateTime((string)$r->meeting_at, new \DateTimeZone('UTC'));
        $deadline_iso = $mdt->modify('-'.$hours.' hours')->format(DATE_ATOM);
      } catch (\Exception $e) {}
      $vars_base = [
        'project_title'    => get_the_title($project_id),
        'meeting_datetime' => $meeting_iso,
        'upload_deadline'  => $deadline_iso,
        'portal_url'       => \Savjetnistvo\Core\sv_portal_url(),
      ];
      // Send to each client
      $client_ids = array_map('intval', (array) get_post_meta($project_id, 'sv_client_id', false));
      foreach ($client_ids as $cid){
        $u = get_userdata($cid); if (!$u) continue;
        $vars = $vars_base; $vars['client_name'] = $u->display_name;
        list($sub,$bod) = \Savjetnistvo\Front\Mailer::tpl('reminder', $vars);
        \Savjetnistvo\Front\Mailer::send($u->user_email, $sub, $bod);
      }
      update_post_meta($project_id, $flag_key, 1);
    }
  }
}

