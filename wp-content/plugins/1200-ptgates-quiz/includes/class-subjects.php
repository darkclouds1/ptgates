<?php
/**
 * PTGates Quiz - 교시 / 과목 / 세부과목 정의 (DB 기반)
 *
 * - 데이터 출처: ptgates_subject, ptgates_categories
 * - 플랫폼(0000-ptgates-platform) 제공 클래스를 우선 사용하며,
 *   미로딩 시 동일한 로직을 여기서 로드하여 하드코딩 MAP 의존을 제거.
 */

namespace PTG\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 이미 로드되었으면 중단
if ( class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
    return;
}

// 플랫폼 코어에 있는 공통 Subjects 클래스를 우선 로드
$platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
if ( file_exists( $platform_subjects_file ) && is_readable( $platform_subjects_file ) ) {
    require_once $platform_subjects_file;
    if ( class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
        return;
    }
}

/**
 * Fallback: 플랫폼 파일을 찾지 못했을 때를 대비한 DB 기반 구현
 */
class Subjects {
    private static $map = null;

    public static function init() {
        if ( self::$map === null ) {
            self::load_map();
        }
    }

    private static function load_map() {
        global $wpdb;

        $rows = $wpdb->get_results( "SELECT * FROM ptgates_subject ORDER BY course_no ASC, id ASC", ARRAY_A );
        $map  = [];

        if ( $rows ) {
            foreach ( $rows as $row ) {
                $course_num  = (int) $row['course_no'];
                $category    = $row['category'];
                $subcategory = $row['subcategory'];
                $questions   = (int) $row['questions'];

                if ( ! isset( $map[ $course_num ] ) ) {
                    $map[ $course_num ] = [
                        'total'    => 0,
                        'subjects' => [],
                    ];
                }

                if ( ! isset( $map[ $course_num ]['subjects'][ $category ] ) ) {
                    $map[ $course_num ]['subjects'][ $category ] = [
                        'total' => 0,
                        'subs'  => [],
                        'codes' => [],
                    ];
                }

                if ( is_null( $subcategory ) || $subcategory === '' ) {
                    // 합계행은 무시 (총계는 세부과목 합으로 계산)
                } else {
                    $map[ $course_num ]['subjects'][ $category ]['subs'][ $subcategory ]  = $questions;
                    $map[ $course_num ]['subjects'][ $category ]['codes'][ $subcategory ] = $subcategory;
                    $map[ $course_num ]['subjects'][ $category ]['total']                += $questions;
                }
            }

            foreach ( $map as $c_num => &$c_data ) {
                $session_total = 0;
                foreach ( $c_data['subjects'] as $cat_name => &$cat_data ) {
                    if ( $cat_data['total'] === 0 && ! empty( $cat_data['subs'] ) ) {
                        $cat_data['total'] = array_sum( $cat_data['subs'] );
                    }
                    $session_total += $cat_data['total'];
                }
                $c_data['total'] = $session_total;
            }
        }

        self::$map = $map;
    }

    public static function get_sessions(): array {
        self::init();
        return array_keys( self::$map );
    }

    public static function get_subjects_for_session( int $session ): array {
        self::init();
        if ( ! isset( self::$map[ $session ]['subjects'] ) ) {
            return [];
        }
        return array_keys( self::$map[ $session ]['subjects'] );
    }

    public static function get_subsubjects( int $session, string $subject ): array {
        self::init();
        if ( ! isset( self::$map[ $session ]['subjects'][ $subject ]['subs'] ) ) {
            return [];
        }
        return array_keys( self::$map[ $session ]['subjects'][ $subject ]['subs'] );
    }

    public static function get_count( int $session, string $subject, string $subsubject ): ?int {
        self::init();
        if ( ! isset( self::$map[ $session ]['subjects'][ $subject ]['subs'][ $subsubject ] ) ) {
            return null;
        }
        return (int) self::$map[ $session ]['subjects'][ $subject ]['subs'][ $subsubject ];
    }

    public static function get_code( int $session, string $subject, string $subsubject ): ?string {
        self::init();
        if ( ! isset( self::$map[ $session ]['subjects'][ $subject ]['codes'][ $subsubject ] ) ) {
            return null;
        }
        return self::$map[ $session ]['subjects'][ $subject ]['codes'][ $subsubject ];
    }

    public static function get_subject_from_subsubject( string $subsubject ): ?string {
        global $wpdb;

        $needle = trim( (string) $subsubject );
        if ( $needle === '' ) {
            return null;
        }

        $table_name = 'ptgates_categories';
        $query      = $wpdb->prepare(
            "SELECT subject_category FROM {$table_name} WHERE subject = %s AND subject_category IS NOT NULL AND subject_category != '' LIMIT 1",
            $needle
        );

        return $wpdb->get_var( $query );
    }

    public static function get_distribution_ratio( string $subject_code ): float {
        self::init();

        $total_questions_all = 0;
        $target_count        = 0;

        foreach ( self::$map as $session_data ) {
            foreach ( $session_data['subjects'] as $main_sub ) {
                foreach ( $main_sub['subs'] as $sub_name => $count ) {
                    $total_questions_all += $count;
                    $code = $main_sub['codes'][ $sub_name ] ?? '';
                    if ( $code === $subject_code ) {
                        $target_count = $count;
                    }
                }
            }
        }

        if ( $total_questions_all === 0 ) {
            return 0.0;
        }

        return $target_count / $total_questions_all;
    }

    public static function get_questions_for_exam( int $n, string $subject_code ): int {
        $ratio = self::get_distribution_ratio( $subject_code );
        return (int) round( $ratio * $n );
    }

    public static function get_map() {
        self::init();
        return self::$map;
    }
}
