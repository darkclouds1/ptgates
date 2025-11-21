(function ($) {
    'use strict';

    const Dashboard = {
        init: function () {
            this.$container = $('#ptg-dashboard-app');
            if (this.$container.length === 0) return;

            this.fetchSummary();
            this.bindEvents();
        },

        bindEvents: function () {
            // Quick Actions
            this.$container.on('click', '[data-action]', function (e) {
                e.preventDefault();
                const action = $(this).data('action');
                const url = $(this).data('url');
                if (url) {
                    window.location.href = url;
                }
            });
        },

        fetchSummary: function () {
            const self = this;
            const restUrl = window.ptg_dashboard_vars ? window.ptg_dashboard_vars.rest_url : '/wp-json/ptg-dash/v1/';
            const nonce = window.ptg_dashboard_vars ? window.ptg_dashboard_vars.nonce : '';

            $.ajax({
                url: restUrl + 'summary',
                method: 'GET',
                dataType: 'json',
                beforeSend: function (xhr) {
                    if (nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', nonce);
                    }
                },
                success: function (data) {
                    if (data && typeof data === 'object') {
                        self.render(data);
                    } else {
                        console.error('Invalid response data:', data);
                        self.$container.html('<p>데이터 형식이 올바르지 않습니다.</p>');
                    }
                },
                error: function (xhr, status, error) {
                    // 상세 에러 로깅
                    console.error('Dashboard fetch error details:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'No response text',
                        error: error,
                        url: restUrl + 'summary'
                    });

                    let errorMessage = '데이터를 불러오는 중 오류가 발생했습니다.';

                    // JSON 응답 파싱 시도
                    try {
                        if (xhr.responseText) {
                            const errorData = JSON.parse(xhr.responseText);
                            if (errorData) {
                                if (errorData.message) {
                                    errorMessage = errorData.message;
                                } else if (errorData.code) {
                                    errorMessage = '오류 코드: ' + errorData.code;
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing error response:', e);
                        // HTML 응답일 경우 (예: PHP Fatal Error)
                        if (xhr.responseText && xhr.responseText.includes('<')) {
                            errorMessage += ' (서버 오류)';
                        }
                    }

                    // 상태 코드별 메시지
                    if (xhr.status === 401 || xhr.status === 403) {
                        errorMessage = '로그인이 필요하거나 권한이 없습니다.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'API 엔드포인트를 찾을 수 없습니다.';
                    } else if (xhr.status === 500) {
                        errorMessage = '서버 내부 오류가 발생했습니다.';
                    }

                    self.$container.html(`
                        <div class="ptg-error-message">
                            <p>⚠️ ${errorMessage}</p>
                            <small>상태: ${xhr.status} ${xhr.statusText}</small>
                        </div>
                    `);
                }
            });
        },

        render: function (data) {
            const { user_name, premium, today_reviews, progress, recent_activity } = data;

            // 1. Welcome Section
            const welcomeHtml = `
                <div class="ptg-dash-welcome">
                    <h2>안녕하세요, <strong>${this.escapeHtml(user_name)}</strong>님! 👋</h2>
                    <div class="ptg-dash-premium-badge ${premium.status === 'active' ? 'is-active' : 'is-free'}">
                        ${premium.status === 'active' ? 'Premium 멤버십' : 'Free 멤버십'}
                        ${premium.expiry ? `<small>(${premium.expiry} 만료)</small>` : ''}
                    </div>
                </div>
            `;

            // 2. Stats Cards
            const statsHtml = `
                <div class="ptg-dash-stats">
                    <div class="ptg-dash-card ptg-card-review">
                        <div class="ptg-card-icon">🔁</div>
                        <div class="ptg-card-content">
                            <h3>오늘의 복습</h3>
                            <p class="ptg-stat-value">${today_reviews} <span class="ptg-stat-unit">문제</span></p>
                            <button class="ptg-btn ptg-btn-sm ptg-btn-primary" data-action="go-review" data-url="/reviewer">복습 시작하기</button>
                        </div>
                    </div>
                    <div class="ptg-dash-card ptg-card-progress">
                        <div class="ptg-card-icon">📈</div>
                        <div class="ptg-card-content">
                            <h3>전체 진도율</h3>
                            <p class="ptg-stat-value">${progress.percent}%</p>
                            <div class="ptg-progress-bar">
                                <div class="ptg-progress-fill" style="width: ${progress.percent}%"></div>
                            </div>
                            <p class="ptg-stat-desc">${progress.solved} / ${progress.total} 문제</p>
                        </div>
                    </div>
                </div>
            `;

            // 3. Quick Actions
            const actionsHtml = `
                <div class="ptg-dash-actions">
                    <h3>빠른 이동</h3>
                    <div class="ptg-action-grid">
                        <button class="ptg-action-btn" data-url="/study">
                            <span class="icon">📚</span>
                            <span class="label">학습하기</span>
                        </button>
                        <button class="ptg-action-btn" data-url="/selftest">
                            <span class="icon">📝</span>
                            <span class="label">모의고사</span>
                        </button>
                        <button class="ptg-action-btn" data-url="/mynote">
                            <span class="icon">📓</span>
                            <span class="label">마이노트</span>
                        </button>
                    </div>
                </div>
            `;

            // 4. Recent Activity
            let activityHtml = '<div class="ptg-dash-recent"><h3>최근 학습 기록</h3>';
            if (recent_activity && recent_activity.length > 0) {
                activityHtml += '<ul class="ptg-activity-list">';
                recent_activity.forEach(item => {
                    const statusClass = item.is_correct ? 'is-correct' : 'is-wrong';
                    const statusText = item.is_correct ? '정답' : '오답';
                    activityHtml += `
                        <li class="ptg-activity-item ${statusClass}">
                            <span class="ptg-activity-status">${statusText}</span>
                            <span class="ptg-activity-title">${this.escapeHtml(item.question_summary)}</span>
                            <span class="ptg-activity-date">${item.date.substring(0, 10)}</span>
                        </li>
                    `;
                });
                activityHtml += '</ul>';
            } else {
                activityHtml += '<p class="ptg-no-data">아직 학습 기록이 없습니다.</p>';
            }
            activityHtml += '</div>';

            this.$container.html(welcomeHtml + statsHtml + actionsHtml + activityHtml);
        },

        escapeHtml: function (text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    $(document).ready(function () {
        Dashboard.init();
    });

})(jQuery);
