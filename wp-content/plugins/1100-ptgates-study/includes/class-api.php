<?php
namespace PTG\Study;

class Study_API {
    public static function register_routes() {
        // Basic routes scaffold
        add_action('rest_api_init', function() {
            register_rest_route('ptg-study/v1', '/courses', [
                'methods' => 'GET',
                'callback' => [ __CLASS__, 'get_courses' ],
                'permission_callback' => '__return_true',
            ]);
            register_rest_route('ptg-study/v1', '/courses/(?P<course_id>\d+)', [
                'methods' => 'GET',
                'callback' => [ __CLASS__, 'get_course_detail' ],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public static function get_courses($request) {
        // Simple placeholder data
        return [
            [ 'id' => 1, 'title' => 'Sample Course: Intro', 'description' => 'A placeholder course' ],
        ];
    }

    public static function get_course_detail($request) {
        $course_id = (int) $request['course_id'];
        // Return minimal data to satisfy frontend scaffolding
        return [ 'id' => $course_id, 'title' => 'Sample Course #'.$course_id, 'lessons' => [] ];
    }
}


