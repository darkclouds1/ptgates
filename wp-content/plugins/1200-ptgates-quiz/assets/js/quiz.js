/**
 * PTGates Quiz - 메인 JavaScript
 * 
 * 문제 풀이 UI, 타이머, 드로잉 기능
 */

// 서버 REST(subjects/sessions/subsubjects)가 준비될 때까지
// 클라이언트 매핑만으로 즉시 채우기 위한 토글
// 서버 준비 후 true로 전환하면 기존 REST 호출 경로를 사용합니다.
// (전역 스코프에 선언하여 모든 함수에서 접근 가능)
var USE_SERVER_SUBJECTS = true;

// 원래 alert 함수 보존(필요 시 원래 alert으로 복구 가능)
if (typeof window !== 'undefined' && typeof window.alert === 'function' && typeof window.__PTG_ORIG_ALERT === 'undefined') {
  window.__PTG_ORIG_ALERT = window.alert;
}

(function() {
    'use strict';
    // 테스트 모드 감지 (URL 쿼리로 활성화)
    if (typeof window !== 'undefined' && window.location && window.location.href) {
        try {
            const urlParams = new URL(window.location.href).searchParams;
            if (urlParams.get('ptg_test_mode') === '404') {
                window.PTGQuizTestMode404 = true;
            }
            if (urlParams.get('ptg_test_mode') === '401') {
                window.PTGQuizTestMode401 = true;
            }
        } catch (e) {
            // ignore parsing errors
        }
    }
})();
// 퀴즈용 alert 헬퍼 - 브라우저 alert 대신 커스텀 모달 사용
function PTG_quiz_alert(message) {
    if (typeof document === 'undefined') {
        return;
    }

    let modal = document.getElementById('ptg-quiz-alert-modal');
    let overlay, box, textEl, btn;

    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'ptg-quiz-alert-modal';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.right = '0';
        modal.style.bottom = '0';
        modal.style.display = 'none';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.zIndex = '99999';

        overlay = document.createElement('div');
        overlay.style.position = 'absolute';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.right = '0';
        overlay.style.bottom = '0';
        overlay.style.background = 'rgba(0,0,0,0.4)';

        box = document.createElement('div');
        box.style.position = 'relative';
        box.style.background = '#ffffff';
        box.style.borderRadius = '8px';
        box.style.padding = '20px 24px';
        box.style.maxWidth = '360px';
        box.style.boxShadow = '0 4px 12px rgba(0,0,0,0.25)';
        box.style.fontSize = '14px';
        box.style.lineHeight = '1.5';
        box.style.textAlign = 'center';

        textEl = document.createElement('div');
        textEl.id = 'ptg-quiz-alert-text';
        textEl.style.marginBottom = '16px';

        btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '확인';
        btn.style.minWidth = '80px';
        btn.style.padding = '8px 16px';
        btn.style.borderRadius = '4px';
        btn.style.border = 'none';
        btn.style.background = '#4a90e2';
        btn.style.color = '#ffffff';
        btn.style.fontWeight = '600';
        btn.style.cursor = 'pointer';

        box.appendChild(textEl);
        box.appendChild(btn);
        modal.appendChild(overlay);
        modal.appendChild(box);
        document.body.appendChild(modal);

        const close = () => {
            modal.style.display = 'none';
        };
        overlay.addEventListener('click', close);
        btn.addEventListener('click', close);
        document.addEventListener('keydown', function(e) {
            if (modal.style.display === 'flex' && e.key === 'Escape') {
                close();
            }
        });
    } else {
        textEl = document.getElementById('ptg-quiz-alert-text');
    }

    if (textEl) {
        textEl.textContent = String(message || '');
    }
    modal.style.display = 'flex';
}


(function() {
    'use strict';
    
    // 전역 네임스페이스
    window.PTGQuiz = window.PTGQuiz || {};
    
    // 설정
    const config = typeof ptgQuiz !== 'undefined' ? ptgQuiz : {
        restUrl: '/wp-json/ptg-quiz/v1/',
        nonce: '',
        userId: 0
    };

    // PTGPlatform Polyfill: 플랫폼 전역이 없더라도 독립 동작 보장
    if (typeof window.PTGPlatform === 'undefined') {
        (function(){
            const buildUrl = (endpoint) => {
                // endpoint 예: 'ptg-quiz/v1/questions/123'
                if (/^https?:\/\//i.test(endpoint)) return endpoint;
                const origin = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
                return origin + '/wp-json/' + String(endpoint).replace(/^\/+/, '');
            };
            async function api(method, endpoint, data) {
                const url = buildUrl(endpoint);
                const headers = {
                    'Accept': 'application/json',
                    'X-WP-Nonce': config.nonce || ''
                };
                const init = { method, headers, credentials: 'same-origin' };
                if (data !== undefined) {
                    headers['Content-Type'] = 'application/json';
                    init.body = JSON.stringify(data);
                }
                const res = await fetch(url, init);
                const ct = res.headers.get('content-type') || '';
                const text = await res.text();
                if (!ct.includes('application/json')) {
                    throw new Error(`[REST Non-JSON ${res.status}] ${text.slice(0,200)}`);
                }
                const json = JSON.parse(text);
                if (!res.ok) {
                    const msg = (json && (json.message || json.code)) || `HTTP ${res.status}`;
                    const err = new Error(msg);
                    err.status = res.status;
                    err.data = json;
                    throw err;
                }
                return json;
            }
            window.PTGPlatform = {
                get: (e, q={}) => {
                    const qp = new URLSearchParams(q).toString();
                    const ep = qp ? `${e}?${qp}` : e;
                    return api('GET', ep);
                },
                post: (e, b={}) => api('POST', e, b),
                patch: (e, b={}) => api('PATCH', e, b),
                showError: (m) => console.error('[PTG Platform Polyfill] 오류:', m),
                debounce: function(fn, wait){ let t=null; return function(...args){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,args), wait); }; }
            };
        })();
    }
    // 항상 안전한 래퍼로 교체(플랫폼 스크립트가 있어도 JSON만 보장하도록)
    (function(){
        const buildUrl = (endpoint) => {
            if (/^https?:\/\//i.test(endpoint)) return endpoint;
            const origin = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
            // ptg-quiz/v1/... 같은 엔드포인트 문자열을 받도록 고정
            return origin + '/wp-json/' + String(endpoint).replace(/^\/+/, '');
        };
        async function safeApi(method, endpoint, data){
            const url = buildUrl(endpoint);
            const headers = {
                'Accept': 'application/json',
                'X-WP-Nonce': config.nonce || ''
            };
            const init = { method, headers, credentials: 'same-origin' };
            if (data !== undefined) { headers['Content-Type']='application/json'; init.body = JSON.stringify(data); }
            const res = await fetch(url, init);
            const ct = res.headers.get('content-type') || '';
            const text = await res.text();
            
            // 응답이 JSON이 아닌 경우 에러 처리
            if (!ct.includes('application/json')) {
                // 401이나 403인 경우 권한 문제일 수 있음
                if (res.status === 401 || res.status === 403) {
                    throw new Error(`권한이 없습니다. 로그인이 필요합니다. (HTTP ${res.status})`);
                }
                // 404인 경우 엔드포인트가 존재하지 않음
                if (res.status === 404) {
                    throw new Error(`API 엔드포인트를 찾을 수 없습니다: ${endpoint} (HTTP 404)`);
                }
                // 기타 HTML 응답
                console.error('[PTG Quiz] JSON이 아닌 응답:', {
                    status: res.status,
                    contentType: ct,
                    url: url,
                    preview: text.slice(0, 200)
                });
                throw new Error(`서버 오류: JSON 응답을 받지 못했습니다. (HTTP ${res.status})`);
            }
            
            let json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                console.error('[PTG Quiz] JSON 파싱 실패:', {
                    status: res.status,
                    contentType: ct,
                    url: url,
                    text: text.slice(0, 200)
                });
                throw new Error(`JSON 파싱 실패: ${e.message}`);
            }
            
            if (!res.ok) { 
                const errorMsg = (json && (json.message || json.code || json.data && json.data.message)) || `HTTP ${res.status}`;
                throw new Error(errorMsg);
            }
            return json;
        }
        // 기존 객체 유지하면서 메서드만 래핑
        try {
            window.PTGPlatform = Object.assign({}, window.PTGPlatform || {}, {
                get: (e, q={}) => {
                    const qp = new URLSearchParams(q).toString();
                    const ep = qp ? `${e}?${qp}` : e;
                    return safeApi('GET', ep);
                },
                post: (e, b={}) => safeApi('POST', e, b),
                patch: (e, b={}) => safeApi('PATCH', e, b)
            });
        } catch(_) {}
    })();
    
    /**
     * 기기 타입 감지 (PC, Tablet, Mobile)
     * @returns {string} 'pc', 'tablet', 또는 'mobile'
     */
    function detectDeviceType() {
        const userAgent = navigator.userAgent.toLowerCase();
        const platform = navigator.platform ? navigator.platform.toLowerCase() : '';
        
        // iPad 감지 (iOS + iPad user agent)
        const isIPad = /ipad/i.test(userAgent) || (platform === 'macintel' && navigator.maxTouchPoints > 1);
        
        // iPhone 감지
        const isIPhone = /iphone/i.test(userAgent) && !isIPad;
        
        // Android 태블릿 감지
        const isAndroid = /android/i.test(userAgent);
        const isAndroidTablet = isAndroid && !/mobile/i.test(userAgent);
        
        // 태블릿 판단
        if (isIPad || isAndroidTablet) {
            return 'tablet';
        }
        
        // 모바일 판단 (iPhone, Android 모바일 등)
        if (isIPhone || (isAndroid && /mobile/i.test(userAgent))) {
            return 'mobile';
        }
        
        // 그 외 모든 기기 (PC)
        return 'pc';
    }
    
    // 상태 관리
    const QuizState = {
        questions: [],        // 문제 ID 목록 배열 (연속 퀴즈용)
        currentIndex: 0,      // 현재 문제 인덱스
        questionId: 0,        // 현재 문제 ID (호환성 유지)
        questionData: null,
        userState: null,
        userAnswer: '',
        isAnswered: false,
        timer: null,
        timerSeconds: 0,
        timerInterval: null,
        drawingEnabled: false,
        isInitialized: false, // 중복 초기화 방지 플래그
        initializing: false, // 초기화 진행 중 재진입 방지
        deviceType: detectDeviceType(), // 기기 타입 (pc, tablet, mobile)
        // 앱 상태머신
        appState: 'idle', // 'idle' | 'running' | 'finished' | 'terminated'
        requestSeq: 0, // 요청 시퀀스 증가값
        lastAppliedSeq: 0, // 마지막으로 적용된 시퀀스
        // 드로잉 상태
        drawingTool: 'pen', // 'pen' 또는 'eraser'
        drawingHistory: [], // Undo/Redo를 위한 히스토리
        drawingHistoryIndex: -1, // 현재 히스토리 인덱스
        isDrawing: false,
        lastX: 0,
        lastY: 0,
        canvasContext: null,
        penColor: 'rgb(255, 0, 0)', // 펜 색상 (기본값: 빨강)
        penWidth: 10, // 펜 두께 (기본값: 10px)
        penAlpha: 0.2, // 펜 불투명도 (기본값: 0.2 = 20%, 0~1 범위, 높을수록 진함)
        drawingSaveTimeout: null, // 드로잉 자동 저장 디바운스 타이머
        giveUpInProgress: false, // 포기하기 중복 실행 방지
        eventsBound: false, // 이벤트 중복 바인딩 방지
        terminated: false, // 포기/종료 이후 추가 동작 차단 (호환용)
        savingDrawing: false, // 드로잉 저장 중 플래그
        // 퀴즈 결과 추적
        answers: [], // 답안 제출 결과 배열 { questionId, isCorrect, userAnswer, correctAnswer }
        startTime: null // 퀴즈 시작 시간 (타임스탬프)
    };

    /**
     * 상태 전환 및 UI 반영
     */
    function setState(nextState) {
        const prev = QuizState.appState;
        QuizState.appState = nextState;
        // 호환 플래그 동기화
        QuizState.terminated = (nextState === 'terminated' || nextState === 'finished');
        applyUIForState();
        // 타이머 제어
        if (nextState === 'running') {
            startTimer();
        } else {
            if (QuizState.timerInterval) {
                clearInterval(QuizState.timerInterval);
                QuizState.timerInterval = null;
            }
        }
        // 콘솔 로깅 (디버깅)
        try { console.log('[PTG Quiz] state:', prev, '→', nextState); } catch(e){}
    }

    /**
     * 현재 상태에 맞게 UI 토글
     */
    function applyUIForState() {
        const filterSection = document.getElementById('ptg-quiz-filter-section');
        const progress = document.getElementById('ptgates-progress-section');
        const toolbar = document.querySelector('.ptg-quiz-toolbar');
        const cardWrapper = document.querySelector('.ptg-quiz-card-wrapper');
        const actions = document.querySelector('.ptg-quiz-actions');
        const resultSection = document.getElementById('ptg-quiz-result-section');

        const show = (el, display='block') => { if (el) el.style.display = display; };
        const hide = (el) => { if (el) el.style.display = 'none'; };

        switch (QuizState.appState) {
            case 'idle':
                show(filterSection, 'flex');
                hide(progress);
                hide(toolbar);
                hide(cardWrapper);
                hide(actions);
                hide(resultSection);
                break;
            case 'running':
                hide(filterSection);
                show(progress, 'block');
                show(toolbar, 'flex');
                show(cardWrapper, 'block');
                show(actions, 'flex');
                hide(resultSection);
                break;
            case 'finished':
            case 'terminated':
                hide(filterSection);
                hide(progress);
                hide(toolbar);
                hide(cardWrapper);
                hide(actions);
                // 안전: 카드 내용 제거 및 버튼 비활성화
                try {
                    const card = document.getElementById('ptg-quiz-card');
                    if (card) { card.innerHTML = ''; }
                    const btnCheck = document.getElementById('ptg-btn-check-answer');
                    const btnNext = document.getElementById('ptg-btn-next-question');
                    const btnGiveup = document.getElementById('ptgates-giveup-btn');
                    if (btnCheck) { btnCheck.disabled = true; btnCheck.style.display = 'none'; }
                    if (btnNext)  { btnNext.disabled  = true; btnNext.style.display  = 'none'; }
                    if (btnGiveup){ btnGiveup.disabled = true; btnGiveup.style.pointerEvents = 'none'; }
                } catch(e){}
                show(resultSection, 'block');
                // 결과로 스크롤
                if (resultSection && typeof resultSection.scrollIntoView === 'function') {
                    resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                break;
        }
    }
    
    /**
     * localStorage에서 펜 설정 불러오기
     */
    function loadPenSettings() {
        try {
            // 색상 불러오기
            const savedColor = localStorage.getItem('ptg-quiz-pen-color');
            if (savedColor && savedColor !== 'null') {
                QuizState.penColor = savedColor;
            }
            
            // 두께 불러오기
            const savedWidth = localStorage.getItem('ptg-quiz-pen-width');
            if (savedWidth && !isNaN(parseInt(savedWidth))) {
                QuizState.penWidth = parseInt(savedWidth);
            }
            
            // 불투명도 불러오기
            const savedAlpha = localStorage.getItem('ptg-quiz-pen-alpha');
            if (savedAlpha && !isNaN(parseFloat(savedAlpha))) {
                QuizState.penAlpha = parseFloat(savedAlpha);
            }
        } catch (e) {
            // localStorage 사용 불가 시 기본값 유지
            // localStorage 사용 불가 시 무시 (로그 제거)
        }
    }
    
    /**
     * 펜 설정을 localStorage에 저장
     */
    function savePenSettings() {
        try {
            localStorage.setItem('ptg-quiz-pen-color', QuizState.penColor);
            localStorage.setItem('ptg-quiz-pen-width', String(QuizState.penWidth));
            localStorage.setItem('ptg-quiz-pen-alpha', String(QuizState.penAlpha));
        } catch (e) {
            // localStorage 사용 불가 시 무시 (로그 제거)
        }
    }
    
    /**
     * 헤더 위치로 스크롤
     */
    function scrollToHeader() {
        const header = document.getElementById('ptgates-header');
        if (header) {
            // 헤더 위치 계산
            const headerRect = header.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const headerTop = headerRect.top + scrollTop;
            
            // WordPress 관리 바 높이 고려 (있는 경우)
            const adminBar = document.getElementById('wpadminbar');
            const adminBarHeight = adminBar ? adminBar.offsetHeight : 0;
            
            // 헤더가 화면 최상단에 오도록 스크롤 (관리 바 아래)
            window.scrollTo({
                top: headerTop - adminBarHeight,
                behavior: 'smooth'
            });
        }
    }
    
    /**
     * 툴바로 스크롤 (툴바가 화면 상단에 보이도록)
     */
    function scrollToToolbar() {
        const toolbar = document.querySelector('.ptg-quiz-toolbar');
        if (!toolbar) return;
        
        // 툴바 위치 계산
        const toolbarRect = toolbar.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const toolbarTop = toolbarRect.top + scrollTop;
        
        // WordPress 관리 바 높이 고려 (있는 경우)
        const adminBar = document.getElementById('wpadminbar');
        const adminBarHeight = adminBar ? adminBar.offsetHeight : 0;
        
        // 툴바가 화면 최상단에 오도록 스크롤 (관리 바 아래)
        window.scrollTo({
            top: toolbarTop - adminBarHeight,
            behavior: 'smooth'
        });
    }
    
    /**
     * 초기화
     */
    function init() {
        // 중복 초기화/동시 초기화 방지
        if (QuizState.isInitialized || QuizState.initializing) {
            return;
        }
        QuizState.initializing = true;
        try { console.log('[PTG Quiz] 초기화 시작'); } catch(e){}
        // 저장된 펜 설정 불러오기
        loadPenSettings();
        
        const container = document.getElementById('ptg-quiz-container');
        if (!container) {
            console.error('[PTG Quiz] 컨테이너를 찾을 수 없음: ptg-quiz-container');
            return;
        }
        
        // URL 파라미터에서 필터 읽기 (우선순위: URL 파라미터 > data 속성)
        const urlParams = new URLSearchParams(window.location.search);
        
        // URL 파라미터에서 필터 읽기
        const yearFromUrl = urlParams.get('year') ? parseInt(urlParams.get('year')) : null;
        const subjectFromUrl = urlParams.get('subject') || '';
        const limitFromUrl = urlParams.get('limit') ? parseInt(urlParams.get('limit')) : null;
        const sessionFromUrl = urlParams.get('session') ? parseInt(urlParams.get('session')) : null;
        const fullSessionFromUrl = urlParams.get('full_session') === '1' || urlParams.get('full_session') === 'true';
        const bookmarkedFromUrl = urlParams.get('bookmarked') === '1' || urlParams.get('bookmarked') === 'true';
        const needsReviewFromUrl = urlParams.get('needs_review') === '1' || urlParams.get('needs_review') === 'true';
        
        // data 속성에서 필터 읽기
        const yearFromData = container.dataset.year ? parseInt(container.dataset.year) : null;
        const subjectFromData = container.dataset.subject || '';
        const limitFromData = container.dataset.limit ? parseInt(container.dataset.limit) : null;
        const sessionFromData = container.dataset.session ? parseInt(container.dataset.session) : null;
        const fullSessionFromData = container.dataset.fullSession === '1';
        const bookmarkedFromData = container.dataset.bookmarked === '1';
        const needsReviewFromData = container.dataset.needsReview === '1';
        
        // 최종 필터 값 (URL 파라미터 우선)
        const year = yearFromUrl || yearFromData;
        const subject = subjectFromUrl || subjectFromData;
        const limit = limitFromUrl || limitFromData;
        const session = sessionFromUrl || sessionFromData;
        const fullSession = fullSessionFromUrl || fullSessionFromData;
        const bookmarked = bookmarkedFromUrl || bookmarkedFromData;
        const needsReview = needsReviewFromUrl || needsReviewFromData;
        
        const questionId = parseInt(container.dataset.questionId) || 0;
        
        // 1200-ptgates-quiz는 기본적으로 기출문제 제외하고 5문제 연속 퀴즈
        // question_id가 없고 필터도 없으면 기본값으로 5문제 시작
        const hasFilters = year || subject || limit || session || bookmarked || needsReview;
        const useDefaultFilters = !questionId && !hasFilters;
        
        // 디버깅 로그
        console.log('[PTG Quiz] 초기화 상태:', {
            questionId,
            hasFilters,
            useDefaultFilters,
            year,
            subject,
            limit,
            session,
            fullSession,
            bookmarked,
            needsReview
        });
        
        // 타이머 설정: 1교시(90분) 또는 2교시(75분)가 아니면 문제당 50초로 계산
        const timerMinutes = parseInt(container.dataset.timer) || 0;
        const isSession1 = timerMinutes === 90;
        const isSession2 = timerMinutes === 75;
        
        // 암기카드와 노트 버튼 강제 제거 (캐시된 버전 대응)
        const flashcardBtn = document.querySelector('.ptg-btn-flashcard');
        const notebookBtn = document.querySelector('.ptg-btn-notebook');
        if (flashcardBtn) {
            flashcardBtn.remove();
        }
        if (notebookBtn) {
            notebookBtn.remove();
        }
        
        // 메모 패널 초기 상태: 숨김
        const notesPanel = document.getElementById('ptg-notes-panel');
        if (notesPanel) {
            notesPanel.style.display = 'none';
        }
        
        // 모바일에서 드로잉 기능 비활성화
        if (QuizState.deviceType === 'mobile') {
            const btnDrawing = document.querySelector('.ptg-btn-drawing');
            const drawingToolbar = document.getElementById('ptg-drawing-toolbar');
            if (btnDrawing) {
                btnDrawing.style.display = 'none';
            }
            if (drawingToolbar) {
                drawingToolbar.style.display = 'none';
            }
        }
        
        // 이벤트 리스너 등록
        setupEventListeners();
        
        // 실전 모의 학습Tip 버튼 이벤트
        setupTipModal();
        
        // 필터 UI 설정
        setupFilterUI();
        
        // 초기 상태 적용
        setState('idle');
        
        // 필터 조건이 있으면 필터 UI 숨기고 바로 시작
        if (hasFilters || questionId) {
            setState('running');
        } else {
            // 필터 섹션이 표시되면 퀴즈는 시작하지 않음 (조회 클릭 시 시작)
            return; // 필터 섹션이 표시되면 여기서 종료
        }
        
        // 1200-ptgates-quiz는 기본적으로 기출문제 제외하고 5문제 연속 퀴즈
        // questionId가 없으면 항상 기본값으로 처리
        if (hasFilters || useDefaultFilters || questionId === 0) {
            (async () => {
                try {
                    const filters = {};
                    if (year) filters.year = year;
                    if (subject) filters.subject = subject;
                    if (limit) {
                        filters.limit = limit;
                    } else if (useDefaultFilters) {
                        // 기본값: 기출문제 제외하고 5문제
                        filters.limit = 5;
                    }
                    if (session) {
                        filters.session = session;
                        filters.full_session = fullSession;
                    }
                    if (bookmarked) {
                        filters.bookmarked = true;
                    }
                    if (needsReview) {
                        filters.needs_review = true;
                    }
                    
                    const questionIds = await loadQuestionsList(filters);
                    
                    if (!questionIds || questionIds.length === 0) {
                        showError('선택한 조건에 맞는 문제를 찾을 수 없습니다.');
                        return;
                    }
                    
                    // 문제 목록 저장
                    QuizState.questions = questionIds;
                    QuizState.currentIndex = 0;
                    QuizState.questionId = questionIds[0];
                    // 퀴즈 결과 초기화
                    QuizState.answers = [];
                    QuizState.startTime = null;
                    
                    // 타이머 설정: 문제 수 × 50초 (1교시/2교시가 아닌 경우)
                    if (isSession1 || isSession2) {
                        QuizState.timerSeconds = timerMinutes * 60;
                    } else {
                        QuizState.timerSeconds = questionIds.length * 50;
                    }
                    
                    // 타이머 표시 즉시 업데이트 (문제 수 × 50초)
                    updateTimerDisplay();
                    
                    // 진행 상태 섹션 표시
                    showProgressSection();
                    // 퀴즈 UI 표시
                    showQuizUI();
                    
                    // 첫 번째 문제 로드
                    await loadQuestion();
                    
                    // 타이머 시작
                    if (container.dataset.unlimited !== '1') {
                        startTimer();
                    }
                } catch (error) {
                    console.error('[PTG Quiz] 문제 목록 로드 오류:', error);
                    showError('문제 목록을 불러오는 중 오류가 발생했습니다.');
                }
            })();
        } else if (questionId) {
            // 기존 방식: 단일 문제
            QuizState.questionId = questionId;
            // 퀴즈 결과 초기화
            QuizState.answers = [];
            QuizState.startTime = null;
            
            if (isSession1 || isSession2) {
                // 1교시 또는 2교시 전체 문제 풀이: 설정된 시간 그대로 사용
                QuizState.timerSeconds = timerMinutes * 60;
            } else {
                // 그 외의 경우: 문제당 50초로 계산 (단일 문제이므로 50초)
                QuizState.timerSeconds = 50;
            }
            
            // 타이머 표시 즉시 업데이트
            updateTimerDisplay();
            
            // 진행 상태 섹션 표시
            showProgressSection();
            // 퀴즈 UI 표시
            showQuizUI();
            
            // 문제 로드
            loadQuestion();
            
            // 타이머 시작
            if (container.dataset.unlimited !== '1') {
                startTimer();
            }
        } else {
            // 문제 ID도 필터도 없으면 기본값으로 처리
            // useDefaultFilters가 false인 경우에도 기본값으로 처리
            (async () => {
                try {
                    const questionIds = await loadQuestionsList({ limit: 5 });
                    
                    if (!questionIds || questionIds.length === 0) {
                        showError('문제를 찾을 수 없습니다.');
                        return;
                    }
                    
                    QuizState.questions = questionIds;
                    QuizState.currentIndex = 0;
                    QuizState.questionId = questionIds[0];
                    QuizState.timerSeconds = questionIds.length * 50;
                    // 퀴즈 결과 초기화
                    QuizState.answers = [];
                    QuizState.startTime = null;
                    // 진행 상태 섹션 표시
                    showProgressSection();
                    // 퀴즈 UI 표시
                    showQuizUI();
                    
                    // 타이머 표시 즉시 업데이트 (문제 수 × 50초)
                    updateTimerDisplay();
                    
                    await loadQuestion();
                    
                    if (container.dataset.unlimited !== '1') {
                        startTimer();
                    }
                } catch (error) {
                    console.error('[PTG Quiz] 기본 문제 목록 로드 오류:', error);
                    showError('문제 목록을 불러오는 중 오류가 발생했습니다.');
                }
            })();
        }
        
        // 키보드 단축키
        setupKeyboardShortcuts();
        
        // 초기화 완료 플래그 설정
        QuizState.isInitialized = true;
        QuizState.initializing = false;
        
        // 페이지 로드 시 헤더로 스크롤 (약간의 지연을 두어 DOM이 완전히 렌더링된 후 실행)
        setTimeout(() => {
            scrollToHeader();
        }, 100);
    }
    
    /**
     * 필터 UI 설정
     */
    function setupFilterUI() {
		// 교시 목록 로드
		const sessionSelect = document.getElementById('ptg-quiz-filter-session');
		if (sessionSelect) {
			loadSessions();
		}
		// 교시 선택 시 과목 목록 로드
		const subjectSelect = document.getElementById('ptg-quiz-filter-subject');
		const subSubjectSelect = document.getElementById('ptg-quiz-filter-subsubject');
        if (sessionSelect) {
            sessionSelect.addEventListener('change', async function() {
                const session = this.value || '';
                await loadSubjectsForSession(session);
            });
        }
		
		// 과목 선택 시 세부과목 목록 채우기
		if (subjectSelect) {
			subjectSelect.addEventListener('change', function() {
				const session = (document.getElementById('ptg-quiz-filter-session') || {}).value || '';
				const subject = this.value || '';
				populateSubSubjects(session, subject);
			});
		}
        
        // 조회 버튼 클릭 시 퀴즈 시작
        const startBtn = document.getElementById('ptg-quiz-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', startQuizFromFilter);
        }
    }
    
    /**
	 * 교시 목록 로드 (ptg-quiz/v1/sessions)
	 */
	async function loadSessions() {
		const sessionSelect = document.getElementById('ptg-quiz-filter-session');
		if (!sessionSelect) return;
		try {
			let sessions = [];
			if (USE_SERVER_SUBJECTS) {
				const response = await PTGPlatform.get('ptg-quiz/v1/sessions');
				sessions = (response && response.success && Array.isArray(response.data)) ? response.data : [];
			}
			// 기본 옵션
			sessionSelect.innerHTML = '<option value="">교시</option>';
			// Fallback: 로그인 실패/호출 실패 시 1,2 제공
			if (!sessions || sessions.length === 0) {
				sessions = [1, 2];
			}
			sessions.forEach(no => {
				const opt = document.createElement('option');
				opt.value = String(no);
				opt.textContent = `${no}교시`;
				sessionSelect.appendChild(opt);
			});
			// 디폴트(전체) 상태에서 과목/세부과목은 "전체" 기준으로 채움
			await loadSubjectsForSession('');
		} catch (e) {
			console.error('[PTG Quiz] 교시 목록 로드 오류:', e);
			// 치명적 오류 시에도 기본 옵션 제공
			try {
				sessionSelect.innerHTML = '<option value="">교시</option><option value="1">1교시</option><option value="2">2교시</option>';
				// 오류 시에도 디폴트(전체) 기준 과목/세부과목 채우기
				await loadSubjectsForSession('');
			} catch(_) {}
		}
	}
	
	/**
     * 교시별 과목 목록 로드
     */
    async function loadSubjectsForSession(session) {
        const subjectSelect = document.getElementById('ptg-quiz-filter-subject');
		const subSubjectSelect = document.getElementById('ptg-quiz-filter-subsubject');
        if (!subjectSelect) return;
        
		// 기본 초기화
		subjectSelect.innerHTML = '<option value="">과목</option>';
		if (subSubjectSelect) {
			subSubjectSelect.innerHTML = '<option value="">세부과목</option>';
		}
		
        try {
			let subjects = [];
			if (USE_SERVER_SUBJECTS) {
				const endpoint = session ? `ptg-quiz/v1/subjects?session=${session}` : 'ptg-quiz/v1/subjects';
				const response = await PTGPlatform.get(endpoint);
				subjects = (response && response.success && Array.isArray(response.data)) ? response.data : [];
			}

			// Fallback (클라이언트 매핑)
			if ((!subjects || subjects.length === 0) && session) {
				const fallback = {
					'1': ['물리치료 기초', '물리치료 진단평가'],
					'2': ['물리치료 중재', '의료관계법규']
				};
				subjects = fallback[String(session)] || [];
			}

			// 과목 목록 추가 (있을 때만)
			if (subjects && subjects.length) {
				// 중복 제거
				const uniqueSubjects = Array.from(new Set(subjects));
				uniqueSubjects.forEach(subject => {
					const option = document.createElement('option');
					option.value = subject;
					option.textContent = subject;
					subjectSelect.appendChild(option);
				});
				// 교시 선택만으로는 세부과목은 "해당 교시 전체" 기준으로 채움
				const sess = session || '';
				await populateSubSubjects(sess, '');
			}
        } catch (error) {
            console.error('[PTG Quiz] 과목 목록 로드 오류:', error);
			// Fallback (오류 시에도 기본 과목 채우기)
			if (session) {
				try {
					const fallback = {
						'1': ['물리치료 기초', '물리치료 진단평가'],
						'2': ['물리치료 중재', '의료관계법규']
					};
					const subjects = fallback[String(session)] || [];
					if (subjects.length) {
						subjects.forEach(subject => {
							const option = document.createElement('option');
							option.value = subject;
							option.textContent = subject;
							subjectSelect.appendChild(option);
						});
						// 자동 선택 및 세부과목 로드 트리거
						if (!subjectSelect.value) {
							subjectSelect.value = String(subjects[0]);
							try {
								subjectSelect.dispatchEvent(new Event('change', { bubbles: true }));
							} catch(_) {}
						}
					}
				} catch(_) {}
			}
        }
    }
	
	/**
	 * 세부과목 목록 채우기 (DB 기반 REST 호출)
	 */
	async function populateSubSubjects(session, subject) {
		const select = document.getElementById('ptg-quiz-filter-subsubject');
		if (!select) return;
		
		select.innerHTML = '<option value="">세부과목</option>';
		try {
			let list = [];
			
			// 1) 특정 과목이 선택된 경우 → 해당 과목의 세부과목만
			if (USE_SERVER_SUBJECTS && session && subject) {
				const endpoint = `ptg-quiz/v1/subsubjects?session=${encodeURIComponent(session)}&subject=${encodeURIComponent(subject)}`;
				const response = await PTGPlatform.get(endpoint);
				list = (response && response.success && Array.isArray(response.data)) ? response.data : [];
			}

			// Fallback (클라이언트 매핑)
			const mapping = {
				'1': {
					'물리치료 기초': ['해부생리학', '운동학', '물리적 인자치료', '공중보건학'],
					'물리치료 진단평가': ['근골격계 물리치료 진단평가', '신경계 물리치료 진단평가', '진단평가 원리', '심폐혈관계 검사 및 평가', '기타 계통 검사', '임상의사결정']
				},
				'2': {
					'물리치료 중재': ['근골격계 중재', '신경계 중재', '심폐혈관계 중재', '림프, 피부계 중재', '물리치료 문제해결'],
					'의료관계법규': ['의료법', '의료기사법', '노인복지법', '장애인복지법', '국민건강보험법']
				}
			};
			
			// 서버에서 못 받았거나, 집계 모드일 때는 정적 매핑으로 계산
			if (!list || list.length === 0) {
				const sessKey = session ? String(session) : null;
				
				if (subject) {
					// 특정 과목의 세부과목
					if (sessKey) {
						list = ((mapping[sessKey] || {})[subject]) || [];
					} else {
						// 교시가 전체인 경우 → 모든 교시에서 해당 과목의 세부과목을 합쳐서 표시
						let all = [];
						Object.keys(mapping).forEach(sk => {
							const subMap = mapping[sk] || {};
							if (subMap[subject]) {
								all = all.concat(subMap[subject]);
							}
						});
						list = Array.from(new Set(all));
					}
				} else if (sessKey) {
					// 특정 교시 전체 세부과목
					const subMap = mapping[sessKey] || {};
					let all = [];
					Object.keys(subMap).forEach(subj => {
						all = all.concat(subMap[subj]);
					});
					// 중복 제거
					list = Array.from(new Set(all));
				} else {
					// 교시도 선택 안 한 경우: 전체 교시의 전체 세부과목
					let all = [];
					Object.keys(mapping).forEach(sk => {
						const subMap = mapping[sk];
						Object.keys(subMap).forEach(subj => {
							all = all.concat(subMap[subj]);
						});
					});
					list = Array.from(new Set(all));
				}
			}

			if (list && list.length) {
				// 최종 중복 제거
				const uniqueList = Array.from(new Set(list));
				uniqueList.forEach(name => {
					const opt = document.createElement('option');
					opt.value = name;
					opt.textContent = name;
					select.appendChild(opt);
				});
			}
		} catch (e) {
			console.error('[PTG Quiz] 세부과목 목록 로드 오류:', e);
		}
	}
    
    /**
     * 필터에서 조회 버튼 클릭 시 퀴즈 시작
     */
    async function startQuizFromFilter() {
        const sessionSelect = document.getElementById('ptg-quiz-filter-session');
        const subjectSelect = document.getElementById('ptg-quiz-filter-subject');
		const subSubjectSelect = document.getElementById('ptg-quiz-filter-subsubject');
        const limitSelect = document.getElementById('ptg-quiz-filter-limit');
        
        if (!sessionSelect || !subjectSelect || !limitSelect) {
            PTG_quiz_alert('필터 요소를 찾을 수 없습니다.');
            return;
        }
        
        const session = sessionSelect.value ? parseInt(sessionSelect.value) : null;
        const subject = subjectSelect.value || null;
		const subsubject = subSubjectSelect ? (subSubjectSelect.value || null) : null;
        const limit = parseInt(limitSelect.value) || 5;
        
        try {
            const filters = {};
            if (session) filters.session = session;
            if (subject) filters.subject = subject;
			// 세부과목이 선택된 경우에만 전달 (빈 값은 전체와 동일)
			if (subsubject) filters.subsubject = subsubject;
            filters.limit = limit;

            // 실행 상태로 전환
            setState('running');
            
            const questionIds = await loadQuestionsList(filters);
            
            if (!questionIds || questionIds.length === 0) {
                PTG_quiz_alert('선택한 조건에 맞는 문제를 찾을 수 없습니다.');
                setState('idle');
                return;
            }
            
            // 문제 목록 저장
            QuizState.questions = questionIds;
            QuizState.currentIndex = 0;
            QuizState.questionId = questionIds[0];
            QuizState.answers = [];
            QuizState.startTime = null;
            
            // 타이머 설정: 문제 수 × 50초
            QuizState.timerSeconds = questionIds.length * 50;
            
            // 필터 섹션 숨기기
            hideFilterSection();
            // 퀴즈 UI 표시
            showQuizUI();
            
            // 타이머 표시 업데이트
            updateTimerDisplay();
            
            // 진행 상태 섹션 표시
            showProgressSection();
            
            // 첫 번째 문제 로드
            await loadQuestion();
            
            // 타이머 시작
            const container = document.getElementById('ptg-quiz-container');
            if (container && container.dataset.unlimited !== '1') {
                startTimer();
            }
            
            // 헤더로 스크롤
            scrollToHeader();
        } catch (error) {
            console.error('[PTG Quiz] 퀴즈 시작 오류:', error);
            PTG_quiz_alert('문제를 불러오는 중 오류가 발생했습니다.');
        }
    }
    
    /**
     * 필터 섹션 표시/숨김
     */
    function showFilterSection() {
        const filterSection = document.getElementById('ptg-quiz-filter-section');
        if (filterSection) {
            filterSection.style.display = 'flex';
        }
    }
    
    function hideFilterSection() {
        const filterSection = document.getElementById('ptg-quiz-filter-section');
        if (filterSection) {
            filterSection.style.display = 'none';
        }
    }
    
    /**
     * 이벤트 리스너 설정
     */
    function setupEventListeners() {
        const container = document.getElementById('ptg-quiz-container');
        if (!container) return;
        
        // 이벤트 중복 바인딩 방지
        if (QuizState.eventsBound) {
            return;
        }
        QuizState.eventsBound = true;
        
        // 툴바 전체에 이벤트 위임으로 클릭 이벤트 추가 (모든 버튼 클릭 시 헤더로 스크롤)
        const toolbar = document.querySelector('.ptg-quiz-toolbar');
        if (toolbar) {
            toolbar.addEventListener('click', function(e) {
                // 버튼 클릭 시에만 스크롤 (버블링된 이벤트 포함)
                const isButton = e.target.closest('button');
                if (isButton) {
                    // 약간의 지연을 두어 버튼의 기본 동작이 완료된 후 스크롤
                    setTimeout(() => {
                        scrollToHeader();
                    }, 50);
                }
            });
        }
        
        // 이벤트 위임 사용 (더 안정적)
        container.addEventListener('click', function(e) {
            const target = e.target.closest('.ptg-btn-notes, .ptg-btn-drawing');
            if (!target) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            if (target.classList.contains('ptg-btn-notes')) {
                toggleNotesPanel();
            } else if (target.classList.contains('ptg-btn-drawing')) {
                // 모바일에서는 드로잉 기능 비활성화
                if (QuizState.deviceType !== 'mobile') {
                    toggleDrawing();
                }
            }
        });
        
        // 북마크 버튼
        const btnBookmark = document.querySelector('.ptg-btn-bookmark');
        if (btnBookmark) {
            btnBookmark.addEventListener('click', toggleBookmark);
        }
        
        // 복습 필요 버튼
        const btnReview = document.querySelector('.ptg-btn-review');
        if (btnReview) {
            btnReview.addEventListener('click', toggleReview);
        }
        
        // 정답 확인 버튼
        const btnCheckAnswer = document.getElementById('ptg-btn-check-answer');
        if (btnCheckAnswer) {
            btnCheckAnswer.addEventListener('click', checkAnswer);
        }
        
        // 다음 문제 버튼
        const btnNext = document.getElementById('ptg-btn-next-question');
        if (btnNext) {
            btnNext.addEventListener('click', loadNextQuestion);
        }
        
        // 포기하기 버튼 (progress-section)
        const btnGiveup = document.getElementById('ptgates-giveup-btn');
        if (btnGiveup) {
			// 외부/중복으로 붙은 모든 기존 클릭 리스너 제거(클론 교체)
			const btnGiveupCloned = btnGiveup.cloneNode(true);
			btnGiveup.parentNode.replaceChild(btnGiveupCloned, btnGiveup);
			
			btnGiveupCloned.addEventListener('click', function(e) {
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                // 중복 실행 방지
                if (QuizState.giveUpInProgress) return;
                
                let proceed = true;
                if (typeof window.confirm === 'function') {
                    proceed = window.confirm('퀴즈를 포기하시겠습니까? 현재까지의 결과가 저장됩니다.');
                }
                if (!proceed) {
                    return;
                }
                
                // 확인 후에만 버튼 비활성화
				btnGiveupCloned.disabled = true;
				btnGiveupCloned.style.pointerEvents = 'none';
                
                giveUpQuiz();
            });
        }
        
        // 시간관리 tip 버튼
        const timeTipBtn = document.getElementById('ptgates-time-tip-btn');
        const timeTipModal = document.getElementById('ptgates-time-tip-modal');
        const timeTipClose = document.getElementById('ptgates-time-tip-close');
        
        if (timeTipBtn && timeTipModal) {
            timeTipBtn.addEventListener('click', function(e) {
                e.preventDefault();
                timeTipModal.style.display = 'block';
            });
        }
        
        if (timeTipClose && timeTipModal) {
            timeTipClose.addEventListener('click', function() {
                timeTipModal.style.display = 'none';
            });
            
            // 오버레이 클릭 시 닫기
            const overlay = timeTipModal.querySelector('.ptgates-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    timeTipModal.style.display = 'none';
                });
            }
        }
        
        // 드로잉 툴바 버튼 이벤트 (닫기 버튼 포함)
        setupDrawingToolbarEvents();
        
        // 페이지 이탈 시 드로잉 저장 보장
        window.addEventListener('beforeunload', function(e) {
            if (QuizState.canvasContext && QuizState.savingDrawing) {
                // 저장 중이면 페이지 이탈 방지 (동기적으로 저장 불가)
                // 단, 브라우저가 저장 완료를 기다려주지 않으므로 
                // 최소한 사용자에게 경고만 표시
                e.preventDefault();
                e.returnValue = '드로잉이 저장 중입니다. 페이지를 떠나시겠습니까?';
                return e.returnValue;
            }
        });
        
        // 페이지 숨김 시 (탭 전환 등) 드로잉 저장
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && QuizState.canvasContext && QuizState.drawingEnabled) {
                // 디바운스 타이머가 있으면 취소하고 즉시 저장
                if (QuizState.drawingSaveTimeout) {
                    clearTimeout(QuizState.drawingSaveTimeout);
                    QuizState.drawingSaveTimeout = null;
                }
                // 비동기 저장 (완료 보장은 어려움)
                saveDrawingToServer();
            }
        });
    }
    
    /**
     * 펜 메뉴 초기화
     */
    function initializePenMenu() {
        // 저장된 색상 선택 (기본값: 빨강)
        const savedColor = QuizState.penColor || 'rgb(255, 0, 0)';
        const colorBtn = document.querySelector(`.ptg-pen-color-btn[data-color="${savedColor}"]`);
        if (colorBtn) {
            colorBtn.classList.add('active');
        } else {
            // 저장된 색상이 없으면 기본 색상 선택
            const redColorBtn = document.querySelector('.ptg-pen-color-btn[data-color="rgb(255, 0, 0)"]');
            if (redColorBtn) {
                redColorBtn.classList.add('active');
            }
        }
        
        // 두께 슬라이더 이벤트 설정 및 저장된 값 복원
        const widthSlider = document.getElementById('ptg-pen-width-slider');
        const widthValue = document.getElementById('ptg-pen-width-value');
        if (widthSlider && widthValue) {
            // 저장된 값으로 슬라이더 초기화
            widthSlider.value = QuizState.penWidth || 10;
            widthValue.textContent = QuizState.penWidth || 10;
            
            widthSlider.addEventListener('input', function(e) {
                const width = parseInt(e.target.value);
                setPenWidth(width);
                widthValue.textContent = width;
                savePenSettings(); // localStorage에 저장
            });
            
            widthSlider.addEventListener('change', function(e) {
                const width = parseInt(e.target.value);
                setPenWidth(width);
                widthValue.textContent = width;
                savePenSettings(); // localStorage에 저장
            });
        }
        
        // 불투명도 슬라이더 이벤트 설정 및 저장된 값 복원
        const alphaSlider = document.getElementById('ptg-pen-alpha-slider');
        const alphaValue = document.getElementById('ptg-pen-alpha-value');
        if (alphaSlider && alphaValue) {
            // 저장된 값으로 슬라이더 초기화
            const alphaPercent = Math.round((QuizState.penAlpha || 0.2) * 100);
            alphaSlider.value = alphaPercent;
            alphaValue.textContent = alphaPercent;
            
            alphaSlider.addEventListener('input', function(e) {
                const alphaPercent = parseInt(e.target.value);
                const alpha = alphaPercent / 100;
                setPenAlpha(alpha);
                alphaValue.textContent = alphaPercent;
                savePenSettings(); // localStorage에 저장
            });
            
            alphaSlider.addEventListener('change', function(e) {
                const alphaPercent = parseInt(e.target.value);
                const alpha = alphaPercent / 100;
                setPenAlpha(alpha);
                alphaValue.textContent = alphaPercent;
                savePenSettings(); // localStorage에 저장
            });
        }
        
        // 외부 클릭/터치 시 메뉴 닫기 (PC와 모바일 모두 지원)
        function closeMenuIfOutside(e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            const penControls = document.querySelector('.ptg-pen-controls');
            if (penMenu && penControls && 
                !e.target.closest('.ptg-pen-controls') && 
                penMenu.style.display !== 'none') {
                penMenu.style.display = 'none';
            }
        }
        
        // 클릭 이벤트 (PC)
        document.addEventListener('click', closeMenuIfOutside);
        
        // 터치 이벤트 (아이패드/모바일)
        document.addEventListener('touchstart', function(e) {
            // 터치가 pen-controls 영역 밖인지 확인
            const penMenu = document.getElementById('ptg-pen-menu');
            const penControls = document.querySelector('.ptg-pen-controls');
            if (penMenu && penControls && penMenu.style.display !== 'none') {
                const touchTarget = e.target;
                // 터치 대상이 pen-controls 내부가 아니면 메뉴 닫기
                if (!penControls.contains(touchTarget)) {
                    // 약간의 지연을 두어 다른 이벤트와 충돌 방지
                    setTimeout(() => {
                        penMenu.style.display = 'none';
                    }, 100);
                }
            }
        }, { passive: true });
        
        // 포커스가 벗어날 때 메뉴 닫기 (PC)
        document.addEventListener('focusout', function(e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            if (penMenu && penMenu.style.display !== 'none') {
                // 포커스가 pen-controls 영역 밖으로 나갔는지 확인
                setTimeout(() => {
                    const activeElement = document.activeElement;
                    const penControls = document.querySelector('.ptg-pen-controls');
                    if (penControls && !penControls.contains(activeElement)) {
                        penMenu.style.display = 'none';
                    }
                }, 0);
            }
        });
        
        // blur 이벤트도 추가 (터치 디바이스 지원)
        const penControls = document.querySelector('.ptg-pen-controls');
        if (penControls) {
            penControls.addEventListener('blur', function(e) {
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu && penMenu.style.display !== 'none') {
                    setTimeout(() => {
                        const activeElement = document.activeElement;
                        if (!penControls.contains(activeElement)) {
                            penMenu.style.display = 'none';
                        }
                    }, 100);
                }
            }, true);
        }
        
        // 퀴즈 카드 클릭/터치 시 메뉴 닫기
        function closeMenuOnCardInteraction(e) {
            const penMenu = document.getElementById('ptg-pen-menu');
            if (penMenu && penMenu.style.display !== 'none') {
                penMenu.style.display = 'none';
            }
        }
        
        const quizCard = document.querySelector('.ptg-quiz-card');
        if (quizCard) {
            quizCard.addEventListener('click', closeMenuOnCardInteraction);
            quizCard.addEventListener('touchstart', closeMenuOnCardInteraction, { passive: true });
        }
    }
    
    /**
     * 드로잉 툴바 버튼 이벤트 설정
     */
    function setupDrawingToolbarEvents() {
        const toolbar = document.getElementById('ptg-drawing-toolbar');
        if (!toolbar) return;
        
        // 이벤트 위임 사용
        toolbar.addEventListener('click', function(e) {
            // 닫기 버튼 처리
            const closeBtn = e.target.closest('.ptg-btn-close-drawing');
            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();
                toggleDrawing(false);
                return;
            }
            
            // 펜 색상/두께 버튼 처리
            const colorBtn = e.target.closest('.ptg-pen-color-btn');
            if (colorBtn) {
                e.preventDefault();
                e.stopPropagation();
                const color = colorBtn.getAttribute('data-color');
                if (color) {
                    setPenColor(color);
                    // 선택된 색상 버튼 표시
                    toolbar.querySelectorAll('.ptg-pen-color-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    colorBtn.classList.add('active');
                }
                return;
            }
            
            // 슬라이더 처리
            const widthSlider = e.target.closest('#ptg-pen-width-slider');
            if (widthSlider) {
                // 슬라이더는 input 이벤트로 처리하므로 여기서는 반환만
                return;
            }
            
            // 드로잉 도구 버튼 처리
            const target = e.target.closest('.ptg-btn-draw');
            if (!target) {
                // 펜 메뉴 외부 클릭 시 메뉴 닫기
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu && !e.target.closest('.ptg-pen-controls')) {
                    penMenu.style.display = 'none';
                }
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const tool = target.getAttribute('data-tool');
            if (!tool) return;
            
            // 펜 버튼 클릭 시 메뉴 토글
            if (tool === 'pen') {
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu) {
                    const isVisible = penMenu.style.display !== 'none';
                    penMenu.style.display = isVisible ? 'none' : 'block';
                }
            } else {
                // 다른 도구 선택 시 펜 메뉴 닫기
                const penMenu = document.getElementById('ptg-pen-menu');
                if (penMenu) {
                    penMenu.style.display = 'none';
                }
            }
            
            // 모든 버튼에서 active 클래스 제거
            toolbar.querySelectorAll('.ptg-btn-draw').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 선택된 버튼에 active 클래스 추가
            target.classList.add('active');
            
            // 도구에 따라 기능 실행
            switch(tool) {
                case 'pen':
                    setDrawingTool('pen');
                    break;
                case 'eraser':
                    setDrawingTool('eraser');
                    break;
                case 'undo':
                    undoDrawing();
                    break;
                case 'redo':
                    redoDrawing();
                    break;
                case 'clear':
                    clearDrawing();
                    break;
            }
        });
    }
    
    /**
     * 키보드 단축키 설정
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Esc: 패널 닫기
            if (e.key === 'Escape') {
                if (QuizState.drawingEnabled) {
                    toggleDrawing(false);
                }
            }
        });
    }
    
    /**
     * 문제 로드
     */
async function loadQuestion() {
        // 종료되었거나 실행 상태가 아니면 로드하지 않음
        if (QuizState.terminated || QuizState.appState !== 'running') {
            return;
        }
        const seq = ++QuizState.requestSeq;
        // questionId가 0이면 기본값으로 문제 목록 로드 시도
        if (QuizState.questionId === 0) {
            // 기본값으로 5문제 로드
            try {
                const questionIds = await loadQuestionsList({ limit: 5 });
                if (questionIds && questionIds.length > 0) {
                    QuizState.questions = questionIds;
                    QuizState.currentIndex = 0;
                    QuizState.questionId = questionIds[0];
                    // 재귀 호출로 첫 번째 문제 로드
                    return await loadQuestion();
                } else {
                    showError('문제를 찾을 수 없습니다.');
                    return;
                }
            } catch (error) {
                console.error('[PTG Quiz] 기본 문제 목록 로드 오류:', error);
                showError('문제 목록을 불러오는 중 오류가 발생했습니다.');
                return;
            }
        }
        
        // questionId가 여전히 0이면 에러 (안전장치)
        if (QuizState.questionId === 0) {
            console.error('[PTG Quiz] questionId가 0입니다. 기본값 처리에 실패했습니다.');
            showError('문제를 불러올 수 없습니다. 페이지를 새로고침해 주세요.');
            return;
        }
        
        // 테스트 모드: 404/401 시나리오를 시뮬레이션하기 위한 모드
        if (typeof window !== 'undefined') {
            if (window.PTGQuizTestMode404) {
                const err = new Error('Not Found');
                err.status = 404;
                throw err;
            }
            if (window.PTGQuizTestMode401) {
                const err = new Error('Unauthorized');
                err.status = 401;
                throw err;
            }
        }
        try {
            // PTGPlatform 확인
            if (typeof PTGPlatform === 'undefined') {
                // PTGPlatform이 없으면 직접 fetch 사용
                const restBase = config.restUrl || '/wp-json/ptg-quiz/v1/';
                const fullUrl = (restBase.startsWith('http') ? restBase : window.location.origin + restBase) + `questions/${QuizState.questionId}`;
                
                const fetchResponse = await fetch(fullUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce || ''
                    }
                });
                
                if (!fetchResponse.ok) {
                    throw new Error(`HTTP ${fetchResponse.status}: ${fetchResponse.statusText}`);
                }
                
                const data = await fetchResponse.json();
                
                if (!data.success || !data.data) {
                    const msg = data.message || '문제를 불러올 수 없습니다.';
                    const isNotFound = /Not Found|404|라우트를 찾을 수 없습니다|URL과 요청한/.test(msg) || /404/.test(msg);
                    if (isNotFound) {
                        showError('해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요.');
                        return;
                    }
                    throw new Error(msg);
                }
                
                if (QuizState.terminated || QuizState.appState !== 'running' || seq < QuizState.requestSeq) return;
                QuizState.questionData = data.data;
                renderQuestion(data.data);
                
                // progress-section 표시 (단일 문제인 경우)
                if (QuizState.questions.length === 0) {
                    showProgressSection();
                    updateProgress(1, 1);
                }
                
                if (QuizState.terminated || QuizState.appState !== 'running' || seq < QuizState.requestSeq) return;
                await loadUserState();
                
                // 문제 로드 완료 후 헤더로 스크롤
                setTimeout(() => {
                    scrollToHeader();
                }, 100);
                
                return;
            }
            
            // 플랫폼 헬퍼 사용 - ptg-quiz/v1 엔드포인트 사용
            const endpoint = `ptg-quiz/v1/questions/${QuizState.questionId}`;

            const response = await PTGPlatform.get(endpoint);

            if (!response || !response.success || !response.data) {
                const errorMsg = response?.message || '문제를 불러올 수 없습니다.';
                const isNotFound = /Not Found|404|라우트를 찾을 수 없습니다|URL과 요청한/.test(errorMsg) || /404/.test(errorMsg);
                if (isNotFound) {
                    showError('해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요.');
                    return;
                }
                throw new Error(errorMsg);
            }
            
            if (QuizState.terminated || QuizState.appState !== 'running' || seq < QuizState.requestSeq) return;
            QuizState.questionData = response.data;
            renderQuestion(response.data);
            
            // 사용자 상태 로드
            if (QuizState.terminated || QuizState.appState !== 'running' || seq < QuizState.requestSeq) return;
            await loadUserState();
            
            // 문제 로드 완료 후 헤더로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
            
    } catch (error) {
        console.error('[PTG Quiz] 문제 로드 오류:', error);
        console.error('[PTG Quiz] 에러 스택:', error.stack);
        const errorMessage = (error && error.message) || '알 수 없는 오류가 발생했습니다.';
        // 404/라우트 매핑 문제에 대한 우아한 처리
        if (typeof errorMessage === 'string') {
            const isNotFound = /404|Not Found|라우트를 찾을 수 없습니다|URL과 요청한/.test(errorMessage);
            if (isNotFound) {
                if (typeof window.__PTG_ORIG_ALERT === 'function') {
                    window.__PTG_ORIG_ALERT('다음 문제 기능은 추후 구현됩니다.');
                }
                showError('문제를 불러오는 중 오류가 발생했습니다: 해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요.');
                return;
            }
            if (errorMessage.includes('404') || errorMessage.includes('라우트를 찾을 수 없습니다')) {
                if (typeof window.__PTG_ORIG_ALERT === 'function') {
                    window.__PTG_ORIG_ALERT('다음 문제 기능은 추후 구현됩니다.');
                }
                showError('문제를 불러오는 중 오류가 발생했습니다: 해당 문제가 존재하지 않거나 비활성화되어 있을 수 있습니다. 다른 문제를 시도해 주세요.');
                return;
            }
        }
        showError('문제를 불러오는 중 오류가 발생했습니다: ' + errorMessage);
        
        // 로딩 표시 제거
        const card = document.getElementById('ptg-quiz-card');
        if (card) {
            card.innerHTML = `
                <div class="ptg-question-content">
                    <p style="color: red;">문제를 불러올 수 없습니다: ${errorMessage}</p>
                    <p style="color: gray; font-size: 12px;">문제 ID: ${QuizState.questionId}</p>
                </div>
            `;
        }
        // 로딩 타임아웃 정리
        if (QuizState.loadingTimeout) {
            clearTimeout(QuizState.loadingTimeout);
            QuizState.loadingTimeout = null;
        }
    }
    }
    
    /**
     * 사용자 상태 로드
     */
    async function loadUserState() {
        try {
            const response = await PTGPlatform.get(`ptg-quiz/v1/questions/${QuizState.questionId}/state`);
            
            if (response && response.success && response.data) {
                QuizState.userState = response.data;
                
                // 북마크 상태 업데이트
                const btnBookmark = document.querySelector('.ptg-btn-bookmark');
                if (btnBookmark) {
                    if (QuizState.userState.bookmarked) {
                    btnBookmark.classList.add('active');
                        const icon = btnBookmark.querySelector('.ptg-icon');
                        if (icon) icon.textContent = '★';
                    } else {
                        btnBookmark.classList.remove('active');
                        const icon = btnBookmark.querySelector('.ptg-icon');
                        if (icon) icon.textContent = '☆';
                    }
                }
                
                // 복습 필요 상태 업데이트
                const btnReview = document.querySelector('.ptg-btn-review');
                if (btnReview) {
                    if (QuizState.userState.needs_review) {
                    btnReview.classList.add('active');
                    } else {
                        btnReview.classList.remove('active');
                    }
                }
                
            }
        } catch (error) {
            console.error('사용자 상태 로드 오류:', error);
        }
    }
    
    /**
     * 문제 렌더링 (공통 퀴즈 UI 컴포넌트 사용)
     */
    function renderQuestion(question) {
        // 종료 상태에서 렌더 차단
        if (QuizState.terminated) {
            return;
        }
        // API에서 이미 파싱된 데이터 사용 (question_text, options)
        const questionText = question.question_text || question.content || '';
        const options = question.options || [];
        
        // 문제 번호 계산 (연속 퀴즈인 경우)
        const questionNumber = QuizState.questions.length > 0 
            ? QuizState.currentIndex + 1 
            : null;
        const totalQuestions = QuizState.questions.length > 0 
            ? QuizState.questions.length 
            : null;
        
        const questionNumberPrefix = questionNumber 
            ? `${questionNumber}. ` 
            : '';
        const questionNumberSuffix = totalQuestions 
            ? ` (${questionNumber}/${totalQuestions})` 
            : '';
        
        // 옵션이 없으면 경고
        if (!options || options.length === 0) {
            console.error('[PTG Quiz] 선택지가 없음');
        }
        
        // 문제 텍스트와 선택지를 하나의 카드 안에 표시 (기출 문제 학습 형식)
        const questionCardEl = document.getElementById('ptg-quiz-card');
        if (questionCardEl) {
            // 로딩 표시 제거 (처음 한 번)
            const loadingEl = questionCardEl.querySelector('.ptg-quiz-loading');
            if (loadingEl) {
                loadingEl.remove();
            }
            
            // 해설 영역을 보존하기 위해 먼저 확인
            let explanationEl = document.getElementById('ptg-quiz-explanation');
            const explanationExists = explanationEl !== null;
            
            // 질문 텍스트와 선택지 컨테이너를 함께 구성
            // 해설 영역이 없으면 생성, 있으면 기존 것 유지
            if (!explanationExists) {
            questionCardEl.innerHTML = `
                <div class="ptg-question-content">
                    ${questionNumberPrefix}${questionText}${questionNumberSuffix}
                </div>
                <div class="ptg-quiz-choices" id="ptg-quiz-choices">
                    <!-- 선택지가 동적으로 로드됨 -->
                </div>
                    <div class="ptg-quiz-explanation" id="ptg-quiz-explanation" style="display: none;">
                        <!-- 해설이 동적으로 로드됨 -->
                </div>
            `;
            } else {
                // 해설이 이미 있으면 선택지와 문제 텍스트만 업데이트
                const questionContent = questionCardEl.querySelector('.ptg-question-content');
                const choicesContainer = document.getElementById('ptg-quiz-choices');
                
                // 문제 텍스트 업데이트
                if (questionContent) {
                    questionContent.innerHTML = `${questionNumberPrefix}${questionText}${questionNumberSuffix}`;
                } else {
                    const newQuestionContent = document.createElement('div');
                    newQuestionContent.className = 'ptg-question-content';
                    newQuestionContent.innerHTML = `${questionNumberPrefix}${questionText}${questionNumberSuffix}`;
                    if (choicesContainer) {
                        choicesContainer.parentNode.insertBefore(newQuestionContent, choicesContainer);
                    } else {
                        questionCardEl.insertBefore(newQuestionContent, explanationEl);
                    }
                }
                
                // 선택지 컨테이너 업데이트
                if (choicesContainer) {
                    choicesContainer.innerHTML = '<!-- 선택지가 동적으로 로드됨 -->';
                } else {
                    const newChoicesContainer = document.createElement('div');
                    newChoicesContainer.className = 'ptg-quiz-choices';
                    newChoicesContainer.id = 'ptg-quiz-choices';
                    newChoicesContainer.innerHTML = '<!-- 선택지가 동적으로 로드됨 -->';
                    questionCardEl.insertBefore(newChoicesContainer, explanationEl);
                }
                
                // 로딩 표시 제거 (해설이 있을 때 추가 확인)
                const loadingElAfter = questionCardEl.querySelector('.ptg-quiz-loading');
                if (loadingElAfter) {
                    loadingElAfter.remove();
                }
            }
            
            // 공통 퀴즈 UI 컴포넌트 사용
            if (typeof PTGQuizUI !== 'undefined' && typeof PTGQuizUI.displayOptions === 'function') {
                const container = document.getElementById('ptg-quiz-choices');
                if (container) {
                    try {
                        PTGQuizUI.displayOptions(options, {
                            containerId: 'ptg-quiz-choices',
                            answerName: 'ptg-answer'
                        });
                    } catch (error) {
                        console.error('[PTG Quiz] displayOptions 호출 오류:', error);
                        renderChoices(options);
                    }
                } else {
                    console.error('[PTG Quiz] 컨테이너를 찾을 수 없음: ptg-quiz-choices');
                    renderChoices(options);
                }
            } else {
                // 공통 컴포넌트가 없는 경우 기존 방식 사용 (로그 제거)
                renderChoices(options);
            }
            
            // 최종 확인: 로딩 표시가 남아있으면 제거
            const finalLoadingEl = questionCardEl.querySelector('.ptg-quiz-loading');
            if (finalLoadingEl) {
                finalLoadingEl.remove();
            }
            
            // 문제 카드가 렌더링된 직후 헤더로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 150);
        } else {
            console.error('[PTG Quiz] 문제 카드를 찾을 수 없음: ptg-quiz-card');
        }
        
        // 버튼 상태 초기화: 답안 제출 버튼 표시, 다음 문제 버튼 숨김
        const btnCheck = document.getElementById('ptg-btn-check-answer');
        if (btnCheck) {
            if (QuizState.terminated) { btnCheck.style.display = 'none'; btnCheck.disabled = true; } else {
            btnCheck.style.display = 'inline-block';
            btnCheck.disabled = false;
            }
        }
        
        const btnNext = document.getElementById('ptg-btn-next-question');
        if (btnNext) {
            if (QuizState.terminated) { btnNext.style.display = 'none'; btnNext.disabled = true; } else {
            btnNext.style.display = 'none';
            }
        }
        
        // 문제 상태 초기화
        QuizState.isAnswered = false;
        QuizState.userAnswer = '';
        
        // 해설 영역 초기화 (새 문제 로드 시 해설 숨김)
        const explanationEl = document.getElementById('ptg-quiz-explanation');
        if (explanationEl) {
            explanationEl.style.display = 'none';
            explanationEl.innerHTML = '<!-- 해설이 동적으로 로드됨 -->';
        }
        
        // 진행률 업데이트 및 progress-section 표시
        if (QuizState.terminated) {
            hideProgressSection();
            return;
        }
        if (questionNumber && totalQuestions) {
            updateProgress(questionNumber, totalQuestions);
            // progress-section 표시 (문제가 로드된 후)
            showProgressSection();
        } else if (QuizState.questions.length > 0) {
            // 연속 퀴즈인 경우
            const current = QuizState.currentIndex + 1;
            const total = QuizState.questions.length;
            updateProgress(current, total);
            showProgressSection();
        } else {
            // 단일 문제인 경우에도 progress-section 표시
            showProgressSection();
            // 단일 문제이므로 1/1로 표시
            updateProgress(1, 1);
        }
    }
    
    /**
     * 문제 본문에서 선택지 파싱 (ptgates-engine 스타일)
     * 
     * @param {string} content 문제 본문 (선택지 포함)
     * @returns {object} { questionText: string, options: array }
     */
    function parseQuestionContent(content) {
        if (!content || typeof content !== 'string') {
            console.error('[PTG Quiz] 파싱 오류: content가 유효하지 않음', content);
            return { questionText: content || '', options: [] };
        }
        
        const options = [];
        let questionText = content;
        
        // HTML 태그 제거 (있는 경우)
        const textContent = content.replace(/<[^>]*>/g, '');
        
        // 먼저 모든 원형 숫자(①~⑳) 또는 괄호 숫자의 위치 찾기
        const numberPattern = /([①-⑳]|\([0-9]+\))\s*/gu;
        const numberMatches = [];
        let numMatch;
        
        // 정규식 리셋
        numberPattern.lastIndex = 0;
        
        while ((numMatch = numberPattern.exec(textContent)) !== null) {
            numberMatches.push({
                number: numMatch[0],
                position: numMatch.index
            });
        }
        
        if (numberMatches.length > 0) {
            const optionRanges = [];
            
            // 각 원형 숫자의 시작/끝 위치 저장
            numberMatches.forEach((numMatch, idx) => {
                const startPos = numMatch.position;
                
                // 다음 원형 숫자의 위치 찾기
                let endPos = textContent.length;
                if (numberMatches[idx + 1]) {
                    endPos = numberMatches[idx + 1].position;
                }
                
                // 옵션 텍스트 추출 (원형 숫자 포함)
                const optionText = textContent.substring(startPos, endPos).trim();
                
                if (optionText) {
                    options.push(optionText);
                    optionRanges.push({ start: startPos, end: endPos });
                }
            });
            
            // 문제 본문에서 옵션 부분 제거
            if (optionRanges.length > 0) {
                const questionParts = [];
                let lastPos = 0;
                
                // 옵션이 없는 부분들을 조합하여 문제 본문 재구성
                optionRanges.forEach((range, idx) => {
                    if (range.start > lastPos) {
                        const part = textContent.substring(lastPos, range.start).trim();
                        if (part) {
                            questionParts.push(part);
                        }
                    }
                    lastPos = range.end;
                });
                
                // 마지막 옵션 이후 텍스트 추가
                if (lastPos < textContent.length) {
                    const part = textContent.substring(lastPos).trim();
                    if (part) {
                        questionParts.push(part);
                    }
                }
                
                questionText = questionParts.join(' ').trim();
            }
        }
        
        return {
            questionText: questionText || content,
            options: options
        };
    }
    
    /**
     * 선택지 렌더링 (ptgates-engine 스타일)
     */
    function renderChoices(options) {
        const choicesContainer = document.getElementById('ptg-quiz-choices');
        if (!choicesContainer) {
            console.error('[PTG Quiz] 선택지 컨테이너를 찾을 수 없음');
            return;
        }
        
        // 컨테이너 초기화 및 스타일 설정
        choicesContainer.innerHTML = '';
        choicesContainer.style.display = 'block';
        choicesContainer.style.width = '100%';
        choicesContainer.style.marginTop = '0';
        choicesContainer.style.padding = '10px 12px';
        
        if (!options || options.length === 0) {
            // 선택지가 없는 경우 (주관식)
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'ptg-text-answer';
            input.id = 'ptg-user-answer';
            input.placeholder = '답을 입력하세요';
            choicesContainer.appendChild(input);
        } else {
            // 객관식 선택지 렌더링
            options.forEach((option, index) => {
                const label = document.createElement('label');
                label.className = 'ptg-quiz-ui-option-label';
                const optionId = `ptg-choice-${index}`;
                label.setAttribute('for', optionId);
                
                // 라벨 스타일
                label.style.display = 'flex';
                label.style.flexDirection = 'row';
                label.style.width = '100%';
                label.style.marginBottom = '0';
                label.style.padding = '4px 8px';
                label.style.alignItems = 'flex-start';
                label.style.border = 'none';
                label.style.borderRadius = '0';
                label.style.cursor = 'pointer';
                label.style.background = 'transparent';
                label.style.boxSizing = 'border-box';
                
                // 라디오 버튼 생성
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'ptg-answer';
                radio.value = option; // 전체 옵션 텍스트를 value로 사용 (원형 숫자 포함)
                radio.id = optionId;
                radio.className = 'ptg-quiz-ui-radio-input';
                
                // 라디오 버튼 스타일
                radio.style.width = '20px';
                radio.style.height = '20px';
                radio.style.minWidth = '20px';
                radio.style.minHeight = '20px';
                radio.style.marginRight = '8px';
                radio.style.marginTop = '2px';
                radio.style.cursor = 'pointer';
                radio.style.flexShrink = '0';
                
                // 옵션 텍스트 (원형 숫자 포함)
                const text = document.createElement('span');
                text.className = 'ptg-quiz-ui-option-text';
                
                // 옵션 텍스트 설정 (확실하게 표시되도록)
                const optionText = String(option || '').trim();
                
                // textContent 사용 (HTML 이스케이프 자동 처리)
                text.textContent = optionText;
                
                // 텍스트 스타일 강제 적용
                text.style.display = 'block';
                text.style.flex = '1';
                text.style.whiteSpace = 'normal';
                text.style.wordWrap = 'break-word';
                text.style.overflowWrap = 'break-word';
                text.style.lineHeight = '1.3';
                text.style.color = '#333';
                text.style.width = 'calc(100% - 40px)';
                text.style.visibility = 'visible';
                text.style.opacity = '1';
                
                // 라벨 클릭 이벤트
                label.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (radio.disabled) { return; }
                    if (e.target !== radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                
                // 라디오 버튼 변경 이벤트
                radio.addEventListener('change', function(e) {
                    QuizState.userAnswer = e.target.value;
                });
                
                // 요소 추가 (순서 중요)
                label.appendChild(radio);
                label.appendChild(text);
                choicesContainer.appendChild(label);
                
                // 생성 후 즉시 확인 (다른 스크립트 간섭 방지)
                setTimeout(() => {
                    const actualText = text.textContent || text.innerText || '';
                    if (!actualText || actualText.trim() === '') {
                        // 다시 설정 시도 (에러 로그 제거)
                        text.textContent = optionText;
                        text.innerText = optionText;
                    }
                }, 100);
            });
            
            // 선택지 렌더링 완료 후 헤더로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 200);
        }
    }
    
    /**
     * 타이머 시작
     */
    function startTimer() {
        if (QuizState.timerInterval) {
            clearInterval(QuizState.timerInterval);
        }
        
        // 퀴즈 시작 시간 설정 (처음 시작할 때만)
        if (!QuizState.startTime) {
            QuizState.startTime = Date.now();
        }
        
        QuizState.timerInterval = setInterval(() => {
            QuizState.timerSeconds--;
            
            if (QuizState.timerSeconds <= 0) {
                clearInterval(QuizState.timerInterval);
                timerExpired();
                return;
            }
            
            updateTimerDisplay();
        }, 1000);
        
        updateTimerDisplay();
    }
    
    /**
     * 타이머 표시 업데이트
     */
    function updateTimerDisplay() {
        // 기존 타이머 표시 (호환성 유지)
        const display = document.getElementById('ptg-timer-display');
        if (display) {
            const minutes = Math.floor(QuizState.timerSeconds / 60);
            const seconds = QuizState.timerSeconds % 60;
            display.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
        
        // progress-section의 타이머 업데이트
        const progressTimer = document.getElementById('ptgates-timer');
        if (progressTimer) {
            const minutes = Math.floor(QuizState.timerSeconds / 60);
            const seconds = QuizState.timerSeconds % 60;
            progressTimer.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    }
    
    /**
     * 타이머 만료
     */
    function timerExpired() {
        if (QuizState.terminated) return;
        showError('시간이 종료되었습니다.');
        // 자동 제출 또는 알림
    }
    
    /**
     * 북마크 토글
     */
    async function toggleBookmark() {
        const btn = document.querySelector('.ptg-btn-bookmark');
        if (!btn) return;
        
        const isBookmarked = btn.classList.contains('active');
        
        try {
            await PTGPlatform.patch(`ptg-quiz/v1/questions/${QuizState.questionId}/state`, {
                bookmarked: !isBookmarked
            });
            
            // 토글: 선택되어 있으면 해제, 해제되어 있으면 선택
            if (isBookmarked) {
                btn.classList.remove('active');
                const icon = btn.querySelector('.ptg-icon');
                if (icon) icon.textContent = '☆';
            } else {
                btn.classList.add('active');
                const icon = btn.querySelector('.ptg-icon');
                if (icon) icon.textContent = '★';
            }
            
            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
        } catch (error) {
            console.error('북마크 업데이트 오류:', error);
            showError('북마크 업데이트에 실패했습니다.');
        }
    }
    
    /**
     * 복습 필요 토글
     */
    async function toggleReview() {
        const btn = document.querySelector('.ptg-btn-review');
        if (!btn) return;
        
        const needsReview = btn.classList.contains('active');
        
        try {
            await PTGPlatform.patch(`ptg-quiz/v1/questions/${QuizState.questionId}/state`, {
                needs_review: !needsReview
            });
            
            // 토글: 선택되어 있으면 해제, 해제되어 있으면 선택
            if (needsReview) {
                btn.classList.remove('active');
            } else {
                btn.classList.add('active');
            }
            
            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
        } catch (error) {
            console.error('복습 필요 업데이트 오류:', error);
            showError('복습 필요 업데이트에 실패했습니다.');
        }
    }
    
    /**
     * 메모 패널 토글
     */
    function toggleNotesPanel(force = null) {
        const panel = document.getElementById('ptg-notes-panel');
        if (!panel) {
            return;
        }
        
        const btnNotes = document.querySelector('.ptg-btn-notes');
        
        // 인라인 스타일과 computedStyle 모두 확인
        const inlineDisplay = panel.style.display;
        const computedStyle = window.getComputedStyle(panel);
        const computedDisplay = computedStyle.display;
        
        // display가 'none'이 아니면 표시된 것으로 간주
        const isCurrentlyVisible = inlineDisplay !== 'none' && computedDisplay !== 'none' && inlineDisplay !== '' && computedDisplay !== '';
        
        // force가 지정되지 않았으면 토글, 지정되었으면 그대로 사용
        const shouldShow = force !== null ? force : !isCurrentlyVisible;
        
        if (shouldShow) {
            panel.style.display = 'block';
            if (btnNotes) {
                btnNotes.classList.add('active');
            }
            
            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
            
            // textarea에 포커스
            const textarea = document.getElementById('ptg-notes-textarea');
            if (textarea) {
                setTimeout(() => {
                    textarea.focus();
                }, 150);
            }
        } else {
            panel.style.display = 'none';
            if (btnNotes) {
                btnNotes.classList.remove('active');
            }
            
            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
        }
    }
    
    /**
     * 드로잉 토글
     */
    async function toggleDrawing(force = null) {
        // 모바일에서는 드로잉 기능 비활성화
        if (QuizState.deviceType === 'mobile') {
            return;
        }
        
        const overlay = document.getElementById('ptg-drawing-overlay');
        const toolbar = document.getElementById('ptg-drawing-toolbar');
        if (!overlay || !toolbar) return;
        
        const btnDrawing = document.querySelector('.ptg-btn-drawing');
        
        // 현재 표시 상태 확인
        const computedStyle = window.getComputedStyle(overlay);
        const isCurrentlyVisible = computedStyle.display !== 'none' && overlay.style.display !== 'none';
        
        // force가 지정되지 않았으면 토글, 지정되었으면 그대로 사용
        const shouldShow = force !== null ? force : !isCurrentlyVisible;
        
        if (shouldShow) {
            overlay.style.display = 'block';
            overlay.classList.add('active');
            toolbar.style.display = 'flex';
            QuizState.drawingEnabled = true;
            if (btnDrawing) {
                btnDrawing.classList.add('active');
            }
            
            // 드로잉 캔버스 초기화
            initDrawingCanvas();
            
            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
        } else {
            // 드로잉 모드 종료 시 저장된 드로잉을 서버에 저장 (완료까지 대기)
            if (QuizState.canvasContext) {
                // 디바운스 타이머가 있으면 취소하고 즉시 저장
                if (QuizState.drawingSaveTimeout) {
                    clearTimeout(QuizState.drawingSaveTimeout);
                    QuizState.drawingSaveTimeout = null;
                }
                // 저장 완료까지 대기
                await saveDrawingToServer();
            }
            
            overlay.style.display = 'none';
            overlay.classList.remove('active');
            toolbar.style.display = 'none';
            QuizState.drawingEnabled = false;
            
            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
            if (btnDrawing) {
                btnDrawing.classList.remove('active');
            }
        }
    }
    
    /**
     * 드로잉 캔버스 초기화
     */
    function initDrawingCanvas() {
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const card = document.getElementById('ptg-quiz-card');
        const toolbar = document.querySelector('.ptg-quiz-toolbar');
        
        if (!card) return;
        
        // 툴바 위치 확인 (툴바 아래부터 캔버스 시작)
        let toolbarBottom = 0;
        if (toolbar) {
            const toolbarRect = toolbar.getBoundingClientRect();
            toolbarBottom = toolbarRect.bottom + 5; // 약간의 여백 추가
        }
        
        // 문제 카드의 위치와 크기 계산 (해설이 포함되어 있으면 자동으로 포함됨)
        const cardRect = card.getBoundingClientRect();
        
        // 캔버스는 툴바 아래부터 시작, 카드 전체를 포함 (해설 포함)
        let startY = Math.max(cardRect.top, toolbarBottom);
        let endY = cardRect.bottom; // 해설이 카드 안에 있으면 자동으로 포함됨
        
        // 캔버스 크기와 위치 설정
        const width = cardRect.width;
        const height = Math.max(0, endY - startY); // 높이가 음수가 되지 않도록
        const left = cardRect.left;
        const top = startY;
        
        // 오버레이 위치 설정
        const overlay = document.getElementById('ptg-drawing-overlay');
        if (overlay) {
            canvas.style.position = 'fixed';
            canvas.style.left = left + 'px';
            canvas.style.top = top + 'px';
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            canvas.style.zIndex = '101'; // 툴바(z-index: 1000)보다 낮게
        }
        
        // 캔버스 실제 크기 설정 (고해상도 디스플레이 지원)
        const dpr = window.devicePixelRatio || 1;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        
        // 컨텍스트 설정
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        ctx.scale(dpr, dpr);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = QuizState.penWidth; // 펜 두께 (기본값: 10px)
        ctx.globalAlpha = QuizState.penAlpha; // 불투명도를 globalAlpha로 직접 설정
        ctx.globalCompositeOperation = 'source-over'; // source-over 모드로 변경 (겹침 점 방지)
        ctx.strokeStyle = QuizState.penColor;
        
        QuizState.canvasContext = ctx;
        
        // 드로잉 이벤트 리스너 등록
        setupDrawingEvents(canvas);
        
        // 기존 드로잉 히스토리 로드 (캔버스 컨텍스트 초기화 후)
        loadDrawingHistory();
        
        // 기본 도구를 펜으로 설정
        setDrawingTool('pen');
        
        // 펜 버튼에 active 클래스 추가
        const penBtn = document.querySelector('.ptg-btn-draw[data-tool="pen"]');
        if (penBtn) {
            penBtn.classList.add('active');
        }
        
        // 펜 색상/두께 메뉴 초기화
        initializePenMenu();
        
        // 윈도우 리사이즈 시 캔버스 재조정
        const resizeHandler = function() {
            if (QuizState.drawingEnabled) {
                setTimeout(() => {
                    initDrawingCanvas();
                }, 100);
            }
        };
        
        // 기존 리사이즈 핸들러 제거 후 재등록 (중복 방지)
        if (window._ptgDrawingResizeHandler) {
            window.removeEventListener('resize', window._ptgDrawingResizeHandler);
        }
        window._ptgDrawingResizeHandler = resizeHandler;
        window.addEventListener('resize', resizeHandler);
    }
    
    /**
     * 드로잉 이벤트 설정
     */
    function setupDrawingEvents(canvas) {
        if (!canvas) return;
        
        // 기존 이벤트 리스너 제거 (중복 방지)
        canvas.removeEventListener('mousedown', handleMouseDown);
        canvas.removeEventListener('mousemove', handleMouseMove);
        canvas.removeEventListener('mouseup', handleMouseUp);
        canvas.removeEventListener('mouseleave', handleMouseUp);
        canvas.removeEventListener('touchstart', handleTouchStart);
        canvas.removeEventListener('touchmove', handleTouchMove);
        canvas.removeEventListener('touchend', handleTouchEnd);
        
        // 마우스 이벤트
        canvas.addEventListener('mousedown', handleMouseDown);
        canvas.addEventListener('mousemove', handleMouseMove);
        canvas.addEventListener('mouseup', handleMouseUp);
        canvas.addEventListener('mouseleave', handleMouseUp);
        
        // 터치 이벤트
        canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
        canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
        canvas.addEventListener('touchend', handleTouchEnd);
    }
    
    /**
     * 마우스 다운 이벤트
     */
    function handleMouseDown(e) {
        if (!QuizState.drawingEnabled || !QuizState.canvasContext) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        
        QuizState.isDrawing = true;
        QuizState.lastX = (e.clientX - rect.left) * dpr;
        QuizState.lastY = (e.clientY - rect.top) * dpr;
        
        // 히스토리 저장 (새로운 선 시작 전 상태 저장)
        saveHistoryState();
    }
    
    /**
     * 마우스 이동 이벤트
     */
    function handleMouseMove(e) {
        if (!QuizState.isDrawing || !QuizState.canvasContext) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const x = (e.clientX - rect.left) * dpr;
        const y = (e.clientY - rect.top) * dpr;
        
        const ctx = QuizState.canvasContext;
        
        // 이전 위치와 현재 위치 사이의 거리 계산
        const lastXNormalized = QuizState.lastX / dpr;
        const lastYNormalized = QuizState.lastY / dpr;
        const currentXNormalized = x / dpr;
        const currentYNormalized = y / dpr;
        const distance = Math.sqrt(
            Math.pow(currentXNormalized - lastXNormalized, 2) + 
            Math.pow(currentYNormalized - lastYNormalized, 2)
        );
        
        // 최소 거리 체크: 너무 가까운 점은 건너뛰기 (겹침 방지)
        // 펜 두께의 30% 또는 최소 2px로 설정하여 겹침 점을 더 효과적으로 방지
        const minDistance = Math.max(2.0, QuizState.penWidth * 0.3);
        if (distance < minDistance) {
            // 너무 가까우면 건너뛰기
            return;
        }
        
        ctx.beginPath();
        ctx.moveTo(lastXNormalized, lastYNormalized);
        ctx.lineTo(currentXNormalized, currentYNormalized);
        
        if (QuizState.drawingTool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
            ctx.globalAlpha = 1.0;
            ctx.lineWidth = 20;
            ctx.strokeStyle = '#000';
        } else {
            // source-over 블렌드 모드와 불투명도 사용: 겹침 점 방지
            ctx.globalCompositeOperation = 'source-over';
            ctx.globalAlpha = QuizState.penAlpha; // 불투명도를 globalAlpha로 직접 설정
            ctx.lineWidth = QuizState.penWidth;
            ctx.strokeStyle = QuizState.penColor; // 불투명도는 globalAlpha로 처리
        }
        
        ctx.stroke();
        
        QuizState.lastX = x;
        QuizState.lastY = y;
    }
    
    /**
     * 마우스 업 이벤트
     */
    function handleMouseUp(e) {
        if (!QuizState.isDrawing) return;
        
        QuizState.isDrawing = false;
        
        // 선 그리기 완료 후 현재 상태를 히스토리에 추가
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas || !QuizState.canvasContext) return;
        
        // Redo 가능한 히스토리 제거
        if (QuizState.drawingHistory.length > QuizState.drawingHistoryIndex + 1) {
            QuizState.drawingHistory = QuizState.drawingHistory.slice(0, QuizState.drawingHistoryIndex + 1);
        }
        
        // 현재 상태를 히스토리에 추가 (선 그리기 완료)
        const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(imageData);
        
        // 히스토리 최대 50개로 제한
        if (QuizState.drawingHistory.length > 50) {
            QuizState.drawingHistory.shift();
        }
        
        QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
        
        // 드로잉 자동 저장 (디바운스)
        debouncedSaveDrawing();
    }
    
    /**
     * 터치 시작 이벤트
     */
    function handleTouchStart(e) {
        e.preventDefault();
        if (!QuizState.drawingEnabled || !QuizState.canvasContext) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        
        QuizState.isDrawing = true;
        QuizState.lastX = (touch.clientX - rect.left) * dpr;
        QuizState.lastY = (touch.clientY - rect.top) * dpr;
        
        saveHistoryState();
    }
    
    /**
     * 터치 이동 이벤트
     */
    function handleTouchMove(e) {
        e.preventDefault();
        if (!QuizState.isDrawing || !QuizState.canvasContext) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const x = (touch.clientX - rect.left) * dpr;
        const y = (touch.clientY - rect.top) * dpr;
        
        const ctx = QuizState.canvasContext;
        
        // 이전 위치와 현재 위치 사이의 거리 계산
        const lastXNormalized = QuizState.lastX / dpr;
        const lastYNormalized = QuizState.lastY / dpr;
        const currentXNormalized = x / dpr;
        const currentYNormalized = y / dpr;
        const distance = Math.sqrt(
            Math.pow(currentXNormalized - lastXNormalized, 2) + 
            Math.pow(currentYNormalized - lastYNormalized, 2)
        );
        
        // 최소 거리 체크: 너무 가까운 점은 건너뛰기 (겹침 방지)
        // 펜 두께의 30% 또는 최소 2px로 설정하여 겹침 점을 더 효과적으로 방지
        const minDistance = Math.max(2.0, QuizState.penWidth * 0.3);
        if (distance < minDistance) {
            // 너무 가까우면 건너뛰기
            return;
        }
        
        ctx.beginPath();
        ctx.moveTo(lastXNormalized, lastYNormalized);
        ctx.lineTo(currentXNormalized, currentYNormalized);
        
        if (QuizState.drawingTool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
            ctx.globalAlpha = 1.0;
            ctx.lineWidth = 20;
            ctx.strokeStyle = '#000';
        } else {
            // source-over 블렌드 모드와 불투명도 사용: 겹침 점 방지
            ctx.globalCompositeOperation = 'source-over';
            ctx.globalAlpha = QuizState.penAlpha; // 불투명도를 globalAlpha로 직접 설정
            ctx.lineWidth = QuizState.penWidth;
            ctx.strokeStyle = QuizState.penColor; // 불투명도는 globalAlpha로 처리
        }
        
        ctx.stroke();
        
        QuizState.lastX = x;
        QuizState.lastY = y;
    }
    
    /**
     * 터치 종료 이벤트
     */
    function handleTouchEnd(e) {
        e.preventDefault();
        handleMouseUp(e);
    }
    
    /**
     * 히스토리 상태 저장
     */
    function saveHistoryState() {
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(imageData);
        
        // 히스토리 최대 50개로 제한
        if (QuizState.drawingHistory.length > 50) {
            QuizState.drawingHistory.shift();
        }
        
        QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
    }
    
    /**
     * 드로잉 히스토리 로드
     */
    function loadDrawingHistory() {
        // 기존 히스토리가 있으면 복원 (서버에서 로드)
        QuizState.drawingHistory = [];
        QuizState.drawingHistoryIndex = -1;
        
        // 서버에서 저장된 드로잉 로드
        loadDrawingFromServer();
    }
    
    /**
     * 서버에서 저장된 드로잉 로드
     */
    async function loadDrawingFromServer() {
        if (!QuizState.questionId) {
            return;
        }
        
        // 캔버스 컨텍스트가 준비될 때까지 대기
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas || !QuizState.canvasContext) {
            // 잠시 후 재시도
            setTimeout(loadDrawingFromServer, 200);
            return;
        }
        
        try {
            // 서버에서 드로잉 데이터 가져오기 (현재 상태와 기기 타입에 맞는 드로잉만 로드)
            // 답안 제출 전(is_answered=0) 또는 답안 제출 후(is_answered=1)
            // 기기 타입별로 각각 로드 (PC, Tablet, Mobile 각각 답안 제출 전/후 2개씩, 총 6개)
            const isAnswered = QuizState.isAnswered ? 1 : 0;
            const response = await window.PTGPlatform.get(`ptg-quiz/v1/questions/${QuizState.questionId}/drawings`, {
                is_answered: isAnswered,
                device_type: QuizState.deviceType // 기기 타입 (pc, tablet, mobile)
            });
            
            if (response && response.success && response.data && response.data.length > 0) {
                const drawing = response.data[0]; // 가장 최근 드로잉 사용
                
                if (drawing.format === 'json' && drawing.data) {
                    // JSON 데이터를 ImageData로 변환
                    try {
                        const imageDataObj = JSON.parse(drawing.data);
                        
                        // 빈 드로잉인지 확인
                        if (imageDataObj.empty || !imageDataObj.data || imageDataObj.data === '') {
                            // 빈 드로잉이면 로드하지 않음
                            return;
                        }
                        
                        // base64 디코딩 또는 배열 변환
                        let uint8Array;
                        if (imageDataObj.encoded && typeof imageDataObj.data === 'string') {
                            // base64 디코딩
                            const binaryString = atob(imageDataObj.data);
                            uint8Array = new Uint8Array(binaryString.length);
                            for (let i = 0; i < binaryString.length; i++) {
                                uint8Array[i] = binaryString.charCodeAt(i);
                            }
                        } else if (Array.isArray(imageDataObj.data)) {
                            // 기존 형식 (배열) - 하위 호환성
                            uint8Array = new Uint8Array(imageDataObj.data);
                        } else {
                            throw new Error('지원하지 않는 데이터 형식');
                        }
                        
                        // Uint8ClampedArray로 변환
                        const imageData = new ImageData(
                            new Uint8ClampedArray(uint8Array),
                            imageDataObj.width,
                            imageDataObj.height
                        );
                        
                        // 캔버스 크기와 저장된 크기 비교
                        const canvasWidth = canvas.width;
                        const canvasHeight = canvas.height;
                        
                        if (imageDataObj.width === canvasWidth && imageDataObj.height === canvasHeight) {
                            // 크기가 같으면 그대로 사용
                            QuizState.canvasContext.putImageData(imageData, 0, 0);
                            
                            // 히스토리에 추가
                            QuizState.drawingHistory = [imageData];
                            QuizState.drawingHistoryIndex = 0;
                        } else {
                            // 크기가 다르면 임시 캔버스로 스케일링하여 표시
                            const tempCanvas = document.createElement('canvas');
                            tempCanvas.width = imageDataObj.width;
                            tempCanvas.height = imageDataObj.height;
                            const tempCtx = tempCanvas.getContext('2d');
                            
                            // 임시 캔버스에 그리기
                            tempCtx.putImageData(imageData, 0, 0);
                            
                            // 현재 캔버스에 스케일링하여 그리기
                            QuizState.canvasContext.clearRect(0, 0, canvasWidth, canvasHeight);
                            QuizState.canvasContext.drawImage(tempCanvas, 0, 0, canvasWidth, canvasHeight);
                            
                            // 히스토리에 추가 (현재 캔버스 크기로 변환)
                            const currentImageData = QuizState.canvasContext.getImageData(0, 0, canvasWidth, canvasHeight);
                            QuizState.drawingHistory = [currentImageData];
                            QuizState.drawingHistoryIndex = 0;
                        }
                    } catch (e) {
                        // JSON 파싱 실패는 조용히 무시 (에러 로그 제거)
                    }
                }
            }
        } catch (e) {
            // 드로잉이 없거나 로드 실패 시 조용히 무시 (정상적인 상황일 수 있음)
            // 404나 다른 에러는 첫 드로잉이므로 무시해도 됨 (로그 제거)
        }
    }
    
    /**
     * 빈 드로잉을 서버에 저장 (드로잉 삭제)
     */
    async function saveEmptyDrawingToServer() {
        if (!QuizState.questionId) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        try {
            // 빈 드로잉 데이터 생성 (모든 픽셀이 투명)
            const emptyDrawingData = {
                data: '', // 빈 문자열
                width: canvas.width,
                height: canvas.height,
                encoded: true,
                empty: true // 빈 드로잉 표시
            };
            
            // 디바이스 정보 수집 (축약된 버전 - 100자 이내)
            const device = {
                ua: navigator.userAgent ? navigator.userAgent.substring(0, 50) : '',
                plt: navigator.platform || '',
                w: window.screen.width || 0,
                h: window.screen.height || 0
            };
            
            let deviceStr = JSON.stringify(device);
            if (deviceStr.length > 100) {
                deviceStr = JSON.stringify({
                    plt: navigator.platform || '',
                    w: window.screen.width || 0,
                    h: window.screen.height || 0
                });
            }
            
            // 서버에 저장 (빈 드로잉으로 덮어쓰기)
            // 답안 제출 여부와 기기 타입에 따라 구분해서 저장
            // 기기 타입별로 각각 저장/삭제 (PC, Tablet, Mobile 각각 답안 제출 전/후 2개씩, 총 6개)
            await window.PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/drawings`, {
                format: 'json',
                data: JSON.stringify(emptyDrawingData),
                width: canvas.width,
                height: canvas.height,
                device: deviceStr,
                is_answered: QuizState.isAnswered ? 1 : 0,
                device_type: QuizState.deviceType // 기기 타입 (pc, tablet, mobile)
            });
        } catch (e) {
            // 빈 드로잉 저장 실패 시 조용히 무시 (로그 제거)
        }
    }
    
    /**
     * 드로잉을 서버에 저장
     */
    async function saveDrawingToServer() {
        if (!QuizState.questionId || !QuizState.canvasContext) return;
        
        // 이미 저장 중이면 대기
        if (QuizState.savingDrawing) {
            return;
        }
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        QuizState.savingDrawing = true;
        
        try {
            // 캔버스를 ImageData로 변환
            const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
            
            // 빈 캔버스인지 확인 (모든 픽셀이 투명한 경우)
            const isEmpty = imageData.data.every((value, index) => {
                // 알파 채널만 확인 (RGBA의 마지막 바이트)
                return index % 4 !== 3 || value === 0;
            });
            
            // 빈 캔버스는 저장하지 않음
            if (isEmpty) {
                // 빈 드로잉으로 저장 (기존 드로잉 삭제)
                await saveEmptyDrawingToServer();
                return;
            }
            
            // ImageData를 base64로 인코딩하여 압축 (메모리 절약)
            // Uint8ClampedArray를 직접 base64로 변환
            const uint8Array = new Uint8Array(imageData.data);
            let binaryString = '';
            const chunkSize = 8192; // 청크 단위로 처리 (메모리 절약)
            for (let i = 0; i < uint8Array.length; i += chunkSize) {
                const chunk = uint8Array.subarray(i, i + chunkSize);
                binaryString += String.fromCharCode.apply(null, chunk);
            }
            const base64Data = btoa(binaryString);
            
            // ImageData를 JSON으로 변환 (base64 인코딩된 데이터 사용)
            const drawingData = {
                data: base64Data, // base64로 인코딩된 문자열
                width: imageData.width,
                height: imageData.height,
                encoded: true // base64 인코딩 여부 표시
            };
            
            // 디바이스 정보 수집 (축약된 버전 - 100자 이내)
            const device = {
                ua: navigator.userAgent ? navigator.userAgent.substring(0, 50) : '', // userAgent 앞 50자만
                plt: navigator.platform || '',
                w: window.screen.width || 0,
                h: window.screen.height || 0
            };
            
            // JSON 문자열로 변환 후 100자 이내로 제한
            let deviceStr = JSON.stringify(device);
            if (deviceStr.length > 100) {
                // 100자를 초과하면 더 축약
                deviceStr = JSON.stringify({
                    plt: navigator.platform || '',
                    w: window.screen.width || 0,
                    h: window.screen.height || 0
                });
            }
            
            // 서버에 저장
            // 답안 제출 여부와 기기 타입에 따라 구분해서 저장 (답안 제출 전: 0, 답안 제출 후: 1)
            // 기기 타입별로 각각 저장 (PC, Tablet, Mobile 각각 답안 제출 전/후 2개씩, 총 6개)
            const response = await window.PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/drawings`, {
                format: 'json',
                data: JSON.stringify(drawingData),
                width: canvas.width,
                height: canvas.height,
                device: deviceStr,
                is_answered: QuizState.isAnswered ? 1 : 0,
                device_type: QuizState.deviceType // 기기 타입 (pc, tablet, mobile)
            });
            
            // 저장 성공 (로그 제거)
        } catch (e) {
            // 저장 실패 시 조용히 무시 (사용자에게는 에러를 보여주지 않음, 로그 제거)
        } finally {
            QuizState.savingDrawing = false;
        }
    }
    
    /**
     * 드로잉 자동 저장 (디바운스 처리)
     * saveDrawingToServer 함수 정의 후에 생성
     */
    const debouncedSaveDrawing = debounce(saveDrawingToServer, 800); // 0.8초 디바운스 (속도 개선)
    
    /**
     * 드로잉 도구 설정
     */
    function setDrawingTool(tool) {
        QuizState.drawingTool = tool;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        // 커서 변경
        if (tool === 'eraser') {
            canvas.style.cursor = 'grab';
        } else {
            // 펜 커서: SVG 펜 아이콘
            const penCursor = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23333\' d=\'M20.71 4.63l-1.34-1.34c-.39-.39-1.02-.39-1.41 0L9 12.25 11.75 15l8.96-8.96c.39-.39.39-1.02 0-1.41zM7 14a3 3 0 0 0-3 3c0 1.31-1.16 2-2 2 .92 1.22 2.49 2 4 2a4 4 0 0 0 4-4c0-1.31-.84-2.42-2-2.83z\'/%3E%3C/svg%3E") 0 24, crosshair';
            canvas.style.cursor = penCursor;
        }
        
        // 캔버스 컨텍스트가 있으면 펜 설정 업데이트
        if (QuizState.canvasContext) {
            if (tool === 'pen') {
                QuizState.canvasContext.lineWidth = QuizState.penWidth;
                QuizState.canvasContext.globalAlpha = QuizState.penAlpha; // 불투명도를 globalAlpha로 직접 설정
                QuizState.canvasContext.globalCompositeOperation = 'source-over'; // source-over 모드로 변경
                QuizState.canvasContext.strokeStyle = QuizState.penColor;
            }
        }
    }
    
    /**
     * 펜 색상 설정
     */
    function setPenColor(color) {
        const previousColor = QuizState.penColor; // 이전 색상 저장
        QuizState.penColor = color;
        
        // 형광펜 색상 정의 (빨, 노, 파, 초)
        const highlightColors = [
            'rgb(255, 0, 0)',   // 빨강
            'rgb(255, 255, 0)', // 노랑
            'rgb(0, 0, 255)',   // 파랑
            'rgb(0, 255, 0)'    // 초록
        ];
        
        const isHighlightColor = highlightColors.includes(color);
        const isBlack = color === 'rgb(0, 0, 0)';
        const wasBlack = previousColor === 'rgb(0, 0, 0)';
        const wasHighlightColor = highlightColors.includes(previousColor);
        
        // 검정색 선택 시: 두께 2px, 불투명도 50%
        if (isBlack) {
            setPenWidth(2);
            setPenAlpha(0.5);
            
            // 슬라이더 값 업데이트
            const widthSlider = document.getElementById('ptg-pen-width-slider');
            const widthValue = document.getElementById('ptg-pen-width-value');
            const alphaSlider = document.getElementById('ptg-pen-alpha-slider');
            const alphaValue = document.getElementById('ptg-pen-alpha-value');
            
            if (widthSlider && widthValue) {
                widthSlider.value = 2;
                widthValue.textContent = '2';
            }
            if (alphaSlider && alphaValue) {
                alphaSlider.value = 50;
                alphaValue.textContent = '50';
            }
            
            // localStorage에 저장
            savePenSettings();
        }
        // 검정 → 형광펜 색상: 두께 10px, 불투명도 20%로 리셋
        else if (wasBlack && isHighlightColor) {
            setPenWidth(10);
            setPenAlpha(0.2);
            
            // 슬라이더 값 업데이트
            const widthSlider = document.getElementById('ptg-pen-width-slider');
            const widthValue = document.getElementById('ptg-pen-width-value');
            const alphaSlider = document.getElementById('ptg-pen-alpha-slider');
            const alphaValue = document.getElementById('ptg-pen-alpha-value');
            
            if (widthSlider && widthValue) {
                widthSlider.value = 10;
                widthValue.textContent = '10';
            }
            if (alphaSlider && alphaValue) {
                alphaSlider.value = 20;
                alphaValue.textContent = '20';
            }
            
            // localStorage에 저장
            savePenSettings();
        }
        // 형광펜 색상 간 전환: 옵션 변경 없음 (현재 설정 유지)
        // else if (wasHighlightColor && isHighlightColor) - 아무것도 하지 않음
        
        // localStorage에 색상 저장
        savePenSettings();
        
        // 캔버스 컨텍스트 업데이트
        if (QuizState.canvasContext && QuizState.drawingTool === 'pen') {
            QuizState.canvasContext.globalCompositeOperation = 'source-over';
            QuizState.canvasContext.globalAlpha = QuizState.penAlpha; // 불투명도를 globalAlpha로 직접 설정
            QuizState.canvasContext.strokeStyle = color;
        }
    }
    
    /**
     * 펜 불투명도 설정
     * @param {number} alpha - 0~1 범위, 높을수록 진함 (0=완전 투명, 1=완전 불투명)
     */
    function setPenAlpha(alpha) {
        QuizState.penAlpha = alpha; // 0~1 범위, 높을수록 진함
        
        // localStorage에 저장 (슬라이더 이벤트에서 중복 저장 방지를 위해 여기서는 저장하지 않음)
        // savePenSettings()는 슬라이더 이벤트 핸들러에서 호출됨
        
        // 캔버스 컨텍스트 업데이트
        if (QuizState.canvasContext && QuizState.drawingTool === 'pen') {
            QuizState.canvasContext.globalCompositeOperation = 'source-over';
            QuizState.canvasContext.globalAlpha = alpha; // 불투명도를 globalAlpha로 직접 설정
            QuizState.canvasContext.strokeStyle = QuizState.penColor;
        }
    }
    
    /**
     * 펜 두께 설정
     */
    function setPenWidth(width) {
        QuizState.penWidth = width;
        
        // localStorage에 저장 (슬라이더 이벤트에서 중복 저장 방지를 위해 여기서는 저장하지 않음)
        // savePenSettings()는 슬라이더 이벤트 핸들러에서 호출됨
        
        // 캔버스 컨텍스트 업데이트
        if (QuizState.canvasContext && QuizState.drawingTool === 'pen') {
            QuizState.canvasContext.lineWidth = width;
        }
    }
    
    /**
     * 실행 취소 (Undo)
     */
    function undoDrawing() {
        if (!QuizState.canvasContext || QuizState.drawingHistoryIndex < 0) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        QuizState.drawingHistoryIndex--;
        
        if (QuizState.drawingHistoryIndex >= 0) {
            const imageData = QuizState.drawingHistory[QuizState.drawingHistoryIndex];
            QuizState.canvasContext.putImageData(imageData, 0, 0);
        } else {
            // 히스토리가 없으면 캔버스 초기화
            QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);
            QuizState.drawingHistoryIndex = -1;
        }
    }
    
    /**
     * 다시 실행 (Redo)
     */
    function redoDrawing() {
        if (!QuizState.canvasContext) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        if (QuizState.drawingHistoryIndex < QuizState.drawingHistory.length - 1) {
            QuizState.drawingHistoryIndex++;
            const imageData = QuizState.drawingHistory[QuizState.drawingHistoryIndex];
            QuizState.canvasContext.putImageData(imageData, 0, 0);
        }
    }
    
    /**
     * 전체 지우기
     */
    async function clearDrawing() {
        // 확인 창 없이 바로 전체 그림 삭제
        if (!QuizState.canvasContext) return;
        
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        // 현재 상태를 히스토리에 저장 (지우기 전 상태)
        saveHistoryState();
        
        // 캔버스 초기화
        QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);
        
        // Redo 가능한 히스토리 제거
        if (QuizState.drawingHistory.length > QuizState.drawingHistoryIndex + 1) {
            QuizState.drawingHistory = QuizState.drawingHistory.slice(0, QuizState.drawingHistoryIndex + 1);
        }
        
        // 빈 상태를 히스토리에 추가
        const imageData = QuizState.canvasContext.getImageData(0, 0, canvas.width, canvas.height);
        QuizState.drawingHistory.push(imageData);
        
        // 히스토리 최대 50개로 제한
        if (QuizState.drawingHistory.length > 50) {
            QuizState.drawingHistory.shift();
        }
        
        QuizState.drawingHistoryIndex = QuizState.drawingHistory.length - 1;
        
        // 서버에 빈 상태로 저장 (드로잉 삭제)
        await saveEmptyDrawingToServer();
    }
    
    /**
     * 정답 확인
     */
    async function checkAnswer() {
        if (QuizState.terminated) {
            return;
        }
        // 공통 컴포넌트에서 답안 가져오기
        let userAnswer = '';
        if (typeof PTGQuizUI !== 'undefined') {
            userAnswer = PTGQuizUI.getSelectedAnswer({
                answerName: 'ptg-answer',
                textAnswerId: 'ptg-user-answer'
            });
        } else {
            userAnswer = QuizState.userAnswer;
        }
        // DOM 기반 보강 체크 (라디오 직접 확인)
        if (!userAnswer) {
            try {
                const checked = document.querySelector('#ptg-quiz-choices input[type="radio"]:checked');
                if (checked && checked.value) {
                    userAnswer = checked.value;
                }
            } catch (e) {
                // ignore
            }
        }
        // 문자열 정리 및 최종 미선택 판별
        let normalizedUserAnswer = userAnswer;
        if (typeof normalizedUserAnswer === 'string') {
            normalizedUserAnswer = normalizedUserAnswer.trim();
        }
        if (
            !normalizedUserAnswer ||
            normalizedUserAnswer === 'null' ||
            normalizedUserAnswer === 'undefined'
        ) {
            const missing = '답안 문항을 선택하세요.';
            PTG_quiz_alert(missing);
            return;
        }
        
        const btnCheck = document.getElementById('ptg-btn-check-answer');
        if (btnCheck) {
            btnCheck.disabled = true;
        }
        
        try {
            // 사용자 답안에서 원형 숫자를 일반 숫자로 변환
            const normalizedAnswer = circleToNumber(normalizedUserAnswer);
            
            const response = await PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/attempt`, {
                answer: normalizedAnswer,
                elapsed: QuizState.timerSeconds
            });
            
            if (response && response.data) {
                // 답안 제출 전 드로잉 저장 완료
                if (QuizState.canvasContext) {
                    // 디바운스 타이머가 있으면 취소하고 즉시 저장
                    if (QuizState.drawingSaveTimeout) {
                        clearTimeout(QuizState.drawingSaveTimeout);
                        QuizState.drawingSaveTimeout = null;
                    }
                    // 답안 제출 전 상태로 마지막 저장
                    await saveDrawingToServer();
                }
                
                QuizState.isAnswered = true;
                
                // 답안 결과 저장 (완료 화면용)
                QuizState.answers.push({
                    questionId: QuizState.questionId,
                    isCorrect: response.data.is_correct,
                    userAnswer: userAnswer,
                    correctAnswer: response.data.correct_answer
                });
                
                // 공통 컴포넌트로 정답/오답 표시
                if (typeof PTGQuizUI !== 'undefined') {
                    PTGQuizUI.showAnswerFeedback('ptg-quiz-choices', response.data.correct_answer, userAnswer);
                }
                // 보조 하이라이트 및 비활성화(보장)
                try { applyAnswerHighlight(response.data.correct_answer, userAnswer); } catch(e) {}
				// 정답/오답 알림은 표시하지 않음 (미선택 제출 시에만 alert 허용)
                
                showResult(response.data.is_correct, response.data.correct_answer);
                
                // 해설 표시
                await showExplanation();
                
                // 헤더 위치로 스크롤
                setTimeout(() => {
                    scrollToHeader();
                }, 200);
                
                // 답안 제출 후 드로잉 로드 (답안 제출 후 상태로 새로 시작)
                if (QuizState.canvasContext) {
                    const canvas = document.getElementById('ptg-drawing-canvas');
                    if (canvas) {
                        // 캔버스 초기화 후 답안 제출 후 드로잉 로드
                        QuizState.canvasContext.clearRect(0, 0, canvas.width, canvas.height);
                    }
                    QuizState.drawingHistory = [];
                    QuizState.drawingHistoryIndex = -1;
                    await loadDrawingFromServer();
                }
            }
        } catch (error) {
            console.error('정답 확인 오류:', error);
            showError('정답 확인 중 오류가 발생했습니다.');
            
            // 오류 발생 시에도 버튼 상태 복원
            const btnCheck = document.getElementById('ptg-btn-check-answer');
            if (btnCheck) {
                btnCheck.disabled = false;
            }
        }
    }

    // 보조 하이라이트 및 입력 비활성화 (클래스/스타일 충돌 대비)
    function applyAnswerHighlight(correctAnswer, userAnswer) {
        const container = document.getElementById('ptg-quiz-choices');
        if (!container) return;
        const correctNum = circleToNumber(String(correctAnswer || ''));
        const userNum = circleToNumber(String(userAnswer || ''));
        const labels = container.querySelectorAll('label');
        labels.forEach(label => {
            const radio = label.querySelector('input[type="radio"]');
            if (!radio) return;
            const optionNum = circleToNumber(String(radio.value || ''));
            label.classList.remove('ptg-quiz-ui-correct-answer');
            label.classList.remove('ptg-quiz-ui-incorrect-answer');
            if (optionNum === correctNum) {
                label.classList.add('ptg-quiz-ui-correct-answer');
                try { label.style.setProperty('background', '#d4edda', 'important'); } catch(e) {}
            } else if (optionNum === userNum) {
                label.classList.add('ptg-quiz-ui-incorrect-answer');
                try { label.style.setProperty('background', '#f8d7da', 'important'); } catch(e) {}
            }
            try { radio.disabled = true; } catch(e) {}
            try { label.style.setProperty('pointer-events', 'none', 'important'); } catch(e) {}
        });
    }
    
    /**
     * 원형 숫자를 일반 숫자로 변환하는 함수
     */
    function circleToNumber(str) {
        const circleMap = {
            '①': '1', '②': '2', '③': '3', '④': '4', '⑤': '5',
            '⑥': '6', '⑦': '7', '⑧': '8', '⑨': '9', '⑩': '10'
        };
        // 옵션 텍스트에서 원형 숫자 추출
        for (const [circle, num] of Object.entries(circleMap)) {
            if (str.includes(circle)) {
                return num;
            }
        }
        // 원형 숫자가 없으면 숫자만 추출
        const numMatch = str.match(/^\d+/);
        return numMatch ? numMatch[0] : str.trim();
    }
    
    /**
     * 결과 표시
     */
    function showResult(isCorrect, correctAnswer) {
        const choicesContainer = document.getElementById('ptg-quiz-choices');
        if (!choicesContainer) return;
        
        // 정답 번호 추출 (원형 숫자 또는 일반 숫자)
        const correctNum = circleToNumber(correctAnswer);
        
        // 선택지에 결과 표시 (공통 UI 클래스 사용)
        choicesContainer.querySelectorAll('.ptg-quiz-ui-option-label').forEach(label => {
            const radio = label.querySelector('input[type="radio"]');
            if (!radio) return;
            
            // 옵션 텍스트에서 번호 추출
            const optionText = radio.value;
            const optionNum = circleToNumber(optionText);
            
            // 기존 클래스 제거 후 엔진 스타일과 동일한 클래스 적용
            label.classList.remove('ptg-quiz-ui-correct-answer');
            label.classList.remove('ptg-quiz-ui-incorrect-answer');

            // 정답 하이라이트
            if (correctNum === optionNum) {
                label.classList.add('ptg-quiz-ui-correct-answer');
            }
            
            // 선택한 답안이 틀렸으면 표시
            if (radio.checked && !isCorrect && optionNum === circleToNumber(QuizState.userAnswer || '')) {
                label.classList.add('ptg-quiz-ui-incorrect-answer');
            }
            
            // 확인 후 선택 비활성화
            try { radio.disabled = true; } catch(e) {}
        });
        
        // 정답 확인 버튼 숨기기
        const btnCheck = document.getElementById('ptg-btn-check-answer');
        if (btnCheck) {
            btnCheck.style.display = 'none';
        }
        
        // 다음 문제 버튼 표시
        const btnNext = document.getElementById('ptg-btn-next-question');
        if (btnNext) {
            btnNext.style.display = 'inline-block';
        }

		// 정/오답 텍스트 피드백 박스는 표시하지 않음 (색상 하이라이트만 유지)
		try {
			const existingFeedback = document.getElementById('ptg-quiz-feedback');
			if (existingFeedback && existingFeedback.parentNode) {
				existingFeedback.parentNode.removeChild(existingFeedback);
			}
		} catch(e) {}
    }
    
    /**
     * 해설 표시
     */
    async function showExplanation() {
        try {
            const response = await PTGPlatform.get(`ptg-quiz/v1/explanation/${QuizState.questionId}`);
            
            if (response && response.data) {
                const explanationEl = document.getElementById('ptg-quiz-explanation');
                if (explanationEl) {
                    const subject = response.data.subject || '';
                    explanationEl.innerHTML = `
                        <h3>해설${subject ? ' | (' + subject + ')' : ''}</h3>
                        <div class="ptg-explanation-content">
                            ${response.data.explanation || '해설이 없습니다.'}
                        </div>
                    `;
                    explanationEl.style.display = 'block';
                    
                    // 해설이 표시되면 카드 크기가 자동으로 조정되도록 강제 리플로우
                    // 카드 요소에 접근하여 리플로우 트리거
                    const cardEl = document.getElementById('ptg-quiz-card');
                    if (cardEl) {
                        // 리플로우 강제 (카드 크기 재계산)
                        cardEl.offsetHeight; // 읽기 작업으로 리플로우 트리거
                    }
                    
                    // 드로잉 모드가 활성화되어 있으면 캔버스 재조정 (카드 크기 변경 반영)
                    if (QuizState.drawingEnabled) {
                        setTimeout(() => {
                            initDrawingCanvas();
                        }, 150); // DOM 업데이트 대기 (리플로우 포함)
                    }
                }
            }
        } catch (error) {
            console.error('해설 로드 오류:', error);
        }
    }
    
    /**
     * 문제 목록 로드 (필터 조건으로)
     */
    async function loadQuestionsList(filters = {}) {
        try {
            // 요청 시퀀스 증가 및 캡처
            const seq = ++QuizState.requestSeq;
            const params = new URLSearchParams();
            if (filters.year) params.append('year', filters.year);
            if (filters.subject) params.append('subject', filters.subject);
            if (filters.subsubject) params.append('subsubject', filters.subsubject);
            if (filters.limit) params.append('limit', filters.limit);
            if (filters.session) params.append('session', filters.session);
            if (filters.full_session) params.append('full_session', 'true');
            if (filters.bookmarked) params.append('bookmarked', 'true');
            if (filters.needs_review) params.append('needs_review', 'true');
            
            const endpoint = `ptg-quiz/v1/questions?${params.toString()}`;
            const response = await PTGPlatform.get(endpoint);

            // 만약 응답이 오래되어 현재 시퀀스보다 작으면 무시
            if (seq < QuizState.requestSeq) {
                return []; // 무시
            }
            
            if (!response || !response.success || !Array.isArray(response.data)) {
                throw new Error(response?.message || '문제 목록을 불러올 수 없습니다.');
            }
            
            return response.data; // question_id 배열
        } catch (error) {
            console.error('[PTG Quiz] 문제 목록 로드 오류:', error);
            throw error;
        }
    }
    
    /**
     * 다음 문제 로드
     */
    function loadNextQuestion() {
        // 종료 상태에서는 더 이상 진행하지 않음
        if (QuizState.terminated) {
            return;
        }
        // 드로잉 모드가 활성화되어 있으면 다음 문제로 넘어가지 않음
        const overlay = document.getElementById('ptg-drawing-overlay');
        const isOverlayVisible = overlay && 
            (window.getComputedStyle(overlay).display !== 'none' && overlay.style.display !== 'none');
        
        if (QuizState.drawingEnabled || isOverlayVisible) {
                // 종료 상태에서는 알림 표시하지 않음
                if (!QuizState.terminated) {
                    PTG_quiz_alert('드로잉 모드를 해제하세요');
                }
            return;
        }
        
        if (QuizState.questions.length === 0) {
            showError('문제 목록이 없습니다.');
            return;
        }
        
        if (QuizState.currentIndex < QuizState.questions.length - 1) {
            // 다음 문제로 이동
            QuizState.currentIndex++;
            QuizState.questionId = QuizState.questions[QuizState.currentIndex];
            QuizState.isAnswered = false;
            QuizState.userAnswer = '';
            
            // 해설 영역 숨기기
            const explanationEl = document.getElementById('ptg-quiz-explanation');
            if (explanationEl) {
                explanationEl.style.display = 'none';
            }
            
            // 피드백 박스 제거
            const feedbackBox = document.getElementById('ptg-quiz-feedback');
            if (feedbackBox) {
                feedbackBox.remove();
            }
            
            // 버튼 상태 초기화
            const btnCheck = document.getElementById('ptg-btn-check-answer');
            const btnNext = document.getElementById('ptg-btn-next-question');
            if (btnCheck) {
                btnCheck.style.display = 'inline-block';
                btnCheck.disabled = true;
            }
            if (btnNext) {
                btnNext.style.display = 'none';
            }
            
            // 문제 로드
            loadQuestion();
        } else {
            // 마지막 문제 완료 - 완료 화면 표시
            finishQuiz();
        }
    }
    
    /**
     * 진행률 업데이트
     */
    function updateProgress(current, total) {
        const counter = document.getElementById('ptgates-question-counter');
        const fill = document.getElementById('ptgates-progress-fill');
        
        if (counter) {
            counter.textContent = `${current} / ${total}`;
        }
        
        if (fill) {
            const percentage = (current / total) * 100;
            fill.style.width = percentage + '%';
        }
    }
    
    /**
     * 진행 상태 섹션 표시/숨김
     */
    function showProgressSection() {
        if (QuizState.terminated) return;
        const progress = document.getElementById('ptgates-progress-section');
        if (progress) {
            progress.style.display = 'block';
            // 강제로 표시 (다른 스타일이 덮어쓸 수 있음)
            progress.style.setProperty('display', 'block', 'important');
        }
    }

    /**
     * 퀴즈 UI 표시 (툴바/카드/버튼)
     */
    function showQuizUI() {
        const cardWrapper = document.querySelector('.ptg-quiz-card-wrapper');
        const actions = document.querySelector('.ptg-quiz-actions');
        const toolbar = document.querySelector('.ptg-quiz-toolbar');
        if (toolbar) toolbar.style.display = 'flex';
        if (cardWrapper) cardWrapper.style.display = 'block';
        if (actions) actions.style.display = 'flex';
    }
    
    function hideProgressSection() {
        const progress = document.getElementById('ptgates-progress-section');
        if (progress) {
            progress.style.display = 'none';
        }
    }
    
    /**
     * 퀴즈 포기
     */
    async function giveUpQuiz() {
        // 중복 실행 방지 플래그 세팅
        if (QuizState.giveUpInProgress) {
            return;
        }
        QuizState.giveUpInProgress = true;
        // 상태 전환: terminated
        setState('terminated');
        
        // 즉시 UI 상호작용 차단 및 숨김
        try {
            // 타이머 즉시 정지
            if (QuizState.timerInterval) {
                clearInterval(QuizState.timerInterval);
                QuizState.timerInterval = null;
            }

            const btnCheck = document.getElementById('ptg-btn-check-answer');
            const btnNext = document.getElementById('ptg-btn-next-question');
            const toolbar = document.querySelector('.ptg-quiz-toolbar');
            const actions = document.querySelector('.ptg-quiz-actions');
            const cardWrapper = document.querySelector('.ptg-quiz-card-wrapper');
            if (btnCheck) { btnCheck.disabled = true; btnCheck.onclick = null; btnCheck.style.display = 'none'; }
            if (btnNext)  { btnNext.disabled  = true; btnNext.onclick  = null; btnNext.style.display  = 'none'; try { btnNext.remove(); } catch(e) {} }
            if (toolbar)  { toolbar.style.display = 'none'; }
            if (actions)  { actions.style.display = 'none'; }
            if (cardWrapper) { cardWrapper.style.pointerEvents = 'none'; cardWrapper.style.display = 'none'; }
			// 진행 상태 바는 유지하여 사용자가 종료 상태를 인지할 수 있게 함
        } catch (e) {
            // ignore
        }
        
        // 현재 문제까지 답안 제출한 경우, 그 문제의 답안도 저장
        const currentQuestionId = QuizState.questionId;
        if (currentQuestionId && !QuizState.isAnswered) {
            // 현재 문제에 대한 답안이 있으면 저장
            let userAnswer = '';
            if (typeof PTGQuizUI !== 'undefined') {
                userAnswer = PTGQuizUI.getSelectedAnswer({
                    answerName: 'ptg-answer',
                    textAnswerId: 'ptg-user-answer'
                });
            } else {
                userAnswer = QuizState.userAnswer;
            }
            
            // 답안이 있으면 저장
            if (userAnswer) {
                try {
                    const normalizedAnswer = circleToNumber(userAnswer);
                    const response = await PTGPlatform.post(`ptg-quiz/v1/questions/${currentQuestionId}/attempt`, {
                        answer: normalizedAnswer,
                        elapsed: QuizState.timerSeconds
                    });
                    
                    if (response && response.data) {
                        QuizState.answers.push({
                            questionId: currentQuestionId,
                            isCorrect: response.data.is_correct,
                            userAnswer: userAnswer,
                            correctAnswer: response.data.correct_answer
                        });
                    }
                } catch (error) {
                    console.error('포기 시 답안 저장 오류:', error);
                    // 오류가 발생해도 계속 진행
                }
            }
        }
        
        // 퀴즈 완료 처리 (terminated 상태 유지)
        try {
            finishQuiz();
        } finally {
            // 만약 어떤 이유로 종료 상태가 아니라면 버튼을 복구
            if (QuizState.appState !== 'terminated' && QuizState.appState !== 'finished') {
                QuizState.giveUpInProgress = false;
                const btnGiveup = document.getElementById('ptgates-giveup-btn');
                if (btnGiveup) {
                    btnGiveup.disabled = false;
                    btnGiveup.style.pointerEvents = '';
                    try { btnGiveup.removeAttribute('disabled'); } catch(e) {}
                }
            }
        }
    }
    
    /**
     * 퀴즈 완료 처리
     */
    function finishQuiz() {
        // 타이머 정지 (안전)
        if (QuizState.timerInterval) {
            clearInterval(QuizState.timerInterval);
            QuizState.timerInterval = null;
        }
        
        // 통계 계산
        const correctCount = QuizState.answers.filter(a => a.isCorrect).length;
        const incorrectCount = QuizState.answers.length - correctCount;
        const accuracy = QuizState.answers.length > 0 
            ? Math.round((correctCount / QuizState.answers.length) * 100) 
            : 0;
        
        // 소요 시간 계산
        let totalTime = 0;
        if (QuizState.startTime) {
            totalTime = Math.floor((Date.now() - QuizState.startTime) / 1000);
        } else {
            // startTime이 없으면 타이머 초기값에서 현재 타이머 값을 빼서 계산
            // 문제 수 × 50초가 초기값이므로, 초기값 - 현재값 = 경과 시간
            // 하지만 정확하지 않으므로 0으로 표시
            totalTime = 0;
        }
        
        // 완료 화면 표시
        showQuizResult({
            accuracy: accuracy,
            correct: correctCount,
            incorrect: incorrectCount,
            totalTime: totalTime
        });
        
        // 상태 전환: terminated 상태면 유지, 아니면 finished로 전환
        if (QuizState.appState !== 'terminated') {
            setState('finished');
        } else {
            applyUIForState();
        }
    }
    
    /**
     * 완료 화면 표시
     */
    function showQuizResult(stats) {
        const section = document.getElementById('ptg-quiz-result-section');
        if (!section) return;
        
        // 통계 업데이트
        const accuracyEl = document.getElementById('ptg-quiz-result-accuracy');
        const correctEl = document.getElementById('ptg-quiz-result-correct');
        const incorrectEl = document.getElementById('ptg-quiz-result-incorrect');
        const timeEl = document.getElementById('ptg-quiz-result-time');
        
        if (accuracyEl) accuracyEl.textContent = stats.accuracy + '%';
        if (correctEl) correctEl.textContent = stats.correct + '개';
        if (incorrectEl) incorrectEl.textContent = stats.incorrect + '개';
        if (timeEl) timeEl.textContent = formatTime(stats.totalTime);
        
        section.style.display = 'block';
        
        // 다시 시작 버튼 이벤트
        const restartBtn = document.getElementById('ptg-quiz-restart-btn');
        if (restartBtn) {
            restartBtn.onclick = function() {
                // 페이지 새로고침
                window.location.reload();
            };
        }
    }
    
    /**
     * 시간 포맷팅 (초 → MM:SS)
     */
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    
    /**
     * 실전 모의 학습Tip 모달 설정
     */
    function setupTipModal() {
        const tipBtn = document.getElementById('ptg-quiz-tip-btn');
        const modal = document.getElementById('ptg-quiz-tip-modal');
        const closeBtn = document.querySelector('.ptg-quiz-tip-modal-close');
        const overlay = document.querySelector('.ptg-quiz-tip-modal-overlay');
        
        if (!tipBtn || !modal) return;
        
        // 모달 열기
        tipBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // 스크롤 방지
        });
        
        // 모달 닫기 함수
        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // 스크롤 복원
        }
        
        // 닫기 버튼 클릭
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        
        // 오버레이 클릭 시 닫기
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }
        
        // ESC 키로 닫기
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });
    }
    
    /**
     * 에러 표시
     */
    function showError(message) {
        if (typeof PTGPlatform !== 'undefined' && typeof PTGPlatform.showError === 'function') {
            PTGPlatform.showError(message);
        } else {
            // 기본 에러 표시
            console.error('[PTG Quiz] 오류:', message);
            const errorEl = document.getElementById('ptg-quiz-card');
            if (errorEl) {
                errorEl.innerHTML = '<div style="color: red; padding: 20px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px;"><strong>오류:</strong> ' + message + '</div>';
            }
        }
    }
    
    /**
     * 디바운스 헬퍼
     */
    function debounce(func, wait) {
        // PTGPlatform이 없으면 기본 debounce 사용
        if (typeof window.PTGPlatform !== 'undefined' && typeof window.PTGPlatform.debounce === 'function') {
            return window.PTGPlatform.debounce(func, wait);
        }
        // 기본 debounce 구현
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    
    // 전역으로 노출 (먼저 노출하여 템플릿에서 접근 가능하도록)
    window.PTGQuiz = {
        init,
        loadQuestion,
        toggleBookmark,
        toggleReview,
        toggleNotesPanel,
        toggleDrawing,
        checkAnswer
    };
    
    // DOM 로드 완료 시 초기화
    function autoInit() {
        const container = document.getElementById('ptg-quiz-container');
        if (container) {
            try {
                init();
            } catch (e) {
                console.error('[PTG Quiz] 초기화 오류:', e);
            }
        }
    }
    
    // 즉시 시도
    setTimeout(autoInit, 0);
    
    // DOM 로드 후 시도
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(autoInit, 100);
        });
    } else {
        setTimeout(autoInit, 100);
    }
    
    // 추가 보장: 재시도 (최대 10회)
    var initAttempts = 0;
    var initInterval = setInterval(function() {
        initAttempts++;
        const container = document.getElementById('ptg-quiz-container');
        if (container && typeof window.PTGQuiz !== 'undefined' && typeof window.PTGQuiz.init === 'function') {
            clearInterval(initInterval);
            try {
                init();
            } catch (e) {
                console.error('[PTG Quiz] 초기화 오류:', e);
            }
        } else if (initAttempts >= 10) {
            console.error('[PTG Quiz] 자동 초기화 실패');
            clearInterval(initInterval);
        }
    }, 500);
    
})();

