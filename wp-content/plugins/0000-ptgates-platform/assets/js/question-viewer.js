/**
 * PTGates Question Viewer Component
 * 
 * 1100 study의 문제 보기 기능을 재사용 가능한 컴포넌트로 제공
 * 모든 플러그인에서 동일한 스타일과 기능으로 문제를 표시할 수 있도록 함
 * 
 * @requires jQuery
 * @requires PTGStudyToolbar (optional, for toolbar functionality)
 */

(function($) {
    'use strict';

    // HTML 엔티티 변환
    const HTML_ENTITIES = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '`': '&#96;'
    };

    /**
     * HTML 이스케이프
     */
    function escapeHtml(str) {
        return String(str !== null && str !== undefined ? str : '').replace(/[&<>"'`]/g, function(match) {
            return HTML_ENTITIES[match] || match;
        });
    }

    /**
     * 원형 숫자 변환 (1→①, 2→②, ...)
     */
    function getCircledNumber(n) {
        const circled = ['①','②','③','④','⑤','⑥','⑦','⑧','⑨','⑩','⑪','⑫','⑬','⑭','⑮','⑯','⑰','⑱','⑲','⑳'];
        return circled[(n - 1) % circled.length] || '';
    }

    /**
     * 설명 텍스트 포맷팅
     * "(정답 해설):" / "(오답 해설):" 구분이 있으면 줄바꿈 추가
     */
    function formatExplanationText(explanationRaw) {
        if (!explanationRaw) return '';
        var text = String(explanationRaw);
        text = text.replace(/\r\n/g, '\n');
        text = text.replace(/(?!^)\(정답 해설\)\s*:/g, '<br>(정답 해설):');
        text = text.replace(/(?!^)\(오답 해설\)\s*:/g, '<br>(오답 해설):');
        text = text.replace(/\n/g, '<br>');
        return text;
    }

    /**
     * 기본 포맷팅 (보기가 별도 배열로 없는 경우)
     */
    function renderBasicFormatted(lessonData, questionNumber) {
        const rawText = String(lessonData.content || '');
        const normalized = rawText.replace(/\r\n/g, '\n');

        // 1) circled numbers ①-⑳
        const circledRegex = /([①-⑳])\s*([^①-⑳]*)/g;
        // 2) numeric 1) or 1. or 1:
        const numericRegex = /(?:^|\s)([1-9])[\)\.\:]\s*([^\n]*)/g;

        let options = [];
        let stem = normalized;

        // Try circled pattern first
        let circledMatches = [];
        let m;
        while ((m = circledRegex.exec(normalized)) !== null) {
            circledMatches.push({ mark: m[1], text: (m[2] || '').trim() });
        }

        if (circledMatches.length >= 2) {
            options = circledMatches.map(x => x.text).filter(Boolean);
            const firstIdx = normalized.search(/[①-⑳]/);
            stem = firstIdx > -1 ? normalized.slice(0, firstIdx).trim() : normalized.trim();
        } else {
            // Fallback to numeric pattern
            let numericMatches = [];
            while ((m = numericRegex.exec(normalized)) !== null) {
                numericMatches.push({ num: m[1], text: (m[2] || '').trim() });
            }
            if (numericMatches.length >= 2) {
                numericMatches.sort((a, b) => parseInt(a.num, 10) - parseInt(b.num, 10));
                options = numericMatches.map(x => x.text).filter(Boolean);
                const firstIdx2 = normalized.search(/[1-9][\)\.\:]/);
                stem = firstIdx2 > -1 ? normalized.slice(0, firstIdx2).trim() : normalized.trim();
            }
        }

        // Convert \n in stem to <br>
        const stemHtml = escapeHtml(stem).replace(/\n/g, '<br>');

        let html = `<div class="ptg-question-text"><span class="ptg-question-number">${questionNumber}.</span> ${stemHtml}</div>`;
        if (options.length > 0) {
            html += `<ul class="ptg-question-options">`;
            options.forEach((opt, idx) => {
                const mark = getCircledNumber(idx + 1);
                const trimmedOpt = String(opt || '').trim();
                html += `<li class="ptg-question-option"><span class="ptg-option-index">${mark}</span>${escapeHtml(trimmedOpt)}</li>`;
            });
            html += `</ul>`;
        }
        return html;
    }

    /**
     * PTGates Question Viewer 전역 객체
     */
    window.PTGQuestionViewer = window.PTGQuestionViewer || {
        
        /**
         * 단일 문제 HTML 렌더링
         * @param {Object} lesson - 문제 데이터
         * @param {number} questionNumber - 문제 번호
         * @returns {string} HTML 문자열
         */
        renderQuestion: function(lesson, questionNumber) {
            // 우선 공용 UI가 있으면 그대로 사용하되, 옵션 배열이 없을 땐 파싱 폴백
            if (typeof window.PTGQuizUI === 'undefined') {
                return renderBasicFormatted(lesson, questionNumber);
            }

            const questionText = lesson.content || '';
            const options = Array.isArray(lesson.options) ? lesson.options : [];

            if (options.length === 0) {
                return renderBasicFormatted(lesson, questionNumber);
            }

            // 기본: 지문 줄바꿈 보존 + 배열 보기를 줄바꿈 리스트로
            const stemHtml = escapeHtml(questionText).replace(/\r?\n/g, '<br>');
            let html = `<div class="ptg-question-text"><span class="ptg-question-number">${questionNumber}.</span> ${stemHtml}</div>`;
            html += `<ul class="ptg-question-options">`;
            options.forEach((option, idx) => {
                const mark = getCircledNumber(idx + 1);
                const trimmedOption = String(option || '').trim();
                html += `<li class="ptg-question-option"><span class="ptg-option-index">${mark}</span>${escapeHtml(trimmedOption)}</li>`;
            });
            html += `</ul>`;
            return html;
        },

        /**
         * 문제 카드 전체 렌더링 (툴바 포함)
         * @param {Object} lesson - 문제 데이터
         * @param {number} questionNumber - 문제 번호
         * @param {Object} options - 옵션
         *   - showToolbar: {boolean} 툴바 표시 여부 (기본: true)
         *   - showMemo: {boolean} 메모 영역 표시 여부 (기본: false)
         *   - memoContent: {string} 메모 내용
         *   - explanationSubject: {string} 해설에 표시할 과목명
         * @returns {string} HTML 문자열
         */
        renderQuestionCard: function(lesson, questionNumber, options = {}) {
            const showToolbar = options.showToolbar !== false;
            const showMemo = options.showMemo === true;
            const memoContent = options.memoContent || '';
            const explanationSubject = options.explanationSubject || (lesson.category && lesson.category.subject) || '';

            // 문제 HTML 렌더링
            const questionHtml = this.renderQuestion(lesson, questionNumber);

            // 이미지 URL 구성
            let imageUrl = '';
            if (lesson.question_image && lesson.category) {
                const year = lesson.category.year || '';
                const session = lesson.category.session || '';
                if (year && session) {
                    imageUrl = `/wp-content/uploads/ptgates-questions/${year}/${session}/${lesson.question_image}`;
                }
            }

            // 툴바 버튼 컨테이너 (툴바가 활성화된 경우)
            let toolbarHtml = '';
            if (showToolbar) {
                toolbarHtml = `
                    <div class="ptg-answer-buttons-container">
                        <button class="toggle-answer ptg-btn ptg-btn-primary">정답 및 해설 보기</button>
                        ${imageUrl ? '<button class="toggle-answer-img ptg-btn ptg-btn-primary">학습 이미지</button>' : ''}
                        <button class="ptg-contextual-action-btn" data-question-id="${escapeHtml(lesson.id)}" title="도구 메뉴" aria-label="문제 도구 메뉴 열기">⋮</button>
                    </div>
                    <div class="ptg-question-toolbar" style="display: none;">
                        <div class="ptg-toolbar-icons">
                            <button class="ptg-toolbar-btn ptg-btn-bookmark" data-action="bookmark" data-question-id="${escapeHtml(lesson.id)}" title="북마크">
                                <span class="ptg-toolbar-icon">🔖</span>
                            </button>
                            <button class="ptg-toolbar-btn ptg-btn-review" data-action="review" data-question-id="${escapeHtml(lesson.id)}" title="복습 표시">
                                <span class="ptg-toolbar-icon">🔁</span>
                            </button>
                            <button class="ptg-toolbar-btn ptg-btn-notes" data-action="memo" data-question-id="${escapeHtml(lesson.id)}" title="메모">
                                <span class="ptg-toolbar-icon">📝</span>
                            </button>
                            <button class="ptg-toolbar-btn ptg-btn-flashcard" data-action="flashcard" data-question-id="${escapeHtml(lesson.id)}" title="암기카드">
                                <span class="ptg-toolbar-icon">🗂️</span>
                            </button>
                        </div>
                    </div>
                `;
            } else {
                // 툴바 없이 버튼만
                toolbarHtml = `
                    <div class="ptg-answer-buttons-container">
                        <button class="toggle-answer ptg-btn ptg-btn-primary">정답 및 해설 보기</button>
                        ${imageUrl ? '<button class="toggle-answer-img ptg-btn ptg-btn-primary">학습 이미지</button>' : ''}
                    </div>
                `;
            }

            // 메모 영역
            let memoHtml = '';
            if (showMemo && memoContent) {
                memoHtml = `
                    <div class="ptg-mynote-memo-display" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px; border-left: 4px solid #4299e1;">
                        <h4>메모</h4>
                        <div class="ptg-memo-content">${escapeHtml(memoContent)}</div>
                    </div>
                `;
            }

            // 전체 카드 HTML
            const html = `
                <div class="ptg-lesson-item ptg-quiz-card" data-lesson-id="${escapeHtml(lesson.id)}">
                    ${questionHtml}
                    <div class="ptg-lesson-answer-area">
                        ${toolbarHtml}
                        <div class="answer-content" style="display: none;">
                            <p><strong>정답:</strong> ${escapeHtml(lesson.answer)}</p>
                            <hr>
                            <p><strong>해설 (${escapeHtml(explanationSubject)}) - quiz-ID: ${escapeHtml(lesson.id)}</strong></p>
                            <div>${lesson.explanation ? formatExplanationText(lesson.explanation) : '해설이 없습니다.'}</div>
                        </div>
                        ${imageUrl ? `<div class="question-image-content" style="display: none;"><img src="${imageUrl}" alt="문제 이미지" style="max-width: 100%; height: auto;" /></div>` : ''}
                    </div>
                    ${memoHtml}
                </div>
            `;

            return html;
        },

        /**
         * 여러 문제 목록 렌더링
         * @param {Array} lessons - 문제 배열
         * @param {Object} meta - 메타 정보
         * @param {Object} options - 옵션
         * @returns {string} HTML 문자열
         */
        renderQuestionList: function(lessons, meta, options = {}) {
            const isCategory = meta && meta.isCategory;
            const subjectTitle = meta && meta.subjectLabel ? meta.subjectLabel : (meta.title || '');
            const categoryTitle = meta && meta.categoryLabel ? meta.categoryLabel : '';
            const currentOffset = typeof meta.offset === 'number' ? meta.offset : 0;
            const pageSize = typeof meta.limit === 'number' ? meta.limit : 0;
            const totalCount = typeof meta.total === 'number' ? meta.total : null;
            const enablePaging = pageSize > 0;

            let heading;
            if (isCategory) {
                heading = `${categoryTitle || subjectTitle} 전체 학습`;
            } else {
                heading = categoryTitle ? `${categoryTitle} · ${subjectTitle}` : `${subjectTitle}`;
            }

            let html = `
                <div class="ptg-lesson-view">
                    <div class="ptg-lesson-header" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <h3 style="margin: 0;">${escapeHtml(heading)}</h3>
                    </div>
            `;

            if (isCategory && Array.isArray(meta.subjects) && meta.subjects.length > 0) {
                const subjectList = meta.subjects.map(function(subjectName) {
                    return `<span class="ptg-lesson-subject-chip">${escapeHtml(subjectName)}</span>`;
                }).join('\n');
                html += `<div class="ptg-lesson-subjects">포함 과목: ${subjectList}</div>`;
            }

            html += '<div class="ptg-lesson-list">';

            if (Array.isArray(lessons) && lessons.length > 0) {
                lessons.forEach(function(lesson, index) {
                    const questionNumber = currentOffset + index + 1;
                    const explanationSubject = (lesson.category && lesson.category.subject) || subjectTitle;
                    html += this.renderQuestionCard(lesson, questionNumber, {
                        showToolbar: options.showToolbar !== false,
                        explanationSubject: explanationSubject
                    });
                }.bind(this));
            } else {
                html += '<div class="ptg-empty">문제가 없습니다.</div>';
            }

            html += '</div>';

            // 페이지네이션
            if (enablePaging && totalCount !== null) {
                const startIndex = currentOffset + 1;
                const endIndex = currentOffset + lessons.length;
                html += `
                    <div class="ptg-lesson-pagination">
                        <div class="ptg-lesson-page-info">${startIndex}-${endIndex} / 총 ${totalCount}문제</div>
                    </div>
                `;
            }

            html += '</div>';

            return html;
        },

        /**
         * 이벤트 핸들러 초기화
         * @param {jQuery} $container - 컨테이너 jQuery 객체
         */
        initEventHandlers: function($container) {
            // 정답 및 해설 보기 버튼
            $container.off('click', '.toggle-answer').on('click', '.toggle-answer', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.ptg-lesson-answer-area').find('.answer-content').slideToggle();
            });

            // 학습 이미지 버튼
            $container.off('click', '.toggle-answer-img').on('click', '.toggle-answer-img', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.ptg-lesson-answer-area').find('.question-image-content').slideToggle();
            });
        },

        /**
         * 툴바 초기화 (1100 study 툴바 사용)
         * @param {jQuery} $container - 컨테이너 jQuery 객체
         */
        initToolbars: function($container) {
            if (typeof window.PTGStudyToolbar !== 'undefined' && window.PTGStudyToolbar.initToolbars) {
                // 1100 study 툴바가 있으면 사용
                window.PTGStudyToolbar.initToolbars();
            }
        },

        /**
         * 유틸리티 함수 노출
         */
        utils: {
            escapeHtml: escapeHtml,
            formatExplanationText: formatExplanationText,
            getCircledNumber: getCircledNumber
        }
    };

})(jQuery);

