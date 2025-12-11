(function ($) {
  "use strict";

  var PTGStudyToolbar = {
    init: function () {
      // Bind global events once
      this.bindGlobalEvents();

      // Initial check for toolbars
      this.initToolbars();

      // Expose to global scope for external calls (e.g. after AJAX load)
      window.PTGStudyToolbar = this;

      // Safe periodic check (polling) as a fallback for AJAX loads
      setInterval(function () {
        PTGStudyToolbar.initToolbars();
      }, 1000);
    },

    initToolbars: function () {
      // 1. Find new question containers that haven't been initialized
      var $newItems = $(".ptg-lesson-item").not('[data-toolbar-init="true"]');

      if ($newItems.length === 0) {
        return;
      }

      $newItems.each(function () {
        var $item = $(this);

        // Mark as initialized immediately to prevent re-entry
        $item.attr("data-toolbar-init", "true");

        var questionId = $item.data("lesson-id") || $item.data("question-id");
        if (!questionId) return;

        // Check if toolbar button already exists (prevent duplicates)
        if ($item.find(".ptg-contextual-action-btn").length > 0) {
          return;
        }

        // Find the answer area
        var $answerArea = $item.find(".ptg-lesson-answer-area").first();
        if ($answerArea.length === 0) return;

        // Find or create button container in answer area
        var $buttonContainer = $answerArea
          .find(".ptg-answer-buttons-container")
          .first();
        if ($buttonContainer.length === 0) {
          // Find existing buttons in answer area
          var $existingButtons = $answerArea.children("button");
          if ($existingButtons.length > 0) {
            // Create container and wrap existing buttons
            $buttonContainer = $(
              '<div class="ptg-answer-buttons-container"></div>'
            );
            $existingButtons.first().before($buttonContainer);
            $existingButtons.appendTo($buttonContainer);
          } else {
            // No existing buttons, create empty container
            $buttonContainer = $(
              '<div class="ptg-answer-buttons-container"></div>'
            );
            $answerArea.prepend($buttonContainer);
          }
        }

        // Add contextual action button to button container
        $buttonContainer.append(
          '<button class="ptg-contextual-action-btn" data-question-id="' +
            questionId +
            '" title="도구 메뉴" aria-label="문제 도구 메뉴 열기">' +
            "⋮" +
            "</button>"
        );

        // Add toolbar after button container (if not already exists)
        if ($item.find(".ptg-question-toolbar").length === 0) {
          $buttonContainer.after(
            '<div class="ptg-question-toolbar" style="display: none;">' +
              '<div class="ptg-toolbar-icons">' +
              '<button class="ptg-toolbar-btn ptg-btn-bookmark" data-action="bookmark" data-question-id="' +
              questionId +
              '" title="북마크">' +
              '<span class="ptg-toolbar-icon">🔖</span>' +
              "</button>" +
              '<button class="ptg-toolbar-btn ptg-btn-review" data-action="review" data-question-id="' +
              questionId +
              '" title="복습 표시">' +
              '<span class="ptg-toolbar-icon">🔁</span>' +
              "</button>" +
              '<button class="ptg-toolbar-btn ptg-btn-notes" data-action="memo" data-question-id="' +
              questionId +
              '" title="메모">' +
              '<span class="ptg-toolbar-icon">📝</span>' +
              "</button>" +
              '<button class="ptg-toolbar-btn ptg-btn-flashcard" data-action="flashcard" data-question-id="' +
              questionId +
              '" title="암기카드">' +
              '<span class="ptg-toolbar-icon">🗂️</span>' +
              "</button>" +
              "</div>" +
              "</div>"
          );
        }

        // Initial status fetch
        PTGStudyToolbar.updateToolbarStatus(questionId);
      });
    },

    updateToolbarStatus: function (questionId) {
      if (!questionId) return;

      // Check if user is logged in before fetching status
      if (!this.isUserLoggedIn()) {
        return;
      }

      $.ajax({
        url:
          (window.location.origin || "") +
          "/wp-json/ptg-quiz/v1/questions/" +
          questionId +
          "/user-status",
        method: "GET",
        headers: {
          "X-WP-Nonce":
            (window.ptgStudy && window.ptgStudy.api_nonce) ||
            (window.ptgPlatform && window.ptgPlatform.nonce) ||
            (window.wpApiSettings && window.wpApiSettings.nonce) ||
            "",
        },
        success: function (status) {
          var $item = $(
            '.ptg-lesson-item[data-lesson-id="' +
              questionId +
              '"], .ptg-lesson-item[data-question-id="' +
              questionId +
              '"]'
          );
          var $toolbar = $item.find(".ptg-question-toolbar");

          // API response is wrapped in 'data' property by Rest::success
          var data = status.data || status;

          // Explicitly check for true/truthy values
          var isBookmarked = !!data.bookmark;
          var isReview = !!data.review;
          var isMemo = !!data.memo;
          var isFlashcard = !!data.flashcard;

          if (isBookmarked) {
            $toolbar.find(".ptg-btn-bookmark").addClass("is-active");
          } else {
            $toolbar.find(".ptg-btn-bookmark").removeClass("is-active");
          }

          if (isReview) {
            $toolbar.find(".ptg-btn-review").addClass("is-active");
          } else {
            $toolbar.find(".ptg-btn-review").removeClass("is-active");
          }

          if (isMemo) {
            $toolbar.find(".ptg-btn-notes").addClass("is-active");
          } else {
            $toolbar.find(".ptg-btn-notes").removeClass("is-active");
          }

          if (isFlashcard) {
            $toolbar.find(".ptg-btn-flashcard").addClass("is-active");
          } else {
            $toolbar.find(".ptg-btn-flashcard").removeClass("is-active");
          }
        },
        error: function (err) {
          console.error(
            "Failed to fetch user status for question " + questionId,
            err
          );
        },
      });
    },

    bindGlobalEvents: function () {
      // Contextual Action Button - Toggle Toolbar
      $(document)
        .off("click", ".ptg-contextual-action-btn")
        .on("click", ".ptg-contextual-action-btn", function (e) {
          e.preventDefault();
          e.stopPropagation();

          var $btn = $(this);
          var $lessonItem = $btn.closest(".ptg-lesson-item");
          var $toolbar = $lessonItem.find(".ptg-question-toolbar");

          // Close all other toolbars
          $(".ptg-question-toolbar").not($toolbar).slideUp(200);
          $(".ptg-contextual-action-btn").not($btn).css({
            background: "transparent",
            "border-color": "#ddd",
            color: "#666",
          });

          // Toggle current toolbar
          $toolbar.slideToggle(200, function () {
            if ($toolbar.is(":visible")) {
              $btn.css({
                background: "#4a90e2",
                "border-color": "#4a90e2",
                color: "white",
              });

              // Refresh status when opening toolbar
              var questionId = $btn.data("question-id");
              if (questionId) {
                PTGStudyToolbar.updateToolbarStatus(questionId);
              }
            } else {
              $btn.css({
                background: "transparent",
                "border-color": "#ddd",
                color: "#666",
              });
            }
          });
        });

      // Bookmark Handler
      $(document)
        .off("click", ".ptg-btn-bookmark")
        .on("click", ".ptg-btn-bookmark", function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (!window.PTGStudyToolbar.isUserLoggedIn()) {
            window.PTGStudyToolbar.showLoginRequiredModal();
            return;
          }

          var $btn = $(this);
          var questionId = $btn.data("question-id");
          var isActive = $btn.hasClass("is-active");

          $btn.toggleClass("is-active");

          $.ajax({
            url:
              (window.location.origin || "") +
              "/wp-json/ptg-quiz/v1/questions/" +
              questionId +
              "/state",
            method: "PATCH",
            data: JSON.stringify({ bookmarked: !isActive }),
            contentType: "application/json",
            headers: {
              "X-WP-Nonce":
                (window.ptgStudy && window.ptgStudy.api_nonce) ||
                (window.ptgPlatform && window.ptgPlatform.nonce) ||
                (window.wpApiSettings && window.wpApiSettings.nonce) ||
                "",
            },
            success: function (response) {},
            error: function (xhr) {
              console.error("Bookmark failed");
              $btn.toggleClass("is-active");
              alert("북마크 저장에 실패했습니다.");
            },
          });
        });

      // Review Handler
      $(document)
        .off("click", ".ptg-btn-review")
        .on("click", ".ptg-btn-review", function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (!window.PTGStudyToolbar.isUserLoggedIn()) {
            window.PTGStudyToolbar.showLoginRequiredModal();
            return;
          }

          var $btn = $(this);
          var questionId = $btn.data("question-id");
          var isActive = $btn.hasClass("is-active");

          $btn.toggleClass("is-active");

          $.ajax({
            url:
              (window.location.origin || "") +
              "/wp-json/ptg-quiz/v1/questions/" +
              questionId +
              "/state",
            method: "PATCH",
            data: JSON.stringify({ needs_review: !isActive }),
            contentType: "application/json",
            headers: {
              "X-WP-Nonce":
                (window.ptgStudy && window.ptgStudy.api_nonce) ||
                (window.ptgPlatform && window.ptgPlatform.nonce) ||
                (window.wpApiSettings && window.wpApiSettings.nonce) ||
                "",
            },
            success: function (response) {},
            error: function (xhr) {
              console.error("Review mark failed");
              $btn.toggleClass("is-active");
              alert("복습 표시 저장에 실패했습니다.");
            },
          });
        });

      // Memo Handler
      $(document)
        .off("click", ".ptg-btn-notes")
        .on("click", ".ptg-btn-notes", function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (!window.PTGStudyToolbar.isUserLoggedIn()) {
            window.PTGStudyToolbar.showLoginRequiredModal();
            return;
          }

          var $btn = $(this);
          var questionId = $btn.data("question-id");
          var $lessonItem = $btn.closest(".ptg-lesson-item");

          // Check if memo area already exists
          var $existingMemo = $lessonItem.find(".ptg-memo-inline-area");
          if ($existingMemo.length > 0) {
            // Toggle visibility
            $existingMemo.slideToggle(200);
            return;
          }

          // Get current memo and show inline textarea
          $.ajax({
            url:
              (window.location.origin || "") +
              "/wp-json/ptg-quiz/v1/questions/" +
              questionId +
              "/memo",
            method: "GET",
            headers: {
              "X-WP-Nonce":
                (window.ptgStudy && window.ptgStudy.api_nonce) ||
                (window.ptgPlatform && window.ptgPlatform.nonce) ||
                (window.wpApiSettings && window.wpApiSettings.nonce) ||
                "",
            },
            success: function (response) {
              // API response might be wrapped in 'data' property by Rest::success
              var content = "";
              if (response && response.data && response.data.content) {
                content = response.data.content;
              } else if (response && response.content) {
                content = response.content;
              }
              window.PTGStudyToolbar.showInlineMemo(
                $lessonItem,
                questionId,
                content
              );
            },
            error: function () {
              window.PTGStudyToolbar.showInlineMemo(
                $lessonItem,
                questionId,
                ""
              );
            },
          });
        });

      // Flashcard Handler
      $(document)
        .off("click", ".ptg-btn-flashcard")
        .on("click", ".ptg-btn-flashcard", function (e) {
          e.preventDefault();
          e.stopPropagation();

          if (!window.PTGStudyToolbar.isUserLoggedIn()) {
            window.PTGStudyToolbar.showLoginRequiredModal();
            return;
          }

          var questionId = $(this).data("question-id");
          var $lessonItem = $(this).closest(".ptg-lesson-item");

          // Helper function to convert HTML to text while preserving line breaks
          function htmlToText($element) {
            var clone = $element.clone();
            // Replace <br> with newline
            clone.find("br").replaceWith("\n");
            // Get text content
            return clone.text().trim();
          }

          // Get complete question content (text + options as displayed)
          var questionText = "";

          // Get question text (including question number)
          var $questionText = $lessonItem.find(".ptg-question-text");
          if ($questionText.length > 0) {
            questionText = htmlToText($questionText);
          }

          // Get question options
          var $questionOptions = $lessonItem.find(".ptg-question-options");
          if ($questionOptions.length > 0) {
            var optionsText = "";
            $questionOptions.find(".ptg-question-option").each(function () {
              optionsText += "\n" + $(this).text().trim();
            });
            questionText += optionsText;
          }

          // Get answer and explanation separately
          var answerText = "";
          var $answerContent = $lessonItem.find(".answer-content");

          if ($answerContent.length > 0) {
            // Extract answer (first <p> contains "정답: X")
            var $answerP = $answerContent.find("p").first();
            var answerValue = "";
            if ($answerP.length > 0) {
              // Get just the answer value (e.g., "1", "2", etc.)
              answerValue = $answerP
                .text()
                .replace(/정답:\s*/, "")
                .trim();
            }

            // Extract explanation (inside the <div> after <hr>)
            var $explanationDiv = $answerContent.find("div").last();
            var explanationValue = "";
            if ($explanationDiv.length > 0) {
              explanationValue = htmlToText($explanationDiv);
            }

            // Combine: "정답: X" + newline + explanation
            answerText = "정답: " + answerValue;
            if (explanationValue) {
              answerText += "\n" + explanationValue;
            }
          }

          window.PTGStudyToolbar.showFlashcardModal(
            questionId,
            questionText,
            answerText
          );
        });
    },

    showLoginRequiredModal: function () {
      var loginUrl = (window.ptgStudy && window.ptgStudy.login_url) || "/login";

      if ($("#ptg-login-required-modal").length === 0) {
        var modalHtml =
          '<div id="ptg-login-required-modal" class="ptg-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%;">' +
          '<div class="ptg-modal-overlay" style="position: absolute; width: 100%; height: 100%; background: rgba(0,0,0,0.5);"></div>' +
          '<div class="ptg-modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">' +
          '<div class="ptg-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">' +
          '<h3 style="margin: 0; font-size: 18px; font-weight: 600;">로그인 필요</h3>' +
          '<button class="ptg-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>' +
          "</div>" +
          '<div class="ptg-modal-body" style="margin-bottom: 20px;">' +
          '<p style="margin: 0; color: #4a5568;">이 기능을 사용하려면 로그인이 필요합니다.</p>' +
          "</div>" +
          '<div class="ptg-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">' +
          '<button class="ptg-btn ptg-btn-secondary ptg-modal-close-btn" style="padding: 8px 16px; border-radius: 4px; border: 1px solid #cbd5e0; background: #fff; cursor: pointer;">닫기</button>' +
          '<a href="' +
          loginUrl +
          '" class="ptg-btn ptg-btn-primary" style="padding: 8px 16px; border-radius: 4px; background: #4a90e2; color: #fff; text-decoration: none; border: none; cursor: pointer;">로그인</a>' +
          "</div>" +
          "</div>" +
          "</div>";
        $("body").append(modalHtml);

        // Bind close events
        $("#ptg-login-required-modal").on(
          "click",
          ".ptg-modal-close, .ptg-modal-close-btn, .ptg-modal-overlay",
          function () {
            $("#ptg-login-required-modal").fadeOut(200);
          }
        );
      }

      $("#ptg-login-required-modal").fadeIn(200);
    },

    showInlineMemo: function ($lessonItem, questionId, initialContent) {
      // Create inline memo area HTML
      var memoHtml =
        '<div class="ptg-memo-inline-area" style="margin-top: 16px; padding: 16px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">' +
        '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">' +
        '<strong style="font-size: 14px; color: #333;">📝 메모</strong>' +
        '<button class="ptg-memo-close-btn" style="background: transparent; border: none; color: #666; cursor: pointer; font-size: 20px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>' +
        "</div>" +
        '<textarea class="ptg-memo-textarea" data-question-id="' +
        questionId +
        '" placeholder="메모를 입력하세요... (포커스를 잃으면 자동 저장됩니다)" style="width: 100%; min-height: 200px; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit; font-size: 14px; line-height: 1.6; resize: none; overflow: hidden; box-sizing: border-box;">' +
        (initialContent || "") +
        "</textarea>" +
        '<div class="ptg-memo-status" style="margin-top: 8px; font-size: 12px; color: #666; min-height: 18px;"></div>' +
        "</div>";

      // Append to lesson item
      $lessonItem.find(".ptg-lesson-answer-area").after(memoHtml);

      var $memoArea = $lessonItem.find(".ptg-memo-inline-area");
      var $textarea = $memoArea.find(".ptg-memo-textarea");
      var $status = $memoArea.find(".ptg-memo-status");

      // Adjust height based on content, focus textarea
      PTGStudyToolbar.autoResizeTextarea($textarea, true);
      $textarea.focus();

      // Close button handler
      $memoArea.find(".ptg-memo-close-btn").on("click", function () {
        $memoArea.slideUp(200, function () {
          $(this).remove();
        });
      });

      $textarea.on("input", function () {
        PTGStudyToolbar.autoResizeTextarea($(this), false);
      });

      // Paste handler to format circled numbers
      $textarea.on("paste", function (e) {
        e.preventDefault();
        var text = "";
        if (
          e.originalEvent.clipboardData &&
          e.originalEvent.clipboardData.getData
        ) {
          text = e.originalEvent.clipboardData.getData("text/plain");
        } else if (window.clipboardData && window.clipboardData.getData) {
          text = window.clipboardData.getData("Text");
        }

        // Remove newline after circled numbers (①\nText -> ① Text)
        // Also handle potential spaces around newline
        var formatted = text.replace(/([①-⑳])\s*[\r\n]+\s*/g, "$1 ");

        // Insert at cursor position
        var start = this.selectionStart;
        var end = this.selectionEnd;
        var currentVal = $(this).val();

        $(this).val(
          currentVal.substring(0, start) + formatted + currentVal.substring(end)
        );

        // Move cursor to end of inserted text
        this.selectionStart = this.selectionEnd = start + formatted.length;

        // Resize
        PTGStudyToolbar.autoResizeTextarea($(this), false);
      });

      // Auto-save on blur
      $textarea.on("blur", function () {
        var content = $(this).val();
        var qId = $(this).data("question-id");

        $status.text("저장 중...").css("color", "#666");

        $.ajax({
          url:
            (window.location.origin || "") +
            "/wp-json/ptg-quiz/v1/questions/" +
            qId +
            "/memo",
          method: "POST",
          data: JSON.stringify({ content: content }),
          contentType: "application/json",
          headers: {
            "X-WP-Nonce":
              (window.ptgStudy && window.ptgStudy.api_nonce) ||
              (window.ptgPlatform && window.ptgPlatform.nonce) ||
              (window.wpApiSettings && window.wpApiSettings.nonce) ||
              "",
          },
          success: function () {
            $status.text("✓ 저장되었습니다").css("color", "#10b981");

            // Update toolbar icon status
            var $item = $(
              '.ptg-lesson-item[data-lesson-id="' +
                qId +
                '"], .ptg-lesson-item[data-question-id="' +
                qId +
                '"]'
            );
            var $toolbar = $item.find(".ptg-question-toolbar");
            if (content.trim().length > 0) {
              $toolbar.find(".ptg-btn-notes").addClass("is-active");
            } else {
              $toolbar.find(".ptg-btn-notes").removeClass("is-active");
            }

            setTimeout(function () {
              $status.fadeOut(300, function () {
                $(this).text("").show();
              });
            }, 2000);
          },
          error: function () {
            $status.text("✗ 저장 실패").css("color", "#ef4444");
          },
        });
      });
    },

    autoResizeTextarea: function ($field, allowLargeDefault) {
      if (!$field || !$field.length) {
        return;
      }
      var el = $field[0];
      el.style.height = "auto";

      var minHeight = allowLargeDefault ? 200 : 120;
      var targetHeight = Math.max(el.scrollHeight, minHeight);
      el.style.height = targetHeight + "px";
    },

    showFlashcardModal: function (questionId, front, back) {
      if ($("#ptg-study-flashcard-modal").length === 0) {
        var modalHtml =
          '<div id="ptg-study-flashcard-modal" class="ptg-modal" style="display: none;">' +
          '<div class="ptg-modal-overlay"></div>' +
          '<div class="ptg-modal-content">' +
          '<div class="ptg-modal-header">' +
          "<h3>암기카드 만들기</h3>" +
          '<button class="ptg-modal-close">&times;</button>' +
          "</div>" +
          '<div class="ptg-modal-body">' +
          '<div class="form-group">' +
          "<label>앞면 (질문)</label>" +
          '<textarea id="ptg-flashcard-front" rows="4"></textarea>' +
          "</div>" +
          '<div class="form-group">' +
          "<label>뒷면 (답변/해설)</label>" +
          '<textarea id="ptg-flashcard-back" rows="4"></textarea>' +
          "</div>" +
          "</div>" +
          '<div class="ptg-modal-footer">' +
          '<div class="ptg-flashcard-status" style="flex: 1; font-size: 14px; color: #666;"></div>' +
          '<button class="ptg-btn ptg-btn-secondary ptg-modal-cancel">취소</button>' +
          '<button class="ptg-btn ptg-btn-primary ptg-flashcard-save">저장</button>' +
          "</div>" +
          "</div>" +
          "</div>";
        $("body").append(modalHtml);

        $("#ptg-study-flashcard-modal").on(
          "click",
          ".ptg-modal-close, .ptg-modal-cancel, .ptg-modal-overlay",
          function () {
            $("#ptg-study-flashcard-modal").fadeOut(200);
          }
        );

        // Save handler (bound once)
        $("#ptg-study-flashcard-modal").on(
          "click",
          ".ptg-flashcard-save",
          function () {
            var frontText = $("#ptg-flashcard-front").val();
            var backText = $("#ptg-flashcard-back").val();
            var qId = $("#ptg-study-flashcard-modal").data("question-id");
            var $status = $("#ptg-study-flashcard-modal .ptg-flashcard-status");

            // Validate input
            if (!frontText || !backText) {
              $status
                .text("✗ 앞면과 뒷면 내용을 모두 입력해주세요")
                .css("color", "#ef4444");
              return;
            }

            if (!qId) {
              $status
                .text("✗ 문제 ID를 찾을 수 없습니다")
                .css("color", "#ef4444");
              return;
            }

            $status.text("세트 정보 확인 중...").css("color", "#666");

            // Force set_id = 1 (Default Set)
            var setId = 1;

            $status.text("저장 중...").css("color", "#666");

            // Extract subject from study view header
            var subject = "";
            var $headerTitle = $(".ptg-lesson-header h3");
            if ($headerTitle.length > 0) {
              var fullTitle = $headerTitle.text().trim();
              // Format is usually "Category · Subject" or just "Subject"
              if (fullTitle.includes("·")) {
                subject = fullTitle.split("·").pop().trim();
              } else {
                subject = fullTitle;
              }
              // Remove " 전체 학습" suffix if present (for category view)
              subject = subject.replace(" 전체 학습", "");
            }

            // Now create the flashcard
            $.ajax({
              url:
                (window.location.origin || "") + "/wp-json/ptg-flash/v1/cards",
              method: "POST",
              data: JSON.stringify({
                set_id: setId,
                source_type: "question",
                source_id: qId,
                front: frontText,
                back: backText,
                subject: subject, // Add subject parameter
              }),
              contentType: "application/json",
              headers: {
                "X-WP-Nonce":
                  (window.ptgStudy && window.ptgStudy.api_nonce) ||
                  (window.ptgPlatform && window.ptgPlatform.nonce) ||
                  (window.wpApiSettings && window.wpApiSettings.nonce) ||
                  "",
              },
              success: function (response) {
                $status.text("✓ 저장되었습니다").css("color", "#10b981");

                // Update toolbar icon status
                var $item = $(
                  '.ptg-lesson-item[data-lesson-id="' +
                    qId +
                    '"], .ptg-lesson-item[data-question-id="' +
                    qId +
                    '"]'
                );
                var $toolbar = $item.find(".ptg-question-toolbar");
                $toolbar.find(".ptg-btn-flashcard").addClass("is-active");

                setTimeout(function () {
                  $("#ptg-study-flashcard-modal").fadeOut(200, function () {
                    $status.text("").css("color", "#666");
                  });
                }, 1500);
              },
              error: function (xhr, status, error) {
                // Try to recover from JSON parse error (mixed HTML)
                if (status === "parsererror" && xhr.responseText) {
                  var jsonMatch = xhr.responseText.match(/\{.*"card_id".*\}/);
                  if (jsonMatch) {
                    try {
                      var response = JSON.parse(jsonMatch[0]);
                      // Manually trigger success logic
                      console.log("Recovered from JSON error:", response);
                      $status.text("✓ 저장되었습니다").css("color", "#10b981");

                      var $item = $(
                        '.ptg-lesson-item[data-lesson-id="' +
                          qId +
                          '"], .ptg-lesson-item[data-question-id="' +
                          qId +
                          '"]'
                      );
                      var $toolbar = $item.find(".ptg-question-toolbar");
                      $toolbar.find(".ptg-btn-flashcard").addClass("is-active");

                      setTimeout(function () {
                        $("#ptg-study-flashcard-modal").fadeOut(
                          200,
                          function () {
                            $status.text("").css("color", "#666");
                          }
                        );
                      }, 1000);
                      return;
                    } catch (e) {
                      console.error("Failed to recover JSON", e);
                    }
                  }
                }

                console.error("Flashcard save failed:", {
                  status: xhr.status,
                  statusText: xhr.statusText,
                  response: xhr.responseJSON || xhr.responseText,
                  error: error,
                });

                var errorMsg = "✗ 저장 실패";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                  errorMsg += ": " + xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                  errorMsg += ": API 없음";
                } else if (xhr.status === 401 || xhr.status === 403) {
                  errorMsg += ": 권한 없음";
                }
                $status.text(errorMsg).css("color", "#ef4444");
              },
            });
          }
        );
      }

      $("#ptg-flashcard-front").val(front);
      $("#ptg-flashcard-back").val(back);
      // Clear status message when opening modal
      $("#ptg-study-flashcard-modal .ptg-flashcard-status")
        .text("")
        .css("color", "#666");
      $("#ptg-study-flashcard-modal")
        .data("question-id", questionId)
        .fadeIn(200);
    },

    isUserLoggedIn: function () {
      // Check Study Plugin context
      if (
        window.ptgStudy &&
        typeof window.ptgStudy.is_user_logged_in !== "undefined"
      ) {
        // Handle both boolean and string 'true'/'false'
        return String(window.ptgStudy.is_user_logged_in) === "true";
      }
      // Check Quiz Plugin / Platform context
      if (
        window.ptgPlatform &&
        typeof window.ptgPlatform.userId !== "undefined"
      ) {
        return parseInt(window.ptgPlatform.userId) > 0;
      }
      // Fallback: if we can't determine, assume false to be safe
      return false;
    },
  };

  // Initialize on ready
  $(document).ready(function () {
    PTGStudyToolbar.init();
  });
})(jQuery);
