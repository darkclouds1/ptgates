/**
 * PTGates Learning Engine - Main JavaScript
 * 
 * REST API 통신 및 퀴즈 메인 로직
 */

(function() {
    'use strict';
    
    // API 엔드포인트 (wp_localize_script로 주입됨)
    const API = typeof ptgatesAPI !== 'undefined' ? ptgatesAPI : {
        restUrl: '/wp-json/ptgates/v1/',
        nonce: '',
        userId: 0
    };
    
    // 퀴즈 상태 관리
    const QuizState = {
        questions: [],
        currentIndex: 0,
        answers: [],
        startTime: null,
        questionStartTime: null,
        timers: []
    };
    
    /**
     * REST API 호출 헬퍼
     */
    async function apiRequest(endpoint, options = {}) {
        const url = API.restUrl + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': API.nonce
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
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'API 요청 실패');
            }
            
            return data;
        } catch (error) {
            console.error('API 요청 오류:', error);
            throw error;
        }
    }
    
    /**
     * 사용 가능한 연도 목록 로드
     */
    async function loadYears() {
        try {
            // 캐시 방지를 위해 타임스탬프 추가
            const timestamp = new Date().getTime();
            const years = await apiRequest(`years?nocache=${timestamp}`);
            const select = document.getElementById('ptgates-filter-year');
            const container = document.getElementById('ptgates-quiz-container');
            
            // 디버깅: API 응답 확인
            console.log('PTGates years API response:', years);
            
            if (select && Array.isArray(years) && years.length) {
                // 숏코드에서 연도 속성 확인
                const shortcodeYear = container?.dataset.year || '';
                
                // 연도 정렬 (숫자 기준 내림차순)
                const sortedYears = years.map(Number).sort((a, b) => b - a);
                
                // 2025년도 필터링 (exam_session >= 1000인 경우 제외)
                // 서버에서 이미 필터링되지만, 클라이언트 측에서도 추가 안전장치
                const filteredYears = sortedYears.filter(year => year !== 2025);
                
                select.innerHTML = '';
                filteredYears.forEach(year => {
                    const option = document.createElement('option');
                    option.value = String(year);
                    option.textContent = year + '년';
                    select.appendChild(option);
                });
                
                // 숏코드에 연도가 지정된 경우 우선 적용, 없으면 최신 연도 선택
                if (shortcodeYear && filteredYears.includes(Number(shortcodeYear))) {
                    select.value = shortcodeYear;
                    await loadSubjects(shortcodeYear);
                } else if (filteredYears.length > 0) {
                    const latestYear = String(filteredYears[0]);
                    select.value = latestYear;
                    await loadSubjects(latestYear);
                }
            }
        } catch (error) {
            console.error('연도 목록 로드 실패:', error);
        }
    }
    
    /**
     * 사용 가능한 과목 목록 로드
     */
    async function loadSubjects(year = null) {
        try {
            const endpoint = year ? `subjects?year=${year}` : 'subjects';
            const subjects = await apiRequest(endpoint);
            const select = document.getElementById('ptgates-filter-subject');
            const container = document.getElementById('ptgates-quiz-container');
            
            if (select && Array.isArray(subjects)) {
                // 숏코드에서 과목 속성 확인
                const shortcodeSubject = container?.dataset.subject || '';
                
                // 기본 "과목" 옵션 유지
                const defaultOption = select.querySelector('option[value=""]');
                select.innerHTML = '';
                if (defaultOption) {
                    defaultOption.textContent = '과목';
                    select.appendChild(defaultOption);
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = '과목';
                    select.appendChild(option);
                }
                
                subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject;
                    select.appendChild(option);
                });
                
                // 숏코드에 과목이 지정된 경우 우선 적용, 없으면 기본값("과목" = 전체) 유지
                if (shortcodeSubject && subjects.includes(shortcodeSubject)) {
                    select.value = shortcodeSubject;
                } else {
                    select.value = '';
                }
            }
        } catch (error) {
            console.error('과목 목록 로드 실패:', error);
        }
    }
    
    /**
     * 단일 문제 로드 (ID로)
     */
    async function loadQuestionById(questionId) {
        try {
            showLoading(true);
            const question = await apiRequest(`question/${questionId}`);
            return question ? [question] : [];
        } catch (error) {
            console.error('문제 로드 오류:', error);
            alert('문제를 불러오는 중 오류가 발생했습니다: ' + error.message);
            return [];
        } finally {
            showLoading(false);
        }
    }
    
    /**
     * 문제 목록 로드
     */
    async function loadQuestions(filters = {}) {
        const params = new URLSearchParams();
        
        if (filters.year) params.append('year', filters.year);
        if (filters.subject) params.append('subject', filters.subject);
        if (filters.limit) params.append('limit', filters.limit);
        if (filters.session) params.append('session', filters.session);
        if (filters.full_session) params.append('full_session', filters.full_session ? '1' : '0');
        
        const queryString = params.toString();
        const endpoint = `questions${queryString ? '?' + queryString : ''}`;
        
        try {
            showLoading(true);
            const questions = await apiRequest(endpoint);
            return questions;
        } catch (error) {
            alert('문제를 불러오는 중 오류가 발생했습니다: ' + error.message);
            return [];
        } finally {
            showLoading(false);
        }
    }
    
    /**
     * 퀴즈 시작
     */
    async function startQuiz() {
        const year = document.getElementById('ptgates-filter-year')?.value || '';
        const subject = document.getElementById('ptgates-filter-subject')?.value || '';
        const limit = document.getElementById('ptgates-filter-limit')?.value || '5';
        const isSession1 = limit === 'session1';
        const isSession2 = limit === 'session2';
        
        // 세션 전체 풀이 시 연도 선택을 권장
        if ((isSession1 || isSession2) && !year) {
            alert('1교시/2교시 전체 풀이를 위해 연도를 선택해 주세요.');
            return;
        }

        const filters = {
            year: year || null,
            subject: subject || null
        };

        if (isSession1 || isSession2) {
            filters.session = isSession1 ? 1 : 2;
            filters.full_session = true;
        } else {
            filters.limit = parseInt(limit, 10);
        }
        
        const questions = await loadQuestions(filters);
        
        if (!questions || questions.length === 0) {
            alert('선택한 조건에 맞는 문제를 찾을 수 없습니다.');
            return;
        }
        
        // 상태 초기화
        QuizState.questions = questions;
        QuizState.currentIndex = 0;
        QuizState.answers = [];
        QuizState.startTime = Date.now();
        QuizState.questionStartTime = Date.now();
        
        // UI 초기화
        hideFilterSection();
        showProgressSection();
        showQuestionSection();
        
        // 첫 번째 문제 표시
        displayQuestion(0);
        
        // 헤더 위치로 스크롤
        scrollToHeader();
        
        // 타이머 시작 (세션 전체 + 제한시간 설정 시 카운트다운으로)
        if (typeof PTGTimer !== 'undefined') {
            let totalLimitSeconds = null;
            if (isSession1 || isSession2) {
                const timeMode = document.querySelector('input[name="ptgates-time-mode"]:checked')?.value || 'unlimited';
                if (timeMode === 'limited') {
                    const minutes = parseInt(document.getElementById('ptgates-time-minutes')?.value || '0', 10);
                    if (minutes > 0) {
                        totalLimitSeconds = minutes * 60;
                    }
                }
            }
            PTGTimer.start(totalLimitSeconds);
        }
    }
    
    /**
     * 문제 표시
     */
    function displayQuestion(index) {
        if (index < 0 || index >= QuizState.questions.length) {
            return;
        }
        
        const question = QuizState.questions[index];
        QuizState.currentIndex = index;
        QuizState.questionStartTime = Date.now();
        
        // UI 업데이트
        if (typeof PTGUI !== 'undefined') {
            PTGUI.displayQuestion(question, index + 1, QuizState.questions.length);
        }
        
        // 진행률 업데이트
        updateProgress(index + 1, QuizState.questions.length);
    }
    
    /**
     * 답안 제출
     */
    function submitAnswer() {
        const question = QuizState.questions[QuizState.currentIndex];
        const userAnswer = PTGUI?.getSelectedAnswer() || '';
        
        if (!userAnswer) {
            alert('답을 선택해주세요.');
            return;
        }
        
        // 소요 시간 계산
        const elapsedTime = Math.floor((Date.now() - QuizState.questionStartTime) / 1000);
        
        // 정답 확인
        const isCorrect = checkAnswer(question.answer, userAnswer);
        
        // 답안 저장
        QuizState.answers.push({
            questionId: question.id,
            userAnswer: userAnswer,
            correctAnswer: question.answer,
            isCorrect: isCorrect,
            elapsedTime: elapsedTime
        });
        
        // 로그 저장 (로그인한 사용자인 경우)
        if (API.userId > 0) {
            saveResult(question.id, userAnswer, isCorrect, elapsedTime);
        }
        
        // 피드백 표시
        if (typeof PTGUI !== 'undefined') {
            PTGUI.showFeedback(isCorrect, question);
        }
        
        // 다음 버튼 표시
        document.getElementById('ptgates-submit-btn')?.style.setProperty('display', 'none');
        document.getElementById('ptgates-next-btn')?.style.setProperty('display', 'inline-block');
        
        // 헤더 위치로 스크롤
        scrollToHeader();
    }
    
    /**
     * 정답 확인
     */
    function checkAnswer(correctAnswer, userAnswer) {
        // 원형 숫자를 일반 숫자로 변환하는 함수
        const circleToNumber = (str) => {
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
            // 원형 숫자가 없으면 원본 반환
            return str.trim();
        };
        
        // 정답 정규화 (원형 숫자 → 숫자 변환)
        const normalizeCorrect = (str) => {
            const normalized = circleToNumber(str);
            // 숫자만 추출
            const numMatch = normalized.match(/^\d+/);
            return numMatch ? numMatch[0] : normalized;
        };
        
        // 사용자 답안 정규화 (옵션 텍스트에서 원형 숫자 추출)
        const normalizeUser = (str) => {
            return circleToNumber(str);
        };
        
        const correct = normalizeCorrect(correctAnswer);
        const user = normalizeUser(userAnswer);
        
        return correct === user;
    }
    
    /**
     * 다음 문제로 이동
     */
    function nextQuestion() {
        const nextIndex = QuizState.currentIndex + 1;
        
        if (nextIndex >= QuizState.questions.length) {
            finishQuiz();
            return;
        }
        
        // UI 초기화
        if (typeof PTGUI !== 'undefined') {
            PTGUI.resetQuestion();
        }
        
        displayQuestion(nextIndex);
        
        // 버튼 상태 복원
        document.getElementById('ptgates-submit-btn')?.style.setProperty('display', 'inline-block');
        document.getElementById('ptgates-next-btn')?.style.setProperty('display', 'none');
        
        // 헤더 위치로 스크롤
        scrollToHeader();
    }
    
    /**
     * 퀴즈 완료
     */
    function finishQuiz() {
        const totalTime = Math.floor((Date.now() - QuizState.startTime) / 1000);
        const correctCount = QuizState.answers.filter(a => a.isCorrect).length;
        const incorrectCount = QuizState.answers.length - correctCount;
        const accuracy = QuizState.answers.length > 0 
            ? Math.round((correctCount / QuizState.answers.length) * 100) 
            : 0;
        
        // 타이머 정지
        if (typeof PTGTimer !== 'undefined') {
            PTGTimer.stop();
        }
        
        // 결과 표시
        if (typeof PTGUI !== 'undefined') {
            PTGUI.showResult({
                accuracy: accuracy,
                correct: correctCount,
                incorrect: incorrectCount,
                totalTime: totalTime
            });
        }
        
        hideQuestionSection();
        hideProgressSection();
    }
    
    /**
     * 퀴즈 포기
     */
    function giveUpQuiz() {
        // 현재 문제까지 답안 제출한 경우, 그 문제의 답안도 저장
        const currentQuestion = QuizState.questions[QuizState.currentIndex];
        if (currentQuestion && QuizState.answers.length === QuizState.currentIndex) {
            // 현재 문제에 대한 답안이 없으면, 빈 답안으로 처리
            const userAnswer = typeof PTGUI !== 'undefined' ? PTGUI.getSelectedAnswer() : '';
            if (userAnswer) {
                const isCorrect = checkAnswer(currentQuestion.answer, userAnswer);
                const elapsedTime = Math.floor((Date.now() - QuizState.questionStartTime) / 1000);
                
                QuizState.answers.push({
                    questionId: currentQuestion.id,
                    userAnswer: userAnswer,
                    isCorrect: isCorrect,
                    elapsedTime: elapsedTime
                });
                
                // 결과 저장
                saveResult(currentQuestion.id, userAnswer, isCorrect, elapsedTime);
            }
        }
        
        // 퀴즈 완료 처리
        finishQuiz();
    }
    
    /**
     * 결과 저장 (REST API)
     */
    async function saveResult(questionId, userAnswer, isCorrect, elapsedTime) {
        try {
            await apiRequest('log', {
                method: 'POST',
                body: JSON.stringify({
                    question_id: questionId,
                    user_answer: userAnswer,
                    is_correct: isCorrect,
                    elapsed_time: elapsedTime
                })
            });
        } catch (error) {
            console.error('결과 저장 실패:', error);
            // 오류가 발생해도 계속 진행
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
     * UI 헬퍼 함수
     */
    function showLoading(show) {
        const loading = document.getElementById('ptgates-loading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    }
    
    function hideFilterSection() {
        const filter = document.querySelector('.ptgates-filter-section');
        if (filter) filter.style.display = 'none';
    }
    
    function showProgressSection() {
        const progress = document.getElementById('ptgates-progress-section');
        if (progress) progress.style.display = 'block';
    }
    
    function hideProgressSection() {
        const progress = document.getElementById('ptgates-progress-section');
        if (progress) progress.style.display = 'none';
    }
    
    function showQuestionSection() {
        const question = document.getElementById('ptgates-question-section');
        if (question) question.style.display = 'block';
    }
    
    function hideQuestionSection() {
        const question = document.getElementById('ptgates-question-section');
        if (question) question.style.display = 'none';
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
     * 초기화
     */
    function init() {
        // DOM 로드 완료 후 실행
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // 숏코드 속성 확인
        const container = document.getElementById('ptgates-quiz-container');
        const shortcodeLimit = container?.dataset.limit || '';
        const shortcodeId = container?.dataset.id || '';
        
        // ID 속성이 있으면 해당 문제를 바로 로드
        if (shortcodeId) {
            const questionId = parseInt(shortcodeId, 10);
            if (questionId > 0) {
                loadQuestionById(questionId).then(questions => {
                    if (questions && questions.length > 0) {
                        // 상태 초기화
                        QuizState.questions = questions;
                        QuizState.currentIndex = 0;
                        QuizState.answers = [];
                        QuizState.startTime = Date.now();
                        QuizState.questionStartTime = Date.now();
                        
                        // UI 초기화
                        hideFilterSection();
                        showProgressSection();
                        showQuestionSection();
                        
                        // 첫 번째 문제 표시
                        displayQuestion(0);
                        
                        // 헤더 위치로 스크롤
                        scrollToHeader();
                    } else {
                        alert('문제를 찾을 수 없습니다.');
                    }
                });
                return; // ID가 있으면 필터 섹션은 표시하지 않음
            }
        }
        
        // 문제 수 숏코드 속성 적용
        if (shortcodeLimit) {
            const limitSelect = document.getElementById('ptgates-filter-limit');
            if (limitSelect) {
                const option = limitSelect.querySelector(`option[value="${shortcodeLimit}"]`);
                if (option) {
                    limitSelect.value = shortcodeLimit;
                }
            }
        }
        
        // 연도 및 과목 목록 로드 (loadYears 내부에서 loadSubjects 호출)
        loadYears();
        
        // 연도 변경 시 과목 목록 업데이트
        const yearSelect = document.getElementById('ptgates-filter-year');
        if (yearSelect) {
            yearSelect.addEventListener('change', (e) => {
                const year = e.target.value;
                loadSubjects(year || null);
            });
        }
        
        // 시작 버튼
        const startBtn = document.getElementById('ptgates-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', startQuiz);
        }

        // 문제 수 선택에 세션 옵션이 포함될 때 시간 설정 토글
        const limitSelect = document.getElementById('ptgates-filter-limit');
        const sessionTimeRow = document.getElementById('ptgates-session-time-row');
        const timeMinutesInput = document.getElementById('ptgates-time-minutes');
        const timeMinutesSuffix = document.getElementById('ptgates-time-minutes-suffix');
        if (limitSelect) {
            limitSelect.addEventListener('change', (e) => {
                const val = e.target.value;
                const isSession1 = val === 'session1';
                const isSession2 = val === 'session2';
                if (sessionTimeRow) {
                    sessionTimeRow.style.display = (isSession1 || isSession2) ? 'flex' : 'none';
                }
                // 기본 시간값 및 라디오 초기화
                if (isSession1 || isSession2) {
                    const defaultMinutes = isSession1 ? 90 : 75;
                    if (timeMinutesInput) {
                        timeMinutesInput.value = String(defaultMinutes);
                    }
                    // 기본은 제한시간 선택
                    const unlimitedRadio = document.querySelector('input[name="ptgates-time-mode"][value="unlimited"]');
                    const limitedRadio = document.querySelector('input[name="ptgates-time-mode"][value="limited"]');
                    if (limitedRadio) limitedRadio.checked = true;
                    if (timeMinutesInput) timeMinutesInput.style.display = 'inline-block';
                    if (timeMinutesSuffix) timeMinutesSuffix.style.display = 'inline-block';
                } else {
                    // 숨김 및 초기화
                    const unlimitedRadio = document.querySelector('input[name="ptgates-time-mode"][value="unlimited"]');
                    if (unlimitedRadio) unlimitedRadio.checked = true;
                    if (timeMinutesInput) timeMinutesInput.style.display = 'none';
                    if (timeMinutesSuffix) timeMinutesSuffix.style.display = 'none';
                }
            });
        }

        // 시간 라디오 토글에 따른 분 입력 표시
        const timeModeRadios = document.querySelectorAll('input[name="ptgates-time-mode"]');
        if (timeModeRadios && timeModeRadios.length) {
            timeModeRadios.forEach((radio) => {
                radio.addEventListener('change', (e) => {
                    const mode = e.target.value;
                    if (mode === 'limited') {
                        if (timeMinutesInput) timeMinutesInput.style.display = 'inline-block';
                        if (timeMinutesSuffix) timeMinutesSuffix.style.display = 'inline-block';
                    } else {
                        if (timeMinutesInput) timeMinutesInput.style.display = 'none';
                        if (timeMinutesSuffix) timeMinutesSuffix.style.display = 'none';
                    }
                });
            });
        }
        
        // 제출 버튼
        const submitBtn = document.getElementById('ptgates-submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', submitAnswer);
        }
        
        // 다음 버튼
        const nextBtn = document.getElementById('ptgates-next-btn');
        if (nextBtn) {
            nextBtn.addEventListener('click', nextQuestion);
        }
        
        // 다시 시작 버튼
        const restartBtn = document.getElementById('ptgates-restart-btn');
        if (restartBtn) {
            restartBtn.addEventListener('click', () => {
                // 리로드 후 헤더로 스크롤하기 위한 플래그 저장
                sessionStorage.setItem('ptgates-scroll-to-header', 'true');
                location.reload();
            });
        }
        
        // 포기 버튼
        const giveupBtn = document.getElementById('ptgates-giveup-btn');
        if (giveupBtn) {
            giveupBtn.addEventListener('click', () => {
                if (confirm('퀴즈를 포기하시겠습니까? 현재까지의 결과가 저장됩니다.')) {
                    giveUpQuiz();
                }
            });
        }
        
        // 시간관리 tip 버튼
        const timeTipBtn = document.getElementById('ptgates-time-tip-btn');
        const timeTipModal = document.getElementById('ptgates-time-tip-modal');
        const timeTipClose = document.getElementById('ptgates-time-tip-close');
        
        if (timeTipBtn && timeTipModal) {
            timeTipBtn.addEventListener('click', () => {
                timeTipModal.style.display = 'block';
            });
        }
        
        if (timeTipClose && timeTipModal) {
            timeTipClose.addEventListener('click', () => {
                timeTipModal.style.display = 'none';
            });
        }
        
        // 모달 외부 클릭 시 닫기
        if (timeTipModal) {
            const overlay = timeTipModal.querySelector('.ptgates-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => {
                    timeTipModal.style.display = 'none';
                });
            }
        }
        
        // ESC 키로 모달 닫기
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && timeTipModal && timeTipModal.style.display === 'block') {
                timeTipModal.style.display = 'none';
            }
        });

        // 다시 시작 버튼 클릭 후 리로드된 경우 헤더로 스크롤
        if (sessionStorage.getItem('ptgates-scroll-to-header') === 'true') {
            sessionStorage.removeItem('ptgates-scroll-to-header');
            // DOM이 완전히 로드된 후 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
        }
    }
    
    // 전역 객체에 함수 노출 (다른 모듈에서 사용)
    window.PTGMain = {
        QuizState: QuizState,
        apiRequest: apiRequest,
        displayQuestion: displayQuestion,
        submitAnswer: submitAnswer,
        nextQuestion: nextQuestion,
        finishQuiz: finishQuiz
    };
    
    // 초기화 실행
    init();
})();
