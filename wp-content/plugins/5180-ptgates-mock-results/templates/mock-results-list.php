<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$user_id = get_current_user_id();
if ( ! $user_id ) {
    echo '<p>로그인이 필요합니다.</p>';
    return;
}

// ensure API
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-api.php';

// Stats for Header
$stats = \PTG\Mock\Results\API::get_dashboard_stats($user_id);

$table_history = 'ptgates_mock_history';
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $table_history WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
    $user_id
) );
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="ptg-mock-results-container">
    <!-- Header Area -->
    <div class="ptg-timeline-header">
        <div class="header-left">
            <h2>모의고사 대시보드</h2>
            <p>나의 학습 성취도와 성장 추이를 분석합니다.</p>
        </div>
    </div>

    <!-- Dashboard Section (Top) -->
    <div class="ptg-dashboard-grid">
        <!-- KPI Cards -->
        <div class="ptg-kpi-row">
            <div class="ptg-kpi-card">
                <div class="kpi-icon">📝</div>
                <div class="kpi-info">
                    <span class="kpi-label">총 응시 횟수</span>
                    <span class="kpi-value"><?php echo $stats['kpi']['total']; ?>회</span>
                </div>
            </div>
            <div class="ptg-kpi-card">
                <div class="kpi-icon">📊</div>
                <div class="kpi-info">
                    <span class="kpi-label">평균 점수</span>
                    <span class="kpi-value"><?php echo $stats['kpi']['avg']; ?>점</span>
                </div>
            </div>
            <div class="ptg-kpi-card">
                <div class="kpi-icon">🏆</div>
                <div class="kpi-info">
                    <span class="kpi-label">최고 점수</span>
                    <span class="kpi-value"><?php echo $stats['kpi']['best']; ?>점</span>
                </div>
            </div>
            <div class="ptg-kpi-card">
                <div class="kpi-icon">✅</div>
                <div class="kpi-info">
                    <span class="kpi-label">합격률</span>
                    <span class="kpi-value"><?php echo $stats['kpi']['pass_rate']; ?>%</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <?php if (!empty($stats['trend'])): ?>
        <div class="ptg-charts-row">
            <div class="ptg-chart-card main-chart">
                <h3>성적 변화 추이</h3>
                <div class="chart-wrapper">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="ptg-chart-card side-chart">
                <h3>과목별 강/약점 분석</h3>
                <div class="chart-wrapper">
                    <canvas id="radarChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="ptg-timeline-divider">
        <h3>최근 응시 타임라인</h3>
    </div>

    <!-- Timeline Container -->
    <div class="ptg-timeline">
        <?php if ( empty( $results ) ): ?>
            <div class="ptg-no-results">
                <p>아직 응시한 내역이 없습니다. 첫 시험을 시작해보세요!</p>
                <a href="/ptg_quiz/?mode=mock" class="ptgates-btn ptgates-btn-primary">모의고사 응시하기</a>
            </div>
        <?php else: ?>
            <?php foreach ( $results as $index => $row ): ?>
                <?php 
                    $date = date( 'Y.m.d', strtotime( $row->created_at ) );
                    $course_txt = (strpos($row->course_no, '교시') !== false) ? $row->course_no : $row->course_no . '교시';
                    $session_label = ($row->session_code - 1000) . '회차 · ' . $course_txt; 
                    $score = number_format( $row->total_score, 1 );
                    $is_pass = $row->is_pass;
                    $status_class = $is_pass ? 'status-pass' : 'status-fail';
                    // Pulse animation for high scores or pass
                    $pulse_class = ($row->total_score >= 60) ? 'score-pulse' : '';
                ?>
                <div class="ptg-timeline-item">
                    <!-- Marker -->
                    <div class="ptg-timeline-marker <?php echo $status_class; ?>"></div>
                    
                    <!-- Date Label (Desktop Side) -->
                    <div class="ptg-timeline-date"><?php echo $date; ?></div>

                    <!-- Card Body -->
                    <div class="ptg-timeline-card">
                        <div class="card-main" onclick="toggleTimelineDetail(<?php echo $row->history_id; ?>, this)">
                            <div class="card-info">
                                <span class="session-tag"><?php echo $session_label; ?></span>
                                <span class="result-tag <?php echo $status_class; ?>">
                                    <?php echo $is_pass ? 'PASS' : 'FAIL'; ?>
                                </span>
                            </div>
                            <div class="card-score">
                                <span class="val <?php echo $pulse_class; ?>"><?php echo $score; ?></span>
                                <span class="unit">점</span>
                            </div>
                            <div class="card-action">
                                <button class="btn-delete" onclick="event.stopPropagation(); confirmDeleteMock(<?php echo $row->history_id; ?>, this)">
                                    삭제
                                </button>
                                <button class="btn-expand">
                                    <span class="icon-arrow">Details ▾</span>
                                </button>
                            </div>
                        </div>

                        <!-- Insight Panel (Hidden) -->
                        <div id="detail-panel-<?php echo $row->history_id; ?>" class="ptg-insight-panel" style="display:none;">
                            <div class="panel-content" id="detail-content-<?php echo $row->history_id; ?>">
                                <div class="ptg-loading">
                                    <div class="spinner"></div>
                                    <span>결과 분석 중...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
var ptg_ajax_url = '<?php echo admin_url('admin-ajax.php'); ?>';

function toggleTimelineDetail(id, cardEl) {
    // Avoid triggering if clicking inner buttons if necessary, though card click is good
    var panel = document.getElementById('detail-panel-' + id);
    var contentDiv = document.getElementById('detail-content-' + id);
    var btnText = cardEl.querySelector('.icon-arrow');
    
    var isActive = panel.style.display !== 'none';

    if (!isActive) {
        panel.style.display = 'block';
        if(btnText) btnText.innerHTML = 'Close ▴';
        
        // [NEW] Scroll to top (User Request)
        var itemContainer = cardEl.closest('.ptg-timeline-item');
        if (itemContainer) {
            setTimeout(function() {
                // Adjust scroll position with offset for sticky header if needed
                // Using scrollIntoView usually works well for general cases
                var y = itemContainer.getBoundingClientRect().top + window.pageYOffset - 80;
                window.scrollTo({top: y, behavior: 'smooth'});
            }, 100);
        }
        
        // Load Data
        if (!contentDiv.dataset.loaded) {
            var formData = new FormData();
            formData.append('action', 'ptg_mock_result_summary');
            formData.append('result_id', id);
            
            fetch(ptg_ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                contentDiv.innerHTML = html;
                contentDiv.dataset.loaded = "true";
            })
            .catch(error => {
                contentDiv.innerHTML = '<p class="error">로드 실패</p>';
            });
        }
    } else {
        panel.style.display = 'none';
        if(btnText) btnText.innerHTML = 'Details ▾';
    }
}

function confirmDeleteMock(id, btn) {
    if(!confirm('해당 모의시험 이력을 완전히 삭제하시겠습니까? (점수 및 결과 기록이 모두 제거됩니다.)')) return;
    
    // UI Loading state
    var originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = '...';

    var formData = new FormData();
    formData.append('action', 'ptg_mock_delete_result');
    formData.append('result_id', id);

    fetch(ptg_ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Remove item with fade out
            var item = btn.closest('.ptg-timeline-item');
            if(item) {
                item.style.transition = 'opacity 0.3s, transform 0.3s';
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                setTimeout(() => item.remove(), 300);
            }
        } else {
            alert('삭제 실패: ' + (data.data || '오류 발생'));
            btn.disabled = false;
            btn.innerText = originalText;
        }
    })
    .catch(err => {
        alert('서버 오류 발생');
        btn.disabled = false;
        btn.innerText = originalText;
    });
}

// Initialize Charts for Dashboard (Top Section)
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($stats['trend'])): ?>
    
    const trendData = <?php echo json_encode($stats['trend']); ?>;
    const radarData = <?php echo json_encode($stats['radar']); ?>;

    // Trend Chart
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: trendData.map(d => (d.session_code - 1000) + '회차'),
            datasets: [{
                label: '총점',
                data: trendData.map(d => d.total_score),
                borderColor: '#2563eb',
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(37, 99, 235, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }, // Simple look
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });

    // Radar Chart
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: Object.keys(radarData),
            datasets: [{
                label: '평균 점수',
                data: Object.values(radarData),
                backgroundColor: 'rgba(34, 197, 94, 0.2)',
                borderColor: '#22c55e',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: { suggestedMin: 0, suggestedMax: 100, ticks: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
    <?php endif; ?>
});
</script>

<style>
/* Container & Reset */
.ptg-mock-results-container {
    max-width: 900px;
    margin: 40px auto;
    font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif;
    color: #333;
    padding: 0 20px;
}

/* Header */
.ptg-timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 30px;
    padding-bottom: 10px;
}
.ptg-timeline-header h2 { font-size: 26px; font-weight: 800; color: #111; margin: 0 0 5px; }
.ptg-timeline-header p { margin: 0; color: #888; font-size: 14px; }

/* Dashboard Grid (Top) */
.ptg-dashboard-grid { display: flex; flex-direction: column; gap: 20px; margin-bottom: 50px; }

.ptg-kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.ptg-kpi-card {
    background: #fff; border-radius: 12px; padding: 20px;
    display: flex; align-items: center; gap: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #f1f3f5;
}
.kpi-icon { font-size: 24px; background: #f8f9fa; padding: 10px; border-radius: 10px; }
.kpi-info { display: flex; flex-direction: column; }
.kpi-label { font-size: 13px; color: #888; font-weight: 500; }
.kpi-value { font-size: 20px; font-weight: 800; color: #333; }

.ptg-charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.ptg-chart-card {
    background: #fff; border-radius: 16px; padding: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #f1f3f5;
}
.ptg-chart-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 20px; color: #444; }
.chart-wrapper { position: relative; height: 250px; width: 100%; }

/* Timeline Divider */
.ptg-timeline-divider { 
    margin: 40px 0 30px; 
    border-bottom: 2px solid #333; 
    padding-bottom: 10px; 
}
.ptg-timeline-divider h3 { font-size: 20px; font-weight: 700; color: #333; margin: 0; }

/* Timeline Structure */
.ptg-timeline {
    position: relative;
    padding-left: 20px; /* Space for line */
    margin-top: 30px;
}
.ptg-timeline::before {
    content: '';
    position: absolute;
    top: 0; bottom: 0; left: 6px;
    width: 2px;
    background: #e9ecef;
}

.ptg-timeline-item { position: relative; margin-bottom: 30px; padding-left: 30px; }

/* Marker */
.ptg-timeline-marker {
    position: absolute; left: -25px; top: 20px;
    width: 14px; height: 14px; border-radius: 50%;
    background: #fff; border: 3px solid #ced4da; z-index: 2;
}
.ptg-timeline-marker.status-pass { border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); }
.ptg-timeline-marker.status-fail { border-color: #ef4444; }

/* Date Label */
.ptg-timeline-date {
    position: absolute; left: -120px; top: 22px; width: 80px; text-align: right;
    font-size: 13px; color: #999; font-weight: 500;
}

/* Card Slab */
.ptg-timeline-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #f1f3f5;
    overflow: hidden; cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ptg-timeline-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }

/* Card Content */
.card-main { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; }
.card-info { display: flex; flex-direction: column; gap: 6px; }
.session-tag { font-size: 18px; font-weight: 700; color: #333; }
.result-tag { display: inline-block; font-size: 12px; font-weight: 700; text-transform: uppercase; }
.result-tag.status-pass { color: #22c55e; }
.result-tag.status-fail { color: #ef4444; }

.card-score { text-align: right; margin-left: auto; margin-right: 30px; }
.card-score .val { font-size: 32px; font-weight: 800; color: #333; display: inline-block; }
.card-score .unit { font-size: 14px; color: #bbb; }

.score-pulse { animation: pulse-green 2s infinite; color: #2563eb !important; }
@keyframes pulse-green {
    0% { transform: scale(1); text-shadow: 0 0 0 rgba(37, 99, 235, 0); }
    50% { transform: scale(1.05); text-shadow: 0 0 10px rgba(37, 99, 235, 0.2); }
    100% { transform: scale(1); text-shadow: 0 0 0 rgba(37, 99, 235, 0); }
}

.btn-expand { background: transparent; border: none; font-size: 13px; color: #adb5bd; font-weight: 600; }

/* Insight Panel */
.ptg-insight-panel { background: #f8f9fa; border-top: 1px solid #eee; }
.panel-content { padding: 30px; }
.ptg-loading .spinner { width: 30px; height: 30px; border: 3px solid #ddd; border-top-color: #333; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 10px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Responsive */
@media (max-width: 900px) {
    .ptg-charts-row { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .ptg-kpi-row { grid-template-columns: repeat(2, 1fr); }
    .ptg-timeline { padding-left: 20px; }
    .ptg-timeline::before { left: 8px; }
    .ptg-timeline-item { padding-left: 25px; margin-bottom: 20px; }
    .ptg-timeline-marker { left: -19px; }
    .ptg-timeline-date { position: static; text-align: left; margin-bottom: 5px; width: auto; }
    .ptg-timeline-header { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .ptg-kpi-row { grid-template-columns: 1fr; }
}

.btn-delete {
    background: transparent; border: none; font-size: 13px; color: #adb5bd; font-weight: 500;
    margin-right: 10px; cursor: pointer; transition: color 0.2s;
}
.btn-delete:hover { color: #ef4444; }
</style>
