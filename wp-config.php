<?php
/**
 * The base configuration for WordPress
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'ptgates' );

/** Database username */
define( 'DB_USER', 'ptgates' );

/** Database password */
define( 'DB_PASSWORD', 'w3m5935P@#21' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 * * ⚠️ 중요: 아래 값들은 https://api.wordpress.org/secret-key/1.1/salt/ 에서 
 * 새로 발급받은 값으로 반드시 덮어씌워 주세요! (현재 값은 예시일 뿐 보안에 취약함)
 */
define('AUTH_KEY',         'fJQ`Esi=Tp-W{?+HX_}=-(G}jjx6C,1CbA6zJg2A, )Q~uh@`njaDl(2<L<@VvZ$');
define('SECURE_AUTH_KEY',  'w2L:cy,occGdz,|N:t.@,_l:t)K.#w[ps,k&xs?,W+F}9{!FmrHzqwA]+*pnjN&F');
define('LOGGED_IN_KEY',    'GLx8i;?-oYQs2-DM}kKbPpS&eYh|`-2l/,+{J;p?7UDxK@=q`6J|#&CiN|MC1PTS');
define('NONCE_KEY',        'yBVm-Iv4{v@`Z-DO5T+oeaSim4+-eY^c>&Y_`Il>{,J~B9/-iHWdML`c>0CB? N`');
define('AUTH_SALT',        '-wTr2(Oi-X=pqdDIT(b=K8K-|0Yu-PTyPN-k@0lfTmTc/Rae^^Fp9gNM&,Z)D=S+');
define('SECURE_AUTH_SALT', '&@9~f+y0RD_3m0d+j&g-|F0Us`6TZs> o(;0)ScWHer(lYtjgei[46v-P7dIL[1D');
define('LOGGED_IN_SALT',   ':_Ls6ntE|@[ov0JaA_W!89c1J-HnT7%k><HF?{}Fc5z|5zBxzr&>m]%@po}]2.wL');
define('NONCE_SALT',       '>:]iwx+8!+i6`D0VtT6aYK|kVvR?!dJEP4!q%r.YOzSfl:$9Un?N~jU?TW|UOVpY');
/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 */
// 디버그 설정: 로그는 남기되, 화면에는 출력하지 않음 (보안 권장)
define( 'WP_DEBUG', true );
if ( WP_DEBUG ) {
    define( 'WP_DEBUG_LOG', true );
    define( 'WP_DEBUG_DISPLAY', false ); // 화면 출력 끔 (필수)
    @ini_set( 'display_errors', 0 );
}

/* Add any custom values between this line and the "stop editing" line. */

// 1. LiteSpeed 및 캐시 설정
define( 'WP_CACHE', true );

// 2. 메모리 제한 설정 (서버 최적화)
@ini_set( 'memory_limit', '512M' );
define( 'WP_MEMORY_LIMIT', '512M' ); // 워드프레스 상수도 같이 설정 추천

// 3. 파일 시스템 권한 (wp-settings.php 로드 전에 있어야 함)
define( 'FS_METHOD', 'direct' );

// 4. WP-Cron 비활성화 (서버 시스템 크론 사용 시)
// 위치 수정됨: wp-settings.php 보다 위에 있어야 작동함
define( 'DISABLE_WP_CRON', true );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

// 불필요한 하단 코드는 제거했습니다. (이미 wp-settings.php가 로드된 후라 효력 없음)