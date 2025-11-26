/**
 * PTGates Learning Engine - Timer Module
 * 
 * 전체 시간 및 문제당 시간 카운트다운 처리
 */

(function() {
    'use strict';
    
    let totalTimerInterval = null;
    let questionTimerInterval = null;
    let totalSeconds = 0;
    let remainingSeconds = 0;
    let questionSeconds = 0;
    let questionTimeLimit = null; // 문제당 제한 시간 (초), null이면 제한 없음
    let totalTimeLimit = null; // 전체 제한 시간 (초), null이면 제한 없음 (카운트업)
    
    const PTGTimer = {
        /**
         * 타이머 시작
         */
        start(limitSeconds = null) {
            this.reset(limitSeconds);
            this.startTotalTimer(limitSeconds);
            this.startQuestionTimer();
        },
        
        /**
         * 타이머 정지
         */
        stop() {
            this.stopTotalTimer();
            this.stopQuestionTimer();
        },
        
        /**
         * 타이머 리셋
         */
        reset(limitSeconds = null) {
            totalSeconds = 0;
            remainingSeconds = 0;
            questionSeconds = 0;
            totalTimeLimit = (typeof limitSeconds === 'number' && limitSeconds > 0) ? limitSeconds : null;
            if (totalTimeLimit) {
                remainingSeconds = totalTimeLimit;
            }
            this.updateDisplay();
        },
        
        /**
         * 전체 시간 타이머 시작
         */
        startTotalTimer(limitSeconds = null) {
            this.stopTotalTimer();
            
            // 제한시간이 있으면 카운트다운, 없으면 카운트업
            if (totalTimeLimit) {
                totalTimerInterval = setInterval(() => {
                    remainingSeconds = Math.max(remainingSeconds - 1, 0);
                    this.updateDisplay();
                    if (remainingSeconds <= 0) {
                        this.onTotalTimeLimitReached();
                    }
                }, 1000);
            } else {
                totalTimerInterval = setInterval(() => {
                    totalSeconds++;
                    this.updateDisplay();
                }, 1000);
            }
        },
        
        /**
         * 전체 시간 타이머 정지
         */
        stopTotalTimer() {
            if (totalTimerInterval) {
                clearInterval(totalTimerInterval);
                totalTimerInterval = null;
            }
        },
        
        /**
         * 문제 타이머 시작
         */
        startQuestionTimer(timeLimit = null) {
            this.stopQuestionTimer();
            
            questionSeconds = 0;
            questionTimeLimit = timeLimit;
            this.updateQuestionTimer();
            
            questionTimerInterval = setInterval(() => {
                questionSeconds++;
                this.updateQuestionTimer();
                
                // 제한 시간 체크
                if (questionTimeLimit && questionSeconds >= questionTimeLimit) {
                    this.onQuestionTimeLimitReached();
                }
            }, 1000);
        },
        
        /**
         * 문제 타이머 정지
         */
        stopQuestionTimer() {
            if (questionTimerInterval) {
                clearInterval(questionTimerInterval);
                questionTimerInterval = null;
            }
        },
        
        /**
         * 문제 타이머 리셋
         */
        resetQuestionTimer() {
            questionSeconds = 0;
            this.updateQuestionTimer();
        },
        
        /**
         * 표시 업데이트
         */
        updateDisplay() {
            const timerEl = document.getElementById('ptgates-timer');
            if (timerEl) {
                const secondsToShow = totalTimeLimit ? remainingSeconds : totalSeconds;
                timerEl.textContent = this.formatTime(secondsToShow);
            }
        },
        
        /**
         * 문제 타이머 표시 업데이트 (필요시)
         */
        updateQuestionTimer() {
            // 문제별 타이머가 별도로 표시되어야 하는 경우 여기에 구현
            // 현재는 전체 타이머만 표시
        },
        
        /**
         * 제한 시간 도달 시 처리
         */
        onQuestionTimeLimitReached() {
            this.stopQuestionTimer();
            
            // 자동으로 답안 제출
            if (typeof PTGMain !== 'undefined' && PTGMain.submitAnswer) {
                // 답을 선택하지 않았으면 빈 답으로 처리
                if (typeof PTGUI !== 'undefined' && !PTGUI.getSelectedAnswer()) {
                    alert('시간이 초과되었습니다.');
                }
                PTGMain.submitAnswer();
            }
        },

        /**
         * 전체 제한 시간 종료 시 처리
         */
        onTotalTimeLimitReached() {
            this.stopTotalTimer();
            this.stopQuestionTimer();
            if (typeof PTGMain !== 'undefined' && PTGMain.finishQuiz) {
                alert('제한 시간이 종료되었습니다.');
                PTGMain.finishQuiz();
            }
        },
        
        /**
         * 시간 포맷팅 (초 → MM:SS)
         */
        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },
        
        /**
         * 현재 전체 시간 가져오기 (초)
         */
        getTotalTime() {
            return totalSeconds;
        },
        
        /**
         * 현재 문제 시간 가져오기 (초)
         */
        getQuestionTime() {
            return questionSeconds;
        }
    };
    
    // 전역 객체에 노출
    window.PTGTimer = PTGTimer;
})();
