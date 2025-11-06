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
            const years = await apiRequest('years');
            const select = document.getElementById('ptgates-filter-year');
            
            if (select && years) {
                years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year + '년';
                    select.appendChild(option);
                });
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
            
            if (select && subjects) {
                // 기존 옵션 유지 (전체 옵션)
                const firstOption = select.querySelector('option[value=""]');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('과목 목록 로드 실패:', error);
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
        const limit = document.getElementById('ptgates-filter-limit')?.value || '10';
        
        const filters = {
            year: year || null,
            subject: subject || null,
            limit: parseInt(limit, 10)
        };
        
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
        
        // 타이머 시작
        if (typeof PTGTimer !== 'undefined') {
            PTGTimer.start();
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
        
        // 연도 및 과목 목록 로드
        loadYears();
        loadSubjects();
        
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
