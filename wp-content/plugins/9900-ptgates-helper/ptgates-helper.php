<?php
/**
 * Plugin Name: 9900-PTGates Helper
 * Description: PTGates 사이트를 위한 자잘한 수정 및 스크립트 모음 (모바일 로고 링크 변경 등)
 * Version: 1.0.0
 * Author: PTGates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 모바일에서 로고 클릭 시 대시보드로 이동하는 스크립트 추가
 */
add_action( 'wp_footer', function() {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		// 로고 링크 선택자
		// 1. 기본 WP/Elementor/Theme 선택자들
		var selectors = [
			'a.custom-logo-link', 
			'.site-logo a', 
			'.elementor-branding a', 
			'.elementor-widget-theme-site-logo a' 
		];
		var logoLinks = Array.from(document.querySelectorAll(selectors.join(', ')));

		// 2. 이미지 클래스로 역추적 (img.ehp-header__site-logo)
		var specificLogoImg = document.querySelector('img.ehp-header__site-logo');
		if (specificLogoImg && specificLogoImg.closest('a')) {
			logoLinks.push(specificLogoImg.closest('a'));
		}
		
		// 중복 제거
		logoLinks = [...new Set(logoLinks)];

		if (logoLinks.length === 0) return;

		// PC에서는 기본 홈 URL, 모바일에서는 대시보드 URL
		var homeUrl = '<?php echo esc_url( home_url( '/' ) ); ?>';
		var dashboardUrl = '<?php echo esc_url( home_url( '/dashboard/' ) ); ?>';

		function updateLogoHref() {
			var isMobile = window.innerWidth <= 768;
			var targetUrl = isMobile ? dashboardUrl : homeUrl;

			logoLinks.forEach(function(link) {
				link.href = targetUrl;
			});
		}

		updateLogoHref();
		window.addEventListener('resize', updateLogoHref);
	});
	</script>
	<?php
});

/**
 * [임시] 마이그레이션 강제 실행
 * 관리자 페이지 로드 시 1회 실행 후 주석 처리 권장
 */
add_action('admin_init', function() {
    // 0000-ptgates-platform 플러그인의 Migration 클래스가 로드되었는지 확인
    if (class_exists('\\PTG\\Platform\\Migration')) {
        // run_migrations 메소드가 존재하는지 확인
        if (method_exists('\\PTG\\Platform\\Migration', 'run_migrations')) {
            // 마이그레이션 실행
            \PTG\Platform\Migration::run_migrations();
            
            // 실행 확인용 메시지 (디버그 모드인 경우)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTGates Helper] Migration forced via admin_init.');
            }
        }
    }
});
