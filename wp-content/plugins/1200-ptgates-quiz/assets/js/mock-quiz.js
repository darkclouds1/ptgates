/**
 * Mock Exam (모의고사) Handling Script
 */
var MockQuiz = (function ($) {
  "use strict";

  return {
    init: function () {
      // "모의시험" 모드인지 확인
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get("mode") !== "mock") {
        return;
      }

      console.log("[MockQuiz] Initialized");
      this.bindEvents();
      this.loadRounds();
    },

    roundsData: [], // [NEW] Store data for dynamic filtering

    bindEvents: function () {
      // 버튼 클릭 이벤트 바인딩 (일반 함수로 감싸서 this 컨텍스트 유지)
      $("#ptg-quiz-mock-start-btn").on("click", (e) => {
        e.preventDefault();
        this.startExam();
      });

      // [NEW] 회차 선택 시 교시 목록 업데이트
      $("#ptg-quiz-mock-round").on("change", (e) => {
        this.updateCourses($(e.target).val());
      });
    },

    loadRounds: function () {
      const $select = $("#ptg-quiz-mock-round");
      if ($select.length === 0) return;

      $select.empty();
      $select.append('<option value="">회차 로딩 중...</option>');
      $select.prop("disabled", true);

      $.ajax({
        url: "/wp-json/ptg-quiz/v1/sessions/mock",
        method: "GET",
        dataType: "json",
        success: (response) => {
          $select.empty();
          $select.append('<option value="">회차를 선택하세요</option>');
          $select.prop("disabled", false);

          // Handle wrapped response
          const rounds = response.data || response;

          if (Array.isArray(rounds) && rounds.length > 0) {
            this.roundsData = rounds; // Store for later usage
            rounds.forEach(function (round) {
              $select.append(
                `<option value="${round.id}">${round.title}</option>`
              );
            });
          } else {
            $select.append(
              '<option value="" disabled>진행 중인 모의고사가 없습니다</option>'
            );
          }
        },
        error: (xhr, status, error) => {
          console.error("[MockQuiz] Failed to load rounds:", error);
          $select.empty();
          $select.append('<option value="">회차 로드 실패</option>');
          $select.prop("disabled", false);
        },
      });
    },

    updateCourses: function (sessionId) {
      const $courseSelect = $("#ptg-quiz-mock-course");
      if ($courseSelect.length === 0) return;

      $courseSelect.empty();
      $courseSelect.append('<option value="">교시 선택</option>');

      if (!sessionId) return;

      // Find selected session data
      const sessionData = this.roundsData.find((r) => r.id == sessionId);

      if (sessionData && Array.isArray(sessionData.courses)) {
        // Sort courses just in case
        sessionData.courses.sort((a, b) => a - b);

        sessionData.courses.forEach((course) => {
          $courseSelect.append(
            `<option value="${course}">${course}교시</option>`
          );
        });
      }
    },

    startExam: function () {
      const session = $("#ptg-quiz-mock-round").val(); // exam_session (1001...)
      const course = $("#ptg-quiz-mock-course").val(); // exam_course (1, 2)
      const modeVal = $("#ptg-quiz-mock-mode").val(); // study or exam

      if (!session) {
        if (typeof PTG_quiz_alert === "function") {
          PTG_quiz_alert("회차를 선택해주세요.");
        } else {
          alert("회차를 선택해주세요.");
        }
        return;
      }
      if (!course) {
        if (typeof PTG_quiz_alert === "function") {
          PTG_quiz_alert("교시를 선택해주세요.");
        } else {
          alert("교시를 선택해주세요.");
        }
        return;
      }

      // 모드 매핑
      // study -> learning (기존 로직 재사용, 피드백 즉시 표시)
      // exam -> mock_exam (정답 숨김, 제출 후 결과)
      let quizMode = "learning";
      if (modeVal === "exam") {
        quizMode = "mock_exam";
      }

      // 타이머 설정 (교시별 시간)
      // FIXME: 정확한 시간 기준 확인 필요. 일단 1교시 90분, 2교시 75분으로 설정
      let timerMinutes = 50; // 기본값
      if (course == "1") {
        timerMinutes = 90;
      } else if (course == "2") {
        timerMinutes = 75;
      } else if (course == "3") {
        timerMinutes = 75;
      }

      // quiz.js의 startQuizWithParams 호출
      const params = {
        session: session,
        exam_course: course,
        mode: quizMode,
        limit: 0,
        full_session: true,
        timer_minutes: timerMinutes,
      };

      console.log("[MockQuiz] Starting exam with params:", params);

      if (window.PTGQuiz && window.PTGQuiz.startQuizWithParams) {
        window.PTGQuiz.startQuizWithParams(params);
      } else {
        console.error("PTGQuiz.startQuizWithParams not found");
        alert("퀴즈 시스템을 불러오는 중입니다. 잠시 후 다시 시도해주세요.");
      }
    },
  };
})(jQuery);

jQuery(document).ready(function () {
  MockQuiz.init();
});
