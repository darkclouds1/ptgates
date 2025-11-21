<?php
/**
 * Plugin Name: 2200-ptGates Flashcards
 * Description: Spaced Repetition System (SRS) Flashcards module for ptGates.
 * Version: 1.0.0
 * Author: ptGates
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PTG_FLASHCARDS_VERSION', '1.0.0');
define('PTG_FLASHCARDS_PATH', plugin_dir_path(__FILE__));
define('PTG_FLASHCARDS_URL', plugin_dir_url(__FILE__));
require_once PTG_FLASHCARDS_PATH . 'includes/class-api.php';

class PTG_Flashcards_Plugin {
    public function __construct() {
        // register_activation_hook(__FILE__, [__CLASS__, 'activate']); // Activation hook removed
        
        add_action('rest_api_init', ['PTG\\Flashcards\\API', 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_modal']);
    }

    // activate method removed

    // check_tables method removed

    public function enqueue_assets() {
        // Only enqueue on pages where quiz or study might be active, or globally for now
        wp_enqueue_style('ptg-flashcards-css', PTG_FLASHCARDS_URL . 'assets/css/flashcards.css', [], PTG_FLASHCARDS_VERSION);
        wp_enqueue_script('ptg-flashcards-js', PTG_FLASHCARDS_URL . 'assets/js/flashcards.js', ['jquery'], PTG_FLASHCARDS_VERSION, true);
        
        wp_localize_script('ptg-flashcards-js', 'ptgFlashcards', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id()
        ]);
    }

    public function render_modal() {
        // Render the "Create Flashcard" modal in the footer
        ?>
        <div id="ptg-flashcard-modal" class="ptg-flashcard-modal" style="display: none;">
            <div class="ptg-flashcard-modal-overlay"></div>
            <div class="ptg-flashcard-modal-content">
                <div class="ptg-flashcard-modal-header">
                    <h3>암기카드 생성</h3>
                    <button class="ptg-flashcard-modal-close">&times;</button>
                </div>
                <div class="ptg-flashcard-modal-body">
                    <form id="ptg-flashcard-form">
                        <div class="ptg-form-group">
                            <label>앞면 (질문)</label>
                            <textarea id="ptg-card-front" rows="4" required></textarea>
                        </div>
                        <div class="ptg-form-group">
                            <label>뒷면 (정답/해설)</label>
                            <textarea id="ptg-card-back" rows="6" required></textarea>
                        </div>
                        <input type="hidden" id="ptg-card-source-id">
                        <input type="hidden" id="ptg-card-subject">
                        <div class="ptg-form-actions">
                            <button type="submit" class="ptg-btn-primary">카드 만들기</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}

new PTG_Flashcards_Plugin();
