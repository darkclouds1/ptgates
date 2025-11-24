(function($) {
    'use strict';

    // 전역 디버그 플래그(기본 off). 필요 시 콘솔에서 window.PTG_STUDY_DEBUG=true로 켜서 상세 로그 확인.
    let PTG_STUDY_DEBUG = false;
    // sessionStorage를 사용하여 페이지 세션 동안 로그된 question_id 추적
    const STORAGE_KEY = 'ptg_study_logged_questions';

    let categoryMap = {};
    let initialCoursesHTML = null;

	// 교시/과목/세부과목 정의(1200-ptgates-quiz/includes/class-subjects.php :: Subjects::MAP를 반영)
	// 클라이언트에서 카드 렌더링을 위해 필요한 최소 구조만 추출
	const PTG_SUBJECTS_FROM_MAP = [
		{
			id: 'ptg-foundation',
			title: '물리치료 기초',
			description: '해부생리 · 운동학 · 물리적 인자치료 · 공중보건학',
			session: 1,
			total: 60,
			subjects: [
				{ id: encodeURIComponent('해부생리학'), title: '해부생리학', count: 22, session: 1 },
				{ id: encodeURIComponent('운동학'), title: '운동학', count: 12, session: 1 },
				{ id: encodeURIComponent('물리적 인자치료'), title: '물리적 인자치료', count: 16, session: 1 },
				{ id: encodeURIComponent('공중보건학'), title: '공중보건학', count: 10, session: 1 }
			]
		},
		{
			id: 'ptg-assessment',
			title: '물리치료 진단평가',
			description: '근골격 · 신경계 · 원리 · 심폐혈관 · 기타 · 임상의사결정',
			session: 1,
			total: 45,
			subjects: [
				{ id: encodeURIComponent('근골격계 물리치료 진단평가'), title: '근골격계 물리치료 진단평가', count: 10, session: 1 },
				{ id: encodeURIComponent('신경계 물리치료 진단평가'), title: '신경계 물리치료 진단평가', count: 16, session: 1 },
				{ id: encodeURIComponent('진단평가 원리'), title: '진단평가 원리', count: 6, session: 1 },
				{ id: encodeURIComponent('심폐혈관계 검사 및 평가'), title: '심폐혈관계 검사 및 평가', count: 4, session: 1 },
				{ id: encodeURIComponent('기타 계통 검사'), title: '기타 계통 검사', count: 2, session: 1 },
				{ id: encodeURIComponent('임상의사결정'), title: '임상의사결정', count: 7, session: 1 }
			]
		},
		{
			id: 'ptg-intervention',
			title: '물리치료 중재',
			description: '근골격 · 신경계 · 심폐혈관 · 림프·피부 · 문제해결',
			session: 2,
			total: 65,
			subjects: [
				{ id: encodeURIComponent('근골격계 중재'), title: '근골격계 중재', count: 28, session: 2 },
				{ id: encodeURIComponent('신경계 중재'), title: '신경계 중재', count: 25, session: 2 },
				{ id: encodeURIComponent('심폐혈관계 중재'), title: '심폐혈관계 중재', count: 5, session: 2 },
				{ id: encodeURIComponent('림프, 피부계 중재'), title: '림프, 피부계 중재', count: 2, session: 2 },
				{ id: encodeURIComponent('물리치료 문제해결'), title: '물리치료 문제해결', count: 5, session: 2 }
			]
		},
		{
			id: 'ptg-medlaw',
			title: '의료관계법규',
			description: '의료법 · 의료기사법 · 노인복지법 · 장애인복지법 · 건보법',
			session: 2,
			total: 20,
			subjects: [
				{ id: encodeURIComponent('의료법'), title: '의료법', count: 5, session: 2 },
				{ id: encodeURIComponent('의료기사법'), title: '의료기사법', count: 5, session: 2 },
				{ id: encodeURIComponent('노인복지법'), title: '노인복지법', count: 4, session: 2 },
				{ id: encodeURIComponent('장애인복지법'), title: '장애인복지법', count: 3, session: 2 },
				{ id: encodeURIComponent('국민건강보험법'), title: '국민건강보험법', count: 3, session: 2 }
			]
		}
	];

	// PTGQuizUI 미존재 경고를 중복 출력하지 않도록 가드
	let PTG_QUIZUI_WARNED = false;

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

    // 설명 텍스트 포맷팅: "(정답 해설):" / "(오답 해설):" 구분이 있으면 줄바꿈 추가
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
     * 초기화 함수
     */
    function init() {
        if (PTG_STUDY_DEBUG) console.log('PTG Study: init() function called.');
		window.PTG_STUDY_GLOBAL_CLICK_DEBUG = true; // 전역 클릭 디버그(기본 on: 일부 테마에서 위임 실패 보완)
        
        const studyContainer = $('#ptg-study-app');
        if (PTG_STUDY_DEBUG) console.log('PTG Study: Found container, length:', studyContainer.length);

        if (studyContainer.length === 0) {
            console.error('PTG Study: Container #ptg-study-app not found. Aborting.');
            return;
        }
        
        // 초기 과목/세부과목 DOM을 그대로 스냅샷 (Subjects::MAP 기반 PHP 렌더링 결과)
        initialCoursesHTML = studyContainer.html();

		// 과목/세부과목 카드는 PHP에서 class-subjects.php MAP을 이용해 렌더링하므로
        // JS에서는 클릭 이벤트만 처리한다.

        setupStudyTipHandlers();

        // URL 파라미터에서 세부과목 자동 열기 (대시보드에서 링크로 이동한 경우)
        const urlParams = new URLSearchParams(window.location.search);
        const subjectParam = urlParams.get('subject');
        if (subjectParam) {
            try {
                const subjectId = decodeURIComponent(subjectParam);
                const subjectLabel = subjectId;
                // 해당 세부과목이 있는 카테고리 찾기
                const $targetItem = studyContainer.find('.ptg-subject-item').filter(function() {
                    const itemId = $(this).data('subject-id');
                    if (!itemId) return false;
                    try {
                        return decodeURIComponent(itemId) === subjectId;
                    } catch (e) {
                        return itemId === subjectId;
                    }
                });
                
                if ($targetItem.length > 0) {
                    const $category = $targetItem.closest('.ptg-category');
                    const categoryLabel = $category.find('.ptg-category-title').text().trim();
                    // 약간의 지연 후 자동으로 클릭 (DOM이 완전히 준비된 후)
                    setTimeout(function() {
                        studyContainer.html(`<p>${escapeHtml(subjectLabel)} 과목의 학습 내용을 불러오는 중...</p>`);
                        fetchAndRenderLessons(studyContainer, subjectId, subjectLabel, categoryLabel);
                    }, 100);
                }
            } catch (e) {
                console.warn('PTG Study: Failed to parse subject parameter', e);
            }
        }

        // 카테고리(과목 카드) 클릭 → 해당 과목의 모든 세부과목을 한 번에 학습
        studyContainer.off('click', '.ptg-category');
        studyContainer.on('click', '.ptg-category', function(event) {
            if (PTG_STUDY_DEBUG) console.log('PTG Study: category clicked (DOM-based)', event.target);

            // 세부과목 클릭일 때는 카테고리 핸들러를 타지 않도록 방지
            if ($(event.target).closest('.ptg-subject-item').length) {
                return;
            }

            const $categoryCard = $(this);
            const categoryId = $categoryCard.data('category-id');
            const categoryTitle = $categoryCard.find('.ptg-category-title').text().trim();

            // 이 카테고리에 포함된 세부과목명들을 data-subject-id에서 복원
            const subjectNames = $categoryCard.find('.ptg-subject-item').map(function() {
                const rawId = $(this).data('subject-id') || '';
                try {
                    return decodeURIComponent(rawId);
                } catch (e) {
                    return rawId;
                }
            }).get();

            if (!subjectNames || subjectNames.length === 0) {
                alert('이 과목에는 학습 가능한 세부과목이 없습니다.');
                return;
            }

            const category = {
                id: categoryId,
                title: categoryTitle,
                subjects: subjectNames, // 문자열 배열로 전달
            };

            fetchAndRenderCategoryLessons(studyContainer, category);
        });
    }

    /**
     * API에서 코스(과목) 목록을 가져와서 렌더링
     */
    function fetchCourses(studyContainer) {
        const rest = getRestConfig();
        $.ajax({
            url: rest.baseUrl + 'courses',
            method: 'GET',
            beforeSend: function(xhr) {
                if (rest.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', rest.nonce);
                }
            }
        }).done(function(courses) {
            renderCourses(studyContainer, courses || []);
        }).fail(function(jqXHR) {
            const msg = (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : '카테고리 목록을 불러오는데 실패했습니다.';
            studyContainer.html(`<p>${escapeHtml(String(msg))}</p>`);
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

		let html = '<h2>🗝️ 학습할 과목을 선택하세요</h2><div class="ptg-course-categories">';
		courses.forEach(function(category) {
			categoryMap[category.id] = category;
			const categoryTitle = category.title || category.label || '';
			const categoryCount = typeof category.total === 'number' ? ` (${category.total})` : '';
			const sessionBadge = typeof category.session === 'number' ? `<span class="ptg-session-badge">${category.session}교시</span>` : '';
			html += `
				<section class="ptg-category" data-category-id="${escapeHtml(category.id)}">
					<header class="ptg-category-header">
						<h3 class="ptg-category-title">${sessionBadge}${escapeHtml(categoryTitle)}${categoryCount}</h3>
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
            if (PTG_STUDY_DEBUG) console.log('PTG Study: subject item clicked', event.target);
            event.stopPropagation();

            const subjectId = $(this).data('subject-id');
            if (PTG_STUDY_DEBUG) console.log('PTG Study: subjectId resolved =', subjectId);
            if (!subjectId) {
                console.warn('PTG Study: subjectId is missing on clicked element', this);
                return;
            }
            const subjectLabel = $(this).text().trim();
            const categoryLabel = $(this).closest('.ptg-category').find('.ptg-category-title').text().trim();
            if (PTG_STUDY_DEBUG) console.log('PTG Study: subjectLabel =', subjectLabel, 'categoryLabel =', categoryLabel);
            // 즉시 로딩 상태 표시
            const displayName = subjectLabel || decodeURIComponent(subjectId);
            studyContainer.html(`<p>${escapeHtml(displayName)} 과목의 학습 내용을 불러오는 중...</p>`);
            fetchAndRenderLessons(studyContainer, subjectId, subjectLabel, categoryLabel);
        });
    }

	/**
	 * REST 설정 가져오기 (ptgStudy 미정의 시 자동 대체)
	 */
	function getRestConfig() {
		var baseUrl;
		if (typeof window.ptgStudy !== 'undefined' && window.ptgStudy.rest_url) {
			baseUrl = window.ptgStudy.rest_url;
		} else {
			// wp-json 경로로 폴백
			var origin = (window.location.origin || (window.location.protocol + '//' + window.location.host));
			baseUrl = origin.replace(/\/$/, '') + '/wp-json/ptg-study/v1/';
		}
		var nonce = null;
		if (typeof window.ptgStudy !== 'undefined' && window.ptgStudy.api_nonce) {
			nonce = window.ptgStudy.api_nonce;
		} else if (typeof window.wpApiSettings !== 'undefined' && window.wpApiSettings.nonce) {
			nonce = window.wpApiSettings.nonce;
		}
		return { baseUrl: baseUrl, nonce: nonce };
	}

    /**
     * Study 진행 기록을 서버에 전송
     */
    function logStudyProgress(questionId) {
        if (!questionId) {
            return;
        }

        // sessionStorage에서 이미 로그된 question_id 목록 가져오기
        let loggedQuestions = [];
        try {
            const stored = sessionStorage.getItem(STORAGE_KEY);
            if (stored) {
                loggedQuestions = JSON.parse(stored);
            }
        } catch (e) {
            if (PTG_STUDY_DEBUG) console.warn('PTG Study: Failed to read sessionStorage', e);
        }

        // 이미 이 세션에서 로그된 question_id인지 확인
        if (loggedQuestions.includes(questionId)) {
            if (PTG_STUDY_DEBUG) console.log('PTG Study: Already logged in this session, ignoring', questionId);
            return;
        }

        const rest = getRestConfig();
        if (!rest || !rest.baseUrl) {
            return;
        }

        // 요청 시작 전에 sessionStorage에 추가 (중복 요청 방지)
        loggedQuestions.push(questionId);
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(loggedQuestions));
        } catch (e) {
            if (PTG_STUDY_DEBUG) console.warn('PTG Study: Failed to write sessionStorage', e);
        }

        $.ajax({
            url: rest.baseUrl + 'study-progress',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ question_id: questionId }),
            processData: false,
            beforeSend: function(xhr) {
                if (rest.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', rest.nonce);
                }
            }
        }).done(function() {
            // 성공 시 sessionStorage에 그대로 유지 (페이지 새로고침 시에만 초기화됨)
            if (PTG_STUDY_DEBUG) console.log('PTG Study: Progress logged successfully', questionId);
        }).fail(function() {
            // 실패 시 sessionStorage에서 제거하여 재시도 가능하도록
            try {
                const stored = sessionStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const questions = JSON.parse(stored);
                    const index = questions.indexOf(questionId);
                    if (index > -1) {
                        questions.splice(index, 1);
                        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(questions));
                    }
                }
            } catch (e) {
                if (PTG_STUDY_DEBUG) console.warn('PTG Study: Failed to remove from sessionStorage', e);
            }
        });
    }

    /**
     * 학습 Tip 모달 열기/닫기 핸들러
     */
    function setupStudyTipHandlers() {
        const $modal    = $('#ptg-study-tip-modal');
        const $backdrop = $modal.find('.ptg-study-tip-backdrop');
        const $closeBtn = $modal.find('.ptg-study-tip-close');
        const $openBtn  = $('[data-ptg-tip-open]');

        if ($modal.length === 0 || $openBtn.length === 0) {
            return;
        }

        function openTip() {
            $modal.addClass('is-open').attr('aria-hidden', 'false');
        }

        function closeTip() {
            $modal.removeClass('is-open').attr('aria-hidden', 'true');
        }

        $openBtn.on('click', function() {
            openTip();
        });

        $backdrop.on('click', function() {
            closeTip();
        });

        $closeBtn.on('click', function() {
            closeTip();
        });

        $(document).on('keydown.ptgStudyTip', function(e) {
            if (e.key === 'Escape') {
                closeTip();
            }
        });
    }

	// 전역(페이지 전체) 위임 핸들러 - 테마/플러그인 간 충돌 시에도 로그를 보이게 함
	$(document).on('click', '.ptg-subject-item', function(e) {
		if (!window.PTG_STUDY_GLOBAL_CLICK_DEBUG) return;
		if (PTG_STUDY_DEBUG) console.log('[GLOBAL] PTG Study: .ptg-subject-item clicked', e.target);

		const $item = $(this);
		const subjectId = $item.data('subject-id');
		const subjectLabel = $item.text().trim();
		const $container = $('#ptg-study-app');
		const categoryLabel = $item.closest('.ptg-category').find('.ptg-category-title').text().trim();

		if (PTG_STUDY_DEBUG) console.log('[GLOBAL] subjectId =', subjectId, 'subjectLabel =', subjectLabel, 'categoryLabel =', categoryLabel, 'container exists =', $container.length);

		// 이미 컨테이너 위임 핸들러가 처리하는 경우 중복 방지
		// 컨테이너가 없거나 컨테이너 핸들러가 동작하지 않는 상황에서만 직접 호출
		if ($container.length) {
			// 로딩 상태 표시
			const displayName = subjectLabel || (subjectId ? decodeURIComponent(subjectId) : '');
			$container.html(`<p>${escapeHtml(displayName)} 과목의 학습 내용을 불러오는 중...</p>`);
			fetchAndRenderLessons($container, subjectId, subjectLabel, categoryLabel);
		}
	});

    function renderSubjectList(subjects) {
        if (!Array.isArray(subjects) || subjects.length === 0) {
            return '<p class="ptg-empty-subjects">준비 중인 과목입니다.</p>';
        }

		let listHtml = '<ul class="ptg-subject-list ptg-subject-list--stack">';
		subjects.forEach(function(subject) {
			const sessText = typeof subject.session === 'number' ? `<span class="ptg-session-badge ptg-session-badge--sm">${subject.session}교시</span>` : '';
			listHtml += `
				<li class="ptg-subject-item" data-subject-id="${escapeHtml(subject.id)}">
					${sessText}${escapeHtml(subject.title)}
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
     * @param {number} offset  // 페이지네이션용 시작 위치
     */
    function fetchAndRenderLessons(studyContainer, subjectId, subjectLabel, categoryLabel, offset = 0, random = false) {
        const displayName = subjectLabel || decodeURIComponent(subjectId);

        const rest = getRestConfig();
        const pageSize = 10;
        const params = new URLSearchParams();
        params.set('limit', pageSize);
        if (!random && offset > 0) {
            params.set('offset', offset);
        }
        if (random) {
            params.set('random', '1');
        }
        const url = rest.baseUrl + 'courses/' + subjectId + '?' + params.toString();
        if (PTG_STUDY_DEBUG) console.log('PTG Study: fetching lessons', { url, subjectId, subjectLabel, categoryLabel, rest, offset, random });
        $.ajax({
			url: url,
            method: 'GET',
            beforeSend: function(xhr) {
                if (rest.nonce) {
					xhr.setRequestHeader('X-WP-Nonce', rest.nonce);
				}
            }
        }).done(function(courseDetail) {
            if (PTG_STUDY_DEBUG) console.log('PTG Study: lessons fetch success, courseDetail:', courseDetail);

            const lessons = courseDetail && Array.isArray(courseDetail.lessons) ? courseDetail.lessons : [];
            const total = typeof courseDetail.total === 'number' ? courseDetail.total : null;
            if (!lessons || lessons.length === 0) {
                alert(`${displayName} 과목의 학습 내용이 없습니다.`);
                // 데이터가 없으면 자동으로 과목 목록 화면으로 복귀
                if (initialCoursesHTML !== null) {
                    studyContainer.html(initialCoursesHTML);
                    // 헤더/학습Tip 버튼 이벤트 다시 바인딩
                    setupStudyTipHandlers();
                }
                return;
            }

            renderLessons(studyContainer, courseDetail, {
                subjectId: subjectId,
                subjectLabel: displayName,
                categoryLabel: categoryLabel,
                isCategory: false,
                offset: offset,
                limit: pageSize,
                total: total,
                random: random
            });
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('PTG Study: lessons fetch failed', { status: jqXHR && jqXHR.status, textStatus, errorThrown });
            alert(`${displayName} 과목의 학습 내용이 없습니다.`);
            // 오류 시에도 과목 목록 화면으로 복귀
            if (initialCoursesHTML !== null) {
                studyContainer.html(initialCoursesHTML);
                setupStudyTipHandlers();
            }
        });
    }

    function fetchAndRenderCategoryLessons(studyContainer, category, offset = 0) {
        const categoryTitle = category.title || category.label || '';
        const rawSubjects = Array.isArray(category.subjects) ? category.subjects : [];

        if (rawSubjects.length === 0) {
            alert('이 과목에는 학습 가능한 세부과목이 없습니다.');
            return;
        }

        // 문자열 배열 또는 { title } 배열 모두 지원
        const subjectNames = rawSubjects.map(function(subject) {
            if (typeof subject === 'string') {
                return subject;
            }
            return subject && subject.title ? subject.title : '';
        }).filter(function(name) { return !!name; });

        const rest = getRestConfig();
        const pageSize = 10;
        $.ajax({
            url: rest.baseUrl + 'courses/' + category.id,
            method: 'GET',
            data: {
				subjects: subjectNames,
                limit: pageSize,
                offset: offset
            },
            beforeSend: function(xhr) {
                if (rest.nonce) {
					xhr.setRequestHeader('X-WP-Nonce', rest.nonce);
				}
            }
        }).done(function(courseDetail) {
            const lessons = courseDetail && Array.isArray(courseDetail.lessons) ? courseDetail.lessons : [];
            const total = typeof courseDetail.total === 'number' ? courseDetail.total : null;
            if (!lessons || lessons.length === 0) {
                alert('과목의 학습 내용이 없습니다.');
                if (initialCoursesHTML !== null) {
                    studyContainer.html(initialCoursesHTML);
                    setupStudyTipHandlers();
                }
                return;
            }

            renderLessons(studyContainer, courseDetail, {
                categoryId: category.id,
                subjectLabel: categoryTitle,
                categoryLabel: categoryTitle,
                isCategory: true,
                offset: offset,
                limit: pageSize,
                total: total,
                random: false
            });
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('PTG Study: category lessons fetch failed', { status: jqXHR && jqXHR.status, textStatus, errorThrown });
            alert('과목의 학습 내용이 없습니다.');
            if (initialCoursesHTML !== null) {
                studyContainer.html(initialCoursesHTML);
                setupStudyTipHandlers();
            }
        });
    }

    /**
     * 학습 내용을 HTML로 렌더링
     * @param {jQuery} studyContainer
     * @param {Object} courseDetail 
     * @param {Object} meta
     */
    function renderLessons(studyContainer, courseDetail, meta) {
        const isCategory    = meta && meta.isCategory;
        const subjectTitle  = meta && meta.subjectLabel ? meta.subjectLabel : courseDetail.title;
        const categoryTitle = meta && meta.categoryLabel ? meta.categoryLabel : '';
        const subjectId     = meta && meta.subjectId ? meta.subjectId : null; // 세부과목 ID (페이지네이션용)
        const categoryId    = meta && meta.categoryId ? meta.categoryId : null;
        const currentOffset = typeof meta.offset === 'number' ? meta.offset : 0;
        const pageSize      = typeof meta.limit === 'number' ? meta.limit : 0;
        const totalCount    = typeof meta.total === 'number' ? meta.total : null;
        const isRandom      = !!(meta && meta.random);

        // 단일 세부과목 / 집계 과목 모두 페이지네이션 사용 (랜덤은 세부과목에서만)
        const enablePaging = pageSize > 0;
        let heading;
        if (isCategory) {
            heading = `${categoryTitle || subjectTitle} 전체 학습`;
        } else {
            heading = categoryTitle ? `${categoryTitle} · ${subjectTitle}` : `${subjectTitle}`;
        }

        let html = `
            <div class="ptg-lesson-view">
                <button id="back-to-courses" class="ptg-btn ptg-btn-secondary">&laquo; 과목 목록으로 돌아가기</button>
                <div class="ptg-lesson-header" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <h3 style="margin: 0;">${escapeHtml(heading)}</h3>
                    ${(!isCategory && subjectId) ? `
                        <div class="ptg-random-toggle-wrapper">
                            <label class="ptg-random-toggle">
                                <input type="checkbox" id="ptg-random-toggle" ${isRandom ? 'checked' : ''}>
                                <span>랜덤 섞기</span>
                            </label>
                        </div>
                    ` : ''}
                </div>
        `;

        if (isCategory && Array.isArray(courseDetail.subjects) && courseDetail.subjects.length > 0) {
            const subjectList = courseDetail.subjects.map(function(subjectName) {
                return `<span class="ptg-lesson-subject-chip">${escapeHtml(subjectName)}</span>`;
            }).join('\n');
            html += `<div class="ptg-lesson-subjects">포함 과목: ${subjectList}</div>`;
        }

        html += '<div class="ptg-lesson-list">';

        const lessons = courseDetail && Array.isArray(courseDetail.lessons) ? courseDetail.lessons : [];
        lessons.forEach(function(lesson, index) {
            const questionHtml = renderQuestionFromUI(lesson, index + 1);


            // 해설에 표시할 세부과목명 결정: 우선 응답의 category.subject, 없으면 현재 과목 제목 사용
            const explanationSubject = (lesson.category && lesson.category.subject)
                ? lesson.category.subject
                : subjectTitle;

            // 이미지 URL 구성 (year, session은 lesson.category에서 가져오기)
            let imageUrl = '';
            if (lesson.question_image && lesson.category) {
                const year = lesson.category.year || '';
                const session = lesson.category.session || '';
                if (year && session) {
                    imageUrl = `/wp-content/uploads/ptgates-questions/${year}/${session}/${lesson.question_image}`;
                }
            }

            html += `
                <div class="ptg-lesson-item ptg-quiz-card" data-lesson-id="${escapeHtml(lesson.id)}">
                    ${questionHtml}
                    <div class="ptg-lesson-answer-area">
                        <button class="toggle-answer ptg-btn ptg-btn-primary">정답 및 해설 보기</button>
                        ${lesson.question_image ? '<button class="toggle-answer-img ptg-btn ptg-btn-primary">학습 이미지</button>' : ''}
                        <div class="answer-content" style="display: none;">
                            <p><strong>정답:</strong> ${escapeHtml(lesson.answer)}</p>
                            <hr>
                            <p><strong>해설 (${escapeHtml(explanationSubject)}) - quiz-ID: ${escapeHtml(lesson.id)}</strong></p>
							<div>${lesson.explanation ? formatExplanationText(lesson.explanation) : '해설이 없습니다.'}</div>
                        </div>
                        ${imageUrl ? `<div class="question-image-content" style="display: none;"><img src="${imageUrl}" alt="문제 이미지" style="max-width: 100%; height: auto;" /></div>` : ''}
                    </div>
                </div>
            `;

        });

        html += '</div>';

        // 페이지네이션 + 과목 목록으로 돌아가기 (하단 네비게이션)
        if (enablePaging) {
            const startIndex = currentOffset + 1;
            const endIndex   = currentOffset + lessons.length;
            const totalLabel = totalCount !== null ? totalCount : endIndex;

            html += '<div class="ptg-lesson-pagination">';

            html += `<div class="ptg-lesson-page-info">${startIndex}-${endIndex} / 총 ${totalLabel}문제</div>`;

            if (!isRandom && currentOffset > 0) {
                html += '<button class="ptg-btn ptg-btn-secondary" data-ptg-action="prev">이전 10문제</button>';
            }

            if (!isRandom && lessons.length === pageSize) {
                html += '<button class="ptg-btn ptg-btn-secondary" data-ptg-action="next">다음 10문제</button>';
            }

            if (isRandom) {
                html += '<button class="ptg-btn ptg-btn-secondary" data-ptg-action="next">다른 10문제</button>';
            }

            html += '<button class="ptg-btn ptg-btn-tertiary" data-ptg-action="back-to-courses">과목 목록으로 돌아가기</button>';

            html += '</div>';
        }

        html += '</div>';
        studyContainer.html(html);

        // 상단 "과목 목록으로 돌아가기" 버튼
        $('#back-to-courses').on('click', function() {
            if (initialCoursesHTML !== null) {
                studyContainer.html(initialCoursesHTML);
                setupStudyTipHandlers();
            }
        });
        $('.toggle-answer').on('click', function() {
            $(this).closest('.ptg-lesson-answer-area').find('.answer-content').slideToggle();

            const lessonId = $(this).closest('.ptg-lesson-item').data('lesson-id');
            const questionId = lessonId ? parseInt(lessonId, 10) : 0;
            if (questionId > 0) {
                logStudyProgress(questionId);
            }
        });
        $('.toggle-answer-img').on('click', function() {
            $(this).closest('.ptg-lesson-answer-area').find('.question-image-content').slideToggle();
        });

        // 랜덤 섞기 토글 (단일 세부과목에서만 표시)
        if (!isCategory && subjectId) {
            $('#ptg-random-toggle').on('change', function() {
                const useRandom = $(this).is(':checked');
                // 랜덤 모드로 전환 시 항상 처음 10문제(또는 랜덤 샘플)부터 시작
                fetchAndRenderLessons(studyContainer, subjectId, subjectTitle, categoryTitle, 0, useRandom);
            });
        }

        // 하단 페이지네이션 / 랜덤 네비게이션 버튼들
        if (enablePaging) {
            $('.ptg-lesson-pagination').on('click', 'button', function() {
                const action = $(this).data('ptg-action');

                if (action === 'back-to-courses') {
                    if (initialCoursesHTML !== null) {
                        studyContainer.html(initialCoursesHTML);
                    }
                    return;
                }

                if (isCategory) {
                    // 집계 과목: offset 기반 페이지네이션 (랜덤 없음)
                    let newOffset = currentOffset;
                    if (action === 'prev') {
                        newOffset = Math.max(0, currentOffset - pageSize);
                    } else if (action === 'next') {
                        newOffset = currentOffset + pageSize;
                    }

                    const category = {
                        id: categoryId,
                        title: categoryTitle,
                        subjects: courseDetail.subjects || []
                    };
                    fetchAndRenderCategoryLessons(studyContainer, category, newOffset);
                } else {
                    // 세부과목 모드
                    // 랜덤 모드에서는 "다른 10문제"만 제공
                    if (isRandom) {
                        if (action === 'next') {
                            fetchAndRenderLessons(studyContainer, subjectId, subjectTitle, categoryTitle, 0, true);
                        }
                        return;
                    }

                    let newOffset = currentOffset;
                    if (action === 'prev') {
                        newOffset = Math.max(0, currentOffset - pageSize);
                    } else if (action === 'next') {
                        newOffset = currentOffset + pageSize;
                    }

                    fetchAndRenderLessons(studyContainer, subjectId, subjectTitle, categoryTitle, newOffset, false);
                }
            });
        }
    }

    /**
     * quiz-ui.js의 기능을 활용하여 문제 HTML을 생성 (문자열 반환)
     * @param {object} lesson 
     */
    function renderQuestionFromUI(lesson, questionNumber) {
		function getCircledNumber(n) {
			// 1→① ... 20→⑳
			const circled = ['①','②','③','④','⑤','⑥','⑦','⑧','⑨','⑩','⑪','⑫','⑬','⑭','⑮','⑯','⑰','⑱','⑲','⑳'];
			return circled[(n - 1) % circled.length] || '';
		}
		// 보기가 별도 배열로 없는 경우, 지문 내 ①~⑤ 또는 1)~5) 패턴을 파싱해 줄바꿈 렌더링
		function renderBasicFormatted(lessonData) {
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
				// Remove matched segments from stem roughly by splitting at first circled marker
				const firstIdx = normalized.search(/[①-⑳]/);
				stem = firstIdx > -1 ? normalized.slice(0, firstIdx).trim() : normalized.trim();
			} else {
				// Fallback to numeric pattern
				let numericMatches = [];
				while ((m = numericRegex.exec(normalized)) !== null) {
					numericMatches.push({ num: m[1], text: (m[2] || '').trim() });
				}
				if (numericMatches.length >= 2) {
					// Sort by number just in case
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

		// 우선 공용 UI가 있으면 그대로 사용하되, 옵션 배열이 없을 땐 파싱 폴백
		if (typeof window.PTGQuizUI === 'undefined') {
			if (!PTG_QUIZUI_WARNED) {
				console.warn('PTGQuizUI is not available. Falling back to basic rendering.');
				PTG_QUIZUI_WARNED = true;
			}
			return renderBasicFormatted(lesson);
		}

		const questionText = lesson.content || '';
		const options = Array.isArray(lesson.options) ? lesson.options : [];

		if (options.length === 0) {
			return renderBasicFormatted(lesson);
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
        if (PTG_STUDY_DEBUG) console.log('PTG Study: Document is ready. Calling init().');
        init();
    });

})(jQuery);
