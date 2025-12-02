/**
 * PTGates Quiz - 툴바 기능 모듈
 * 
 * 툴바 버튼 이벤트, 북마크, 복습, 메모, 암기카드 기능
 */

(function() {
    'use strict';

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
     * 암기카드 버튼 강제 표시 및 순서 보장 함수 (전역 함수)
     */
    function ensureFlashcardButton() {
        const toolbarIcons = document.querySelector('.ptg-toolbar-icons');
        if (!toolbarIcons) return;
        
        // 버튼 순서 재정렬: 북마크, 복습, 메모, 암기카드, 드로잉
        const bookmarkBtn = toolbarIcons.querySelector('.ptg-btn-bookmark');
        const reviewBtn = toolbarIcons.querySelector('.ptg-btn-review');
        const notesBtn = toolbarIcons.querySelector('.ptg-btn-notes');
        const flashcardBtn = toolbarIcons.querySelector('.ptg-btn-flashcard');
        const drawingBtn = toolbarIcons.querySelector('.ptg-btn-drawing');
        
        // 모든 버튼을 제거하고 올바른 순서로 다시 추가
        if (bookmarkBtn && reviewBtn && notesBtn && drawingBtn) {
            // 임시 저장
            const buttons = [bookmarkBtn, reviewBtn, notesBtn];
            if (flashcardBtn) {
                buttons.push(flashcardBtn);
            } else {
                // 암기카드 버튼이 없으면 생성
                const newFlashcardBtn = document.createElement('button');
                newFlashcardBtn.type = 'button';
                newFlashcardBtn.className = 'ptg-btn-icon ptg-btn-flashcard';
                newFlashcardBtn.setAttribute('aria-label', '암기카드 생성');
                newFlashcardBtn.setAttribute('title', '암기카드 생성');
                newFlashcardBtn.innerHTML = '<span class="ptg-icon">🗂️</span>';
                newFlashcardBtn.addEventListener('click', showFlashcardModal);
                buttons.push(newFlashcardBtn);
            }
            buttons.push(drawingBtn);
            
            // 기존 버튼들 제거
            buttons.forEach(btn => {
                if (btn.parentNode === toolbarIcons) {
                    toolbarIcons.removeChild(btn);
                }
            });
            
            // 올바른 순서로 추가
            buttons.forEach(btn => {
                toolbarIcons.appendChild(btn);
            });
            
            // 이벤트 핸들러 재등록 (필요한 경우)
            if (!flashcardBtn) {
                const newBtn = toolbarIcons.querySelector('.ptg-btn-flashcard');
                if (newBtn && !newBtn.hasAttribute('data-event-bound')) {
                    newBtn.addEventListener('click', showFlashcardModal);
                    newBtn.setAttribute('data-event-bound', 'true');
                }
            }
            
            // 표시 보장
            const finalFlashcardBtn = toolbarIcons.querySelector('.ptg-btn-flashcard');
            if (finalFlashcardBtn) {
                finalFlashcardBtn.style.display = '';
                finalFlashcardBtn.style.visibility = 'visible';
                finalFlashcardBtn.style.opacity = '1';
                finalFlashcardBtn.style.width = '';
                finalFlashcardBtn.style.height = '';
                finalFlashcardBtn.style.padding = '';
            }
        }
    }

    /**
     * HTML을 텍스트로 변환 (줄바꿈 유지)
     */
    function htmlToText(html) {
        if (!html) return '';
        const div = document.createElement('div');
        div.innerHTML = html;
        // Replace <br> with newline
        div.querySelectorAll('br').forEach(br => {
            br.replaceWith('\n');
        });
        return div.textContent || div.innerText || '';
    }

    /**
     * HTML을 텍스트로 변환 (줄바꿈 유지) - 암기카드용
     */
    function htmlToTextForFlashcard(html) {
        if (!html) return '';
        const div = document.createElement('div');
        div.innerHTML = html;
        // Replace <br> with newline
        div.querySelectorAll('br').forEach(br => {
            br.replaceWith('\n');
        });
        return div.textContent || div.innerText || '';
    }

    /**
     * 북마크 토글
     */
    async function toggleBookmark() {
        const btn = document.querySelector('.ptg-btn-bookmark');
        if (!btn) return;

        const isBookmarked = btn.classList.contains('active');

        try {
            await window.PTGPlatform.patch(`ptg-quiz/v1/questions/${window.PTGQuiz?.QuizState.questionId}/state`, {
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
            if (typeof showError === 'function') {
                showError('북마크 업데이트에 실패했습니다.');
            }
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
            await window.PTGPlatform.patch(`ptg-quiz/v1/questions/${window.PTGQuiz?.QuizState.questionId}/state`, {
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
            if (typeof showError === 'function') {
                showError('복습 필요 업데이트에 실패했습니다.');
            }
        }
    }

    /**
     * 메모 저장
     */
    async function saveNote() {
        if (!window.PTGQuiz?.QuizState.questionId) return;

        const textarea = document.getElementById('ptg-notes-textarea');
        if (!textarea) return;

        const content = textarea.value.trim();

        try {
            await window.PTGPlatform.post(`ptg-quiz/v1/questions/${window.PTGQuiz?.QuizState.questionId}/memo`, {
                content: content
            });

            // 저장 후 활성화 상태 업데이트
            updateNotesButtonState();

            // 사용자 상태 업데이트
            if (window.PTGQuiz?.QuizState?.userState) {
                window.PTGQuiz.QuizState.userState.note = content;
            }
        } catch (error) {
            console.error('메모 저장 오류:', error);
            // 저장 실패해도 UI는 업데이트 (로컬 상태 유지)
            updateNotesButtonState();
        }
    }

    /**
     * 메모 버튼 활성화 상태 업데이트
     */
    function updateNotesButtonState() {
        const btnNotes = document.querySelector('.ptg-btn-notes');
        const textarea = document.getElementById('ptg-notes-textarea');
        
        if (!btnNotes || !textarea) return;

        const hasContent = textarea.value.trim().length > 0;
        
        if (hasContent) {
            btnNotes.classList.add('active');
        } else {
            btnNotes.classList.remove('active');
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

            // 헤더 위치로 스크롤
            setTimeout(() => {
                scrollToHeader();
            }, 100);
        }

        // 메모 내용에 따라 활성화 상태 업데이트 (패널 표시 여부와 무관)
        updateNotesButtonState();
    }

    /**
     * 암기카드 모달 표시
     */
    async function showFlashcardModal() {
        const questionId = window.PTGQuiz?.QuizState.questionId;
        if (!questionId) {
            return;
        }

        // Helper function to convert HTML to text while preserving line breaks
        function htmlToTextHelper($element) {
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
            
            const cardsResponse = await window.PTGPlatform.get('ptg-flash/v1/cards', params);
            
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

        // DB 데이터가 없으면 window.PTGQuiz?.QuizState.questionData에서 추출
        if (!hasDbData) {
            // 앞면: 지문과 선택지를 window.PTGQuiz?.QuizState.questionData에서 가져오기
            if (window.PTGQuiz?.QuizState.questionData) {
                // 지문 추가 (질문 시작 부분에 ID 추가)
                const questionIdPrefix = '(id-' + window.PTGQuiz?.QuizState.questionId + ') ';
                if (window.PTGQuiz?.QuizState.questionData.question_text) {
                    frontText = questionIdPrefix + window.PTGQuiz?.QuizState.questionData.question_text.trim();
                } else if (window.PTGQuiz?.QuizState.questionData.content) {
                    frontText = questionIdPrefix + window.PTGQuiz?.QuizState.questionData.content.trim();
                }
                
                // 선택지 추가
                if (window.PTGQuiz?.QuizState.questionData.options && Array.isArray(window.PTGQuiz?.QuizState.questionData.options) && window.PTGQuiz?.QuizState.questionData.options.length > 0) {
                    window.PTGQuiz?.QuizState.questionData.options.forEach((option, index) => {
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
                if (window.PTGQuiz?.QuizState.questionData.answer) {
                    backText = '정답: ' + window.PTGQuiz?.QuizState.questionData.answer;
                }
                
                // 해설 추가
                if (window.PTGQuiz?.QuizState.questionData.explanation) {
                    if (backText) {
                        backText += '\n\n';
                    }
                    backText += htmlToTextForFlashcard(window.PTGQuiz?.QuizState.questionData.explanation);
                }
            } else {
                // window.PTGQuiz?.QuizState.questionData가 없으면 DOM에서 추출 (fallback)
                const card = document.getElementById('ptg-quiz-card');
                
                if (card) {
                    // Get question text (질문 시작 부분에 ID 추가)
                    const questionEl = card.querySelector('.ptg-question-text, .ptg-question-content');
                    if (questionEl) {
                        const questionIdPrefix = '(id-' + window.PTGQuiz?.QuizState.questionId + ') ';
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
                    saveFlashcard();
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

    /**
     * 암기카드 저장
     */
    async function saveFlashcard() {
        const modal = document.getElementById('ptg-quiz-flashcard-modal');
        if (!modal) return;

        const frontTextarea = document.getElementById('ptg-flashcard-front');
        const backTextarea = document.getElementById('ptg-flashcard-back');
        const statusEl = modal.querySelector('.ptg-flashcard-status');
        const questionId = modal.getAttribute('data-question-id');

        if (!frontTextarea || !backTextarea || !questionId) return;

        const frontText = frontTextarea.value.trim();
        const backText = backTextarea.value.trim();

        // Validate input
        if (!frontText || !backText) {
            if (statusEl) {
                statusEl.textContent = '✗ 앞면과 뒷면 내용을 모두 입력해주세요';
                statusEl.style.color = '#ef4444';
            }
            return;
        }

        if (!questionId) {
            if (statusEl) {
                statusEl.textContent = '✗ 문제 ID를 찾을 수 없습니다';
                statusEl.style.color = '#ef4444';
            }
            return;
        }

        if (statusEl) {
            statusEl.textContent = '세트 정보 확인 중...';
            statusEl.style.color = '#666';
        }

        try {
            // First, get the user's default set_id
            const setsResponse = await window.PTGPlatform.get('ptg-flash/v1/sets');
            const sets = setsResponse.data || setsResponse;
            const setId = (sets && Array.isArray(sets) && sets.length > 0) ? sets[0].set_id : 1;
            
            if (statusEl) {
                statusEl.textContent = '저장 중...';
                statusEl.style.color = '#666';
            }
            
            // Now create the flashcard
            const payload = {
                set_id: setId,
                source_type: 'question',
                source_id: parseInt(questionId),
                front: frontText,
                back: backText
            };

            // Add subject if available for auto-set creation
            if (window.PTGQuiz?.QuizState?.questionData?.category?.subject) {
                payload.subject = window.PTGQuiz.QuizState.questionData.category.subject;
            } else if (window.PTGQuiz?.QuizState?.questionData?.subject) {
                payload.subject = window.PTGQuiz.QuizState.questionData.subject;
            }

            console.log('[PTG Quiz Toolbar] Flashcard payload:', payload);
            console.log('[PTG Quiz Toolbar] QuizState:', window.PTGQuiz?.QuizState);

            const response = await window.PTGPlatform.post('ptg-flash/v1/cards', payload);

            if (statusEl) {
                statusEl.textContent = '✓ 저장되었습니다';
                statusEl.style.color = '#10b981';
            }
            
            // Update toolbar icon status
            const btnFlashcard = document.querySelector('.ptg-btn-flashcard');
            if (btnFlashcard) {
                btnFlashcard.classList.add('is-active');
            }

            // 1.5초 후 모달 닫기
            setTimeout(() => {
                modal.style.display = 'none';
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.style.color = '#666';
                }
            }, 1000);

        } catch (error) {
            console.error('[PTG Quiz] 암기카드 저장 실패:', error);
            
            let errorMsg = '✗ 저장 실패';
            if (error.response && error.response.message) {
                errorMsg += ': ' + error.response.message;
            } else if (error.status === 404) {
                errorMsg += ': API 없음';
            } else if (error.status === 401 || error.status === 403) {
                errorMsg += ': 권한 없음';
            }
            
            if (statusEl) {
                statusEl.textContent = errorMsg;
                statusEl.style.color = '#ef4444';
            }
        }
    }

    /**
     * 툴바 이벤트 설정
     */
    function setupToolbarEvents() {
        const container = document.getElementById('ptg-quiz-container');
        if (!container) return;

        // QuizState 참조 가져오기 (안전한 접근 - 정의되지 않았을 수 있으므로 모든 참조에서 안전하게 체크)

        // 툴바 전체에 이벤트 위임으로 클릭 이벤트 추가 (모든 버튼 클릭 시 헤더로 스크롤)
        const toolbar = document.querySelector('.ptg-quiz-toolbar');
        if (toolbar) {
            toolbar.addEventListener('click', function (e) {
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
        container.addEventListener('click', function (e) {
            const target = e.target.closest('.ptg-btn-notes, .ptg-btn-drawing');
            if (!target) return;

            e.preventDefault();
            e.stopPropagation();

            if (target.classList.contains('ptg-btn-notes')) {
                toggleNotesPanel();
            } else if (target.classList.contains('ptg-btn-drawing')) {
                // 모바일에서는 드로잉 기능 비활성화
                const currentQuizState = window.PTGQuiz?.QuizState;
                if (currentQuizState && currentQuizState.deviceType !== 'mobile') {
                    if (window.PTGQuizDrawing && window.PTGQuizDrawing.toggleDrawing) {
                        window.PTGQuizDrawing.toggleDrawing();
                    }
                }
            }
        });

        // 북마크 버튼
        const btnBookmark = document.querySelector('.ptg-btn-bookmark');
        if (btnBookmark) {
            // study-toolbar.js와 호환성을 위해 data-question-id 설정 (초기값)
            const currentQuizState = window.PTGQuiz?.QuizState;
            if (currentQuizState && currentQuizState.questionId) {
                btnBookmark.setAttribute('data-question-id', currentQuizState.questionId);
            }
            btnBookmark.addEventListener('click', toggleBookmark);
        }

        // 복습 필요 버튼
        const btnReview = document.querySelector('.ptg-btn-review');
        if (btnReview) {
            // study-toolbar.js와 호환성을 위해 data-question-id 설정 (초기값)
            const currentQuizState = window.PTGQuiz?.QuizState;
            if (currentQuizState && currentQuizState.questionId) {
                btnReview.setAttribute('data-question-id', currentQuizState.questionId);
            }
            btnReview.addEventListener('click', toggleReview);
        }

        // 메모 자동 저장 이벤트 리스너
        const notesTextarea = document.getElementById('ptg-notes-textarea');
        if (notesTextarea) {
            // 디바운스된 저장 함수
            let saveTimeout = null;
            notesTextarea.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    saveNote();
                }, 1000); // 1초 디바운스

                // 메모 내용에 따라 활성화 상태 업데이트
                updateNotesButtonState();
            });

            // blur 시 즉시 저장
            notesTextarea.addEventListener('blur', function() {
                clearTimeout(saveTimeout);
                saveNote();
            });
        }

        // 암기카드 버튼
        const btnFlashcard = document.querySelector('.ptg-btn-flashcard');
        if (btnFlashcard) {
            // study-toolbar.js 이벤트를 먼저 제거 (jQuery 이벤트)
            if (typeof $ !== 'undefined') {
                $(btnFlashcard).off('click');
            }
            
            // quiz.js 이벤트를 capture phase에서 먼저 바인딩
            btnFlashcard.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation(); // 다른 모든 이벤트 핸들러 차단
                showFlashcardModal();
            }, true); // capture phase
        }

        // 암기카드 버튼 강제 표시 및 순서 보장 (전역 함수 사용)
        // 초기 확인 1회만 (MutationObserver가 있으므로 중복 호출 불필요)
        setTimeout(ensureFlashcardButton, 300);
        
        // MutationObserver로 버튼 제거 감지 및 복구
        const toolbarIcons = document.querySelector('.ptg-toolbar-icons');
        if (toolbarIcons) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        const flashcardBtn = toolbarIcons.querySelector('.ptg-btn-flashcard');
                        if (!flashcardBtn) {
                            setTimeout(ensureFlashcardButton, 100);
                        }
                    }
                });
            });
            
            observer.observe(toolbarIcons, {
                childList: true,
                subtree: false
            });
        }
    }

    // 전역으로 함수 노출 (quiz.js에서 사용)
    if (typeof window !== 'undefined') {
        window.PTGQuizToolbar = {
            scrollToHeader: scrollToHeader,
            scrollToToolbar: scrollToToolbar,
            ensureFlashcardButton: ensureFlashcardButton,
            setupToolbarEvents: setupToolbarEvents,
            toggleBookmark: toggleBookmark,
            toggleReview: toggleReview,
            toggleNotesPanel: toggleNotesPanel,
            saveNote: saveNote,
            updateNotesButtonState: updateNotesButtonState,
            showFlashcardModal: showFlashcardModal,
            saveFlashcard: saveFlashcard,
            htmlToText: htmlToText,
            htmlToTextForFlashcard: htmlToTextForFlashcard
        };
    }

})();

