<?php
namespace Savjetnistvo\REST;

class Routes {
  public static function init(){
    add_action('rest_api_init', [__CLASS__, 'register']);
  }

  public static function register(){
    require_once SAVJETNISTVO_DIR . 'src/REST/MeetingsController.php';
    require_once SAVJETNISTVO_DIR . 'src/REST/PaymentsController.php';
    require_once SAVJETNISTVO_DIR . 'src/REST/FilesController.php';
    // GET /sv/v1/me
    register_rest_route('sv/v1', '/me', [
      'methods'  => 'GET',
      'permission_callback' => function(){ return is_user_logged_in(); },
      'callback' => function(\WP_REST_Request $req){
        $u = wp_get_current_user();
        return [
          'id'         => $u->ID,
          'display'    => $u->display_name,
          'email'      => $u->user_email,
          'pseudonim'  => get_user_meta($u->ID,'wpc_pseudonim',true),
          'phone'      => get_user_meta($u->ID,'wpc_phone',true),
        ];
      }
    ]);

    // GET /sv/v1/projects
    register_rest_route('sv/v1', '/projects', [
      'methods'  => 'GET',
      'permission_callback' => function(){ return is_user_logged_in(); },
      'callback' => [__CLASS__, 'get_projects'],
    ]);

    // GET /sv/v1/meetings
    register_rest_route('sv/v1', '/meetings', [
      'methods'  => 'GET',
      'args'     => [
        'project_id' => [ 'type' => 'integer', 'required' => true ],
      ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'permission'],
      'callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'get_meetings'],
    ]);

    // POST /sv/v1/meetings (create)
    register_rest_route('sv/v1', '/meetings', [
      'methods'  => 'POST',
      'permission_callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'permission_manage'],
      'callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'create_meeting'],
      'args' => [
        'project_id' => ['type'=>'integer','required'=>true],
        'meeting_at' => ['type'=>'string','required'=>true],
        'status'     => ['type'=>'string','required'=>false],
        'coach_notes'=> ['type'=>'string','required'=>false],
      ],
    ]);

    // PUT /sv/v1/meetings/{id}
    register_rest_route('sv/v1', '/meetings/(?P<id>\d+)', [
      'methods'  => 'PUT',
      'permission_callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'permission_item_manage'],
      'callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'update_meeting'],
    ]);

    // DELETE /sv/v1/meetings/{id}
    register_rest_route('sv/v1', '/meetings/(?P<id>\d+)', [
      'methods'  => 'DELETE',
      'permission_callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'permission_item_manage'],
      'callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'delete_meeting'],
    ]);

    // POST /sv/v1/meetings/{id}/client-notes
    register_rest_route('sv/v1', '/meetings/(?P<id>\d+)/client-notes', [
      'methods'  => 'POST',
      'args'     => [
        'id' => [ 'type' => 'integer', 'required' => true ],
      ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'permission_meeting'],
      'callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'post_client_notes'],
    ]);

    // POST /sv/v1/meetings/{id}/upload (form-data file)
    register_rest_route('sv/v1', '/meetings/(?P<id>\d+)/upload', [
      'methods'  => 'POST',
      'args'     => [
        'id' => [ 'type' => 'integer', 'required' => true ],
      ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'permission_upload'],
      'callback' => ['\\Savjetnistvo\\REST\\MeetingsController', 'post_upload'],
    ]);

    // GET /sv/v1/payments
    register_rest_route('sv/v1', '/payments', [
      'methods'  => 'GET',
      'args'     => [ 'project_id' => [ 'type' => 'integer', 'required' => true ] ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'permission_get'],
      'callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'get_payments'],
    ]);

    // POST /sv/v1/payments (create)
    register_rest_route('sv/v1', '/payments', [
      'methods'  => 'POST',
      'permission_callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'permission_manage'],
      'callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'create_payment'],
    ]);

    // PUT /sv/v1/payments/{id}
    register_rest_route('sv/v1', '/payments/(?P<id>\d+)', [
      'methods'  => 'PUT',
      'args'     => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'permission_item_manage'],
      'callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'update_payment'],
    ]);

    // POST /sv/v1/payments/{id}/mark-paid
    register_rest_route('sv/v1', '/payments/(?P<id>\d+)/mark-paid', [
      'methods'  => 'POST',
      'args'     => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'permission_item_manage'],
      'callback' => ['\\Savjetnistvo\\REST\\PaymentsController', 'mark_paid'],
    ]);

    // GET /sv/v1/files/{type}/{id}
    register_rest_route('sv/v1', '/files/(?P<type>client|coach)/(?P<id>\d+)', [
      'methods'  => 'GET',
      'args'     => [
        'type' => [ 'type' => 'string', 'required' => true, 'enum' => ['client','coach'] ],
        'id'   => [ 'type' => 'integer', 'required' => true ],
      ],
      'permission_callback' => ['\\Savjetnistvo\\REST\\FilesController', 'permission'],
      'callback' => ['\\Savjetnistvo\\REST\\FilesController', 'get_file'],
    ]);
  }

  public static function get_projects(\WP_REST_Request $req){
    $uid = get_current_user_id();

    $args = [
      'post_type'      => 'writing_project',
      'posts_per_page' => -1,
      'post_status'    => ['publish','draft','pending','private'],
      'meta_query'     => [
        'relation' => 'OR',
        [
          'key'   => 'sv_client_id',
          'value' => $uid,
          'compare' => '='
        ],
        [
          'key'   => 'sv_coach_id',
          'value' => $uid,
          'compare' => '='
        ],
      ],
    ];
    $q = new \WP_Query($args);
    $out = [];

    foreach($q->posts as $p){
      $client_ids = array_map('intval', get_post_meta($p->ID,'sv_client_id',false));
      $coach_id   = (int) get_post_meta($p->ID,'sv_coach_id',true);
      $role = in_array($uid, $client_ids) ? 'client' : (($uid === $coach_id) ? 'coach' : 'other');

      $out[] = [
        'id'         => $p->ID,
        'title'      => get_the_title($p),
        'excerpt'    => wp_trim_words( strip_tags($p->post_content), 30 ),
        'program'    => get_post_meta($p->ID,'sv_program',true),
        'discount'   => (float) get_post_meta($p->ID,'sv_discount',true),
        'start_date' => get_post_meta($p->ID,'sv_start_date',true),
        'end_date'   => get_post_meta($p->ID,'sv_end_date',true),
        'role'       => $role,
        'status'     => get_post_status($p),
        'edit_link'  => current_user_can('edit_post',$p->ID) ? get_edit_post_link($p->ID,'') : null,
      ];
    }
    return $out;
  }
}
