<?php
if (!defined('ABSPATH')) {
    exit;
}

// $result_id is expected from the parent context
if (empty($result_id)) {
    echo '<p>결과 ID가 지정되지 않았습니다.</p>';
    return;
}

// Fetch Data
$data = \PTG\Mock\Results\API::get_mock_result_full_data($result_id);

if (!$data || !$data['history']) {
    echo '<p>결과를 찾을 수 없습니다.</p>';
    return;
}

$history = $data['history'];
$subjects = $data['subjects'];
$questions = $data['questions'];

// Group questions by subject
$grouped_questions = [];
foreach ($questions as $q) {
    $subj = $q['subject'];
    if (!isset($grouped_questions[$subj])) {
        $grouped_questions[$subj] = [];
    }
    $grouped_questions[$subj][] = $q;
}

// Calculate Pass/Fail status string
$pass_status = $history->is_pass ? '합격' : '불합격';
$pass_class = $history->is_pass ? 'pass' : 'fail';
?>

<div class="ptg-mock-result-container">
    <!-- Header -->
    <div class="ptg-result-header ptg-card">
        <div class="header-left">
            <h2><?php echo esc_html($history->session_code); ?>회차 결과 상세</h2>
            <span class="badge <?php echo $pass_class; ?>"><?php echo $pass_status; ?></span>
            <span class="score">총점: <?php echo esc_html($history->total_score); ?>점</span>
        </div>
        <div class="header-right">
            <a href="<?php echo esc_url( remove_query_arg( 'result_id' ) ); ?>" class="ptg-btn ptg-btn-outline">목록보기</a>
        </div>
    </div>

    <!-- Summary (Subject Scores) -->
    <div class="ptg-result-summary ptg-card">
        <h3>과목별 점수</h3>
        <div class="subject-scores-grid">
            <?php foreach ($subjects as $subj): ?>
                <div class="subject-score-item <?php echo ($subj->is_fail) ? 'fail' : ''; ?>">
                    <span class="subj-name"><?php echo esc_html($subj->subject_name); ?></span>
                    <span class="subj-score"><?php echo esc_html($subj->score); ?>점</span>
                    <?php if ($subj->is_fail): ?>
                        <span class="fail-badge">과락</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Question List -->
    <div class="ptg-result-questions">
        <?php 
        $global_idx = 1;
        foreach ($grouped_questions as $subject => $qs): 
        ?>
            <h3 class="subject-heading"><?php echo esc_html($subject); ?></h3>
            <div class="question-list">
                <?php foreach ($qs as $q): 
                    $is_correct = $q['is_correct'];
                    $status_class = $is_correct ? 'correct' : 'incorrect';
                ?>
                    <div class="question-list-item">
                        <div class="q-header">
                            <span class="q-num">No. <?php echo $global_idx; ?></span>
                            <span class="q-status <?php echo $status_class; ?>"><?php echo $is_correct ? '정답' : '오답'; ?></span>
                            <span class="q-preview"><?php echo mb_strimwidth(strip_tags($q['question']), 0, 60, '...'); ?></span>
                            
                            <button class="ptg-btn ptg-btn-sm toggle-study-btn" onclick="toggleStudy(<?php echo $q['id']; ?>)">
                                학습하기 <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        
                        <!-- Accordion Content (Initial Hidden) -->
                        <div id="study-block-<?php echo $q['id']; ?>" class="study-block" style="display:none;">
                            <div class="study-card ptg-card">
                                <div class="q-content">
                                    <div class="q-text">
                                        <?php echo wp_kses_post($q['question']); ?>
                                    </div>
                                    <div class="q-choices">
                                        <?php for($i=1; $i<=5; $i++): 
                                            $choice_text = $q["choice_$i"];
                                            $user_checked = ($q['user_answer'] == $i);
                                            $is_ans = ($q['answer'] == $i);
                                            
                                            $choice_class = '';
                                            if ($user_checked) $choice_class .= ' user-selected';
                                            if ($is_ans) $choice_class .= ' correct-answer'; // Will be revealed later
                                        ?>
                                            <div class="choice-item <?php echo $choice_class; ?>" data-is-answer="<?php echo $is_ans ? 1 : 0; ?>">
                                                <span class="choice-num">①②③④⑤</span>
                                                <span class="choice-text"><?php echo esc_html($choice_text); ?></span>
                                                <?php if($user_checked): ?><span class="mark-user">(내답)</span><?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="answer-section-toggle">
                                    <button class="ptg-btn ptg-btn-primary full-width" onclick="toggleAnswer(<?php echo $q['id']; ?>)">
                                        정답 및 해설 보기
                                    </button>
                                </div>

                                <div id="answer-block-<?php echo $q['id']; ?>" class="answer-block" style="display:none;">
                                    <div class="correct-answer-display">
                                        <strong>정답: <?php echo $q['answer']; ?>번</strong>
                                    </div>
                                    <div class="explanation-display">
                                        <strong>해설:</strong>
                                        <div class="explanation-content">
                                            <?php echo wp_kses_post($q['explanation']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    $global_idx++;
                endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.ptg-mock-result-container { max-width: 800px; margin: 0 auto; padding: 20px 0; font-family: 'Noto Sans KR', sans-serif; }
.ptg-card { background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

/* Header */
.ptg-result-header { display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #007bff; }
.header-left h2 { margin: 0 0 10px 0; font-size: 1.5em; }
.badge { padding: 5px 10px; border-radius: 4px; color: #fff; font-weight: bold; margin-right: 10px; }
.badge.pass { background-color: #28a745; }
.badge.fail { background-color: #dc3545; }
.score { font-size: 1.2em; font-weight: bold; color: #333; }

/* Summary */
.subject-scores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
.subject-score-item { padding: 10px; background: #f8f9fa; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; }
.subject-score-item.fail { border-color: #dc3545; background-color: #fff5f5; }
.fail-badge { color: #dc3545; font-size: 0.8em; font-weight: bold; }

/* List */
.subject-heading { margin-top: 30px; margin-bottom: 15px; font-size: 1.2em; border-bottom: 2px solid #333; padding-bottom: 10px; }
.question-list-item { border-bottom: 1px solid #eee; padding: 15px 0; }
.q-header { display: flex; align-items: center; gap: 15px; }
.q-num { font-weight: bold; width: 60px; }
.q-status { padding: 2px 8px; border-radius: 12px; font-size: 0.8em; color: #fff; }
.q-status.correct { background: #28a745; }
.q-status.incorrect { background: #dc3545; }
.q-preview { flex: 1; color: #666; font-size: 0.9em; }

/* Study Card */
.study-block { margin-top: 15px; }
.study-card { border: 1px solid #007bff; background: #fdfdfd; padding: 20px; }
.q-text { font-size: 1.1em; font-weight: bold; margin-bottom: 20px; }
.choice-item { padding: 8px 12px; margin-bottom: 5px; border-radius: 4px; cursor: default; }
.choice-item.user-selected { background-color: #e2e6ea; border: 1px solid #ced4da; }
.reveal-answer .choice-item.correct-answer { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; font-weight: bold; }
/* Hide correct answer initially */

.answer-section-toggle { margin-top: 20px; text-align: center; }
.answer-block { margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ccc; background: #f0f7ff; padding: 15px; border-radius: 6px; }
.correct-answer-display { color: #0056b3; font-size: 1.1em; margin-bottom: 10px; }

/* Buttons */
.ptg-btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; transition: background 0.2s; }
.ptg-btn-outline { border: 1px solid #ccc; background: #fff; color: #333; }
.ptg-btn-primary { background: #007bff; color: #fff; }
.ptg-btn-sm { padding: 5px 10px; font-size: 12px; }
.full-width { width: 100%; display: block; }
</style>

<script>
function toggleStudy(id) {
    var block = document.getElementById('study-block-' + id);
    if (block.style.display === 'none') {
        block.style.display = 'block';
    } else {
        block.style.display = 'none';
        // Reset answer block
        document.getElementById('answer-block-' + id).style.display = 'none';
    }
}

function toggleAnswer(id) {
    var block = document.getElementById('answer-block-' + id);
    var studyCard = block.closest('.study-card');
    
    if (block.style.display === 'none') {
        block.style.display = 'block';
        if(studyCard) studyCard.classList.add('reveal-answer');
    } else {
        block.style.display = 'none';
        if(studyCard) studyCard.classList.remove('reveal-answer');
    }
}

</script>
