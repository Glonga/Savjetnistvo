<?php
namespace Savjetnistvo\REST;

class PaymentsController {
  protected static function get_project_ids_for_item(int $payment_id): int {
    if ($payment_id <= 0) return 0;
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_payments';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$table} WHERE id = %d", $payment_id));
  }

  protected static function is_admin(): bool {
    return current_user_can('manage_options');
  }

  protected static function is_coach(int $project_id, int $user_id): bool {
    $coach_id = (int) get_post_meta($project_id, 'sv_coach_id', true);
    return $user_id > 0 && $user_id === $coach_id;
  }

  protected static function is_client(int $project_id, int $user_id): bool {
    $client_ids = array_map('intval', (array) get_post_meta($project_id, 'sv_client_id', false));
    return in_array($user_id, $client_ids, true);
  }

  protected static function has_project_access(int $project_id): bool {
    if (!is_user_logged_in() || $project_id <= 0) return false;
    if (self::is_admin()) return true;
    $uid = get_current_user_id();
    return self::is_coach($project_id, $uid) || self::is_client($project_id, $uid);
  }

  protected static function can_manage_project(int $project_id): bool {
    if (!is_user_logged_in() || $project_id <= 0) return false;
    if (self::is_admin()) return true;
    $uid = get_current_user_id();
    return self::is_coach($project_id, $uid); // coach (or admin) only
  }

  public static function permission_get(\WP_REST_Request $req){
    $project_id = (int) $req->get_param('project_id');
    return self::has_project_access($project_id);
  }

  public static function permission_manage(\WP_REST_Request $req){
    $json = $req->get_json_params();
    $project_id = (int) ($json['project_id'] ?? 0);
    return self::can_manage_project($project_id);
  }

  public static function permission_item_manage(\WP_REST_Request $req){
    $id = (int) $req->get_param('id');
    $project_id = self::get_project_ids_for_item($id);
    return self::can_manage_project($project_id);
  }

  public static function get_payments(\WP_REST_Request $req){
    if (!self::permission_get($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    global $wpdb;
    $project_id = (int) $req->get_param('project_id');
    $table = $wpdb->prefix . 'wpc_payments';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE project_id=%d ORDER BY COALESCE(due_at,'9999-12-31') ASC, id ASC", $project_id));

    $items = [];
    $currency = 'EUR';
    $total = 0.0;
    $paid  = 0.0;
    foreach ((array)$rows as $r){
      $cur = $r->currency ?: $currency;
      if (empty($items)) $currency = $cur ?: $currency;
      $amount = (float) $r->amount;
      $disc   = isset($r->discount_pct) ? (float) $r->discount_pct : 0.0;
      $eff    = $amount - ($amount * $disc / 100.0);
      $total += $eff;
      if ($r->status === 'placeno') $paid += $eff;

      $items[] = [
        'id'           => (int) $r->id,
        'title'        => (string) $r->title,
        'amount'       => (float) $r->amount,
        'currency'     => (string) ($r->currency ?: 'EUR'),
        'discount_pct' => isset($r->discount_pct) ? (float) $r->discount_pct : 0.0,
        'status'       => (string) $r->status,
        'issued_at'    => $r->issued_at ? (string) $r->issued_at : null,
        'due_at'       => $r->due_at ? (string) $r->due_at : null,
        'paid_at'      => $r->paid_at ? (string) $r->paid_at : null,
        'method'       => $r->method ? (string) $r->method : null,
        'note'         => $r->note ? (string) $r->note : '',
      ];
    }
    $open = $total - $paid;

    return [
      'summary' => [
        'total'    => round($total, 2),
        'paid'     => round($paid, 2),
        'open'     => round($open, 2),
        'currency' => $currency,
      ],
      'items' => $items,
    ];
  }

  public static function create_payment(\WP_REST_Request $req){
    if (!self::permission_manage($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    $json = $req->get_json_params();
    $project_id   = (int) ($json['project_id'] ?? 0);
    $title        = sanitize_text_field($json['title'] ?? '');
    $amount       = (float) ($json['amount'] ?? 0);
    $currency     = sanitize_text_field($json['currency'] ?? 'EUR');
    $discount_pct = isset($json['discount_pct']) ? (float) $json['discount_pct'] : null;
    $issued_at    = !empty($json['issued_at']) ? sanitize_text_field($json['issued_at']) : null;
    $due_at       = !empty($json['due_at']) ? sanitize_text_field($json['due_at']) : null;
    $status       = sanitize_text_field($json['status'] ?? 'otvoreno');
    $method       = !empty($json['method']) ? sanitize_text_field($json['method']) : null;
    $note         = !empty($json['note']) ? sanitize_textarea_field($json['note']) : null;

    if ($project_id <= 0 || !$title || $amount <= 0){
      return new \WP_Error('invalid_params', __('Neispravni parametri', 'savjetnistvo'), ['status' => 400]);
    }
    $allowed_status = ['otvoreno','placeno','u_tijeku','otkazano'];
    if (!in_array($status, $allowed_status, true)) $status = 'otvoreno';

    // Validate currency code (3 letters)
    if (!preg_match('/^[A-Za-z]{3}$/', $currency)){
      return new \WP_Error('invalid_currency', __('Neispravna valuta (3 slova).', 'savjetnistvo'), ['status' => 400]);
    }
    $currency = strtoupper($currency);

    global $wpdb;
    $table = $wpdb->prefix . 'wpc_payments';
    $wpdb->insert($table, [
      'project_id'   => $project_id,
      'title'        => $title,
      'amount'       => $amount,
      'currency'     => $currency ?: 'EUR',
      'discount_pct' => $discount_pct,
      'status'       => $status,
      'issued_at'    => $issued_at,
      'due_at'       => $due_at,
      'method'       => $method,
      'note'         => $note,
      'created_by'   => get_current_user_id(),
      'created_at'   => current_time('mysql'),
      'updated_at'   => current_time('mysql'),
    ], [ '%d','%s','%f','%s','%f','%s','%s','%s','%s','%d','%s','%s' ]);

    $id = (int) $wpdb->insert_id;
    if ($id <= 0){
      return new \WP_Error('db_error', __('Greška pri stvaranju stavke', 'savjetnistvo'), ['status' => 500]);
    }
    return [ 'ok' => true, 'id' => $id ];
  }

  public static function update_payment(\WP_REST_Request $req){
    if (!self::permission_item_manage($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    $id = (int) $req->get_param('id');
    $json = $req->get_json_params();
    $allowed_status = ['otvoreno','placeno','u_tijeku','otkazano'];

    $fields = [];
    $formats = [];
    $map = [
      'title'        => '%s',
      'amount'       => '%f',
      'currency'     => '%s',
      'discount_pct' => '%f',
      'issued_at'    => '%s',
      'due_at'       => '%s',
      'status'       => '%s',
      'method'       => '%s',
      'note'         => '%s',
    ];
    foreach ($map as $key => $fmt){
      if (array_key_exists($key, $json)){
        $val = $json[$key];
        if ($key === 'status' && !in_array($val, $allowed_status, true)) continue;
        if ($key === 'title') $val = sanitize_text_field($val);
        if ($key === 'currency') {
          $val = sanitize_text_field($val);
          if (!preg_match('/^[A-Za-z]{3}$/', $val)){
            return new \WP_Error('invalid_currency', __('Neispravna valuta (3 slova).', 'savjetnistvo'), ['status' => 400]);
          }
          $val = strtoupper($val);
        }
        if ($key === 'amount') {
          $val = (float) $val;
          if ($val <= 0) {
            return new \WP_Error('invalid_amount', __('Iznos mora biti veći od nule.', 'savjetnistvo'), ['status' => 400]);
          }
        }
        if ($key === 'note') $val = sanitize_textarea_field($val);
        $fields[$key] = $val;
        $formats[] = $fmt;
      }
    }
    if (empty($fields)) return [ 'ok' => true ];

    global $wpdb;
    $table = $wpdb->prefix . 'wpc_payments';
    $fields['updated_at'] = current_time('mysql');
    $formats[] = '%s';
    $wpdb->update($table, $fields, [ 'id' => $id ], $formats, [ '%d' ]);
    return [ 'ok' => true ];
  }

  public static function mark_paid(\WP_REST_Request $req){
    if (!self::permission_item_manage($req)){
      return new \WP_Error('forbidden', __('Nedozvoljen pristup', 'savjetnistvo'), ['status' => 403]);
    }
    $id = (int) $req->get_param('id');
    $today = date('Y-m-d', current_time('timestamp'));
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_payments';
    $wpdb->update($table, [
      'status'     => 'placeno',
      'paid_at'    => $today,
      'updated_at' => current_time('mysql'),
    ], [ 'id' => $id ], [ '%s','%s','%s' ], [ '%d' ]);
    return [ 'ok' => true, 'paid_at' => $today ];
  }
}
