(function($) {
    'use strict';

    console.log('PTG Study script file is parsed and executed.');

    // 이 스크립트가 로드되었는지 확인용
    console.log('PTG Study script loaded.');

    let categoryMap = {};

    // 코스 목록을 렌더링할 컨테이너 (init 함수 내부로 이동)
    // const studyContainer = $('#ptg-study-app');

    const HTML_ENTITIES = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '`': '&#96;'
    };

    function escapeHtml(str) {
        // ?? 연산자는 구형 브라우저에서 호환성 문제가 있을 수 있으므로 변경
        return String(str !== null && str !== undefined ? str : '').replace(/[&<>"'`]/g, function(match) {
            return HTML_ENTITIES[match] || match;
        });
    }

    /**
     * 초기화 함수
     */
    function init() {
        console.log('PTG Study: init() function called.');
        
        const studyContainer = $('#ptg-study-app');
        console.log('PTG Study: Found container, length:', studyContainer.length);

        if (studyContainer.length === 0) {
            console.error('PTG Study: Container #ptg-study-app not found. Aborting.');
            return;
        }
        
        // 로딩 상태 표시
        studyContainer.html('<p>학습 카테고리를 불러오는 중...</p>');

        // 코스 목록 가져오기
        fetchCourses(studyContainer);
    }

    /**
     * API에서 코스(과목) 목록을 가져와서 렌더링
     */
    function fetchCourses(studyContainer) {
        // ptgStudy 객체와 rest_url이 정의되어 있는지 확인
        if (typeof ptgStudy === 'undefined' || typeof ptgStudy.rest_url === 'undefined') {
            console.error('ptgStudy object or REST URL is not defined.');
            studyContainer.html('<p>오류: API 설정이 올바르지 않습니다.</p>');
            return;
        }

        $.ajax({
            url: ptgStudy.rest_url + 'courses',
            method: 'GET',
            beforeSend: function(xhr) {
                // Nonce 헤더 추가
                xhr.setRequestHeader('X-WP-Nonce', ptgStudy.api_nonce);
            }
        }).done(function(courses) {
            renderCourses(studyContainer, courses || []);
        }).fail(function() {
            studyContainer.html('<p>카테고리 목록을 불러오는데 실패했습니다.</p>');
        });
    }

    /**
     * 코스 목록을 HTML로 렌더링
     * @param {jQuery} studyContainer 
     * @param {Array} courses 
     */
    function renderCourses(studyContainer, courses) {
        if (!Array.isArray(courses) || courses.length === 0) {
            studyContainer.html('<p>학습 가능한 과목이 없습니다.</p>');
            return;
        }

        categoryMap = {};

        let html = '<h2>🗝️학습할 과목을 선택하세요</h2><div class="ptg-course-categories">';
        courses.forEach(function(category) {
            categoryMap[category.id] = category;
            const categoryTitle = category.title || category.label || '';
            html += `
                <section class="ptg-category" data-category-id="${escapeHtml(category.id)}">
                    <header class="ptg-category-header">
                        <h3 class="ptg-category-title">${escapeHtml(categoryTitle)}</h3>
                        ${category.description ? `<p class="ptg-category-desc">${escapeHtml(category.description)}</p>` : ''}
                    </header>
                    ${renderSubjectList(category.subjects || [])}
                </section>
            `;
        });
        html += '</div>';

        studyContainer.html(html);

        // 카테고리 클릭 이벤트 (과목 클릭 시 중복 실행 방지)
        studyContainer.off('click', '.ptg-category');
        studyContainer.on('click', '.ptg-category', function(event) {
            console.log('PTG Study: category clicked', event.target);

            if ($(event.target).closest('.ptg-subject-item').length) {
                return;
            }

            const $categoryCard = $(this);
            const categoryId = $categoryCard.data('category-id');
            const category = categoryMap[categoryId];
            if (!category) {
                console.warn('PTG Study: category not found for id', categoryId, categoryMap);
                return;
            }

            $('.ptg-category').removeClass('ptg-category--active');
            $categoryCard.addClass('ptg-category--active');

            fetchAndRenderCategoryLessons(studyContainer, category);
        });

        // 과목 클릭 이벤트 바인딩 (중복 바인딩 방지)
        studyContainer.off('click', '.ptg-subject-item');
        studyContainer.on('click', '.ptg-subject-item', function(event) {
            event.stopPropagation();

            const subjectId = $(this).data('subject-id');
            if (!subjectId) {
                return;
            }
            const subjectLabel = $(this).text().trim();
            const categoryLabel = $(this).closest('.ptg-category').find('.ptg-category-title').text().trim();
            fetchAndRenderLessons(studyContainer, subjectId, subjectLabel, categoryLabel);
        });
    }

    function renderSubjectList(subjects) {
        if (!Array.isArray(subjects) || subjects.length === 0) {
            return '<p class="ptg-empty-subjects">준비 중인 과목입니다.</p>';
        }

        let listHtml = '<ul class="ptg-subject-list">';
        subjects.forEach(function(subject) {
            listHtml += `
                <li class="ptg-subject-item" data-subject-id="${escapeHtml(subject.id)}">
                    ${escapeHtml(subject.title)}
                </li>
            `;
        });
        listHtml += '</ul>';
        return listHtml;
    }

    /**
     * 특정 과목의 학습 내용(문제)을 가져와서 렌더링
     * @param {string} subjectId 
     * @param {string} subjectLabel
     * @param {string} categoryLabel
     */
    function fetchAndRenderLessons(studyContainer, subjectId, subjectLabel, categoryLabel) {
        const displayName = subjectLabel || decodeURIComponent(subjectId);
        studyContainer.html(`<p>${escapeHtml(displayName)} 과목의 학습 내용을 불러오는 중...</p>`);

        $.ajax({
            url: ptgStudy.rest_url + 'courses/' + subjectId,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgStudy.api_nonce);
            }
        }).done(function(courseDetail) {
            renderLessons(studyContainer, courseDetail, {
                subjectLabel: displayName,
                categoryLabel: categoryLabel,
                isCategory: false
            });
        }).fail(function() {
            studyContainer.html(`<p>${escapeHtml(displayName)} 과목의 학습 내용을 불러오는데 실패했습니다.</p>`);
        });
    }

    function fetchAndRenderCategoryLessons(studyContainer, category) {
        const categoryTitle = category.title || category.label || '';
        const subjects = Array.isArray(category.subjects) ? category.subjects : [];

        if (subjects.length === 0) {
            studyContainer.html(`<p>${escapeHtml(categoryTitle)} 분류에 등록된 과목이 없습니다.</p>`);
            return;
        }

        const subjectNames = subjects.map(function(subject) { return subject.title; });

        studyContainer.html(`<p>${escapeHtml(categoryTitle)} 분류의 학습 내용을 불러오는 중...</p>`);

        $.ajax({
            url: ptgStudy.rest_url + 'courses/' + category.id,
            method: 'GET',
            traditional: true,
            data: {
                subjects: subjectNames
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgStudy.api_nonce);
            }
        }).done(function(courseDetail) {
            renderLessons(studyContainer, courseDetail, {
                subjectLabel: categoryTitle,
                categoryLabel: categoryTitle,
                isCategory: true
            });
        }).fail(function() {
            studyContainer.html(`<p>${escapeHtml(categoryTitle)} 분류의 학습 내용을 불러오는데 실패했습니다.</p>`);
        });
    }

    /**
     * 학습 내용을 HTML로 렌더링
     * @param {jQuery} studyContainer
     * @param {Object} courseDetail 
     * @param {Object} meta
     */
    function renderLessons(studyContainer, courseDetail, meta) {
        const isCategory = meta && meta.isCategory;
        const subjectTitle = meta && meta.subjectLabel ? meta.subjectLabel : courseDetail.title;
        const categoryTitle = meta && meta.categoryLabel ? meta.categoryLabel : '';
        let heading;
        if (isCategory) {
            heading = `${categoryTitle || subjectTitle} 전체 학습`;
        } else {
            heading = categoryTitle ? `${categoryTitle} · ${subjectTitle}` : `${subjectTitle}`;
        }

        let html = `
            <div class="ptg-lesson-view">
                <button id="back-to-courses" class="ptg-btn ptg-btn-secondary">&laquo; 과목 목록으로 돌아가기</button>
                <h2>${escapeHtml(heading)}</h2>
        `;

        if (isCategory && Array.isArray(courseDetail.subjects) && courseDetail.subjects.length > 0) {
            const subjectList = courseDetail.subjects.map(function(subjectName) {
                return `<span class="ptg-lesson-subject-chip">${escapeHtml(subjectName)}</span>`;
            }).join('\n');
            html += `<div class="ptg-lesson-subjects">포함 과목: ${subjectList}</div>`;
        }

        html += '<div class="ptg-lesson-list">';

        if (!courseDetail.lessons || courseDetail.lessons.length === 0) {
            html += '<p>이 과목에는 학습할 내용이 아직 없습니다.</p>';
        } else {
            courseDetail.lessons.forEach(function(lesson, index) {
                const questionHtml = renderQuestionFromUI(lesson, index + 1);

                html += `
                    <div class="ptg-lesson-item ptg-quiz-card" data-lesson-id="${escapeHtml(lesson.id)}">
                        ${questionHtml}
                        <div class="ptg-lesson-answer-area">
                            <button class="toggle-answer ptg-btn ptg-btn-primary">정답 및 해설 보기</button>
                            <div class="answer-content" style="display: none;">
                                <p><strong>정답:</strong> ${escapeHtml(lesson.answer)}</p>
                                <hr>
                                <p><strong>해설:</strong></p>
                                <div>${lesson.explanation ? lesson.explanation : '해설이 없습니다.'}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        html += '</div></div>';
        studyContainer.html(html);

        // 이벤트 바인딩
        $('#back-to-courses').on('click', function() {
            fetchCourses(studyContainer);
        });
        $('.toggle-answer').on('click', function() {
            $(this).siblings('.answer-content').slideToggle();
        });
    }

    /**
     * quiz-ui.js의 기능을 활용하여 문제 HTML을 생성 (문자열 반환)
     * @param {object} lesson 
     */
    function renderQuestionFromUI(lesson, questionNumber) {
        if (typeof window.PTGQuizUI === 'undefined') {
            console.error('PTGQuizUI is not available.');
            return `<div class="ptg-question-text"><span class="ptg-question-number">${questionNumber}.</span> ${escapeHtml(lesson.content || '')}</div>`;
        }

        const questionText = lesson.content || '';
        const options = lesson.options || [];

        let html = `<div class="ptg-question-text"><span class="ptg-question-number">${questionNumber}.</span> ${escapeHtml(questionText)}</div>`;

        if (options.length > 0) {
            html += `<ul class="ptg-question-options">`;
            options.forEach((option) => {
                html += `<li class="ptg-question-option">${escapeHtml(option)}</li>`;
            });
            html += `</ul>`;
        }
        
        return html;
    }


    /*
    function formatQuestion(lesson) {
        let questionText = lesson.content || '';
        let optionsHtml = '';

        // 보기 추출 및 렌더링 (간단한 버전)
        const optionRegex = /([①-⑳]\s*.*?(?=[①-⑳]|$))/g;
        const matches = questionText.match(optionRegex);
        
        if (matches) {
            optionsHtml = '<ul class="ptg-options">';
            matches.forEach(option => {
                optionsHtml += `<li>${escapeHtml(option.trim())}</li>`;
                // 원본 텍스트에서 보기를 제거하여 문제 지문만 남김 (간단하게)
                questionText = questionText.replace(option, ''); 
            });
            optionsHtml += '</ul>';
        }

        return `
            <div class="ptg-question-text">${questionText.trim()}</div>
            ${optionsHtml}
        `;
    }
    */

    // DOM 로드 후 초기화
    $(document).ready(function() {
        console.log('PTG Study: Document is ready. Calling init().');
        init();
    });

})(jQuery);
