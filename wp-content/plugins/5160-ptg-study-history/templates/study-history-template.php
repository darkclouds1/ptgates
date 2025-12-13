<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="ptg-study-history-app" class="ptg-study-history-container">
    <div class="ptg-loading">
        <div class="ptg-spinner"></div>
        <span>과목별 학습 기록을 불러오는 중입니다...</span>
    </div>
</div>

<style>
    .ptg-study-history-container {
        max-width: 1000px;
        margin: 20px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .ptg-loading {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }
    
    .ptg-spinner {
        width: 30px;
        height: 30px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .ptg-error, .ptg-no-data {
        text-align: center;
        padding: 40px;
        color: #64748b;
        background: #f8fafc;
        border-radius: 8px;
    }

    /* Extracted CSS from dashboard-template.php */
    .ptg-dash-learning {
        background: #fff;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 30px;
    }

    .ptg-learning-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
        padding: 12px 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15,23,42,0.06);
    }

    .ptg-learning-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ptg-study-tip-trigger {
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        cursor: pointer;
        text-decoration: none;
        padding: 6px 10px;
        border-radius: 9999px;
        line-height: 1;
        transition: all .18s ease;
    }

    .ptg-study-tip-trigger:hover {
        background: #dbeafe;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .ptg-course-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .ptg-session-group {
        grid-column: 1 / -1;
    }

    .ptg-session-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .ptg-category {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        overflow: hidden;
    }

    .ptg-category:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15,23,42,0.08);
        border-color: #d1d5db;
    }

    .ptg-category-header {
        padding: 14px 16px 8px 16px;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    /* 1100 Study 헤드 색상 매칭 */
    .ptg-dash-learning .ptg-category[data-category-id="ptg-foundation"] .ptg-category-header {
        background: linear-gradient(180deg, #ecfeff 0%, #f0fdf4 100%);
        border-bottom-color: #dcfce7;
    }
    .ptg-dash-learning .ptg-category[data-category-id="ptg-assessment"] .ptg-category-header {
        background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%);
        border-bottom-color: #dbeafe;
    }
    .ptg-dash-learning .ptg-category[data-category-id="ptg-intervention"] .ptg-category-header {
        background: linear-gradient(180deg, #f5f3ff 0%, #eef2ff 100%);
        border-bottom-color: #e9d5ff;
    }
    .ptg-dash-learning .ptg-category[data-category-id="ptg-medlaw"] .ptg-category-header {
        background: linear-gradient(180deg, #fffbeb 0%, #fef2f2 100%);
        border-bottom-color: #fde68a;
    }

    .ptg-category-title {
        margin: 0 0 6px 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .ptg-category-name {
        flex: 1;
    }

    .ptg-category-total {
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
    }

    .ptg-category-desc {
        margin: 0;
        font-size: 12px;
        color: #64748b;
    }

    .ptg-session-badge {
        display: inline-block;
        padding: 2px 8px;
        font-size: 12px;
        line-height: 1.4;
        color: #0b3d2e;
        background: #d1fae5;
        border: 1px solid #10b981;
        border-radius: 9999px;
        vertical-align: middle;
    }

    .ptg-subject-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 6px;
        margin: 0;
        padding: 12px;
    }

    .ptg-subject-item {
        list-style: none;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 20px;
        background-color: #f1f5f9;
        color: #0f172a;
        font-size: 0.875rem;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        flex-shrink: 0;
    }

    .ptg-subject-item:hover {
        background-color: #e2e8f0;
        color: #0f172a;
    }

    .ptg-subject-item:hover .ptg-subject-counts {
        color:rgb(197, 214, 250) !important;
    }

    @media (max-width: 768px) {
        .ptg-subject-item {
            width: 100%;
            text-align: center;
        }
    }

    .ptg-subject-item .ptg-subject-name {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-right: 12px;
    }

    .ptg-subject-counts {
        font-size: 13px;
        color: #1f3b75;
        white-space: nowrap;
    }

    .ptg-subject-counts strong {
        font-weight: 700;
        color: #111e55;
    }

    .ptg-learning-tip-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }
    
    .ptg-learning-tip-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.5);
    }
    
    .ptg-learning-tip-dialog {
        position: relative;
        background: #fff;
        width: 90%;
        max-width: 500px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        padding: 24px;
        z-index: 10001;
    }
    
    .ptg-learning-tip-close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
    }
    
    .ptg-learning-tip-title {
        margin: 0 0 16px 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
    }
    
    .ptg-learning-tip-content {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #334155;
    }
    
    .ptg-learning-tip-content ul {
        padding-left: 20px;
        margin: 10px 0;
    }
    
    .ptg-learning-tip-content li {
        margin-bottom: 8px;
    }
    @media (max-width: 480px) {
        .ptg-dash-learning,
        .ptg-learning-header,
        .ptg-category-header,
        .ptg-subject-list,
        .ptg-subject-item {
            padding: 10px !important;
        }
        .ptg-learning-header h2 {
            font-size: 16px;
        }
    }
</style>

<script>
// Check if document is already loaded (for AJAX) or wait for DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStudyHistory);
} else {
    initStudyHistory();
}

function initStudyHistory() {
    const container = document.getElementById('ptg-study-history-app');

    // Fetch Data
    fetch('/wp-json/ptgates/v1/study-history', {
        headers: {
            'X-WP-Nonce': '<?php echo wp_create_nonce( "wp_rest" ); ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        renderApp(data);
    })
    .catch(err => {
        console.error(err);
        container.innerHTML = '<div class="ptg-error">데이터를 불러오는 중 오류가 발생했습니다.</div>';
    });

    function renderApp(records) {
        const subjectSessions = Array.isArray(records.subjects) ? records.subjects : [];

        if (!subjectSessions.length) {
            container.innerHTML = '<div class="ptg-no-data">학습 기록이 없습니다.</div>';
            return;
        }

        const subjectHtml = `
            <div class="ptg-course-categories">
                ${subjectSessions.map(session => buildSessionGroup(session)).join('')}
            </div>
        `;

        container.innerHTML = `
            <div class="ptg-dash-learning">
                <div class="ptg-study-header ptg-learning-header">
                    <h2>🗝️ 과목 별 학습 기록</h2>
                    <button type="button" class="ptg-study-tip-trigger" data-learning-tip-open>[학습Tip]</button>
                </div>
                ${subjectHtml}
                ${buildLearningTipModal()}
            </div>
        `;
        
        bindEvents();
    }

    function buildSessionGroup(session) {
        if (!session || !Array.isArray(session.subjects)) {
            return '';
        }

        const subjectsHtml = session.subjects.map(subject => buildSubjectCard(session.session, subject)).join('');

        return `
            <div class="ptg-session-group" data-session="${escapeHtml(session.session)}">
                <div class="ptg-session-grid">
                    ${subjectsHtml}
                </div>
            </div>
        `;
    }

    function buildSubjectCard(session, subject) {
        if (!subject) {
            return '';
        }

        const subList = Array.isArray(subject.subsubjects) ? subject.subsubjects : [];
        const description = subject.description ? `<p class="ptg-category-desc">${escapeHtml(subject.description)}</p>` : '';
        
        // 세부과목별 study와 quiz 총계 계산
        let totalStudy = 0;
        let totalQuiz = 0;
        if (subList.length > 0) {
            subList.forEach(sub => {
                totalStudy += typeof sub.study === 'number' ? sub.study : 0;
                totalQuiz += typeof sub.quiz === 'number' ? sub.quiz : 0;
            });
        }
        
        // 헤더 오른쪽 끝에 총계 표시
        const totalCountsHtml = `<span class="ptg-category-total">Study(${totalStudy}) / Quiz(${totalQuiz})</span>`;
        
        const subsHtml = subList.length
            ? subList.map(sub => {
                const encodedSubjectId = encodeURIComponent(sub.name);
                const studyCount = typeof sub.study === 'number' ? sub.study : 0;
                const quizCount = typeof sub.quiz === 'number' ? sub.quiz : 0;
                return `
                    <li class="ptg-subject-item" data-subject-id="${escapeHtml(encodedSubjectId)}">
                        <span class="ptg-subject-name">${escapeHtml(sub.name)}</span>
                        <span class="ptg-subject-counts">(${studyCount}/${quizCount})</span>
                    </li>
                `;
            }).join('')
            : '<li class="ptg-subject-item is-empty">데이터가 없습니다.</li>';

        return `
            <section class="ptg-category" data-category-id="${escapeHtml(subject.id)}">
                <header class="ptg-category-header">
                    <h4 class="ptg-category-title">
                        <span class="ptg-session-badge">${escapeHtml(session)}교시</span>
                        <span class="ptg-category-name">${escapeHtml(subject.name)}</span>
                        ${totalCountsHtml}
                    </h4>
                    ${description}
                </header>
                <ul class="ptg-subject-list ptg-subject-list--stack">
                    ${subsHtml}
                </ul>
            </section>
        `;
    }

    function buildLearningTipModal() {
        return `
            <div id="ptg-learning-tip-modal" class="ptg-learning-tip-modal" aria-hidden="true">
                <div class="ptg-learning-tip-backdrop" data-learning-tip-close></div>
                <div class="ptg-learning-tip-dialog" role="dialog" aria-modal="true">
                    <button type="button" class="ptg-learning-tip-close" data-learning-tip-close>&times;</button>
                    <h3 class="ptg-learning-tip-title">💡 학습 Tip</h3>
                    <div class="ptg-learning-tip-content">
                        <p><strong>과목별 학습 기록 활용하기</strong></p>
                        <ul>
                            <li><strong>Study</strong>: 해당 과목의 문제를 학습한 횟수입니다.</li>
                            <li><strong>Quiz</strong>: 해당 과목의 퀴즈를 푼 횟수입니다.</li>
                        </ul>
                        <p>각 세부 과목을 클릭하면 해당 과목의 <strong>Study 페이지</strong>로 바로 이동하여 학습을 이어서 할 수 있습니다.</p>
                        <p>꾸준한 학습으로 모든 과목의 그래프를 채워보세요!</p>
                        
                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
                        
                        <p><strong>일자별 Study/Quiz 학습 이력 활용하기</strong></p>
                        <ul>
                            <li><strong>Study</strong>: 날짜별로 학습한 과목과 횟수를 확인할 수 있습니다.</li>
                            <li><strong>Quiz</strong>: 날짜별로 푼 퀴즈와 횟수를 확인할 수 있습니다.</li>
                        </ul>
                        <p>날짜를 클릭하면 세부 학습 내역이 펼쳐집니다.</p>
                </div>
            </div>
        `;
    }

    function bindEvents() {
        // Tip Modal Open
        const openBtn = container.querySelector('[data-learning-tip-open]');
        if (openBtn) {
            openBtn.addEventListener('click', function() {
                const modal = document.getElementById('ptg-learning-tip-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
        }

        // Tip Modal Close
        document.addEventListener('click', function(e) {
            if (e.target.matches('[data-learning-tip-close]')) {
                const modal = document.getElementById('ptg-learning-tip-modal');
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                }
            }
        });

        // Subject Item Click
        container.addEventListener('click', function(e) {
            const item = e.target.closest('.ptg-subject-item');
            if (item) {
                e.preventDefault();
                e.stopPropagation();
                
                const subjectName = item.querySelector('.ptg-subject-name').textContent.trim();
                
                if (subjectName) {
                    // Study 페이지 URL 가져오기 (기본값 설정)
                    let studyBaseUrl = '/ptg_study/';
                    
                    const url = new URL(studyBaseUrl, window.location.origin);
                    url.searchParams.set('subject', subjectName);
                    window.location.href = url.toString();
                }
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}
</script>
