<?php
namespace Savjetnistvo\Front;

class Mailer {
  public static function send($to, $subject, $body){
    $opts = get_option('sv_settings', []);
    $fromName = isset($opts['email_from_name']) ? (string) $opts['email_from_name'] : get_bloginfo('name');
    $fromEmail = isset($opts['email_from']) ? (string) $opts['email_from'] : get_option('admin_email');
    $headers = [];
    if ($fromEmail){
      $headers[] = 'From: ' . wp_specialchars_decode($fromName, ENT_QUOTES) . ' <' . $fromEmail . '>';
    }
    return wp_mail($to, $subject, $body, $headers);
  }

  public static function tpl($key, array $vars){
    $opts = get_option('sv_settings', []);
    $subKey = 'email_tpl_' . $key . '_subject';
    $bodKey = 'email_tpl_' . $key . '_body';
    $subject = isset($opts[$subKey]) ? (string)$opts[$subKey] : self::default_subject($key);
    $body    = isset($opts[$bodKey]) ? (string)$opts[$bodKey] : self::default_body($key);
    $repl = [
      '{client_name}'      => (string)($vars['client_name'] ?? ''),
      '{project_title}'    => (string)($vars['project_title'] ?? ''),
      '{meeting_datetime}' => (string)($vars['meeting_datetime'] ?? ''),
      '{upload_deadline}'  => (string)($vars['upload_deadline'] ?? ''),
      '{portal_url}'       => (string)($vars['portal_url'] ?? ''),
    ];
    $subject = strtr($subject, $repl);
    $body    = strtr($body, $repl);
    return [ $subject, $body ];
  }

  protected static function default_subject($key){
    switch ($key){
      case 'zakazan': return __('Susret zakazan', 'savjetnistvo');
      case 'reminder': return __('Podsjetnik na susret', 'savjetnistvo');
      case 'predan': return __('Dokument je predan', 'savjetnistvo');
      default: return __('Obavijest', 'savjetnistvo');
    }
  }

  protected static function default_body($key){
    switch ($key){
      case 'zakazan':
        return __('Poštovani {client_name}, vaš susret za projekt "{project_title}" zakazan je za {meeting_datetime}. Rok za predaju je {upload_deadline}. Posjetite portal: {portal_url}', 'savjetnistvo');
      case 'reminder':
        return __('Podsjetnik: susret za projekt "{project_title}" održava se {meeting_datetime}. Rok za predaju je {upload_deadline}. Portal: {portal_url}', 'savjetnistvo');
      case 'predan':
        return __('Klijent je predao dokument za projekt "{project_title}". Pogledajte na portalu: {portal_url}', 'savjetnistvo');
      default:
        return __('Obavijest s portala', 'savjetnistvo');
    }
  }
}

