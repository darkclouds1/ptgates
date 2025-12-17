<?php
namespace PTG\Platform;

if (!defined('ABSPATH')) {
    exit;
}

class Payment {

    // KG Inicis Config (Defaults for Test)
    const MID_PC_DEFAULT = 'INIpayTest'; 
    const MID_MO_DEFAULT = 'INIpayTest'; 
    const SIGN_KEY_DEFAULT = 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS'; 
    const URL_STD_PAY_DEFAULT = 'https://stgstdpay.inicis.com/stdjs/INIStdPay.js';

    /**
     * 결제 준비 (트랜잭션 생성 및 시그니처 생성) - Step 1
     * 
     * @param int $user_id
     * @param string $product_code
     * @param string $device_type 'pc' or 'mobile'
     * @param string $site_url 사이트 도메인 (리턴 URL용)
     * @return array|WP_Error
     */
    public static function prepare_transaction($user_id, $product_code, $device_type = 'pc', $site_url = '') {
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
            'memo' => json_encode(['product_code' => $product_code, 'device' => $device_type])
        ]);

        if ($inserted === false) {
            return new \WP_Error('db_error', '주문 생성 실패: ' . $wpdb->last_error);
        }

        // 4. KG Inicis 파라미터 생성 (Dynamic Settings)
        $mid = get_option('ptg_payment_mid_pc', self::MID_PC_DEFAULT);
        
        $retrieved_sign_key = get_option('ptg_payment_sign_key', self::SIGN_KEY_DEFAULT);
        // Force fix if old wrong key is stored in DB
        if ($retrieved_sign_key === 'SU5JTGl0ZV90cmlwbGVkZXNfa2V5U3Ry') {
            $signKey = self::SIGN_KEY_DEFAULT; // Correct: SU5JTElURV9UUklQTEVERVNfS0VZU1RS
        } else {
            $signKey = $retrieved_sign_key;
        }

        $price = $product->price;
        // $timestamp = time() * 1000; // Legacy
        $timestamp = round(microtime(true) * 1000); // 밀리초 타임스탬프 (High Precision)

        // Signature (Step 1): SHA256(oid + price + timestamp)
        $signature_string = "oid={$oid}&price={$price}&timestamp={$timestamp}";
        $signature = hash('sha256', $signature_string);

        // Verification (Step 1): SHA256(oid + price + signKey + timestamp)
        $verification_string = "oid={$oid}&price={$price}&signKey={$signKey}&timestamp={$timestamp}";
        $verification = hash('sha256', $verification_string);

        // mKey: SHA256(signKey)
        $mKey = hash('sha256', $signKey);

        // 리턴 데이터 (프론트엔드 Form 매핑용)
        $data = [
            'version' => '1.0',
            'gopaymethod' => 'Card', // 기본 카드
            'mid' => $mid,
            'oid' => $oid,
            'price' => $price,
            'goodname' => $product->title,
            'buyername' => wp_get_current_user()->display_name,
            'buyeremail' => wp_get_current_user()->user_email,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'verification' => $verification,
            'mKey' => $mKey,
            'currency' => 'WON',
            'use_chkfake' => 'Y', // PC 보안강화
            'acceptmethod' => 'centerCd(Y)', // 필수 옵션
            'returnUrl' => $site_url . '/ptg-payment-return', 
            'closeUrl' => $site_url . '/ptg-payment-close', 
            // Mobile Specific
            'P_NEXT_URL' => $site_url . '/ptg-payment-return-mobile',
        ];

        return $data;
    }

    /**
     * 승인 요청 (Step 3) - Server to Server
     * 
     * @param array $params Step 2 인증결과 파라미터
     * @return bool|WP_Error
     */
    public static function approve_transaction($params) {
        // 1. 필수 파라미터 확인
        if (empty($params['authUrl']) || empty($params['authToken'])) {
            return new \WP_Error('invalid_params', '승인 요청 정보가 부족합니다.');
        }

        $authUrl = $params['authUrl'];
        // 보안상 여기서 authUrl이 이니시스 도메인인지 체크하는 것이 좋음 (fcstdpay.inicis.com, stgstdpay.inicis.com 등)
        
        $mid = get_option('ptg_payment_mid_pc', self::MID_PC_DEFAULT);
        
        $retrieved_sign_key = get_option('ptg_payment_sign_key', self::SIGN_KEY_DEFAULT);
        if ($retrieved_sign_key === 'SU5JTGl0ZV90cmlwbGVkZXNfa2V5U3Ry') {
            $signKey = self::SIGN_KEY_DEFAULT;
        } else {
            $signKey = $retrieved_sign_key;
        }
        
        $authToken = $params['authToken'];
        // $timestamp = time() * 1000;
        $timestamp = round(microtime(true) * 1000);

        // Signature (Step 3): SHA256(authToken + timestamp)
        $signature = hash('sha256', "authToken={$authToken}&timestamp={$timestamp}");
        
        // Verification (Step 3): SHA256(authToken + signKey + timestamp)
        $verification = hash('sha256', "authToken={$authToken}&signKey={$signKey}&timestamp={$timestamp}");

        // 2. 승인 요청 (POST)
        $request_args = [
            'body' => [
                'mid' => $mid,
                'authToken' => $authToken,
                'signature' => $signature,
                'verification' => $verification,
                'timestamp' => $timestamp,
                'charset' => 'UTF-8',
                'format' => 'JSON'
            ],
            'timeout' => 30,
            'sslverify' => false // 개발/테스트 환경용. 운영 시 true 권장
        ];

        $response = wp_remote_post($authUrl, $request_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!$result) {
            return new \WP_Error('api_error', '승인 응답 파싱 실패: ' . $body);
        }

        // 3. 승인 결과 확인 및 처리
        $oid = $params['oid'] ?? ($params['orderNumber'] ?? ''); // Return params의 orderNumber 사용 가능
        
        // complete_transaction 호출로 통합 처리
        return self::complete_transaction($oid, $result);
    }

    /**
     * 결제 완료 처리 (DB 업데이트) - Step 4
     * 
     * @param string $oid
     * @param array $pg_result PG사 최종 승인 결과 데이터
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

        // 2. 결과 코드 확인
        $is_success = isset($pg_result['resultCode']) && $pg_result['resultCode'] === '0000';
        
        if (!$is_success) {
             $wpdb->update($history_table, [
                'status' => 'failed',
                'memo' => $transaction->memo . ' | Fail: ' . ($pg_result['resultMsg'] ?? 'Unknown Error')
            ], ['order_id' => $oid]);
            return new \WP_Error('payment_failed', '결제 승인 실패: ' . ($pg_result['resultMsg'] ?? ''));
        }

        // 3. DB 업데이트 (Paid)
        $memo_data = json_decode($transaction->memo, true);
        $product_code = $memo_data['product_code'];
        
        // 상품 정보 재조회
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM ptgates_products WHERE product_code = %s",
            $product_code
        ));
        
        $duration_months = $product ? $product->duration_months : 1;

        // 트랜잭션 시작
        $wpdb->query('START TRANSACTION');

        try {
            // 3-1. Billing History 업데이트
            $wpdb->update($history_table, [
                'status' => 'paid',
                'pg_transaction_id' => $pg_result['tid'] ?? '', // 결과 tid
                'payment_method' => $pg_result['payMethod'] ?? 'card',
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
}
