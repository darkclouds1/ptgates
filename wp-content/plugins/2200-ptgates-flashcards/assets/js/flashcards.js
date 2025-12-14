jQuery(document).ready(function ($) {
  // --- PTGFlashApp: Main Application Logic ---
  const PTGFlashApp = {
    config: {
      // Loader script defines 'ptgFlash', not 'ptgFlashcards'
      root: typeof ptgFlash !== "undefined" ? ptgFlash.restUrl : "",
      nonce: typeof ptgFlash !== "undefined" ? ptgFlash.nonce : "",
      api: {
        sets: "sets",
        createSet: "create-set", // New endpoint
        cards: "cards",
        review: "review",
        courses: "/wp-json/ptg-study/v1/courses", // Study Plugin API
      },
    },
    state: {
      currentView: "dashboard",
      sets: [],
      studyQueue: [],
      currentIndex: 0,
      isFlipped: false,
    },

    init: function () {
      // Merge server config (ptgFlash) into existing config (this.config)
      // This ensures 'api' object is preserved
      this.config = $.extend({}, this.config, window.ptgFlash || {});

      // Ensure root is set
      this.config.root = this.config.restUrl || this.config.root;

      // Guest Check
      if (this.config.memberGrade === "guest") {
        alert("로그인이 필요한 서비스입니다.");
        window.location.href = "/login";
        return;
      }

      this.cacheDOM();
      this.bindEvents();
      this.setupFilterUI();
      this.populateSessions();
      this.populateSubjects("");
      this.populateSubSubjects("", "");
      this.loadSets();
      this.setupTipModal();
    },

    cacheDOM: function () {
      // Placeholder for future caching if needed
    },

    bindEvents: function () {
      const self = this;

      // Navigation
      $("#ptg-back-to-dash, #ptg-result-home").on("click", function () {
        self.switchView("dashboard");
        self.loadSets(); // Refresh stats
      });

      // Set Click (Delegate)
      $("#ptg-sets-grid").on("click", ".ptg-set-card", function () {
        const setId = $(this).data("id");
        self.startStudy(setId);
      });

      // Create Set
      $("#ptg-create-set-btn").on("click", function (e) {
        e.preventDefault();
        self.createSet();
      });

      // Tip Popup
      $("#ptg-tip-toggle").on("click", function () {
        $("#ptg-tip-popup").fadeToggle(200);
      });
      $(".ptg-tip-close").on("click", function () {
        $("#ptg-tip-popup").fadeOut(200);
      });

      // Toggle Create Section
      $("#ptg-toggle-create").on("click", function () {
        $(".ptgates-filter-section").slideToggle(200);
      });

      // Delete Set
      $("#ptg-sets-grid").on("click", ".ptg-btn-delete-set", function (e) {
        e.stopPropagation();
        const setId = $(this).data("id");
        const isDefault = $(this).data("default");

        let msg = "정말 삭제하시겠습니까?";
        if (isDefault)
          msg = "이 세트의 모든 카드를 비우시겠습니까? (세트는 유지됩니다)";

        if (!confirm(msg)) return;
        self.deleteSet(setId);
      });

      // Flashcard Interaction
      $("#ptg-active-card").on("click", function () {
        self.flipCard();
      });

      // SRS Buttons
      $(".ptg-btn-srs").on("click", function (e) {
        e.stopPropagation();
        const quality = $(this).data("quality");
        self.submitReview(quality);

        // Scroll to top on mobile
        if (window.innerWidth <= 768) {
          window.scrollTo({ top: 0, behavior: "smooth" });
        }
      });

      // Keyboard Shortcuts
      $(document).on("keydown", function (e) {
        if (self.state.currentView !== "study") return;

        if (e.code === "Space") {
          e.preventDefault(); // Prevent scroll
          self.flipCard();
        } else if (self.state.isFlipped) {
          if (e.key === "1") self.submitReview("again");
          if (e.key === "2") self.submitReview("hard");
          if (e.key === "3") self.submitReview("good");
          if (e.key === "4") self.submitReview("easy");
        }
      });
    },

    setupTipModal: function () {
      // Initial tip setup if needed
    },

    // --- API Calls ---

    loadSets: function () {
      const self = this;
      $.ajax({
        url: self.config.root + self.config.api.sets,
        method: "GET",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function (data) {
          self.state.sets = data;
          self.renderSets(data);
        },
        error: function () {
          $("#ptg-sets-grid").html(
            '<div class="ptg-error">세트 목록을 불러오지 못했습니다.</div>'
          );
        },
      });
    },

    // --- Filter Logic (Ported from Quiz) ---

    // Use injected map or fallback to empty object
    SESSION_STRUCTURE:
      typeof ptgFlash !== "undefined" && ptgFlash.subjectMap
        ? ptgFlash.subjectMap
        : {},

    setupFilterUI: function () {
      const self = this;
      const sessionSelect = $("#ptg-flash-filter-session");
      const subjectSelect = $("#ptg-flash-filter-subject");
      const subSubjectSelect = $("#ptg-flash-filter-subsubject");
      const limitSelect = $("#ptg-flash-filter-limit");

      // Session Change
      sessionSelect.on("change", function () {
        const session = $(this).val();
        self.populateSubjects(session);
        self.populateSubSubjects(session, "");
        self.updateRecommendedLimit();
      });

      // Subject Change
      subjectSelect.on("change", function () {
        const session = sessionSelect.val();
        const subject = $(this).val();
        self.populateSubSubjects(session, subject);
        self.updateRecommendedLimit();
      });

      // SubSubject Change
      subSubjectSelect.on("change", function () {
        self.updateRecommendedLimit();
      });
    },

    populateSessions: function () {
      const sessionSelect = $("#ptg-flash-filter-session");
      sessionSelect.html('<option value="">교시</option>');

      if (!this.SESSION_STRUCTURE) return;

      const sessions = Object.keys(this.SESSION_STRUCTURE);
      // Sort sessions numerically if possible
      sessions.sort((a, b) => parseInt(a) - parseInt(b));

      sessions.forEach((sess) => {
        sessionSelect.append(
          $("<option>")
            .val(sess)
            .text(sess + "교시")
        );
      });
    },

    populateSubjects: function (session) {
      const subjectSelect = $("#ptg-flash-filter-subject");
      const subSubjectSelect = $("#ptg-flash-filter-subsubject");

      subjectSelect.html('<option value="">과목</option>');
      subSubjectSelect.html('<option value="">세부과목</option>');

      let subjects = [];

      if (session && this.SESSION_STRUCTURE[session]) {
        // Specific session
        subjects = Object.keys(this.SESSION_STRUCTURE[session].subjects);
      } else {
        // All sessions -> Aggregate all subjects
        Object.keys(this.SESSION_STRUCTURE).forEach((sessKey) => {
          const sessSubjects = Object.keys(
            this.SESSION_STRUCTURE[sessKey].subjects
          );
          subjects = subjects.concat(sessSubjects);
        });
        // Unique sort
        subjects = [...new Set(subjects)];
      }

      subjects.forEach((subj) => {
        subjectSelect.append($("<option>").val(subj).text(subj));
      });
    },

    populateSubSubjects: function (session, subject) {
      const subSubjectSelect = $("#ptg-flash-filter-subsubject");
      subSubjectSelect.html('<option value="">세부과목</option>');

      let subs = [];

      // Helper to get subs from a specific session/subject
      const getSubsFromSubject = (sessKey, subjKey) => {
        if (
          this.SESSION_STRUCTURE[sessKey] &&
          this.SESSION_STRUCTURE[sessKey].subjects[subjKey]
        ) {
          return Object.keys(
            this.SESSION_STRUCTURE[sessKey].subjects[subjKey].subs
          );
        }
        return [];
      };

      if (session) {
        // Specific Session
        if (subject) {
          // Specific Subject
          subs = getSubsFromSubject(session, subject);
        } else {
          // All Subjects in Session
          if (this.SESSION_STRUCTURE[session]) {
            const subjects = this.SESSION_STRUCTURE[session].subjects;
            Object.keys(subjects).forEach((subjKey) => {
              subs = subs.concat(getSubsFromSubject(session, subjKey));
            });
          }
        }
      } else {
        // All Sessions
        if (subject) {
          // Specific Subject (across all sessions - though usually subject implies session)
          Object.keys(this.SESSION_STRUCTURE).forEach((sessKey) => {
            subs = subs.concat(getSubsFromSubject(sessKey, subject));
          });
        } else {
          // All Subjects in All Sessions
          Object.keys(this.SESSION_STRUCTURE).forEach((sessKey) => {
            const subjects = this.SESSION_STRUCTURE[sessKey].subjects;
            Object.keys(subjects).forEach((subjKey) => {
              subs = subs.concat(getSubsFromSubject(sessKey, subjKey));
            });
          });
        }
      }

      // Unique sort
      subs = [...new Set(subs)];

      subs.forEach((sub) => {
        subSubjectSelect.append($("<option>").val(sub).text(sub));
      });
    },

    updateRecommendedLimit: function () {
      const session = $("#ptg-flash-filter-session").val();
      const subject = $("#ptg-flash-filter-subject").val();
      const subsubject = $("#ptg-flash-filter-subsubject").val();
      const limitSelect = $("#ptg-flash-filter-limit");

      let total = null;

      if (session && this.SESSION_STRUCTURE[session]) {
        const sData = this.SESSION_STRUCTURE[session];
        if (subject && sData.subjects[subject]) {
          const subjData = sData.subjects[subject];
          if (subsubject && subjData.subs[subsubject]) {
            total = subjData.subs[subsubject];
          } else {
            total = subjData.total;
          }
        } else {
          total = sData.total;
        }
      }

      // Remove auto-added options
      limitSelect.find('option[data-auto-added="1"]').remove();

      if (total !== null) {
        // Add recommended option
        const exists = limitSelect.find(`option[value="${total}"]`).length > 0;
        if (!exists) {
          limitSelect.append(
            $("<option>")
              .val(total)
              .text(`${total}문제`)
              .attr("data-auto-added", "1")
          );
          // Sort options
          const options = limitSelect.find("option");
          options.sort((a, b) => {
            const va = $(a).val() === "full" ? 9999 : parseInt($(a).val()) || 0;
            const vb = $(b).val() === "full" ? 9999 : parseInt($(b).val()) || 0;
            return va - vb;
          });
          limitSelect.append(options);
        }
        limitSelect.val(total);
      } else {
        limitSelect.val("5"); // Default
      }
    },

    createSet: function () {
      const self = this;
      const mode = $("#ptg-flash-filter-random").val(); // 'flashcard' or 'random'
      const session = $("#ptg-flash-filter-session").val();
      const subject = $("#ptg-flash-filter-subject").val();
      const subsubject = $("#ptg-flash-filter-subsubject").val();
      const limit = $("#ptg-flash-filter-limit").val();

      const isRandom = mode === "random";

      const data = {
        mode: mode, // Pass the selected mode (random, flashcard, bookmark, review, wrong)
        random: isRandom,
        session: session,
        subject: subject,
        subsubject: subsubject,
        limit: limit,
      };

      $.ajax({
        url: self.config.root + self.config.api.createSet,
        method: "POST",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        data: data,
        success: function (res) {
          if (res.set_id === 1 && !isRandom && !session && !subject) {
            // Redirected to default set (already exists)
            self.startStudy(1);
          } else {
            self.loadSets();
          }
        },
        error: function (xhr, status, error) {
          console.log("Create Set Error:", xhr.status, xhr);
          if (xhr.status == 409) {
            self.showToast("이미 생성된 암기카드 세트입니다.");
          } else {
            let msg =
              xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : xhr.statusText || "오류가 발생했습니다.";

            // Translate common errors
            if (xhr.status === 500 || msg === "Internal Server Error") {
              msg = "서버 오류가 발생했습니다. (잠시 후 다시 시도해주세요)";
            }

            self.showToast(msg);
          }
        },
      });
    },

    deleteSet: function (setId) {
      const self = this;
      $.ajax({
        url: self.config.root + self.config.api.sets + "/" + setId,
        method: "DELETE",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function (res) {
          alert("세트가 삭제되었습니다.");
          self.loadSets();
        },
        error: function () {
          alert("세트 삭제 실패");
        },
      });
    },

    startStudy: function (setId) {
      // Check limit before starting
      if (!this.checkUsageLimit()) return;
      this.loadCards(setId);
    },

    loadCards: function (setId) {
      const self = this;
      // Load cards due for review (mode=review)
      $.ajax({
        url: self.config.root + self.config.api.cards,
        method: "GET",
        data: { set_id: setId, mode: "review" },
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function (data) {
          if (!data || data.length === 0) {
            alert("오늘 학습할 카드가 없습니다!");
            return;
          }
          self.state.studyQueue = data;
          self.state.currentIndex = 0;
          self.state.isFlipped = false;
          self.switchView("study");
          self.renderCard();
        },
        error: function () {
          alert("카드 로드 실패");
        },
      });
    },

    submitReview: function (quality) {
      const self = this;
      const card = self.state.studyQueue[self.state.currentIndex];

      if (!card) {
        console.error("No card found at index", self.state.currentIndex);
        return;
      }

      // Optimistic UI update
      self.nextCard();

      $.ajax({
        url: self.config.root + self.config.api.review,
        method: "POST",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        data: { card_id: card.card_id, quality: quality },
        success: function (res) {},
      });
    },

    // --- Logic & Rendering ---

    switchView: function (viewName) {
      this.state.currentView = viewName;
      $(".ptg-view").removeClass("active");
      $("#ptg-flash-" + viewName).addClass("active");
    },

    renderSets: function (sets) {
      const container = $("#ptg-sets-grid");
      container.empty();

      if (!sets || sets.length === 0) {
        container.html(
          '<div class="ptg-empty">생성된 암기카드 세트가 없습니다.</div>'
        );
        return;
      }

      // Sorting: Flashcard Only (non-random) first, then Random sets
      sets.sort((a, b) => {
        const isRandomA = a.set_name.includes(" [R]");
        const isRandomB = b.set_name.includes(" [R]");

        if (a.set_id == 1) return -1; // Default set always first
        if (b.set_id == 1) return 1;

        if (isRandomA && !isRandomB) return 1; // Random comes after
        if (!isRandomA && isRandomB) return -1;

        return 0; // Keep original order (created_at DESC)
      });

      sets.forEach((set) => {
        const total = set.total_cards || 0;
        const due = set.due_cards || 0;
        const isDefault = set.set_id == 1;

        // Check for Random marker [R]
        let displayName = set.set_name;
        let isRandom = false;
        if (displayName.includes(" [R]")) {
          isRandom = true;
          displayName = displayName.replace(" [R]", "");
        } else if (displayName.includes("[랜덤]")) {
          // Legacy check
          isRandom = true;
        }

        // Indicators
        let indicators = "";
        if (isDefault) {
          indicators += '<span class="ptg-badge-flash">🎴 암기카드만</span>';
        } else if (isRandom) {
          indicators += '<span class="ptg-badge-random">모의</span>';
        } else {
          indicators += '<span class="ptg-badge-flash">🎴 암기카드만</span>';
        }

        // Delete Button
        let deleteBtn = `<button class="ptg-btn-delete-set" data-id="${
          set.set_id
        }" data-default="${isDefault}" title="${
          isDefault ? "비우기" : "삭제"
        }">${isDefault ? "🧹" : "🗑️"}</button>`;

        const html = `
                    <div class="ptg-set-card ${
                      isRandom ? "is-random" : ""
                    }" data-id="${set.set_id}">
                        <div class="ptg-set-header">
                            <div class="ptg-set-title">${displayName}</div>
                            ${deleteBtn}
                        </div>
                        <div class="ptg-set-indicators">${indicators}</div>
                        <div class="ptg-set-stats">
                            <div class="ptg-stat-item">
                                <span class="ptg-stat-val due">${due}</span>
                                <span class="ptg-stat-label">학습 필요</span>
                            </div>
                            <div class="ptg-stat-item">
                                <span class="ptg-stat-val">${total}</span>
                                <span class="ptg-stat-label">총 카드</span>
                            </div>
                        </div>
                        </div>
                    </div>
                `;
        container.append(html);
      });
    },

    renderCard: function () {
      const self = this;
      // Check limit before rendering each card
      if (!this.checkUsageLimit()) return;

      // Increment usage for this card view
      this.incrementUsage();

      const card = this.state.studyQueue[this.state.currentIndex];
      const total = this.state.studyQueue.length;

      // Update Progress
      $("#ptg-progress-text").text(`${this.state.currentIndex + 1} / ${total}`);
      const pct = (this.state.currentIndex / total) * 100;
      $("#ptg-progress-fill").css("width", pct + "%");

      // Reset Flip
      this.state.isFlipped = false;
      $("#ptg-active-card").removeClass("flipped");
      $(".ptg-study-controls").removeClass("active"); // Hide controls

      if (!card) {
        console.error("Card data missing for index", this.state.currentIndex);
        return;
      }

      // Set Content
      const qNum = this.state.currentIndex + 1;
      const frontHtml =
        `<span style="font-weight:bold; margin-right:5px;">${qNum}.</span>` +
        this.formatContent(card.front_custom);

      // Subject Info
      let subjectInfo = "";
      if (card.exam_course || card.subject) {
        const parts = [];
        if (card.exam_course) parts.push(card.exam_course);

        // Resolve Parent Subject from Map if card.subject (SubSubject) exists
        let parentSubject = "";
        let subSubject = card.subject || "";

        if (subSubject && this.SESSION_STRUCTURE) {
          // Search for parent subject in the map
          Object.keys(this.SESSION_STRUCTURE).some((sessKey) => {
            const sData = this.SESSION_STRUCTURE[sessKey];
            if (sData && sData.subjects) {
              return Object.keys(sData.subjects).some((subjKey) => {
                const subjData = sData.subjects[subjKey];
                if (subjData.subs && subjData.subs[subSubject]) {
                  parentSubject = subjKey;
                  return true;
                }
                return false;
              });
            }
            return false;
          });
        }

        if (parentSubject) parts.push(parentSubject);
        if (subSubject) parts.push(subSubject);

        if (parts.length > 0) {
          subjectInfo = `<div style="margin-top:15px; font-size:0.85em; color:#888; text-align:right; border-top:1px solid #eee; padding-top:5px;">${parts.join(
            " > "
          )}</div>`;
        }
      }

      $("#ptg-card-front-content").html(frontHtml);
      $("#ptg-card-back-content").html(
        this.formatContent(card.back_custom) + subjectInfo
      );

      // Handle Image Loading for proper height calculation
      const images = $(
        "#ptg-card-front-content img, #ptg-card-back-content img"
      );
      let imagesLoaded = 0;

      if (images.length > 0) {
        images.on("load", function () {
          imagesLoaded++;
          if (imagesLoaded === images.length) {
            self.adjustCardHeight();
          } else {
            // Adjust incrementally as images load
            self.adjustCardHeight();
          }
        });

        // Safety timeout in case load event doesn't fire (cached)
        setTimeout(() => self.adjustCardHeight(), 500);
      }

      // Initial Adjustment (for text/cached)
      this.adjustCardHeight();
    },

    adjustCardHeight: function () {
      const self = this;
      // Wait for DOM update
      setTimeout(() => {
        const frontContent = $("#ptg-card-front-content");
        const backContent = $("#ptg-card-back-content");

        if (frontContent.length && backContent.length) {
          // Calculate required height including padding (approx 60px + hint 40px) -> Reduced buffer
          const frontH = Math.max(frontContent[0].scrollHeight + 70, 200);
          const backH = Math.max(backContent[0].scrollHeight + 70, 200);

          self.state.cardHeights = { front: frontH, back: backH };

          // Set initial height (Front)
          $(".ptg-card-container").css("height", frontH + "px");
        }
      }, 10);
    },

    formatContent: function (text) {
      if (!text) return "";

      // 1. Collapse multiple newlines into one
      let formatted = text.replace(/\n+/g, "\n");

      // 2. Convert to <br>
      formatted = formatted.replace(/\n/g, "<br>");

      return formatted;
    },

    flipCard: function () {
      this.state.isFlipped = !this.state.isFlipped;
      $("#ptg-active-card").toggleClass("flipped");
      $(".ptg-study-controls").toggleClass("active", this.state.isFlipped); // Toggle controls

      // Adjust height based on side
      if (this.state.cardHeights) {
        const newHeight = this.state.isFlipped
          ? this.state.cardHeights.back
          : this.state.cardHeights.front;
        $(".ptg-card-container").css("height", newHeight + "px");
      }
    },

    nextCard: function () {
      this.state.currentIndex++;
      if (this.state.currentIndex >= this.state.studyQueue.length) {
        this.finishStudy();
      } else {
        this.renderCard();
      }
    },

    finishStudy: function () {
      console.log("Study finished");
      this.switchView("result");
    },

    checkUsageLimit: function () {
      const grade = this.config.memberGrade;
      // Premium and Admin are unlimited
      if (grade === "premium" || grade === "administrator") return true;

      const limit =
        this.config.limits && this.config.limits[grade]
          ? this.config.limits[grade]
          : 0;

      // If limit is 0 (and not premium), it might mean guest or error, but guest is handled in init.
      // If basic/trial have 0 limit configured, block.

      const today = new Date().toISOString().slice(0, 10).replace(/-/g, "");
      const key = "ptg_flash_usage_" + today;
      const usage = parseInt(localStorage.getItem(key) || "0");

      if (usage >= limit) {
        if (
          confirm(
            `일일 학습 제한(${limit}개)을 초과했습니다.\n멤버십을 업그레이드 하시겠습니까?`
          )
        ) {
          window.location.href = this.config.membershipUrl;
        }
        return false;
      }
      return true;
    },

    incrementUsage: function () {
      const grade = this.config.memberGrade;
      if (grade === "premium" || grade === "administrator") return;

      const today = new Date().toISOString().slice(0, 10).replace(/-/g, "");
      const key = "ptg_flash_usage_" + today;
      let usage = parseInt(localStorage.getItem(key) || "0");
      usage++;
      localStorage.setItem(key, usage);
    },

    showToast: function (message) {
      const toast = $("#ptg-toast");
      toast.text(message);
      toast.addClass("show");
      setTimeout(function () {
        toast.removeClass("show");
      }, 3000);
    },
  };

  // Initialize App if container exists
  if ($("#ptg-flash-app").length) {
    window.PTGFlashApp = PTGFlashApp; // Expose for debugging
    PTGFlashApp.init();
  }

  // --- Legacy Modal Logic ---

  // Open Modal
  $(document).on("click", ".ptg-btn-flashcard", function (e) {
    e.preventDefault();

    // Extract Data from Quiz UI (Same as before)
    var questionText = $(".ptg-question-text").text().trim();
    var explanationText = $(".ptg-quiz-explanation").html();

    if (typeof QuizState !== "undefined" && QuizState.currentQuestionData) {
      var q = QuizState.currentQuestionData;
      questionText = q.content;
      explanationText =
        "<strong>정답: " + q.answer + "</strong><br><br>" + q.explanation;
      $("#ptg-card-source-id").val(q.id);
      var subject = q.category ? q.category.subject : "";
      $("#ptg-card-subject").val(subject);
    } else {
      questionText =
        $("#ptg-quiz-card").text().trim().substring(0, 200) + "...";
      explanationText = $("#ptg-quiz-explanation").html();
    }

    $("#ptg-card-front").val(questionText);
    $("#ptg-card-back").val(
      explanationText
        ? explanationText
            .replace(/<br\s*\/?>/gi, "\n")
            .replace(/<\/?[^>]+(>|$)/g, "")
        : ""
    );

    $("#ptg-flashcard-modal").fadeIn(200);
  });

  // Close Modal
  $(".ptg-flashcard-modal-close, .ptg-flashcard-modal-overlay").on(
    "click",
    function () {
      $("#ptg-flashcard-modal").fadeOut(200);
    }
  );

  // Submit Form (Legacy)
  $("#ptg-flashcard-form").on("submit", function (e) {
    e.preventDefault();

    var data = {
      front: $("#ptg-card-front").val(),
      back: $("#ptg-card-back").val(),
      source_id: $("#ptg-card-source-id").val(),
      subject: $("#ptg-card-subject").val(),
      set_id: "", // Let backend handle auto-assignment by subject
    };

    $.ajax({
      url: PTGFlashApp.config.root + PTGFlashApp.config.api.cards,
      method: "POST",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", PTGFlashApp.config.nonce);
      },
      data: data,
      success: function (response) {
        alert("암기카드가 생성되었습니다!");
        $("#ptg-flashcard-modal").fadeOut(200);
        $("#ptg-flashcard-form")[0].reset();
        if (typeof PTGFlashApp !== "undefined" && PTGFlashApp.loadSets) {
          PTGFlashApp.loadSets();
        }
      },
      error: function (err) {
        alert(
          "카드 생성 실패: " +
            (err.responseJSON ? err.responseJSON.message : err.statusText)
        );
      },
    });
  });
});
