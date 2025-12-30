/**
 * PTGates Admin 문제 목록 JavaScript
 * Refactored to use Module Pattern and Namespace Event Binding
 * Updated: Inline Editing Support
 */

var PTGates_Admin_List = {
  // 설정값 (Selectors & Config)
  config: {
    apiUrl: "", // init에서 설정
    restUrl: "", // REST API 기본 URL (init에서 설정)
    ajaxUrl: "", // init에서 설정
    nonce: "", // init에서 설정
    selectors: {
      // Filters
      yearFilter: "#ptg-year-filter",
      examSessionFilter: "#ptg-exam-session-filter",
      sessionFilter: "#ptg-session-filter",
      subjectFilter: "#ptg-subject-filter",
      subsubjectFilter: "#ptg-subsubject-filter",

      // Search
      searchIdInput: "#ptg-search-id",
      searchInput: "#ptg-search-input",
      searchBtn: "#ptg-search-btn",
      clearBtn: "#ptg-clear-search",

      // List & Pagination
      listContainer: "#ptg-questions-list",
      paginationContainer: "#ptg-pagination",
      resultCount: "#ptg-result-count",

      // Inline Edit
      editTrigger: ".pt-admin-edit-btn",
      editWrapper: ".ptg-inline-edit-form",
      saveBtn: ".pt-btn-save-edit",
      cancelBtn: ".pt-btn-cancel-edit",

      // Question Card Elements
      card: ".ptg-question-card",
      viewContent: ".ptg-question-content",
      viewActions: ".ptg-question-actions",
    },
  },

  state: {
    currentPage: 1,
    currentSearch: "",
    currentSearchId: "",
    filters: {
      year: "",
      examSession: "",
      session: "",
      subject: "",
      subsubject: "",
    },
    isLoading: false,
    isEnd: false,
  },

  init: function () {
    console.log("[PTGates Admin] List Module Initialized");

    // 전역 설정 가져오기
    if (typeof ptgAdmin !== "undefined") {
      this.config.apiUrl = ptgAdmin.apiUrl;
      this.config.restUrl = ptgAdmin.restUrl || ptgAdmin.apiUrl; // REST API 기본 URL
      this.config.ajaxUrl = ptgAdmin.ajaxUrl;
      this.config.nonce = ptgAdmin.nonce;
    } else {
      console.error("[PTGates Admin] ptgAdmin global object not found.");
      return;
    }

    this.bindEvents();
    this.loadInitialData();
  },

  bindEvents: function () {
    var self = this;
    var s = self.config.selectors;

    // 1. 편집 버튼 클릭 (Inline Edit)
    jQuery(document)
      .off("click.ptAdminList", s.editTrigger)
      .on("click.ptAdminList", s.editTrigger, function (e) {
        e.preventDefault();
        var $btn = jQuery(this);
        var $card = $btn.closest(s.card);
        var questionId = $btn.data("id");

        // 중복 실행 방지
        if ($card.find(s.editWrapper).length > 0) {
          return;
        }

        console.log("[PTGates Admin] Inline Edit clicked. ID:", questionId);
        self.startInlineEdit($card, questionId, $btn);
      });

    // 2. 삭제 버튼 클릭
    jQuery(document)
      .off("click.ptAdminList", ".pt-admin-delete-btn")
      .on("click.ptAdminList", ".pt-admin-delete-btn", function (e) {
        e.preventDefault();
        var $btn = jQuery(this);
        var questionId = $btn.data("id");

        // 확인 창
        if (
          !confirm(
            "문제 ID " +
              questionId +
              "를 정말 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다."
          )
        ) {
          return;
        }

        console.log("[PTGates Admin] Delete clicked. ID:", questionId);
        self.deleteQuestion(questionId, $btn);
      });

    // 3. 검색 버튼
    jQuery(document).on("click.ptAdminList", s.searchBtn, function () {
      self.state.currentSearch = jQuery(s.searchInput).val().trim();
      self.state.currentSearchId = jQuery(s.searchIdInput).val().trim();
      self.state.currentPage = 1;
      self.loadQuestions();
    });

    // 4. 검색 엔터키
    jQuery(document).on(
      "keypress.ptAdminList",
      s.searchInput + ", " + s.searchIdInput,
      function (e) {
        if (e.which === 13) {
          jQuery(s.searchBtn).click();
        }
      }
    );

    // 5. 초기화 버튼
    jQuery(document).on("click.ptAdminList", s.clearBtn, function () {
      self.resetFilters();
    });

    // 6. 필터 변경 이벤트들
    jQuery(document).on("change.ptAdminList", s.yearFilter, function () {
      self.state.filters.year = jQuery(this).val();
      self.state.filters.examSession = "";
      self.resetSelectOptions(jQuery(s.examSessionFilter), "회차");
      if (self.state.filters.year) {
        self.loadExamSessions(self.state.filters.year);
      }
    });

    jQuery(document).on("change.ptAdminList", s.examSessionFilter, function () {
      self.state.filters.examSession = jQuery(this).val();
    });

    jQuery(document).on("change.ptAdminList", s.sessionFilter, function () {
      self.state.filters.session = jQuery(this).val();
      self.state.filters.subject = "";
      self.state.filters.subsubject = "";
      self.resetSelectOptions(jQuery(s.subjectFilter), "과목");
      self.resetSelectOptions(jQuery(s.subsubjectFilter), "세부과목");
      self.loadSubjects(self.state.filters.session);
    });

    jQuery(document).on("change.ptAdminList", s.subjectFilter, function () {
      self.state.filters.subject = jQuery(this).val();
      self.state.filters.subsubject = "";
      self.resetSelectOptions(jQuery(s.subsubjectFilter), "세부과목");
      if (self.state.filters.subject) {
        self.updateSubsubjects(self.state.filters.subject);
      }
    });

    jQuery(document).on("change.ptAdminList", s.subsubjectFilter, function () {
      self.state.filters.subsubject = jQuery(this).val();
    });

    // 7. 인라인 편집 - 취소
    jQuery(document).on("click.ptAdminList", s.cancelBtn, function (e) {
      e.preventDefault();
      var $wrapper = jQuery(this).closest(s.editWrapper);
      var $card = $wrapper.closest(s.card);

      // 편집 폼 제거 및 보기 모드 복구
      $wrapper.remove();
      $card.find(s.viewContent).show();
      $card.find(s.viewActions).show();
    });

    // 8. 인라인 편집 - 저장
    jQuery(document).on("click.ptAdminList", s.saveBtn, function (e) {
      e.preventDefault();
      var $wrapper = jQuery(this).closest(s.editWrapper);
      self.saveInlineEdit($wrapper);
    });

    // 9. 페이지네이션
    jQuery(document).on(
      "click.ptAdminList",
      ".ptg-pagination-btn",
      function () {
        self.state.currentPage = jQuery(this).data("page");
        self.loadQuestions();
      }
    );

    // 10. 이미지 미리보기 (Inline Edit)
    jQuery(document).on(
      "change.ptAdminList",
      'input[name="question_image"]',
      function (e) {
        var file = e.target.files[0];
        var $wrapper = jQuery(this).closest(s.editWrapper);
        var $previewContainer = $wrapper.find(".ptg-image-preview-container");

        if (file) {
          // Reset delete flag
          $wrapper.find('input[name="delete_image"]').val("0");

          // 기존 이미지가 있으면 숨기기 (새 이미지로 대체)
          var $existingImage = $wrapper
            .find(".ptg-image-preview-container")
            .not(".ptg-new-image-preview");
          if ($existingImage.length > 0) {
            $existingImage.hide();
          }

          var reader = new FileReader();
          reader.onload = function (e) {
            // 새 이미지 미리보기 컨테이너 찾기 또는 생성
            var $newPreview = $wrapper.find(".ptg-new-image-preview");
            if ($newPreview.length === 0) {
              $newPreview = jQuery(
                '<div class="ptg-image-preview-container ptg-new-image-preview" style="margin-top: 10px; max-width: 500px; max-height: 500px;"><div style="max-width: 500px; max-height: 500px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;"><img class="ptg-image-preview" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;"></div><p class="ptg-image-filename" style="margin-top: 5px; font-size: 12px; color: #666;"></p></div>'
              );
              $wrapper.find('input[name="question_image"]').after($newPreview);
            }

            $newPreview.show();
            $newPreview.find("img").attr("src", e.target.result);
            $newPreview
              .find(".ptg-image-filename")
              .text(
                "새 이미지: " +
                  file.name +
                  " (" +
                  (file.size / 1024).toFixed(2) +
                  " KB)"
              );
          };
          reader.readAsDataURL(file);
        } else {
          // 파일 선택 취소 시 새 미리보기 숨기기
          var $newPreview = $wrapper.find(".ptg-new-image-preview");
          if ($newPreview.length > 0) {
            $newPreview.hide();
          }
        }
      }
    );

    // 11. 이미지 삭제 버튼
    jQuery(document).on(
      "click.ptAdminList",
      ".ptg-btn-delete-image",
      function (e) {
        e.preventDefault();
        var $wrapper = jQuery(this).closest(s.editWrapper);

        if (confirm("이미지를 삭제하시겠습니까? 저장 시 반영됩니다.")) {
          $wrapper.find('input[name="delete_image"]').val("1");
          $wrapper.find(".ptg-image-preview-container").hide();
          $wrapper.find('input[name="question_image"]').val(""); // 파일 입력 초기화
        }
      }
    );

    // 12. 인라인 편집 - 과목 변경
    jQuery(document).on(
      "change.ptAdminList",
      ".ptg-subject-select",
      function () {
        var $wrapper = jQuery(this).closest(s.editWrapper);
        var subject = jQuery(this).val();
        self.updateEditSubsubjects($wrapper, subject);
      }
    );

    // 13. 엑셀 다운로드 버튼
    jQuery(document).on(
      "click.ptAdminList",
      "#ptg-export-excel-btn",
      function (e) {
        e.preventDefault();
        self.exportExcel();
      }
    );

    // 14. 무한 스크롤 (Infinite Scroll)
    jQuery(window).on("scroll.ptAdminList", function () {
      // 문서 전체 높이 - (현재 스크롤 위치 + 창 높이) < 100px 일 때 로딩
      if (
        jQuery(document).height() -
          (jQuery(window).scrollTop() + jQuery(window).height()) <
        100
      ) {
        if (!self.state.isLoading && !self.state.isEnd) {
          self.state.currentPage++;
          self.loadQuestions(null, true); // true = append mode
        }
      }
    });
  },

  loadInitialData: function () {
    this.loadExamYears();
    this.loadSessions();
    // 초기 안내 메시지
    jQuery(this.config.selectors.listContainer).html(
      '<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>'
    );
  },

  resetFilters: function () {
    var s = this.config.selectors;
    jQuery(s.searchInput).val("");
    jQuery(s.searchIdInput).val("");
    this.state.currentSearch = "";
    this.state.currentSearchId = "";
    this.state.filters = {
      year: "",
      examSession: "",
      session: "",
      subject: "",
      subsubject: "",
    };
    this.state.currentPage = 1;

    jQuery(s.yearFilter).val("");
    jQuery(s.sessionFilter).val("");
    this.resetSelectOptions(jQuery(s.examSessionFilter), "회차");
    this.resetSelectOptions(jQuery(s.subjectFilter), "과목");
    this.resetSelectOptions(jQuery(s.subsubjectFilter), "세부과목");

    this.loadSubjects(); // Reload all subjects

    jQuery(s.listContainer).html(
      '<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>'
    );
    jQuery(s.resultCount).hide();
    jQuery(s.paginationContainer).html("");
  },

  resetSelectOptions: function ($select, label) {
    $select.html('<option value="">' + label + "</option>");
  },

  // --- Data Loading Methods ---

  loadExamYears: function () {
    var self = this;
    jQuery.ajax({
      url: self.config.apiUrl + "exam-years",
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        if (response.success && Array.isArray(response.data)) {
          var $select = jQuery(self.config.selectors.yearFilter);
          self.resetSelectOptions($select, "년도");
          response.data.forEach(function (year) {
            $select.append(
              jQuery("<option>", { value: year, text: year + "년" })
            );
          });
        }
      },
    });
  },

  loadExamSessions: function (year) {
    var self = this;
    if (!year) return;
    jQuery.ajax({
      url:
        self.config.apiUrl + "exam-sessions?year=" + encodeURIComponent(year),
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        if (response.success && Array.isArray(response.data)) {
          var $select = jQuery(self.config.selectors.examSessionFilter);
          self.resetSelectOptions($select, "회차");
          response.data.forEach(function (session) {
            $select.append(
              jQuery("<option>", { value: session, text: session + "회" })
            );
          });
        }
      },
    });
  },

  loadSessions: function () {
    var self = this;
    jQuery.ajax({
      url: self.config.apiUrl + "sessions",
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        if (response.success && Array.isArray(response.data)) {
          var $select = jQuery(self.config.selectors.sessionFilter);
          self.resetSelectOptions($select, "교시");
          response.data.forEach(function (session) {
            $select.append(
              jQuery("<option>", { value: session.id, text: session.name })
            );
          });
          self.loadSubjects();
        }
      },
    });
  },

  loadSubjects: function (session) {
    var self = this;
    // 1200-ptgates-quiz의 REST API 사용 (DB에서 직접 가져오기)
    var quizApiUrl = self.config.restUrl.replace("ptg-admin/v1", "ptg-quiz/v1");
    var url = quizApiUrl + "subjects" + (session ? "?session=" + session : "");
    jQuery.ajax({
      url: url,
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        if (response && Array.isArray(response)) {
          var $select = jQuery(self.config.selectors.subjectFilter);
          self.resetSelectOptions($select, "과목");
          response.forEach(function (subjectName) {
            $select.append(
              jQuery("<option>", {
                value: subjectName,
                text: subjectName,
              })
            );
          });
        } else if (
          response &&
          response.success &&
          Array.isArray(response.data)
        ) {
          // 응답 형식이 {success: true, data: [...]}인 경우
          var $select = jQuery(self.config.selectors.subjectFilter);
          self.resetSelectOptions($select, "과목");
          response.data.forEach(function (subjectName) {
            $select.append(
              jQuery("<option>", {
                value: subjectName,
                text: subjectName,
              })
            );
          });
        }
      },
      error: function (xhr, status, error) {
        console.error("과목 로드 오류:", error);
      },
    });
  },

  updateSubsubjects: function (subjectName) {
    var self = this;
    var $subjectSelect = jQuery(this.config.selectors.subjectFilter);
    var $subSelect = jQuery(this.config.selectors.subsubjectFilter);
    var $sessionSelect = jQuery(this.config.selectors.sessionFilter);
    var session = $sessionSelect.val();

    if (!session || !subjectName) {
      self.resetSelectOptions($subSelect, "세부과목");
      return;
    }

    // 1200-ptgates-quiz의 REST API 사용 (DB에서 직접 가져오기)
    var quizApiUrl = self.config.restUrl.replace("ptg-admin/v1", "ptg-quiz/v1");
    var url =
      quizApiUrl +
      "subsubjects?session=" +
      encodeURIComponent(session) +
      "&subject=" +
      encodeURIComponent(subjectName);

    jQuery.ajax({
      url: url,
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        self.resetSelectOptions($subSelect, "세부과목");
        var subsubjects = [];

        if (response && Array.isArray(response)) {
          subsubjects = response;
        } else if (
          response &&
          response.success &&
          Array.isArray(response.data)
        ) {
          subsubjects = response.data;
        }

        subsubjects.forEach(function (subsubject) {
          $subSelect.append(
            jQuery("<option>", { value: subsubject, text: subsubject })
          );
        });
      },
      error: function (xhr, status, error) {
        console.error("세부과목 로드 오류:", error);
        self.resetSelectOptions($subSelect, "세부과목");
      },
    });
  },

  loadQuestions: function (callback, isAppend) {
    var self = this;
    if (self.state.isLoading) return;

    self.state.isLoading = true;

    // 첫 페이지 로드인 경우 (검색/필터 변경 시) 상태 초기화는 호출하는 쪽에서 담당하거나 여기서 확인
    if (!isAppend) {
      self.state.currentPage = 1;
      self.state.isEnd = false;
      jQuery(self.config.selectors.listContainer).html(
        '<p class="ptg-loading">로딩 중...</p>'
      );
      jQuery(self.config.selectors.paginationContainer).html(""); // 페이지네이션 제거
    }

    var params = {
      page: self.state.currentPage,
      per_page: 5, // 무한 스크롤: 5개씩
    };

    // Add filters
    if (self.state.filters.subsubject)
      params.subsubject = self.state.filters.subsubject;
    else if (self.state.filters.subject)
      params.subject = self.state.filters.subject;

    if (self.state.filters.year) params.exam_year = self.state.filters.year;
    if (self.state.filters.examSession)
      params.exam_session = self.state.filters.examSession;

    var sessionValue = jQuery(self.config.selectors.sessionFilter).val();
    if (sessionValue) {
      params.exam_course = sessionValue.endsWith("교시")
        ? sessionValue
        : sessionValue + "교시";
    }

    if (self.state.currentSearch) params.search = self.state.currentSearch;
    if (self.state.currentSearchId)
      params.question_id = self.state.currentSearchId;

    console.log("[PTG Admin] loadQuestions params:", params);

    // Append 모드일 때 로딩 인디케이터 추가
    if (isAppend) {
      jQuery(self.config.selectors.listContainer).append(
        '<div class="ptg-append-loading" style="text-align:center; padding:10px;">로딩 중...</div>'
      );
    }

    jQuery.ajax({
      url: self.config.apiUrl + "questions",
      method: "GET",
      data: params,
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        self.state.isLoading = false;
        if (isAppend) {
          jQuery(".ptg-append-loading").remove();
        }

        if (response.success && response.data) {
          var questions = response.data.questions;

          // 더 이상 로드할 데이터가 없으면 isEnd 설정
          if (questions.length < params.per_page) {
            self.state.isEnd = true;
          }

          if (isAppend) {
            self.renderQuestionsAppend(questions);
          } else {
            self.renderQuestions(questions);
          }

          self.updateResultCount(response.data.total, params);

          // 콜백이 있으면 실행
          if (typeof callback === "function") {
            callback();
          }
        } else {
          if (!isAppend) {
            jQuery(self.config.selectors.listContainer).html(
              "<p>문제를 불러올 수 없습니다.</p>"
            );
          }
          jQuery(self.config.selectors.resultCount).hide();

          // 콜백이 있으면 실행
          if (typeof callback === "function") {
            callback();
          }
        }
      },
      error: function () {
        self.state.isLoading = false;
        if (isAppend) {
          jQuery(".ptg-append-loading").remove();
        } else {
          jQuery(self.config.selectors.listContainer).html(
            "<p>문제를 불러오는 중 오류가 발생했습니다.</p>"
          );
        }

        // 콜백이 있으면 실행
        if (typeof callback === "function") {
          callback();
        }
      },
    });
  },

  // --- Rendering Methods ---

  // --- Rendering Methods ---

  renderQuestions: function (questions) {
    if (questions.length === 0) {
      jQuery(this.config.selectors.listContainer).html(
        "<p>문제가 없습니다.</p>"
      );
      return;
    }

    var html = '<div class="ptg-questions-grid">';
    var self = this;

    questions.forEach(function (q) {
      html += self.generateQuestionItemHtml(q);
    });

    html += "</div>";
    jQuery(this.config.selectors.listContainer).html(html);
  },

  renderQuestionsAppend: function (questions) {
    if (questions.length === 0) return;

    var html = "";
    var self = this;

    questions.forEach(function (q) {
      html += self.generateQuestionItemHtml(q);
    });

    // .ptg-questions-grid가 이미 존재하는지 확인
    var $grid = jQuery(this.config.selectors.listContainer).find(
      ".ptg-questions-grid"
    );
    if ($grid.length > 0) {
      $grid.append(html);
    } else {
      // 없으면 새로 생성 (기존 버그 방지)
      jQuery(this.config.selectors.listContainer).html(
        '<div class="ptg-questions-grid">' + html + "</div>"
      );
    }
  },

  generateQuestionItemHtml: function (q) {
    var self = this;
    var content = q.content || ""; // DB 내용 그대로 표시
    var explanation = q.explanation || ""; // DB 내용 그대로 표시

    var year = q.exam_years ? q.exam_years.split(",")[0] : "";
    var session = q.exam_sessions ? q.exam_sessions.split(",")[0] : "";
    var course = q.exam_courses ? q.exam_courses.split(",")[0] : "";
    var mainSubject = q.main_subjects ? q.main_subjects.split(",")[0] : "";
    var subsubject = q.subsubjects
      ? q.subsubjects.split(",")[0]
      : q.subjects
      ? q.subjects.split(",")[0]
      : "";

    var metaParts = [];
    if (year) metaParts.push(year + "년");
    if (session) metaParts.push(session + "회");
    if (course) metaParts.push(course);
    if (mainSubject) metaParts.push(mainSubject);
    var metaInfo = metaParts.length > 0 ? metaParts.join(" ") : "-";

    // 이미지 아이콘 표시
    var imageIcon = q.question_image
      ? '<span class="ptg-image-indicator" title="이미지 있음">🖼️</span>'
      : "";

    // 이미지 URL 생성 (이미지가 있는 경우)
    var imageHtml = "";
    if (q.question_image && year && session) {
      // WordPress upload URL 생성
      var uploadBaseUrl =
        typeof ptgAdmin !== "undefined" && ptgAdmin.uploadUrl
          ? ptgAdmin.uploadUrl
          : "/wp-content/uploads";
      var imageUrl =
        uploadBaseUrl +
        "/ptgates-questions/" +
        year +
        "/" +
        session +
        "/" +
        q.question_image;

      imageHtml = `
                    <div class="ptg-question-field ptg-question-image-field" style="max-width: 500px; max-height: 500px; margin: 10px 0;">
                        <div style="max-width: 500px; max-height: 500px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                            <img src="${imageUrl}" alt="문제 이미지" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;" onerror="this.onerror=null; this.src=this.src.replace(/\\.jpg$/, '.png');" />
                        </div>
                    </div>
                `;
    }

    // 지문과 선택지 분리 (간단한 파싱)
    // ①, (1), 1. 등으로 시작하는 패턴 찾기
    var contentHtml = "";
    var optionsHtml = "";
    var contentText = self.escapeHtml(content);

    // 정규식으로 선택지 시작 위치 찾기
    // 보기: ①, ②, ③... 또는 (1), (2)... 또는 1., 2....
    // 주의: 텍스트에 중복된 보기가 있을 수 있으므로 첫 번째 매칭을 찾아서 분리함
    var optionRegex = /(?:^|\s|>)(\(?\d+\)|[①-⑳]|\d+\.)\s/;
    var match = contentText.match(optionRegex);

    if (match && match.index > 0) {
      var splitIndex = match.index;
      // 선택지 앞부분 (지문)
      contentHtml =
        '<div class="ptg-question-text">' +
        contentText.substring(0, splitIndex) +
        "</div>";
      // 선택지 뒷부분 (옵션) - 줄바꿈을 유지하며 표시
      var optionsText = contentText.substring(splitIndex);
      // 줄바꿈을 <br>로 변환하여 가독성 높임
      optionsHtml =
        '<div class="ptg-question-options" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #eee;">' +
        optionsText.replace(/\n/g, "<br>") +
        "</div>";
    } else {
      contentHtml = '<div class="ptg-question-text">' + contentText + "</div>";
    }

    return `
                <div class="ptg-question-card">
                    <div class="ptg-question-header">
                        <div class="ptg-question-id-info">
                            <strong>문제 ID: ${q.question_id} ${
      q.question_no ? `(no: ${q.question_no})` : ""
    }</strong>
                            <span class="ptg-question-meta-info">${metaInfo}</span>
                            ${imageIcon}
                        </div>
                        <span class="ptg-question-subsubjects">${
                          subsubject || "-"
                        }</span>
                    </div>
                    <div class="ptg-question-content">
                        <div class="ptg-question-field ptg-field-content">
                            <label>지문:</label>
                            ${contentHtml}
                        </div>
                        ${imageHtml}
                        ${
                          optionsHtml
                            ? `<div class="ptg-question-field ptg-field-options"><label>선택지:</label>${optionsHtml}</div>`
                            : ""
                        }
                        <div class="ptg-question-field ptg-field-answer">
                            <label>정답:</label>
                            <div class="ptg-question-text">${self.escapeHtml(
                              q.answer || "-"
                            )}</div>
                        </div>
                        <div class="ptg-question-field ptg-field-explanation">
                            <label>해설:</label>
                            <div class="ptg-question-text">${self.escapeHtml(
                              explanation
                            )}</div>
                        </div>
                        <div class="ptg-question-meta">
                            <span>난이도: ${q.difficulty || "-"}</span>
                            <span>활성: ${q.is_active ? "예" : "아니오"}</span>
                        </div>
                    </div>
                    <div class="ptg-question-actions">
                        <button class="pt-admin-edit-btn" data-id="${
                          q.question_id
                        }">✏️ 편집</button>
                        <button class="pt-admin-delete-btn" data-id="${
                          q.question_id
                        }">🗑️ 삭제</button>
                    </div>
                </div>
            `;
  },

  exportExcel: function () {
    var self = this;
    var params = [];

    // Add filters
    if (self.state.filters.subsubject)
      params.push(
        "subsubject=" + encodeURIComponent(self.state.filters.subsubject)
      );
    else if (self.state.filters.subject)
      params.push("subject=" + encodeURIComponent(self.state.filters.subject));

    if (self.state.filters.year)
      params.push("exam_year=" + self.state.filters.year);
    if (self.state.filters.examSession)
      params.push("exam_session=" + self.state.filters.examSession);

    var sessionValue = jQuery(self.config.selectors.sessionFilter).val();
    if (sessionValue) {
      var val = sessionValue.endsWith("교시")
        ? sessionValue
        : sessionValue + "교시";
      params.push("exam_course=" + encodeURIComponent(val));
    }

    if (self.state.currentSearch)
      params.push("search=" + encodeURIComponent(self.state.currentSearch));
    if (self.state.currentSearchId)
      params.push("question_id=" + self.state.currentSearchId);

    // AJAX Action
    params.push("action=pt_admin_export_questions_csv");

    var exportUrl = self.config.ajaxUrl + "?" + params.join("&");

    // 새 탭/창에서 다운로드 트리거
    window.location.href = exportUrl;
  },

  renderPagination: function (data) {
    // Pagination removed in favor of Infinite Scroll
    jQuery(this.config.selectors.paginationContainer).html("");
  },

  updateResultCount: function (total, params) {
    var $countEl = jQuery(this.config.selectors.resultCount);
    if (total > 0) {
      var conditionText = "";
      var conditions = [];
      if (params.question_id) conditions.push("ID: " + params.question_id);
      if (params.search) conditions.push('검색: "' + params.search + '"');
      if (params.subsubject) conditions.push("세부과목: " + params.subsubject);
      else if (params.subject) conditions.push("과목: " + params.subject);
      if (params.exam_year) conditions.push("년도: " + params.exam_year);
      if (params.exam_session) conditions.push("회차: " + params.exam_session);
      if (params.exam_course) conditions.push("교시: " + params.exam_course);

      if (conditions.length > 0)
        conditionText = " (" + conditions.join(", ") + ")";
      $countEl
        .text("총 " + total.toLocaleString() + "개" + conditionText)
        .show();
    } else {
      $countEl.hide();
    }
  },

  // --- Inline Edit Functionality ---

  startInlineEdit: function ($card, questionId, $btn) {
    var self = this;
    var s = self.config.selectors;
    var originalBtnText = $btn.text();

    $btn.text("로딩...").prop("disabled", true);

    jQuery.ajax({
      url: self.config.ajaxUrl,
      type: "POST",
      data: {
        action: "pt_get_question_edit_form",
        question_id: questionId,
        security: self.config.nonce,
      },
      success: function (response) {
        $btn.text(originalBtnText).prop("disabled", false);

        if (response.success) {
          // 1. Hide view mode
          $card.find(s.viewContent).hide();
          $card.find(s.viewActions).hide();

          // 2. Append edit form
          $card.append(response.data);

          // 3. Populate subjects
          self.populateEditSubjects($card.find(s.editWrapper));
        } else {
          alert("오류: " + (response.data || "폼을 불러올 수 없습니다."));
        }
      },
      error: function (xhr, status, error) {
        $btn.text(originalBtnText).prop("disabled", false);
        console.error(
          "[PTGates Admin] AJAX Error:",
          status,
          error,
          xhr.responseText
        );
        alert(
          "서버 통신 오류: " +
            status +
            " " +
            error +
            "\n" +
            (xhr.responseText ? xhr.responseText.substring(0, 100) : "")
        );
      },
    });
  },

  saveInlineEdit: function ($wrapper) {
    var self = this;
    var $btn = $wrapper.find(self.config.selectors.saveBtn);

    console.log("[PTGates Admin] saveInlineEdit called");
    console.log("[PTGates Admin] Wrapper length:", $wrapper.length);
    console.log(
      "[PTGates Admin] Wrapper HTML (first 100 chars):",
      $wrapper.prop("outerHTML").substring(0, 100)
    );
    console.log(
      "[PTGates Admin] Data question-id:",
      $wrapper.data("question-id")
    );
    console.log(
      "[PTGates Admin] Input question-id val:",
      $wrapper.find('input[name="question_id"]').val()
    );

    // FormData 객체 생성 (파일 업로드 지원)
    var formData = new FormData();
    formData.append("action", "pt_update_question_inline");
    formData.append("security", self.config.nonce);

    // Try to get ID from data attribute first, then input
    var questionId = $wrapper.data("question-id");
    if (!questionId) {
      questionId = $wrapper.find('input[name="question_id"]').val();
    }

    // Ensure it's an integer (or string that looks like one)
    if (questionId) {
      questionId = parseInt(questionId, 10);
    }
    console.log("[PTGates Admin] Final Resolved Question ID:", questionId);

    if (!questionId) {
      alert("오류: 문제 ID를 찾을 수 없습니다.");
      return;
    }

    // 카드 요소 참조 저장
    var $card = $wrapper.closest(self.config.selectors.card);

    formData.append("question_id", questionId);
    formData.append("content", $wrapper.find('textarea[name="content"]').val());
    formData.append("answer", $wrapper.find('input[name="answer"]').val());
    formData.append(
      "explanation",
      $wrapper.find('textarea[name="explanation"]').val()
    );
    formData.append(
      "difficulty",
      $wrapper.find('select[name="difficulty"]').val()
    );
    formData.append(
      "is_active",
      $wrapper.find('input[name="is_active"]').is(":checked") ? 1 : 0
    );
    formData.append(
      "delete_image",
      $wrapper.find('input[name="delete_image"]').val()
    );

    // 과목/세부과목 추가
    formData.append("subject", $wrapper.find('select[name="subject"]').val());
    formData.append(
      "subsubject",
      $wrapper.find('select[name="subsubject"]').val()
    );

    // 파일 추가 및 최적화
    var fileInput = $wrapper.find('input[name="question_image"]')[0];
    if (fileInput && fileInput.files.length > 0) {
      var file = fileInput.files[0];

      console.log("[PTGates Admin] 파일 정보:", {
        name: file.name,
        size: file.size,
        type: file.type,
        lastModified: file.lastModified,
      });

      // 이미지 최적화 후 업로드
      $btn.text("이미지 최적화 중...").prop("disabled", true);

      self
        .optimizeImage(file, 500, 500, 0.85)
        .then(function (optimizedBlob) {
          var optimizedFile = new File([optimizedBlob], file.name, {
            type: file.type === "image/png" ? "image/png" : "image/jpeg",
            lastModified: Date.now(),
          });
          formData.append("question_image", optimizedFile);

          // 실제 업로드 시작
          self.uploadInlineEdit(formData, $wrapper, $card, $btn);
        })
        .catch(function (error) {
          console.error("[PTGates Admin] 이미지 최적화 실패:", error);
          // 원본 파일로 업로드 시도
          formData.append("question_image", file);
          self.uploadInlineEdit(formData, $wrapper, $card, $btn);
        });

      return; // 비동기 처리이므로 여기서 종료
    } else {
      console.log("[PTGates Admin] 파일 입력이 없거나 파일이 선택되지 않음");
      // 파일이 없으면 바로 업로드
      self.uploadInlineEdit(formData, $wrapper, $card, $btn);
    }
  },

  /**
   * 이미지 리사이징 및 최적화 (클라이언트 측)
   * @param {File} file 원본 파일
   * @param {number} maxWidth 최대 너비
   * @param {number} maxHeight 최대 높이
   * @param {number} quality JPEG 품질 (0-1)
   * @returns {Promise<Blob>} 최적화된 이미지 Blob
   */
  optimizeImage: function (file, maxWidth, maxHeight, quality) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();

      reader.onload = function (e) {
        var img = new Image();

        img.onload = function () {
          var canvas = document.createElement("canvas");
          var ctx = canvas.getContext("2d");

          // 리사이징 계산
          var width = img.width;
          var height = img.height;

          if (width > maxWidth || height > maxHeight) {
            var ratio = Math.min(maxWidth / width, maxHeight / height);
            width = width * ratio;
            height = height * ratio;
          }

          canvas.width = width;
          canvas.height = height;

          // 이미지 그리기
          ctx.drawImage(img, 0, 0, width, height);

          // Blob으로 변환
          canvas.toBlob(
            function (blob) {
              if (blob) {
                console.log("[PTGates Admin] 이미지 최적화 완료:", {
                  원본크기: (file.size / 1024).toFixed(2) + " KB",
                  최적화크기: (blob.size / 1024).toFixed(2) + " KB",
                  감소율: ((1 - blob.size / file.size) * 100).toFixed(1) + "%",
                  크기: width + "x" + height,
                });
                resolve(blob);
              } else {
                reject(new Error("이미지 최적화 실패"));
              }
            },
            file.type === "image/png" ? "image/png" : "image/jpeg",
            quality
          );
        };

        img.onerror = function () {
          reject(new Error("이미지 로드 실패"));
        };

        img.src = e.target.result;
      };

      reader.onerror = function () {
        reject(new Error("파일 읽기 실패"));
      };

      reader.readAsDataURL(file);
    });
  },

  uploadInlineEdit: function (formData, $wrapper, $card, $btn) {
    var self = this;

    $btn.text("저장 중...").prop("disabled", true);

    jQuery.ajax({
      url: self.config.ajaxUrl,
      type: "POST",
      data: formData,
      processData: false, // 파일 전송 시 필수
      contentType: false, // 파일 전송 시 필수
      success: function (response) {
        if (response.success) {
          // 편집 폼에서 입력된 값들 가져오기
          var savedContent = $wrapper.find('textarea[name="content"]').val();
          var savedAnswer = $wrapper.find('input[name="answer"]').val();
          var savedExplanation = $wrapper
            .find('textarea[name="explanation"]')
            .val();
          var savedDifficulty = $wrapper
            .find('select[name="difficulty"]')
            .val();
          var savedIsActive = $wrapper
            .find('input[name="is_active"]')
            .is(":checked");
          var savedSubject = $wrapper.find('select[name="subject"]').val();
          var savedSubsubject = $wrapper
            .find('select[name="subsubject"]')
            .val();

          // 이미지 정보 확인
          var deleteImage =
            $wrapper.find('input[name="delete_image"]').val() === "1";
          var hasNewImage =
            $wrapper.find('input[name="question_image"]')[0] &&
            $wrapper.find('input[name="question_image"]')[0].files.length > 0;
          var questionId = $wrapper.find('input[name="question_id"]').val();

          // 편집 폼 제거 전에 보기 모드 요소 확인
          var $viewContent = $card.find(self.config.selectors.viewContent);
          var $viewActions = $card.find(self.config.selectors.viewActions);

          // 보기 모드가 존재하는지 확인
          if ($viewContent.length === 0 || $viewActions.length === 0) {
            console.error(
              "[PTGates Admin] View mode elements not found before removing edit form"
            );
            console.error(
              "[PTGates Admin] Card HTML:",
              $card.prop("outerHTML").substring(0, 1000)
            );
            alert(
              "오류: 보기 모드 요소를 찾을 수 없습니다. 페이지를 새로고침해주세요."
            );
            $btn.text("저장").prop("disabled", false);
            return;
          }

          // 편집 폼 제거
          $wrapper.remove();

          // 보기 모드 복구
          $viewContent.show();
          $viewActions.show();

          // 이미지 정보 준비
          var imageData = null;

          if (response.data && response.data.new_image) {
            imageData = {
              hasNewImage: true,
              questionId: questionId,
              newImage: response.data.new_image,
            };
          } else if (hasNewImage) {
            imageData = {
              hasNewImage: true,
              questionId: questionId,
            };
          } else if (deleteImage) {
            imageData = {
              deleted: true,
            };
          }

          // 카드 내용 즉시 업데이트
          self.updateQuestionCard($card, {
            content: savedContent,
            answer: savedAnswer,
            explanation: savedExplanation,
            difficulty: savedDifficulty,
            is_active: savedIsActive,
            subsubject: savedSubsubject || savedSubject,
            image: imageData,
          });

          // 저장한 카드 헤더로 스크롤
          setTimeout(function () {
            var cardHeader = $card.find(".ptg-question-header");
            if (cardHeader.length) {
              jQuery("html, body").animate(
                {
                  scrollTop: cardHeader.offset().top - 100,
                },
                300
              );
            }
          }, 100);

          alert("저장되었습니다.");
        } else {
          alert("저장에 실패했습니다: " + (response.data || "알 수 없는 오류"));
          $btn.text("저장").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error("[PTGates Admin] Save Error:", {
          status: status,
          error: error,
          statusCode: xhr.status,
          responseText: xhr.responseText,
          readyState: xhr.readyState,
        });

        var errorMsg = "서버 통신 오류가 발생했습니다.";
        if (xhr.status === 0) {
          errorMsg = "서버에 연결할 수 없습니다. 네트워크 연결을 확인해주세요.";
        } else if (xhr.status === 413) {
          errorMsg = "파일 크기가 너무 큽니다. (최대 10MB)";
        } else if (xhr.status >= 500) {
          errorMsg =
            "서버 오류가 발생했습니다. (상태 코드: " + xhr.status + ")";
        } else if (xhr.responseText) {
          try {
            var response = JSON.parse(xhr.responseText);
            if (response.data && response.data.message) {
              errorMsg = response.data.message;
            }
          } catch (e) {
            errorMsg =
              "오류: " +
              (xhr.responseText.substring(0, 200) || status + " " + error);
          }
        }

        alert(errorMsg);
        $btn.text("저장").prop("disabled", false);
      },
    });
  },

  populateEditSubjects: function ($wrapper) {
    var self = this;
    var $subjectSelect = $wrapper.find(".ptg-subject-select");
    var $subsubjectSelect = $wrapper.find(".ptg-subsubject-select");
    var selectedSubject = $subjectSelect.data("selected");
    var selectedSubsubject = $subsubjectSelect.data("selected");

    // 1200-ptgates-quiz의 REST API 사용 (DB에서 직접 가져오기)
    var quizApiUrl = self.config.restUrl.replace("ptg-admin/v1", "ptg-quiz/v1");
    var url = quizApiUrl + "subjects";

    jQuery.ajax({
      url: url,
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        $subjectSelect.html('<option value="">과목 선택</option>');

        var subjects = [];
        if (response && Array.isArray(response)) {
          subjects = response;
        } else if (
          response &&
          response.success &&
          Array.isArray(response.data)
        ) {
          subjects = response.data;
        }

        subjects.forEach(function (subjectName) {
          var option = jQuery("<option>", {
            value: subjectName,
            text: subjectName,
          });
          if (subjectName === selectedSubject) {
            option.prop("selected", true);
          }
          $subjectSelect.append(option);
        });

        // Trigger update for subsubjects
        if (selectedSubject) {
          self.updateEditSubsubjects(
            $wrapper,
            selectedSubject,
            selectedSubsubject
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("과목 로드 오류:", error);
      },
    });
  },

  updateEditSubsubjects: function ($wrapper, subjectName, selectedSubsubject) {
    var self = this;
    var $subjectSelect = $wrapper.find(".ptg-subject-select");
    var $subSelect = $wrapper.find(".ptg-subsubject-select");
    var $sessionSelect = jQuery(self.config.selectors.sessionFilter);
    var session = $sessionSelect.val();

    $subSelect.html('<option value="">세부과목 선택</option>');

    if (!subjectName) {
      self.resetSelectOptions($subSelect, "세부과목");
      return;
    }

    // 1200-ptgates-quiz의 REST API 사용 (DB에서 직접 가져오기)
    var quizApiUrl = self.config.restUrl.replace("ptg-admin/v1", "ptg-quiz/v1");
    var url =
      quizApiUrl +
      "subsubjects?session=" +
      encodeURIComponent(session) +
      "&subject=" +
      encodeURIComponent(subjectName);

    jQuery.ajax({
      url: url,
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        var subsubjects = [];
        if (response && Array.isArray(response)) {
          subsubjects = response;
        } else if (
          response &&
          response.success &&
          Array.isArray(response.data)
        ) {
          subsubjects = response.data;
        }

        subsubjects.forEach(function (subsubject) {
          var option = jQuery("<option>", {
            value: subsubject,
            text: subsubject,
          });
          if (selectedSubsubject && subsubject === selectedSubsubject) {
            option.prop("selected", true);
          }
          $subSelect.append(option);
        });
      },
      error: function (xhr, status, error) {
        console.error("세부과목 로드 오류:", error);
      },
    });
  },

  /**
   * 문제 카드 업데이트 (저장 후)
   */
  updateQuestionCard: function ($card, data) {
    var self = this;
    var s = self.config.selectors;

    // 보기 모드 컨텐츠 영역 찾기
    var $viewContent = $card.find(s.viewContent);
    if ($viewContent.length === 0) {
      console.error("[PTGates Admin] View content not found in card");
      console.error(
        "[PTGates Admin] Card HTML:",
        $card.prop("outerHTML").substring(0, 500)
      );
      return;
    }

    // 줄바꿈을 <br>로 변환하는 헬퍼 함수
    var escapeHtmlWithBreaks = function (text) {
      if (!text) return "";
      var escaped = self.escapeHtml(text);
      // 줄바꿈을 <br>로 변환
      escaped = escaped.replace(/\n/g, "<br>");
      return escaped;
    };

    // 모든 필드 찾기
    var $fields = $viewContent.find(".ptg-question-field");
    console.log("[PTGates Admin] Found fields:", $fields.length);

    // 지문 및 선택지 업데이트
    var fullContent = data.content || "";
    var contentText = fullContent;
    var optionsText = "";

    // 선택지 분리 로직 (renderQuestions와 동일)
    var escapedContent = self.escapeHtml(fullContent);
    var optionRegex = /(?:^|\s|>)(\(?\d+\)|[①-⑳]|\d+\.)\s/;
    var match = escapedContent.match(optionRegex);

    var contentHtml = "";
    var optionsHtml = "";

    if (match && match.index > 0) {
      var splitIndex = match.index;
      // 선택지 앞부분 (지문)
      contentHtml = escapedContent.substring(0, splitIndex);
      // 선택지 뒷부분 (옵션)
      var optText = escapedContent.substring(splitIndex);
      optionsHtml = optText.replace(/\n/g, "<br>");
    } else {
      contentHtml = escapedContent;
    }

    // 1. 지문 업데이트
    var $contentField = $viewContent.find(
      ".ptg-field-content .ptg-question-text"
    );
    if ($contentField.length > 0) {
      $contentField.html(contentHtml);
    }

    // 2. 선택지 업데이트
    var $optionsField = $viewContent.find(".ptg-field-options");
    if (optionsHtml) {
      if ($optionsField.length > 0) {
        // 이미 선택지 필드가 있으면 내용 업데이트
        // 선택지 필드 내부는 <div class="ptg-question-options">...</div> 가 아니라 바로 내용을 넣었던가?
        // renderQuestions: <div class="ptg-question-field ptg-field-options"><label>선택지:</label>${optionsHtml}</div>
        // optionsHtml 자체는 <div class="ptg-question-options"...>...</div>

        // 단순히 HTML을 교체하지 말고, 라벨 뒤의 내용을 교체해야 함, 구족 복잡함.
        // 쉽게 가기 위해 필드 전체를 다시 구성하거나 내부 div만 타겟팅.
        // 하지만 optionsHtml 변수 자체가 div를 포함하고 있음.

        // renderQuestions 에서 optionsHtml: '<div class="ptg-question-options"...>'

        // 기존 필드 있으면 교체
        $optionsField.find(".ptg-question-options").remove();
        $optionsField.append(optionsHtml);
        $optionsField.show();
      } else {
        // 없으면 새로 생성 (이미지 필드 다음, 혹은 지문 필드 다음에)
        // 지문 필드 찾기
        var $contentFieldWrapper = $viewContent.find(".ptg-field-content");
        var $imageField = $viewContent.find(".ptg-question-image-field");

        var newOptionsField = `<div class="ptg-question-field ptg-field-options"><label>선택지:</label>${optionsHtml}</div>`;

        if ($imageField.length > 0) {
          $imageField.after(newOptionsField);
        } else {
          $contentFieldWrapper.after(newOptionsField);
        }
      }
    } else {
      // 선택지가 없으면 필드 숨기기/제거
      if ($optionsField.length > 0) {
        $optionsField.remove();
      }
    }

    // 3. 정답 업데이트
    var $answerText = $viewContent.find(".ptg-field-answer .ptg-question-text");
    if ($answerText.length > 0) {
      $answerText.html(escapeHtmlWithBreaks(data.answer || "-"));
    }

    // 4. 해설 업데이트
    var $explanationText = $viewContent.find(
      ".ptg-field-explanation .ptg-question-text"
    );
    if ($explanationText.length > 0) {
      $explanationText.html(escapeHtmlWithBreaks(data.explanation || ""));
    }

    // 난이도 업데이트
    var difficultyText = data.difficulty || "-";
    if (data.difficulty === "1") difficultyText = "1 (하)";
    else if (data.difficulty === "2") difficultyText = "2 (중)";
    else if (data.difficulty === "3") difficultyText = "3 (상)";
    var $metaSpans = $viewContent.find(".ptg-question-meta span");
    if ($metaSpans.length > 0) {
      $metaSpans.eq(0).text("난이도: " + difficultyText);
    }

    // 활성 상태 업데이트
    if ($metaSpans.length > 1) {
      $metaSpans.eq(1).text("활성: " + (data.is_active ? "예" : "아니오"));
    }

    // 세부과목 업데이트
    if (data.subsubject) {
      $card.find(".ptg-question-subsubjects").text(data.subsubject);
    }

    // 이미지 업데이트
    if (data.image) {
      var $imageField = $viewContent.find(".ptg-question-image-field");

      if (data.image.hasNewImage && data.image.questionId) {
        // 새 이미지가 업로드된 경우 (우선 처리)
        // 년도/회차 정보 가져오기 (카드에서)
        var metaInfo = $card.find(".ptg-question-meta-info").text();
        var yearMatch = metaInfo.match(/(\d{4})년/);
        var sessionMatch = metaInfo.match(/(\d+)회/);
        var year = yearMatch ? yearMatch[1] : "";
        var session = sessionMatch ? sessionMatch[1] : "";

        if (year && session) {
          // 이미지 URL 생성
          var uploadBaseUrl =
            typeof ptgAdmin !== "undefined" && ptgAdmin.uploadUrl
              ? ptgAdmin.uploadUrl
              : "/wp-content/uploads";

          var filename = data.image.newImage || data.image.questionId + ".jpg";

          var imageUrl =
            uploadBaseUrl +
            "/ptgates-questions/" +
            year +
            "/" +
            session +
            "/" +
            filename;

          // 기존 이미지 필드 제거
          $imageField.remove();

          // 새 이미지 필드 추가 (지문 다음에)
          var $contentField = $fields.eq(0);
          if ($contentField.length > 0) {
            // 이미지 URL에 타임스탬프 추가하여 캐시 방지
            var timestamp = new Date().getTime();
            var newImageHtml = `
                            <div class="ptg-question-field ptg-question-image-field" style="max-width: 500px; max-height: 500px; margin: 10px 0;">
                                <div style="max-width: 500px; max-height: 500px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                                    <img src="${imageUrl}?t=${timestamp}" alt="문제 이미지" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;" onerror="this.onerror=null; this.src=this.src.replace(/\\.jpg$/, '.png').replace(/\\?t=\\d+/, '?t=' + Date.now());" />
                                </div>
                            </div>
                        `;
            $contentField.after(newImageHtml);
          }

          // 이미지 아이콘 추가 (없으면)
          if ($card.find(".ptg-image-indicator").length === 0) {
            $card
              .find(".ptg-question-id-info")
              .append(
                '<span class="ptg-image-indicator" title="이미지 있음">🖼️</span>'
              );
          }
        }
      } else if (data.image.deleted) {
        // 이미지 삭제된 경우 (새 이미지가 없을 때만)
        $imageField.remove();
        // 이미지 아이콘도 제거
        $card.find(".ptg-image-indicator").remove();
      }
    }
  },

  /**
   * 문제 삭제
   */
  deleteQuestion: function (questionId, $btn) {
    var self = this;
    var originalBtnText = $btn.text();

    // 삭제할 카드 찾기
    var $card = $btn.closest(self.config.selectors.card);

    $btn.text("삭제 중...").prop("disabled", true);

    jQuery.ajax({
      url: self.config.apiUrl + "questions/" + questionId,
      method: "DELETE",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
      },
      success: function (response) {
        if (response.success) {
          // 카드 제거 (애니메이션 효과)
          $card.fadeOut(300, function () {
            $card.remove();

            // 현재 페이지에 카드가 없으면 빈 상태 메시지 표시
            var $grid = jQuery(self.config.selectors.listContainer).find(
              ".ptg-questions-grid"
            );
            if (
              $grid.length > 0 &&
              $grid.find(self.config.selectors.card).length === 0
            ) {
              jQuery(self.config.selectors.listContainer).html(
                "<p>문제가 없습니다.</p>"
              );
            }
          });

          alert("문제가 삭제되었습니다.");
        } else {
          alert(
            "삭제에 실패했습니다: " +
              (response.data || response.message || "알 수 없는 오류")
          );
          $btn.text(originalBtnText).prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error(
          "[PTGates Admin] Delete Error:",
          status,
          error,
          xhr.responseText
        );
        alert("서버 통신 오류: " + status + " " + error);
        $btn.text(originalBtnText).prop("disabled", false);
      },
    });
  },

  // --- Utilities ---

  cleanText: function (text) {
    if (!text) return "";
    var cleaned = text
      .replace(/_x000D_/g, "")
      .replace(/\r\n/g, "\n")
      .replace(/\r/g, "\n");
    cleaned = cleaned.replace(/\n{2,}\s*([①-⑳])/g, "\n$1");
    cleaned = cleaned.replace(/\n{2,}/g, "\n");
    return cleaned;
  },

  escapeHtml: function (text) {
    if (!text) return "";
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  },
};

// Initialize on ready
jQuery(document).ready(function () {
  PTGates_Admin_List.init();
});
