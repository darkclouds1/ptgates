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

		// CLI 지원 (기존 기능 유지)
		if ( php_sapi_name() === 'cli' ) {
			$this->init_cli();
		}
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
			$member_actions = array( 'ptg_admin_get_member', 'ptg_admin_update_member', 'ptg_admin_get_history' );
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
					}
					exit;
				}
			}
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
			
			// REST API URL과 nonce 전달
			$script_data = array(
				'apiUrl' => rest_url('ptg-admin/v1/'),
				'nonce' => wp_create_nonce('wp_rest'),
				'ajaxUrl' => admin_url('admin-ajax.php')
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
				wp_enqueue_script(
					'ptg-admin-stats',
					plugin_dir_url( __FILE__ ) . 'assets/js/admin-stats.js',
					['jquery'],
					$js_stats_ver,
					true
				);
			}
			
			// REST API URL과 nonce 전달
			$script_data = array(
				'apiUrl' => rest_url('ptg-admin/v1/'),
				'nonce' => wp_create_nonce('wp_rest'),
				'ajaxUrl' => admin_url('admin-ajax.php')
			);
			
			wp_localize_script('ptg-admin-list', 'ptgAdmin', $script_data);
			wp_localize_script('ptg-admin-stats', 'ptgAdmin', $script_data);
			wp_localize_script('ptg-admin-list', 'ptgAdmin', $script_data);
			wp_localize_script('ptg-admin-stats', 'ptgAdmin', $script_data);
			
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

		// 세 번째 서브메뉴: 통계 대시보드
		add_submenu_page(
			'ptgates-admin',
			'통계 대시보드',
			'통계 대시보드',
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

		// 다섯 번째 서브메뉴: 도구 (Tools)
		add_submenu_page(
			'ptgates-admin',
			'관리 도구',
			'관리 도구',
			'manage_options',
			'ptgates-admin-tools',
			[ $this, 'render_tools_page' ]
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
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-stats' ); ?>">통계 대시보드</a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-members' ); ?>">멤버십 관리</a></li>
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
						<input type="file" name="question_image" accept="image/*" />
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
	 * 통계 페이지 렌더링
	 */
	public function render_stats_page() {
		// ptGates 관리자 권한 확인
		if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
			wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
		}

		echo '<div class="wrap">';
		$this->render_statistics();
		echo '</div>';
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
			<h2>📊 문제은행 통계 대시보드</h2>
			
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

	/**
	 * 관리 도구 페이지 렌더링
	 */
	public function render_tools_page() {
		// ptGates 관리자 권한 확인
		if ( ! class_exists( '\PTG\Platform\Permissions' ) || ! \PTG\Platform\Permissions::can_manage_ptgates() ) {
			wp_die( 'ptGates 관리자 권한이 필요합니다. (pt_admin 등급 필요)' );
		}

		?>
		<div class="wrap">
			<h1>🛠️ 관리 도구</h1>
			
			<div class="card" style="max-width: 600px; margin-top: 20px;">
				<h2>과목 카테고리 일괄 업데이트 (Backfill)</h2>
				<p>기존 문제 데이터 중 <code>subject_category</code> (대분류) 필드가 비어있는 항목을 찾아 자동으로 채워넣습니다.</p>
				<p>이 작업은 <code>0000-ptgates-platform/includes/class-subjects.php</code>의 매핑 정보를 사용합니다.</p>
				
				<div style="margin-top: 15px;">
					<button id="ptg-backfill-btn" class="button button-primary">업데이트 실행</button>
					<span id="ptg-backfill-status" style="margin-left: 10px;"></span>
				</div>
				
				<div id="ptg-backfill-result" style="margin-top: 15px; display: none; padding: 10px; background: #f0f0f1; border: 1px solid #ccd0d4;"></div>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				$('#ptg-backfill-btn').on('click', function() {
					if (!confirm('업데이트를 실행하시겠습니까?')) return;
					
					const $btn = $(this);
					const $status = $('#ptg-backfill-status');
					const $result = $('#ptg-backfill-result');
					
					$btn.prop('disabled', true);
					$status.text('처리 중...');
					$result.hide();
					
					$.ajax({
						url: '<?php echo rest_url('ptg-admin/v1/backfill-categories'); ?>',
						method: 'POST',
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
						},
						success: function(response) {
							$btn.prop('disabled', false);
							$status.text('완료');
							
							let msg = '';
							if (response.message) {
								msg = response.message;
							} else if (response.data && response.data.message) {
								msg = response.data.message;
							} else {
								msg = JSON.stringify(response);
							}
							
							$result.html('<p><strong>결과:</strong> ' + msg + '</p>').show();
						},
						error: function(xhr, status, error) {
							$btn.prop('disabled', false);
							$status.text('오류 발생');
							
							let errorMsg = '알 수 없는 오류';
							if (xhr.responseJSON && xhr.responseJSON.message) {
								errorMsg = xhr.responseJSON.message;
							} else {
								errorMsg = error;
							}
							
							$result.html('<p style="color: red;"><strong>오류:</strong> ' + errorMsg + '</p>').show();
						}
					});
				});
			});
			</script>
		</div>
		<?php
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
				<textarea name="content" rows="8" class="ptg-edit-input"><?php echo esc_textarea( $question->content ); ?></textarea>
			</div>
			<div class="ptg-edit-field">
				<label>정답 (answer):</label>
				<input type="text" name="answer" value="<?php echo esc_attr( $question->answer ); ?>" class="ptg-edit-input">
			</div>
			
			<div class="ptg-edit-field">
				<label>해설 (explanation):</label>
				<textarea name="explanation" rows="8" class="ptg-edit-input"><?php echo esc_textarea( $question->explanation ); ?></textarea>
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
						<div class="ptg-image-preview-container">
							<img src="<?php echo esc_url( $image_url ); ?>" class="ptg-image-preview" alt="Question Image">
							<p class="ptg-image-filename"><?php echo esc_html( $question->question_image ); ?></p>
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
	public function ajax_update_question_inline() {
		check_ajax_referer( 'wp_rest', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( '권한이 없습니다.' );
		}

		$question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
		if ( ! $question_id ) {
            error_log('PTGates Admin Update Error: Invalid Question ID. POST data: ' . print_r($_POST, true));
			wp_send_json_error( '잘못된 문제 ID입니다.' );
		}

		global $wpdb;
		// 테이블 이름은 prefix 없이 사용 (다른 플러그인과 일관성 유지)
		$table_name = 'ptgates_questions';

		$content = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
		$explanation = isset( $_POST['explanation'] ) ? wp_kses_post( $_POST['explanation'] ) : '';

		// 줄바꿈 제거 후 동그라미 숫자 앞에 줄바꿈 추가 (지문만)
		$content = str_replace( array( "\r\n", "\r", "\n" ), '', $content );
		$content = preg_replace( '/([①-⑳])/u', "\n$1", $content );
		
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
		if ( ! empty( $_FILES['question_image']['name'] ) ) {
			$file = $_FILES['question_image'];
			
			// 파일 타입 검사
			$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
			if ( ! in_array( $file['type'], $allowed_types ) ) {
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

			// 파일명 생성 (문제ID.확장자)
			$ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
			$filename = $question_id . '.' . $ext;
			$target_file = $target_dir . '/' . $filename;

			// 파일 이동
			if ( move_uploaded_file( $file['tmp_name'], $target_file ) ) {
				$data['question_image'] = $filename;
			} else {
				wp_send_json_error( '파일 업로드에 실패했습니다.' );
			}
		}

		$result = $wpdb->update( $table_name, $data, array( 'question_id' => $question_id ) );

		if ( $result === false ) {
			wp_send_json_error( '데이터베이스 업데이트 실패' );
		}

		wp_send_json_success( '저장되었습니다.' );
	}
}

// 플러그인 인스턴스 생성
PTG_Admin_Plugin::get_instance();

