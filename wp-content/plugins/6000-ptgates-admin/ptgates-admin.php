<?php
/**
 * Plugin Name: PTGates Admin
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
			$js_path  = plugin_dir_path( __FILE__ ) . 'assets/js/admin-list.js';
			$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';
			$js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0';

			wp_enqueue_style(
				'ptg-admin-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
				[],
				$css_ver
			);
			
			// list 타입일 때 JavaScript 로드
			if ( has_shortcode( $post->post_content, 'ptg_admin' ) ) {
				wp_enqueue_script(
					'ptg-admin-list',
					plugin_dir_url( __FILE__ ) . 'assets/js/admin-list.js',
					['jquery'],
					$js_ver,
					true
				);
				
				// REST API URL과 nonce 전달
				wp_localize_script('ptg-admin-list', 'ptgAdmin', array(
					'apiUrl' => rest_url('ptg-admin/v1/'),
					'nonce' => wp_create_nonce('wp_rest'),
				));
			}
		}
	}
	
	/**
	 * 관리자 페이지 스타일/스크립트 로드
	 */
	public function enqueue_admin_assets( $hook ) {
		// 문제 목록 페이지에서만 로드
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $current_page === 'ptgates-admin-list' ) {
			$css_path = plugin_dir_path( __FILE__ ) . 'assets/css/admin.css';
			$js_path  = plugin_dir_path( __FILE__ ) . 'assets/js/admin-list.js';
			$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';
			$js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0';

			wp_enqueue_style(
				'ptg-admin-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
				[],
				$css_ver
			);
			
			wp_enqueue_script(
				'ptg-admin-list',
				plugin_dir_url( __FILE__ ) . 'assets/js/admin-list.js',
				['jquery'],
				$js_ver,
				true
			);
			
			// REST API URL과 nonce 전달
			wp_localize_script('ptg-admin-list', 'ptgAdmin', array(
				'apiUrl' => rest_url('ptg-admin/v1/'),
				'nonce' => wp_create_nonce('wp_rest'),
			));
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
			[ $this, 'render_admin_page' ],
			'dashicons-clipboard',
			30
		);

		add_submenu_page(
			'ptgates-admin',
			'문제 목록 & 편집',
			'문제 목록 & 편집',
			'manage_options',
			'ptgates-admin-list',
			[ $this, 'render_list_page' ]
		);

		add_submenu_page(
			'ptgates-admin',
			'문제 일괄 등록',
			'문제 일괄 등록',
			'manage_options',
			'ptgates-admin-import',
			[ $this, 'render_import_page' ]
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
				<li><a href="<?php echo admin_url( 'admin.php?page=ptgates-admin-import' ); ?>">CSV 일괄 삽입</a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * 문제 목록 페이지 렌더링
	 */
	public function render_list_page() {
		// 관리자 권한 재확인
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '권한이 없습니다.' );
		}

		echo '<div class="wrap">';
		$this->render_question_list();
		echo '</div>';
	}

	/**
	 * CSV 일괄 삽입 페이지 렌더링
	 */
	public function render_import_page() {
		// 관리자 권한 재확인
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '권한이 없습니다.' );
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
			<div id="ptg-edit-modal" class="ptg-edit-modal" style="display: none;">
				<div class="ptg-edit-modal-content">
					<div class="ptg-edit-modal-header">
						<h3>문제 편집</h3>
						<button class="ptg-edit-modal-close">×</button>
					</div>
					<div class="ptg-edit-modal-body">
						<input type="hidden" id="ptg-edit-question-id" />
						
						<div class="ptg-edit-field">
							<label>지문 (content):</label>
							<textarea id="ptg-edit-content" rows="10" style="width: 100%;"></textarea>
						</div>
						
						<div class="ptg-edit-field">
							<label>정답 (answer):</label>
							<input type="text" id="ptg-edit-answer" style="width: 100%;" />
						</div>
						
						<div class="ptg-edit-field">
							<label>해설 (explanation):</label>
							<textarea id="ptg-edit-explanation" rows="10" style="width: 100%;"></textarea>
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
						<button id="ptg-save-btn" class="ptg-btn-primary">저장</button>
						<button id="ptg-cancel-btn" class="ptg-btn-secondary">취소</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 통계 렌더링 (향후 구현)
	 */
	private function render_statistics() {
		echo '<div class="ptg-admin-info"><p>📊 통계 기능은 향후 구현 예정입니다.</p></div>';
	}

	/**
	 * CLI 초기화
	 */
	private function init_cli() {
		$import_file = plugin_dir_path( __FILE__ ) . 'includes/class-import.php';
		if ( file_exists( $import_file ) ) {
			// CLI 환경에서는 직접 실행
			// class-import.php가 CLI 모드일 때 자체적으로 실행됨
		}
	}
}

// 플러그인 인스턴스 생성
PTG_Admin_Plugin::get_instance();

