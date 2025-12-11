(function ($) {
  "use strict";

  const Bookmarks = {
    page: 1,
    perPage: 5,
    totalPages: 0,
    isLoading: false,
    $container: null,
    cardCounter: 0, // 카드 번호 카운터

    init: function () {
      this.$container = $("#ptg-bookmarks-app");
      if (!this.$container.length) {
        return;
      }

      this.loadBookmarks();
      this.bindEvents();
    },

    bindEvents: function () {
      const self = this;

      // 선택지 보기 토글
      this.$container.on("click", ".ptg-toggle-options", function (e) {
        e.preventDefault();
        const $card = $(this).closest(".ptg-bookmark-card");
        const $options = $card.find(".ptg-bookmark-options");
        const $btn = $(this);

        $options.toggleClass("is-visible");
        $btn.toggleClass("is-active");
        $btn.text(
          $options.hasClass("is-visible") ? "선택지 숨기기" : "선택지 보기"
        );
      });

      // 문제풀이 보기 토글
      this.$container.on("click", ".ptg-toggle-explanation", function (e) {
        e.preventDefault();
        const $card = $(this).closest(".ptg-bookmark-card");
        const $explanation = $card.find(".ptg-bookmark-explanation");
        const $btn = $(this);

        $explanation.toggleClass("is-visible");
        $btn.toggleClass("is-active");
        $btn.text(
          $explanation.hasClass("is-visible")
            ? "문제풀이 숨기기"
            : "문제풀이 보기"
        );
      });

      // 북마크 문제풀기 버튼
      this.$container.on("click", ".ptg-btn-quiz-start", function (e) {
        e.preventDefault();
        self.startQuiz();
      });

      // 더보기 버튼
      this.$container.on("click", ".ptg-btn-load-more", function (e) {
        e.preventDefault();
        if (!self.isLoading && self.page < self.totalPages) {
          self.page++;
          self.loadBookmarks(true);
        } else if (self.page >= self.totalPages) {
          alert("마지막 북마크 입니다.");
        }
      });

      // MAP 링크 클릭 이벤트
      this.$container.on("click", ".ptg-map-link", function (e) {
        e.preventDefault();
        self.showMapPopup();
      });

      // 무한 스크롤 (선택적)
      let scrollTimeout;
      $(window).on("scroll", function () {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function () {
          if (!self.isLoading && self.page < self.totalPages) {
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            const documentHeight = $(document).height();

            if (scrollTop + windowHeight >= documentHeight - 100) {
              self.page++;
              self.loadBookmarks(true);
            }
          }
        }, 100);
      });
    },

    loadBookmarks: function (append) {
      if (this.isLoading) {
        return;
      }

      this.isLoading = true;

      if (!append) {
        this.page = 1;
        this.cardCounter = 0; // 카드 번호 초기화
        this.$container.html(
          '<div class="ptg-bookmarks-loading">북마크 목록을 불러오는 중...</div>'
        );
      }

      const self = this;
      const restUrl =
        (window.ptg_bookmarks_vars && window.ptg_bookmarks_vars.rest_url) ||
        "/wp-json/ptg-bookmarks/v1/";
      const nonce =
        (window.ptg_bookmarks_vars && window.ptg_bookmarks_vars.nonce) || "";

      $.ajax({
        url: restUrl + "bookmarks",
        method: "GET",
        data: {
          page: this.page,
          per_page: this.perPage,
        },
        headers: {
          "X-WP-Nonce": nonce,
        },
        success: function (response) {
          // WordPress REST API는 응답을 래핑할 수 있으므로 확인
          let data = response;
          if (response && response.data) {
            data = response.data;
          }

          // 디버깅 로그
          console.log("북마크 API 응답:", {
            response: response,
            data: data,
            items: data.items,
            itemsLength: data.items ? data.items.length : 0,
            total: data.total,
            total_pages: data.total_pages,
          });

          if (
            !data ||
            !data.items ||
            (Array.isArray(data.items) && data.items.length === 0)
          ) {
            console.log("북마크 데이터 없음 - renderEmpty 호출");
            self.renderEmpty();
            self.isLoading = false;
            return;
          }

          self.totalPages = data.total_pages || 0;

          if (append && data.items && data.items.length > 0) {
            const html = self.renderBookmarks(data.items);
            self.$container.find(".ptg-bookmarks-list").append(html);
          } else {
            self.renderPage(data);
          }

          self.isLoading = false;
        },
        error: function (xhr, status, error) {
          console.error("북마크 목록 로드 실패:", {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            url: xhr.responseURL || restUrl + "bookmarks",
          });

          let errorMessage = "북마크 목록을 불러오는데 실패했습니다.";
          if (xhr.status === 404) {
            errorMessage =
              "북마크 API를 찾을 수 없습니다. 플러그인이 활성화되어 있는지 확인해주세요.";
          } else if (xhr.status === 401) {
            errorMessage = "로그인이 필요합니다.";
          } else if (xhr.status === 403) {
            errorMessage = "접근 권한이 없습니다.";
          }

          self.$container.html(
            '<div class="ptg-bookmarks-empty">' + errorMessage + "</div>"
          );
          self.isLoading = false;
        },
      });
    },

    renderPage: function (data) {
      if (!data || !data.items || data.items.length === 0) {
        this.renderEmpty();
        return;
      }

      const dashboardUrl =
        (window.ptg_bookmarks_vars &&
          window.ptg_bookmarks_vars.dashboard_url) ||
        "/";
      const html = `
                <div class="ptg-bookmarks-header">
                    <h1>북마크 문제 모아보기</h1>
                    <div class="ptg-header-actions">
                        <button class="ptg-btn-quiz-start">북마크 문제풀기</button>
                        <a href="${this.escapeHtml(
                          dashboardUrl
                        )}" class="ptg-dashboard-link">학습현황</a>
                    </div>
                </div>
                <div class="ptg-bookmarks-info">
                    모든 북마크 문제가 공통 <span class="ptg-map-link">MAP</span> 순서에 따라 과목별로 랜덤하게 섞여서 표시됩니다.
                </div>
                <div class="ptg-bookmarks-list">
                    ${this.renderBookmarks(data.items)}
                </div>
                ${this.renderLoadMore(data.total_pages)}
            `;

      this.$container.html(html);
    },

    renderBookmarks: function (items) {
      if (!items || items.length === 0) {
        return "";
      }

      const self = this;
      return items
        .map(function (item) {
          return self.renderBookmarkCard(item);
        })
        .join("");
    },

    renderBookmarkCard: function (item) {
      this.cardCounter++; // 카드 번호 증가
      const bookmarkedDate = this.formatDate(item.bookmarked_at);
      const optionsHtml = this.renderOptions(item.options);
      const explanationHtml = this.renderExplanation(
        item.answer,
        item.explanation
      );

      // 과목/세부과목 표시
      let subjectInfo = "";
      if (item.subject || item.subsubject) {
        const subject = item.subject || "";
        const subsubject = item.subsubject || "";
        if (subject && subsubject) {
          subjectInfo = `<span class="ptg-bookmark-subject">${this.escapeHtml(
            subject
          )} / ${this.escapeHtml(subsubject)}</span>`;
        } else if (subject) {
          subjectInfo = `<span class="ptg-bookmark-subject">${this.escapeHtml(
            subject
          )}</span>`;
        } else if (subsubject) {
          subjectInfo = `<span class="ptg-bookmark-subject">${this.escapeHtml(
            subsubject
          )}</span>`;
        }
      }

      return `
                <div class="ptg-bookmark-card">
                    <div class="ptg-bookmark-card-header">
                        <div class="ptg-bookmark-meta">
                            <span class="ptg-bookmark-date">${this.escapeHtml(
                              bookmarkedDate
                            )}</span>
                            ${subjectInfo}
                            <span class="ptg-bookmark-id">${this.escapeHtml(
                              item.question_id_display
                            )}</span>
                        </div>
                        <button class="ptg-bookmark-toggle ptg-toggle-options">선택지 보기</button>
                    </div>
                    <div class="ptg-bookmark-question-text"><span class="ptg-bookmark-number">${
                      this.cardCounter
                    }.</span> ${this.escapeHtml(item.question_text)}</div>
                    <div class="ptg-bookmark-options">
                        ${optionsHtml}
                    </div>
                    <div style="margin-top: 12px;">
                        <button class="ptg-bookmark-toggle ptg-toggle-explanation">문제풀이 보기</button>
                    </div>
                    <div class="ptg-bookmark-explanation">
                        ${explanationHtml}
                    </div>
                </div>
            `;
    },

    renderOptions: function (options) {
      if (!options || options.length === 0) {
        return '<div class="ptg-bookmark-option">선택지 정보가 없습니다.</div>';
      }

      const self = this;
      return options
        .map(function (option, index) {
          return `<div class="ptg-bookmark-option">${self.escapeHtml(
            option
          )}</div>`;
        })
        .join("");
    },

    renderExplanation: function (answer, explanation) {
      let html = "";
      if (answer) {
        html += `<div class="ptg-bookmark-answer">정답: ${this.escapeHtml(
          answer
        )}</div>`;
      }
      if (explanation) {
        html += `<div class="ptg-bookmark-explanation-content">${this.escapeHtml(
          explanation
        )}</div>`;
      }
      if (!html) {
        html =
          '<div class="ptg-bookmark-explanation-content">해설 정보가 없습니다.</div>';
      }
      return html;
    },

    renderLoadMore: function (totalPages) {
      if (this.page >= totalPages) {
        return "";
      }

      return `
                <div class="ptg-bookmarks-load-more">
                    <button class="ptg-btn-load-more">더보기</button>
                </div>
            `;
    },

    renderEmpty: function () {
      const dashboardUrl =
        (window.ptg_bookmarks_vars &&
          window.ptg_bookmarks_vars.dashboard_url) ||
        "/";
      const html = `
                <div class="ptg-bookmarks-header">
                    <h1>북마크 문제 모아보기</h1>
                    <div class="ptg-header-actions">
                        <button class="ptg-btn-quiz-start">북마크 문제풀기</button>
                        <a href="${this.escapeHtml(
                          dashboardUrl
                        )}" class="ptg-dashboard-link">학습현황</a>
                    </div>
                </div>
                <div class="ptg-bookmarks-empty">
                    북마크된 문제가 없습니다. 
                </div>
            `;
      this.$container.html(html);
    },

    startQuiz: function () {
      // 북마크 문제풀기 로직
      // 1200- 퀴즈 플러그인으로 이동하되, bookmarked=1 조건으로 필터링
      const quizUrl =
        (window.ptg_bookmarks_vars && window.ptg_bookmarks_vars.quiz_url) ||
        "/ptg_quiz/";

      // URL 생성 (기존 쿼리 파라미터 유지)
      let url = quizUrl;
      if (url.indexOf("?") === -1) {
        url += "?bookmarked=1";
      } else {
        url += "&bookmarked=1";
      }

      // 안전한 페이지 이동 (에러 방지)
      try {
        // replace를 사용하여 히스토리에 남기지 않음 (뒤로가기 시 북마크 페이지로 돌아가지 않도록)
        window.location.replace(url);
      } catch (e) {
        // 에러 발생 시 대체 방법
        console.warn("[PTG Bookmarks] 페이지 이동 중 에러:", e);
        window.location.href = url;
      }
    },

    formatDate: function (dateString) {
      if (!dateString) {
        return "";
      }

      const date = new Date(dateString);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");

      return `${year}-${month}-${day}`;
    },

    escapeHtml: function (text) {
      if (!text) {
        return "";
      }

      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },

    showMapPopup: function () {
      // 공통 팝업 유틸리티 사용 (내용은 중앙 저장소에서 자동 가져옴)
      if (
        typeof window.PTGTips === "undefined" ||
        typeof window.PTGTips.show !== "function"
      ) {
        console.warn(
          "[PTG Bookmarks] 공통 팝업 유틸리티가 아직 로드되지 않았습니다. 잠시 후 다시 시도해주세요."
        );
        // 잠시 후 다시 시도 (최대 3초)
        let retryCount = 0;
        const maxRetries = 30;
        const self = this;
        const checkInterval = setInterval(function () {
          retryCount++;
          if (
            typeof window.PTGTips !== "undefined" &&
            typeof window.PTGTips.show === "function"
          ) {
            clearInterval(checkInterval);
            self.showMapPopup(); // 재시도
          } else if (retryCount >= maxRetries) {
            clearInterval(checkInterval);
            alert("팝업을 표시할 수 없습니다. 페이지를 새로고침해주세요.");
          }
        }, 100);
        return;
      }

      // 중앙 저장소에서 팝업 내용을 자동으로 가져와서 표시 (옵션 없이 호출)
      window.PTGTips.show("map-tip");
    },
  };

  // DOM ready
  $(document).ready(function () {
    Bookmarks.init();
  });

  // 전역으로 노출 (다른 스크립트에서 접근 가능)
  window.PTGBookmarks = Bookmarks;
})(jQuery);
