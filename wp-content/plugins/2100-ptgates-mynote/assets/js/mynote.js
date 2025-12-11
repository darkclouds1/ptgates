(function ($) {
  "use strict";

  const MyNote = {
    init: function () {
      this.container = $("#ptg-mynote-app");
      // 컨테이너가 없으면 초기화하지 않음
      if (!this.container.length) {
        return;
      }

      this.config = window.ptgMyNote || {};
      // 필수 설정이 없으면 초기화하지 않음
      if (!this.config.restUrl || !this.config.nonce) {
        console.warn(
          "PTG MyNote: 필수 설정이 없습니다. 숏코드가 올바르게 렌더링되었는지 확인하세요."
        );
        return;
      }

      this.currentView = "list"; // 'list' or 'detail'
      this.currentQuestionId = null;
      this.currentMemo = null;
      this.sortBy = "date"; // 'date', 'subject', 'subsubject'
      this.sortOrder = "desc"; // 'asc', 'desc'
      this.displayMode = (this.config.viewMode || "card").toLowerCase();
      this.currentPage = 1;
      this.pageSize = this.displayMode === "card" ? 10 : 50;
      this.hasMore = true;
      this.isLoadingMore = false;
      this.infiniteScrollHandler = null;
      this.infiniteEndShown = false;

      this.bindEvents();
      this.loadMemos({ reset: true });
    },

    bindEvents: function () {
      const self = this;

      // 정렬 버튼
      this.container.on("click", ".ptg-sort-btn", function () {
        const sort = $(this).data("sort");
        if (self.sortBy === sort) {
          self.sortOrder = self.sortOrder === "asc" ? "desc" : "asc";
        } else {
          self.sortBy = sort;
          self.sortOrder = "desc";
        }
        self.currentPage = 1;
        self.hasMore = true;
        self.infiniteEndShown = false;
        self.loadMemos({ reset: true });
      });

      // 검색
      this.container.on("input", "#ptg-memo-search", function () {
        self.currentPage = 1;
        self.hasMore = true;
        self.infiniteEndShown = false;
        self.loadMemos({ reset: true });
      });

      // 삭제
      this.container.on("click", ".ptg-memo-delete", function (e) {
        e.stopPropagation();
        if (confirm("정말 삭제하시겠습니까?")) {
          const id = $(this).data("id");
          self.deleteMemo(id);
        }
      });

      // 문제보기 버튼
      this.container.on("click", ".ptg-view-question", function (e) {
        e.stopPropagation();
        const questionId = $(this).data("question-id");
        const memoContent = $(this).data("memo-content") || "";
        self.showQuestionDetail(questionId, memoContent);
      });

      // 카드형 메모 편집 버튼
      this.container.on("click", ".ptg-memo-edit", function (e) {
        e.preventDefault();
        const $card = $(this).closest(".ptg-memo-card");
        self.enterMemoEditMode($card);
      });

      // 편집 필드 포커스 아웃 시 자동 저장
      this.container.on("blur", ".ptg-memo-edit-field", function () {
        const $field = $(this);
        const $card = $field.closest(".ptg-memo-card");
        const memoId = $card.data("memo-id");
        const newContent = $field.val();
        const original = $field.data("original") || "";

        if (!memoId) {
          self.showCardStatus($card, "메모 ID를 찾을 수 없습니다.", "error");
          return;
        }

        if (newContent.trim() === original.trim()) {
          self.exitMemoEditMode($card, original);
          self.showCardStatus($card, "변경 사항이 없습니다.", "info");
          return;
        }

        self.saveMemoContent(memoId, newContent, $card);
      });

      // 닫기 버튼 (상세 화면)
      this.container.on("click", "#ptg-mynote-close", function () {
        self.showList();
      });

      this.container.on("input", ".ptg-memo-edit-field", function () {
        self.autoResizeTextarea($(this));
      });
    },

    loadMemos: function (options = {}) {
      const self = this;
      const append = options.append || false;
      const reset = options.reset || false;

      // 안전성 체크: 컨테이너와 설정이 없으면 실행하지 않음
      if (!this.container || !this.container.length) {
        return;
      }
      if (!this.config || !this.config.restUrl || !this.config.nonce) {
        return;
      }

      if (this.displayMode === "card") {
        this.pageSize = 10;
      } else {
        this.pageSize = 50;
      }

      if (reset) {
        this.currentPage = 1;
        this.hasMore = true;
        this.infiniteEndShown = false;
        this.hideEndMessage();
      }

      if (this.displayMode === "card" && append) {
        if (this.isLoadingMore || !this.hasMore) {
          return;
        }
      }

      const listContainer = this.container.find(".ptg-memo-list");
      if (!listContainer.length) {
        return;
      }

      if (!append) {
        listContainer.html('<div class="ptg-loading">로딩 중...</div>');
      } else {
        this.showInfiniteLoader();
      }

      const search = this.container.find("#ptg-memo-search").val();
      this.isLoadingMore = true;

      $.ajax({
        url: self.config.restUrl + "memos",
        method: "GET",
        data: {
          search: search || "",
          sort: self.sortBy,
          order: self.sortOrder,
          limit: self.pageSize,
          offset: (self.currentPage - 1) * self.pageSize,
        },
        beforeSend: function (xhr) {
          if (self.config.nonce) {
            xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
          }
        },
        success: function (response) {
          if (self.container && self.container.length) {
            self.renderList(response, append);

            const itemsLength = (response.items || []).length;
            const isCardMode = self.displayMode === "card";

            if (isCardMode && itemsLength > 0) {
              self.currentPage += 1;
            }

            if (isCardMode) {
              if (itemsLength < self.pageSize) {
                self.hasMore = false;
                self.showEndMessage();
              } else {
                self.hasMore = true;
              }
            }
          }
        },
        error: function (xhr, status, error) {
          // 컨테이너가 여전히 존재하는지 확인 후 에러 표시
          if (self.container && self.container.length && listContainer.length) {
            // 401, 403 에러는 조용히 처리 (로그인 필요)
            if (xhr.status === 401 || xhr.status === 403) {
              listContainer.html(
                '<div class="ptg-error">로그인이 필요합니다.</div>'
              );
            } else {
              listContainer.html(
                '<div class="ptg-error">메모를 불러오는데 실패했습니다.</div>'
              );
            }
            // 개발 환경에서만 콘솔 에러 출력
            if (
              window.console &&
              console.error &&
              typeof console.error === "function"
            ) {
              console.error("[PTG MyNote] API 호출 실패:", {
                status: xhr.status,
                statusText: xhr.statusText,
                url: self.config.restUrl + "memos",
              });
            }
          }
        },
        complete: function () {
          self.isLoadingMore = false;
          if (append) {
            self.hideInfiniteLoader();
          }
          if (self.displayMode === "card") {
            self.maybeTriggerImmediateLoad();
          }
        },
      });
    },

    renderList: function (data, append = false) {
      if (this.displayMode === "card") {
        this.renderCardList(data, append);
        return;
      }

      const listContainer = this.container.find(".ptg-memo-list");
      const items = data.items || [];
      const total = data.total || 0;

      if (!items || items.length === 0) {
        listContainer.html(
          '<div class="ptg-empty">저장된 메모가 없습니다.</div>'
        );
        return;
      }

      // 테이블 헤더
      let html = `
                <table class="ptg-memo-table">
                    <thead>
                        <tr>
                            <th>번호</th>
                            <th>문제ID</th>
                            <th>
                                <button class="ptg-sort-btn ${
                                  this.sortBy === "subject" ? "active" : ""
                                }" data-sort="subject">
                                    과목 ${
                                      this.sortBy === "subject"
                                        ? this.sortOrder === "asc"
                                          ? "↑"
                                          : "↓"
                                        : ""
                                    }
                                </button>
                            </th>
                            <th>
                                <button class="ptg-sort-btn ${
                                  this.sortBy === "subsubject" ? "active" : ""
                                }" data-sort="subsubject">
                                    세부과목 ${
                                      this.sortBy === "subsubject"
                                        ? this.sortOrder === "asc"
                                          ? "↑"
                                          : "↓"
                                        : ""
                                    }
                                </button>
                            </th>
                            <th>메모 내용</th>
                            <th>문제보기</th>
                            <th>
                                <button class="ptg-sort-btn ${
                                  this.sortBy === "date" ? "active" : ""
                                }" data-sort="date">
                                    등록일자 ${
                                      this.sortBy === "date"
                                        ? this.sortOrder === "asc"
                                          ? "↑"
                                          : "↓"
                                        : ""
                                    }
                                </button>
                            </th>
                            <th>삭제</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

      // 테이블 행
      items.forEach(
        function (memo, index) {
          const rowNum = (this.currentPage - 1) * this.pageSize + index + 1;
          const mainSubject = memo.main_subject || "-";
          const subSubject = memo.sub_subject || "-";
          const memoContent = memo.content || "";
          const updatedAt = memo.updated_at
            ? memo.updated_at.split(" ")[0]
            : "-";
          const questionId = memo.question_id;

          html += "<tr>";
          html += '<td style="text-align: center;">' + rowNum + "</td>";
          html +=
            '<td style="text-align: center;">' + (questionId || "-") + "</td>";
          html +=
            '<td style="text-align: center;">' +
            escapeHtml(mainSubject) +
            "</td>";
          html +=
            '<td style="text-align: center;">' +
            escapeHtml(subSubject) +
            "</td>";
          html +=
            '<td class="ptg-memo-content-cell" style="text-align: left;">' +
            escapeHtml(memoContent) +
            "</td>";
          html +=
            '<td style="text-align: center;"><button class="ptg-btn ptg-btn-primary ptg-view-question" data-question-id="' +
            questionId +
            '" data-memo-content="' +
            escapeHtml(memoContent) +
            '">문제보기</button></td>';
          html += '<td style="text-align: center;">' + updatedAt + "</td>";
          html +=
            '<td style="text-align: center;"><button class="ptg-btn ptg-btn-danger ptg-memo-delete" data-id="' +
            memo.id +
            '">삭제</button></td>';
          html += "</tr>";
        }.bind(this)
      );

      html += "</tbody></table>";

      // 페이지네이션
      const totalPages = Math.ceil(total / this.pageSize);
      if (totalPages > 1) {
        html += '<div class="ptg-pagination">';
        if (this.currentPage > 1) {
          html +=
            '<button class="ptg-page-btn" data-page="' +
            (this.currentPage - 1) +
            '">이전</button>';
        }
        html +=
          '<span class="ptg-page-info">' +
          this.currentPage +
          " / " +
          totalPages +
          "</span>";
        if (this.currentPage < totalPages) {
          html +=
            '<button class="ptg-page-btn" data-page="' +
            (this.currentPage + 1) +
            '">다음</button>';
        }
        html += "</div>";
      }

      listContainer.html(html);

      // 페이지네이션 이벤트
      this.container.off("click", ".ptg-page-btn").on(
        "click",
        ".ptg-page-btn",
        function () {
          const page = parseInt($(this).data("page"));
          if (page > 0) {
            this.currentPage = page;
            this.loadMemos();
          }
        }.bind(this)
      );
    },

    renderCardList: function (data, append) {
      const listContainer = this.container.find(".ptg-memo-list");
      const items = data.items || [];

      if (!append && (!items || items.length === 0)) {
        listContainer.html(
          '<div class="ptg-empty">저장된 메모가 없습니다.</div>'
        );
        this.hasMore = false;
        this.showEndMessage();
        this.destroyInfiniteScroll();
        return;
      }

      if (!append) {
        listContainer.html('<div class="ptg-memo-card-grid"></div>');
      } else if (!listContainer.find(".ptg-memo-card-grid").length) {
        listContainer.append('<div class="ptg-memo-card-grid"></div>');
      }

      const grid = listContainer.find(".ptg-memo-card-grid").first();

      const cardsHtml = items
        .map((memo) => {
          const mainSubject = memo.main_subject || "-";
          const subSubject = memo.sub_subject || "-";
          const memoContent = memo.content || "";
          const updatedAt = memo.updated_at
            ? memo.updated_at.split(" ")[0]
            : "-";
          const questionId = memo.question_id;
          const memoIdLabel = questionId ? `id-${questionId}` : "id-없음";

          return `
                    <article class="ptg-memo-card" data-memo-id="${
                      memo.id || ""
                    }">
                        <header class="ptg-memo-card-header">
                            <div class="ptg-memo-card-hgroup">
                                <span class="ptg-memo-card-subject">${escapeHtml(
                                  mainSubject
                                )}</span>
                                <span class="ptg-memo-card-divider">|</span>
                                <span class="ptg-memo-card-subsubject">${escapeHtml(
                                  subSubject
                                )}</span>
                            </div>
                            <span class="ptg-memo-card-id">
                                    ${memoIdLabel}
                                    <button class="ptg-memo-delete ptg-btn-icon" data-id="${
                                      memo.id
                                    }" title="메모 삭제" style="background:none;border:none;cursor:pointer;margin-left:6px;padding:0;color:#dc3545;vertical-align:middle;display:inline-flex;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                </span>
                        </header>
                        <div class="ptg-memo-card-body">
                            <p class="ptg-memo-card-content">${escapeHtml(
                              memoContent
                            )}</p>
                        </div>
                        <div class="ptg-card-status" aria-live="polite"></div>
                        <footer class="ptg-memo-card-footer">
                            <span class="ptg-memo-card-date">등록일자 ${updatedAt}</span>
                            <div class="ptg-card-actions">
                                <button class="ptg-btn ptg-btn-outline ptg-memo-edit">메모 편집</button>
                                <button class="ptg-btn ptg-btn-primary ptg-view-question" data-question-id="${questionId}" data-memo-content="${escapeHtml(
            memoContent
          )}">문제보기</button>
                                <button class="ptg-btn ptg-btn-danger ptg-memo-delete" data-id="${
                                  memo.id
                                }">삭제</button>
                            </div>
                        </footer>
                    </article>
                `;
        })
        .join("");

      grid.append(cardsHtml);
      this.setupInfiniteScroll();
    },

    showQuestionDetail: function (questionId, memoContent) {
      const self = this;
      this.currentQuestionId = questionId;
      this.currentMemo = memoContent;

      // 1100 study의 API를 사용하여 문제 상세 정보 가져오기
      // 단일 문제를 세부과목 단일 조회 형식으로 변환하여 표시
      // 우선 간단하게 1100 study의 renderLessons 함수를 재사용

      // 1100 study의 API 호출
      const rest = window.PTGStudy || {};
      const studyBaseUrl = rest.baseUrl || "/wp-json/ptg-study/v1/";

      // 문제 ID로 세부과목 찾기
      $.ajax({
        url: self.config.restUrl + "questions/" + questionId,
        method: "GET",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function (response) {
          if (response.question) {
            self.renderQuestionDetail(
              response.question,
              response.memo || memoContent
            );
          } else {
            alert("문제를 불러올 수 없습니다.");
          }
        },
        error: function () {
          alert("문제를 불러오는데 실패했습니다.");
        },
      });
    },

    renderQuestionDetail: function (question, memoContent) {
      // 공통 컴포넌트 PTGQuestionViewer 사용
      if (typeof window.PTGQuestionViewer === "undefined") {
        console.error(
          "PTGQuestionViewer is not loaded. Please ensure question-viewer.js is loaded."
        );
        alert("문제 보기 기능을 로드할 수 없습니다.");
        return;
      }

      const self = this;
      const container = this.container;

      // 문제 데이터를 공통 컴포넌트 형식으로 변환
      const lesson = {
        id: question.question_id,
        title: "문제 #" + question.question_id,
        content: question.content,
        answer: question.answer,
        explanation: question.explanation,
        question_image: question.question_image || null,
        options: question.options || [], // 옵션이 있으면 사용
        category: {
          year: question.exam_year,
          session: question.exam_session,
          subject: question.subject,
        },
      };

      // 공통 컴포넌트로 문제 카드 렌더링
      const questionCardHtml = window.PTGQuestionViewer.renderQuestionCard(
        lesson,
        1,
        {
          showToolbar: true,
          showMemo: false,
          explanationSubject: question.subject || "",
        }
      );

      // 대시보드 URL 가져오기
      const dashboardUrl =
        (window.ptgMyNote && window.ptgMyNote.dashboardUrl) || "#";
      this.currentView = "detail";
      this.destroyInfiniteScroll();

      // 전체 HTML 구성 (헤더 포함)
      let html = `
                <div class="ptg-mynote-header">
                    <div class="ptg-mynote-header-left">
                        <h2>마이노트</h2>
                        <a href="${dashboardUrl}" class="ptg-btn-dashboard">
                            ← 학습현황으로 돌아가기
                        </a>
                    </div>
                    <div class="ptg-mynote-controls">
                        <button id="ptg-mynote-close" class="ptg-btn ptg-btn-secondary">목록으로 돌아가기</button>
                    </div>
                </div>
                <div class="ptg-mynote-detail">
                    <div class="ptg-lesson-view">
                        ${questionCardHtml}
                    </div>
                </div>
            `;

      container.html(html);

      // 공통 컴포넌트의 이벤트 핸들러 초기화
      window.PTGQuestionViewer.initEventHandlers(container);

      // 1100 study 툴바 초기화 (study-toolbar.js가 로드되어 있다면)
      const $lessonItem = container.find(
        '.ptg-lesson-item[data-lesson-id="' + lesson.id + '"]'
      );
      if ($lessonItem.length > 0) {
        const questionId = lesson.id;

        // 툴바 핸들러 설정
        if (
          typeof window.PTGStudyToolbar !== "undefined" &&
          window.PTGStudyToolbar.initToolbars
        ) {
          // 1100 study 툴바가 있으면 사용
          window.PTGStudyToolbar.initToolbars();
          window.PTGStudyToolbar.updateToolbarStatus(questionId);
          this.activateToolbarMemo($lessonItem, questionId);
        } else {
          // 툴바가 없으면 직접 이벤트 핸들러 연결
          this.setupToolbarHandlers($lessonItem, questionId);
          this.renderFallbackMemo($lessonItem, memoContent);
        }
      }
    },

    setupToolbarHandlers: function ($item, questionId) {
      const self = this;

      // 도구 메뉴 버튼 클릭 (1100 study와 동일한 동작)
      $item
        .find(".ptg-contextual-action-btn")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          e.stopPropagation();

          const $btn = $(this);
          const $toolbar = $item.find(".ptg-question-toolbar");

          // 다른 툴바 닫기
          $(".ptg-question-toolbar").not($toolbar).slideUp(200);
          $(".ptg-contextual-action-btn").not($btn).css({
            background: "transparent",
            "border-color": "#ddd",
            color: "#666",
          });

          // 현재 툴바 토글
          $toolbar.slideToggle(200, function () {
            if ($toolbar.is(":visible")) {
              $btn.css({
                background: "#4a90e2",
                "border-color": "#4a90e2",
                color: "white",
              });
            } else {
              $btn.css({
                background: "transparent",
                "border-color": "#ddd",
                color: "#666",
              });
            }
          });
        });

      // 툴바 버튼 클릭 (1100 study의 툴바 핸들러 재사용)
      if (
        typeof window.PTGStudyToolbar !== "undefined" &&
        window.PTGStudyToolbar.handleToolbarAction
      ) {
        $item
          .find(".ptg-toolbar-btn")
          .off("click")
          .on("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            const action = $(this).data("action");
            const qId = $(this).data("question-id");
            window.PTGStudyToolbar.handleToolbarAction(action, qId, $item);
          });
      } else {
        // 툴바가 없으면 기본 동작만 (나중에 구현 가능)
        console.warn("PTGStudyToolbar not available");
      }
    },

    // renderQuestionCard 함수는 이제 공통 컴포넌트 PTGQuestionViewer를 사용하므로 제거됨
    // 필요시 window.PTGQuestionViewer.renderQuestion() 또는 renderQuestionCard()를 직접 호출

    formatQuestionContent: function (content) {
      if (!content) return "";
      // 기본적인 HTML 이스케이프 및 줄바꿈 처리
      return escapeHtml(content).replace(/\n/g, "<br>");
    },

    formatExplanationText: function (explanationRaw) {
      if (!explanationRaw) return "";
      var text = String(explanationRaw);
      text = text.replace(/\r\n/g, "\n");
      text = text.replace(/(?!^)\(정답 해설\)\s*:/g, "<br>(정답 해설):");
      text = text.replace(/(?!^)\(오답 해설\)\s*:/g, "<br>(오답 해설):");
      text = text.replace(/\n/g, "<br>");
      return text;
    },

    setupStudyEventHandlers: function () {
      const self = this;
      // 1100 study와 동일한 방식으로 구현
      this.container
        .off("click", ".toggle-answer")
        .on("click", ".toggle-answer", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $(this)
            .closest(".ptg-lesson-answer-area")
            .find(".answer-content")
            .slideToggle();
        });

      this.container
        .off("click", ".toggle-answer-img")
        .on("click", ".toggle-answer-img", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $(this)
            .closest(".ptg-lesson-answer-area")
            .find(".question-image-content")
            .slideToggle();
        });
    },

    showList: function () {
      const self = this;
      this.currentView = "list";
      this.currentQuestionId = null;
      this.currentMemo = null;

      // 대시보드 URL 가져오기 (PHP에서 전달된 값 또는 기본값)
      const dashboardUrl =
        (window.ptgMyNote && window.ptgMyNote.dashboardUrl) || "#";

      // 원래 템플릿 구조로 복원 (대시보드 버튼 포함)
      const container = this.container;
      container.html(`
                <div class="ptg-mynote-header">
                    <div class="ptg-mynote-header-left">
                        <h2>마이노트</h2>
                        <a href="${dashboardUrl}" class="ptg-btn-dashboard">
                            ← 학습현황으로 돌아가기
                        </a>
                    </div>
                    <div class="ptg-mynote-controls">
                        <input type="text" id="ptg-memo-search" placeholder="검색어 입력..." class="ptg-input">
                    </div>
                </div>
                <div class="ptg-memo-list">
                    <div class="ptg-loading">로딩 중...</div>
                </div>
            `);

      // 목록 로드 (이벤트는 이미 위임 방식으로 바인딩되어 있음)
      this.destroyInfiniteScroll();
      this.loadMemos({ reset: true });
    },

    setupInfiniteScroll: function () {
      if (this.displayMode !== "card") {
        this.destroyInfiniteScroll();
        return;
      }

      if (this.infiniteScrollHandler) {
        return;
      }

      const self = this;
      this.infiniteScrollHandler = function () {
        // 쓰로틀링 (100ms)
        if (self.scrollThrottleTimer) {
          return;
        }
        self.scrollThrottleTimer = setTimeout(function () {
          self.scrollThrottleTimer = null;

          if (
            self.currentView !== "list" ||
            self.isLoadingMore ||
            !self.hasMore
          ) {
            return;
          }

          const scrollTop = $(window).scrollTop();
          const windowHeight = $(window).height();
          const containerBottom =
            self.container.offset().top + self.container.outerHeight();

          // 여유분 300px로 증가
          if (scrollTop + windowHeight + 300 >= containerBottom) {
            self.loadMemos({ append: true });
          }
        }, 100);
      };

      $(window).on("scroll.ptgMynote", this.infiniteScrollHandler);
    },

    destroyInfiniteScroll: function () {
      if (this.infiniteScrollHandler) {
        $(window).off("scroll.ptgMynote", this.infiniteScrollHandler);
        this.infiniteScrollHandler = null;
      }
    },

    showInfiniteLoader: function () {
      if (this.displayMode !== "card") {
        return;
      }
      const listContainer = this.container.find(".ptg-memo-list");
      if (!listContainer.find(".ptg-infinite-loader").length) {
        listContainer.append(
          '<div class="ptg-infinite-loader">메모를 불러오는 중...</div>'
        );
      }
    },

    hideInfiniteLoader: function () {
      this.container.find(".ptg-infinite-loader").remove();
    },

    showEndMessage: function () {
      if (this.displayMode !== "card" || this.infiniteEndShown) {
        return;
      }
      const listContainer = this.container.find(".ptg-memo-list");
      listContainer.append(
        '<div class="ptg-infinite-end">마지막 메모까지 확인했습니다.</div>'
      );
      this.infiniteEndShown = true;
    },

    hideEndMessage: function () {
      this.container.find(".ptg-infinite-end").remove();
      this.infiniteEndShown = false;
    },

    maybeTriggerImmediateLoad: function () {
      if (this.displayMode !== "card" || this.isLoadingMore || !this.hasMore) {
        return;
      }

      const containerBottom =
        this.container.offset().top + this.container.outerHeight();
      const windowBottom = $(window).scrollTop() + $(window).height();

      if (containerBottom <= windowBottom + 80) {
        this.loadMemos({ append: true });
      }
    },

    deleteMemo: function (id) {
      const self = this;
      $.ajax({
        url: self.config.restUrl + "memos/" + id,
        method: "DELETE",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function () {
          self.loadMemos({ reset: self.displayMode === "card" });
        },
        error: function () {
          alert("삭제 실패");
        },
      });
    },

    enterMemoEditMode: function ($card) {
      if (!$card || !$card.length || $card.hasClass("is-editing")) {
        const $existingField = $card
          ? $card.find(".ptg-memo-edit-field")
          : null;
        if ($existingField && $existingField.length) {
          $existingField.focus();
        }
        return;
      }

      const $content = $card.find(".ptg-memo-card-content");
      if (!$content.length) {
        return;
      }

      const originalText = $content.text();
      const textarea = $(
        '<textarea class="ptg-memo-edit-field" rows="4"></textarea>'
      );
      textarea.val(originalText);
      textarea.data("original", originalText);
      $content.replaceWith(textarea);
      this.autoResizeTextarea(textarea);
      textarea.focus();

      $card.addClass("is-editing");
      this.showCardStatus(
        $card,
        "포커스를 다른 곳으로 이동하면 자동으로 저장됩니다.",
        "info"
      );
    },

    exitMemoEditMode: function ($card, text) {
      if (!$card || !$card.length) {
        return;
      }
      const $field = $card.find(".ptg-memo-edit-field");
      if ($field.length) {
        const paragraph = $('<p class="ptg-memo-card-content"></p>');
        paragraph.html(escapeHtml(text || ""));
        $field.replaceWith(paragraph);
      }
      $card.removeClass("is-editing");
    },

    saveMemoContent: function (memoId, content, $card) {
      const self = this;
      if (!$card || !$card.length) {
        return;
      }

      const payload = {
        content: content || "",
      };

      this.showCardStatus($card, "저장 중입니다...", "info");

      $.ajax({
        url: this.config.restUrl + "memos/" + memoId,
        method: "PATCH",
        contentType: "application/json; charset=utf-8",
        data: JSON.stringify(payload),
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function (response) {
          const updatedContent =
            response && typeof response.content !== "undefined"
              ? response.content
              : content || "";
          const updatedAt =
            response && response.updated_at
              ? response.updated_at.split(" ")[0]
              : null;

          self.exitMemoEditMode($card, updatedContent);
          self.updateCardMeta($card, updatedContent, updatedAt);
          self.showCardStatus($card, "저장되었습니다.", "success");
        },
        error: function (xhr) {
          let message = "저장 중 오류가 발생했습니다.";
          if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
          }
          self.showCardStatus($card, message, "error");
          const $field = $card.find(".ptg-memo-edit-field");
          if ($field.length) {
            setTimeout(function () {
              $field.focus();
            }, 10);
          }
        },
      });
    },

    updateCardMeta: function ($card, content, updatedAt) {
      if (!$card || !$card.length) {
        return;
      }

      const sanitized = content || "";
      $card.find(".ptg-memo-card-content").html(escapeHtml(sanitized));
      const $viewBtn = $card.find(".ptg-view-question");
      if ($viewBtn.length) {
        $viewBtn.data("memo-content", sanitized);
      }
      if (updatedAt) {
        $card.find(".ptg-memo-card-date").text("등록일자 " + updatedAt);
      }
    },

    showCardStatus: function ($card, message, type) {
      if (!$card || !$card.length) {
        return;
      }
      const $status = $card.find(".ptg-card-status");
      if (!$status.length) {
        return;
      }

      $status
        .removeClass("is-success is-error is-info")
        .addClass("is-" + (type || "info"));
      $status.text(message || "");
      $status.stop(true, true).fadeIn(120);

      if (type === "success") {
        setTimeout(function () {
          $status.fadeOut(200);
        }, 2200);
      }
    },

    autoResizeTextarea: function ($field) {
      if (!$field || !$field.length) {
        return;
      }
      const el = $field[0];
      el.style.height = "auto";
      el.style.height = el.scrollHeight + "px";
    },

    activateToolbarMemo: function ($lessonItem, questionId) {
      if (!$lessonItem || !$lessonItem.length) {
        return;
      }

      const $toolbarBtn = $lessonItem
        .find(".ptg-contextual-action-btn")
        .first();
      const $toolbar = $lessonItem.find(".ptg-question-toolbar").first();

      if ($toolbarBtn.length && $toolbar.length && !$toolbar.is(":visible")) {
        $toolbarBtn.trigger("click");
      }

      // 메모 패널은 자동으로 열지 않음
    },

    renderFallbackMemo: function ($lessonItem, memoContent) {
      if (
        !$lessonItem ||
        !$lessonItem.length ||
        !memoContent ||
        $lessonItem.find(".ptg-mynote-memo-display").length
      ) {
        return;
      }

      const memoTemplate = `
                <div class="ptg-mynote-memo-display" style="margin-top: 20px;">
                    <h4>메모</h4>
                    <div class="ptg-memo-content">${escapeHtml(
                      memoContent
                    )}</div>
                </div>
            `;

      $lessonItem.find(".ptg-lesson-answer-area").after(memoTemplate);
    },
  };

  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    const HTML_ENTITIES = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
      "`": "&#96;",
    };
    return String(str).replace(/[&<>"'`]/g, function (match) {
      return HTML_ENTITIES[match] || match;
    });
  }

  $(document).ready(function () {
    // 컨테이너가 존재하는지 먼저 확인
    if ($("#ptg-mynote-app").length === 0) {
      return; // 컨테이너가 없으면 초기화하지 않음
    }
    MyNote.init();
  });
})(jQuery);
