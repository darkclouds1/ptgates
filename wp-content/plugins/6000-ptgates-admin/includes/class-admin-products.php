<?php
namespace PTG\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Products {

    /**
     * 상품 관리 페이지 렌더링
     */
    public static function render_page() {
        // 테이블 존재 확인 및 마이그레이션 (Safety Check)
        self::ensure_table_exists();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">상품 관리</h1>
            <button type="button" class="page-title-action" id="ptg-add-product-btn">상품 추가</button>
            <hr class="wp-header-end">

            <div id="ptg-product-manager-app">
                <div class="ptg-loading" style="display: none;">
                    <span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> 로딩 중...
                </div>

                <div class="ptg-product-list-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column column-cb check-column"><input type="checkbox"></th>
                                <th scope="col" style="width: 60px;">ID</th>
                                <th scope="col" style="width: 150px;">상품코드</th>
                                <th scope="col">상품명</th>
                                <th scope="col" style="width: 100px;">기간 (개월)</th>
                                <th scope="col" style="width: 120px;">가격</th>
                                <th scope="col" style="width: 80px;">추천포인트</th>
                                <th scope="col" style="width: 80px;">정렬</th>
                                <th scope="col" style="width: 80px;">상태</th>
                                <th scope="col" style="width: 150px;">관리</th>
                            </tr>
                        </thead>
                        <tbody id="ptg-product-list-body">
                            <!-- JS로 로드됨 -->
                        </tbody>
                    </table>
                </div>

                <!-- Add/Edit Modal -->
                <div id="ptg-product-modal" class="ptg-modal-overlay" style="display: none;">
                    <div class="ptg-modal">
                        <div class="ptg-modal-header">
                            <h3 id="ptg-modal-title">상품 추가</h3>
                            <button type="button" class="ptg-modal-close">×</button>
                        </div>
                        <div class="ptg-modal-body">
                            <form id="ptg-product-form">
                                <input type="hidden" name="id" id="prod-id">
                                
                                <div class="ptg-form-row">
                                    <div class="ptg-form-group half">
                                        <label for="prod-code">상품코드 (Unique)</label>
                                        <input type="text" name="product_code" id="prod-code" required placeholder="예: PREMIUM_1M">
                                        <p class="description">영문 대문자, 숫자, 언더바(_) 권장</p>
                                    </div>
                                    <div class="ptg-form-group half">
                                        <label for="prod-price">가격 (원)</label>
                                        <input type="number" name="price" id="prod-price" required min="0">
                                    </div>
                                </div>

                                <div class="ptg-form-group">
                                    <label for="prod-title">상품명</label>
                                    <input type="text" name="title" id="prod-title" required placeholder="예: Premium 1개월 멤버십">
                                </div>

                                <div class="ptg-form-group">
                                    <label for="prod-desc">상품 설명</label>
                                    <textarea name="description" id="prod-desc" rows="3"></textarea>
                                </div>

                                <div class="ptg-form-row">
                                    <div class="ptg-form-group half">
                                        <label for="prod-label">가격 라벨 (Price Label)</label>
                                        <input type="text" name="price_label" id="prod-label" placeholder="예: 월 9,900원">
                                    </div>
                                    <div class="ptg-form-group half">
                                        <label for="prod-duration">기간 (개월)</label>
                                        <input type="number" name="duration_months" id="prod-duration" required min="1" value="1">
                                    </div>
                                </div>

                                <div class="ptg-form-group">
                                    <label for="prod-features">트징/혜택 (한 줄에 하나씩 입력)</label>
                                    <textarea name="features" id="prod-features" rows="5" placeholder="무제한 문제 풀이&#13;&#10;무제한 암기카드&#13;&#10;..."></textarea>
                                </div>

                                <div class="ptg-form-row">
                                    <div class="ptg-form-group half">
                                        <label for="prod-featured">추천 등급 (Featured Level)</label>
                                        <input type="number" name="featured_level" id="prod-featured" value="0" min="0">
                                        <p class="description">높을수록 강조됨 (1 이상)</p>
                                    </div>
                                    <div class="ptg-form-group half">
                                        <label for="prod-sort">정렬 순서</label>
                                        <input type="number" name="sort_order" id="prod-sort" value="0">
                                    </div>
                                </div>

                                <div class="ptg-form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" name="is_active" id="prod-active" value="1" checked> 활성화 (판매 중)
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="ptg-modal-footer">
                            <button type="button" class="button button-primary" id="ptg-save-product-btn">저장</button>
                            <button type="button" class="button" id="ptg-modal-cancel">취소</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
                /* Simple Modal Styles */
                .ptg-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; justify-content: center; align-items: center; }
                .ptg-modal { background: #fff; width: 600px; max-width: 90%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 5px 30px rgba(0,0,0,0.3); border-radius: 4px; }
                .ptg-modal-header { padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
                .ptg-modal-header h3 { margin: 0; }
                .ptg-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
                .ptg-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
                .ptg-modal-footer { padding: 15px 20px; border-top: 1px solid #ddd; background: #fcfcfc; text-align: right; }
                
                .ptg-form-row { display: flex; gap: 20px; margin-bottom: 15px; }
                .ptg-form-group { margin-bottom: 15px; flex: 1; }
                .ptg-form-group.half { width: 50%; }
                .ptg-form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
                .ptg-form-group input[type="text"], 
                .ptg-form-group input[type="number"], 
                .ptg-form-group textarea { width: 100%; border: 1px solid #8c8f94; border-radius: 4px; padding: 4px 8px; }
                .ptg-form-group textarea { line-height: 1.4; }
                
                .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
                .status-active { background: #dcfce7; color: #166534; }
                .status-inactive { background: #f3f4f6; color: #6b7280; }
            </style>
        </div>
        <?php
    }

    /**
     * AJAX: 상품 목록 조회
     */
    public static function ajax_get_products() {
        check_ajax_referer( 'wp_rest', 'security' );
        
        global $wpdb;
        $table_name = 'ptgates_products';
        
        $results = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY sort_order ASC, duration_months ASC" );
        
        // JSON 디코딩
        foreach ($results as &$row) {
            $row->features = json_decode($row->features_json);
        }
        
        wp_send_json_success( $results );
    }

    /**
     * AJAX: 상품 저장 (추가/수정)
     */
    public static function ajax_save_product() {
        check_ajax_referer( 'wp_rest', 'security' );
        
        global $wpdb;
        $table_name = 'ptgates_products';
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $data = [
            'product_code' => sanitize_text_field($_POST['product_code']),
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'price' => intval($_POST['price']),
            'price_label' => sanitize_text_field($_POST['price_label']),
            'duration_months' => intval($_POST['duration_months']),
            'featured_level' => intval($_POST['featured_level']),
            'sort_order' => intval($_POST['sort_order']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Features JSON 처리
        $features_raw = isset($_POST['features']) ? $_POST['features'] : '';
        $features_list = array_filter(array_map('trim', explode("\n", $features_raw)));
        $data['features_json'] = json_encode(array_values($features_list), JSON_UNESCAPED_UNICODE);
        
        $format = ['%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%d'];
        
        if ($id > 0) {
            // Update
            $result = $wpdb->update($table_name, $data, ['id' => $id], $format, ['%d']);
            if ($result === false) {
                wp_send_json_error('DB 업데이트 실패: ' . $wpdb->last_error);
            }
        } else {
            // Insert
            $result = $wpdb->insert($table_name, $data, $format);
            if ($result === false) {
                wp_send_json_error('DB 저장 실패: ' . $wpdb->last_error);
            }
        }
        
        wp_send_json_success();
    }

    /**
     * AJAX: 상품 삭제
     */
    public static function ajax_delete_product() {
        check_ajax_referer( 'wp_rest', 'security' );
        
        global $wpdb;
        $table_name = 'ptgates_products';
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error('Invalid ID');
        }
        
        $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
        
        if ($result === false) {
            wp_send_json_error('삭제 실패');
        }
        
        wp_send_json_success();
    }

    /**
     * AJAX: 상태 토글
     */
    public static function ajax_toggle_product_status() {
        check_ajax_referer( 'wp_rest', 'security' );
        
        global $wpdb;
        $table_name = 'ptgates_products';
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = isset($_POST['status']) ? intval($_POST['status']) : 0;
        
        $result = $wpdb->update($table_name, ['is_active' => $status], ['id' => $id], ['%d'], ['%d']);
        
        if ($result === false) {
            wp_send_json_error('상태 변경 실패');
        }
        
        wp_send_json_success();
    }

    /**
     * Helper: 테이블 존재 확인 (마이그레이션 trigger)
     */
    private static function ensure_table_exists() {
        global $wpdb;
        $table_name = 'ptgates_products';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 테이블이 없으면 Migration 실행
            if (class_exists('\PTG\Platform\Migration')) {
                \PTG\Platform\Migration::run_migrations();
            }
        }
    }
}
