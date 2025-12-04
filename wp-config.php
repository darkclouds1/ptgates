<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'ptgates' );

/** Database username */
define( 'DB_USER', 'ptgates' );

/** Database password */
define( 'DB_PASSWORD', ')ZPN07xSn6R87-tH' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'SECURE_AUTH_KEY',  'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'LOGGED_IN_KEY',    'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'NONCE_KEY',        'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'AUTH_SALT',        'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'SECURE_AUTH_SALT', 'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'LOGGED_IN_SALT',   'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );
define( 'NONCE_SALT',       'N8JHk4yZxq2Vt7WcP3mE0AydR5uH9LbQf6Ts1nM2XvYZaCwGDeKiBoUpjLrS' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */

    define( 'WP_DEBUG', true );
    if (WP_DEBUG) {
        define( 'WP_DEBUG_LOG', true );
        define( 'WP_DEBUG_DISPLAY', true );
    }


/* Add any custom values between this line and the "stop editing" line. */

// 개발 중 캐시 비활성화
define( 'LITESPEED_DISABLE_ALL', true ); // LiteSpeed Cache 완전 비활성화
define( 'WP_CACHE', true ); // WordPress 캐시 활성화

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

// Disable direct file access
define( 'FS_METHOD', 'direct' );

// 개발 중: LiteSpeed Cache 플러그인 강제 비활성화 (wp-settings.php 로드 후)
add_filter( 'option_active_plugins', function( $plugins ) {
    if ( is_array( $plugins ) ) {
        $plugins = array_filter( $plugins, function( $plugin ) {
            return stripos( $plugin, 'litespeed' ) === false;
        } );
    }
    return $plugins;
}, 1 );

// LiteSpeed Cache 관리자 메뉴 제거
add_action( 'admin_menu', function() {
    remove_menu_page( 'litespeed' );
    remove_menu_page( 'litespeed-settings' );
    remove_menu_page( 'litespeed-toolbox' );
}, 999 );

define('DISABLE_WP_CRON', true);
