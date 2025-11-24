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

const SESSION_STRUCTURE = {
    '1': {
        total: 105,
        subjects: {
            '물리치료 기초': {
                total: 60,
                subs: {
                    '해부생리학': 22,
                    '운동학': 12,
                    '물리적 인자치료': 16,
                    '공중보건학': 10
                }
            },
            '물리치료 진단평가': {
                total: 45,
                subs: {
                    '근골격계 물리치료 진단평가': 10,
                    '신경계 물리치료 진단평가': 16,
                    '진단평가 원리': 6,
                    '심폐혈관계 검사 및 평가': 4,
                    '기타 계통 검사': 2,
                    '임상의사결정': 7
                }
            }
        }
    },
    '2': {
        total: 85,
        subjects: {
            '물리치료 중재': {
                total: 65,
                subs: {
                    '근골격계 중재': 28,
                    '신경계 중재': 25,
                    '심폐혈관계 중재': 5,
                    '림프, 피부계 중재': 2,
                    '물리치료 문제해결': 5
                }
            },
            '의료관계법규': {
                total: 20,
                subs: {
                    '의료법': 5,
                    '의료기사법': 5,
                    '노인복지법': 4,
                    '장애인복지법': 3,
                    '국민건강보험법': 3
                }
            }
        }
    }
};

const LimitSelectionState = {
    lastKey: '',
    userOverride: false
};

// 원래 alert 함수 보존(필요 시 원래 alert으로 복구 가능)
if (typeof window !== 'undefined' && typeof window.alert === 'function' && typeof window.__PTG_ORIG_ALERT === 'undefined') {
    window.__PTG_ORIG_ALERT = window.alert;
}

(function () {
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
        document.addEventListener('keydown', function (e) {
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


(function () {
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
        (function () {
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
                    throw new Error(`[REST Non-JSON ${res.status}] ${text.slice(0, 200)}`);
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
                get: (e, q = {}) => {
                    const qp = new URLSearchParams(q).toString();
                    const ep = qp ? `${e}?${qp}` : e;
                    return api('GET', ep);
                },
                post: (e, b = {}) => api('POST', e, b),
                patch: (e, b = {}) => api('PATCH', e, b),
                showError: (m) => console.error('[PTG Platform Polyfill] 오류:', m),
                debounce: function (fn, wait) { let t = null; return function (...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); }; }
            };
        })();
    }
    // 항상 안전한 래퍼로 교체(플랫폼 스크립트가 있어도 JSON만 보장하도록)
    (function () {
        const buildUrl = (endpoint) => {
            if (/^https?:\/\//i.test(endpoint)) return endpoint;
            const origin = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
            // ptg-quiz/v1/... 같은 엔드포인트 문자열을 받도록 고정
            return origin + '/wp-json/' + String(endpoint).replace(/^\/+/, '');
        };
        async function safeApi(method, endpoint, data) {
            const url = buildUrl(endpoint);
            const headers = {
                'Accept': 'application/json',
                'X-WP-Nonce': config.nonce || ''
            };
            const init = { method, headers, credentials: 'same-origin' };
            if (data !== undefined) { headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(data); }
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
                get: (e, q = {}) => {
                    const qp = new URLSearchParams(q).toString();
                    const ep = qp ? `${e}?${qp}` : e;
                    return safeApi('GET', ep);
                },
                post: (e, b = {}) => safeApi('POST', e, b),
                patch: (e, b = {}) => safeApi('PATCH', e, b)
            });
        } catch (_) { }
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
        eventsBound: false, // 이벤트 중복 바인딩 방지
        // 앱 상태머신
        appState: 'idle', // 'idle' | 'running' | 'finished' | 'terminated'
        requestSeq: 0, // 요청 시퀀스 증가값
        lastAppliedSeq: 0, // 마지막으로 적용된 시퀀스
        // 드로잉 상태
        drawingTool: 'pen', // 'pen' 또는 'eraser'
        drawingHistory: [], // Undo/Redo를 위한 히스토리
        drawingHistoryIndex: -1, // 현재 히스토리 인덱스
        strokes: [], // 선 추적 배열 (스마트 지우개용) - 각 선의 메타데이터
        nextStrokeId: 1, // 다음 선 ID (고유 ID 생성용)
        isDrawing: false,
        lastX: 0,
        lastY: 0,
        canvasContext: null,
        penColor: 'rgb(255, 0, 0)', // 펜 색상 (기본값: 빨강)
        penWidth: 10, // 펜 두께 (기본값: 10px)
        penAlpha: 0.5, // 펜 불투명도 (기본값: 0.5 = 50%, 0~1 범위, 높을수록 진함)
        drawingSaveTimeout: null, // 드로잉 자동 저장 디바운스 타이머
        drawingPoints: [], // 현재 그리는 선의 점들 (자동 정렬용)
        autoAlignTimeout: null, // 자동 정렬 타이머
        autoAlignEnabled: true, // 자동 정렬 활성화 여부
        currentStrokeStartIndex: -1, // 현재 선의 시작 히스토리 인덱스
        currentStrokeId: null, // 현재 그리는 선의 ID
        giveUpInProgress: false, // 포기하기 중복 실행 방지
        eventsBound: false, // 이벤트 중복 바인딩 방지
        terminated: false, // 포기/종료 이후 추가 동작 차단 (호환용)
        savingDrawing: false, // 드로잉 저장 중 플래그
        // 퀴즈 결과 추적
        answers: [], // 답안 제출 결과 배열 { questionId, isCorrect, userAnswer, correctAnswer }
        startTime: null, // 퀴즈 시작 시간 (타임스탬프)
        lastBlockingMessage: ''
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

        const show = (el, display = 'block') => { if (el) el.style.display = display; };
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
                // 암기카드 버튼 강제 표시 및 순서 보장 (상태 변경 시 재확인)
                setTimeout(function() {
                    if (window.PTGQuizToolbar && window.PTGQuizToolbar.ensureFlashcardButton) {
                        window.PTGQuizToolbar.ensureFlashcardButton();
                    }
                }, 100);
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
                    if (btnNext) { btnNext.disabled = true; btnNext.style.display = 'none'; }
                    if (btnGiveup) { btnGiveup.disabled = true; btnGiveup.style.pointerEvents = 'none'; }
                } catch (e) { }
                show(resultSection, 'block');
                // 결과로 스크롤
                if (resultSection && typeof resultSection.scrollIntoView === 'function') {
                    resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                break;
        }
    }

    function showBlockingAlert(message) {
        const text = (typeof message === 'string' && message.trim().length > 0)
            ? message.trim()
            : '문제를 불러오는 중 오류가 발생했습니다.';

        QuizState.lastBlockingMessage = text;

        const runNativeAlert = () => {
            try {
                if (typeof window !== 'undefined' && typeof window.alert === 'function') {
                    window.alert(text);
                } else if (typeof PTG_quiz_alert === 'function') {
                    PTG_quiz_alert(text);
                } else {
                    console.warn('[PTG Quiz] ALERT:', text);
                }
            } catch (alertError) {
                console.warn('[PTG Quiz] window.alert 실패, 커스텀 알림 사용:', alertError);
                if (typeof PTG_quiz_alert === 'function') {
                    PTG_quiz_alert(text);
                }
            } finally {
                if (typeof PTG_quiz_alert === 'function') {
                    PTG_quiz_alert(text);
                }
            }
        };

        if (typeof window !== 'undefined' && typeof window.setTimeout === 'function') {
            window.setTimeout(runNativeAlert, 0);
        } else {
            runNativeAlert();
        }
    }

    // loadPenSettings, savePenSettings 함수는 quiz-drawing.js로 이동됨

    // 툴바 관련 함수는 quiz-toolbar.js로 이동됨

    /**
     * 초기화
     */
    function init() {
        // 중복 초기화/동시 초기화 방지
        if (QuizState.isInitialized || QuizState.initializing) {
            return;
        }
        QuizState.initializing = true;
        // 저장된 펜 설정 불러오기
        if (window.PTGQuizDrawing && window.PTGQuizDrawing.loadPenSettings) {
            window.PTGQuizDrawing.loadPenSettings();
        }

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


        // 타이머 설정: 1교시(90분) 또는 2교시(75분)가 아니면 문제당 50초로 계산
        const timerMinutes = parseInt(container.dataset.timer) || 0;
        const isSession1 = timerMinutes === 90;
        const isSession2 = timerMinutes === 75;

        // 암기카드와 노트 버튼 강제 제거 (캐시된 버전 대응) - 제거됨
        // const flashcardBtn = document.querySelector('.ptg-btn-flashcard');
        // const notebookBtn = document.querySelector('.ptg-btn-notebook');
        // if (flashcardBtn) {
        //     flashcardBtn.remove();
        // }
        // if (notebookBtn) {
        //     notebookBtn.remove();
        // }

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

                    if (questionIds === null) {
                        setState('idle');
                        return;
                    }

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

                    if (questionIds === null) {
                        setState('idle');
                        return;
                    }

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
            if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
        }, 100);
    }

    /**
     * 필터 UI 설정
     */
    function setupFilterUI() {
        // 교시 목록 로드
        const sessionSelect = document.getElementById('ptg-quiz-filter-session');
        const limitSelect = document.getElementById('ptg-quiz-filter-limit');
        if (sessionSelect) {
            loadSessions();
        }
        if (limitSelect) {
            limitSelect.addEventListener('change', () => {
                LimitSelectionState.userOverride = true;
            });
        }
        // 교시 선택 시 과목 목록 로드
        const subjectSelect = document.getElementById('ptg-quiz-filter-subject');
        const subSubjectSelect = document.getElementById('ptg-quiz-filter-subsubject');
        if (sessionSelect) {
            sessionSelect.addEventListener('change', async function () {
                const session = this.value || '';
                await loadSubjectsForSession(session);
                const subjectValue = (subjectSelect && subjectSelect.value) || '';
                const subValue = (subSubjectSelect && subSubjectSelect.value) || '';
                applyRecommendedLimit(session || null, subjectValue || null, subValue || null);
            });
        }

        // 과목 선택 시 세부과목 목록 채우기
        if (subjectSelect) {
            subjectSelect.addEventListener('change', async function () {
                const session = (document.getElementById('ptg-quiz-filter-session') || {}).value || '';
                const subject = this.value || '';
                await populateSubSubjects(session, subject);
                const subValue = (subSubjectSelect && subSubjectSelect.value) || '';
                applyRecommendedLimit(session || null, subject || null, subValue || null);
            });
        }

        if (subSubjectSelect) {
            subSubjectSelect.addEventListener('change', function () {
                const session = (document.getElementById('ptg-quiz-filter-session') || {}).value || '';
                const subject = (subjectSelect && subjectSelect.value) || '';
                const subValue = this.value || '';
                applyRecommendedLimit(session || null, subject || null, subValue || null);
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
            } catch (_) { }
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
                            } catch (_) { }
                        }
                    }
                } catch (_) { }
            }
        }

        const sessionValue = (document.getElementById('ptg-quiz-filter-session') || {}).value || session || '';
        const subjectValue = (subjectSelect && subjectSelect.value) || '';
        const subValue = (subSubjectSelect && subSubjectSelect.value) || '';
        applyRecommendedLimit(sessionValue || null, subjectValue || null, subValue || null);
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

    function getRecommendedLimitValue(session, subject, subsubject) {
        const sessKey = session ? String(session) : null;
        const normalizedSubject = subject || '';
        const normalizedSub = subsubject || '';

        if (sessKey && SESSION_STRUCTURE[sessKey]) {
            const sessionData = SESSION_STRUCTURE[sessKey];
            if (normalizedSub && normalizedSubject && sessionData.subjects[normalizedSubject] && sessionData.subjects[normalizedSubject].subs[normalizedSub]) {
                return sessionData.subjects[normalizedSubject].subs[normalizedSub];
            }
            if (normalizedSubject && sessionData.subjects[normalizedSubject]) {
                return sessionData.subjects[normalizedSubject].total;
            }
            if (!normalizedSubject && !normalizedSub) {
                return sessionData.total;
            }
        }

        if (!sessKey && normalizedSubject) {
            let total = null;
            Object.keys(SESSION_STRUCTURE).some(key => {
                const sessionData = SESSION_STRUCTURE[key];
                if (sessionData.subjects[normalizedSubject]) {
                    total = normalizedSub
                        ? (sessionData.subjects[normalizedSubject].subs[normalizedSub] || null)
                        : sessionData.subjects[normalizedSubject].total;
                    return total !== null;
                }
                return false;
            });
            if (total !== null) {
                return total;
            }
        }

        if (!sessKey && !normalizedSubject && normalizedSub) {
            let subTotal = null;
            Object.keys(SESSION_STRUCTURE).some(key => {
                const sessionData = SESSION_STRUCTURE[key];
                return Object.keys(sessionData.subjects).some(subjName => {
                    const subjectData = sessionData.subjects[subjName];
                    if (subjectData.subs[normalizedSub]) {
                        subTotal = subjectData.subs[normalizedSub];
                        return true;
                    }
                    return false;
                }) && subTotal !== null;
            });
            if (subTotal !== null) {
                return subTotal;
            }
        }

        return null;
    }

    function applyRecommendedLimit(session, subject, subsubject) {
        const limitSelect = document.getElementById('ptg-quiz-filter-limit');
        if (!limitSelect) {
            return;
        }

        const key = `${session || 'all'}|${subject || 'all'}|${subsubject || 'all'}`;
        if (LimitSelectionState.lastKey !== key) {
            LimitSelectionState.lastKey = key;
            LimitSelectionState.userOverride = false;
        }

        if (LimitSelectionState.userOverride) {
            return;
        }

        const recommended = getRecommendedLimitValue(session, subject, subsubject);
        if (!recommended) {
            return;
        }

        let option = Array.from(limitSelect.options).find(opt => parseInt(opt.value, 10) === recommended);
        if (!option) {
            option = document.createElement('option');
            option.value = String(recommended);
            option.textContent = `${recommended}문제`;
            option.dataset.autoAdded = '1';
            limitSelect.appendChild(option);
        }

        const sortedOptions = Array.from(limitSelect.options)
            .sort((a, b) => parseInt(a.value, 10) - parseInt(b.value, 10));

        const fragment = document.createDocumentFragment();
        sortedOptions.forEach(opt => fragment.appendChild(opt));
        limitSelect.appendChild(fragment);

        limitSelect.value = String(recommended);
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
        const limitVal = limitSelect.value;

        let limit = 5;
        let fullSession = false;

        if (limitVal === 'full') {
            if (!session) {
                PTG_quiz_alert('실전 모의고사는 교시를 선택해야 합니다.');
                return;
            }
            fullSession = true;
            limit = 0; // API에서 full_session=true일 때 limit 무시됨
        } else {
            limit = parseInt(limitVal) || 5;
        }

        const startBtn = document.getElementById('ptg-quiz-start-btn');

        try {
            const filters = {};
            if (session) filters.session = session;
            if (subject) filters.subject = subject;
            // 세부과목이 선택된 경우에만 전달 (빈 값은 전체와 동일)
            if (subsubject) filters.subsubject = subsubject;
            filters.limit = limit;
            if (fullSession) filters.full_session = true;

            if (startBtn) {
                startBtn.disabled = true;
                startBtn.classList.add('ptg-btn-loading');
            }

            const questionIds = await loadQuestionsList(filters);

            if (questionIds === null) {
                setState('idle');
                return;
            }

            if (!questionIds || questionIds.length === 0) {
                PTG_quiz_alert('선택한 조건에 맞는 문제를 찾을 수 없습니다.');
                setState('idle');
                return;
            }

            // 실행 상태로 전환
            setState('running');

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
            if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
        } catch (error) {
            console.error('[PTG Quiz] 퀴즈 시작 오류:', error);
            if (!error || !error.alertShown) {
                const fallback = (error && typeof error.message === 'string' && error.message.trim().length > 0)
                    ? error.message
                    : '문제를 불러오는 중 오류가 발생했습니다.';
                PTG_quiz_alert(fallback);
            }
            setState('idle');
        } finally {
            if (startBtn) {
                startBtn.disabled = false;
                startBtn.classList.remove('ptg-btn-loading');
            }
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

        // 툴바 이벤트 설정 (quiz-toolbar.js에서 처리)
        if (window.PTGQuizToolbar && window.PTGQuizToolbar.setupToolbarEvents) {
            window.PTGQuizToolbar.setupToolbarEvents();
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

            btnGiveupCloned.addEventListener('click', function (e) {
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
            timeTipBtn.addEventListener('click', function (e) {
                e.preventDefault();
                timeTipModal.style.display = 'block';
            });
        }

        if (timeTipClose && timeTipModal) {
            timeTipClose.addEventListener('click', function () {
                timeTipModal.style.display = 'none';
            });

            // 오버레이 클릭 시 닫기
            const overlay = timeTipModal.querySelector('.ptgates-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', function () {
                    timeTipModal.style.display = 'none';
                });
            }
        }

        // 드로잉 툴바 버튼 이벤트 (닫기 버튼 포함)
        if (window.PTGQuizDrawing && window.PTGQuizDrawing.setupDrawingToolbarEvents) {
            window.PTGQuizDrawing.setupDrawingToolbarEvents();
        }

        // 페이지 이탈 시 드로잉 저장 보장
        window.addEventListener('beforeunload', function (e) {
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
        document.addEventListener('visibilitychange', function () {
            if (document.hidden && QuizState.canvasContext && QuizState.drawingEnabled) {
                // 디바운스 타이머가 있으면 취소하고 즉시 저장
                if (QuizState.drawingSaveTimeout) {
                    clearTimeout(QuizState.drawingSaveTimeout);
                    QuizState.drawingSaveTimeout = null;
                }
                // 비동기 저장 (완료 보장은 어려움)
                if (window.PTGQuizDrawing && window.PTGQuizDrawing.saveDrawingToServer) {
                    window.PTGQuizDrawing.saveDrawingToServer();
                }
            }
        });
    }

    // initializePenMenu 함수는 quiz-drawing.js로 이동됨

    // setupDrawingToolbarEvents 함수는 quiz-drawing.js로 이동됨

    /**
     * 키보드 단축키 설정
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Esc: 패널 닫기
            if (e.key === 'Escape') {
                if (QuizState.drawingEnabled) {
                    if (window.PTGQuizDrawing && window.PTGQuizDrawing.toggleDrawing) {
                        window.PTGQuizDrawing.toggleDrawing(false);
                    }
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

                if (questionIds === null) {
                    setState('idle');
                    showFilterSection();
                    return;
                }

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
                    if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
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
                if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
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

                // 암기카드 상태 업데이트
                const btnFlashcard = document.querySelector('.ptg-btn-flashcard');
                if (btnFlashcard) {
                    if (QuizState.userState.flashcard) {
                        btnFlashcard.classList.add('active');
                    } else {
                        btnFlashcard.classList.remove('active');
                    }
                }

                // 메모 상태 업데이트 (메모 내용이 있으면 활성화)
                const btnNotes = document.querySelector('.ptg-btn-notes');
                if (btnNotes) {
                    const hasNote = QuizState.userState.note && QuizState.userState.note.trim().length > 0;
                    if (hasNote) {
                        btnNotes.classList.add('active');
                        // 메모 텍스트 영역에도 내용 설정
                        const notesTextarea = document.getElementById('ptg-notes-textarea');
                        if (notesTextarea && !notesTextarea.value.trim()) {
                            notesTextarea.value = QuizState.userState.note;
                        }
                    } else {
                        btnNotes.classList.remove('active');
                    }
                }

            }
        } catch (error) {
            console.error('사용자 상태 로드 오류:', error);
        }
    }

    /**
     * 텍스트 정리 함수 (_x000D_ 제거 및 줄바꿈 처리)
     * @param {string} text 원본 텍스트
     * @returns {string} 정리된 텍스트
     */
    function cleanText(text) {
        if (!text) return '';
        // _x000D_ 제거 (Windows 줄바꿈 문자 \r\n의 유니코드 표현)
        let cleaned = String(text).replace(/_x000D_/g, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        // 연속된 줄바꿈을 하나로 (2개 이상의 \n을 하나로)
        cleaned = cleaned.replace(/\n{2,}/g, '\n');
        return cleaned;
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
        const questionText = cleanText(question.question_text || question.content || '');
        const options = question.options || [];

        // 문제 번호 계산 (연속 퀴즈인 경우)
        const questionNumber = QuizState.questions.length > 0
            ? QuizState.currentIndex + 1
            : null;
        const totalQuestions = QuizState.questions.length > 0
            ? QuizState.questions.length
            : null;

        const questionNumberPrefix = questionNumber
            ? `<strong class="ptg-question-number">${questionNumber}.</strong> `
            : '';
        const questionNumberSuffix = '';

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
                if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
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

        // 툴바 상태 업데이트 (1100 플러그인과 통합)
        if (typeof window.PTGStudyToolbar !== 'undefined' && typeof window.PTGStudyToolbar.updateToolbarStatus === 'function') {
            window.PTGStudyToolbar.updateToolbarStatus(QuizState.questionId);
        }

        // 버튼에 data-question-id 속성 추가 (study-toolbar.js 호환성)
        if (QuizState.questionId) {
            const toolbarButtons = document.querySelectorAll('.ptg-quiz-toolbar .ptg-btn-icon');
            toolbarButtons.forEach(btn => {
                btn.setAttribute('data-question-id', QuizState.questionId);
            });
        }

        // 암기카드 버튼 강제 표시 및 순서 보장 (문제 렌더링 시 재확인)
        setTimeout(function() {
            if (typeof ensureFlashcardButton === 'function') {
                ensureFlashcardButton();
            }
        }, 200);
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

        // _x000D_ 제거 및 정규화
        const cleanedContent = cleanText(content);

        const options = [];
        let questionText = cleanedContent;

        // HTML 태그 제거 (있는 경우)
        const textContent = cleanedContent.replace(/<[^>]*>/g, '');

        // 먼저 모든 원형 숫자(①~⑳)의 위치 찾기 (괄호 숫자는 선택지 내용이므로 제외)
        const numberPattern = /[①-⑳]\s*/gu;
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
                let optionText = textContent.substring(startPos, endPos).trim();
                // 연속된 줄바꿈을 공백으로 정리 (선택지는 한 줄로 표시)
                optionText = optionText.replace(/\n{2,}/g, ' ').replace(/\n/g, ' ');

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
                let optionText = String(option || '').trim();
                // 모든 줄바꿈을 공백으로 정리 (선택지는 한 줄로 표시)
                optionText = optionText.replace(/\n{2,}/g, ' ').replace(/\n/g, ' ');

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
                label.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (radio.disabled) { return; }
                    if (e.target !== radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                // 라디오 버튼 변경 이벤트
                radio.addEventListener('change', function (e) {
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
                if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
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

    // 툴바 관련 함수는 quiz-toolbar.js로 이동됨

    /**
     * 암기카드 모달 표시 (레거시 호환용 - quiz-toolbar.js로 이동됨)
     */
    async function showFlashcardModalLegacy() {
        const questionId = QuizState.questionId;
        if (!questionId) {
            return;
        }

        // Helper function to convert HTML to text while preserving line breaks
        function htmlToText($element) {
            const clone = $element.cloneNode(true);
            // Replace <br> with newline
            clone.querySelectorAll('br').forEach(br => {
                br.replaceWith('\n');
            });
            // Get text content
            return (clone.textContent || clone.innerText || '').trim();
        }

        let frontText = '';
        let backText = '';

        // 먼저 DB에서 저장된 암기카드 데이터 조회
        let hasDbData = false;
        try {
            const params = {
                source_type: 'question',
                source_id: questionId
            };
            
            const cardsResponse = await PTGPlatform.get('ptg-flash/v1/cards', params);
            
            // WordPress REST API는 배열을 직접 반환하거나 data 속성에 포함
            const cards = Array.isArray(cardsResponse) ? cardsResponse : (cardsResponse.data || []);
            
            // 첫 번째 카드 사용 (source_type, source_id로 필터링됨)
            const existingCard = Array.isArray(cards) && cards.length > 0 ? cards[0] : null;

            if (existingCard) {
                // front_custom, back_custom이 존재하고 빈 문자열이 아닌지 확인
                const frontValue = existingCard.front_custom;
                const backValue = existingCard.back_custom;
                
                const hasFront = frontValue !== null && frontValue !== undefined && String(frontValue).trim() !== '';
                const hasBack = backValue !== null && backValue !== undefined && String(backValue).trim() !== '';
                
                // 둘 중 하나라도 값이 있으면 DB 데이터 사용
                if (hasFront || hasBack) {
                    frontText = frontValue ? String(frontValue) : '';
                    backText = backValue ? String(backValue) : '';
                    hasDbData = true;
                }
            }
        } catch (error) {
            // DB 조회 실패 시 DOM에서 추출로 진행
            console.error('[PTG Quiz] 암기카드 DB 조회 실패:', error);
        }

        // DB 데이터가 없으면 QuizState.questionData에서 추출
        if (!hasDbData) {
            // 앞면: 지문과 선택지를 QuizState.questionData에서 가져오기
            if (QuizState.questionData) {
                // 지문 추가 (질문 시작 부분에 ID 추가)
                const questionIdPrefix = '(id-' + QuizState.questionId + ') ';
                if (QuizState.questionData.question_text) {
                    frontText = questionIdPrefix + QuizState.questionData.question_text.trim();
                } else if (QuizState.questionData.content) {
                    frontText = questionIdPrefix + QuizState.questionData.content.trim();
                }
                
                // 선택지 추가
                if (QuizState.questionData.options && Array.isArray(QuizState.questionData.options) && QuizState.questionData.options.length > 0) {
                    QuizState.questionData.options.forEach((option, index) => {
                        let optionText = String(option || '').trim();
                        if (optionText) {
                            // 이미 원형 숫자가 있으면 제거 (①~⑳ 패턴 제거)
                            optionText = optionText.replace(/^[①-⑳]\s*/, '');
                            
                            // 선택지 형식: ① 선택지 내용
                            const optionNumber = String.fromCharCode(0x2460 + index); // 원형 숫자 ①, ②, ③...
                            frontText += '\n' + optionNumber + ' ' + optionText;
                        }
                    });
                }
                
                // 뒷면: 정답과 해설
                // 정답 추가
                if (QuizState.questionData.answer) {
                    backText = '정답: ' + QuizState.questionData.answer;
                }
                
                // 해설 추가
                if (QuizState.questionData.explanation) {
                    if (backText) {
                        backText += '\n\n';
                    }
                    backText += htmlToTextForFlashcard(QuizState.questionData.explanation);
                }
            } else {
                // QuizState.questionData가 없으면 DOM에서 추출 (fallback)
                const card = document.getElementById('ptg-quiz-card');
                
                if (card) {
                    // Get question text (질문 시작 부분에 ID 추가)
                    const questionEl = card.querySelector('.ptg-question-text, .ptg-question-content');
                    if (questionEl) {
                        const questionIdPrefix = '(id-' + QuizState.questionId + ') ';
                        frontText = questionIdPrefix + htmlToText(questionEl);
                    }
                    
                    // Get question options/choices (실제 렌더링된 클래스 사용)
                    const choicesEl = card.querySelector('.ptg-quiz-choices');
                    if (choicesEl) {
                        const choices = choicesEl.querySelectorAll('.ptg-quiz-ui-option-label, .ptg-quiz-choice, .ptg-choice-item');
                        choices.forEach(choice => {
                            // 선택지 텍스트 추출
                            const optionText = choice.querySelector('.ptg-quiz-ui-option-text');
                            if (optionText) {
                                const choiceText = htmlToText(optionText);
                                if (choiceText) {
                                    frontText += '\n' + choiceText.trim();
                                }
                            } else {
                                // fallback: 직접 텍스트 추출
                                const choiceText = htmlToText(choice);
                                if (choiceText) {
                                    frontText += '\n' + choiceText.trim();
                                }
                            }
                        });
                    }
                }
                
                // 뒷면: DOM에서 추출 (fallback)
                const explanation = document.getElementById('ptg-quiz-explanation');
                
                if (explanation && explanation.style.display !== 'none') {
                    // Extract answer and explanation
                    const explanationContent = explanation.querySelector('.ptg-explanation-content');
                    let extractedText = '';
                    if (explanationContent) {
                        extractedText = htmlToText(explanationContent);
                    } else {
                        extractedText = htmlToText(explanation);
                    }
                    // 뒷면에서 ID 패턴 제거 (id-xxxx 형식)
                    backText = extractedText.replace(/\s*\(id-\d+\)\s*/g, '').trim();
                }
            }
        }

        // Create modal if it doesn't exist
        let modal = document.getElementById('ptg-quiz-flashcard-modal');
        if (!modal) {
            const modalHtml = 
                '<div id="ptg-quiz-flashcard-modal" class="ptg-modal" style="display: none;">' +
                    '<div class="ptg-modal-overlay"></div>' +
                    '<div class="ptg-modal-content">' +
                        '<div class="ptg-modal-header">' +
                            '<h3>암기카드 만들기</h3>' +
                            '<button class="ptg-modal-close">&times;</button>' +
                        '</div>' +
                        '<div class="ptg-modal-body">' +
                            '<div class="form-group">' +
                                '<label>앞면 (질문)</label>' +
                                '<textarea id="ptg-flashcard-front" rows="4"></textarea>' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label>뒷면 (답변/해설)</label>' +
                                '<textarea id="ptg-flashcard-back" rows="4"></textarea>' +
                            '</div>' +
                        '</div>' +
                        '<div class="ptg-modal-footer">' +
                            '<div class="ptg-flashcard-status" style="flex: 1; font-size: 14px; color: #666;"></div>' +
                            '<button class="ptg-btn ptg-btn-secondary ptg-modal-cancel">취소</button>' +
                            '<button class="ptg-btn ptg-btn-primary ptg-flashcard-save">저장</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = modalHtml;
            modal = tempDiv.firstElementChild;
            document.body.appendChild(modal);

            // Close handler
            modal.addEventListener('click', function(e) {
                if (e.target.classList.contains('ptg-modal-close') || 
                    e.target.classList.contains('ptg-modal-cancel') ||
                    e.target.classList.contains('ptg-modal-overlay')) {
                    modal.style.display = 'none';
                    const statusEl = modal.querySelector('.ptg-flashcard-status');
                    if (statusEl) statusEl.textContent = '';
                }
            });

            // Save handler (bound once)
            modal.addEventListener('click', function(e) {
                if (e.target.classList.contains('ptg-flashcard-save')) {
                    e.preventDefault();
                    if (window.PTGQuizToolbar && window.PTGQuizToolbar.saveFlashcard) {
                        window.PTGQuizToolbar.saveFlashcard();
                    }
                }
            });
        }

        // Fill modal fields
        const frontTextarea = document.getElementById('ptg-flashcard-front');
        const backTextarea = document.getElementById('ptg-flashcard-back');
        const statusEl = modal.querySelector('.ptg-flashcard-status');
        
        if (frontTextarea) {
            frontTextarea.value = frontText ? frontText.trim() : '';
        }
        
        if (backTextarea) {
            backTextarea.value = backText ? backText.trim() : '';
        }
        
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.style.color = '#666';
        }
        
        // Set question ID
        modal.setAttribute('data-question-id', questionId);
        
        // Show modal
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        
        // 모달 표시 후 포커스
        setTimeout(() => {
            if (frontTextarea) frontTextarea.focus();
        }, 100);
    }


    // toggleDrawing 함수는 quiz-drawing.js로 이동됨

    // initDrawingCanvas, setupDrawingEvents, handleMouseDown, handleMouseMove, handleMouseUp, handleTouchStart, handleTouchMove, handleTouchEnd, saveHistoryState, loadDrawingHistory, loadDrawingFromServer, saveEmptyDrawingToServer, saveDrawingToServer, debouncedSaveDrawing, setDrawingTool, setPenColor, setPenAlpha, setPenWidth, undoDrawing, redoDrawing, clearDrawing 함수들은 quiz-drawing.js로 이동됨

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
            // sessionStorage에서 이미 로그된 question_id 목록 가져오기
            const QUIZ_STORAGE_KEY = 'ptg_quiz_logged_questions';
            let loggedQuestions = [];
            try {
                const stored = sessionStorage.getItem(QUIZ_STORAGE_KEY);
                if (stored) {
                    loggedQuestions = JSON.parse(stored);
                }
            } catch (e) {
                console.warn('PTG Quiz: Failed to read sessionStorage', e);
            }

            // 이미 이 세션에서 로그된 question_id인지 확인
            const alreadyLogged = loggedQuestions.includes(QuizState.questionId);
            
            // 사용자 답안에서 원형 숫자를 일반 숫자로 변환
            const normalizedAnswer = circleToNumber(normalizedUserAnswer);

            // attempt API 호출 (이미 로그된 경우 skip_count_update 파라미터 추가)
            const response = await PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/attempt`, {
                answer: normalizedAnswer,
                elapsed: QuizState.timerSeconds,
                skip_count_update: alreadyLogged ? true : false
            });

            // 성공 시 sessionStorage에 추가 (아직 로그되지 않은 경우만)
            if (response && response.data && !alreadyLogged) {
                loggedQuestions.push(QuizState.questionId);
                try {
                    sessionStorage.setItem(QUIZ_STORAGE_KEY, JSON.stringify(loggedQuestions));
                } catch (e) {
                    console.warn('PTG Quiz: Failed to write sessionStorage', e);
                }
            }

            if (response && response.data) {
                // 답안 제출 전 드로잉 저장 완료
                if (QuizState.canvasContext) {
                    // 디바운스 타이머가 있으면 취소하고 즉시 저장
                    if (QuizState.drawingSaveTimeout) {
                        clearTimeout(QuizState.drawingSaveTimeout);
                        QuizState.drawingSaveTimeout = null;
                    }
                    // 답안 제출 전 상태로 마지막 저장
                    if (window.PTGQuizDrawing && window.PTGQuizDrawing.saveDrawingToServer) {
                        await window.PTGQuizDrawing.saveDrawingToServer();
                    }
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
                try { applyAnswerHighlight(response.data.correct_answer, userAnswer); } catch (e) { }
                // 정답/오답 알림은 표시하지 않음 (미선택 제출 시에만 alert 허용)

                showResult(response.data.is_correct, response.data.correct_answer);

                // 해설 표시
                await showExplanation();

                // 헤더 위치로 스크롤
                setTimeout(() => {
                    if (window.PTGQuizToolbar && window.PTGQuizToolbar.scrollToHeader) {
                window.PTGQuizToolbar.scrollToHeader();
            }
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
                    QuizState.strokes = [];
                    QuizState.nextStrokeId = 1;
                    QuizState.currentStrokeId = null;
                    if (window.PTGQuizDrawing && window.PTGQuizDrawing.loadDrawingFromServer) {
                        await window.PTGQuizDrawing.loadDrawingFromServer();
                    }
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
                try { label.style.setProperty('background', '#d4edda', 'important'); } catch (e) { }
            } else if (optionNum === userNum) {
                label.classList.add('ptg-quiz-ui-incorrect-answer');
                try { label.style.setProperty('background', '#f8d7da', 'important'); } catch (e) { }
            }
            try { radio.disabled = true; } catch (e) { }
            try { label.style.setProperty('pointer-events', 'none', 'important'); } catch (e) { }
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
            try { radio.disabled = true; } catch (e) { }
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
        } catch (e) { }
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
                    let explanationHtml = cleanText(response.data.explanation || '해설이 없습니다.');

                    if (explanationHtml.includes('(오답 해설)')) {
                        explanationHtml = explanationHtml.split('(오답 해설)').join('<br><br>(오답 해설)');
                    }

                    explanationEl.innerHTML = `
                        <h3>해설${subject ? ' | (' + subject + ')' : ''} &nbsp;&nbsp;(id-${QuizState.questionId})</h3>
                        <div class="ptg-explanation-content">
                            ${explanationHtml}
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
                            if (window.PTGQuizDrawing && window.PTGQuizDrawing.initDrawingCanvas) {
                window.PTGQuizDrawing.initDrawingCanvas();
            }
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
            const code = error?.code || error?.data?.code || error?.data?.error || null;
            const message = (typeof error?.message === 'string' && error.message.trim().length > 0)
                ? error.message.trim()
                : '문제 목록을 불러오는 중 오류가 발생했습니다.';

            const gateCodes = ['limit_reached', 'daily_limit', 'guest_limit', 'login_required', 'forbidden', 'unauthorized'];
            const lowerMessage = message.toLowerCase();
            const isGateError =
                (code && gateCodes.includes(String(code).toLowerCase())) ||
                lowerMessage.includes('일일 무료') ||
                lowerMessage.includes('프리미엄') ||
                lowerMessage.includes('로그인') ||
                lowerMessage.includes('한도') ||
                lowerMessage.includes('멤버십');

            if (isGateError) {
                showBlockingAlert(message);
                console.warn('[PTG Quiz] 제한으로 문제 로드 차단:', { code, message });
                return null;
            }

            console.error('[PTG Quiz] 문제 목록 로딩 실패:', { code, message, error });
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
            if (btnNext) { btnNext.disabled = true; btnNext.onclick = null; btnNext.style.display = 'none'; try { btnNext.remove(); } catch (e) { } }
            if (toolbar) { toolbar.style.display = 'none'; }
            if (actions) { actions.style.display = 'none'; }
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
                    try { btnGiveup.removeAttribute('disabled'); } catch (e) { }
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
            restartBtn.onclick = function () {
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
        tipBtn.addEventListener('click', function (e) {
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
        document.addEventListener('keydown', function (e) {
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
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // 전역으로 노출 (먼저 노출하여 템플릿에서 접근 가능하도록)
    window.PTGQuiz = {
        init,
        loadQuestion,
        QuizState, // quiz-toolbar.js에서 접근 가능하도록 노출
        toggleBookmark: function() {
            if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleBookmark) {
                return window.PTGQuizToolbar.toggleBookmark();
            }
        },
        toggleReview: function() {
            if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleReview) {
                return window.PTGQuizToolbar.toggleReview();
            }
        },
        toggleNotesPanel: function(force) {
            if (window.PTGQuizToolbar && window.PTGQuizToolbar.toggleNotesPanel) {
                return window.PTGQuizToolbar.toggleNotesPanel(force);
            }
        },
        toggleDrawing: function(force) {
            if (window.PTGQuizDrawing && window.PTGQuizDrawing.toggleDrawing) {
                return window.PTGQuizDrawing.toggleDrawing(force);
            }
        },
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
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(autoInit, 100);
        });
    } else {
        setTimeout(autoInit, 100);
    }

    // 추가 보장: 재시도 (최대 10회)
    var initAttempts = 0;
    var initInterval = setInterval(function () {
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

    // init() 함수나 초기화 시점에서 모달 존재 확인

})();

