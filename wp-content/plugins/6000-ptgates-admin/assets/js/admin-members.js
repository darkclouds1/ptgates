jQuery(document).ready(function ($) {
  // 등급 수정 모달 열기
  $(".ptg-edit-grade-btn").on("click", function (e) {
    e.preventDefault();
    var userId = $(this).data("user-id");

    // 데이터 로드
    $.post(
      ajaxurl,
      {
        action: "ptg_admin_get_member",
        user_id: userId,
      },
      function (response) {
        if (response.success) {
          var data = response.data;
          $("#ptg-edit-user-id").val(data.user_id);
          $("#ptg-edit-grade").val(data.member_grade);
          $("#ptg-edit-status").val(data.billing_status);

          if (data.billing_expiry_date) {
            // datetime에서 date 부분만 추출 (YYYY-MM-DD)
            var datePart = data.billing_expiry_date.split(" ")[0];
            $("#ptg-edit-expiry").val(datePart);
          } else {
            $("#ptg-edit-expiry").val("");
          }

          $("#ptg-grade-modal").show();
        } else {
          alert(response.data);
        }
      }
    );
  });

  // 등급 수정 폼 제출
  $("#ptg-grade-form").on("submit", function (e) {
    e.preventDefault();
    var formData = $(this).serialize();

    $.post(
      ajaxurl,
      {
        action: "ptg_admin_update_member",
        user_id: $("#ptg-edit-user-id").val(),
        member_grade: $("#ptg-edit-grade").val(),
        billing_status: $("#ptg-edit-status").val(),
        billing_expiry_date: $("#ptg-edit-expiry").val(),
      },
      function (response) {
        if (response.success) {
          alert("저장되었습니다.");
          $("#ptg-grade-modal").hide();
          location.reload(); // 목록 갱신
        } else {
          alert(response.data);
        }
      }
    );
  });

  // 결제 이력 모달 열기
  $(".ptg-view-history-btn").on("click", function (e) {
    e.preventDefault();
    var userId = $(this).data("user-id");

    $("#ptg-history-modal h2").text("결제 이력"); // 타이틀 초기화
    $("#ptg-history-content").html("<p>로딩 중...</p>");
    $("#ptg-history-modal").show();

    $.post(
      ajaxurl,
      {
        action: "ptg_admin_get_history",
        user_id: userId,
      },
      function (response) {
        if (response.success) {
          $("#ptg-history-content").html(response.data);
        } else {
          $("#ptg-history-content").html("<p>오류: " + response.data + "</p>");
        }
      }
    );
  });

  // 상태 컬럼 클릭 (일일 사용량 조회)
  $(".ptg-status-cell").on("click", function () {
    var userId = $(this).data("user-id");

    $("#ptg-history-modal h2").text("오늘의 활동량 통계");
    $("#ptg-history-content").html(
      '<p style="padding:20px; text-align:center;">데이터 조회 중...</p>'
    );
    $("#ptg-history-modal").show();

    $.post(
      ajaxurl,
      {
        action: "ptg_admin_get_user_stats",
        user_id: userId,
      },
      function (response) {
        if (response.success) {
          var d = response.data;
          var html =
            '<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">';
          html +=
            "<thead><tr><th>항목</th><th>오늘 사용량</th></tr></thead><tbody>";
          html +=
            "<tr><th>과목 학습 (Study)</th><td><strong>" +
            d.study +
            "</strong> 문제</td></tr>";
          html +=
            "<tr><th>퀴즈 풀이 (Quiz)</th><td><strong>" +
            d.quiz +
            "</strong> 문제</td></tr>";
          html +=
            "<tr><th>모의고사 (Mock)</th><td><strong>" +
            d.mock +
            "</strong> 회</td></tr>";
          html +=
            "<tr><th>암기카드 (Flash)</th><td><strong>" +
            d.flash +
            "</strong> 개 (생성/수정)</td></tr>";
          html +=
            '<tr style="border-top: 2px solid #ddd;"><th>마지막 활동 시간</th><td>' +
            d.last_active +
            "</td></tr>";
          html += "</tbody></table>";

          $("#ptg-history-content").html(html);
        } else {
          $("#ptg-history-content").html("<p>오류가 발생했습니다.</p>");
        }
      }
    );
  });

  // 모달 닫기
  $(".ptg-modal-close").on("click", function () {
    $(".ptg-modal").hide();
  });

  // 모달 외부 클릭 시 닫기
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("ptg-modal")) {
      $(".ptg-modal").hide();
    }
  });
});
