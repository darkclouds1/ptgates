<?php
namespace PTG\Platform;

if (!defined('ABSPATH')) {
    exit;
}

class Payment {

    // PortOne V2 Config
    const V2_API_URL = 'https://api.portone.io/payments/';

    /**
     * 결제 준비 (트랜잭션 생성) - Step 1
     * 
     * @param int $user_id
     * @param string $product_code
     * @return array|WP_Error
     */
    public static function prepare_transaction($user_id, $product_code) {
        global $wpdb;

        // 1. 상품 조회
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM ptgates_products WHERE product_code = %s AND is_active = 1",
            $product_code
        ));

        if (!$product) {
            return new \WP_Error('invalid_product', '유효하지 않은 상품입니다.');
        }

        // 2. 주문 번호 생성 (OID) - Unique
        // Format: ptg_{timestamp}_{random}
        $oid = 'ptg_' . date('YmdHis') . '_' . wp_rand(1000, 9999);
        
        // 3. Billing History에 Pending 상태로 기록
        $history_table = 'ptgates_billing_history';
        $inserted = $wpdb->insert($history_table, [
            'user_id' => $user_id,
            'order_id' => $oid,
            'transaction_type' => 'purchase',
            'product_name' => $product->title,
            'payment_method' => 'card', // 기본값
            'amount' => $product->price,
            'currency' => 'KRW',
            'status' => 'pending',
            'transaction_date' => current_time('mysql'),
            // 'memo' => json_encode(['product_code' => $product_code])
             // PortOne V2 requires simple strings or objects, storing product_code in memo for recovery
             'memo' => json_encode(['product_code' => $product_code])
        ]);

        if ($inserted === false) {
            return new \WP_Error('db_error', '주문 생성 실패: ' . $wpdb->last_error);
        }

        // 4. PortOne V2 파라미터 반환
        $store_id = get_option('ptg_portone_store_id', '');
        $channel_key = get_option('ptg_portone_channel_key', '');
        
        // KCP V2 Bypass 및 추가 설정 (옵션 또는 하드코딩된 기본값 사용)
        $bypass = get_option('ptg_portone_bypass', []); 
        // 예시: ['kcp_v2' => ['site_logo' => '...', 'skin_indx' => 6]]
        // 옵션이 비어있으면 기본값 적용 (필요 시)
        if (empty($bypass)) {
             $bypass = [
                 'kcp_v2' => [
                     'site_name' => 'PTGates',
                     'skin_indx' => 1, 
                 ]
             ];
        }

        if (empty($store_id) || empty($channel_key)) {
            return new \WP_Error('config_error', '결제 설정(Store ID/Channel Key)이 누락되었습니다.');
        }

        $current_user = wp_get_current_user();

        // 리턴 데이터 (프론트엔드 SDK 전달용)
        $data = [
            'storeId' => $store_id,
            'channelKey' => $channel_key,
            'paymentId' => $oid,
            'orderName' => $product->title,
            'totalAmount' => (int)$product->price,
            'currency' => 'CURRENCY_KRW',
            'payMethod' => 'CARD', // Default
            'customer' => [
                 'fullName' => $current_user->display_name,
                 'email' => $current_user->user_email,
                 'phoneNumber' => get_user_meta($user_id, 'billing_phone', true) ?: '010-0000-0000', // PortOne V2 Required field
                 'id' => (string)$user_id, // 사용자 식별용
            ],
            'bypass' => $bypass,
            'redirectUrl' => home_url('/membership/'), // 모바일 결제 후 복귀 URL (필수는 아니지만 권장)
        ];

        return $data;
    }

    /**
     * 결제 검증 (Step 3) - Server to Server (PortOne V2)
     * 
     * @param string $payment_id (OID와 동일)
     * @return bool|WP_Error
     */
    public static function verify_payment($payment_id) {
        // 1. 설정 확인
        $api_secret = get_option('ptg_portone_api_secret', '');
        if (empty($api_secret)) {
            return new \WP_Error('config_error', 'API Secret이 설정되지 않았습니다.');
        }

        // 2. PortOne V2 API 호출 (GET /payments/{paymentId})
        $url = self::V2_API_URL . $payment_id;
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'PortOne ' . $api_secret,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
             return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($code !== 200 || empty($result)) {
            return new \WP_Error('api_error', 'PortOne API 호출 실패: ' . $body);
        }
        
        // 3. 검증 로직
        // $result 구조: { "status": "PAID", "amount": { "total": 1000, ... }, ... }
        $status = $result['status'] ?? '';
        $amount_data = $result['amount'] ?? [];
        $total_amount = $amount_data['total'] ?? 0;

        if ($status !== 'PAID') {
            return new \WP_Error('payment_not_paid', "결제 상태가 PAID가 아닙니다. (Status: $status)");
        }

        // DB에서 주문 정보 조회하여 금액 비교
        global $wpdb;
        $history_table = 'ptgates_billing_history';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $history_table WHERE order_id = %s",
            $payment_id
        ));

        if (!$transaction) {
             return new \WP_Error('invalid_order', '내부 주문 정보를 찾을 수 없습니다.');
        }

        // 금액 비교 (주의: 정수형 비교)
        if ((int)$transaction->amount !== (int)$total_amount) {
             return new \WP_Error('amount_mismatch', "결제 금액 불일치: 요청({$transaction->amount}) != 실결제({$total_amount})");
        }

        // 4. 완료 처리
        return self::complete_transaction($payment_id, $result);
    }

    /**
     * 결제 완료 처리 (DB 업데이트)
     * 
     * @param string $oid (payment_id)
     * @param array $pg_result PortOne API 결과
     * @return bool|WP_Error
     */
    public static function complete_transaction($oid, $pg_result) {
        global $wpdb;

        // 1. 주문 조회
        $history_table = 'ptgates_billing_history';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $history_table WHERE order_id = %s",
            $oid
        ));

        if (!$transaction) {
            return new \WP_Error('invalid_order', '주문 정보를 찾을 수 없습니다.');
        }
        
        if ($transaction->status === 'paid') {
            return true; // 이미 처리됨
        }

        // 3. DB 업데이트 (Paid)
        $memo_data = json_decode($transaction->memo, true);
        $product_code = $memo_data['product_code'] ?? '';
        
        // 상품 정보 재조회
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM ptgates_products WHERE product_code = %s",
            $product_code
        ));
        
        $duration_months = $product ? $product->duration_months : 1;
        $method_str = $pg_result['method']['type'] ?? 'CARD'; // V2 structure check needed, assuming 'method' object or similar
        // V2 Response: "method": { "type": "CARD", "card": { ... } } or simple method string depending on version? 
        // Docs say: selectedPaymentMethod... actually let's just save generic 'card' or parse if possible.
        // Let's stick to safe default or extracted value.

        // 트랜잭션 시작
        $wpdb->query('START TRANSACTION');

        try {
            // 3-1. Billing History 업데이트
            $wpdb->update($history_table, [
                'status' => 'paid',
                'pg_transaction_id' => $pg_result['id'] ?? '', // PortOne Payment ID acts as transaction ID
                'payment_method' => $method_str,
                // 'transaction_date'는 결제 시작일 유지
            ], ['order_id' => $oid]);

            // 3-2. User Membership 업데이트
            self::update_user_membership($transaction->user_id, $product, $duration_months);

            $wpdb->query('COMMIT');
            
            // 캐시 삭제
            $cache_key = 'ptg_dashboard_summary_' . $transaction->user_id;
            delete_transient($cache_key);
            
            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('db_error', 'DB 업데이트 중 오류 발생: ' . $e->getMessage());
        }
    }

    /**
     * 사용자 멤버십 기간 연장/갱신
     */
    private static function update_user_membership($user_id, $product, $months) {
        global $wpdb;
        $member_table = 'ptgates_user_member';
        
        // 기존 멤버십 조회
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $member_table WHERE user_id = %d",
            $user_id
        ));

        $now = current_time('mysql');
        $expiry_date = '';

        if ($existing && $existing->billing_status === 'active' && !empty($existing->billing_expiry_date) && $existing->billing_expiry_date > $now) {
            // 이미 활성 상태이면 기간 연장
            $base_date = $existing->billing_expiry_date;
             $expiry_date = date('Y-m-d H:i:s', strtotime("+$months months", strtotime($base_date)));
        } else {
            // 신규 또는 만료 후 갱신
            $expiry_date = date('Y-m-d H:i:s', strtotime("+$months months"));
        }

        // 등급 결정 (admin은 건드리지 않음)
        $new_grade = 'premium';
        if ($existing && $existing->member_grade === 'pt_admin') {
            $new_grade = 'pt_admin';
        }

        if ($existing) {
            $wpdb->update($member_table, [
                'member_grade' => $new_grade,
                'billing_status' => 'active',
                'billing_expiry_date' => $expiry_date,
                'total_payments_krw' => $existing->total_payments_krw + $product->price,
                'updated_at' => $now
            ], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($member_table, [
                'user_id' => $user_id,
                'membership_source' => 'individual',
                'member_grade' => $new_grade,
                'billing_status' => 'active',
                'billing_expiry_date' => $expiry_date,
                'total_payments_krw' => $product->price,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
    }

    /**
     * 결제 취소 (사용자 취소/실패 시 상태 업데이트)
     * 
     * @param string $oid
     * @param string $reason
     * @return bool|WP_Error
     */
    public static function cancel_transaction($oid, $reason = 'User Cancelled') {
        global $wpdb;

        // 1. 주문 조회
        $history_table = 'ptgates_billing_history';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $history_table WHERE order_id = %s",
            $oid
        ));

        if (!$transaction) {
            return new \WP_Error('invalid_order', '주문 정보를 찾을 수 없습니다.');
        }

        // 이미 완료된 건은 취소 불가 (별도 환불 로직 필요)
        if ($transaction->status === 'paid') {
            return new \WP_Error('invalid_status', '이미 결제 완료된 건입니다.');
        }

        // 2. DB 업데이트 (Cancelled)
        $result = $wpdb->update($history_table, [
            'status' => 'cancelled',
            'memo' => $transaction->memo . " | Cancelled: " . $reason 
        ], ['order_id' => $oid]);

        if ($result === false) {
            return new \WP_Error('db_error', 'DB 업데이트 실패');
        }

        return true;
    }

    /**
     * 결제 내역 삭제 (Pending/Cancelled/Failed 상태만 가능)
     * 
     * @param int $user_id
     * @param string $oid
     * @return bool|WP_Error
     */
    public static function delete_transaction($user_id, $oid) {
        global $wpdb;

        $history_table = 'ptgates_billing_history';
        
        // 1. 주문 조회 및 권한 확인
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $history_table WHERE order_id = %s AND user_id = %d",
            $oid,
            $user_id
        ));

        if (!$transaction) {
            // 주문이 없거나 권한이 없는 경우
            return new \WP_Error('invalid_order', '주문 정보를 찾을 수 없습니다.', ['status' => 404]);
        }

        // 2. 삭제 가능 상태 확인
        // paid, refunded는 삭제 불가
        if (in_array($transaction->status, ['paid', 'refunded'])) {
            return new \WP_Error('invalid_status', '결제 완료 또는 환불된 내역은 삭제할 수 없습니다.', ['status' => 400]);
        }

        // 3. 삭제
        $result = $wpdb->delete($history_table, ['order_id' => $oid]);

        if ($result === false) {
             error_log('[PTG DB Error] Delete failed: ' . $wpdb->last_error);
            return new \WP_Error('db_error', '삭제 실패: 데이터베이스 오류', ['status' => 500]);
        }

        return true;
    }
}

