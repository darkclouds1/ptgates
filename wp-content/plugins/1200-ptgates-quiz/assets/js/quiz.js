/**
 * PTGates Quiz - 메인 JavaScript
 * 
 * 문제 풀이 UI, 타이머, 드로잉 기능
 */

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
// 퀴즈용 alert 헬퍼 (가용하면 원래 alert 사용)
function PTG_quiz_alert(message) {
    if (typeof window === 'undefined') {
        return;
    }
    if (typeof window.__PTG_ORIG_ALERT === 'function') {
        window.__PTG_ORIG_ALERT(message);
        return;
    }
    if (typeof window.alert === 'function') {
        window.alert(message);
        return;
    }
    try {
        console.warn('[PTG Quiz] ' + message);
    } catch (e) {}
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
            if (!ct.includes('application/json')) { throw new Error(`[REST Non-JSON ${res.status}] ${text.slice(0,200)}`); }
            const json = JSON.parse(text);
            if (!res.ok) { throw new Error((json && (json.message||json.code)) || `HTTP ${res.status}`); }
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
    
    // 상태 관리
    const QuizState = {
        questionId: 0,
        questionData: null,
        userState: null,
        userAnswer: '',
        isAnswered: false,
        timer: null,
        timerSeconds: 0,
        timerInterval: null,
        drawingEnabled: false,
        isInitialized: false // 중복 초기화 방지 플래그
    };
    
    /**
     * 초기화
     */
    function init() {
        // 중복 초기화 방지
        if (QuizState.isInitialized) {
            return;
        }
        
        const container = document.getElementById('ptg-quiz-container');
        if (!container) {
            console.error('[PTG Quiz] 컨테이너를 찾을 수 없음: ptg-quiz-container');
            return;
        }
        
        QuizState.questionId = parseInt(container.dataset.questionId) || 0;
        QuizState.timerSeconds = parseInt(container.dataset.timer) * 60 || 0;
        
        // 문제 ID가 없으면 경고
        if (!QuizState.questionId) {
            console.error('[PTG Quiz] 문제 ID가 없음');
            showError('문제 ID가 지정되지 않았습니다.');
            return;
        }
        
        // 암기카드와 노트 버튼 강제 제거 (캐시된 버전 대응)
        const flashcardBtn = document.querySelector('.ptg-btn-flashcard');
        const notebookBtn = document.querySelector('.ptg-btn-notebook');
        if (flashcardBtn) {
            flashcardBtn.remove();
        }
        if (notebookBtn) {
            notebookBtn.remove();
        }
        
        // 메모 패널 항상 표시 및 스타일 강제 적용 함수
        function forceNotesPanelStyle() {
            const notesPanel = document.getElementById('ptg-notes-panel');
            if (!notesPanel) return;
            
            // 인라인 스타일로 강제 적용 (CSS보다 우선순위 높음)
            notesPanel.style.position = 'static';
            notesPanel.style.top = 'auto';
            notesPanel.style.left = 'auto';
            notesPanel.style.right = 'auto';
            notesPanel.style.bottom = 'auto';
            notesPanel.style.zIndex = 'auto';
            notesPanel.style.width = '100%';
            notesPanel.style.maxWidth = '100%';
            notesPanel.style.margin = '20px 0 0 0';
            notesPanel.style.transform = 'none';
            notesPanel.style.float = 'none';
            notesPanel.style.clear = 'both';
            notesPanel.style.display = 'flex';
        }
        
        // 즉시 적용
        forceNotesPanelStyle();
        
        // 전역 함수로 등록
        window.ptgForceNotesPanelStyle = forceNotesPanelStyle;
        
        // MutationObserver로 스타일 변경 감지 및 재적용
        const notesPanel = document.getElementById('ptg-notes-panel');
        if (notesPanel && !notesPanel._ptgNotesStyleObserver) {
            const observer = new MutationObserver(function(mutations) {
                let shouldForce = false;
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes') {
                        if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                            shouldForce = true;
                        }
                    }
                });
                if (shouldForce) {
                    // 약간의 지연 후 강제 적용 (다른 스크립트가 스타일을 변경한 후)
                    setTimeout(function() {
                        forceNotesPanelStyle();
                    }, 10);
                }
            });
            
            observer.observe(notesPanel, {
                attributes: true,
                attributeFilter: ['style', 'class']
            });
            
            notesPanel._ptgNotesStyleObserver = observer;
            
            // 주기적으로 스타일 확인 및 재적용 (다른 CSS가 덮어쓸 경우 대비)
            const styleCheckInterval = setInterval(function() {
                if (!notesPanel || !notesPanel.parentNode) {
                    clearInterval(styleCheckInterval);
                    return;
                }
                const computedStyle = window.getComputedStyle(notesPanel);
                if (computedStyle.position !== 'static' && computedStyle.position !== '') {
                    forceNotesPanelStyle();
                }
            }, 200); // 200ms마다 확인
            
            // 전역에 interval ID 저장 (나중에 정리 가능하도록)
            notesPanel._ptgNotesStyleInterval = styleCheckInterval;
        }
        
        // 이벤트 리스너 등록
        setupEventListeners();
        
        // 문제 로드
        loadQuestion();
        
        // 타이머 시작
        if (container.dataset.unlimited !== '1') {
            startTimer();
        }
        
        // 키보드 단축키
        setupKeyboardShortcuts();
        
        // 초기화 완료 플래그 설정
        QuizState.isInitialized = true;
    }
    
    /**
     * 이벤트 리스너 설정
     */
    function setupEventListeners() {
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
        
        // 메모 버튼
        const btnNotes = document.querySelector('.ptg-btn-notes');
        if (btnNotes) {
            btnNotes.addEventListener('click', toggleNotesPanel);
        }
        
        // 메모 패널 닫기 버튼
        const btnCloseNotes = document.querySelector('.ptg-btn-close-notes');
        if (btnCloseNotes) {
            btnCloseNotes.addEventListener('click', () => toggleNotesPanel(false));
        }
        
        // 드로잉 버튼
        const btnDrawing = document.querySelector('.ptg-btn-drawing');
        if (btnDrawing) {
            btnDrawing.addEventListener('click', toggleDrawing);
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
        
        // 드로잉 패널 닫기
        const btnCloseDrawing = document.querySelector('.ptg-btn-close-drawing');
        if (btnCloseDrawing) {
            btnCloseDrawing.addEventListener('click', () => toggleDrawing(false));
        }
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
                
                QuizState.questionData = data.data;
                console.log('[PTG Quiz] 문제 데이터 저장 완료:', QuizState.questionData);
                renderQuestion(data.data);
                await loadUserState();
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
            
            QuizState.questionData = response.data;
            renderQuestion(response.data);
            
            // 사용자 상태 로드
            await loadUserState();
            
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
                if (btnBookmark && QuizState.userState.bookmarked) {
                    btnBookmark.classList.add('active');
                    btnBookmark.querySelector('.ptg-icon').textContent = '★';
                }
                
                // 복습 필요 상태 업데이트
                const btnReview = document.querySelector('.ptg-btn-review');
                if (btnReview && QuizState.userState.needs_review) {
                    btnReview.classList.add('active');
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
        // API에서 이미 파싱된 데이터 사용 (question_text, options)
        const questionText = question.question_text || question.content || '';
        const options = question.options || [];
        
        // 옵션이 없으면 경고
        if (!options || options.length === 0) {
            console.error('[PTG Quiz] 선택지가 없음');
        }
        
        // 문제 텍스트와 선택지를 하나의 카드 안에 표시 (기출 문제 학습 형식)
        const questionCardEl = document.getElementById('ptg-quiz-card');
        if (questionCardEl) {
            // 질문 텍스트와 선택지 컨테이너를 함께 구성
            questionCardEl.innerHTML = `
                <div class="ptg-question-content">
                    ${questionText}
                </div>
                <div class="ptg-quiz-choices" id="ptg-quiz-choices">
                    <!-- 선택지가 동적으로 로드됨 -->
                </div>
            `;
            
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
                // 공통 컴포넌트가 없는 경우 기존 방식 사용
                console.warn('[PTG Quiz] 공통 퀴즈 UI 컴포넌트를 찾을 수 없음. 기존 방식 사용.');
                renderChoices(options);
            }
        } else {
            console.error('[PTG Quiz] 문제 카드를 찾을 수 없음: ptg-quiz-card');
        }
        
        // 정답 확인 버튼 활성화
        const btnCheck = document.getElementById('ptg-btn-check-answer');
        if (btnCheck) {
            btnCheck.disabled = false;
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
        
        console.log('[PTG Quiz] 원형 숫자 매칭 결과:', {
            총개수: numberMatches.length,
            매칭: numberMatches.map(m => ({ 숫자: m.number, 위치: m.position }))
        });
        
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
                
                console.log(`[PTG Quiz] 선택지 ${idx + 1} 추출:`, {
                    시작위치: startPos,
                    끝위치: endPos,
                    텍스트: optionText,
                    길이: optionText.length
                });
                
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
                
                console.log('[PTG Quiz] 지문 재구성:', {
                    원본: textContent,
                    재구성: questionText,
                    선택지수: options.length
                });
            }
        } else {
            console.warn('[PTG Quiz] 원형 숫자를 찾을 수 없음:', content);
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
        
        console.log('[PTG Quiz] 선택지 렌더링 시작:', {
            선택지수: options ? options.length : 0,
            선택지: options
        });
        
        // 컨테이너 초기화 및 스타일 설정
        choicesContainer.innerHTML = '';
        choicesContainer.style.display = 'block';
        choicesContainer.style.width = '100%';
        choicesContainer.style.marginTop = '0';
        choicesContainer.style.padding = '10px 12px';
        
        if (!options || options.length === 0) {
            console.warn('[PTG Quiz] 선택지가 없음 - 주관식 모드');
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
                if (!optionText) {
                    console.warn('[PTG Quiz] 빈 선택지 발견:', index, option);
                }
                
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
                
                // 디버깅
                if (typeof console !== 'undefined' && console.log) {
                    console.log(`[PTG Quiz] 선택지 ${index + 1} 렌더링:`, {
                        원본: option,
                        텍스트: optionText,
                        길이: optionText.length
                    });
                }
                
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
                        console.error('[PTG Quiz] 선택지 텍스트가 비어있음:', {
                            index: index,
                            option: option,
                            optionText: optionText,
                            textElement: text,
                            textContent: text.textContent,
                            innerText: text.innerText,
                            innerHTML: text.innerHTML,
                            parentLabel: label,
                            computedStyle: window.getComputedStyle(text)
                        });
                        // 다시 설정 시도
                        text.textContent = optionText;
                        text.innerText = optionText;
                    } else {
                        console.log(`[PTG Quiz] 선택지 ${index + 1} 렌더링 성공:`, actualText);
                    }
                }, 100);
            });
            
            console.log('[PTG Quiz] 모든 선택지 렌더링 완료:', {
                총개수: options.length,
                컨테이너자식수: choicesContainer.children.length
            });
        }
    }
    
    /**
     * 타이머 시작
     */
    function startTimer() {
        if (QuizState.timerInterval) {
            clearInterval(QuizState.timerInterval);
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
        const display = document.getElementById('ptg-timer-display');
        if (!display) return;
        
        const minutes = Math.floor(QuizState.timerSeconds / 60);
        const seconds = QuizState.timerSeconds % 60;
        
        display.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    
    /**
     * 타이머 만료
     */
    function timerExpired() {
        showError('시간이 종료되었습니다.');
        // 자동 제출 또는 알림
    }
    
    /**
     * 북마크 토글
     */
    async function toggleBookmark() {
        const btn = document.querySelector('.ptg-btn-bookmark');
        const isBookmarked = btn.classList.contains('active');
        
        try {
            await PTGPlatform.patch(`ptg-quiz/v1/questions/${QuizState.questionId}/state`, {
                bookmarked: !isBookmarked
            });
            
            btn.classList.toggle('active');
            btn.querySelector('.ptg-icon').textContent = isBookmarked ? '☆' : '★';
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
        const needsReview = btn.classList.contains('active');
        
        try {
            await PTGPlatform.patch(`ptg-quiz/v1/questions/${QuizState.questionId}/state`, {
                needs_review: !needsReview
            });
            
            btn.classList.toggle('active');
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
        if (!panel) return;
        
        const isCurrentlyVisible = panel.style.display !== 'none';
        const shouldShow = force !== null ? force : !isCurrentlyVisible;
        
        if (shouldShow) {
            // 인라인 스타일로 position을 static으로 강제 설정 (팝업 방지)
            // 인라인 스타일은 CSS보다 우선순위가 높으므로 직접 설정
            panel.style.cssText = 
                'position: static; ' +
                'top: auto; ' +
                'left: auto; ' +
                'right: auto; ' +
                'bottom: auto; ' +
                'z-index: auto; ' +
                'width: 100%; ' +
                'max-width: 100%; ' +
                'margin: 20px 0 0 0; ' +
                'transform: none; ' +
                'float: none; ' +
                'clear: both; ' +
                'display: flex;';
            
            // MutationObserver로 스타일 변경 감지 및 재적용
            if (!panel._ptgNotesObserver) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                            // 다른 스크립트가 스타일을 변경했을 때 다시 적용
                            setTimeout(function() {
                                if (typeof window.ptgForceNotesPanelStyle === 'function') {
                                    window.ptgForceNotesPanelStyle();
                                }
                            }, 10);
                        }
                    });
                });
                observer.observe(panel, {
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
                panel._ptgNotesObserver = observer;
            }
            
            // 전역 함수 호출
            if (typeof window.ptgForceNotesPanelStyle === 'function') {
                window.ptgForceNotesPanelStyle();
            }
            
            // 메모 패널로 스크롤
            setTimeout(() => {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        } else {
            panel.style.display = 'none';
            // Observer 정리
            if (panel._ptgNotesObserver) {
                panel._ptgNotesObserver.disconnect();
                panel._ptgNotesObserver = null;
            }
        }
    }
    
    /**
     * 드로잉 토글
     */
    function toggleDrawing(force = null) {
        const overlay = document.getElementById('ptg-drawing-overlay');
        if (!overlay) return;
        
        const shouldEnable = force !== null ? force : !QuizState.drawingEnabled;
        
        if (shouldEnable) {
            overlay.style.display = 'block';
            QuizState.drawingEnabled = true;
            
            // 드로잉 캔버스 초기화
            initDrawingCanvas();
        } else {
            overlay.style.display = 'none';
            QuizState.drawingEnabled = false;
        }
    }
    
    /**
     * 드로잉 캔버스 초기화
     */
    function initDrawingCanvas() {
        const canvas = document.getElementById('ptg-drawing-canvas');
        if (!canvas) return;
        
        const card = document.getElementById('ptg-quiz-card');
        if (!card) return;
        
        // 캔버스 크기를 문제 카드에 맞춤
        const rect = card.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        
        // 드로잉 기능 구현 (추후 확장)
        // TODO: 펜, 지우개, Undo/Redo 구현
    }
    
    /**
     * 정답 확인
     */
    async function checkAnswer() {
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
        
        if (!userAnswer) {
            const missing = '답을 선택해주세요.';
            if (typeof window.__PTG_ORIG_ALERT === 'function') {
                window.__PTG_ORIG_ALERT(missing);
            } else if (typeof window.alert === 'function') {
                window.alert(missing);
            } else {
                showError(missing);
            }
            return;
        }
        
        const btnCheck = document.getElementById('ptg-btn-check-answer');
        if (btnCheck) {
            btnCheck.disabled = true;
        }
        
        try {
            // 사용자 답안에서 원형 숫자를 일반 숫자로 변환
            const normalizedAnswer = circleToNumber(userAnswer);
            
            const response = await PTGPlatform.post(`ptg-quiz/v1/questions/${QuizState.questionId}/attempt`, {
                answer: normalizedAnswer,
                elapsed: QuizState.timerSeconds
            });
            
            if (response && response.data) {
                QuizState.isAnswered = true;
                
                // 공통 컴포넌트로 정답/오답 표시
                if (typeof PTGQuizUI !== 'undefined') {
                    PTGQuizUI.showAnswerFeedback('ptg-quiz-choices', response.data.correct_answer, userAnswer);
                }
                // 보조 하이라이트 및 비활성화(보장)
                try { applyAnswerHighlight(response.data.correct_answer, userAnswer); } catch(e) {}
                // 정답 알림(Alert) - 기출 문제 학습 UX 강화
                if (response.data.is_correct) {
                    if (typeof PTG_quiz_alert === 'function') {
                        PTG_quiz_alert('정답입니다!');
                    }
                }
                
                showResult(response.data.is_correct, response.data.correct_answer);
                
                // 해설 표시
                await showExplanation();
            }
        } catch (error) {
            console.error('정답 확인 오류:', error);
            showError('정답 확인 중 오류가 발생했습니다.');
        } finally {
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
        
        const btnNext = document.getElementById('ptg-btn-next-question');
        if (btnNext) {
            btnNext.style.display = 'inline-block';
        }

        // 피드백 메시지: 기출문제학습 스타일의 간단한 피드백 박스 추가
        let feedbackBox = document.getElementById('ptg-quiz-feedback');
        if (!feedbackBox && document.getElementById('ptg-quiz-card')) {
            feedbackBox = document.createElement('div');
            feedbackBox.id = 'ptg-quiz-feedback';
            feedbackBox.style.margin = '12px 0';
            feedbackBox.style.padding = '12px';
            feedbackBox.style.borderRadius = '6px';
            feedbackBox.style.border = '1px solid #d6e4f0';
            feedbackBox.style.background = isCorrect ? '#e8f5e8' : '#f8d7da';
        }
        if (feedbackBox) {
            const correctLabel = '정답입니다!';
            const notFoundLabel = `오답입니다. 정답은 "${correctNum}" 입니다.`;
            feedbackBox.textContent = isCorrect ? correctLabel : notFoundLabel;
            // insert after explanation area if exists, else after card
            const cardEl = document.getElementById('ptg-quiz-card');
            if (cardEl && feedbackBox.parentNode !== cardEl.parentNode) {
                cardEl.parentNode.insertBefore(feedbackBox, cardEl.nextSibling);
            }
        }
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
                }
            }
        } catch (error) {
            console.error('해설 로드 오류:', error);
        }
    }
    
    /**
     * 다음 문제 로드
     */
    function loadNextQuestion() {
        // TODO: 다음 문제 로직 구현
        console.log('[PTG Quiz] 다음 문제 기능은 추후 구현됩니다.');
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
        if (typeof PTGPlatform !== 'undefined' && typeof PTGPlatform.debounce === 'function') {
            return PTGPlatform.debounce(func, wait);
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

