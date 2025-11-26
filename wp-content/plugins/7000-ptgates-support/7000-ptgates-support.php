<?php
/**
 * Plugin Name: 7000-ptgates-support (PTGates Support)
 * Description: QnA Board and Support features for ptGates.
 * Version: 1.0.0
 * Author: ptGates Team
 * Text Domain: ptgates-support
 * Requires Plugins: 0000-ptgates-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PTG_Support {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_shortcode( 'ptg_qna_board', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'handle_form_submission' ) );
		add_action( 'template_redirect', array( $this, 'redirect_single_qna' ) );
		
		// Admin Interface
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_submission' ) );
	}

	public function register_cpt() {
		$labels = array(
			'name'               => 'QnA',
			'singular_name'      => 'QnA',
			'menu_name'          => 'QnA 게시판',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // Hide from default menu
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'qna' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'ptg_qna', $args );

		// Register Contact CPT
		register_post_type( 'ptg_contact', array(
			'labels' => array(
				'name' => '1:1 문의',
				'singular_name' => '문의',
			),
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'supports' => array( 'title', 'editor' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		));
	}

	public function register_admin_menu() {
		add_menu_page(
			'QnA 게시판',
			'QnA 게시판',
			'manage_options',
			'ptg-qna-admin',
			array( $this, 'render_admin_page' ),
			'dashicons-format-chat',
			25
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_ptg-qna-admin' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'ptg-admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), '1.0.0' );
	}

	public function handle_admin_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save Answer
		if ( isset( $_POST['ptg_admin_action'] ) && 'save_answer' === $_POST['ptg_admin_action'] ) {
			check_admin_referer( 'ptg_save_answer', 'ptg_admin_nonce' );
			
			$post_id = intval( $_POST['post_id'] );
			$answer = wp_kses_post( $_POST['answer_content'] );
			
			update_post_meta( $post_id, '_ptg_qna_answer', $answer );
			
			wp_redirect( add_query_arg( array( 'page' => 'ptg-qna-admin', 'action' => 'view', 'id' => $post_id, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Create New Post (Admin)
		if ( isset( $_POST['ptg_admin_action'] ) && 'create_post' === $_POST['ptg_admin_action'] ) {
			check_admin_referer( 'ptg_create_post', 'ptg_admin_nonce' );
			
			$title = sanitize_text_field( $_POST['post_title'] );
			$content = wp_kses_post( $_POST['post_content'] );
			
			if ( ! empty( $title ) && ! empty( $content ) ) {
				$post_id = wp_insert_post( array(
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'ptg_qna',
					'post_author'  => get_current_user_id(),
				) );
				
				if ( $post_id ) {
					wp_redirect( add_query_arg( array( 'page' => 'ptg-qna-admin', 'created' => 1 ), admin_url( 'admin.php' ) ) );
					exit;
				}
			}
		}
	}

	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'qna';
		
		echo '<div class="wrap ptg-admin-wrapper">';
		echo '<h1 class="wp-heading-inline">고객지원 센터</h1>';
		
		echo '<nav class="nav-tab-wrapper">';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ptg-qna-admin', 'tab' => 'qna' ), admin_url( 'admin.php' ) ) ) . '" class="nav-tab ' . ( 'qna' === $tab ? 'nav-tab-active' : '' ) . '">QnA 게시판</a>';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ptg-qna-admin', 'tab' => 'contact' ), admin_url( 'admin.php' ) ) ) . '" class="nav-tab ' . ( 'contact' === $tab ? 'nav-tab-active' : '' ) . '">1:1 문의</a>';
		echo '</nav>';
		
		echo '<div class="ptg-tab-content" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none;">';
		
		if ( 'contact' === $tab ) {
			$this->render_admin_contact_section();
		} else {
			$this->render_admin_qna_section();
		}
		
		echo '</div>';
		echo '</div>';
	}

	private function render_admin_qna_section() {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		if ( 'new' === $action ) {
			$this->render_admin_create();
		} elseif ( 'view' === $action && isset( $_GET['id'] ) ) {
			$this->render_admin_view( intval( $_GET['id'] ) );
		} else {
			$this->render_admin_list();
		}
	}

	private function render_admin_contact_section() {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		if ( 'view' === $action && isset( $_GET['id'] ) ) {
			$this->render_admin_contact_view( intval( $_GET['id'] ) );
		} else {
			$this->render_admin_contact_list();
		}
	}

	private function render_admin_contact_list() {
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$args = array(
			'post_type'      => 'ptg_contact',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => 'publish',
		);
		$query = new WP_Query( $args );

		echo '<div class="ptg-admin-header">';
		echo '<h2>1:1 문의 목록</h2>';
		echo '</div>';

		echo '<table class="ptg-admin-table">';
		echo '<thead><tr>
			<th>문의명(요약)</th>
			<th style="width: 150px;">담당자</th>
			<th style="width: 200px;">이메일</th>
			<th style="width: 150px;">연락처</th>
			<th style="width: 150px;">접수일</th>
			<th style="width: 100px;">관리</th>
		</tr></thead>';
		echo '<tbody>';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$id = get_the_ID();
				$company = get_post_meta( $id, '_ptg_contact_company', true );
				$name = get_post_meta( $id, '_ptg_contact_name', true );
				$email = get_post_meta( $id, '_ptg_contact_email', true );
				$phone = get_post_meta( $id, '_ptg_contact_phone', true );
				
				$edit_url = add_query_arg( array( 'page' => 'ptg-qna-admin', 'tab' => 'contact', 'action' => 'view', 'id' => $id ), admin_url( 'admin.php' ) );

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $company ) . '</a></strong></td>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td>' . esc_html( $email ) . '</td>';
				echo '<td>' . esc_html( $phone ) . '</td>';
				echo '<td>' . get_the_date( 'Y-m-d' ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-small">확인</a></td>';
				echo '</tr>';
			}
			wp_reset_postdata();
		} else {
			echo '<tr><td colspan="6" style="text-align: center;">접수된 문의가 없습니다.</td></tr>';
		}
		echo '</tbody></table>';
		
		// Pagination
		$big = 999999999;
		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo paginate_links( array(
			'base' => str_replace( $big, '%#%', esc_url( add_query_arg( 'paged', '%#%' ) ) ),
			'format' => '',
			'current' => $paged,
			'total' => $query->max_num_pages
		) );
		echo '</div></div>';
	}

	private function render_admin_contact_view( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'ptg_contact' !== $post->post_type ) {
			echo '<div class="notice notice-error"><p>문의를 찾을 수 없습니다.</p></div>';
			return;
		}

		$company = get_post_meta( $post_id, '_ptg_contact_company', true );
		$name = get_post_meta( $post_id, '_ptg_contact_name', true );
		$email = get_post_meta( $post_id, '_ptg_contact_email', true );
		$phone = get_post_meta( $post_id, '_ptg_contact_phone', true );

		echo '<div class="ptg-admin-header">';
		echo '<h2>문의 상세 내용</h2>';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ptg-qna-admin', 'tab' => 'contact' ), admin_url( 'admin.php' ) ) ) . '" class="ptg-btn ptg-btn-secondary">목록으로</a>';
		echo '</div>';

		echo '<div class="ptg-admin-card">';
		echo '<table class="form-table">';
		echo '<tr><th>문의명(요약)</th><td>' . esc_html( $company ) . '</td></tr>';
		echo '<tr><th>담당자</th><td>' . esc_html( $name ) . '</td></tr>';
		echo '<tr><th>이메일</th><td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td></tr>';
		echo '<tr><th>연락처</th><td>' . esc_html( $phone ) . '</td></tr>';
		echo '<tr><th>접수일시</th><td>' . get_the_date( 'Y-m-d H:i:s', $post ) . '</td></tr>';
		echo '</table>';
		
		echo '<hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">';
		
		echo '<h3>문의 내용</h3>';
		echo '<div class="ptg-question-content" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">';
		echo nl2br( esc_html( $post->post_content ) );
		echo '</div>';
		echo '</div>';
		
		// Delete button
		echo '<div class="ptg-actions" style="margin-top: 20px; text-align: right;">';
		echo '<a href="' . get_delete_post_link( $post_id ) . '" class="ptg-btn ptg-btn-danger" onclick="return confirm(\'정말 삭제하시겠습니까?\');">문의 삭제</a>';
		echo '</div>';
	}

	private function render_admin_list() {
		$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$args = array(
			'post_type'      => 'ptg_qna',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => 'publish',
		);
		$query = new WP_Query( $args );

		echo '<div class="ptg-admin-header">';
		echo '<h1>QnA 게시판 관리</h1>';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ptg-qna-admin', 'action' => 'new' ), admin_url( 'admin.php' ) ) ) . '" class="ptg-btn ptg-btn-primary">새 글 작성</a>';
		echo '</div>';

		if ( isset( $_GET['created'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>새 글이 작성되었습니다.</p></div>';
		}

		echo '<table class="ptg-admin-table">';
		echo '<thead><tr>
			<th style="width: 100px;">상태</th>
			<th>제목</th>
			<th style="width: 150px;">작성자</th>
			<th style="width: 150px;">작성일</th>
			<th style="width: 100px;">관리</th>
		</tr></thead>';
		echo '<tbody>';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$answer = get_post_meta( get_the_ID(), '_ptg_qna_answer', true );
				$is_answered = ! empty( $answer );
				$status_class = $is_answered ? 'answered' : 'waiting';
				$status_text = $is_answered ? '답변완료' : '대기중';
				
				$edit_url = add_query_arg( array( 'page' => 'ptg-qna-admin', 'action' => 'view', 'id' => get_the_ID() ), admin_url( 'admin.php' ) );

				echo '<tr>';
				echo '<td><span class="ptg-status-badge ' . $status_class . '">' . $status_text . '</span></td>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . get_the_title() . '</a></strong></td>';
				echo '<td>' . get_the_author() . '</td>';
				echo '<td>' . get_the_date( 'Y-m-d' ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-small">관리</a></td>';
				echo '</tr>';
			}
			wp_reset_postdata();
		} else {
			echo '<tr><td colspan="5" style="text-align: center;">등록된 질문이 없습니다.</td></tr>';
		}
		echo '</tbody></table>';
		
		// Pagination
		$big = 999999999;
		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo paginate_links( array(
			'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'format' => '?paged=%#%',
			'current' => $paged,
			'total' => $query->max_num_pages
		) );
		echo '</div></div>';
	}

	private function render_admin_view( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'ptg_qna' !== $post->post_type ) {
			echo '<div class="notice notice-error"><p>글을 찾을 수 없습니다.</p></div>';
			return;
		}

		$answer = get_post_meta( $post_id, '_ptg_qna_answer', true );

		echo '<div class="ptg-admin-header">';
		echo '<h1>질문 관리</h1>';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ptg-qna-admin' ), admin_url( 'admin.php' ) ) ) . '" class="ptg-btn ptg-btn-secondary">목록으로</a>';
		echo '</div>';

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>답변이 저장되었습니다.</p></div>';
		}

		echo '<div class="ptg-admin-card">';
		echo '<h2>' . esc_html( $post->post_title ) . '</h2>';
		echo '<div class="ptg-question-meta">';
		echo '<span>작성자: ' . get_the_author_meta( 'display_name', $post->post_author ) . '</span>';
		echo '<span>작성일: ' . get_the_date( 'Y-m-d H:i', $post ) . '</span>';
		echo '</div>';
		echo '<div class="ptg-question-content">';
		echo apply_filters( 'the_content', $post->post_content );
		echo '</div>';
		echo '</div>';

		echo '<div class="ptg-admin-card ptg-answer-section">';
		echo '<h3>답변 작성</h3>';
		echo '<form method="post" action="">';
		wp_nonce_field( 'ptg_save_answer', 'ptg_admin_nonce' );
		echo '<input type="hidden" name="ptg_admin_action" value="save_answer">';
		echo '<input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '">';
		
		wp_editor( $answer, 'answer_content', array(
			'media_buttons' => false,
			'textarea_rows' => 10,
			'teeny' => true
		) );

		echo '<div class="ptg-actions">';
		echo '<button type="submit" class="ptg-btn ptg-btn-primary">답변 저장하기</button>';
		echo '<a href="' . get_delete_post_link( $post_id ) . '" class="ptg-btn ptg-btn-danger" onclick="return confirm(\'정말 삭제하시겠습니까?\');">질문 삭제</a>';
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	private function render_admin_create() {
		echo '<div class="ptg-admin-header">';
		echo '<h1>새 글 작성</h1>';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'ptg-qna-admin' ), admin_url( 'admin.php' ) ) ) . '" class="ptg-btn ptg-btn-secondary">목록으로</a>';
		echo '</div>';

		echo '<div class="ptg-admin-card">';
		echo '<form method="post" action="">';
		wp_nonce_field( 'ptg_create_post', 'ptg_admin_nonce' );
		echo '<input type="hidden" name="ptg_admin_action" value="create_post">';
		
		echo '<div class="ptg-form-group">';
		echo '<label for="post_title">제목</label>';
		echo '<input type="text" id="post_title" name="post_title" required placeholder="제목을 입력하세요">';
		echo '</div>';

		echo '<div class="ptg-form-group">';
		echo '<label for="post_content">내용</label>';
		wp_editor( '', 'post_content', array(
			'media_buttons' => false,
			'textarea_rows' => 15,
			'teeny' => true
		) );
		echo '</div>';

		echo '<div class="ptg-actions">';
		echo '<button type="submit" class="ptg-btn ptg-btn-primary">글 작성하기</button>';
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'ptg-support-css', plugin_dir_url( __FILE__ ) . 'assets/css/support.css', array(), '1.0.1' );
	}

	public function redirect_single_qna() {
		if ( is_singular( 'ptg_qna' ) ) {
			$redirect_url = add_query_arg( 
				array( 
					'action' => 'view', 
					'qna_id' => get_the_ID() 
				), 
				home_url( '/contact/' ) 
			);
			wp_redirect( $redirect_url );
			exit;
		}
	}

	public function handle_form_submission() {
		if ( isset( $_POST['ptg_qna_action'] ) && 'submit_question' === $_POST['ptg_qna_action'] ) {
			if ( ! isset( $_POST['ptg_qna_nonce'] ) || ! wp_verify_nonce( $_POST['ptg_qna_nonce'], 'ptg_qna_submit' ) ) {
				wp_die( '보안 검증 실패' );
			}

			$title   = sanitize_text_field( $_POST['qna_title'] );
			$content = wp_kses_post( $_POST['qna_content'] );
			$user_id = get_current_user_id();

			if ( empty( $title ) || empty( $content ) ) {
				return;
			}

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'ptg_qna',
				'post_author'  => $user_id,
			);

			$post_id = wp_insert_post( $post_data );

			if ( $post_id ) {
				wp_redirect( remove_query_arg( array( 'action', 'qna_id' ) ) );
				exit;
			}
		}
	}

	public function render_shortcode( $atts ) {
		$a = shortcode_atts( array(
			'items_per_page' => 10,
		), $atts );

		ob_start();

		$action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
		$qna_id = isset( $_GET['qna_id'] ) ? intval( $_GET['qna_id'] ) : 0;

		echo '<div class="ptg-qna-wrapper">';

		if ( 'write' === $action ) {
			$this->render_form();
		} elseif ( 'view' === $action && $qna_id ) {
			$this->render_single( $qna_id );
		} else {
			$this->render_list( $a['items_per_page'] );
		}

		echo '</div>';

		return ob_get_clean();
	}

	private function render_list( $items_per_page ) {
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		
		$args = array(
			'post_type'      => 'ptg_qna',
			'posts_per_page' => $items_per_page,
			'paged'          => $paged,
			'post_status'    => 'publish',
		);

		$query = new WP_Query( $args );

		echo '<div class="ptg-qna-header">';
		echo '<h2>QnA 게시판</h2>';
		echo '<a href="' . esc_url( add_query_arg( 'action', 'write' ) ) . '" class="ptg-btn ptg-btn-primary">질문하기</a>';
		echo '</div>';

		if ( $query->have_posts() ) {
			echo '<div class="ptg-qna-list">';
			echo '<div class="ptg-qna-list-header">';
			echo '<div class="col-status">상태</div>';
			echo '<div class="col-title">제목</div>';
			echo '<div class="col-author">작성자</div>';
			echo '<div class="col-date">날짜</div>';
			echo '</div>';
			
			while ( $query->have_posts() ) {
				$query->the_post();
				$answer = get_post_meta( get_the_ID(), '_ptg_qna_answer', true );
				$is_answered = ! empty( $answer );
				$status_class = $is_answered ? 'status-answered' : 'status-waiting';
				$status_text = $is_answered ? '답변완료' : '대기중';

				echo '<div class="ptg-qna-item">';
				echo '<div class="col-status"><span class="status-badge ' . $status_class . '">' . $status_text . '</span></div>';
				echo '<div class="col-title">';
				echo '<a href="' . esc_url( add_query_arg( array( 'action' => 'view', 'qna_id' => get_the_ID() ) ) ) . '">' . get_the_title() . '</a>';
				echo '</div>';
				echo '<div class="col-author">' . get_the_author() . '</div>';
				echo '<div class="col-date">' . get_the_date( 'Y-m-d' ) . '</div>';
				echo '</div>';
			}
			echo '</div>';

			// Pagination
			echo '<div class="ptg-pagination">';
			echo paginate_links( array(
				'total' => $query->max_num_pages,
				'current' => $paged,
			) );
			echo '</div>';
			
			wp_reset_postdata();
		} else {
			echo '<div class="ptg-no-data">';
			echo '<p>등록된 질문이 없습니다.</p>';
			echo '</div>';
		}
	}

	private function render_form() {
		if ( ! is_user_logged_in() ) {
			echo '<div class="ptg-login-required">';
			echo '<p>질문을 작성하려면 <a href="' . wp_login_url( get_permalink() ) . '">로그인</a>이 필요합니다.</p>';
			echo '</div>';
			return;
		}

		echo '<div class="ptg-qna-form-wrapper">';
		echo '<h3>질문 작성하기</h3>';
		echo '<form method="post" action="">';
		wp_nonce_field( 'ptg_qna_submit', 'ptg_qna_nonce' );
		echo '<input type="hidden" name="ptg_qna_action" value="submit_question">';
		
		echo '<div class="form-group">';
		echo '<label for="qna_title">제목</label>';
		echo '<input type="text" id="qna_title" name="qna_title" required placeholder="질문 제목을 입력해주세요">';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="qna_content">내용</label>';
		wp_editor( '', 'qna_content', array( 
			'media_buttons' => false, 
			'textarea_rows' => 10,
			'teeny' => true,
			'quicktags' => false
		) );
		echo '</div>';

		echo '<div class="form-actions">';
		echo '<button type="submit" class="ptg-btn ptg-btn-primary">등록하기</button>';
		echo '<a href="' . esc_url( remove_query_arg( 'action' ) ) . '" class="ptg-btn ptg-btn-secondary">취소</a>';
		echo '</div>';
		
		echo '</form>';
		echo '</div>';
	}

	private function render_single( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'ptg_qna' !== $post->post_type ) {
			echo '질문을 찾을 수 없습니다.';
			return;
		}

		$answer = get_post_meta( $post_id, '_ptg_qna_answer', true );
		$is_answered = ! empty( $answer );

		echo '<div class="ptg-qna-single">';
		
		// Header
		echo '<div class="qna-single-header">';
		echo '<div class="qna-status-wrap">';
		if ( $is_answered ) {
			echo '<span class="status-badge status-answered">답변완료</span>';
		} else {
			echo '<span class="status-badge status-waiting">대기중</span>';
		}
		echo '</div>';
		echo '<h3>' . esc_html( $post->post_title ) . '</h3>';
		echo '<div class="qna-meta">';
		echo '<span>작성자: ' . get_the_author_meta( 'display_name', $post->post_author ) . '</span>';
		echo '<span>날짜: ' . get_the_date( 'Y-m-d H:i', $post ) . '</span>';
		echo '</div>';
		echo '</div>';

		// Question Content
		echo '<div class="qna-content-box">';
		echo '<div class="box-label">질문 내용</div>';
		echo '<div class="qna-content">';
		echo apply_filters( 'the_content', $post->post_content );
		echo '</div>';
		echo '</div>';

		// Answer Section
		if ( $is_answered ) {
			echo '<div class="qna-answer-box">';
			echo '<div class="box-label">관리자 답변</div>';
			echo '<div class="answer-content">';
			echo wpautop( $answer );
			echo '</div>';
			echo '</div>';
		} else {
			echo '<div class="qna-no-answer">';
			echo '<p>아직 답변이 등록되지 않았습니다. 관리자가 확인 후 답변을 드릴 예정입니다.</p>';
			echo '</div>';
		}

		// Back button
		echo '<div class="qna-actions">';
		echo '<a href="' . esc_url( remove_query_arg( array( 'action', 'qna_id' ) ) ) . '" class="ptg-btn ptg-btn-secondary">목록으로 돌아가기</a>';
		echo '</div>';
		
		echo '</div>';
	}
}

PTG_Support::get_instance();
