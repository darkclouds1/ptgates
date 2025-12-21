<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($history) || empty($subjects)) {
    echo '<p>데이터가 없습니다.</p>';
    return;
}

// 1. Calculate Insights (Best/Worst by Subject Name for the banner)
$subjects_sorted = $subjects;
usort($subjects_sorted, function($a, $b) {
    return $b->score - $a->score; 
});
$best_subject = $subjects_sorted[0];
$worst_subject = end($subjects_sorted);

// Pass/Fail
$pass_status = $history->is_pass ? '합격' : '불합격';
$pass_class = $history->is_pass ? 'pass' : 'fail';

// Header Text
$session_num = $history->session_code - 1000;
$period_text = (strpos($history->course_no, '교시') !== false) ? $history->course_no : $history->course_no . '교시';
?>

<div class="ptg-insight-view">
    
    <!-- 1. Header (Updated Format) -->
    <div class="ptg-summary-compact">
        <div class="info-group">
            <div class="session-title">
                <?php echo esc_html($session_num); ?>회차 <span class="divider">·</span> <?php echo esc_html($period_text); ?>
            </div>
            <span class="ptg-badge <?php echo $pass_class; ?>"><?php echo $pass_status; ?></span>
        </div>
        <div class="score-group">
            <span class="score-val"><?php echo esc_html($history->total_score); ?></span>
            <span class="score-unit">점</span>
        </div>
    </div>

    <!-- 2. Insight Banner -->
    <div class="ptg-insight-banner">
        <div class="insight-text">
            <?php if ($history->is_pass): ?>
                <h3>🎉 축하합니다! 합격 기준을 달성하셨습니다.</h3>
                <p><strong><?php echo esc_html($best_subject->subject_name); ?></strong> 과목이 점수를 견인했습니다.</p>
            <?php else: ?>
                <h3>💪 조금 더 힘내세요!</h3>
                <p><strong><?php echo esc_html($worst_subject->subject_name); ?></strong> 과목을 보완하면 충분히 합격할 수 있습니다.</p>
            <?php endif; ?>
        </div>
        <div class="insight-action">
            <?php 
            // Banner Link: 1200-quiz based Retry (Session Scope)
            // Target: /ptg_quiz/?mode=mock_retry&mock_exam_id=...&course_no=...&wrong_only=1
            $banner_url = sprintf('/ptg_quiz/?mode=mock_retry&mock_exam_id=%d&course_no=%s&wrong_only=1&random=0', 
                $history->history_id, urlencode($history->course_no));
            ?>
            <a href="<?php echo $banner_url; ?>" class="btn-review" target="_blank">오답 다시 풀기</a>
        </div>
    </div>

    <?php
    // Grouping Logic
    $grouped = [];
    foreach ($subjects as $subj) {
        $cat = $subj->category;
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [
                'name' => $cat,
                'total_q' => 0,
                'total_c' => 0,
                'scores' => 0, 
                'is_fail' => false,
                'items' => [] // Store sub-subjects
            ];
        }
        $grouped[$cat]['total_q'] += $subj->question_count;
        $grouped[$cat]['total_c'] += $subj->correct_count;
        $grouped[$cat]['scores'] += $subj->score; 
        if ($subj->is_fail) $grouped[$cat]['is_fail'] = true; 
        
        $grouped[$cat]['items'][] = $subj;
    }
    ?>

    <!-- 3. Category Grid (Major Group Cards) -->
    <div class="ptg-subject-grid">
        <?php foreach ($grouped as $cat_data): 
            $percent = ($cat_data['total_q'] > 0) ? ($cat_data['total_c'] / $cat_data['total_q']) * 100 : 0;
            $wrong_count = $cat_data['total_q'] - $cat_data['total_c'];
            $bar_color = ($percent >= 60) ? '#22c55e' : '#f59e0b';
            if ($percent < 40) $bar_color = '#ef4444'; 
        ?>
        <div class="subject-card category-mode">
            <!-- Category Header -->
            <div class="subj-header">
                <span class="subj-name category-title"><?php echo esc_html($cat_data['name']); ?></span>
                <div class="header-right">
                    <?php if ($cat_data['is_fail']): ?>
                        <span class="badge-fail">과락</span>
                    <?php endif; ?>
                    <span class="subj-score"><?php echo number_format($cat_data['scores'], 1); ?>점</span>
                </div>
            </div>
            
            <!-- Category Progress -->
            <div class="subj-bar-bg">
                <div class="subj-bar-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $bar_color; ?>;"></div>
            </div>

            <!-- Category Stats Summary -->
            <div class="subj-stats-row">
                <div class="stat-item">
                    <span class="lbl">전체</span> 
                    <span class="val"><?php echo $cat_data['total_q']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="lbl">정답</span> 
                    <span class="val"><?php echo $cat_data['total_c']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="lbl">오답</span> 
                    <span class="val fail-txt"><?php echo $wrong_count; ?></span>
                </div>
            </div>

            <!-- Sub-Category List (New) -->
            <div class="sub-subject-list">
                <?php foreach ($cat_data['items'] as $sub): 
                    $sub_wrong = $sub->question_count - $sub->correct_count;
                    // Generate Review Token for this specific subject
                    $review_token = \PTG\Mock\Results\API::generate_review_token($history->history_id, $sub->subject_name, $history->user_id);
                    $review_link = sprintf('/mock-review/?token=%s&subject=%s&mock_exam_id=%d&wrong_only=1&random=0&infinite_scroll=1',
                        $review_token, urlencode($sub->subject_name), $history->history_id);
                ?>
                <div class="sub-subject-row">
                    <div class="sub-name"><?php echo esc_html($sub->subject_name); ?></div>
                    <div class="sub-stats">
                        <span class="sub-correct">정답 <?php echo $sub->correct_count; ?>/<?php echo $sub->question_count; ?></span>
                        <?php if ($sub_wrong > 0): ?>
                            <a href="<?php echo $review_link; ?>" class="sub-wrong-chip" target="_blank">오답 <?php echo $sub_wrong; ?></a>
                        <?php else: ?>
                            <span class="sub-perfect">완벽!</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

</div>

<style>
/* Insight View Styles */
.ptg-insight-view { padding: 10px; }

/* Header Compact */
.ptg-summary-compact {
    display: flex; justify-content: space-between; align-items: center;
    background: #fff; padding: 20px; border-radius: 12px;
    margin-bottom: 20px; border: 1px solid #eee;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.session-title { font-size: 18px; font-weight: 700; color: #111; display: flex; align-items: center; gap: 8px; }
.session-title .divider { color: #ddd; }
.score-val { font-size: 28px; font-weight: 800; color: #333; }
.score-unit { font-size: 14px; color: #999; }

/* Banner */
.ptg-insight-banner {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 12px; padding: 20px 25px;
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 25px; border: 1px solid #bae6fd;
}
.insight-text h3 { margin: 0 0 5px; font-size: 18px; color: #0369a1; }
.insight-text p { margin: 0; font-size: 14px; color: #0c4a6e; }

.btn-review {
    background: #0ea5e9; color: #fff; padding: 10px 20px; 
    border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;
    transition: background 0.2s; white-space: nowrap;
}
.btn-review:hover { background: #0284c7; }

/* Category Grid */
.ptg-subject-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px; margin-bottom: 30px;
}
.subject-card {
    background: #fff; border: 1px solid #f1f3f5; padding: 20px;
    border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.subj-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.category-title { font-size: 16px; font-weight: 700; color: #222; }
.header-right { display: flex; align-items: center; gap: 8px; }

.subj-score { font-size: 16px; font-weight: 800; color: #333; }
.badge-fail { 
    background: #fee2e2; color: #ef4444; font-size: 11px; font-weight: 700;
    padding: 2px 6px; border-radius: 4px;
}

.subj-bar-bg {
    width: 100%; height: 8px; background: #f1f3f5; border-radius: 4px; overflow: hidden; margin-bottom: 15px;
}
.subj-bar-fill { height: 100%; border-radius: 4px; }

/* Stats Row */
.subj-stats-row { 
    display: flex; justify-content: space-between; 
    background: #f8f9fa; padding: 10px 15px; border-radius: 8px;
    margin-bottom: 15px;
}
.stat-item { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.stat-item .lbl { font-size: 11px; color: #999; }
.stat-item .val { font-size: 14px; font-weight: 700; color: #555; }
.stat-item .val.fail-txt { color: #ef4444; }

/* Sub-Subject List */
.sub-subject-list {
    border-top: 1px solid #f1f3f5;
    padding-top: 10px;
    display: flex; flex-direction: column; gap: 8px;
}
.sub-subject-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 13px; color: #555;
    padding: 4px 0;
}
.sub-name { font-weight: 600; color: #444; }
.sub-stats { display: flex; align-items: center; gap: 10px; }
.sub-correct { color: #888; font-size: 12px; }
.sub-perfect { color: #22c55e; font-weight: 700; font-size: 12px; }

/* Interactive Wrong Chip */
.sub-wrong-chip {
    background: #fee2e2; color: #ef4444; 
    font-weight: 700; font-size: 11px;
    padding: 3px 8px; border-radius: 12px;
    cursor: pointer; text-decoration: none;
    transition: all 0.2s;
}
.sub-wrong-chip:hover {
    background: #ef4444; color: #fff;
    transform: translateY(-1px);
}

/* Mobile */
@media (max-width: 600px) {
    .ptg-insight-banner { flex-direction: column; align-items: flex-start; gap: 15px; }
    .btn-review { width: 100%; text-align: center; }
    .ptg-subject-grid { grid-template-columns: 1fr; }
    .ptg-summary-compact { flex-direction: column; align-items: flex-start; gap: 10px; }
    .score-group { align-self: flex-end; }
    
    .sub-subject-row { flex-direction: column; align-items: flex-start; gap: 4px; padding: 8px 0; border-bottom: 1px dotted #eee; }
    .sub-stats { width: 100%; justify-content: space-between; }
}
</style>
