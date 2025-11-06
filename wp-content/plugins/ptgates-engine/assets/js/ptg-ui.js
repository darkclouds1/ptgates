/**
 * PTGates Learning Engine - UI Module
 * 
 * UI 렌더링 및 인터랙션 처리
 */

(function() {
    'use strict';
    
    const PTGUI = {
        /**
         * 문제 표시
         */
        displayQuestion(question, current, total) {
            // 문제 본문 (번호 포함)
            const questionText = document.getElementById('ptgates-question-text');
            if (questionText) {
                const questionNumber = current || 1;
                const questionContent = question.question_text || '';
                questionText.textContent = `${questionNumber}. ${questionContent}`;
            }
            
            // 옵션 표시
            this.displayOptions(question.options || []);
            
            // 해설 초기화
            this.hideExplanation();
            this.hideFeedback();
        },
        
        /**
         * 옵션 표시
         */
        displayOptions(options) {
            const container = document.getElementById('ptgates-options-container');
            if (!container) return;
            
            // 컨테이너 스타일 강제 적용 (시험지와 유사하게 간격 최소화)
            container.style.display = 'block';
            container.style.width = '100%';
            container.style.marginTop = '0';
            container.style.padding = '10px 12px';
            container.innerHTML = '';
            
            if (options.length === 0) {
                // 주관식인 경우
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'ptgates-text-answer';
                input.id = 'ptgates-user-answer';
                input.placeholder = '답을 입력하세요';
                container.appendChild(input);
            } else {
                // 객관식인 경우
                options.forEach((option, index) => {
                    const label = document.createElement('label');
                    label.className = 'ptgates-option-label';
                    const optionId = `ptgates-option-${index}`;
                    label.setAttribute('for', optionId);
                    
                    // 인라인 스타일 강제 적용 (다른 CSS 간섭 방지)
                    // 개별 외곽선 제거 (컨테이너에만 외곽선 적용)
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
                    
                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'ptgates-answer';
                    radio.value = option;
                    radio.id = optionId;
                    radio.className = 'ptgates-radio-input';
                    
                    // 라디오 버튼 인라인 스타일 강제 적용 (setProperty로 !important 추가)
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
                    
                    const text = document.createElement('span');
                    text.className = 'ptgates-option-text';
                    // 옵션 텍스트를 그대로 표시 (원형 숫자 포함)
                    // 옵션 텍스트가 이미 "① 텍스트" 형식으로 포함되어 있으므로 그대로 사용
                    text.textContent = String(option).trim();
                    
                    // 텍스트 인라인 스타일 강제 적용
                    text.style.display = 'block';
                    text.style.flex = '1';
                    text.style.whiteSpace = 'normal';
                    text.style.wordWrap = 'break-word';
                    text.style.overflowWrap = 'break-word';
                    text.style.lineHeight = '1.3';
                    text.style.color = '#333';
                    text.style.width = 'calc(100% - 40px)';
                    
                    // label 클릭 이벤트 추가 (추가 보장)
                    label.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (e.target !== radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    
                    // 라디오 버튼 클릭 이벤트
                    radio.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                    
                    // 요소를 올바른 순서로 추가
                    label.appendChild(radio);
                    label.appendChild(text);
                    
                    // 컨테이너에 추가
                    container.appendChild(label);
                    
                    // 생성 후 즉시 스타일 재확인 (다른 스크립트가 간섭하는 경우 대비)
                    setTimeout(() => {
                        if (radio.style.display === 'none' || radio.offsetWidth === 0) {
                            radio.style.display = 'inline-block';
                            radio.style.visibility = 'visible';
                            radio.style.opacity = '1';
                        }
                    }, 0);
                });
            }
        },
        
        /**
         * 선택된 답안 가져오기
         */
        getSelectedAnswer() {
            // 객관식
            const selectedRadio = document.querySelector('input[name="ptgates-answer"]:checked');
            if (selectedRadio) {
                return selectedRadio.value;
            }
            
            // 주관식
            const textInput = document.getElementById('ptgates-user-answer');
            if (textInput) {
                return textInput.value.trim();
            }
            
            return '';
        },
        
        /**
         * 피드백 표시
         */
        showFeedback(isCorrect, question) {
            const feedback = document.getElementById('ptgates-feedback');
            const message = document.getElementById('ptgates-feedback-message');
            
            if (!feedback || !message) return;
            
            // 피드백 메시지
            if (isCorrect) {
                feedback.className = 'ptgates-feedback ptgates-feedback-correct';
                message.textContent = '정답입니다!';
            } else {
                feedback.className = 'ptgates-feedback ptgates-feedback-incorrect';
                message.textContent = `오답입니다. 정답은 "${question.answer}"입니다.`;
            }
            
            feedback.style.display = 'block';
            
            // 선택한 답안 비활성화
            this.disableOptions();
            
            // 정답 표시
            this.highlightCorrectAnswer(question.answer, question.options || []);
            
            // 해설 표시
            this.showExplanation(question);
        },
        
        /**
         * 옵션 비활성화
         */
        disableOptions() {
            const radios = document.querySelectorAll('input[name="ptgates-answer"]');
            radios.forEach(radio => {
                radio.disabled = true;
            });
            
            const textInput = document.getElementById('ptgates-user-answer');
            if (textInput) {
                textInput.disabled = true;
            }
        },
        
        /**
         * 정답 하이라이트
         */
        highlightCorrectAnswer(correctAnswer, options) {
            if (options.length === 0) return;
            
            // 원형 숫자를 숫자로 변환하는 함수
            const circleToNumber = (str) => {
                const circleMap = {
                    '①': '1', '②': '2', '③': '3', '④': '4', '⑤': '5',
                    '⑥': '6', '⑦': '7', '⑧': '8', '⑨': '9', '⑩': '10'
                };
                for (const [circle, num] of Object.entries(circleMap)) {
                    if (str.includes(circle)) {
                        return num;
                    }
                }
                // 숫자만 추출
                const numMatch = str.match(/^\d+/);
                return numMatch ? numMatch[0] : str.trim();
            };
            
            // 정답 번호 추출
            const correctNum = circleToNumber(correctAnswer);
            
            options.forEach((option, index) => {
                const radio = document.getElementById(`ptgates-option-${index}`);
                const label = radio ? radio.closest('.ptgates-option-label') : null;
                
                // 옵션에서 번호 추출
                const optionNum = circleToNumber(option);
                
                // 정답 하이라이트
                if (label && correctNum === optionNum) {
                    label.classList.add('ptgates-correct-answer');
                }
                
                // 선택한 답안이 틀렸으면 표시
                if (radio && radio.checked && correctNum !== optionNum && label) {
                    label.classList.add('ptgates-incorrect-answer');
                }
            });
        },
        
        /**
         * 해설 텍스트 포맷팅 (동그라미 숫자 기준 줄바꿈)
         */
        formatExplanationText(text) {
            if (!text) return '';
            
            // 동그라미 숫자 패턴 (①-⑳)
            const circleNumberPattern = /[①-⑳]/g;
            
            // 동그라미 숫자 위치 찾기
            const matches = [];
            let match;
            while ((match = circleNumberPattern.exec(text)) !== null) {
                matches.push(match.index);
            }
            
            if (matches.length === 0) {
                return text;
            }
            
            // 포맷팅된 텍스트 생성
            let formatted = '';
            let lastIndex = 0;
            
            for (let i = 0; i < matches.length; i++) {
                const currentIndex = matches[i];
                const nextIndex = matches[i + 1];
                
                // 현재 동그라미 숫자 이전 텍스트
                if (currentIndex > lastIndex) {
                    formatted += text.substring(lastIndex, currentIndex);
                }
                
                // 첫 번째 동그라미 숫자 앞에 줄바꿈 추가
                if (i === 0 && formatted.length > 0) {
                    formatted += '\n';
                }
                
                // 다음 동그라미 숫자와의 거리 확인
                if (nextIndex !== undefined) {
                    const distance = nextIndex - currentIndex;
                    // 다음 동그라미 숫자가 10자 이내에 있으면 연속으로 간주
                    if (distance <= 10) {
                        // 연속된 동그라미 숫자: 현재 세그먼트에 포함하되 줄바꿈 없음
                        formatted += text.substring(currentIndex, nextIndex);
                        lastIndex = nextIndex;
                        continue;
                    } else {
                        // 떨어져 있는 경우: 현재 세그먼트 끝에 줄바꿈 추가
                        const segment = text.substring(currentIndex, nextIndex);
                        formatted += segment;
                        // 마침표나 줄바꿈이 없으면 추가
                        if (!segment.trim().endsWith('.') && !segment.trim().endsWith('。')) {
                            formatted += '\n';
                        }
                        lastIndex = nextIndex;
                    }
                } else {
                    // 마지막 동그라미 숫자: 텍스트 끝까지 추가
                    formatted += text.substring(currentIndex);
                    lastIndex = text.length;
                }
            }
            
            return formatted.trim();
        },
        
        /**
         * 해설 표시
         */
        showExplanation(question) {
            const section = document.getElementById('ptgates-explanation-section');
            const header = section ? section.querySelector('.ptgates-explanation-header h4') : null;
            const baseExp = document.getElementById('ptgates-base-explanation');
            const advancedExp = document.getElementById('ptgates-advanced-explanation');
            
            if (!section) return;
            
            // 해설 헤더에 과목 정보 추가
            if (header) {
                const subject = question.subject || '';
                if (subject) {
                    header.textContent = `해설 | (${subject})`;
                } else {
                    header.textContent = '해설';
                }
            }
            
            // 기본 해설
            if (baseExp && question.base_explanation) {
                const formattedText = this.formatExplanationText(question.base_explanation);
                baseExp.textContent = formattedText;
                baseExp.style.whiteSpace = 'pre-line';
                baseExp.style.display = 'block';
            }
            
            // 고급 해설
            if (advancedExp && question.advanced_explanation) {
                const formattedText = this.formatExplanationText(question.advanced_explanation);
                advancedExp.textContent = formattedText;
                advancedExp.style.whiteSpace = 'pre-line';
                advancedExp.style.display = 'block';
            }
            
            section.style.display = 'block';
        },
        
        /**
         * 해설 숨기기
         */
        hideExplanation() {
            const section = document.getElementById('ptgates-explanation-section');
            if (section) {
                section.style.display = 'none';
            }
            
            const baseExp = document.getElementById('ptgates-base-explanation');
            const advancedExp = document.getElementById('ptgates-advanced-explanation');
            
            if (baseExp) {
                baseExp.textContent = '';
                baseExp.style.display = 'none';
            }
            
            if (advancedExp) {
                advancedExp.textContent = '';
                advancedExp.style.display = 'none';
            }
        },
        
        /**
         * 피드백 숨기기
         */
        hideFeedback() {
            const feedback = document.getElementById('ptgates-feedback');
            if (feedback) {
                feedback.style.display = 'none';
                feedback.className = 'ptgates-feedback';
            }
        },
        
        /**
         * 문제 초기화
         */
        resetQuestion() {
            this.hideFeedback();
            this.hideExplanation();
            
            // 옵션 선택 해제
            const radios = document.querySelectorAll('input[name="ptgates-answer"]');
            radios.forEach(radio => {
                radio.checked = false;
                radio.disabled = false;
                const label = radio.closest('.ptgates-option-label');
                if (label) {
                    label.classList.remove('ptgates-correct-answer', 'ptgates-incorrect-answer');
                }
            });
            
            const textInput = document.getElementById('ptgates-user-answer');
            if (textInput) {
                textInput.value = '';
                textInput.disabled = false;
            }
        },
        
        /**
         * 결과 표시
         */
        showResult(stats) {
            const section = document.getElementById('ptgates-result-section');
            if (!section) return;
            
            // 통계 업데이트
            const accuracyEl = document.getElementById('ptgates-result-accuracy');
            const correctEl = document.getElementById('ptgates-result-correct');
            const incorrectEl = document.getElementById('ptgates-result-incorrect');
            const timeEl = document.getElementById('ptgates-result-time');
            
            if (accuracyEl) accuracyEl.textContent = stats.accuracy + '%';
            if (correctEl) correctEl.textContent = stats.correct + '개';
            if (incorrectEl) incorrectEl.textContent = stats.incorrect + '개';
            if (timeEl) timeEl.textContent = this.formatTime(stats.totalTime);
            
            section.style.display = 'block';
        },
        
        /**
         * 시간 포맷팅 (초 → MM:SS)
         */
        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
    };
    
    // 전역 객체에 노출
    window.PTGUI = PTGUI;
})();
