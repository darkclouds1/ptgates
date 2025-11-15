<?php
/**
 * PTGates Platform Repository Class
 * 
 * 데이터베이스 읽기/쓰기 공통 레포지토리
 * $wpdb->prepare 사용하여 SQL 인젝션 방지
 */

namespace PTG\Platform;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class Repo {
    
    /**
     * 테이블 이름 접두사 (wpdb prefix 포함)
     */
    private static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . $table;
    }
    
    /**
     * 일반 쿼리 실행 (SELECT)
     * 
     * @param string $table 테이블 이름 (prefix 제외)
     * @param array $where WHERE 조건 배열 ['column' => 'value']
     * @param array $args 추가 옵션 (orderby, order, limit, offset 등)
     * @return array 결과 배열
     */
    public static function find($table, $where = array(), $args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name($table);
        $where_clause = '';
        $where_values = array();
        
        if (!empty($where)) {
            $where_parts = array();
            foreach ($where as $column => $value) {
                // 컬럼명은 백틱으로 이스케이프 (SQL 인젝션 방지)
                $column_escaped = '`' . esc_sql($column) . '`';

                if (is_null($value)) {
                    $where_parts[] = "{$column_escaped} IS NULL";
                    continue;
                }

                // 숫자 값은 %d, 문자열 값은 %s 플레이스홀더 사용
                if (is_numeric($value) && !is_float($value)) {
                    // 정수인 경우 %d 사용
                    $where_parts[] = "{$column_escaped} = %d";
                    $where_values[] = (int)$value;
                } else {
                    // 문자열이나 실수인 경우 %s 사용
                    $where_parts[] = "{$column_escaped} = %s";
                    $where_values[] = $value;
                }
            }
            $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
        }
        
        $orderby = isset($args['orderby']) ? $args['orderby'] : '';
        $order = isset($args['order']) ? strtoupper($args['order']) : 'ASC';
        $limit = isset($args['limit']) ? absint($args['limit']) : 0;
        $offset = isset($args['offset']) ? absint($args['offset']) : 0;
        
        $order_clause = '';
        if ($orderby) {
            // 컬럼명 이스케이프 (SQL 인젝션 방지)
            $orderby_escaped = '`' . esc_sql($orderby) . '`';
            $order_clause = sprintf('ORDER BY %s %s', $orderby_escaped, $order);
        }
        
        $limit_clause = '';
        if ($limit > 0) {
            $limit_clause = sprintf('LIMIT %d', $limit);
            if ($offset > 0) {
                $limit_clause .= sprintf(' OFFSET %d', $offset);
            }
        }
        
        // 테이블명은 esc_sql로 이스케이프 (sprintf 사용 시 % 문제 방지)
        $table_name_escaped = esc_sql($table_name);
        
        // sprintf를 사용하지 않고 직접 문자열 연결 (WHERE 절에 플레이스홀더 사용)
        $sql = "SELECT * FROM `{$table_name_escaped}`";
        if (!empty($where_clause)) {
            $sql .= ' ' . $where_clause;
        }
        if (!empty($order_clause)) {
            $sql .= ' ' . $order_clause;
        }
        if (!empty($limit_clause)) {
            $sql .= ' ' . $limit_clause;
        }
        
        // $wpdb->prepare 사용 (가변 인수이므로 스프레드 연산자 사용)
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, ...$where_values);
        }
        
        // 디버깅: 실제 실행될 SQL 로그 (prepare 후)
        if (defined('WP_DEBUG') && WP_DEBUG && false) { // 필요시 true로 변경
            error_log('[PTG Repo] SQL (prepare 후): ' . $sql);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // 디버깅: 결과 로그
        if (defined('WP_DEBUG') && WP_DEBUG && false) { // 필요시 true로 변경
            error_log('[PTG Repo] SQL 결과 수: ' . (is_array($results) ? count($results) : 0));
            if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                error_log('[PTG Repo] SQL 오류: ' . $wpdb->last_error);
            }
        }
        
        return $results;
    }
    
    /**
     * 단일 레코드 조회
     * 
     * @param string $table 테이블 이름
     * @param array $where WHERE 조건
     * @return array|null 단일 레코드 또는 null
     */
    public static function find_one($table, $where = array()) {
        $results = self::find($table, $where, array('limit' => 1));
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * 레코드 삽입
     * 
     * @param string $table 테이블 이름
     * @param array $data 삽입할 데이터
     * @return int|false 삽입된 ID 또는 false
     */
    public static function insert($table, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name($table);
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * 레코드 업데이트
     * 
     * @param string $table 테이블 이름
     * @param array $data 업데이트할 데이터
     * @param array $where WHERE 조건
     * @return int|false 영향받은 행 수 또는 false
     */
    public static function update($table, $data, $where) {
        global $wpdb;
        
        $table_name = self::get_table_name($table);
        return $wpdb->update($table_name, $data, $where);
    }
    
    /**
     * 레코드 삭제
     * 
     * @param string $table 테이블 이름
     * @param array $where WHERE 조건
     * @return int|false 삭제된 행 수 또는 false
     */
    public static function delete($table, $where) {
        global $wpdb;
        
        $table_name = self::get_table_name($table);
        return $wpdb->delete($table_name, $where);
    }
    
    /**
     * 집계 쿼리 (COUNT, SUM 등)
     * 
     * @param string $table 테이블 이름
     * @param string $function 집계 함수 (COUNT, SUM 등)
     * @param string $column 컬럼 이름
     * @param array $where WHERE 조건
     * @return int|float 집계 결과
     */
    public static function aggregate($table, $function, $column = '*', $where = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name($table);
        $where_clause = '';
        $where_values = array();
        
        if (!empty($where)) {
            $where_parts = array();
            foreach ($where as $col => $val) {
                $column_escaped = '`' . esc_sql($col) . '`';
                if (is_null($val)) {
                    $where_parts[] = "{$column_escaped} IS NULL";
                } elseif (is_numeric($val)) {
                    $where_parts[] = "{$column_escaped} = %d";
                    $where_values[] = $val;
                } else {
                    $where_parts[] = "{$column_escaped} = %s";
                    $where_values[] = $val;
                }
            }
            $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
        } else {
            $where_clause = '';
        }
        
        $table_name_escaped = esc_sql($table_name);
        $sql = "SELECT " . strtoupper($function) . "(" . ($column === '*' ? '*' : esc_sql($column)) . ") as aggregated FROM `{$table_name_escaped}`";
        if (!empty($where_clause)) {
            $sql .= ' ' . $where_clause;
        }
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, ...$where_values);
        }
        
        $result = $wpdb->get_var($sql);
        return $result !== null ? (int) $result : 0;
    }
    
    /**
     * 사용자별 데이터 조회 (권한 체크 포함)
     * 
     * @param string $table 테이블 이름
     * @param int $user_id 사용자 ID
     * @param array $where 추가 WHERE 조건
     * @param array $args 추가 옵션
     * @return array 결과 배열
     */
    public static function find_by_user($table, $user_id, $where = array(), $args = array()) {
        $where['user_id'] = absint($user_id);
        return self::find($table, $where, $args);
    }
    
    /**
     * 사용자 ID 강제 적용 (보안)
     * 현재 로그인한 사용자 ID 반환
     * 
     * @return int 사용자 ID (0 = 비로그인)
     */
    public static function get_current_user_id() {
        return get_current_user_id();
    }
}

