/**
 * 학습 내용을 HTML로 렌더링 (무한 스크롤 지원)
 * @param {jQuery} studyContainer
 * @param {Object} courseDetail
 * @param {Object} meta
 */
function renderLessons(studyContainer, courseDetail, meta) {
  const isCategory = meta && meta.isCategory;
  const subjectTitle =
    meta && meta.subjectLabel ? meta.subjectLabel : courseDetail.title;
  const categoryTitle = meta && meta.categoryLabel ? meta.categoryLabel : "";
  const subjectId = meta && meta.subjectId ? meta.subjectId : null;
  const categoryId = meta && meta.categoryId ? meta.categoryId : null;
  const currentOffset = typeof meta.offset === "number" ? meta.offset : 0;
  const pageSize = typeof meta.limit === "number" ? meta.limit : 0;
  const totalCount = typeof meta.total === "number" ? meta.total : null;
  const isRandom = !!(meta && meta.random);
  const rawSubjects = meta.rawSubjects || []; // For category context

  const lessons =
    courseDetail && Array.isArray(courseDetail.lessons)
      ? courseDetail.lessons
      : [];

  // 1. 초기 로드 (Offset 0) - 전체 레이아웃 생성
  if (currentOffset === 0) {
    let heading;
    if (isCategory) {
      heading = `${categoryTitle || subjectTitle} 전체 학습`;
    } else {
      heading = categoryTitle
        ? `${categoryTitle} · ${subjectTitle}`
        : `${subjectTitle}`;
    }

    let html = `
                <div class="ptg-lesson-view">
                    <button id="back-to-courses" class="ptg-btn ptg-btn-secondary">&laquo; 과목 목록으로 돌아가기</button>
                    <div class="ptg-lesson-header" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <h3 style="margin: 0;">${escapeHtml(heading)}</h3>
                        ${
                          !isCategory && subjectId
                            ? `
                            <div class="ptg-random-toggle-wrapper">
                                <label class="ptg-random-toggle">
                                    <input type="checkbox" id="ptg-random-toggle" ${
                                      isRandom ? "checked" : ""
                                    }>
                                    <span>랜덤 섞기</span>
                                </label>
                            </div>
                        `
                            : ""
                        }
                    </div>
            `;

    if (
      isCategory &&
      Array.isArray(courseDetail.subjects) &&
      courseDetail.subjects.length > 0
    ) {
      const subjectList = courseDetail.subjects
        .map(function (subjectName) {
          return `<span class="ptg-lesson-subject-chip">${escapeHtml(
            subjectName
          )}</span>`;
        })
        .join("\n");
      html += `<div class="ptg-lesson-subjects">포함 과목: ${subjectList}</div>`;
    }

    // Count Display (Fixed at top or just below header)
    const currentCount = lessons.length;
    html += `<div class="ptg-lesson-count-info" style="margin: 10px 0; font-weight: bold; color: #555;">
                        <span id="ptg-current-count">${currentCount}</span> / 총 ${totalCount}문제
                     </div>`;

    html += '<div class="ptg-lesson-list"></div>'; // Empty container for appending

    // Loader for infinite scroll
    html +=
      '<div id="ptg-infinite-loader" style="display:none; text-align:center; padding:20px;"><span class="ptg-spinner"></span> 불러오는 중...</div>';

    // Sentinel for IntersectionObserver
    html += '<div id="ptg-scroll-sentinel" style="height: 20px;"></div>';

    html += "</div>"; // End ptg-lesson-view

    studyContainer.html(html);

    // Event Handlers for Header
    $("#back-to-courses").on("click", function () {
      if (initialCoursesHTML !== null) {
        studyContainer.html(initialCoursesHTML);
        setupStudyTipHandlers();
      }
    });

    if (!isCategory && subjectId) {
      $("#ptg-random-toggle").on("change", function () {
        const useRandom = $(this).is(":checked");
        fetchAndRenderLessons(
          studyContainer,
          subjectId,
          subjectTitle,
          categoryTitle,
          0,
          useRandom
        );
      });
    }
  }

  // 2. Append Lessons
  const $listContainer = studyContainer.find(".ptg-lesson-list");
  let newItemsHtml = "";

  lessons.forEach(function (lesson, index) {
    // Calculate absolute index for numbering (optional, if needed)
    const absIndex = currentOffset + index + 1;
    const questionHtml = renderQuestionFromUI(lesson, absIndex);

    const explanationSubject =
      lesson.category && lesson.category.subject
        ? lesson.category.subject
        : subjectTitle;

    let imageUrl = "";
    if (lesson.question_image && lesson.category) {
      const year = lesson.category.year || "";
      const session = lesson.category.session || "";
      if (year && session) {
        imageUrl = `/wp-content/uploads/ptgates-questions/${year}/${session}/${lesson.question_image}`;
      }
    }

    newItemsHtml += `
                <div class="ptg-lesson-item ptg-quiz-card" data-lesson-id="${escapeHtml(
                  lesson.id
                )}">
                    ${questionHtml}
                    <div class="ptg-lesson-answer-area">
                        <button class="toggle-answer ptg-btn ptg-btn-primary">정답 및 해설 보기</button>
                        ${
                          lesson.question_image
                            ? '<button class="toggle-answer-img ptg-btn ptg-btn-primary">학습 이미지</button>'
                            : ""
                        }
                        <div class="answer-content" style="display: none;">
                            <p><strong>정답:</strong> ${escapeHtml(
                              lesson.answer
                            )}</p>
                            <hr>
                            <p><strong>해설 (${escapeHtml(
                              explanationSubject
                            )}) - quiz-ID: ${escapeHtml(lesson.id)}</strong></p>
                            <div>${
                              lesson.explanation
                                ? formatExplanationText(lesson.explanation)
                                : "해설이 없습니다."
                            }</div>
                        </div>
                        ${
                          imageUrl
                            ? `<div class="question-image-content" style="display: none;"><img src="${imageUrl}" alt="문제 이미지" style="max-width: 100%; height: auto;" /></div>`
                            : ""
                        }
                    </div>
                </div>
            `;
  });

  $listContainer.append(newItemsHtml);

  // 3. Update Count
  const totalDisplayed = currentOffset + lessons.length;
  $("#ptg-current-count").text(totalDisplayed);

  // 4. Re-bind Toggle Events (Delegated events handled in init or re-bound here)
  // Re-binding logic for toggles (Delegated style, idempotent)
  studyContainer.off("click", ".toggle-answer");
  studyContainer.on("click", ".toggle-answer", function () {
    $(this)
      .closest(".ptg-lesson-answer-area")
      .find(".answer-content")
      .slideToggle();
    const lessonId = $(this).closest(".ptg-lesson-item").data("lesson-id");
    const questionId = lessonId ? parseInt(lessonId, 10) : 0;
    if (questionId > 0) {
      logStudyProgress(questionId);
    }
  });

  studyContainer.off("click", ".toggle-answer-img");
  studyContainer.on("click", ".toggle-answer-img", function () {
    $(this)
      .closest(".ptg-lesson-answer-area")
      .find(".question-image-content")
      .slideToggle();
  });

  // 5. Setup Infinite Scroll Observer
  const hasMore = totalCount > totalDisplayed;

  if (hasMore) {
    const sentinel = document.getElementById("ptg-scroll-sentinel");
    if (sentinel) {
      // Disconnect previous observer if any
      if (window.ptgStudyObserver) {
        window.ptgStudyObserver.disconnect();
      }

      window.ptgStudyObserver = new IntersectionObserver(
        (entries) => {
          if (entries[0].isIntersecting) {
            // Load next batch
            const nextOffset = currentOffset + pageSize;
            if (isCategory) {
              fetchAndRenderCategoryLessons(
                studyContainer,
                {
                  id: categoryId,
                  title: categoryTitle,
                  subjects: rawSubjects,
                },
                nextOffset
              );
            } else {
              fetchAndRenderLessons(
                studyContainer,
                subjectId,
                subjectTitle,
                categoryTitle,
                nextOffset,
                isRandom
              );
            }
          }
        },
        { rootMargin: "200px" }
      );

      window.ptgStudyObserver.observe(sentinel);
    }
  } else {
    // No more items, hide sentinel
    $("#ptg-scroll-sentinel").hide();
    if (window.ptgStudyObserver) {
      window.ptgStudyObserver.disconnect();
    }
  }
}
