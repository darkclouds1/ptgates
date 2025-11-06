/**
 * PTGates Platform - 공통 JavaScript 헬퍼
 * 
 * 모든 모듈에서 사용할 수 있는 공통 REST API 헬퍼 함수
 */

(function() {
    'use strict';
    
    // alert 차단 (중복 재정의 에러 방지: 단순 대입만 시도)
    if (typeof window !== 'undefined') {
        try { window.alert = function() { return false; }; } catch (e) {}
    }
    
    // 전역 네임스페이스
    window.PTGPlatform = window.PTGPlatform || {};
    
    // 설정 (wp_localize_script로 주입됨)
    const config = typeof ptgPlatform !== 'undefined' ? ptgPlatform : {
        restUrl: '/wp-json/',  // WordPress REST API 기본 URL
        nonce: '',
        userId: 0,
        timezone: 'Asia/Seoul'
    };
    
    /**
     * REST API 요청 헬퍼
     * 
     * @param {string} endpoint 엔드포인트
     * @param {object} options 요청 옵션
     * @returns {Promise} 응답 프로미스
     */
    PTGPlatform.apiRequest = async function(endpoint, options = {}) {
        // 엔드포인트 앞의 슬래시 제거 및 URL 구성
        const cleanEndpoint = endpoint.replace(/^\//, '');
        const url = config.restUrl + cleanEndpoint;
        
        // 디버깅: 실제 요청 URL 확인
        if (typeof console !== 'undefined' && console.log) {
            console.log('[PTG Platform] API 요청:', {
                endpoint: endpoint,
                cleanEndpoint: cleanEndpoint,
                restUrl: config.restUrl,
                fullUrl: url,
                method: options.method || 'GET'
            });
        }
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        try {
            const response = await fetch(url, mergedOptions);
            
            // 응답이 JSON이 아닐 수 있으므로 체크
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(`예상치 못한 응답 형식: ${text}`);
            }
            
            if (!response.ok) {
                // WordPress REST API 표준 에러 형식 처리
                const errorMessage = data.message || 
                                   (data.data && data.data.status ? `HTTP ${data.data.status}: ${data.data.message || 'Unknown error'}` : null) ||
                                   `HTTP ${response.status}: ${response.statusText}`;
                const error = new Error(errorMessage);
                error.status = response.status;
                error.data = data;
                throw error;
            }
            
            return data;
        } catch (error) {
            // 네트워크 오류 또는 기타 오류
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                console.error('PTG Platform API 네트워크 오류:', error);
                throw new Error('네트워크 연결에 실패했습니다. 인터넷 연결을 확인해주세요.');
            }
            
            // 이미 처리된 에러는 그대로 전달
            if (error.status || error.data) {
                console.error('PTG Platform API 오류:', error);
                throw error;
            }
            
            // 기타 오류
            console.error('PTG Platform API 오류:', error);
            throw error;
        }
    };
    
    /**
     * GET 요청
     */
    PTGPlatform.get = function(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return PTGPlatform.apiRequest(url, { method: 'GET' });
    };
    
    /**
     * POST 요청
     */
    PTGPlatform.post = function(endpoint, data = {}) {
        return PTGPlatform.apiRequest(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    };
    
    /**
     * PATCH 요청
     */
    PTGPlatform.patch = function(endpoint, data = {}) {
        return PTGPlatform.apiRequest(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    };
    
    /**
     * PUT 요청
     */
    PTGPlatform.put = function(endpoint, data = {}) {
        return PTGPlatform.apiRequest(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    };
    
    /**
     * DELETE 요청
     */
    PTGPlatform.delete = function(endpoint) {
        return PTGPlatform.apiRequest(endpoint, { method: 'DELETE' });
    };
    
    /**
     * 디바운스 함수
     * 
     * @param {Function} func 실행할 함수
     * @param {number} wait 대기 시간 (ms)
     * @returns {Function} 디바운스된 함수
     */
    PTGPlatform.debounce = function(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    };
    
    /**
     * 현재 사용자 ID 반환
     */
    PTGPlatform.getUserId = function() {
        return config.userId || 0;
    };
    
    /**
     * 로그인 여부 확인
     */
    PTGPlatform.isLoggedIn = function() {
        return PTGPlatform.getUserId() > 0;
    };
    
    /**
     * 날짜 포맷팅 (KST 기준)
     */
    PTGPlatform.formatDate = function(dateString, format = 'YYYY-MM-DD HH:mm:ss') {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        const kstOffset = 9 * 60; // KST는 UTC+9
        const utc = date.getTime() + (date.getTimezoneOffset() * 60000);
        const kstDate = new Date(utc + (kstOffset * 60000));
        
        return format
            .replace('YYYY', kstDate.getFullYear())
            .replace('MM', String(kstDate.getMonth() + 1).padStart(2, '0'))
            .replace('DD', String(kstDate.getDate()).padStart(2, '0'))
            .replace('HH', String(kstDate.getHours()).padStart(2, '0'))
            .replace('mm', String(kstDate.getMinutes()).padStart(2, '0'))
            .replace('ss', String(kstDate.getSeconds()).padStart(2, '0'));
    };
    
    /**
     * 오늘 날짜 (KST 기준, YYYY-MM-DD 형식)
     */
    PTGPlatform.todayKST = function() {
        const now = new Date();
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        const kst = new Date(utc + (9 * 60 * 60000));
        
        return kst.toISOString().split('T')[0];
    };
    
    /**
     * 에러 메시지 표시 헬퍼
     */
    PTGPlatform.showError = function(message) {
        // 콘솔 로그만 출력
        console.error('[PTG Platform] 오류:', message);
    };
    
    /**
     * 성공 메시지 표시 헬퍼
     */
    PTGPlatform.showSuccess = function(message) {
        // 콘솔 로그만 출력
        console.log('[PTG Platform] 성공:', message);
    };
    
})();

