<?php
namespace Savjetnistvo\Admin;

class MetaboxProject {
  public static function init(){
    add_action('add_meta_boxes', [__CLASS__,'add_box']);
    add_action('save_post_writing_project', [__CLASS__,'save'], 10, 2);
  }

    public static function add_box(){
      add_meta_box(
        'sv_project_settings',
        __('Postavke projekta','savjetnistvo'),
        [__CLASS__,'render'],
        'writing_project',
        'side',
        'high'
      );

      add_meta_box(
        'sv_project_meetings',
        __('Evidencija susreta','savjetnistvo'),
        [__CLASS__,'render_meetings'],
        'writing_project',
        'normal',
        'default'
      );
    }

    public static function render($post){
      wp_nonce_field('sv_project_settings','sv_project_nonce');

    $client_ids  = array_map('intval', get_post_meta($post->ID,'sv_client_id',false));
    if(empty($client_ids)) $client_ids = [0];    $coach_id    = (int) get_post_meta($post->ID,'sv_coach_id',true);
    $start_date  = get_post_meta($post->ID,'sv_start_date',true);
    $end_date    = get_post_meta($post->ID,'sv_end_date',true);
    $program     = get_post_meta($post->ID,'sv_program',true);
    $discount    = get_post_meta($post->ID,'sv_discount',true);
    $price       = get_post_meta($post->ID,'sv_price',true);
    $work_title  = get_post_meta($post->ID,'sv_work_title',true);
    $work_type   = get_post_meta($post->ID,'sv_work_type',true);
    $synopsis    = get_post_meta($post->ID,'sv_synopsis',true);
    $work_data   = get_post_meta($post->ID,'sv_work_data',true);
    $meeting_log = get_post_meta($post->ID,'sv_meeting_log',true);
    $meeting_notes = get_post_meta($post->ID,'sv_meeting_notes',true);

    // dohvat korisnika po ulozi
    $clients = get_users(['role'=>'wpc_client','orderby'=>'display_name','number'=>-1]);
    $coaches = get_users(['role'=>'wpc_coach','orderby'=>'display_name','number'=>-1]);

    ?>
    <style>
      .sv-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
      .sv-grid label{display:block;font-weight:600;margin-bottom:4px}
      .sv-row{margin-bottom:12px}
    </style>
    <div class="sv-grid">


      <div class="sv-row" style="grid-column:1/-1">        <label><?php _e('Klijenti','savjetnistvo'); ?></label>
        <div id="sv-client-container">
          <?php foreach($client_ids as $cid): ?>
            <div class="sv-client-select">
              <select name="sv_client_id[]">
                <option value="0">—</option>
                <?php foreach($clients as $u): ?>
                  <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($cid,$u->ID); ?>>
                    <?php echo esc_html($u->display_name . ' ('.$u->user_email.')'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="sv-add-client"><?php _e('Dodaj','savjetnistvo'); ?></button>
      </div>

      <div class="sv-row">
        <label><?php _e('Savjetnik','savjetnistvo'); ?></label>
        <select name="sv_coach_id">
          <option value="0">—</option>
          <?php foreach($coaches as $u): ?>
            <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($coach_id,$u->ID); ?>>
              <?php echo esc_html($u->display_name); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="sv-row">
        <label><?php _e('Datum početka','savjetnistvo'); ?></label>
        <input type="date" name="sv_start_date" value="<?php echo esc_attr($start_date); ?>">
      </div>

      <div class="sv-row">
        <label><?php _e('Datum završetka','savjetnistvo'); ?></label>
        <input type="date" name="sv_end_date" value="<?php echo esc_attr($end_date); ?>">
      </div>

      <div class="sv-row">
        <label><?php _e('Program','savjetnistvo'); ?></label>
        <input type="text" name="sv_program" class="regular-text" value="<?php echo esc_attr($program); ?>" placeholder="npr. Paket A">
      </div>

      <div class="sv-row">
        <label><?php _e('Cijena programa','savjetnistvo'); ?></label>
        <input type="number" min="0" step="0.01" name="sv_price" value="<?php echo esc_attr($price); ?>">
      </div>


      <div class="sv-row">
        <label><?php _e('Popust (%)','savjetnistvo'); ?></label>
        <input type="number" min="0" max="100" step="0.01" name="sv_discount" value="<?php echo esc_attr($discount); ?>">
      </div>
            <div class="sv-row" style="grid-column:1/-1">
        <label><?php _e('Naziv djela','savjetnistvo'); ?></label>
        <input type="text" name="sv_work_title" class="regular-text" value="<?php echo esc_attr($work_title); ?>">
      </div>

      <div class="sv-row" style="grid-column:1/-1">
        <label><?php _e('Vrsta djela','savjetnistvo'); ?></label>
        <input type="text" name="sv_work_type" class="regular-text" value="<?php echo esc_attr($work_type); ?>">
      </div>

      <div class="sv-row" style="grid-column:1/-1">
        <label><?php _e('Sinopsis djela','savjetnistvo'); ?></label>
        <textarea name="sv_synopsis" rows="3" style="width:100%"><?php echo esc_textarea($synopsis); ?></textarea>
      </div>

      <div class="sv-row" style="grid-column:1/-1">
        <label><?php _e('Podaci o djelu','savjetnistvo'); ?></label>
        <textarea name="sv_work_data" rows="3" style="width:100%"><?php echo esc_textarea($work_data); ?></textarea>
      </div>
    </div>
        <script>
    (function(){
      const btn = document.getElementById('sv-add-client');
      if(!btn) return;
      btn.addEventListener('click', function(e){
        e.preventDefault();
        const container = document.getElementById('sv-client-container');
        const proto = container.querySelector('.sv-client-select');
        const clone = proto.cloneNode(true);
        clone.querySelector('select').value = '0';
        container.appendChild(clone);
      });
    })();
    </script>
    <?php
  }

    /**
     * Renders payments section below meetings (mini form + list)
     */
    public static function render_payments($post){
      global $wpdb;
      $table = $wpdb->prefix . 'wpc_payments';
      $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE project_id=%d ORDER BY COALESCE(due_at, '9999-12-31') ASC, id DESC", $post->ID));
      ?>
      <h3><?php esc_html_e('Plaćanja','savjetnistvo'); ?></h3>
      <p>
        <label><?php esc_html_e('Naslov','savjetnistvo'); ?></label><br>
        <input type="text" name="sv_payment_new_title" class="regular-text" />
      </p>
      <p>
        <label><?php esc_html_e('Iznos','savjetnistvo'); ?></label><br>
        <input type="number" step="0.01" min="0" name="sv_payment_new_amount" style="width:120px" />
      </p>
      <p>
        <label><?php esc_html_e('Rok plaćanja','savjetnistvo'); ?></label><br>
        <input type="date" name="sv_payment_new_due" />
      </p>
      <p>
        <label><?php esc_html_e('Status','savjetnistvo'); ?></label><br>
        <select name="sv_payment_new_status">
          <option value="otvoreno"><?php esc_html_e('otvoreno','savjetnistvo'); ?></option>
          <option value="u_tijeku"><?php esc_html_e('u tijeku','savjetnistvo'); ?></option>
          <option value="placeno"><?php esc_html_e('plaćeno','savjetnistvo'); ?></option>
          <option value="otkazano"><?php esc_html_e('otkazano','savjetnistvo'); ?></option>
        </select>
      </p>
      <p><em><?php esc_html_e('Spremi objavu za dodavanje stavke.','savjetnistvo'); ?></em></p>

      <h4><?php esc_html_e('Stavke','savjetnistvo'); ?></h4>
      <?php if (!empty($rows)): ?>
        <table class="widefat striped">
          <thead>
            <tr>
              <th><?php esc_html_e('Naslov','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Iznos','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Rok','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Status','savjetnistvo'); ?></th>
              <th><?php esc_html_e('Akcije','savjetnistvo'); ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo esc_html($r->title); ?></td>
              <td><?php echo esc_html(number_format((float)$r->amount, 2)); ?> <?php echo esc_html($r->currency ?: 'EUR'); ?></td>
              <td><?php echo esc_html($r->due_at ?: ''); ?></td>
              <td><?php echo esc_html($r->status); ?></td>
              <td>
                <?php if ($r->status !== 'placeno'): ?>
                  <button type="submit" class="button" name="sv_payment_mark_paid" value="<?php echo (int)$r->id; ?>"><?php esc_html_e('Označi kao plaćeno','savjetnistvo'); ?></button>
                <?php else: ?>
                  <em><?php esc_html_e('Plaćeno','savjetnistvo'); ?></em>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p><?php esc_html_e('Nema plaćanja.','savjetnistvo'); ?></p>
      <?php endif; ?>
      <?php
    }

    public static function render_meetings($post){
    $meetings = get_post_meta($post->ID,'sv_meetings',true);
    if(!is_array($meetings) || empty($meetings)){
      $meetings = [['date'=>'','note'=>'']];
    }
    ?>
    <style>
      .sv-meeting-row{display:flex;gap:12px;margin-bottom:12px}
      .sv-meeting-row input[type="date"]{flex:0 0 150px}
      .sv-meeting-row textarea{flex:1}
    </style>
    <div id="sv-meetings-container">
      <?php foreach($meetings as $m): ?>
        <div class="sv-meeting-row">
          <input type="date" name="sv_meeting_date[]" value="<?php echo esc_attr($m['date']); ?>">
          <textarea name="sv_meeting_note[]" rows="2" placeholder="<?php esc_attr_e('Bilješka','savjetnistvo'); ?>"><?php echo esc_textarea($m['note']); ?></textarea>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="button" id="sv-add-meeting"><?php _e('Dodaj susret','savjetnistvo'); ?></button>
    <script>
    (function(){
      const btn = document.getElementById('sv-add-meeting');
      if(!btn) return;
      btn.addEventListener('click', function(e){
        e.preventDefault();
        const container = document.getElementById('sv-meetings-container');
        const proto = container.querySelector('.sv-meeting-row');
        const clone = proto.cloneNode(true);
        clone.querySelector('input').value='';
        clone.querySelector('textarea').value='';
        container.appendChild(clone);
      });
    })();
    </script>
    <?php
    // REST-driven meetings UI
    wp_enqueue_script('sv-admin-meetings', SAVJETNISTVO_URL . 'assets/js/admin-meetings.js', [], SAVJETNISTVO_VER, true);
    wp_localize_script('sv-admin-meetings', 'SV', [
      'nonce'     => wp_create_nonce('wp_rest'),
      'rest'      => esc_url_raw( rest_url('sv/v1/') ),
      'projectId' => (int)$post->ID,
    ]);
    ?>
    <div class="sv-meetings-admin">
      <p>
        <label><strong><?php esc_html_e('Datum i vrijeme','savjetnistvo'); ?></strong></label><br>
        <input type="datetime-local" id="sv_meeting_at">
      </p>
      <p>
        <label><strong><?php esc_html_e('Status','savjetnistvo'); ?></strong></label><br>
        <select id="sv_meeting_status">
          <option value="zakazano"><?php esc_html_e('zakazano','savjetnistvo'); ?></option>
          <option value="odrzano"><?php esc_html_e('održano','savjetnistvo'); ?></option>
          <option value="otkazano"><?php esc_html_e('otkazano','savjetnistvo'); ?></option>
        </select>
      </p>
      <p>
        <label><strong><?php esc_html_e('Bilješke savjetnika','savjetnistvo'); ?></strong></label><br>
        <textarea id="sv_meeting_notes" rows="3" style="width:100%"></textarea>
      </p>
      <p><button type="button" class="button button-primary" id="sv_add_meeting"><?php esc_html_e('Dodaj susret','savjetnistvo'); ?></button></p>
      <hr>
      <div id="sv_meetings_list"></div>
    </div>
    <?php
    // Payments section directly below meetings
    self::render_payments($post);
  }

  public static function save($post_id, $post){
    if ( !isset($_POST['sv_project_nonce']) || !wp_verify_nonce($_POST['sv_project_nonce'], 'sv_project_settings') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( !current_user_can('edit_post', $post_id) ) return;

    $client_ids = isset($_POST['sv_client_id']) ? array_filter(array_map('intval', (array)$_POST['sv_client_id'])) : [];
    delete_post_meta($post_id,'sv_client_id');
    foreach($client_ids as $cid){ add_post_meta($post_id,'sv_client_id',$cid); }

    update_post_meta($post_id,'sv_coach_id',   isset($_POST['sv_coach_id']) ? (int)$_POST['sv_coach_id'] : 0);
    update_post_meta($post_id,'sv_start_date', sanitize_text_field($_POST['sv_start_date'] ?? ''));
    update_post_meta($post_id,'sv_end_date',   sanitize_text_field($_POST['sv_end_date'] ?? ''));
    update_post_meta($post_id,'sv_program',    sanitize_text_field($_POST['sv_program'] ?? ''));
    update_post_meta($post_id,'sv_price',      floatval($_POST['sv_price'] ?? 0));
    update_post_meta($post_id,'sv_discount',   floatval($_POST['sv_discount'] ?? 0));

    $dates  = $_POST['sv_meeting_date'] ?? [];
    $notes  = $_POST['sv_meeting_note'] ?? [];
    $meetings = [];
    $count = max(count($dates), count($notes));
    for($i=0; $i<$count; $i++){
      $date = sanitize_text_field($dates[$i] ?? '');
      $note = sanitize_textarea_field($notes[$i] ?? '');
      if($date || $note){
        $meetings[] = ['date'=>$date,'note'=>$note];
      }
    }
    update_post_meta($post_id,'sv_meetings',$meetings);

    // Payments handling (create new + mark paid)
    global $wpdb;
    $table = $wpdb->prefix . 'wpc_payments';

    // New payment
    $new_title  = isset($_POST['sv_payment_new_title']) ? sanitize_text_field(wp_unslash($_POST['sv_payment_new_title'])) : '';
    $new_amount = isset($_POST['sv_payment_new_amount']) ? (float) $_POST['sv_payment_new_amount'] : 0.0;
    $new_due    = isset($_POST['sv_payment_new_due']) ? sanitize_text_field(wp_unslash($_POST['sv_payment_new_due'])) : '';
    $allowed_status = ['otvoreno','placeno','u_tijeku','otkazano'];
    $new_status = isset($_POST['sv_payment_new_status']) ? sanitize_text_field(wp_unslash($_POST['sv_payment_new_status'])) : 'otvoreno';
    if (!in_array($new_status, $allowed_status, true)) $new_status = 'otvoreno';
    if ($new_title && $new_amount > 0) {
      $wpdb->insert($table, [
        'project_id' => (int)$post_id,
        'title'      => $new_title,
        'amount'     => $new_amount,
        'currency'   => 'EUR',
        'status'     => $new_status,
        'due_at'     => $new_due ?: null,
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
      ], [ '%d','%s','%f','%s','%s','%s','%d','%s','%s' ]);
    }

    // Mark paid
    if (!empty($_POST['sv_payment_mark_paid'])) {
      $pid = (int) $_POST['sv_payment_mark_paid'];
      if ($pid > 0) {
        $paid_date = date('Y-m-d', current_time('timestamp'));
        $wpdb->update($table, [
          'status'    => 'placeno',
          'paid_at'   => $paid_date,
          'updated_at'=> current_time('mysql'),
        ], [ 'id' => $pid, 'project_id' => (int)$post_id ], [ '%s','%s','%s' ], [ '%d','%d' ]);
      }
    }
  }
}
