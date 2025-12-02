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
            // Loading state is already in HTML

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
                    self.container.html('<div class="ptg-error" style="text-align:center; padding:40px;">' + msg + '</div>');
                }
            });
        },

        renderDashboard: function (data) {
            let html = '<div class="ptg-dashboard">';

            // 1. Top Stats Row
            html += '<div class="ptg-stats-grid">';
            html += this.buildStatCard('최근 정답률', data.recent_accuracy + '%', '최근 50문제 기준');
            html += this.buildStatCard('예상 점수', data.predicted_score + '점', '현재 실력 기반');
            html += this.buildStatCard('학습 연속일', data.learning_streak + '일', '꾸준함이 합격의 열쇠!');
            html += '</div>';

            // 2. Middle Row: Charts (Radar + Velocity)
            html += '<div class="ptg-charts-row">';
            
            // Radar Chart Container
            html += '<div class="ptg-section"><h3>과목별 밸런스</h3>';
            html += '<div class="ptg-chart-container"><canvas id="ptgRadarChart"></canvas></div>';
            html += '</div>'; 

            // Learning Velocity
            html += '<div class="ptg-section"><h3>학습 속도 (최근 7일)</h3>';
            html += '<div class="ptg-chart-container"><canvas id="ptgVelocityChart"></canvas></div>';
            html += '</div>';

            html += '</div>'; // End Charts Row

            // 3. Bottom Row: Detailed Subject Analysis (Grouped)
            html += '<div class="ptg-section"><h3>상세 과목 정답률 분석</h3>';
            html += '<div class="ptg-legend">범례: (정답수 / 풀이수 / 전체문항수)</div>';
            
            if (data.all_subject_stats && data.all_subject_stats.length > 0) {
                // Group by parent subject
                const grouped = {};
                data.all_subject_stats.forEach(item => {
                    if (!grouped[item.parent_subject]) {
                        grouped[item.parent_subject] = [];
                    }
                    grouped[item.parent_subject].push(item);
                });

                // Define styles for direct injection
                const styles = {
                    foundation: { border: '#10b981', bg: '#ecfdf5', text: '#065f46', borderBottom: '#d1fae5' },
                    assessment: { border: '#3b82f6', bg: '#eff6ff', text: '#1e40af', borderBottom: '#dbeafe' },
                    intervention: { border: '#8b5cf6', bg: '#f5f3ff', text: '#5b21b6', borderBottom: '#ede9fe' },
                    law: { border: '#f59e0b', bg: '#fffbeb', text: '#92400e', borderBottom: '#fde68a' }
                };

                html += '<div class="ptg-subject-grid">';
                
                for (const [parent, items] of Object.entries(grouped)) {
                    let s = null;
                    const p = parent.trim();
                    
                    if (p.includes('기초')) s = styles.foundation;
                    else if (p.includes('진단')) s = styles.assessment;
                    else if (p.includes('중재')) s = styles.intervention;
                    else if (p.includes('법규')) s = styles.law;
                    
                    const cardStyle = s ? `border: 2px solid ${s.border} !important;` : '';
                    const headerStyle = s ? `background: ${s.bg} !important; color: ${s.text} !important; border-bottom-color: ${s.borderBottom} !important;` : '';

                    html += `<div class="ptg-subject-card" style="${cardStyle}">`;
                    html += `<div class="ptg-group-title" style="${headerStyle}">${parent}</div>`;
                    html += '<ul class="ptg-weak-list ptg-compact-list">';
                    
                    items.forEach(function (item) {
                        const colorClass = Analytics.getAccuracyColorClass(item.accuracy);
                        
                        html += '<li>';
                        html += '<span class="ptg-subject-name" title="' + item.subject + '">' + item.subject + '</span>';
                        html += '<div class="ptg-progress-bar"><div class="ptg-progress-fill ' + colorClass + '" style="width:' + item.accuracy + '%"></div></div>';
                        html += '<div class="ptg-accuracy-group">';
                        html += '<div class="ptg-accuracy ' + colorClass + '-text">' + item.accuracy + '%</div>';
                        html += '<span class="ptg-counts">(' + item.correct + ' / ' + item.attempted + ' / ' + item.total_available + ')</span>';
                        html += '</div>';
                        html += '</li>';
                    });
                    
                    html += '</ul>';
                    html += '</div>'; // End Card
                }
                
                html += '</div>'; // End Grid
            } else {
                html += '<p style="text-align:center; color:#999; padding:20px;">분석할 데이터가 없습니다.</p>';
            }
            html += '</div>'; // End Subject Analysis

            html += '</div>'; // End Dashboard

            this.container.html(html);

            // Inject Responsive Styles
            const style = document.createElement('style');
            style.innerHTML = `
                @media (max-width: 480px) {
                    .ptg-section,
                    .ptg-stat-card,
                    .ptg-subject-card,
                    .ptg-group-title,
                    .ptg-compact-list {
                        padding: 10px !important;
                    }
                    .ptg-compact-list {
                        padding-top: 5px !important; /* Slightly less top padding for list */
                    }
                    .ptg-progress-bar {
                        display: none !important; /* Hide progress bar on mobile */
                    }
                    .ptg-subject-name {
                        width: auto !important;
                        flex: 1;
                        white-space: normal !important; /* Allow wrapping if needed */
                        margin-right: 10px;
                    }
                    .ptg-accuracy-group {
                        text-align: right;
                        min-width: auto !important;
                        flex-shrink: 0;
                    }
                    .ptg-accuracy {
                        font-size: 0.95rem;
                    }
                    .ptg-counts {
                        font-size: 0.7rem;
                    }
                }
            `;
            document.head.appendChild(style);

            // Initialize Charts
            this.initRadarChart(data.subject_radar);
            this.initVelocityChart(data.learning_velocity);
        },

        getAccuracyColorClass: function(accuracy) {
            if (accuracy >= 80) return 'ptg-excellent';
            if (accuracy >= 40) return 'ptg-average';
            return 'ptg-poor';
        },

        buildStatCard: function(title, value, sub) {
            return `
                <div class="ptg-stat-card">
                    <h3>${title}</h3>
                    <div class="ptg-stat-value">${value}</div>
                    <div class="ptg-stat-sub">${sub}</div>
                </div>
            `;
        },

        initRadarChart: function(data) {
            if (!data || data.length === 0) return;

            const ctx = document.getElementById('ptgRadarChart').getContext('2d');
            const labels = data.map(item => item.subject);
            const values = data.map(item => item.accuracy);

            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '정답률 (%)',
                        data: values,
                        backgroundColor: 'rgba(74, 144, 226, 0.2)',
                        borderColor: 'rgba(74, 144, 226, 1)',
                        pointBackgroundColor: 'rgba(74, 144, 226, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: { display: true },
                            suggestedMin: 0,
                            suggestedMax: 100
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        },

        initVelocityChart: function(data) {
            if (!data || data.length === 0) return;

            const ctx = document.getElementById('ptgVelocityChart').getContext('2d');
            const labels = data.map(item => item.date.substring(5)); // MM-DD
            const values = data.map(item => item.count);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '풀이 문항 수',
                        data: values,
                        borderColor: '#50E3C2',
                        backgroundColor: 'rgba(80, 227, 194, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [2, 4] }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    };

    $(document).ready(function () {
        Analytics.init();
    });

})(jQuery);
