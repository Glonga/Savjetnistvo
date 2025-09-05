<?php
namespace Savjetnistvo\Core;

class Deactivator {
  public static function deactivate(){
    // Clear cron
    $ts = wp_next_scheduled('sv_cron_hourly');
    if ($ts) wp_unschedule_event($ts, 'sv_cron_hourly');
    wp_clear_scheduled_hook('sv_cron_hourly');
    flush_rewrite_rules();
  }
}
