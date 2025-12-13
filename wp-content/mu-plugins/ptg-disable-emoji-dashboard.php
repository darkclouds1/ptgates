<?php
/*
Plugin Name: PTGates Disable Emoji on Dashboard
Description: Disables WP Emoji scripts and styles on dashboard pages for performance.
Version: 1.0
Author: PTGates
*/

// 1. Start Timing (Global)
global $ptg_server_timings;
$ptg_server_timings = [];

// Base start time (approximate server start)
$ptg_server_timings['start'] = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);

add_action('plugins_loaded', function() {
    global $ptg_server_timings;
    $ptg_server_timings['plugins_loaded'] = microtime(true);
}, 1);

add_action('setup_theme', function() {
    global $ptg_server_timings;
    $ptg_server_timings['setup_theme'] = microtime(true);
}, 1);

add_action('init', function() {
    global $ptg_server_timings;
    $ptg_server_timings['init'] = microtime(true);
}, 1);

add_action('rest_api_init', function() {
    global $ptg_server_timings;
    $ptg_server_timings['rest_api_init'] = microtime(true);
}, 1);

// Disable Emojis Logic
add_action('init', function () {
  // Apply to 'dashboard' (5100) and 'dashboard-new' (5110) pages
  // Adjust checks based on actual page slugs or IDs if needed.
  // Assuming slugs 'dashboard' and 'ptgates-dashboard-new' (or whatever page holds the shortcode).
  // The user suggested is_page(['dashboard', 'admin-dashboard']).
  // I will verify the page slug for [ptg_dashboard_new] later, but 'dashboard' is standard.
  // Also checking for URL segments might be safer if slugs vary.

  $request_uri = $_SERVER['REQUEST_URI'] ?? '';

  // Check commonly used dashboard slugs
  $is_dashboard = (
      is_page(['dashboard', 'my-dashboard', 'admin-dashboard']) ||
      strpos($request_uri, '/dashboard') !== false
  );

  if ($is_dashboard) {
      remove_action('wp_head', 'print_emoji_detection_script', 7);
      remove_action('admin_print_scripts', 'print_emoji_detection_script');
      remove_action('wp_print_styles', 'print_emoji_styles');
      remove_action('admin_print_styles', 'print_emoji_styles');
      remove_filter('the_content_feed', 'wp_staticize_emoji');
      remove_filter('comment_text_rss', 'wp_staticize_emoji');
      remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
      add_filter('emoji_svg_url', '__return_false'); // Keep this filter for completeness
  }
}, 1);
