(function ($) {
  "use strict";

  const Reviewer = {
    init: function () {
      this.container = $("#ptg-reviewer-app");
      if (!this.container.length) return;

      this.config = window.ptgReviewer || {};
      this.mode = this.container.data("mode") || "today";
      this.dashboardUrl = this.config.dashboardUrl || "/";

      this.loadReviews();
    },

    loadReviews: function () {
      const self = this;

      $.ajax({
        url: self.config.restUrl + "today",
        method: "GET",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", self.config.nonce);
        },
        success: function (response) {
          self.renderList(response);
        },
        error: function (err) {
          self.container.html(
            '<div class="ptg-error">복습 목록을 불러오는데 실패했습니다.</div>'
          );
          console.error(err);
        },
      });
    },

    renderList: function (questions) {
      const self = this;

      // Header HTML
      let html = `
                <div class="ptg-reviewer-header">
                    <h1>오늘의 복습</h1>
                    <div class="ptg-reviewer-header-right">
                        <a href="${self.escapeHtml(
                          self.dashboardUrl
                        )}" class="ptg-reviewer-dashboard-link">학습현황</a>
                    </div>
                </div>
            `;

      if (!questions || questions.length === 0) {
        html += '<div class="ptg-empty">오늘 복습할 내용이 없습니다. 🎉</div>';
        this.container.html(html);
        return;
      }

      html += '<div class="ptg-review-list">';
      html += "<h3>오늘의 복습 (" + questions.length + ")</h3>";
      html += "<ul>";

      questions.forEach(function (q) {
        const quizUrl = "/quiz/?id=" + q.question_id;

        html += '<li class="ptg-review-item">';
        html +=
          '<span class="ptg-review-title">[' +
          q.type +
          "] " +
          (q.content
            ? self.escapeHtml(q.content.substring(0, 50)) + "..."
            : "문제 #" + q.question_id) +
          "</span>";
        html +=
          '<a href="' + quizUrl + '" class="ptg-btn ptg-btn-sm">복습하기</a>';
        html += "</li>";
      });

      html += "</ul></div>";
      this.container.html(html);
    },

    escapeHtml: function (text) {
      if (!text) return "";
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  $(document).ready(function () {
    Reviewer.init();
  });
})(jQuery);
