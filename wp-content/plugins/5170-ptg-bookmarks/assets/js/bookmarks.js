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

        // 버튼으로 스크롤
        if ($explanation.hasClass("is-visible")) {
          setTimeout(function() {
            const btnOffset = $btn.offset();
            if (btnOffset) {
              $("html, body").animate({
                scrollTop: btnOffset.top - 20
              }, 300);
            }
          }, 100);
        }
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
                    <h1>북마크</h1>
                    <div class="ptg-header-actions">
                        <button class="ptg-btn-quiz-start">북마크 문제풀기</button>
                        <a href="${this.escapeHtml(
                          dashboardUrl
                        )}" class="ptg-dashboard-link ptg-header-btn">학습현황</a>
                    </div>
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

      // 세부과목만 표시
      let subjectInfo = "";
      if (item.subsubject) {
        subjectInfo = `<span class="ptg-bookmark-subject">${this.escapeHtml(
          item.subsubject
        )}</span>`;
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
                    <h1>북마크</h1>
                    <div class="ptg-header-actions">
                        <button class="ptg-btn-quiz-start">북마크 문제풀기</button>
                        <a href="${this.escapeHtml(
                          dashboardUrl
                        )}" class="ptg-dashboard-link ptg-header-btn">학습현황</a>
                    </div>
                </div>
                <div class="ptg-bookmarks-empty">
                    북마크된 문제가 없습니다. 
                </div>
            `;
      this.$container.html(html);
    },

    startQuiz: function () {
      // 북마크 문제풀기 → 실전 Quiz로 이동
      // 요청: 모든 북마크 문제를 바로 시작 (limit=0, auto_start=1)
      const quizUrl =
        (window.ptg_bookmarks_vars && window.ptg_bookmarks_vars.quiz_url) ||
        "/ptg_quiz/";

      try {
        const targetUrl = new URL(quizUrl, window.location.origin);
        targetUrl.searchParams.set("bookmarked", "1");
        targetUrl.searchParams.set("limit", "0"); // 북마크 전체
        targetUrl.searchParams.set("auto_start", "1"); // 바로 시작

        // replace로 이동해 뒤로가기 시 북마크 페이지로 돌아오지 않도록 처리
        window.location.replace(targetUrl.toString());
      } catch (e) {
        // new URL이 실패할 경우의 안전한 폴백
        console.warn("[PTG Bookmarks] 페이지 이동 중 에러:", e);
        let url = quizUrl;
        const hasQuery = url.indexOf("?") !== -1;
        url += (hasQuery ? "&" : "?") + "bookmarked=1&limit=0&auto_start=1";
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
  };

  // DOM ready
  $(document).ready(function () {
    Bookmarks.init();
  });

  // 전역으로 노출 (다른 스크립트에서 접근 가능)
  window.PTGBookmarks = Bookmarks;
})(jQuery);
