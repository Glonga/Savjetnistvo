<?php
namespace Savjetnistvo\Core;

class Activator {
  public static function activate(){
    require_once SAVJETNISTVO_DIR . 'src/Users/Roles.php';
    \Savjetnistvo\Users\Roles::add_roles();
    
    // Ako ikad dodaš DB tablice, ovdje ide dbDelta();
    // Create or update DB tables
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      project_id BIGINT(20) UNSIGNED NOT NULL,
      meeting_at DATETIME NOT NULL,
      status ENUM('zakazan','odrzan','otkazan') DEFAULT 'zakazan',
      coach_notes LONGTEXT NULL,
      client_notes LONGTEXT NULL,
      client_upload_id BIGINT(20) UNSIGNED NULL,
      client_upload_at DATETIME NULL,
      coach_attachment_id BIGINT(20) UNSIGNED NULL,
      created_by BIGINT(20) UNSIGNED NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY project_id (project_id),
      KEY meeting_at (meeting_at),
      KEY status (status)
    ) {$charset_collate};";

    dbDelta($sql);

    // Payments table
    $table2 = $wpdb->prefix . 'wpc_payments';
    $sql2 = "CREATE TABLE {$table2} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      project_id BIGINT(20) UNSIGNED NOT NULL,
      title VARCHAR(190) NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      currency CHAR(3) NOT NULL DEFAULT 'EUR',
      discount_pct DECIMAL(5,2) NULL,
      status ENUM('otvoreno','placeno','u_tijeku','otkazano') DEFAULT 'otvoreno',
      issued_at DATE NULL,
      due_at DATE NULL,
      paid_at DATE NULL,
      method VARCHAR(50) NULL,
      note TEXT NULL,
      created_by BIGINT(20) UNSIGNED NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY project_id (project_id),
      KEY status (status),
      KEY due_at (due_at)
    ) {$charset_collate};";

    dbDelta($sql2);

    // Migrations for meetings: ensure meeting_at exists and is populated
    // 1) Add meeting_at if missing
    $has_meeting_at = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'meeting_at'", DB_NAME, $table));
    if (!$has_meeting_at) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN meeting_at DATETIME NULL AFTER status");
    }
    // 2) If legacy columns exist, migrate to meeting_at when null
    $has_date = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'meeting_date'", DB_NAME, $table));
    $has_time = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'meeting_time'", DB_NAME, $table));
    if ($has_date && $has_time) {
      // Combine date and time
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
      $wpdb->query("UPDATE {$table} SET meeting_at = CONCAT(meeting_date, ' ', IFNULL(meeting_time,'00:00:00')) WHERE meeting_at IS NULL AND meeting_date IS NOT NULL");
    } elseif ($has_date) {
      // Only date, set midnight
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
      $wpdb->query("UPDATE {$table} SET meeting_at = CONCAT(meeting_date, ' 00:00:00') WHERE meeting_at IS NULL AND meeting_date IS NOT NULL");
    }

    // Schedule hourly cron if not set
    if (!wp_next_scheduled('sv_cron_hourly')){
      wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'sv_cron_hourly');
    }

    flush_rewrite_rules();
  }
}
