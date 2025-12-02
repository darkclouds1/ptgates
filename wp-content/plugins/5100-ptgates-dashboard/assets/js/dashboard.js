(function ($) {
  "use strict";

  const Dashboard = {
    greetingCycleIndex: 0,
    init: function () {
      this.$container = $("#ptg-dashboard-app");
      if (this.$container.length === 0) return;

      this.fetchSummary();
      this.bindEvents();
    },

    bindEvents: function () {
      // Quick Actions
      this.$container.on("click", "[data-action], [data-url]", function (e) {
        e.preventDefault();
        const action = $(this).data("action");
        const url = $(this).data("url");
        if (url) {
          window.location.href = url;
        }
      });

      // Learning Day 카드 선택 효과
      this.$container.on("click", ".ptg-learning-day", function (e) {
        e.stopPropagation();
        const $day = $(this);
        // 같은 카드 내의 다른 day는 선택 해제
        $day.siblings(".ptg-learning-day").removeClass("is-active");
        // 현재 카드 토글
        $day.toggleClass("is-active");
      });

      // 과목별 학습 기록 - 세부과목 클릭 시 Study 페이지로 이동
      this.$container.on(
        "click",
        ".ptg-dash-learning .ptg-subject-item",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const $item = $(this);
          // 세부과목명을 직접 텍스트에서 가져오기 (가장 안전한 방법)
          const subjectName = $item.find(".ptg-subject-name").text().trim();
          if (subjectName) {
            // Study 페이지 URL 가져오기 (PHP에서 전달된 값 사용)
            let studyBaseUrl =
              (window.ptg_dashboard_vars &&
                window.ptg_dashboard_vars.study_url) ||
              "";

            // Study URL이 없으면 fallback으로 /ptg_study/ 사용
            if (!studyBaseUrl || studyBaseUrl === "#" || studyBaseUrl === "") {
              studyBaseUrl = "/ptg_study/";
              console.warn(
                "Dashboard: Study page URL not found, using fallback /ptg_study/. Please ensure a page with [ptg_study] shortcode exists."
              );
            }

            // 1100 Study 플러그인과 동일한 방식으로 URL 파라미터 추가
            // URLSearchParams를 사용하여 쿼리 파라미터 구성
            const url = new URL(studyBaseUrl, window.location.origin);
            url.searchParams.set("subject", subjectName); // encodeURIComponent는 URLSearchParams가 자동 처리
            const finalUrl = url.toString();

            // 디버깅용 로그 (개발 환경에서만)
            if (window.console && window.console.log) {
              console.log("Dashboard: Navigating to Study page", {
                studyBaseUrl: studyBaseUrl,
                subjectName: subjectName,
                finalUrl: finalUrl,
              });
            }

            window.location.href = finalUrl;
          } else {
            console.warn(
              "Dashboard: subject name not found on clicked item",
              $item
            );
          }
        }
      );
    },

    fetchSummary: function () {
      const self = this;
      const restUrl = window.ptg_dashboard_vars
        ? window.ptg_dashboard_vars.rest_url
        : "/wp-json/ptg-dash/v1/";
      const nonce = window.ptg_dashboard_vars
        ? window.ptg_dashboard_vars.nonce
        : "";

      $.ajax({
        url: restUrl + "summary",
        method: "GET",
        dataType: "json",
        beforeSend: function (xhr) {
          if (nonce) {
            xhr.setRequestHeader("X-WP-Nonce", nonce);
          }
        },
        success: function (data) {
          if (data && typeof data === "object") {
            self.render(data);
          } else {
            console.error("Invalid response data:", data);
            self.$container.html("<p>데이터 형식이 올바르지 않습니다.</p>");
          }
        },
        error: function (xhr, status, error) {
          // 상세 에러 로깅
          console.error("Dashboard fetch error details:", {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText
              ? xhr.responseText.substring(0, 500)
              : "No response text",
            error: error,
            url: restUrl + "summary",
          });

          let errorMessage = "데이터를 불러오는 중 오류가 발생했습니다.";

          // JSON 응답 파싱 시도
          try {
            if (xhr.responseText) {
              const errorData = JSON.parse(xhr.responseText);
              if (errorData) {
                if (errorData.message) {
                  errorMessage = errorData.message;
                } else if (errorData.code) {
                  errorMessage = "오류 코드: " + errorData.code;
                }
              }
            }
          } catch (e) {
            console.error("Error parsing error response:", e);
            // HTML 응답일 경우 (예: PHP Fatal Error)
            if (xhr.responseText && xhr.responseText.includes("<")) {
              errorMessage += " (서버 오류)";
            }
          }

          // 상태 코드별 메시지
          if (xhr.status === 401 || xhr.status === 403) {
            errorMessage = "로그인이 필요하거나 권한이 없습니다.";
          } else if (xhr.status === 404) {
            errorMessage = "API 엔드포인트를 찾을 수 없습니다.";
          } else if (xhr.status === 500) {
            errorMessage = "서버 내부 오류가 발생했습니다.";
          }

          self.$container.html(`
                        <div class="ptg-error-message">
                            <p>⚠️ ${errorMessage}</p>
                            <small>상태: ${xhr.status} ${xhr.statusText}</small>
                        </div>
                    `);
        },
      });
    },

    render: function (data) {
      const {
        user_name,
        premium,
        today_reviews,
        progress,
        recent_activity,
        bookmarks,
        study_progress,
        flashcard,
        mynote_count,
      } = data;
      const learningRecords = data.learning_records || { subjects: [] };

      // Calculate Percentages
      const totalQuestions = progress.total || 1; // Avoid division by zero

      // 1. Study Progress: (study_count > 0) / totalQuestions
      const studyPercent = Math.min(
        100,
        Math.round(((study_progress || 0) / totalQuestions) * 100)
      );

      // 2. Mock Exam (Quiz) Progress: Same as Overall Progress (solved / total)
      const quizPercent = progress.percent || 0;

      // 3. Flashcard Progress: (Total - Due) / Total (Retention Rate)
      // If total is 0, progress is 0.
      let flashcardPercent = 0;
      if (flashcard && flashcard.total > 0) {
        flashcardPercent = Math.min(
          100,
          Math.round(
            ((flashcard.total - flashcard.due) / flashcard.total) * 100
          )
        );
      }

      // 1. Welcome Section
      const greeting = this.getGreeting();
      const greetingText = this.escapeHtml(greeting.text);
      const greetingAttr = greeting.translation
        ? ` data-translation="${this.escapeHtml(greeting.translation)}"`
        : "";
      const greetingHtml = `<span class="ptg-greeting ${
        greeting.isEnglish ? "is-english" : ""
      }"${greetingAttr}>${greetingText}</span>`;

      // 멤버십 등급 라벨 (API에서 전달받은 값 사용)
      const membershipLabel = premium.grade
        ? `${premium.grade} 멤버십`
        : premium.status === "active"
        ? "Premium 멤버십"
        : "Free 멤버십";

      const welcomeHtml = `
                <div class="ptg-dash-welcome">
                    <div class="ptg-welcome-text">
                        <h2>${this.formatName(user_name)}님,</h2>
                        <div class="ptg-greeting-wrapper">${greetingHtml}</div>
                    </div>
                    <div class="ptg-dash-premium-badge ${
                      premium.status === "active" ? "is-active" : "is-free"
                    }" data-url="?view=membership" style="cursor: pointer;">
                        <span class="ptg-badge-label">${membershipLabel}</span>
                        ${
                          premium.expiry
                            ? `<small>(${premium.expiry} 만료)</small>`
                            : ""
                        }
                    </div>
                </div>
            `;

      // 2. Stats Cards (Row 3: Bookmarks, Review, My Note, Progress)
      // Buttons removed, cards are clickable.
      const statsHtml = `
                <div class="ptg-dash-stats">
                    <div class="ptg-dash-card ptg-card-bookmark" data-url="/bookmark/">
                        <div class="ptg-card-icon">🔖</div>
                        <div class="ptg-card-content">
                            <h3>북마크</h3>
                            <p class="ptg-stat-value">${this.escapeHtml(
                              bookmarks?.count ?? 0
                            )} <span class="ptg-stat-unit">문제</span></p>
                        </div>
                    </div>
                    <div class="ptg-dash-card ptg-card-review" data-url="/ptg_quiz/?needs_review=1&wrong_only=1">
                        <div class="ptg-card-icon">🔁</div>
                        <div class="ptg-card-content">
                            <h3>복습|Quiz</h3>
                            <p class="ptg-stat-value">${today_reviews} <span class="ptg-stat-unit">문제</span></p>
                        </div>
                    </div>
                    <div class="ptg-dash-card ptg-card-mynote" data-url="/mynote/">
                        <div class="ptg-card-icon">🗒️</div>
                        <div class="ptg-card-content">
                            <h3>마이노트</h3>
                            <p class="ptg-stat-value">${
                              mynote_count || 0
                            } <span class="ptg-stat-unit">문제</span></p>
                        </div>
                    </div>
                </div>
            `;

      // 3. Quick Actions (Row 2: Study, Mock Exam, Flashcards)
      const actionsHtml = `
                <div class="ptg-dash-actions">
                    <div class="ptg-action-grid">
                        <div class="ptg-action-card" data-url="${
                          (window.ptg_dashboard_vars &&
                            window.ptg_dashboard_vars.study_url) ||
                          "/ptg_study/"
                        }">
                            <div class="ptg-action-icon">📚</div>
                            <div class="ptg-action-info">
                                <span class="ptg-action-label">학습하기</span>
                                <div class="ptg-progress-bar ptg-progress-sm">
                                    <div class="ptg-progress-fill" style="width: ${studyPercent}%"></div>
                                </div>
                                <span class="ptg-action-percent">${studyPercent}%</span>
                            </div>
                        </div>
                        <div class="ptg-action-card" data-url="/ptg_quiz/">
                            <div class="ptg-action-icon">📝</div>
                            <div class="ptg-action-info">
                                <span class="ptg-action-label">모의고사</span>
                                <div class="ptg-progress-bar ptg-progress-sm">
                                    <div class="ptg-progress-fill" style="width: ${quizPercent}%"></div>
                                </div>
                                <span class="ptg-action-percent">${quizPercent}%</span>
                            </div>
                        </div>
                        <div class="ptg-action-card" data-url="/flashcards/">
                            <div class="ptg-action-icon">🃏</div>
                            <div class="ptg-action-info">
                                <span class="ptg-action-label">암기카드</span>
                                <div class="ptg-progress-bar ptg-progress-sm">
                                    <div class="ptg-progress-fill" style="width: ${flashcardPercent}%"></div>
                                </div>
                                <span class="ptg-action-percent">${flashcardPercent}%</span>
                            </div>
                        </div>
                        <div class="ptg-action-card" style="cursor: default;">
                            <div class="ptg-action-icon">📈</div>
                            <div class="ptg-action-info">
                                <span class="ptg-action-label">전체 진도율</span>
                                <div class="ptg-progress-bar ptg-progress-sm">
                                    <div class="ptg-progress-fill" style="width: ${
                                      progress.percent
                                    }%"></div>
                                </div>
                                <span class="ptg-action-percent">${
                                  progress.percent
                                }% (${progress.solved}/${progress.total})</span>
                            </div>
                        </div>
                    </div> 
                </div>
            `;

      // 5. Subject Learning Records
      const learningHtml = this.renderLearningRecords(learningRecords);

      // Combine all sections (Row 1: Welcome, Row 2: Actions, Row 3: Stats, Row 4: Learning)
      this.$container.html(
        welcomeHtml + actionsHtml + statsHtml + learningHtml
      );
      this.bindLearningTipModal();
    },

    renderLearningRecords: function (records) {
      const subjectSessions = Array.isArray(records.subjects)
        ? records.subjects
        : [];

      if (!subjectSessions.length) {
        return "";
      }

      const subjectHtml = `
                <div class="ptg-course-categories">
                    ${subjectSessions
                      .map((session) => this.buildSessionGroup(session))
                      .join("")}
                </div>
            `;

      return `
                <div class="ptg-dash-learning">
                    <div class="ptg-study-header ptg-learning-header">
                        <h2>🗝️ 과목 별 학습 기록</h2>
                        <button type="button" class="ptg-study-tip-trigger" data-learning-tip-open>[학습Tip]</button>
                    </div>
                    ${subjectHtml}
                    ${this.buildLearningTipModal()}
                </div>
            `;
    },

    buildSessionGroup: function (session) {
      if (!session || !Array.isArray(session.subjects)) {
        return "";
      }

      const subjectsHtml = session.subjects
        .map((subject) => this.buildSubjectCard(session.session, subject))
        .join("");

      return `
                <div class="ptg-session-group" data-session="${this.escapeHtml(
                  session.session
                )}">
                    <div class="ptg-session-grid">
                        ${subjectsHtml}
                    </div>
                </div>
            `;
    },

    buildSubjectCard: function (session, subject) {
      if (!subject) {
        return "";
      }

      const subList = Array.isArray(subject.subsubjects)
        ? subject.subsubjects
        : [];
      const description = subject.description
        ? `<p class="ptg-category-desc">${this.escapeHtml(
            subject.description
          )}</p>`
        : "";

      // 세부과목별 study와 quiz 총계 계산
      let totalStudy = 0;
      let totalQuiz = 0;
      if (subList.length > 0) {
        subList.forEach((sub) => {
          totalStudy += typeof sub.study === "number" ? sub.study : 0;
          totalQuiz += typeof sub.quiz === "number" ? sub.quiz : 0;
        });
      }

      // 헤더 오른쪽 끝에 총계 표시
      const totalCountsHtml = `<span class="ptg-category-total">Study(${totalStudy}) / Quiz(${totalQuiz})</span>`;

      const subsHtml = subList.length
        ? subList
            .map((sub) => {
              // 1100 Study 플러그인과 동일하게 rawurlencode (encodeURIComponent)로 인코딩해서 저장
              const encodedSubjectId = encodeURIComponent(sub.name);
              const studyCount = typeof sub.study === "number" ? sub.study : 0;
              const quizCount = typeof sub.quiz === "number" ? sub.quiz : 0;
              return `
                        <li class="ptg-subject-item" data-subject-id="${this.escapeHtml(
                          encodedSubjectId
                        )}">
                            <span class="ptg-subject-name">${this.escapeHtml(
                              sub.name
                            )}</span>
                            <span class="ptg-subject-counts">(${studyCount}/${quizCount})</span>
                        </li>
                    `;
            })
            .join("")
        : '<li class="ptg-subject-item is-empty">데이터가 없습니다.</li>';

      return `
                <section class="ptg-category" data-category-id="${this.escapeHtml(
                  subject.id
                )}">
                    <header class="ptg-category-header">
                        <h4 class="ptg-category-title">
                            <span class="ptg-session-badge">${this.escapeHtml(
                              session
                            )}교시</span>
                            <span class="ptg-category-name">${this.escapeHtml(
                              subject.name
                            )}</span>
                            ${totalCountsHtml}
                        </h4>
                        ${description}
                    </header>
                    <ul class="ptg-subject-list ptg-subject-list--stack">
                        ${subsHtml}
                    </ul>
                </section>
            `;
    },

    buildLearningTipModal: function () {
      return `
                <div id="ptg-learning-tip-modal" class="ptg-learning-tip-modal" aria-hidden="true">
                    <div class="ptg-learning-tip-backdrop" data-learning-tip-close></div>
                    <div class="ptg-learning-tip-dialog" role="dialog" aria-modal="true">
                        <div class="ptg-learning-tip-header">
                            <h3>과목 별 학습 기록 안내</h3>
                            <button type="button" class="ptg-learning-tip-close" data-learning-tip-close aria-label="닫기">&times;</button>
                        </div>
                        <div class="ptg-learning-tip-body">
                            <section>
                                <h4>📚 데이터 확인 방법</h4>
                                <ul>
                                    <li>각 세부과목 오른쪽의 <strong>Study</strong>/<strong>Quiz</strong> 수치로 학습 빈도를 확인하세요.</li>
                                    <li>최근 학습 데이터 기준으로 업데이트되며, 학습 시 즉시 집계됩니다.</li>
                                </ul>
                            </section>
                            <section>
                                <h4>🎯 활용 팁</h4>
                                <ul>
                                    <li>Study 대비 Quiz 비율을 보고 복습이 필요한 세부과목을 파악하세요.</li>
                                    <li>어려운 과목은 암기카드나 마이노트로 연결하여 반복 학습하세요.</li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </div>
            `;
    },

    bindLearningTipModal: function () {
      const $modal = this.$container.find("#ptg-learning-tip-modal");
      if (!$modal.length) {
        return;
      }

      this.$container.off("click.dashboardTip", "[data-learning-tip-open]");
      this.$container.on(
        "click.dashboardTip",
        "[data-learning-tip-open]",
        function (e) {
          e.preventDefault();
          $modal.addClass("is-open").attr("aria-hidden", "false");
        }
      );

      this.$container.off(
        "click.dashboardTipClose",
        "[data-learning-tip-close]"
      );
      this.$container.on(
        "click.dashboardTipClose",
        "[data-learning-tip-close]",
        function (e) {
          e.preventDefault();
          $modal.removeClass("is-open").attr("aria-hidden", "true");
        }
      );

      this.$container.off("click.dashboardDayToggle", ".ptg-learning-date-row");
      this.$container.on(
        "click.dashboardDayToggle",
        ".ptg-learning-date-row",
        function (e) {
          e.preventDefault();
          const $row = $(this);
          const $day = $row.closest(".ptg-learning-day");
          const $content = $day.find(".ptg-learning-day-content");
          const isOpen = $day.hasClass("is-open");

          $day.toggleClass("is-open");
          $row.attr("aria-expanded", !isOpen);
          $content.stop(true, true).slideToggle(160);
        }
      );
    },

    escapeHtml: function (text) {
      if (text === null || text === undefined) return "";
      const safeText = String(text);
      return safeText
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    formatName: function (name) {
      const safe = this.escapeHtml(name || "");
      const parts = safe.trim().split(/\s+/).filter(Boolean);

      if (parts.length === 2) {
        return `${parts[1]}${parts[0]}`;
      }
      return safe || "학습자";
    },

    getGreeting: function () {
      const englishGreetings = [
        {
          text: "✨ BE THE LIGHT. KEEP GOING.",
          translation: "빛이 되어라. 멈추지 말고 계속 나아가라.",
        },
        {
          text: "🧭 LIFE IS A JOURNEY, NOT THE DESTINATION.",
          translation: "인생은 여정이지, 목적지가 아니다.",
        },
        {
          text: '⏰ "If you want to make your dream come true, the first thing you have to do is to wake up."',
          translation:
            "꿈을 이루고 싶다면, 가장 먼저 해야 할 일은 잠에서 깨어나는 것이다.",
        },
        {
          text: '🔥 "If you plant fire in your heart, it will burn against the wind."',
          translation:
            "당신의 가슴 속에 불꽃을 심는다면, 그 불꽃은 바람에 맞서 타오를 것이다.",
        },
        {
          text: '💖 "Give up worrying about what others think of you. What they think isn\'t important. What is important is how you feel about yourself."',
          translation:
            "남들이 당신을 어떻게 생각할지 걱정하는 것을 멈추세요. 중요한 것은 당신이 자신에 대해 어떻게 느끼느냐입니다.",
        },
        {
          text: '🌌 "Something to accept the face of the arrogance you have to lose to recognize own fantasy."',
          translation:
            "자신의 환상을 깨닫기 위해 버려야 할 오만함의 민낯을 받아들이세요.",
        },
      ];

      const koreanGreetings = [
        { text: "학습을 이어가볼까요? 👋" },
        { text: "오늘도 화이팅입니다! 💪" },
        { text: "새로운 도전을 시작해볼까요? 🚀" },
        { text: "꾸준한 학습이 답입니다! 📚" },
        { text: "한 걸음씩 나아가요! 🎯" },
        { text: "오늘의 목표를 달성해봐요! ⭐" },
        { text: "지금이 바로 시작할 때입니다! 🌟" },
        { text: "작은 실천이 큰 변화를 만듭니다! ✨" },
        { text: "오늘도 성장하는 하루가 되길! 🌱" },
        { text: "포기하지 않는 당신이 멋져요! 💎" },
        { text: "매일 조금씩, 꾸준히! 📖" },
        { text: "도전하는 모습이 아름답습니다! 🌈" },
        { text: "오늘도 한 문제씩 풀어봐요! 🎓" },
        { text: "노력하는 당신을 응원합니다! 👏" },
        { text: "작은 시작이 큰 성과를 만듭니다! 🎁" },
      ];

      const isEnglishTurn = this.greetingCycleIndex % 3 === 0;
      this.greetingCycleIndex = (this.greetingCycleIndex + 1) % 3;

      const pool = isEnglishTurn ? englishGreetings : koreanGreetings;
      const randomIndex = Math.floor(Math.random() * pool.length);
      const selection = pool[randomIndex];

      return {
        text: selection.text,
        translation: selection.translation || "",
        isEnglish: Boolean(selection.translation),
      };
    },
  };

  $(document).ready(function () {
    Dashboard.init();
  });
})(jQuery);
