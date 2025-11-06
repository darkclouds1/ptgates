/**
 * PTGates 공통 퀴즈 UI 컴포넌트
 * 
 * 문제 표시 및 선택지 렌더링을 위한 공통 JavaScript 모듈
 */

(function() {
    'use strict';
    
    // alert 차단(간단한 대입으로 우회, 중복 재정의 방지)
    if (typeof window !== 'undefined') {
        try { window.alert = function() { return false; }; } catch (e) {}
    }
    
    /**
     * 공통 퀴즈 UI 객체
     */
    const PTGQuizUI = {
        /**
         * 문제 표시
         * 
         * @param {object} question 문제 데이터
         * @param {object} options 옵션
         * @param {string} options.questionId 문제 텍스트 요소 ID
         * @param {string} options.optionsId 선택지 컨테이너 ID
         * @param {string} options.answerName 라디오 버튼 name 속성
         * @param {number} options.questionNumber 문제 번호 (선택사항)
         */
        displayQuestion(question, options = {}) {
            const {
                questionId = 'ptg-quiz-ui-question-text',
                optionsId = 'ptg-quiz-ui-options-container',
                answerName = 'ptg-quiz-ui-answer',
                questionNumber = null
            } = options;
            
            // 문제 텍스트 표시
            const questionTextEl = document.getElementById(questionId);
            if (questionTextEl) {
                const questionText = question.question_text || question.content || '';
                const numberPrefix = questionNumber ? `${questionNumber}. ` : '';
                questionTextEl.textContent = numberPrefix + questionText;
            }
            
            // 선택지 표시
            const optionsArray = question.options || [];
            this.displayOptions(optionsArray, {
                containerId: optionsId,
                answerName: answerName
            });
        },
        
        /**
         * 선택지 표시
         * 
         * @param {array} options 선택지 배열
         * @param {object} config 설정
         * @param {string} config.containerId 컨테이너 ID
         * @param {string} config.answerName 라디오 버튼 name 속성
         */
        displayOptions(options, config = {}) {
            const {
                containerId = 'ptg-quiz-ui-options-container',
                answerName = 'ptg-quiz-ui-answer'
            } = config;
            
            const container = document.getElementById(containerId);
            if (!container) {
                console.error('[PTG Quiz UI] 선택지 컨테이너를 찾을 수 없음: ' + containerId);
                return;
            }
            
            // 컨테이너 초기화 및 스타일 설정
            container.style.display = 'block';
            container.style.width = '100%';
            container.style.marginTop = '0';
            container.style.padding = '10px 12px';
            container.innerHTML = '';
            
            if (!options || options.length === 0) {
                // 주관식인 경우
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'ptg-quiz-ui-text-answer';
                input.id = 'ptg-quiz-ui-user-answer';
                input.placeholder = '답을 입력하세요';
                container.appendChild(input);
            } else {
                // 객관식인 경우
                options.forEach((option, index) => {
                    const label = document.createElement('label');
                    label.className = 'ptg-quiz-ui-option-label';
                    const optionId = `${containerId}-option-${index}`;
                    label.setAttribute('for', optionId);
                    
                    // 라벨 스타일 강제 적용
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
                    label.style.clear = 'both';
                    label.style.float = 'none';
                    label.style.minHeight = 'auto';
                    
                    // 라디오 버튼 생성
                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = answerName;
                    radio.value = option;
                    radio.id = optionId;
                    radio.className = 'ptg-quiz-ui-radio-input';
                    
                    // 라디오 버튼 스타일 강제 적용
                    const radioStyles = {
                        'width': '20px',
                        'height': '20px',
                        'min-width': '20px',
                        'min-height': '20px',
                        'max-width': '20px',
                        'max-height': '20px',
                        'margin-right': '8px',
                        'margin-top': '2px',
                        'cursor': 'pointer',
                        'flex-shrink': '0',
                        'opacity': '1',
                        'visibility': 'visible',
                        'display': 'inline-block',
                        'z-index': '10',
                        'pointer-events': 'auto',
                        'position': 'relative',
                        '-webkit-appearance': 'radio',
                        '-moz-appearance': 'radio',
                        'appearance': 'radio'
                    };
                    
                    Object.keys(radioStyles).forEach(prop => {
                        radio.style.setProperty(prop, radioStyles[prop], 'important');
                    });
                    
                    // 옵션 텍스트 생성 (ptgates-engine과 동일한 방식)
                    const text = document.createElement('span');
                    text.className = 'ptg-quiz-ui-option-text';
                    // 옵션 텍스트를 그대로 표시 (원형 숫자 포함)
                    // 옵션 텍스트가 이미 "① 텍스트" 형식으로 포함되어 있으므로 그대로 사용
                    const optionText = String(option || '').trim();
                    
                    // 옵션 텍스트가 비어있으면 경고
                    if (!optionText) {
                        console.error(`[PTG Quiz UI] 선택지 ${index + 1} 텍스트가 비어있음:`, option);
                    }
                    
                    // textContent 설정 (ptgates-engine과 동일)
                    text.textContent = optionText;
                    // innerHTML도 설정 (일부 브라우저 호환성)
                    text.innerHTML = optionText;
                    
                    // 강제로 텍스트 표시
                    text.setAttribute('data-option-text', optionText);
                    
                    // 텍스트가 비어있으면 강제로 다시 설정
                    if (!text.textContent || text.textContent.trim() === '') {
                        text.textContent = optionText;
                        text.innerHTML = optionText;
                    }
                    
                    // 텍스트 인라인 스타일 강제 적용 (ptgates-engine과 동일)
                    // ptgates-engine과 정확히 동일한 방식으로 설정
                    text.style.display = 'block';
                    text.style.flex = '1';
                    text.style.whiteSpace = 'normal';
                    text.style.wordWrap = 'break-word';
                    text.style.overflowWrap = 'break-word';
                    text.style.lineHeight = '1.3';
                    text.style.color = '#333';
                    text.style.width = 'calc(100% - 40px)';
                    
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
                    
                    // 라디오 버튼 클릭 이벤트
                    radio.addEventListener('click', function(e) {
                        if (radio.disabled) { e.preventDefault(); e.stopPropagation(); return; }
                        e.stopPropagation();
                    });
                    
                    // 요소 추가 (순서 중요: radio → text)
                    label.appendChild(radio);
                    label.appendChild(text);
                    container.appendChild(label);
                    
                    // 즉시 확인: 텍스트가 제대로 추가되었는지
                    const addedText = label.querySelector('.ptg-quiz-ui-option-text');
                    if (!addedText) {
                        console.error(`[PTG Quiz UI] 선택지 ${index + 1} 텍스트 요소가 추가되지 않음`);
                        // 강제로 다시 추가
                        const newText = document.createElement('span');
                        newText.className = 'ptg-quiz-ui-option-text';
                        newText.textContent = optionText;
                        newText.innerHTML = optionText;
                        // textStyles 변수 사용 (위에서 정의됨)
                        const fallbackTextStyles = {
                            'display': 'block',
                            'flex': '1',
                            'white-space': 'normal',
                            'word-wrap': 'break-word',
                            'overflow-wrap': 'break-word',
                            'line-height': '1.3',
                            'color': '#333',
                            'width': 'calc(100% - 40px)',
                            'visibility': 'visible',
                            'opacity': '1',
                            'font-size': '16px',
                            'font-weight': 'normal',
                            'text-align': 'left',
                            'position': 'relative',
                            'z-index': '1'
                        };
                        Object.keys(fallbackTextStyles).forEach(prop => {
                            newText.style.setProperty(prop, fallbackTextStyles[prop], 'important');
                        });
                        label.appendChild(newText);
                    } else {
                        // 텍스트가 비어있거나 보이지 않으면 강제로 설정
                        if (!addedText.textContent || addedText.textContent.trim() === '' || 
                            addedText.offsetWidth === 0 || addedText.offsetHeight === 0) {
                            addedText.textContent = optionText;
                            addedText.innerHTML = optionText;
                            addedText.style.display = 'block';
                            addedText.style.visibility = 'visible';
                            addedText.style.opacity = '1';
                            addedText.style.width = 'calc(100% - 40px)';
                            addedText.style.color = '#333';
                            addedText.style.fontSize = '16px';
                            addedText.style.fontWeight = 'normal';
                            addedText.style.lineHeight = '1.3';
                            addedText.style.whiteSpace = 'normal';
                            addedText.style.wordWrap = 'break-word';
                            addedText.style.overflowWrap = 'break-word';
                        }
                    }
                    
                    // 생성 후 즉시 스타일 재확인 (ptgates-engine과 동일)
                    setTimeout(() => {
                        // 라디오 버튼 확인 (ptgates-engine과 동일)
                        if (radio.style.display === 'none' || radio.offsetWidth === 0) {
                            radio.style.display = 'inline-block';
                            radio.style.visibility = 'visible';
                            radio.style.opacity = '1';
                        }
                        
                        // 부모 레이아웃 강제 설정 (항상 적용)
                        label.style.setProperty('display', 'flex', 'important');
                        label.style.setProperty('flex-direction', 'row', 'important');
                        label.style.setProperty('width', '100%', 'important');
                        label.style.setProperty('align-items', 'flex-start', 'important');
                        
                        // 텍스트 요소 확인 및 강제 설정
                        const computedStyle = window.getComputedStyle(text);
                        const parentComputedStyle = window.getComputedStyle(label);
                        
                        if (text.offsetWidth === 0 || text.offsetHeight === 0) {
                            // 텍스트 스타일 강제 재설정 (setProperty로 !important 적용)
                            text.style.setProperty('display', 'block', 'important');
                            text.style.setProperty('flex', '1', 'important');
                            text.style.setProperty('width', 'calc(100% - 40px)', 'important');
                            text.style.setProperty('visibility', 'visible', 'important');
                            text.style.setProperty('opacity', '1', 'important');
                            text.style.setProperty('white-space', 'normal', 'important');
                            text.style.setProperty('word-wrap', 'break-word', 'important');
                            text.style.setProperty('overflow-wrap', 'break-word', 'important');
                            text.style.setProperty('line-height', '1.3', 'important');
                            text.style.setProperty('color', '#333', 'important');
                        }
                    }, 0);
                });
            }
        },
        
        /**
         * 선택된 답안 가져오기
         * 
         * @param {object} config 설정
         * @param {string} config.answerName 라디오 버튼 name 속성
         * @param {string} config.textAnswerId 주관식 입력 필드 ID
         * @returns {string} 선택된 답안
         */
        getSelectedAnswer(config = {}) {
            const {
                answerName = 'ptg-quiz-ui-answer',
                textAnswerId = 'ptg-quiz-ui-user-answer'
            } = config;
            
            // 객관식
            const selectedRadio = document.querySelector(`input[name="${answerName}"]:checked`);
            if (selectedRadio) {
                return selectedRadio.value;
            }
            
            // 주관식
            const textInput = document.getElementById(textAnswerId);
            if (textInput) {
                return textInput.value.trim();
            }
            
            return '';
        },
        
        /**
         * 정답/오답 표시
         * 
         * @param {string} containerId 선택지 컨테이너 ID
         * @param {string} correctAnswer 정답
         * @param {string} userAnswer 사용자 답안
         */
        showAnswerFeedback(containerId, correctAnswer, userAnswer) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const labels = container.querySelectorAll('.ptg-quiz-ui-option-label');
            const normalizedCorrect = this.normalizeAnswer(String(correctAnswer || ''));
            const normalizedUser = this.normalizeAnswer(String(userAnswer || ''));

            labels.forEach(label => {
                const radio = label.querySelector('input[type="radio"]');
                if (!radio) return;
                
                // 기존 표시 초기화
                label.classList.remove('ptg-quiz-ui-correct-answer');
                label.classList.remove('ptg-quiz-ui-incorrect-answer');
                
                const optionText = String(radio.value || '');
                const optionNorm = this.normalizeAnswer(optionText);
                const isCorrect = optionNorm === normalizedCorrect;
                const isUserAnswer = optionNorm === normalizedUser;
                
                if (isCorrect) {
                    label.classList.add('ptg-quiz-ui-correct-answer');
                    // 가시성 보장(강제 스타일)
                    try {
                        label.style.setProperty('background', '#d4edda', 'important');
                    } catch(e) {}
                } else if (isUserAnswer && !isCorrect) {
                    label.classList.add('ptg-quiz-ui-incorrect-answer');
                    try {
                        label.style.setProperty('background', '#f8d7da', 'important');
                    } catch(e) {}
                }
                // 정답 확인 이후 입력 비활성화
                try { radio.disabled = true; } catch(e) {}
                // 추가 가드: 라벨 클릭 비활성화
                try {
                    label.style.setProperty('pointer-events', 'none', 'important');
                    label.style.setProperty('cursor', 'default', 'important');
                } catch(e) {}
            });
        },
        
        /**
         * 답안 정규화 (원형 숫자 → 일반 숫자)
         * 
         * @param {string} answer 답안
         * @returns {string} 정규화된 답안
         */
        normalizeAnswer(answer) {
            const circleMap = {
                '①': '1', '②': '2', '③': '3', '④': '4', '⑤': '5',
                '⑥': '6', '⑦': '7', '⑧': '8', '⑨': '9', '⑩': '10'
            };
            
            // 옵션 텍스트에서 원형 숫자 추출
            for (const [circle, num] of Object.entries(circleMap)) {
                if (answer.includes(circle)) {
                    return num;
                }
            }
            
            // 원형 숫자가 없으면 원본 반환
            return answer.trim();
        },
        
        /**
         * 선택지 초기화 (정답 표시 제거)
         * 
         * @param {string} containerId 선택지 컨테이너 ID
         */
        resetOptions(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const labels = container.querySelectorAll('.ptg-quiz-ui-option-label');
            labels.forEach(label => {
                label.classList.remove('ptg-quiz-ui-correct-answer');
                label.classList.remove('ptg-quiz-ui-incorrect-answer');
                
                const radio = label.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = false;
                }
            });
            
            const textInput = container.querySelector('.ptg-quiz-ui-text-answer');
            if (textInput) {
                textInput.value = '';
            }
        }
    };
    
    // 전역 객체로 노출
    if (typeof window !== 'undefined') {
        window.PTGQuizUI = PTGQuizUI;
    }
    
    // CommonJS 모듈로도 노출 (Node.js 환경)
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = PTGQuizUI;
    }
})();

