<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="ptg-daily-history-app" class="ptg-daily-history-container">
    <div class="ptg-loading">
        <div class="ptg-spinner"></div>
        <span>학습 기록을 불러오는 중입니다...</span>
    </div>
</div>

<style>
    .ptg-daily-history-container {
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

    /* New Header & Container Styles */
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



    /* Extracted CSS */
    .ptg-learning-recent {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .ptg-learning-recent-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        overflow: hidden;
    }

    .ptg-learning-column-head {
        padding: 14px 16px;
        margin: 0;
        border-bottom: 1px solid rgba(0,0,0,0.08);
    }

    .ptg-learning-column-head h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }

    /* 과목 Study 카드 헤더 */
    .ptg-learning-recent-card.ptg-card-study .ptg-learning-column-head {
        background: linear-gradient(180deg, #e0f2fe 0%, #dbeafe 100%);
        border-bottom-color: #bfdbfe;
    }

    /* 학습 Quiz 카드 헤더 */
    .ptg-learning-recent-card.ptg-card-quiz .ptg-learning-column-head {
        background: linear-gradient(180deg, #f3e8ff 0%, #e9d5ff 100%);
        border-bottom-color: #d8b4fe;
    }

    .ptg-learning-recent-card > *:not(.ptg-learning-column-head) {
        padding: 16px;
    }

    .ptg-learning-day {
        border: 1px solid #e4e9f5;
        border-radius: 12px;
        padding: 14px 16px;
        margin: 10px 0;
        background: #fdfdff;
        box-shadow: 0 6px 16px rgba(13, 31, 68, 0.04);
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }

    .ptg-learning-day:hover,
    .ptg-learning-day.is-open {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(130, 151, 202, 0.08);
        border-color: #d1d5db;
        background: #fdfdff !important;
    }

    .ptg-learning-date-row {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        text-align: left;
    }

    .ptg-learning-date {
        font-weight: 700;
        color: #ffffffff;
        font-size: 0.95rem;
    }

    .ptg-learning-total {
        background: #eff6ff;
        color: #3b82f6;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-left: auto;
        margin-right: 10px;
    }
    
    .ptg-card-quiz .ptg-learning-total {
        background: #f3e8ff;
        color: #9333ea;
    }

    .ptg-learning-toggle {
        font-size: 12px;
        color: #94a3b8;
        transition: transform 0.2s;
    }

    .ptg-learning-day.is-open .ptg-learning-toggle {
        transform: rotate(180deg);
    }

    .ptg-learning-day-content {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
        display: none;
    }

    .ptg-learning-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 0.9rem;
        color: #475569;
    }

    .ptg-learning-subject {
        color: #64748b;
    }

    .ptg-learning-count {
        font-weight: 600;
        color: #0f172a;
    }
    
    .ptg-no-data-sm {
        color: #94a3b8;
        font-size: 0.9rem;
        text-align: center;
        padding: 20px 0;
    }


    @media (max-width: 480px) {
        .ptg-dash-learning,
        .ptg-learning-header,
        .ptg-learning-recent-card > *:not(.ptg-learning-column-head),
        .ptg-learning-day {
            padding: 10px !important;
        }
        .ptg-learning-header h2 {
            font-size: 16px; /* Slightly smaller font for header */
        }
    }
</style>

<script>
// Check if document is already loaded (for AJAX) or wait for DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDailyHistory);
} else {
    initDailyHistory();
}

function initDailyHistory() {
    const container = document.getElementById('ptg-daily-history-app');

    // Fetch Data
    fetch('/wp-json/ptgates/v1/daily-history', {
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
        if (!records || (!records.study.length && !records.quiz.length)) {
            container.innerHTML = '<div class="ptg-no-data">학습 기록이 없습니다.</div>';
            return;
        }

        const studyEntries = Array.isArray(records.study) ? records.study : [];
        const quizEntries = Array.isArray(records.quiz) ? records.quiz : [];

        const contentHtml = `
            <div class="ptg-learning-recent">
                ${buildRecentCard('과목 Study', studyEntries)}
                ${buildRecentCard('학습 Quiz', quizEntries)}
            </div>
        `;

        container.innerHTML = `
            <div class="ptg-dash-learning">
                <div class="ptg-study-header ptg-learning-header">
                    <h2>🗝️ 일자별 Study/Quiz 학습 이력</h2>
                </div>
                ${contentHtml}
            </div>
        `;
        
        bindEvents();
    }

    function bindEvents() {
        // Toggle Buttons
        container.querySelectorAll('.ptg-learning-date-row').forEach(btn => {
            btn.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const isHidden = content.style.display === 'none';
                content.style.display = isHidden ? 'block' : 'none';
                this.setAttribute('aria-expanded', isHidden);
                this.closest('.ptg-learning-day').classList.toggle('is-open', isHidden);
            });
        });


    }

    function buildRecentCard(title, entries = []) {
        const cardClass = title === '과목 Study' ? 'ptg-card-study' : 'ptg-card-quiz';
        let html = `
            <div class="ptg-learning-recent-card ${cardClass}">
                <div class="ptg-learning-column-head"><h4>${escapeHtml(title)}</h4></div>
                <div class="ptg-card-body">
        `;

        if (!entries.length) {
            html += '<p class="ptg-no-data-sm">기록이 없습니다.</p></div></div>';
            return html;
        }

        entries.slice(0, 7).forEach((day, index) => {
            const isOpen = index === 0;
            const total = getDayTotal(day.subjects);
            html += `
                <div class="ptg-learning-day ${isOpen ? 'is-open' : ''}">
                    <button class="ptg-learning-date-row" type="button" aria-expanded="${isOpen}">
                        <span class="ptg-learning-date">${escapeHtml(day.date)}</span>
                        <span class="ptg-learning-total">${escapeHtml(String(total))}회</span>
                        <span class="ptg-learning-toggle" aria-hidden="true">⌃</span>
                    </button>
                    <div class="ptg-learning-day-content" style="display:${isOpen ? 'block' : 'none'};">
                        ${buildDayLines(day.subjects)}
                    </div>
                </div>
            `;
        });

        html += '</div></div>';
        return html;
    }

    function getDayTotal(subjects = []) {
        if (!Array.isArray(subjects) || !subjects.length) {
            return 0;
        }
        return subjects.reduce((sum, subject) => {
            const subjectTotal = subject && typeof subject.total === 'number' ? subject.total : 0;
            return sum + subjectTotal;
        }, 0);
    }

    function buildDayLines(subjects = []) {
        if (!Array.isArray(subjects) || subjects.length === 0) {
            return '<p class="ptg-no-data-sm">세부 데이터가 아직 없습니다.</p>';
        }

        return subjects.map(subject => {
            const subName = subject.name || subject.subject || '미분류';
            const total = subject.total || 0;
            
            // 하위 과목이 있으면 그것도 표시 (옵션)
            let detail = '';
            if (subject.subsubjects && subject.subsubjects.length > 0) {
                 const subDetails = subject.subsubjects.map(s => `${s.name} (${s.count})`).join(', ');
                 detail = `<div style="font-size:0.8em; color:#94a3b8; margin-top:4px;">${escapeHtml(subDetails)}</div>`;
            }

            return `
                <div class="ptg-learning-line">
                    <div class="ptg-learning-subject">
                        ${escapeHtml(subName)}
                        ${detail}
                    </div>
                    <span class="ptg-learning-count">${total}</span>
                </div>
            `;
        }).join('');
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
