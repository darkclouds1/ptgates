<?php
/**
 * PTGates Admin Members Class
 * 
 * 멤버십 관리 페이지 및 AJAX 처리
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PTG_Admin_Members {

	/**
	 * 멤버십 관리 페이지 렌더링
	 */
	/**
	 * 멤버십 관리 페이지 렌더링
	 */
	public static function render_page() {
		// 권한 확인 로직
		$is_wp_admin = current_user_can('manage_options');
		$is_pt_admin = false;
		
		if (class_exists('\PTG\Platform\Permissions')) {
			$is_pt_admin = \PTG\Platform\Permissions::is_pt_admin();
		}

		// 설정 저장 처리
		if (isset($_POST['ptg_settings_action']) && check_admin_referer('ptg_save_settings')) {
			self::save_settings();
		}

		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'users';

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">ptGates 멤버십 관리</h1>';

		// 탭 네비게이션
		$tabs = [
			'users' => '사용자 관리',
			'study' => '1100-Study 설정',
			'quiz'  => '1200-Quiz 설정',
			'flash' => '2200-Flash 설정'
		];

		echo '<h2 class="nav-tab-wrapper">';
		foreach ($tabs as $key => $title) {
			$class = ($active_tab === $key) ? 'nav-tab-active' : '';
			$url = add_query_arg(['page' => 'ptgates-admin-members', 'tab' => $key], admin_url('admin.php'));
			echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($class) . '">' . esc_html($title) . '</a>';
		}
		echo '</h2>';

		// 1. pt_admin이 아닌 WP 관리자에게는 등급 할당 폼만 표시 (사용자 관리 탭일 때만)
		if (!$is_pt_admin && $active_tab === 'users') {
			if ($is_wp_admin) {
				self::render_assignment_form();
			} else {
				wp_die('접근 권한이 없습니다. (pt_admin 등급 필요)');
			}
		} else {
			// 탭별 콘텐츠 렌더링
			if ($active_tab === 'users') {
				self::render_dashboard();
			} else {
				self::render_settings_tab($active_tab);
			}
		}
		
		echo '</div>';
	}

	/**
	 * 설정 탭 렌더링
	 */
	private static function render_settings_tab($tab) {
		$configs = [];
		$option_name = '';
		$descriptions = [];

		if ($tab === 'study') {
			$option_name = 'ptg_conf_study';
			$defaults = [
				'LIMIT_GUEST_VIEW' => 10,
				'LIMIT_FREE_VIEW' => 20,
				'MEMBERSHIP_URL' => '/membership'
			];
			$descriptions = [
				'LIMIT_GUEST_VIEW' => '비회원(Guest)이 하루에 볼 수 있는 문제 수 제한',
				'LIMIT_FREE_VIEW' => '무료회원(Basic)이 하루에 볼 수 있는 문제 수 제한',
				'MEMBERSHIP_URL' => '멤버십 안내 페이지 URL'
			];
			$configs = get_option($option_name, $defaults);
		} elseif ($tab === 'quiz') {
			$option_name = 'ptg_conf_quiz';
			$defaults = [
				'LIMIT_MOCK_EXAM' => 1,
				'LIMIT_QUIZ_QUESTIONS' => 20,
				'LIMIT_TRIAL_QUESTIONS' => 50,
				'MEMBERSHIP_URL' => '/membership'
			];
			$descriptions = [
				'LIMIT_MOCK_EXAM' => 'Basic(로그인 무료회원) 1일 모의고사 응시 횟수 제한',
				'LIMIT_QUIZ_QUESTIONS' => 'Basic(로그인 무료회원) 1일 일반 퀴즈 문제 수 제한',
				'LIMIT_TRIAL_QUESTIONS' => 'Trial(체험판) 회원 1일 일반 퀴즈 문제 수 제한',
				'MEMBERSHIP_URL' => '멤버십 안내 페이지 URL'
			];
			$configs = get_option($option_name, $defaults);
		} elseif ($tab === 'flash') {
			$option_name = 'ptg_conf_flash';
			$defaults = [
				'LIMIT_BASIC_CARDS' => 20,
				'LIMIT_TRIAL_CARDS' => 50,
				'MEMBERSHIP_URL' => '/membership'
			];
			$descriptions = [
				'LIMIT_BASIC_CARDS' => 'Basic(로그인 무료회원) 1일 암기카드 학습 제한',
				'LIMIT_TRIAL_CARDS' => 'Trial(체험판) 회원 1일 암기카드 학습 제한',
				'MEMBERSHIP_URL' => '멤버십 안내 페이지 URL'
			];
			$configs = get_option($option_name, $defaults);
		}

		if (empty($option_name)) return;

		?>
		<form method="post" action="">
			<?php wp_nonce_field('ptg_save_settings'); ?>
			<input type="hidden" name="ptg_settings_action" value="save">
			<input type="hidden" name="ptg_option_name" value="<?php echo esc_attr($option_name); ?>">
			
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ($configs as $key => $value) : ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($key); ?></label>
								<?php if (isset($descriptions[$key])) : ?>
									<p class="description" style="font-weight: normal; color: #666; margin-top: 4px;">
										<?php echo esc_html($descriptions[$key]); ?>
									</p>
								<?php endif; ?>
							</th>
							<td>
								<input name="ptg_settings[<?php echo esc_attr($key); ?>]" type="text" id="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * 설정 저장 처리
	 */
	private static function save_settings() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$option_name = sanitize_text_field($_POST['ptg_option_name']);
		$settings = isset($_POST['ptg_settings']) ? $_POST['ptg_settings'] : [];

		// Sanitize values
		$clean_settings = [];
		foreach ($settings as $key => $val) {
			$clean_settings[sanitize_text_field($key)] = sanitize_text_field($val);
		}

		update_option($option_name, $clean_settings);
		echo '<div class="notice notice-success is-dismissible"><p>설정이 저장되었습니다.</p></div>';
	}

	/**
	 * (기존) 관리자 등급 할당 폼 렌더링
	 */
	private static function render_assignment_form() {
		// 폼 처리 로직은 ptgates-admin.php에서 이동하거나 여기서 처리
		// 여기서는 UI만 렌더링하고 처리는 AJAX 또는 POST로
		
		// 간단한 메시지
		echo '<div class="notice notice-warning inline"><p>현재 <strong>pt_admin</strong> 등급이 아닙니다. 전체 멤버십 관리 기능을 사용하려면 먼저 자신에게 관리자 등급을 부여하세요.</p></div>';
		
		// 기존 폼 코드 재사용
		?>
		<div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
			<h2>관리자 등급 셀프 할당</h2>
			<form method="post" action="">
				<?php wp_nonce_field('ptg_update_member_grade'); ?>
				<input type="hidden" name="ptg_action" value="update_grade">
				<input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>">
				<input type="hidden" name="member_grade" value="pt_admin">
				<p>현재 로그인한 계정(<?php echo wp_get_current_user()->user_login; ?>)에 <code>pt_admin</code> 등급을 부여합니다.</p>
				<?php submit_button('관리자 등급 부여하기'); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * 전체 멤버십 관리 대시보드 렌더링
	 */
	private static function render_dashboard() {
		// 검색 및 필터
		$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
		$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		
		// 데이터 조회
		$data = self::get_members_list($page, 20, $search);
		$members = $data['items'];
		$total_pages = $data['total_pages'];
		$total_items = $data['total_items'];

		?>
		<!-- 상단 툴바 -->
		<div class="tablenav top">
			<form method="get">
				<input type="hidden" name="page" value="ptgates-admin-members">
				<div class="alignleft actions">
					<!-- 필터 추가 가능 -->
				</div>
				<div class="alignright actions">
					<p class="search-box">
						<label class="screen-reader-text" for="user-search-input">회원 검색:</label>
						<input type="search" id="user-search-input" name="s" value="<?php echo esc_attr($search); ?>">
						<input type="submit" id="search-submit" class="button" value="회원 검색">
					</p>
				</div>
			</form>
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo number_format($total_items); ?>개 항목</span>
				<?php
				// 페이지네이션 링크
				$page_links = paginate_links(array(
					'base' => add_query_arg('paged', '%#%'),
					'format' => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total' => $total_pages,
					'current' => $page
				));
				if ($page_links) {
					echo '<span class="pagination-links">' . $page_links . '</span>';
				}
				?>
			</div>
		</div>

		<!-- 테이블 -->
		<table class="wp-list-table widefat fixed striped table-view-list users">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-cb check-column"><input type="checkbox"></th>
					<th scope="col" class="manage-column column-username">사용자</th>
					<th scope="col" class="manage-column">등급</th>
					<th scope="col" class="manage-column">상태</th>
					<th scope="col" class="manage-column">만료일</th>
					<th scope="col" class="manage-column">누적 결제액</th>
					<th scope="col" class="manage-column">관리</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($members)) : ?>
					<tr><td colspan="7">데이터가 없습니다.</td></tr>
				<?php else : ?>
					<?php foreach ($members as $member) : ?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="users[]" value="<?php echo $member['user_id']; ?>"></th>
							<td class="username column-username">
								<strong><a href="<?php echo get_edit_user_link($member['user_id']); ?>"><?php echo esc_html($member['user_login']); ?></a></strong>
								<br><?php echo esc_html($member['user_email']); ?>
							</td>
							<td>
								<span class="ptg-badge ptg-grade-<?php echo esc_attr($member['member_grade']); ?>"><?php echo esc_html($member['member_grade']); ?></span>
								<a href="#" class="ptg-edit-grade-btn dashicons dashicons-edit" data-user-id="<?php echo $member['user_id']; ?>" title="등급 수정"></a>
							</td>
							<td>
								<span class="ptg-status-<?php echo esc_attr($member['billing_status']); ?>"><?php echo esc_html($member['billing_status']); ?></span>
							</td>
							<td>
								<?php echo $member['billing_expiry_date'] ? date('Y-m-d', strtotime($member['billing_expiry_date'])) : '-'; ?>
							</td>
							<td>
								<?php echo number_format($member['total_payments_krw']); ?> KRW
							</td>
							<td>
								<button type="button" class="button ptg-view-history-btn" data-user-id="<?php echo $member['user_id']; ?>">결제 이력</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- 모달: 등급 수정 -->
		<div id="ptg-grade-modal" class="ptg-modal" style="display:none;">
			<div class="ptg-modal-content">
				<span class="ptg-modal-close">&times;</span>
				<h2>멤버십 정보 수정</h2>
				<form id="ptg-grade-form">
					<input type="hidden" name="user_id" id="ptg-edit-user-id">
					<table class="form-table">
						<tr>
							<th><label for="ptg-edit-grade">등급</label></th>
							<td>
								<select name="member_grade" id="ptg-edit-grade">
									<option value="basic">Basic</option>
									<option value="premium">Premium</option>
									<option value="trial">Trial</option>
									<option value="pt_admin">ptGates Admin</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="ptg-edit-status">결제 상태</label></th>
							<td>
								<select name="billing_status" id="ptg-edit-status">
									<option value="active">Active</option>
									<option value="expired">Expired</option>
									<option value="pending">Pending</option>
									<option value="cancelled">Cancelled</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="ptg-edit-expiry">만료일</label></th>
							<td>
								<input type="date" name="billing_expiry_date" id="ptg-edit-expiry">
							</td>
						</tr>
					</table>
					<div class="ptg-modal-footer">
						<button type="submit" class="button button-primary">저장</button>
					</div>
				</form>
			</div>
		</div>

		<!-- 모달: 결제 이력 -->
		<div id="ptg-history-modal" class="ptg-modal" style="display:none;">
			<div class="ptg-modal-content wide">
				<span class="ptg-modal-close">&times;</span>
				<h2>결제 이력</h2>
				<div id="ptg-history-content">
					<p>로딩 중...</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 회원 목록 데이터 조회
	 */
	private static function get_members_list($page = 1, $per_page = 20, $search = '') {
		global $wpdb;
		
		$offset = ($page - 1) * $per_page;
		
		// 기본 쿼리: wp_users와 ptgates_user_member LEFT JOIN
		// ptgates_user_member가 없는 사용자도 있을 수 있으므로 LEFT JOIN 사용
		// 하지만 요구사항은 "통합 목록"이므로 ptgates_user_member가 있는 사용자를 우선으로 하거나
		// wp_users 기준으로 모두 보여주되 member 정보가 없으면 비워둠.
		// 여기서는 ptgates_user_member 테이블이 있는 사용자 위주로 보여주는 것이 관리 목적에 부합할 수 있으나,
		// "모든 사용자"를 관리해야 하므로 wp_users 기준이 맞음.
		// 그러나 성능상 ptgates_user_member 테이블을 기준으로 하는게 나을 수도 있음 (멤버십 관리니까).
		// 일단 ptgates_user_member 테이블 기준으로 조회 (로그인 시 자동 생성되므로 활성 유저는 다 있음)
		
		$sql = "SELECT m.*, u.user_login, u.user_email 
				FROM ptgates_user_member m
				JOIN {$wpdb->users} u ON m.user_id = u.ID";
		
		if ($search) {
			$search_esc = '%' . $wpdb->esc_like($search) . '%';
			$sql .= $wpdb->prepare(" WHERE u.user_login LIKE %s OR u.user_email LIKE %s", $search_esc, $search_esc);
		}
		
		$sql .= " ORDER BY m.created_at DESC";
		
		// 전체 개수
		$count_sql = "SELECT COUNT(*) FROM ($sql) as total";
		$total_items = $wpdb->get_var($count_sql);
		
		// 페이징
		$sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);
		
		$items = $wpdb->get_results($sql, ARRAY_A);
		
		return array(
			'items' => $items,
			'total_items' => $total_items,
			'total_pages' => ceil($total_items / $per_page)
		);
	}

	/**
	 * AJAX: 회원 상세 정보 조회
	 */
	public static function ajax_get_member() {
		self::check_permission();
		
		$user_id = intval($_POST['user_id']);
		global $wpdb;
		
		$member = $wpdb->get_row($wpdb->prepare("SELECT * FROM ptgates_user_member WHERE user_id = %d", $user_id), ARRAY_A);
		
		if ($member) {
			wp_send_json_success($member);
		} else {
			wp_send_json_error('회원 정보를 찾을 수 없습니다.');
		}
	}

	/**
	 * AJAX: 회원 정보 업데이트
	 */
	public static function ajax_update_member() {
		self::check_permission();
		
		$user_id = intval($_POST['user_id']);
		$data = array(
			'member_grade' => sanitize_text_field($_POST['member_grade']),
			'billing_status' => sanitize_text_field($_POST['billing_status']),
			'billing_expiry_date' => !empty($_POST['billing_expiry_date']) ? sanitize_text_field($_POST['billing_expiry_date']) : null
		);
		
		global $wpdb;
		$result = $wpdb->update('ptgates_user_member', $data, array('user_id' => $user_id));
		
		if ($result !== false) {
			wp_send_json_success('업데이트되었습니다.');
		} else {
			wp_send_json_error('업데이트 실패');
		}
	}

	/**
	 * AJAX: 결제 이력 조회
	 */
	public static function ajax_get_history() {
		self::check_permission();
		
		$user_id = intval($_POST['user_id']);
		global $wpdb;
		
		$history = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM ptgates_billing_history WHERE user_id = %d ORDER BY transaction_date DESC", 
			$user_id
		), ARRAY_A);
		
		ob_start();
		if (empty($history)) {
			echo '<p>결제 이력이 없습니다.</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr><th>날짜</th><th>상품명</th><th>금액</th><th>상태</th><th>주문번호</th></tr></thead>';
			echo '<tbody>';
			foreach ($history as $row) {
				echo '<tr>';
				echo '<td>' . esc_html($row['transaction_date']) . '</td>';
				echo '<td>' . esc_html($row['product_name']) . '</td>';
				echo '<td>' . number_format($row['amount']) . ' ' . esc_html($row['currency']) . '</td>';
				echo '<td>' . esc_html($row['status']) . '</td>';
				echo '<td>' . esc_html($row['order_id']) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		$html = ob_get_clean();
		
		wp_send_json_success($html);
	}

	/**
	 * 권한 체크 헬퍼
	 */
	private static function check_permission() {
		if (!class_exists('\PTG\Platform\Permissions') || !\PTG\Platform\Permissions::is_pt_admin()) {
			wp_send_json_error('권한이 없습니다.');
		}
	}
}
