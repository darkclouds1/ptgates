<?php
/**
 * Plugin Name: 6000-ptgates-admin (PTGates Admin)
 * Description: PTGates 문제은행 관리 모듈 (관리자 전용). CSV 일괄 삽입, 문제 편집/삭제 기능.
 * Version: 1.0.0
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class PTG_Admin_Plugin {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// 관리자 메뉴 추가 (관리자 페이지에서만)
		if ( is_admin() ) {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'remove_duplicate_submenu' ], 999 );
			// AJAX 요청 처리 (WordPress 헤더 출력 전에 처리)
			add_action( 'admin_init', [ $this, 'handle_ajax_request' ], 1 );
			// Seed Products (Once)
			add_action( 'init', [ $this, 'seed_products_once' ] );
            
            // Register Admin Columns (Join Date, Last Login)
            include_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';
            PTG_Admin_Settings::init_columns();
		}

		// 숏코드 등록 (프론트엔드/관리자 모두)
		add_action( 'init', [ $this, 'register_shortcode' ] );

		// 스타일 로드 (프론트엔드)
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// 관리자 페이지 스타일/스크립트 로드
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// REST API 등록
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );

		// Inline Edit AJAX
		add_action( 'wp_ajax_pt_get_question_edit_form', [ $this, 'ajax_get_question_edit_form' ] );
		add_action( 'wp_ajax_pt_update_question_inline', [ $this, 'ajax_update_question_inline' ] );

        // Excel Export
        add_action( 'wp_ajax_pt_admin_export_questions_csv', [ $this, 'ajax_export_questions_csv' ] );

		// CLI 지원 (기존 기능 유지)
		if ( php_sapi_name() === 'cli' ) {
			$this->init_cli();
		}

        // DB 스키마 점검 및 업데이트 (question_no 추가)
        add_action( 'admin_init', [ $this, 'check_and_update_db_schema' ] );
	}

    /**
     * DB 스키마 점검 및 업데이트 (question_no 컬럼 추가 및 백필)
     */
    public function check_and_update_db_schema() {
        // 옵션으로 이미 실행되었는지 확인
        if ( get_option( 'ptg_db_question_no_updated' ) ) {
            return;
        }

        global $wpdb;
        $table_name = 'ptgates_categories';

        // 테이블이 존재하는지 확인
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            return;
        }

        // 컬럼 존재 여부 확인
        $column_exists = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'question_no'" );

        if ( ! $column_exists ) {
            // 컬럼 추가
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN question_no INT NULL AFTER question_id" );
        }

        // 데이터 백필 (Backfill) - question_no가 NULL인 데이터가 있으면 실행
        $has_null = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE question_no IS NULL" );
        
        if ( $has_null > 0 ) {
            // MySQL 변수를 사용하여 그룹핑 및 순번 매기기
            $wpdb->query( "
                UPDATE $table_name t
                JOIN (
                    SELECT id, 
                        @rn := IF(@prev_year = exam_year AND @prev_sess = IFNULL(exam_session, 0) AND @prev_course = exam_course, @rn + 1, 1) AS row_number,
                        @prev_year := exam_year,
                        @prev_sess := IFNULL(exam_session, 0),
                        @prev_course := exam_course
                    FROM $table_name, (SELECT @rn:=0, @prev_year:=NULL, @prev_sess:=NULL, @prev_course:=NULL) vars
                    ORDER BY exam_year ASC, exam_session ASC, exam_course ASC, id ASC
                ) derived ON t.id = derived.id
                SET t.question_no = derived.row_number
                WHERE t.question_no IS NULL
            " );
        }

        // 작업 완료 표시
        update_option( 'ptg_db_question_no_updated', 1 );
    }

	/**
	 * AJAX 요청 처리 (WordPress 헤더 출력 전)
	 */
	public function handle_ajax_request() {
		// 관리자 권한 체크
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// AJAX 요청인지 확인
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) ) {
			$ajax_actions = array( 'get_subject_statistics', 'get_category_statistics', 'delete_exam_data', 'import_csv', 'generate_csv_from_txt', 'get_question_statistics' );
			if ( in_array( $_POST['action'], $ajax_actions, true ) ) {
				$import_file = plugin_dir_path( __FILE__ ) . 'includes/class-import.php';
				if ( file_exists( $import_file ) ) {
					global $wpdb;
					// 출력 버퍼 정리 (WordPress 헤더 제거)
					while ( ob_get_level() ) {
						ob_end_clean();
					}
					require_once $import_file;
					// class-import.php에서 exit 호출됨
					exit;
				}
			}
			
			// 멤버십 관련 AJAX 액션
			$member_actions = array( 'ptg_admin_get_member', 'ptg_admin_update_member', 'ptg_admin_get_history', 'ptg_admin_get_user_stats' );
			if ( in_array( $_POST['action'], $member_actions, true ) ) {
				$members_file = plugin_dir_path( __FILE__ ) . 'includes/class-members.php';
				if ( file_exists( $members_file ) ) {
					require_once $members_file;
					
					// 액션에 따라 메서드 호출
					if ( $_POST['action'] === 'ptg_admin_get_member' ) {
						PTG_Admin_Members::ajax_get_member();
					} elseif ( $_POST['action'] === 'ptg_admin_update_member' ) {
						PTG_Admin_Members::ajax_update_member();
					} elseif ( $_POST['action'] === 'ptg_admin_get_history' ) {
						PTG_Admin_Members::ajax_get_history();
					} elseif ( $_POST['action'] === 'ptg_admin_get_user_stats' ) {
						PTG_Admin_Members::ajax_get_user_stats();
					}
					exit;
				}
			}

            // 상품 관련 AJAX 액션
            $product_actions = array( 'ptg_admin_get_products', 'ptg_admin_save_product', 'ptg_admin_delete_product', 'ptg_admin_toggle_product_status' );
            if ( in_array( $_POST['action'], $product_actions, true ) ) {
                $products_file = plugin_dir_path( __FILE__ ) . 'includes/class-admin-products.php';
                if ( file_exists( $products_file ) ) {
                    require_once $products_file;
                    
                    if ( $_POST['action'] === 'ptg_admin_get_products' ) {
                        \PTG\Admin\Products::ajax_get_products();
                    } elseif ( $_POST['action'] === 'ptg_admin_save_product' ) {
                        \PTG\Admin\Products::ajax_save_product();
                    } elseif ( $_POST['action'] === 'ptg_admin_delete_product' ) {
                        \PTG\Admin\Products::ajax_delete_product();
                    } elseif ( $_POST['action'] === 'ptg_admin_toggle_product_status' ) {
                        \PTG\Admin\Products::ajax_toggle_product_status();
                    }
                    exit;
                }
            }
		}
	}

	/**
	 * Seed Products Data (Run once or on demand)
	 */
	public function seed_products_once() {
        // 이미 실행되었는지 확인 (옵션 키: ptg_products_seeded_v2)
        // 강제 실행을 위해 URL 파라미터 체크도 가능
        if ( get_option( 'ptg_products_seeded_v2' ) && !isset($_GET['ptg_force_seed']) ) {
            return;
        }

        global $wpdb;
        $table_name = 'ptgates_products';
        
        // 테이블 존재 확인
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
             // 테이블이 없으면 마이그레이션 실행 시도 (optional)
             return;
        }

        $features = [
            "무제한 문제 풀이 (Study & Quiz)",
            "무제한 암기카드 생성 및 학습",
            "모의고사 무제한 응시",
            "오답노트 및 학습 통계 제공",
            "광고 없는 쾌적한 학습 환경"
        ];
        $features_json = json_encode($features, JSON_UNESCAPED_UNICODE);

        $products = [
            [
                'product_code' => 'PREMIUM_1M',
                'title' => 'Premium 1개월',
                'description' => '1개월 동안 모든 프리미엄 기능을 이용할 수 있습니다.',
                'price' => 9900,
                'price_label' => '월 9,900원',
                'duration_months' => 1,
                'features_json' => $features_json,
                'featured_level' => 0,
                'sort_order' => 1,
                'is_active' => 1
            ],
            [
                'product_code' => 'PREMIUM_3M',
                'title' => 'Premium 3개월',
                'description' => '3개월 프리미엄 멤버십 할인 상품입니다.',
                'price' => 29000,
                'price_label' => '29,000원 (약 2% 할인)',
                'duration_months' => 3,
                'features_json' => $features_json,
                'featured_level' => 1, 
                'sort_order' => 2,
                'is_active' => 1
            ],
            [
                'product_code' => 'PREMIUM_6M',
                'title' => 'Premium 6개월',
                'description' => '6개월 치어업 멤버십입니다.',
                'price' => 55000,
                'price_label' => '55,000원 (약 7% 할인)',
                'duration_months' => 6,
                'features_json' => $features_json,
                'featured_level' => 0,
                'sort_order' => 3,
                'is_active' => 1
            ],
            [
                'product_code' => 'PREMIUM_12M',
                'title' => 'Premium 12개월',
                'description' => '1년 베스트 멤버십입니다.',
                'price' => 99000,
                'price_label' => '99,000원 (약 17% 할인)',
                'duration_months' => 12,
                'features_json' => $features_json,
                'featured_level' => 2,
                'sort_order' => 4,
                'is_active' => 1
            ]
        ];

        foreach ($products as $p) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE product_code = %s", $p['product_code']));
            if ($exists) {
                $wpdb->update($table_name, $p, ['id' => $exists]);
            } else {
                $wpdb->insert($table_name, $p);
            }
        }

        update_option( 'ptg_products_seeded_v2', 1 );
        
        if (isset($_GET['ptg_force_seed'])) {
             wp_die('Products Seeded Successfully! <br><a href="' . admin_url() . '">Return to Admin</a>');
        }
    }

	/**
	 * 숏코드 등록
	 */
	public function register_shortcode() {
		add_shortcode( 'ptg_admin', [ $this, 'render_shortcode' ] );
	}

	/**
	 * 스타일 로드 (프론트엔드)
	 */
	public function enqueue_assets() {
		global $post;
		// [ptg_admin] 숏코드가 있는 페이지에서만 스타일 로드
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_admin' ) ) {
			$css_path = plugin_dir_path( __FILE__ ) . 'assets/css/admin.css';
			$js_list_path  = plugin_dir_path( __FILE__ ) . 'assets/js/admin-list.js';
			$js_stats_path = plugin_dir_path( __FILE__ ) . 'assets/js/admin-stats.js';
			
			$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';
			$js_list_ver   = file_exists( $js_list_path ) ? filemtime( $js_list_path ) : '1.0.0';
			$js_stats_ver  = file_exists( $js_stats_path ) ? filemtime( $js_stats_path ) : '1.0.0';

			wp_enqueue_style(
				'ptg-admin-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
				[],
				$css_ver
			);
			
			// list 타입일 때 JavaScript 로드
			wp_enqueue_script(
				'ptg-admin-list',
				plugin_dir_url( __FILE__ ) . 'assets/js/admin-list.js',
				['jquery'],
				$js_list_ver,
				true
			);
			
			// stats 타입일 때 JavaScript 로드
			wp_enqueue_script(
				'ptg-admin-stats',
				plugin_dir_url( __FILE__ ) . 'assets/js/admin-stats.js',
				['jquery'],
				$js_stats_ver,
				true
			);
			
			// REST API URL과 nonce 전달 (upload URL 포함)
			$upload_dir = wp_upload_dir();
			$script_data = array(
				'apiUrl' => rest_url('ptg-admin/v1/'),
				'restUrl' => rest_url('ptg-admin/v1/'), // REST API 기본 URL
				'nonce' => wp_create_nonce('wp_rest'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'uploadUrl' => $upload_dir['baseurl'] // 이미지 URL 생성을 위한 upload base URL
			);
			
			wp_localize_script('ptg-admin-list', 'ptgAdmin', $script_data);
			wp_localize_script('ptg-admin-stats', 'ptgAdmin', $script_data);
		}
	}
	
	/**
	 * 관리자 페이지 스타일/스크립트 로드
	 */
	public function enqueue_admin_assets( $hook ) {
		// 문제 목록 페이지 또는 통계 페이지에서 로드
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		
		if ( strpos( $current_page, 'ptgates-admin' ) !== false ) {
			$css_path = plugin_dir_path( __FILE__ ) . 'assets/css/admin.css';
			$js_list_path  = plugin_dir_path( __FILE__ ) . 'assets/js/admin-list.js';
			$js_stats_path = plugin_dir_path( __FILE__ ) . 'assets/js/admin-stats.js';
			
			$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';
			$js_list_ver   = file_exists( $js_list_path ) ? filemtime( $js_list_path ) : '1.0.0';
			$js_stats_ver  = file_exists( $js_stats_path ) ? filemtime( $js_stats_path ) : '1.0.0';

			wp_enqueue_style(
				'ptg-admin-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
				[],
				$css_ver
			);
			
			// REST API URL과 nonce 전달 (upload URL 포함)
			$upload_dir = wp_upload_dir();
			$script_data = array(
				'apiUrl' => rest_url('ptg-admin/v1/'),
				'restUrl' => rest_url('ptg-admin/v1/'), // REST API 기본 URL
				'nonce' => wp_create_nonce('wp_rest'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'uploadUrl' => $upload_dir['baseurl'] // 이미지 URL 생성을 위한 upload base URL
			);

			if ( $current_page === 'ptgates-admin-list' ) {
				wp_enqueue_script(
					'ptg-admin-list',
					plugin_dir_url( __FILE__ ) . 'assets/js/admin-list.js',
					['jquery'],
					$js_list_ver,
					true
				);
			}
			
			if ( $current_page === 'ptgates-admin-stats' ) {
				// Vue.js 로드 (CDN)
				wp_enqueue_script( 'vue-js', 'https://unpkg.com/vue@3/dist/vue.global.js', [], '3.0.0', true );
				
				wp_enqueue_script(
					'ptg-admin-subjects',
					plugin_dir_url( __FILE__ ) . 'assets/js/admin-subjects.js',
					['jquery', 'vue-js'],
					file_exists(plugin_dir_path(__FILE__) . 'assets/js/admin-subjects.js') ? filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin-subjects.js') : '1.0.0',
					true
				);
				wp_localize_script('ptg-admin-subjects', 'ptgAdmin', $script_data);
			}
			
			wp_localize_script('ptg-admin-list', 'ptgAdmin', $script_data);
			// wp_localize_script('ptg-admin-stats', 'ptgAdmin', $script_data); // Removed old stats script
			
			// 멤버십 관리 페이지 스크립트
			if ( $current_page === 'ptgates-admin-members' ) {
				wp_enqueue_script(
					'ptg-admin-members',
					plugin_dir_url( __FILE__ ) . 'assets/js/admin-members.js',
					['jquery'],
					file_exists(plugin_dir_path(__FILE__) . 'assets/js/admin-members.js') ? filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin-members.js') : '1.0.0',
					true
				);
			}

            // 상품 관리 페이지 스크립트
            if ( $current_page === 'ptgates-admin-products' ) {
                wp_enqueue_script(
                    'ptg-admin-products',
                    plugin_dir_url( __FILE__ ) . 'assets/js/admin-products.js',
                    ['jquery'],
                    file_exists(plugin_dir_path(__FILE__) . 'assets/js/admin-products.js') ? filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin-products.js') : '1.0.0',
                    true
                );
                wp_localize_script('ptg-admin-products', 'ptgAdmin', $script_data);
            }
			
			// 문제 생성 페이지 스크립트
			if ( $current_page === 'ptgates-admin-create' ) {
				wp_enqueue_script(
					'ptg-admin-create',
					plugin_dir_url( __FILE__ ) . 'assets/js/admin-create.js',
					['jquery'],
					file_exists(plugin_dir_path(__FILE__) . 'assets/js/admin-create.js') ? filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin-create.js') : '1.0.0',
					true
				);
				wp_localize_script('ptg-admin-create', 'ptgAdmin', $script_data);
			}
		}
	}

	/**
	 * 관리자 메뉴 추가
	 */
	public function add_admin_menu() { 
		// Include Settings Class
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';

		add_menu_page(
			'PTGates 문제은행 관리',
			'PTGate 문제은행',
			'manage_options',
			'ptgates-admin',
			[ $this, 'render_import_page' ], // 기본 페이지를 "문제 일괄 등록"으로 설정
			'dashicons-clipboard',
			30
		);

		// 첫 번째 서브메뉴: 문제 일괄 등록 (기본 페이지)
		add_submenu_page(
			'ptgates-admin',
			'문제 일괄 등록',
			'문제 일괄 등록',
			'manage_options',
			'ptgates-admin-import',
			[ $this, 'render_import_page' ]
		);

		// 두 번째 서브메뉴: 문제 목록 & 편집
		add_submenu_page(
			'ptgates-admin',
			'문제 목록 & 편집',
			'문제 목록 & 편집',
			'manage_options',
			'ptgates-admin-list',
			[ $this, 'render_list_page' ]
		);

		// 세 번째 서브메뉴: 문제 등록 & 9999 (신규 등록)
		add_submenu_page(
			'ptgates-admin',
			'문제 등록 & 9999',
			'문제 등록 & 9999',
			'manage_options',
			'ptgates-admin-create',
			[ $this, 'render_create_page' ]
		);

		// 세 번째 서브메뉴: 과목 관리 (구 통계 대시보드)
		add_submenu_page(
			'ptgates-admin',
			'과목 관리',
			'과목 관리',
			'manage_options',
			'ptgates-admin-stats',
			[ $this, 'render_stats_page' ]
		);

		// 네 번째 서브메뉴: 멤버십 관리 (WP 관리자 전용)
		add_submenu_page(
			'ptgates-admin',
			'멤버십 관리',
			'멤버십 관리',
			'manage_options',
			'ptgates-admin-members',
			[ $this, 'render_members_page' ]
		);

		// 다섯 번째 서브메뉴: 상품 관리
		add_submenu_page(
			'ptgates-admin',
			'상품 관리',
			'상품 관리',
			'manage_options',
			'ptgates-admin-products',
			[ $this, 'render_products_page' ]
		);

		// 여섯 번째 서브메뉴: 설정 (Kakao, 결제 등)
		add_submenu_page(
			'ptgates-admin',
			'설정',
			'설정',
			'manage_options',
			'ptgates-admin-settings',
			[ 'PTG_Admin_Settings', 'render_page' ]
		);



		// 기본 상위 메뉴(첫 번째 하위) 중복 제거
		remove_submenu_page( 'ptgates-admin', 'ptgates-admin' );
	}

	/**
	 * WordPress가 자동으로 추가하는 기본 서브메뉴 제거 (안전망)
	 */
	public function remove_duplicate_submenu() {
		remove_submenu_page( 'ptgates-admin', 'ptgates-admin' );
	}

	/**
	 * 관리자 페이지 렌더링
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<h1>PTGates 문제은행 관리</h1>
			<p>문제은행 관리 도구에 오신 것을 환영합니다.</p>
			<ul>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-list' ); ?>">문제 목록</a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-create' ); ?>">문제 등록 & 9999</a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-import' ); ?>">CSV 일괄 삽입</a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-stats' ); ?>">과목 관리</a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-members' ); ?>">멤버십 관리</a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-products' ); ?>">상품 관리</a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * 문제 목록 페이지 렌더링
	 */
	public function render_list_page() {
		// ptGates 관리자 권한 확인
		if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
			wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
		}

		echo '<div class="wrap">';
		$this->render_question_list();
		echo '</div>';
	}

	/**
	 * 문제 생성 페이지 렌더링 (문제 등록 & 9999)
	 */
	public function render_create_page() {
		// ptGates 관리자 권한 확인
		if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
			wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
		}

		?>
		<div class="wrap">
			<div class="ptg-admin-create-container">
				<div class="ptg-create-header">
					<h2><span class="dashicons dashicons-edit"></span> 문제 등록 & 9999</h2>
					<div class="ptg-create-meta">
						<span class="ptg-meta-badge"><?php echo date('Y'); ?>년</span>
						<span class="ptg-meta-badge">9999회차</span>
					</div>
				</div>

				<form id="ptg-create-question-form" class="ptg-form">
					<input type="hidden" name="exam_year" value="<?php echo date('Y'); ?>" />
					<input type="hidden" name="exam_session" value="9999" />

					<div class="ptg-form-row">
						<div class="ptg-form-group">
							<select id="ptg-create-subject" name="subject" required>
								<option value="">과목 선택</option>
								<!-- JS로 로드됨 -->
							</select>
						</div>
						<div class="ptg-form-group">
							<select id="ptg-create-subsubject" name="subsubject" required>
								<option value="">세부과목 선택</option>
								<!-- JS로 로드됨 -->
							</select>
						</div>
					</div>

					<div class="ptg-form-group">
						<label for="ptg-create-content">지문 (content)</label>
						<textarea id="ptg-create-content" name="content" rows="10"></textarea>
					</div>

					<div class="ptg-form-group">
						<label for="ptg-create-answer">정답 (answer)</label>
						<input type="text" id="ptg-create-answer" name="answer" />
					</div>

					<div class="ptg-form-group">
						<label for="ptg-create-explanation">해설 (explanation)</label>
						<textarea id="ptg-create-explanation" name="explanation" rows="10"></textarea>
					</div>
					
					<div class="ptg-form-group">
						<label>이미지 (Image)</label>
						<input type="file" name="question_image" id="ptg-create-image-input" accept="image/*" />
						<div id="ptg-create-image-preview" style="margin-top: 10px; display: none; max-width: 500px; max-height: 500px;">
							<div style="max-width: 500px; max-height: 500px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
								<img id="ptg-create-image-preview-img" src="" alt="미리보기" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;" />
							</div>
							<p id="ptg-create-image-info" style="margin-top: 5px; font-size: 12px; color: #666;"></p>
						</div>
					</div>

					<div class="ptg-form-row">
						<div class="ptg-form-group">
							<label for="ptg-create-difficulty">난이도</label>
							<select id="ptg-create-difficulty" name="difficulty">
								<option value="1">1 (하)</option>
								<option value="2" selected>2 (중)</option>
								<option value="3">3 (상)</option>
							</select>
						</div>
						<div class="ptg-form-group checkbox-group">
							<label>
								<input type="checkbox" name="is_active" value="1" checked /> 활성화
							</label>
						</div>
					</div>

					<div class="ptg-form-actions">
						<button type="submit" class="button button-primary button-large">문제 등록</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

    /**
     * 상품 관리 페이지 렌더링
     */
    public function render_products_page() {
        // ptGates 관리자 권한 확인
        if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
            wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
        }

        $products_file = plugin_dir_path( __FILE__ ) . 'includes/class-admin-products.php';
        if ( file_exists( $products_file ) ) {
            require_once $products_file;
            \PTG\Admin\Products::render_page();
        } else {
            echo '<div class="wrap"><h1>오류</h1><p>상품 관리 파일을 찾을 수 없습니다.</p></div>';
        }
    }

    /**
     * 카카오 로그인 설정 페이지 렌더링
     */
    public function render_kakao_settings_page() {
        // ptGates 관리자 권한 확인
        if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
            wp_die( 'ptGates 관리자 권한이 필요합니다.' );
        }

        $kakao_file = plugin_dir_path( __FILE__ ) . 'includes/class-admin-kakao.php';
        if ( file_exists( $kakao_file ) ) {
            require_once $kakao_file;
            PTG_Admin_Kakao::render_page();
        } else {
            echo '<div class="wrap"><h1>오류</h1><p>카카오 로그인 설정 파일을 찾을 수 없습니다.</p></div>';
        }
    }

	/**
	 * 과목 관리 페이지 렌더링 (구 통계 페이지)
	 */
	public function render_stats_page() {
		// ptGates 관리자 권한 확인
		if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
			wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
		}

		?>
		<div class="wrap">
			<h1>📚 과목 관리 시스템</h1>
			<div id="ptg-subject-manager-app">
				<div class="ptg-loading">
					<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> 로딩 중...
				</div>
			</div>
			
			<!-- Vue Template (Inline or loaded via JS) -->
			<!-- We will use JS render function or template string in JS for simplicity, 
			     but here is a basic structure for styling if needed -->
			<style>
				.ptg-course-container { display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap; }
				.ptg-course-column { flex: 1 1 calc(50% - 10px); background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); min-width: 320px; box-sizing: border-box; }
				.ptg-course-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #f0f0f1; padding-bottom: 10px; }
				.ptg-subject-list { list-style: none; padding: 0; margin: 0; }
				.ptg-subject-item { background: #f9f9f9; border: 1px solid #e5e5e5; margin-bottom: 10px; padding: 10px; display: flex; justify-content: space-between; align-items: center; cursor: move; }
				.ptg-subject-item:hover { background: #f0f0f1; border-color: #999; }
				.ptg-subject-info { flex-grow: 1; }
				.ptg-subject-meta { font-size: 0.85em; color: #666; }
				.ptg-subject-actions { display: flex; gap: 5px; }
				.ptg-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-right: 5px; }
				.ptg-badge-category { background: #e5e5e5; color: #333; }
				.ptg-badge-count { background: #2271b1; color: #fff; }
				.ptg-total-warning { color: #d63638; font-weight: bold; }
				.ptg-total-ok { color: #00a32a; font-weight: bold; }
				
				/* Modal Styles */
				.ptg-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; justify-content: center; align-items: center; }
				.ptg-modal { background: #fff; width: 500px; max-width: 90%; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-radius: 4px; }
				.ptg-modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
				.ptg-modal-footer { margin-top: 20px; text-align: right; }
				.ptg-form-group { margin-bottom: 15px; }
				.ptg-form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
				.ptg-form-group input, .ptg-form-group select { width: 100%; }
				
				.ptg-message { position: fixed; top: 32px; right: 20px; padding: 10px 20px; background: #fff; border-left: 4px solid #00a32a; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10000; animation: slideIn 0.3s; }
				.ptg-message.error { border-left-color: #d63638; }
				@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

                /* Category Card Styles - Simplified */
                .ptg-category-card { margin-bottom: 20px; }
                .ptg-category-title { margin: 0 0 10px 0; font-size: 1.1em; font-weight: 600; color: #2c3338; padding-left: 5px; }
                
                /* Subject Item - Single Line */
                .ptg-subject-list { list-style: none; padding: 0; margin: 0; }
                .ptg-subject-item { 
                    display: flex; 
                    align-items: center; 
                    padding: 5px 10px; 
                    margin-bottom: 0; 
                    border-bottom: 1px solid #f0f0f1; /* Minimal separator */
                }
                .ptg-subject-item:last-child { border-bottom: none; }
                .ptg-subject-item:hover { background-color: #f6f7f7; }
                
                .ptg-subject-info { flex-grow: 1; display: flex; align-items: center; gap: 10px; }
                .ptg-subject-name { font-weight: 500; min-width: 150px; font-size: 14px; color: #1d2327; }
                .ptg-subject-meta { display: flex; align-items: center; gap: 10px; color: #1d2327; font-size: 14px; font-weight: 500; }
                .ptg-subject-code { color: #1d2327; font-family: inherit; font-size: 14px; font-weight: 500; }
                
                .ptg-subject-actions .button { font-size: 14px; font-weight: 500; }
                
                .ptg-subject-actions { display: flex; gap: 5px; opacity: 0.5; transition: opacity 0.2s; }
                .ptg-subject-item:hover .ptg-subject-actions { opacity: 1; }
			</style>
			
			<script type="text/x-template" id="ptg-subject-manager-template">
					<div class="ptg-app">
						<div v-if="message.text" :class="['ptg-message', message.type]">{{ message.text }}</div>
						
                        <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
                            <a href="#" :class="['nav-tab', currentTab === 'manage' ? 'nav-tab-active' : '']" @click.prevent="currentTab = 'manage'">과목 관리</a>
                            <a href="#" :class="['nav-tab', currentTab === 'mapping' ? 'nav-tab-active' : '']" @click.prevent="currentTab = 'mapping'">과목 매핑</a>
                            <a href="#" :class="['nav-tab', currentTab === 'tools' ? 'nav-tab-active' : '']" @click.prevent="currentTab = 'tools'">코드 맵핑</a>
                        </h2>

                        <div v-if="loading" class="ptg-loading">
                            <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> 데이터 로딩 중...
                        </div>

                        <div v-else-if="currentTab === 'tools'">
                            <div class="card" style="max-width: 600px; margin-top: 20px;">
                                <h2>코드 맵핑 업데이트 (Backfill)</h2>
                                <p><code>ptgates_categories</code> 테이블의 레코드 중 코드(<code>subject_category_code</code>, <code>subject_code</code>)가 없는 항목을 업데이트합니다.</p>
                                <p><code>ptgates_subject_config</code> 설정 테이블의 코드 값을 참조하여 자동으로 매핑합니다.</p>
                                
                                <div style="margin-top: 15px;">
                                    <button class="button button-primary" @click="runBackfill" :disabled="backfill.loading">
                                        {{ backfill.loading ? '처리 중...' : '업데이트 실행' }}
                                    </button>
                                </div>
                                
                                <div v-if="backfill.result" :style="{ marginTop: '15px', padding: '10px', background: backfill.result.success ? '#f0f0f1' : '#fbeaea', border: '1px solid #ccd0d4' }">
                                    <p><strong>결과:</strong> {{ backfill.result.message }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="currentTab === 'manage'">
                            <div v-if="courses.length === 0" class="ptg-empty-state" style="text-align: center; padding: 50px;">
                                <p>등록된 과목 설정이 없습니다.</p>
                                <button class="button button-primary button-hero" @click="initializeDefaults">기본 설정 초기화 (1, 2, 3교시)</button>
                            </div>

                            <div v-else class="ptg-course-container">
                                <div v-for="course in courses" :key="course.id" class="ptg-course-column">
                                    <div class="ptg-course-header">
                                        <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                                            <h2 style="margin:0;">{{ course.exam_course }}</h2>
                                            <div class="ptg-course-config" style="display:flex; align-items:center; gap:10px;">
                                                <span :class="totalQuestionsByCourse[course.exam_course] == course.total_questions ? 'ptg-total-ok' : 'ptg-total-warning'">
                                                    {{ totalQuestionsByCourse[course.exam_course] }}
                                                </span> / 
                                                <input type="number" v-model="course.total_questions" @change="updateCourseTotal(course)" style="width: 50px; padding: 0 5px;" />
                                                <button class="button button-small" @click="openModal('create', null, course.exam_course)">+ 추가</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Category Loop -->
                                    <div v-for="(category, catIndex) in subjectsByCourseAndCategory[course.exam_course]" :key="category.name" class="ptg-category-card">
                                        <h3 class="ptg-category-title">{{ catIndex + 1 }}) {{ category.name }} ({{ category.total }})</h3>
                                        <ul class="ptg-subject-list" @dragover.prevent @drop="drop($event, index, course.exam_course, category.name)">
                                            <li v-for="(subject, index) in category.subjects" 
                                                :key="subject.config_id" 
                                                class="ptg-subject-item"
                                                draggable="true"
                                                @dragstart="dragStart($event, index, course.exam_course, category.name)"
                                                @drop="drop($event, index, course.exam_course, category.name)"
                                                @dragover.prevent>
                                                
                                                <div class="ptg-subject-info">
                                                    <span class="ptg-subject-name">{{ subject.subject }}</span>
                                                    <div class="ptg-subject-meta">
                                                        <span>{{ subject.question_count }}문항</span>
                                                        <span class="ptg-subject-code">{{ subject.subject_code }}</span>
                                                    </div>
                                                </div>
                                                <div class="ptg-subject-actions">
                                                    <button class="button button-small" @click="openModal('edit', subject, course.exam_course)">수정</button>
                                                    <button class="button button-small button-link-delete" @click="deleteSubject(subject.config_id)">삭제</button>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="currentTab === 'mapping'">
                            <div class="ptg-mapping-container">
                                <p class="description">
                                    문제 데이터(ptgates_categories)에서 발견된 과목명을 정식 과목(ptgates_subject_config)으로 매핑하여 데이터를 정규화합니다.
                                </p>
                                <table class="widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>발견된 과목명 (문제 수)</th>
                                            <th>정식 과목명 선택</th>
                                            <th>적용</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="raw in rawSubjects" :key="raw.subject">
                                            <td>
                                                <strong>{{ raw.subject }}</strong> 
                                                <span class="count" style="cursor: pointer; color: #2271b1; text-decoration: underline;" @click="openQuestionIdsModal(raw)">({{ raw.count }}문제)</span>
                                            </td>
                                            <td>
                                                <select v-model="raw.selectedConfigId" style="width: 100%; max-width: 300px;">
                                                    <option value="">🔽 정식 과목 선택</option>
                                                    <option v-for="official in officialSubjectsList" :key="official.config_id" :value="official.config_id">
                                                        {{ official.subject }} ({{ official.subject_category }})
                                                    </option>
                                                </select>
                                            </td>
                                            <td>
                                                <button class="button button-primary" @click="saveMapping(raw)" :disabled="!raw.selectedConfigId">저장</button>
                                            </td>
                                        </tr>
                                        <tr v-if="rawSubjects.length === 0">
                                            <td colspan="3">매핑할 원시 과목 데이터가 없습니다.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

					<!-- Modal -->
					<div v-if="showModal" class="ptg-modal-overlay" @click.self="closeModal">
						<div class="ptg-modal">
							<div class="ptg-modal-header">
								<h3>{{ modalMode === 'create' ? '과목 추가' : '과목 수정' }}</h3>
								<button type="button" class="button-link" @click="closeModal">×</button>
							</div>
							<div class="ptg-modal-body">
								<div class="ptg-form-group">
									<label>교시</label>
									<input type="text" v-model="currentSubject.exam_course" readonly />
								</div>
								<div class="ptg-form-group">
									<label>대분류 (Category)</label>
									<input type="text" v-model="currentSubject.subject_category" list="category-list" placeholder="예: 물리치료 기초" />
									<datalist id="category-list">
										<option v-for="cat in categories" :value="cat.subject_category"></option>
									</datalist>
								</div>
								<div class="ptg-form-group">
									<label>세부과목명 (Subject)</label>
									<input type="text" v-model="currentSubject.subject" placeholder="예: 해부생리학" />
								</div>
								<div class="ptg-form-group">
									<label>과목 코드 (Subject Code)</label>
									<input type="text" v-model="currentSubject.subject_code" placeholder="예: PT_BASE_ANAT" />
								</div>
								<div class="ptg-form-group">
									<label>문항 수</label>
									<input type="number" v-model="currentSubject.question_count" min="0" />
								</div>
								<div class="ptg-form-group">
									<label>정렬 순서</label>
									<input type="number" v-model="currentSubject.sort_order" />
								</div>
							</div>
							<div class="ptg-modal-footer">
								<button class="button button-primary" @click="saveSubject" :disabled="saving">{{ saving ? '저장 중...' : '저장' }}</button>
								<button class="button" @click="closeModal">취소</button>
							</div>
						</div>
					</div>

                    <!-- Question IDs Modal -->
                    <div v-if="questionIdsModal.visible" class="ptg-modal-overlay" @click.self="closeQuestionIdsModal">
                        <div class="ptg-modal" style="width: 600px;">
                            <div class="ptg-modal-header">
                                <h3>{{ questionIdsModal.title }} - 문제 ID 목록</h3>
                                <button type="button" class="button-link" @click="closeQuestionIdsModal">×</button>
                            </div>
                            <div class="ptg-modal-body" style="max-height: 400px; overflow-y: auto;">
                                <div v-if="questionIdsModal.ids.length > 0" style="display: flex; flex-wrap: wrap; gap: 5px;">
                                    <span v-for="id in questionIdsModal.ids" :key="id" 
                                          style="background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 13px; border: 1px solid #c3c4c7;">
                                        {{ id }}
                                    </span>
                                </div>
                                <p v-else>문제 ID 정보가 없습니다.</p>
                            </div>
                            <div class="ptg-modal-footer">
                                <button class="button" @click="closeQuestionIdsModal">닫기</button>
                            </div>
                        </div>
                    </div>
				</div>
			</script>
		</div>
		<?php
	}

	/**
	 * CSV 일괄 삽입 페이지 렌더링
	 */
	public function render_import_page() {
		// ptGates 관리자 권한 확인
		if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
			wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
		}

		// 기존 import_question.php의 웹 인터페이스 부분 사용
		$import_file = plugin_dir_path( __FILE__ ) . 'includes/class-import.php';
		if ( file_exists( $import_file ) ) {
			// WordPress 환경에서 실행되도록 설정
			global $wpdb;
			
			// WordPress 관리자 페이지 헤더 출력
			echo '<div class="wrap">';
			
			// 기존 웹 인터페이스 출력 (class-import.php가 직접 HTML 출력)
			require_once $import_file;
			
			// WordPress 관리자 페이지 푸터는 자동으로 출력됨
			echo '</div>';
		} else {
			echo '<div class="wrap"><h1>오류</h1><p>import 파일을 찾을 수 없습니다.</p></div>';
		}
	}

	/**
	 * REST API 초기화
	 */
	public function init_rest_api() {
		$rest_api_file = plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
		if ( file_exists( $rest_api_file ) && is_readable( $rest_api_file ) ) {
			require_once $rest_api_file;
			if ( class_exists( '\PTG\Admin\API' ) ) {
				\PTG\Admin\API::register_routes();
			}
		}
	}

	/**
	 * 숏코드 렌더링
	 * 
	 * 사용법: [ptg_admin type="import"]
	 * 
	 * 옵션:
	 * - type: 'import' (CSV 일괄 삽입, 기본값), 'list' (문제 목록), 'stats' (통계)
	 */
	public function render_shortcode( $atts ) {
		// 관리자 권한 체크
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<div class="ptg-admin-error"><p>⚠️ 관리자 권한이 필요합니다.</p></div>';
		}

		$atts = shortcode_atts(
			[
				'type' => 'import', // import, list, stats
			],
			$atts,
			'ptg_admin'
		);

		$type = sanitize_text_field( $atts['type'] );

		ob_start();

		switch ( $type ) {
			case 'import':
				$this->render_import_interface();
				break;
			case 'list':
				$this->render_question_list();
				break;
			case 'stats':
				$this->render_statistics();
				break;
			default:
				echo '<div class="ptg-admin-error"><p>⚠️ 알 수 없는 타입입니다: ' . esc_html( $type ) . '</p></div>';
		}

		return ob_get_clean();
	}

	/**
	 * CSV 일괄 삽입 인터페이스 렌더링
	 */
	private function render_import_interface() {
		$import_file = plugin_dir_path( __FILE__ ) . 'includes/class-import.php';
		if ( file_exists( $import_file ) ) {
			global $wpdb;
			require_once $import_file;
		} else {
			echo '<div class="ptg-admin-error"><p>⚠️ import 파일을 찾을 수 없습니다.</p></div>';
		}
	}

	/**
	 * 문제 목록 렌더링
	 */
	private function render_question_list() {
		?>
		<div class="ptg-admin-list-container">
			<div class="ptg-admin-list-header">
				<h2>📋 문제 목록</h2>
				
				<!-- 검색 바 -->
				<div class="ptg-admin-search-box">
					<input type="number" id="ptg-search-id" placeholder="ID" style="width: 80px; margin-right: 5px;" />
					<input type="text" id="ptg-search-input" placeholder="지문 또는 해설 검색..." />
					<button id="ptg-search-btn">🔍 검색</button>
					<button id="ptg-clear-search">초기화</button>
                    <button id="ptg-export-excel-btn" class="button button-primary" style="margin-left: 10px;">📥 엑셀 다운로드</button>
				</div>
				
				<!-- 필터 -->
				<div class="ptg-admin-filter-box">
					<select id="ptg-year-filter">
						<option value="">년도</option>
					</select>
					<select id="ptg-exam-session-filter">
						<option value="">회차</option>
					</select>
					<select id="ptg-session-filter">
						<option value="">교시</option>
					</select>
					<select id="ptg-subject-filter">
						<option value="">과목</option>
					</select>
					<select id="ptg-subsubject-filter">
						<option value="">세부과목</option>
					</select>
					<span id="ptg-result-count" class="ptg-result-count" style="display: none;"></span>
				</div>
			</div>
			
			<!-- 문제 목록 영역 -->
			<div id="ptg-questions-list" class="ptg-questions-list">
				<p class="ptg-loading">로딩 중...</p>
			</div>
			
			<!-- 페이지네이션 -->
			<div id="ptg-pagination" class="ptg-pagination"></div>
			
			<!-- 편집 모달 -->
			<div id="pt-admin-question-edit-modal" class="ptg-edit-modal" style="display: none;">
				<div class="ptg-edit-modal-content">
					<div class="ptg-edit-modal-header">
						<h3>문제 편집</h3>
						<button class="pt-admin-modal-close">×</button>
					</div>
					<div class="ptg-edit-modal-body">
						<input type="hidden" id="ptg-edit-question-id" />
						
						<div class="ptg-edit-field">
							<label>지문 (content):</label>
							<textarea id="ptg-edit-content" rows="15" style="width: 100%;"></textarea>
						</div>
						
						<div class="ptg-edit-field">
							<label>정답 (answer):</label>
							<input type="text" id="ptg-edit-answer" style="width: 100%;" />
						</div>
						
						<div class="ptg-edit-field">
							<label>해설 (explanation):</label>
							<textarea id="ptg-edit-explanation" rows="15" style="width: 100%;"></textarea>
						</div>
						
						<div class="ptg-edit-field">
							<label>난이도 (difficulty):</label>
							<select id="ptg-edit-difficulty" style="width: 100%;">
								<option value="1">1 (하)</option>
								<option value="2">2 (중)</option>
								<option value="3">3 (상)</option>
							</select>
						</div>
						
						<div class="ptg-edit-field">
							<label>
								<input type="checkbox" id="ptg-edit-is-active" /> 활성화
							</label>
						</div>
					</div>
					<div class="ptg-edit-modal-footer">
						<button id="pt-admin-save-question" class="ptg-btn-primary">저장</button>
						<button id="pt-admin-cancel-btn" class="ptg-btn-secondary">취소</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 통계 렌더링
	 */
	private function render_statistics() {
		?>
		<div class="ptg-admin-stats-container">
			<h2>📊 문제은행 학습현황</h2>
			
			<!-- 요약 카드 -->
			<div class="ptg-stats-summary">
				<div class="ptg-stat-card">
					<h3>총 문제 수</h3>
					<div id="ptg-total-count" class="ptg-stat-value">-</div>
				</div>
				<div class="ptg-stat-card">
					<h3>최근 업데이트</h3>
					<div id="ptg-last-update" class="ptg-stat-value small">-</div>
				</div>
			</div>
			
			<div class="ptg-stats-grid">
				<!-- 회차별 현황 -->
				<div class="ptg-stats-section">
					<h3>📅 회차별 현황</h3>
					<div class="ptg-stats-table-container">
						<table class="ptg-stats-table" id="ptg-exam-stats-table">
							<thead>
								<tr>
									<th>년도</th>
									<th>회차</th>
									<th>교시</th>
									<th>문항 수</th>
									<th>생성일</th>
								</tr>
							</thead>
							<tbody>
								<tr><td colspan="5" class="loading">로딩 중...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
				
				<!-- 과목별 분포 -->
				<div class="ptg-stats-section">
					<h3>📚 과목별 분포</h3>
					<div class="ptg-stats-controls">
						<select id="ptg-stats-year">
							<option value="">년도 선택</option>
						</select>
						<select id="ptg-stats-course">
							<option value="1교시">1교시</option>
							<option value="2교시">2교시</option>
						</select>
						<button id="ptg-stats-refresh" class="ptg-btn-small">조회</button>
					</div>
					<div id="ptg-subject-chart" class="ptg-chart-container">
						<p class="ptg-chart-placeholder">년도와 교시를 선택하여 조회하세요.</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 멤버십 관리 페이지 렌더링
	 */
	public function render_members_page() {
		$members_file = plugin_dir_path( __FILE__ ) . 'includes/class-members.php';
		if ( file_exists( $members_file ) ) {
			require_once $members_file;
			PTG_Admin_Members::render_page();
		} else {
			echo '<div class="error"><p>멤버십 관리 클래스 파일을 찾을 수 없습니다.</p></div>';
		}
	}


	private function init_cli() {
		$import_file = plugin_dir_path( __FILE__ ) . 'includes/class-import.php';
		if ( file_exists( $import_file ) ) {
			// CLI 환경에서는 직접 실행
			// class-import.php가 CLI 모드일 때 자체적으로 실행됨
		}
	}

	/**
	 * AJAX: 문제 편집 폼 가져오기 (Inline)
	 */
	public function ajax_get_question_edit_form() {
		check_ajax_referer( 'wp_rest', 'security' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( '권한이 없습니다.' );
		}

		$question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
		if ( ! $question_id ) {
			wp_send_json_error( '잘못된 문제 ID입니다.' );
		}

		global $wpdb;
		// 테이블 이름은 prefix 없이 사용 (다른 플러그인과 일관성 유지)
		$table_name = 'ptgates_questions';
		$question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE question_id = %d", $question_id ) );

		if ( ! $question ) {
			wp_send_json_error( '문제를 찾을 수 없습니다.' );
		}

		// 카테고리 정보 조회 (과목/세부과목)
		$cat_info = $wpdb->get_row( $wpdb->prepare( "SELECT subject, exam_year, exam_session FROM ptgates_categories WHERE question_id = %d LIMIT 1", $question_id ) );
		$current_subject = $cat_info ? $cat_info->subject : '';
		// Note: 현재 DB 구조상 subject 컬럼에 세부과목이 저장되고 있음. 대분류 과목은 별도로 저장되지 않거나 로직으로 처리됨.
		// 하지만 여기서는 사용자가 '과목'과 '세부과목'을 선택할 수 있게 해야 함.
		// 기존 데이터가 세부과목만 있다면, 대분류를 역추적해야 함.
		
		// Subjects 클래스 로드 (최초 로드는 0000-ptgates-platform에서 수행됨)
		if ( ! class_exists( '\PTG\Quiz\Subjects' ) ) {
			// 플랫폼 코어를 먼저 시도
			$platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
			if ( file_exists( $platform_subjects_file ) && is_readable( $platform_subjects_file ) ) {
				require_once $platform_subjects_file;
			}
			// 플랫폼 코어가 없으면 기존 위치에서 로드 (호환성)
			if ( ! class_exists( '\PTG\Quiz\Subjects' ) ) {
				$subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
				if ( file_exists( $subjects_file ) && is_readable( $subjects_file ) ) {
					require_once $subjects_file;
				}
			}
		}

		$main_subject = '';
		$sub_subject = $current_subject;

		if ( class_exists( '\PTG\Quiz\Subjects' ) ) {
			// 세부과목으로 대분류 찾기 (역추적)
			// 모든 세션(교시)을 뒤져서 해당 세부과목을 포함하는 대분류를 찾음
			$sessions = \PTG\Quiz\Subjects::get_sessions();
			foreach ( $sessions as $sess ) {
				$subjects = \PTG\Quiz\Subjects::get_subjects_for_session( $sess );
				foreach ( $subjects as $subj ) {
					$subs = \PTG\Quiz\Subjects::get_subsubjects( $sess, $subj );
					if ( in_array( $sub_subject, $subs ) ) {
						$main_subject = $subj;
						break 2;
					}
				}
			}
		}

		// 폼 HTML 생성
		ob_start();
		?>
		<div class="ptg-inline-edit-form" data-question-id="<?php echo esc_attr( $question->question_id ); ?>">
			<input type="hidden" name="question_id" value="<?php echo esc_attr( $question->question_id ); ?>">
			
			<div class="ptg-edit-row">
				<div class="ptg-edit-field half">
					<label>과목 (Subject):</label>
					<select name="subject" class="ptg-edit-input ptg-subject-select" data-selected="<?php echo esc_attr($main_subject); ?>">
						<option value="">과목 선택</option>
						<!-- JS로 로드 -->
					</select>
				</div>
				<div class="ptg-edit-field half">
					<label>세부과목 (Sub-subject):</label>
					<select name="subsubject" class="ptg-edit-input ptg-subsubject-select" data-selected="<?php echo esc_attr($sub_subject); ?>">
						<option value="">세부과목 선택</option>
						<!-- JS로 로드 -->
					</select>
				</div>
			</div>
			
			<div class="ptg-edit-field">
				<label>지문 (content):</label>
				<textarea name="content" rows="8" class="ptg-edit-input"><?php echo esc_textarea( wp_unslash( $question->content ) ); ?></textarea>
			</div>
			<div class="ptg-edit-field">
				<label>정답 (answer):</label>
				<input type="text" name="answer" value="<?php echo esc_attr( wp_unslash( $question->answer ) ); ?>" class="ptg-edit-input">
			</div>
			
			<div class="ptg-edit-field">
				<label>해설 (explanation):</label>
				<textarea name="explanation" rows="8" class="ptg-edit-input"><?php echo esc_textarea( wp_unslash( $question->explanation ) ); ?></textarea>
			</div>

			<div class="ptg-edit-field">
				<label>이미지 (Image):</label>
				<?php if ( ! empty( $question->question_image ) ) : ?>
					<?php
					// 이미지 경로 계산
					// DB에서 년도/회차 정보를 가져와야 함. ptgates_categories 테이블 조인 필요하지만
					// 여기서는 간단히 question_id로 조회하거나, 이미지가 있으면 보여주는 방식.
					// 하지만 정확한 경로를 알기 위해선 category 정보가 필요함.
					// $question 객체는 ptgates_questions 테이블만 조회한 상태임.
					// 따라서 카테고리 정보를 추가로 조회해야 함.
					$cat_info = $wpdb->get_row( $wpdb->prepare( "SELECT exam_year, exam_session FROM ptgates_categories WHERE question_id = %d LIMIT 1", $question_id ) );
					$image_url = '';
					if ( $cat_info ) {
						$upload_dir = wp_upload_dir();
						$image_path = '/ptgates-questions/' . $cat_info->exam_year . '/' . $cat_info->exam_session . '/' . $question->question_image;
						$image_url = $upload_dir['baseurl'] . $image_path;
					}
					?>
					<?php if ( $image_url ) : ?>
						<div class="ptg-image-preview-container" style="max-width: 500px; max-height: 500px; margin-top: 10px;">
							<div style="max-width: 500px; max-height: 500px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
								<img src="<?php echo esc_url( $image_url ); ?>" class="ptg-image-preview" alt="Question Image" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;">
							</div>
							<p class="ptg-image-filename" style="margin-top: 5px; font-size: 12px; color: #666;"><?php echo esc_html( $question->question_image ); ?></p>
							<button type="button" class="ptg-btn-delete-image">이미지 삭제</button>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				<input type="hidden" name="delete_image" value="0">
				<input type="file" name="question_image" accept="image/*" class="ptg-edit-input">
				<p class="description">이미지를 업로드하면 기존 이미지는 덮어씌워집니다. (자동으로 {문제ID}.확장자 로 저장됨)</p>
			</div>
			
			<div class="ptg-edit-row">
				<div class="ptg-edit-field half">
					<label>난이도:</label>
					<select name="difficulty" class="ptg-edit-input">
						<option value="1" <?php selected( $question->difficulty, 1 ); ?>>1 (하)</option>
						<option value="2" <?php selected( $question->difficulty, 2 ); ?>>2 (중)</option>
						<option value="3" <?php selected( $question->difficulty, 3 ); ?>>3 (상)</option>
					</select>
				</div>
				<div class="ptg-edit-field half checkbox-field">
					<label>
						<input type="checkbox" name="is_active" value="1" <?php checked( $question->is_active, 1 ); ?>> 활성화
					</label>
				</div>
			</div>

			<div class="ptg-edit-actions">
				<button type="button" class="ptg-btn-primary pt-btn-save-edit">저장</button>
				<button type="button" class="ptg-btn-secondary pt-btn-cancel-edit">취소</button>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		wp_send_json_success( $html );
	}

	/**
	 * AJAX: 문제 업데이트 (Inline)
	 */
	/**
	 * 이미지 리사이징 및 최적화
	 * 
	 * @param string $file_path 원본 파일 경로
	 * @param string $target_path 저장할 파일 경로
	 * @param int $max_width 최대 너비 (기본값: 500px)
	 * @param int $max_height 최대 높이 (기본값: 500px)
	 * @param int $quality JPEG 품질 (기본값: 85)
	 * @return bool 성공 여부
	 */
	private function resize_and_optimize_image( $file_path, $target_path, $max_width = 500, $max_height = 500, $quality = 85 ) {
		if ( ! file_exists( $file_path ) ) {
			// error_log( '[PTGates Admin] 리사이징 실패: 원본 파일이 없음 - ' . $file_path );
			return false;
		}

		// WordPress 이미지 에디터 사용
		$image = wp_get_image_editor( $file_path );
		
		if ( is_wp_error( $image ) ) {
			// error_log( '[PTGates Admin] 이미지 에디터 로드 실패: ' . $image->get_error_message() );
			return false;
		}

		// 원본 이미지 크기 확인
		$original_size = $image->get_size();
		$original_width = $original_size['width'];
		$original_height = $original_size['height'];
		
		// error_log( sprintf( '[PTGates Admin] 원본 이미지 크기: %dx%d', $original_width, $original_height ) );

		// 리사이징이 필요한지 확인
		$needs_resize = ( $original_width > $max_width || $original_height > $max_height );
		
		if ( $needs_resize ) {
			// 비율 계산
			$ratio = min( $max_width / $original_width, $max_height / $original_height );
			$new_width = intval( $original_width * $ratio );
			$new_height = intval( $original_height * $ratio );
			
			// error_log( sprintf( '[PTGates Admin] 리사이징: %dx%d -> %dx%d', $original_width, $original_height, $new_width, $new_height ) );
			
			// 리사이징 실행
			$resized = $image->resize( $new_width, $new_height, false );
			
			if ( is_wp_error( $resized ) ) {
				// error_log( '[PTGates Admin] 리사이징 실패: ' . $resized->get_error_message() );
				return false;
			}
		} else {
			// error_log( '[PTGates Admin] 리사이징 불필요 (이미 최적 크기)' );
		}

		// JPEG 품질 설정
		$image->set_quality( $quality );
		
		// 파일 저장
		$saved = $image->save( $target_path );
		
		if ( is_wp_error( $saved ) ) {
			// error_log( '[PTGates Admin] 이미지 저장 실패: ' . $saved->get_error_message() );
			return false;
		}
		
		$saved_size = filesize( $target_path );
		$original_file_size = filesize( $file_path );
		$size_reduction = $original_file_size > 0 ? ( 1 - ( $saved_size / $original_file_size ) ) * 100 : 0;
		
		/*
		error_log( sprintf( 
			'[PTGates Admin] 이미지 최적화 완료: 원본 %s -> 저장 %s (%.1f%% 감소)', 
			size_format( $original_file_size ),
			size_format( $saved_size ),
			$size_reduction
		) );
		*/
		
		return true;
	}

	public function ajax_update_question_inline() {
		// 디버깅: 요청 시작 로그
		// error_log( '[PTGates Admin] ajax_update_question_inline 시작' );
		// error_log( '[PTGates Admin] POST 데이터 키: ' . implode( ', ', array_keys( $_POST ) ) );
		// error_log( '[PTGates Admin] FILES 데이터 키: ' . ( isset( $_FILES ) ? implode( ', ', array_keys( $_FILES ) ) : '없음' ) );
		
		check_ajax_referer( 'wp_rest', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( '권한이 없습니다.' );
		}

		$question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
		if ( ! $question_id ) {
            // error_log('PTGates Admin Update Error: Invalid Question ID. POST data: ' . print_r($_POST, true));
			wp_send_json_error( '잘못된 문제 ID입니다.' );
		}
		
		// error_log( '[PTGates Admin] Question ID: ' . $question_id );

		global $wpdb;
		// 테이블 이름은 prefix 없이 사용 (다른 플러그인과 일관성 유지)
		$table_name = 'ptgates_questions';

		// 역슬래시 제거: wp_unslash()로 슬래시 제거 후 DB에 저장 (중복 슬래시 방지)
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$explanation = isset( $_POST['explanation'] ) ? wp_unslash( $_POST['explanation'] ) : '';

		// 줄바꿈 정규화 (\r\n, \r -> \n)
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		
		// 동그라미 숫자 앞에 줄바꿈이 없으면 추가 (선택지 내부 줄바꿈은 보존)
		$content = preg_replace( '/(?<!\n)([①-⑳])/u', "\n$1", $content );
		
		// 연속된 줄바꿈 정리 (3개 이상 -> 2개로, 단 동그라미 숫자 앞의 줄바꿈은 유지)
		$content = preg_replace( '/\n{3,}/u', "\n\n", $content );
		
		// (오답 해설), (정답 해설) 앞에 줄바꿈 추가 (이미 줄바꿈 있으면 그대로 둠)
		$explanation = preg_replace(
			'/(?<!\n)[^\S\r\n]*(\((?:오답|정답)\s*해설\))/u',
			"\n$1",
			$explanation
		);

		// (보충 자료) 앞에 줄바꿈 추가 (이미 줄바꿈 있으면 그대로 둠)
		$explanation = preg_replace(
			'/(?<!\n)[^\S\r\n]*(\(보충\s*자료\))/u',
			"\n$1",
			$explanation
		);

		$data = array(
			'content'     => $content,
			'answer'      => isset( $_POST['answer'] ) ? sanitize_text_field( $_POST['answer'] ) : '',
			'explanation' => $explanation,
			'difficulty'  => isset( $_POST['difficulty'] ) ? intval( $_POST['difficulty'] ) : 2,
			'is_active'   => isset( $_POST['is_active'] ) ? 1 : 0,
			'updated_at'  => current_time( 'mysql' )
		);

		// 과목/세부과목 업데이트
		$subject_val = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
		$subsubject_val = isset( $_POST['subsubject'] ) ? sanitize_text_field( $_POST['subsubject'] ) : '';
		
		// 세부과목이 선택되었다면 그것을 subject 컬럼에 저장 (DB 구조상)
		// 만약 세부과목이 없고 과목만 있다면 과목을 저장 (예외 처리)
		$final_subject = $subsubject_val ? $subsubject_val : $subject_val;
		
		if ( $final_subject ) {
			// ptgates_categories 테이블 업데이트
			// 기존 레코드가 있는지 확인
			$cat_exists = $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM ptgates_categories WHERE question_id = %d", $question_id ) );
			
			if ( $cat_exists ) {
				$wpdb->update( 
					'ptgates_categories', 
					array( 'subject' => $final_subject ), 
					array( 'question_id' => $question_id ), 
					array( '%s' ), 
					array( '%d' ) 
				);
			} else {
				// 카테고리 정보가 없으면 새로 생성 (기본값 사용)
				$wpdb->insert(
					'ptgates_categories',
					array(
						'question_id' => $question_id,
						'subject' => $final_subject,
						'exam_year' => date('Y'), // 정보가 없으므로 현재 년도
						'exam_session' => 0,
						'exam_course' => '1교시' // 기본값
					),
					array( '%d', '%s', '%d', '%d', '%s' )
				);
			}
		}

		// 이미지 삭제 처리
		if ( isset( $_POST['delete_image'] ) && $_POST['delete_image'] === '1' ) {
			// 기존 이미지 정보 조회
			$old_image = $wpdb->get_var( $wpdb->prepare( "SELECT question_image FROM {$table_name} WHERE question_id = %d", $question_id ) );
			
			if ( $old_image ) {
				// 카테고리 정보 조회 (년도/회차)
				$cat_info = $wpdb->get_row( $wpdb->prepare( "SELECT exam_year, exam_session FROM ptgates_categories WHERE question_id = %d LIMIT 1", $question_id ) );
				
				if ( $cat_info ) {
					$upload_dir = wp_upload_dir();
					$target_file = $upload_dir['basedir'] . '/ptgates-questions/' . $cat_info->exam_year . '/' . $cat_info->exam_session . '/' . $old_image;
					
					if ( file_exists( $target_file ) ) {
						unlink( $target_file );
					}
				}
				
				$data['question_image'] = null; // DB에서 삭제
			}
		}

		// 이미지 업로드 처리
		// 디버깅: $_FILES 전체 확인
		// error_log( '[PTGates Admin] $_FILES 전체: ' . print_r( $_FILES, true ) );
		// error_log( '[PTGates Admin] POST 데이터 키: ' . implode( ', ', array_keys( $_POST ) ) );
		
		if ( ! empty( $_FILES['question_image']['name'] ) ) {
			// 새 이미지가 업로드되는 경우, 기존 이미지가 있다면 삭제
			$old_image = $wpdb->get_var( $wpdb->prepare( "SELECT question_image FROM {$table_name} WHERE question_id = %d", $question_id ) );
			
			if ( $old_image ) {
				// 카테고리 정보 조회 (년도/회차)
				$cat_info = $wpdb->get_row( $wpdb->prepare( "SELECT exam_year, exam_session FROM ptgates_categories WHERE question_id = %d LIMIT 1", $question_id ) );
				
				if ( $cat_info ) {
					$upload_dir = wp_upload_dir();
					$old_file_path = $upload_dir['basedir'] . '/ptgates-questions/' . $cat_info->exam_year . '/' . $cat_info->exam_session . '/' . $old_image;
					
					if ( file_exists( $old_file_path ) ) {
						unlink( $old_file_path );
					}
				}
			}

			$file = $_FILES['question_image'];
			
			// 디버깅: 파일 정보 전체 로그
			// error_log( '[PTGates Admin] 파일 정보: ' . print_r( $file, true ) );
			
			// 파일 크기 확인 (4MB = 4194304 bytes)
			$max_size = 10 * 1024 * 1024; // 10MB
			if ( isset( $file['size'] ) && $file['size'] > $max_size ) {
				// error_log( '[PTGates Admin] 파일 크기 초과: ' . $file['size'] . ' bytes (최대: ' . $max_size . ' bytes)' );
				wp_send_json_error( '파일 크기가 너무 큽니다. (최대 10MB)' );
			}
			
			// 확장자 추출 (여러 방법 시도)
			$ext = '';
			$ext_from_pathinfo = pathinfo( $file['name'], PATHINFO_EXTENSION );
			if ( ! empty( $ext_from_pathinfo ) ) {
				$ext = strtolower( $ext_from_pathinfo );
			} else {
				// 파일명에서 직접 추출
				$parts = explode( '.', $file['name'] );
				if ( count( $parts ) > 1 ) {
					$ext = strtolower( end( $parts ) );
				}
			}
			
			$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif' );
			
			// 확장자로 검증 (MIME 타입은 브라우저마다 다를 수 있으므로 확장자 우선)
			$is_valid = ! empty( $ext ) && in_array( $ext, $allowed_extensions );
			
			// MIME 타입도 추가 검증 (선택적)
			if ( ! $is_valid && ! empty( $file['type'] ) ) {
				$mime_type = strtolower( $file['type'] );
				$allowed_mime_types = array( 'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/x-png' );
				$is_valid = in_array( $mime_type, $allowed_mime_types );
				// MIME 타입이 유효하면 확장자 추정
				if ( $is_valid && empty( $ext ) ) {
					if ( strpos( $mime_type, 'jpeg' ) !== false ) {
						$ext = 'jpg';
					} elseif ( strpos( $mime_type, 'png' ) !== false ) {
						$ext = 'png';
					} elseif ( strpos( $mime_type, 'gif' ) !== false ) {
						$ext = 'gif';
					}
				}
			}
			
			// 파일 에러 코드 확인
			if ( isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
				$error_messages = array(
					UPLOAD_ERR_INI_SIZE => '파일 크기가 php.ini의 upload_max_filesize를 초과했습니다.',
					UPLOAD_ERR_FORM_SIZE => '파일 크기가 HTML form의 MAX_FILE_SIZE를 초과했습니다.',
					UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드되었습니다.',
					UPLOAD_ERR_NO_FILE => '파일이 업로드되지 않았습니다.',
					UPLOAD_ERR_NO_TMP_DIR => '임시 폴더가 없습니다.',
					UPLOAD_ERR_CANT_WRITE => '파일을 디스크에 쓸 수 없습니다.',
					UPLOAD_ERR_EXTENSION => 'PHP 확장에 의해 파일 업로드가 중지되었습니다.'
				);
				$error_msg = isset( $error_messages[ $file['error'] ] ) ? $error_messages[ $file['error'] ] : '알 수 없는 업로드 오류 (코드: ' . $file['error'] . ')';
				// error_log( '[PTGates Admin] 파일 업로드 에러: ' . $error_msg );
				wp_send_json_error( '파일 업로드 오류: ' . $error_msg );
			}
			
			if ( ! $is_valid || empty( $ext ) ) {
				// 디버깅 정보 포함
				$debug_info = sprintf(
					'파일명: %s, 확장자: %s, MIME 타입: %s, 파일 크기: %s, 에러 코드: %s',
					$file['name'],
					$ext ? $ext : '(추출 실패)',
					isset( $file['type'] ) ? $file['type'] : '없음',
					isset( $file['size'] ) ? $file['size'] : '없음',
					isset( $file['error'] ) ? $file['error'] : '없음'
				);
				// error_log( '[PTGates Admin] 이미지 검증 실패: ' . $debug_info );
				wp_send_json_error( '허용되지 않는 파일 형식입니다. (jpg, png, gif 만 가능)' );
			}

			// 카테고리 정보 조회 (년도/회차)
			$cat_info = $wpdb->get_row( $wpdb->prepare( "SELECT exam_year, exam_session FROM ptgates_categories WHERE question_id = %d LIMIT 1", $question_id ) );
			
			if ( ! $cat_info ) {
				wp_send_json_error( '문제의 카테고리 정보를 찾을 수 없어 이미지를 저장할 수 없습니다.' );
			}

			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/ptgates-questions/' . $cat_info->exam_year . '/' . $cat_info->exam_session;

			// 디렉토리 생성
			if ( ! file_exists( $target_dir ) ) {
				if ( ! wp_mkdir_p( $target_dir ) ) {
					wp_send_json_error( '업로드 디렉토리를 생성할 수 없습니다.' );
				}
			}

			// 파일명 생성 (문제ID_타임스탬프.확장자) - 캐시 방지 및 고유성 보장
			$filename = $question_id . '_' . time() . '.' . $ext;
			$target_file = $target_dir . '/' . $filename;

			// 임시 파일을 먼저 임시 위치로 이동
			$temp_file = $target_dir . '/temp_' . $filename;
			// error_log( '[PTGates Admin] 파일 이동 시도 - tmp_name: ' . $file['tmp_name'] . ', temp: ' . $temp_file );
			
			if ( ! file_exists( $file['tmp_name'] ) ) {
				// error_log( '[PTGates Admin] 임시 파일이 존재하지 않음: ' . $file['tmp_name'] );
				wp_send_json_error( '임시 파일을 찾을 수 없습니다.' );
			}
			
			// 먼저 임시 위치로 이동
			if ( ! move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
				$last_error = error_get_last();
				// error_log( '[PTGates Admin] 파일 이동 실패 - tmp_name: ' . $file['tmp_name'] . ', temp: ' . $temp_file );
				// error_log( '[PTGates Admin] PHP 에러: ' . print_r( $last_error, true ) );
				// error_log( '[PTGates Admin] 디렉토리 쓰기 권한 확인: ' . ( is_writable( $target_dir ) ? '가능' : '불가능' ) );
				wp_send_json_error( '파일 업로드에 실패했습니다. (디렉토리 권한 또는 디스크 공간 확인 필요)' );
			}
			
			// 이미지 리사이징 및 최적화
			if ( $this->resize_and_optimize_image( $temp_file, $target_file, 500, 500, 85 ) ) {
				// 리사이징 성공 시 임시 파일 삭제
				if ( file_exists( $temp_file ) ) {
					unlink( $temp_file );
				}
				// error_log( '[PTGates Admin] 이미지 리사이징 및 저장 완료: ' . $target_file );
				$data['question_image'] = $filename;
				$format[] = '%s';
				$new_filename = $filename;
			} else {
				// 리사이징 실패 시 원본 파일 사용 (하위 호환성)
				// error_log( '[PTGates Admin] 리사이징 실패, 원본 파일 사용' );
				if ( file_exists( $temp_file ) ) {
					if ( rename( $temp_file, $target_file ) ) {
						$data['question_image'] = $filename;
						$format[] = '%s';
						$new_filename = $filename;
					} else {
						// error_log( '[PTGates Admin] 원본 파일 이동도 실패' );
						wp_send_json_error( '이미지 처리에 실패했습니다.' );
					}
				} else {
					wp_send_json_error( '이미지 처리에 실패했습니다.' );
				}
			}
		}

		$result = $wpdb->update( $table_name, $data, array( 'question_id' => $question_id ) );

		if ( $result === false ) {
			wp_send_json_error( '데이터베이스 업데이트 실패' );
		}

		wp_send_json_success( array( 
			'message' => '문제가 수정되었습니다.',
			'new_image' => isset( $new_filename ) ? $new_filename : null
		) );
	}

    /**
     * AJAX: Excel Export (CSV)
     */
    public function ajax_export_questions_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }

        global $wpdb;
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';

        // Filters
        $where = array("q.is_active = 1");
        $where_values = array();

        $subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';
        $subsubject = isset($_GET['subsubject']) ? sanitize_text_field($_GET['subsubject']) : '';
        $exam_year = isset($_GET['exam_year']) ? intval($_GET['exam_year']) : 0;
        $exam_session = isset($_GET['exam_session']) ? intval($_GET['exam_session']) : 0;
        $exam_course = isset($_GET['exam_course']) ? sanitize_text_field($_GET['exam_course']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;

        // --- WHERE Clause Construction (Mirroring API logic) ---

        // 과목 필터
        if (!empty($subsubject)) {
            $where[] = "c.subject = %s";
            $where_values[] = $subsubject;
        } elseif (!empty($subject)) {
            if (class_exists('\PTG\Quiz\Subjects')) {
                $subsubjects_to_include = array();
                $sessions = \PTG\Quiz\Subjects::get_sessions();
                foreach ($sessions as $sess) {
                    $subsubjects = \PTG\Quiz\Subjects::get_subsubjects($sess, $subject);
                    if (!empty($subsubjects)) {
                        $subsubjects_to_include = array_merge($subsubjects_to_include, $subsubjects);
                    }
                }
                $subsubjects_to_include = array_values(array_unique($subsubjects_to_include));
                
                if (!empty($subsubjects_to_include)) {
                    $or_parts = array();
                    $or_params = array();
                    foreach ($subsubjects_to_include as $sub_name) {
                        $or_parts[] = "c.subject = %s";
                        $or_params[] = $sub_name;
                    }
                    $where[] = "(" . implode(" OR ", $or_parts) . ")";
                    $where_values = array_merge($where_values, $or_params);
                } else {
                    $where[] = "c.subject LIKE %s";
                    $where_values[] = $subject . '%';
                }
            } else {
                $where[] = "c.subject LIKE %s";
                $where_values[] = $subject . '%';
            }
        }

        if (!empty($exam_year)) {
            $where[] = "c.exam_year = %d";
            $where_values[] = $exam_year;
        }

        if (!empty($exam_session)) {
            $where[] = "c.exam_session = %d";
            $where_values[] = $exam_session;
        }

        if (!empty($exam_course)) {
            if (is_numeric($exam_course)) {
                $exam_course_val = $exam_course . '교시';
            } else {
                $exam_course_val = $exam_course;
            }
            $where[] = "REPLACE(TRIM(c.exam_course), ' ', '') = %s";
            $where_values[] = str_replace(' ', '', $exam_course_val);
        }

        if (!empty($question_id)) {
            $where[] = "q.question_id = %d";
            $where_values[] = $question_id;
        }
        
        // 검색 필터
        if (!empty($search)) {
            $search = trim($search);
            $terms = preg_split('/\s+/', $search);
            $term_conditions = array();

            foreach ($terms as $term) {
                $term = trim($term);
                if ($term === '') continue;
                $search_like = '%' . $wpdb->esc_like($term) . '%';
                $term_conditions[] = "(q.content LIKE %s OR q.explanation LIKE %s)";
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            }

            if (!empty($term_conditions)) {
                $where[] = '(' . implode(' AND ', $term_conditions) . ')';
            }
        }
        
        $where_clause = implode(' AND ', $where);

        // Query
        $sql = "SELECT DISTINCT q.question_id, q.content, q.answer, q.explanation, q.type, q.difficulty, q.is_active,
                GROUP_CONCAT(DISTINCT c.subject ORDER BY c.subject SEPARATOR ', ') as subsubjects,
                GROUP_CONCAT(DISTINCT c.exam_year ORDER BY c.exam_year SEPARATOR ', ') as exam_years,
                GROUP_CONCAT(DISTINCT c.exam_session ORDER BY c.exam_session SEPARATOR ', ') as exam_sessions,
                GROUP_CONCAT(DISTINCT c.exam_course ORDER BY c.exam_course SEPARATOR ', ') as exam_courses
                FROM {$questions_table} q
                INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                WHERE {$where_clause}
                GROUP BY q.question_id
                ORDER BY q.question_id DESC";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($sql, $where_values);
        } else {
            $query = $sql;
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Header
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=questions_export_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');

        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV Header
        fputcsv($output, array('ID', '년도', '회차', '교시', '세부과목', '대분류(추정)', '지문', '정답', '해설', '난이도', '활성여부'));

         // Subjects Main Category Logic
         $sessions_obj = class_exists('\PTG\Quiz\Subjects') ? \PTG\Quiz\Subjects::get_sessions() : array();

        foreach ($results as $row) {
            // Main Subject Logic
            $main_subjects = array();
            if (class_exists('\PTG\Quiz\Subjects') && !empty($row['subsubjects'])) {
                $sub_list = explode(', ', $row['subsubjects']);
                foreach ($sub_list as $sub_name) {
                    $found_main = false;
                    foreach ($sessions_obj as $sess) {
                        $main_cats = \PTG\Quiz\Subjects::get_subjects_for_session($sess);
                        foreach ($main_cats as $main_cat) {
                            $subs = \PTG\Quiz\Subjects::get_subsubjects($sess, $main_cat);
                            if (in_array($sub_name, $subs)) {
                                if (!in_array($main_cat, $main_subjects)) {
                                    $main_subjects[] = $main_cat;
                                }
                                $found_main = true;
                                break;
                            }
                        }
                        if ($found_main) break;
                    }
                }
            }
            $main_subject_str = implode(', ', $main_subjects);

            fputcsv($output, array(
                $row['question_id'],
                $row['exam_years'],
                $row['exam_sessions'],
                $row['exam_courses'],
                $row['subsubjects'],
                $main_subject_str,
                $row['content'],
                $row['answer'],
                $row['explanation'],
                $row['difficulty'],
                $row['is_active'] ? 'Y' : 'N'
            ));
        }

        fclose($output);
        exit;
    }
}

// 플러그인 인스턴스 생성
PTG_Admin_Plugin::get_instance();

