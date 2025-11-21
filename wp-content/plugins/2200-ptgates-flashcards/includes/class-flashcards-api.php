<?php

if (!defined('ABSPATH')) {
    exit;
}

class PTG_Flashcards_API {
    public static function register_routes() {
        register_rest_route('ptg-flashcards/v1', '/cards', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_card'],
            'permission_callback' => [__CLASS__, 'check_auth']
        ]);

        register_rest_route('ptg-flashcards/v1', '/cards/(?P<id>\d+)/review', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'review_card'],
            'permission_callback' => [__CLASS__, 'check_auth']
        ]);
    }

    public static function check_auth() {
        return is_user_logged_in();
    }

    public static function create_card($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        if (empty($params['front']) || empty($params['back'])) {
            return new WP_Error('missing_params', 'Front and Back content are required', ['status' => 400]);
        }
        
        // Support both ref_id (new) and source_id (legacy)
        $ref_id = isset($params['ref_id']) ? intval($params['ref_id']) : (isset($params['source_id']) ? intval($params['source_id']) : 0);
        $ref_type = isset($params['ref_type']) ? sanitize_text_field($params['ref_type']) : (isset($params['subject']) ? 'question' : 'question');

        $card_id = PTG_Flashcards_DB::create_card([
            'user_id' => $user_id,
            'front' => sanitize_textarea_field($params['front']),
            'back' => wp_kses_post($params['back']), // Allow HTML for explanation
            'ref_id' => $ref_id,
            'ref_type' => $ref_type
        ]);

        if ($card_id) {
            return rest_ensure_response(['success' => true, 'id' => $card_id]);
        }

        return new WP_Error('db_error', 'Failed to create card', ['status' => 500]);
    }

    public static function review_card($request) {
        $card_id = $request->get_param('id');
        $params = $request->get_json_params();
        $rating = isset($params['rating']) ? intval($params['rating']) : 2; // Default Normal

        $result = PTG_Flashcards_DB::update_srs($card_id, $rating);

        if ($result !== false) {
            return rest_ensure_response(['success' => true]);
        }

        return new WP_Error('db_error', 'Failed to update card', ['status' => 500]);
    }
}
