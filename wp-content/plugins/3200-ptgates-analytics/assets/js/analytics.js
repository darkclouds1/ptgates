(function ($) {
    'use strict';

    const Analytics = {
        init: function () {
            this.container = $('#ptg-analytics-app');
            if (!this.container.length) return;

            this.config = window.ptgAnalytics || {};
            this.loadDashboard();
        },

        loadDashboard: function () {
            const self = this;
            this.container.html('<div class="ptg-loading">데이터 분석 중...</div>');

            $.ajax({
                url: self.config.restUrl + 'dashboard',
                method: 'GET',
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
                success: function (data) {
                    self.renderDashboard(data);
                },
                error: function (xhr, status, error) {
                    console.error('Analytics API Error:', status, error, xhr.responseText);
                    let msg = '데이터를 불러오지 못했습니다.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg += '<br>Details: ' + xhr.responseJSON.message;
                    } else {
                        msg += '<br>Status: ' + status + ' ' + error;
                    }
                    self.container.html('<div class="ptg-error">' + msg + '</div>');
                }
            });
        },

        renderDashboard: function (data) {
            let html = '<div class="ptg-dashboard">';

            // Overview Cards
            html += '<div class="ptg-stats-grid">';
            html += '<div class="ptg-stat-card"><h3>최근 정답률</h3><div class="ptg-stat-value">' + data.recent_accuracy + '%</div></div>';
            html += '<div class="ptg-stat-card"><h3>예상 점수</h3><div class="ptg-stat-value">' + data.predicted_score + '점</div></div>';
            html += '</div>';

            // Weak Subjects
            html += '<div class="ptg-section"><h3>취약 단원 (Top 5)</h3>';
            if (data.weak_subjects.length > 0) {
                html += '<ul class="ptg-weak-list">';
                data.weak_subjects.forEach(function (item) {
                    html += '<li>';
                    html += '<span class="ptg-subject-name">' + item.subject + '</span>';
                    html += '<div class="ptg-progress-bar"><div class="ptg-progress-fill" style="width:' + item.accuracy + '%"></div></div>';
                    html += '<span class="ptg-accuracy">' + item.accuracy + '%</span>';
                    html += '</li>';
                });
                html += '</ul>';
            } else {
                html += '<p>아직 분석할 데이터가 충분하지 않습니다.</p>';
            }
            html += '</div>';

            html += '</div>';
            this.container.html(html);
        }
    };

    $(document).ready(function () {
        Analytics.init();
    });

})(jQuery);
