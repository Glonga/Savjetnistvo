<?php
namespace Savjetnistvo\Admin;

use WP_Post;

class MeetingsBox {
  /**
   * Renders a simple "Dodaj susret" form inside a metabox.
   *
   * @param int $project_id Writing project post ID.
   */
  public static function render_simple_form($project_id){
    // Ensure integers only in attributes
    $project_id = (int) $project_id;
    // Nonce for saving
    wp_nonce_field('sv_meeting_save','sv_meeting_nonce');
    ?>
    <div class="sv-meeting-simple-form">
      <p>
        <label for="sv_meeting_at"><strong><?php esc_html_e('Datum i vrijeme susreta','savjetnistvo'); ?></strong></label><br>
        <?php
          // If editing existing, convert MySQL to datetime-local
          $value = '';
          // Try to fetch the latest meeting for prefill (optional)
          global $wpdb;
          $table = $wpdb->prefix . 'wpc_meetings';
          $existing = $wpdb->get_var($wpdb->prepare("SELECT meeting_at FROM {$table} WHERE project_id=%d ORDER BY id DESC LIMIT 1", $project_id));
          if ($existing) {
            try {
              $tz = function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone('UTC');
              $dtObj = new \DateTime($existing, $tz);
              $value = $dtObj->format('Y-m-d\TH:i');
            } catch (\Exception $e) { $value = ''; }
          }
        ?>
        <input id="sv_meeting_at" type="datetime-local" name="meeting_at" value="<?php echo esc_attr($value); ?>" required>
      </p>

      <p>
        <label for="sv_meeting_status"><strong><?php esc_html_e('Status','savjetnistvo'); ?></strong></label><br>
        <select id="sv_meeting_status" name="status">
          <option value="zakazan"><?php esc_html_e('zakazan','savjetnistvo'); ?></option>
          <option value="odrzan"><?php esc_html_e('održan','savjetnistvo'); ?></option>
          <option value="otkazan"><?php esc_html_e('otkazan','savjetnistvo'); ?></option>
        </select>
      </p>

      <p>
        <label for="sv_coach_notes"><strong><?php esc_html_e('Bilješke savjetnika','savjetnistvo'); ?></strong></label><br>
        <textarea id="sv_coach_notes" name="coach_notes" rows="3" style="width:100%"></textarea>
      </p>

      <p>
        <label for="sv_coach_attachment"><strong><?php esc_html_e('Privitak savjetnika','savjetnistvo'); ?></strong></label><br>
        <input type="file" id="sv_coach_attachment" name="coach_attachment" />
      </p>
    </div>
    <?php
    // Existing meetings list for this project
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_meetings';
    $rows = $wpdb->get_results( $wpdb->prepare("SELECT id, meeting_at, status, client_upload_id FROM {$table} WHERE project_id = %d ORDER BY meeting_at DESC", $project_id) );
    ?>
    <div class="sv-meeting-existing-list">
      <h4><?php esc_html_e('Postojeći susreti','savjetnistvo'); ?></h4>
      <?php if (!empty($rows)): ?>
        <table class="widefat striped">
          <thead>
            <tr>
              <th><?php esc_html_e('Datum i vrijeme','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Status','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Klijent predao','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Akcije','savjetnistvo'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <?php
                    $ts = $r->meeting_at ? strtotime($r->meeting_at) : false;
                    echo esc_html($ts ? date_i18n('Y-m-d H:i', $ts) : '—');
                  ?>
                </td>
                <td><?php echo esc_html($r->status ?: ''); ?></td>
                <td><?php echo !empty($r->client_upload_id) ? esc_html__('da','savjetnistvo') : esc_html__('ne','savjetnistvo'); ?></td>
                <td>
                  <button type="button" class="button sv-meeting-edit" data-meeting-id="<?php echo (int) $r->id; ?>"><?php esc_html_e('Uredi','savjetnistvo'); ?></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <script>
          (function(){
            const btns = document.querySelectorAll('.sv-meeting-edit');
            btns.forEach(btn => {
              btn.addEventListener('click', function(e){
                e.preventDefault();
                const field = document.getElementById('sv_meeting_at');
                if (field) {
                  field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                  field.focus();
                }
              });
            });
          })();
        </script>
      <?php else: ?>
        <p><?php esc_html_e('Nema zabilježenih susreta.','savjetnistvo'); ?></p>
      <?php endif; ?>
    </div>
    <?php
  }

  /**
   * Saves the simple form submission to the custom table and handles attachment upload.
   *
   * @param int     $post_id Writing project post ID.
   * @return void
   */
  public static function save_simple_form($post_id){
    // Basic guards
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! isset($_POST['sv_meeting_nonce']) || ! wp_verify_nonce($_POST['sv_meeting_nonce'], 'sv_meeting_save') ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    // Only act if a meeting is actually being submitted
    $raw = sanitize_text_field($_POST['meeting_at'] ?? '');
    if ($raw === '') return;

    global $wpdb;
    // Prefer prefixed table (e.g., wp_wpc_meetings). Adjust if your table is unprefixed.
    $table = $wpdb->prefix . 'wpc_meetings';

    // Parse as local time and convert to MySQL DATETIME
    try {
      $tz = function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone('UTC');
      $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $raw, $tz);
      if (!$dt) return;
      $meeting_at = $dt->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
      return;
    }

    $allowed_status = ['zakazan','odrzan','otkazan'];
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'zakazan';
    if ( ! in_array($status, $allowed_status, true) ) {
      $status = 'zakazan';
    }

    $coach_notes = isset($_POST['coach_notes']) ? sanitize_textarea_field(wp_unslash($_POST['coach_notes'])) : '';

    // Optional file upload
    $attachment_id = null;
    if ( ! empty($_FILES['coach_attachment']) && ! empty($_FILES['coach_attachment']['name']) ) {
      $file = $_FILES['coach_attachment'];
      if ( isset($file['error']) && UPLOAD_ERR_OK === (int) $file['error'] ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = [ 'test_form' => false ];
        $movefile = wp_handle_upload( $file, $overrides );
        if ( $movefile && empty($movefile['error']) ) {
          $filetype = wp_check_filetype( $movefile['file'] );
          $attachment = [
            'post_mime_type' => $filetype['type'] ?? '',
            'post_title'     => sanitize_file_name( basename( $movefile['file'] ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
          ];
          $attachment_id = wp_insert_attachment( $attachment, $movefile['file'], $post_id );
          if ( ! is_wp_error($attachment_id) ) {
            $attach_data = wp_generate_attachment_metadata( $attachment_id, $movefile['file'] );
            wp_update_attachment_metadata( $attachment_id, $attach_data );
          } else {
            $attachment_id = null;
          }
        } else {
          // Upload failed; keep going without attachment
          // error_log('coach_attachment upload error: ' . (is_array($movefile) ? ($movefile['error'] ?? '') : 'unknown'));
        }
      }
    }

    // Prepare insert
    $created_by = get_current_user_id();
    $data = [
      'project_id'          => (int) $post_id,
      'meeting_at'          => $meeting_at,
      'status'              => $status,
      'coach_notes'         => $coach_notes,
      'coach_attachment_id' => $attachment_id ? (int) $attachment_id : null,
      'created_by'          => (int) $created_by,
      'created_at'          => current_time('mysql'),
      'updated_at'          => current_time('mysql'),
    ];

    $formats = [
      '%d',   // project_id
      '%s',   // meeting_at
      '%s',   // status
      '%s',   // coach_notes
      is_null($data['coach_attachment_id']) ? null : '%d', // coach_attachment_id
      '%d',   // created_by
      '%s',   // created_at
      '%s',   // updated_at
    ];

    // Remove null format entries to match data keys
    $filtered_formats = [];
    $filtered_data = [];
    $i = 0;
    foreach ($data as $key => $val) {
      $fmt = $formats[$i++];
      if ($fmt === null) {
        if (!is_null($val)) {
          // Should not happen, but keep sane
          $filtered_formats[] = '%s';
          $filtered_data[$key] = (string) $val;
        }
        continue;
      }
      $filtered_formats[] = $fmt;
      $filtered_data[$key] = $val;
    }

    $wpdb->insert( $table, $filtered_data, $filtered_formats );

    // Notify clients if enabled
    $insert_id = (int) $wpdb->insert_id;
    $settings = get_option('sv_settings', []);
    if (!empty($settings['email_toggle_zakazan'])){
      require_once SAVJETNISTVO_DIR . 'src/Front/Mailer.php';
      $tz = function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone('UTC');
      try {
        $dt = new \DateTime($meeting_at, $tz);
      } catch (\Exception $e) { $dt = null; }
      $hours = (int) ($settings['meetings_cutoff_hours'] ?? 72);
      if ($hours <= 0) $hours = 72;
      $deadline = null;
      if ($dt) { $deadline = (clone $dt)->modify('-'.$hours.' hours'); }
      $vars = [
        'project_title'    => get_the_title($post_id),
        'meeting_datetime' => $dt ? $dt->format('Y-m-d H:i') : $meeting_at,
        'upload_deadline'  => $deadline ? $deadline->format('Y-m-d H:i') : '',
        'portal_url'       => \Savjetnistvo\Core\sv_portal_url(),
      ];
      $client_ids = array_map('intval', (array) get_post_meta($post_id, 'sv_client_id', false));
      foreach ($client_ids as $cid){
        $u = get_userdata($cid);
        if (!$u) continue;
        $vars['client_name'] = $u->display_name;
        list($sub,$bod) = \Savjetnistvo\Front\Mailer::tpl('zakazan', $vars);
        \Savjetnistvo\Front\Mailer::send($u->user_email, $sub, $bod);
      }
    }
  }
}


