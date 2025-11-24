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
            this.$container.on('click', '[data-action], [data-url]', function (e) {
                e.preventDefault();
                const action = $(this).data('action');
                const url = $(this).data('url');
                if (url) {
                    window.location.href = url;
                }
            });

            // Learning Day 카드 선택 효과
            this.$container.on('click', '.ptg-learning-day', function (e) {
                e.stopPropagation();
                const $day = $(this);
                // 같은 카드 내의 다른 day는 선택 해제
                $day.siblings('.ptg-learning-day').removeClass('is-active');
                // 현재 카드 토글
                $day.toggleClass('is-active');
            });

            // 과목별 학습 기록 - 세부과목 클릭 시 Study 페이지로 이동
            this.$container.on('click', '.ptg-dash-learning .ptg-subject-item', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(this);
                // 세부과목명을 직접 텍스트에서 가져오기 (가장 안전한 방법)
                const subjectName = $item.find('.ptg-subject-name').text().trim();
                if (subjectName) {
                    // Study 페이지 URL 가져오기 (PHP에서 전달된 값 사용)
                    let studyBaseUrl = (window.ptg_dashboard_vars && window.ptg_dashboard_vars.study_url) || '';
                    
                    // Study URL이 없으면 fallback으로 /ptg_study/ 사용
                    if (!studyBaseUrl || studyBaseUrl === '#' || studyBaseUrl === '') {
                        studyBaseUrl = '/ptg_study/';
                        console.warn('Dashboard: Study page URL not found, using fallback /ptg_study/. Please ensure a page with [ptg_study] shortcode exists.');
                    }
                    
                    // 1100 Study 플러그인과 동일한 방식으로 URL 파라미터 추가
                    // URLSearchParams를 사용하여 쿼리 파라미터 구성
                    const url = new URL(studyBaseUrl, window.location.origin);
                    url.searchParams.set('subject', subjectName); // encodeURIComponent는 URLSearchParams가 자동 처리
                    const finalUrl = url.toString();
                    
                    // 디버깅용 로그 (개발 환경에서만)
                    if (window.console && window.console.log) {
                        console.log('Dashboard: Navigating to Study page', {
                            studyBaseUrl: studyBaseUrl,
                            subjectName: subjectName,
                            finalUrl: finalUrl
                        });
                    }
                    
                    window.location.href = finalUrl;
                } else {
                    console.warn('Dashboard: subject name not found on clicked item', $item);
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
            const { user_name, premium, today_reviews, progress, recent_activity, bookmarks, learning_records } = data;
            const learningRecords = learning_records || { study: [], quiz: [] };

            // 1. Welcome Section
            const randomGreeting = this.getRandomGreeting();
            const welcomeHtml = `
                <div class="ptg-dash-welcome">
                    <h2>${this.formatName(user_name)}님, ${randomGreeting}</h2>
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
                            <button class="ptg-btn ptg-btn-sm ptg-btn-primary" data-action="go-review" data-url="/reviewer/">복습 시작하기</button>
                        </div>
                    </div>
                    <div class="ptg-dash-card ptg-card-bookmark">
                        <div class="ptg-card-icon">🔖</div>
                        <div class="ptg-card-content">
                            <h3>북마크</h3>
                            <p class="ptg-stat-value">${this.escapeHtml(bookmarks?.count ?? 0)} <span class="ptg-stat-unit">문제</span></p>
                            <button class="ptg-btn ptg-btn-sm ptg-btn-primary" data-action="go-bookmark" data-url="/bookmark/">북마크 보기</button>
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
                    <div class="ptg-action-grid">
                        <button class="ptg-action-btn" data-url="${(window.ptg_dashboard_vars && window.ptg_dashboard_vars.study_url) || '/ptg_study/'}">
                            <span class="icon">📚</span>
                            <span class="label">학습하기</span>
                        </button>
                        <button class="ptg-action-btn" data-url="/selftest">
                            <span class="icon">📝</span>
                            <span class="label">모의고사</span>
                        </button>
                        <button class="ptg-action-btn" data-url="/mynote/">
                            <span class="icon">🗒️</span>
                            <span class="label">마이노트</span>
                        </button>
                        <button class="ptg-action-btn" data-url="/flashcards/">
                            <span class="icon">🃏</span>
                            <span class="label">암기카드</span>
                        </button>
                    </div> 
                </div>
            `;

            // 3.5. Banner
            const bannerHtml = `
                <div class="ptg-dash-banner">
                    <div class="ptg-banner-icon ptg-banner-brain">🧠</div>
                    <div class="ptg-banner-content">
                        <p class="ptg-banner-quote">"The mind is everything.<br>What you think you become."</p>
                    </div>
                    <div class="ptg-banner-icon ptg-banner-bulb">💡</div>
                </div>
            `;

            // 4. Recent Activity Cards (Study/Quiz)
            const recentActivityHtml = this.renderRecentActivity(learningRecords);

            // 5. Subject Learning Records
            const learningHtml = this.renderLearningRecords(learningRecords);

            // Combine all sections
            this.$container.html(welcomeHtml + statsHtml + bannerHtml + actionsHtml + recentActivityHtml + learningHtml);
            this.bindLearningTipModal();
        },

        renderRecentActivity: function(records) {
            const studyEntries = Array.isArray(records.study) ? records.study : [];
            const quizEntries = Array.isArray(records.quiz) ? records.quiz : [];

            if (!studyEntries.length && !quizEntries.length) {
                return '';
            }

            return `
                <div class="ptg-dash-recent-activity">
                    <div class="ptg-learning-recent">
                        ${this.buildRecentCard('과목 Study', studyEntries)}
                        ${this.buildRecentCard('학습 Quiz', quizEntries)}
                    </div>
                </div>
            `;
        },

        renderLearningRecords: function(records) {
            const subjectSessions = Array.isArray(records.subjects) ? records.subjects : [];

            if (!subjectSessions.length) {
                return '';
            }

            const subjectHtml = `
                <div class="ptg-course-categories">
                    ${subjectSessions.map(session => this.buildSessionGroup(session)).join('')}
                </div>
            `;

            return `
                <div class="ptg-dash-learning">
                    <div class="ptg-study-header ptg-learning-header">
                        <h2>🗝️ 과목 별 학습 기록</h2>
                        <button type="button" class="ptg-study-tip-trigger" data-learning-tip-open>[학습Tip]</button>
                    </div>
                    ${subjectHtml}
                    ${this.buildLearningTipModal()}
                </div>
            `;
        },

        buildSessionGroup: function(session) {
            if (!session || !Array.isArray(session.subjects)) {
                return '';
            }

            const subjectsHtml = session.subjects.map(subject => this.buildSubjectCard(session.session, subject)).join('');

            return `
                <div class="ptg-session-group" data-session="${this.escapeHtml(session.session)}">
                    <div class="ptg-session-grid">
                        ${subjectsHtml}
                    </div>
                </div>
            `;
        },

        buildSubjectCard: function(session, subject) {
            if (!subject) {
                return '';
            }

            const subList = Array.isArray(subject.subsubjects) ? subject.subsubjects : [];
            const description = subject.description ? `<p class="ptg-category-desc">${this.escapeHtml(subject.description)}</p>` : '';
            const subsHtml = subList.length
                ? subList.map(sub => {
                    // 1100 Study 플러그인과 동일하게 rawurlencode (encodeURIComponent)로 인코딩해서 저장
                    const encodedSubjectId = encodeURIComponent(sub.name);
                    return `
                        <li class="ptg-subject-item" data-subject-id="${this.escapeHtml(encodedSubjectId)}">
                            <span class="ptg-subject-name">${this.escapeHtml(sub.name)}</span>
                            <span class="ptg-subject-counts">
                                Study(${this.escapeHtml(typeof sub.study === 'number' ? sub.study : 0)}) /
                                Quiz(${this.escapeHtml(typeof sub.quiz === 'number' ? sub.quiz : 0)})
                            </span>
                        </li>
                    `;
                }).join('')
                : '<li class="ptg-subject-item is-empty">데이터가 없습니다.</li>';

            return `
                <section class="ptg-category" data-category-id="${this.escapeHtml(subject.id)}">
                    <header class="ptg-category-header">
                        <h4 class="ptg-category-title">
                            <span class="ptg-session-badge">${this.escapeHtml(session)}교시</span>
                            ${this.escapeHtml(subject.name)}
                        </h4>
                        ${description}
                    </header>
                    <ul class="ptg-subject-list ptg-subject-list--stack">
                        ${subsHtml}
                    </ul>
                </section>
            `;
        },

        buildRecentCard: function(title, entries = []) {
            const cardClass = title === '과목 Study' ? 'ptg-card-study' : 'ptg-card-quiz';
            let html = `
                <div class="ptg-learning-recent-card ${cardClass}">
                    <div class="ptg-learning-column-head"><h4>${this.escapeHtml(title)}</h4></div>
            `;

            if (!entries.length) {
                html += '<p class="ptg-no-data-sm">기록이 없습니다.</p></div>';
                return html;
            }

            entries.slice(0, 7).forEach(day => {
                const total = this.getDayTotal(day.subjects);
                html += `
                    <div class="ptg-learning-day">
                        <div class="ptg-learning-date-row">
                            <span class="ptg-learning-date">${this.escapeHtml(day.date)}</span>
                            <span class="ptg-learning-total">${this.escapeHtml(total)}회</span>
                        </div>
                        ${this.buildDayLines(day.subjects)}
                    </div>
                `;
            });

            html += '</div>';
            return html;
        },

        getDayTotal: function(subjects = []) {
            if (!Array.isArray(subjects) || !subjects.length) {
                return 0;
            }
            return subjects.reduce((sum, subject) => {
                const subjectTotal = subject && typeof subject.total === 'number' ? subject.total : 0;
                return sum + subjectTotal;
            }, 0);
        },

        buildDayLines: function(subjects = []) {
            if (!Array.isArray(subjects) || subjects.length === 0) {
                return '<p class="ptg-no-data-sm">세부 데이터가 아직 없습니다.</p>';
            }

            const lines = [];
            subjects.forEach(subject => {
                if (!subject || !Array.isArray(subject.subsubjects)) {
                    return;
                }

                subject.subsubjects.forEach(sub => {
                    const count = typeof sub.count === 'number' ? sub.count : 0;
                    if (count <= 0) {
                        return;
                    }
                    lines.push(`
                        <div class="ptg-learning-line">
                            <span class="ptg-learning-line-label">${this.escapeHtml(subject.subject)} &gt; ${this.escapeHtml(sub.name)}</span>
                            <span class="ptg-learning-line-count">${this.escapeHtml(count)}회</span>
                        </div>
                    `);
                });
            });

            return lines.length
                ? `<div class="ptg-learning-lines">${lines.join('')}</div>`
                : '<p class="ptg-no-data-sm">세부 과목 데이터가 없습니다.</p>';
        },

        buildLearningTipModal: function() {
            return `
                <div id="ptg-learning-tip-modal" class="ptg-learning-tip-modal" aria-hidden="true">
                    <div class="ptg-learning-tip-backdrop" data-learning-tip-close></div>
                    <div class="ptg-learning-tip-dialog" role="dialog" aria-modal="true">
                        <div class="ptg-learning-tip-header">
                            <h3>과목 별 학습 기록 안내</h3>
                            <button type="button" class="ptg-learning-tip-close" data-learning-tip-close aria-label="닫기">&times;</button>
                        </div>
                        <div class="ptg-learning-tip-body">
                            <section>
                                <h4>📚 데이터 확인 방법</h4>
                                <ul>
                                    <li>각 세부과목 오른쪽의 <strong>Study</strong>/<strong>Quiz</strong> 수치로 학습 빈도를 확인하세요.</li>
                                    <li>최근 학습 데이터 기준으로 업데이트되며, 학습 시 즉시 집계됩니다.</li>
                                </ul>
                            </section>
                            <section>
                                <h4>🎯 활용 팁</h4>
                                <ul>
                                    <li>Study 대비 Quiz 비율을 보고 복습이 필요한 세부과목을 파악하세요.</li>
                                    <li>어려운 과목은 암기카드나 마이노트로 연결하여 반복 학습하세요.</li>
                                </ul>
                            </section>
                        </div>
                    </div>
                </div>
            `;
        },

        bindLearningTipModal: function() {
            const $modal = this.$container.find('#ptg-learning-tip-modal');
            if (!$modal.length) {
                return;
            }

            this.$container.off('click.dashboardTip', '[data-learning-tip-open]');
            this.$container.on('click.dashboardTip', '[data-learning-tip-open]', function(e) {
                e.preventDefault();
                $modal.addClass('is-open').attr('aria-hidden', 'false');
            });

            this.$container.off('click.dashboardTipClose', '[data-learning-tip-close]');
            this.$container.on('click.dashboardTipClose', '[data-learning-tip-close]', function(e) {
                e.preventDefault();
                $modal.removeClass('is-open').attr('aria-hidden', 'true');
            });
        },

        escapeHtml: function (text) {
            if (text === null || text === undefined) return '';
            const safeText = String(text);
            return safeText
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },

        formatName: function(name) {
            const safe = this.escapeHtml(name || '');
            const parts = safe.trim().split(/\s+/).filter(Boolean);

            if (parts.length === 2) {
                return `${parts[1]} ${parts[0]}`;
            }
            return safe || '학습자';
        },

        getRandomGreeting: function() {
            const greetings = [
                '학습을 이어가볼까요? 👋',
                '오늘도 화이팅입니다! 💪',
                '새로운 도전을 시작해볼까요? 🚀',
                '꾸준한 학습이 답입니다! 📚',
                '한 걸음씩 나아가요! 🎯',
                '오늘의 목표를 달성해봐요! ⭐',
                '지금이 바로 시작할 때입니다! 🌟',
                '작은 실천이 큰 변화를 만듭니다! ✨',
                '오늘도 성장하는 하루가 되길! 🌱',
                '포기하지 않는 당신이 멋져요! 💎',
                '매일 조금씩, 꾸준히! 📖',
                '도전하는 모습이 아름답습니다! 🌈',
                '오늘도 한 문제씩 풀어봐요! 🎓',
                '노력하는 당신을 응원합니다! 👏',
                '작은 시작이 큰 성과를 만듭니다! 🎁'
            ];
            
            const randomIndex = Math.floor(Math.random() * greetings.length);
            return greetings[randomIndex];
        }
    };

    $(document).ready(function () {
        Dashboard.init();
    });

})(jQuery);
