<?php
/**
 * PTGates 공통 퀴즈 UI 컴포넌트
 * 
 * 문제 표시 및 선택지 렌더링을 위한 공통 템플릿
 * 
 * @param array $args {
 *     @type string $question_text 문제 지문
 *     @type array  $options 선택지 배열
 *     @type string $question_number 문제 번호 (선택사항)
 *     @type string $container_id 컨테이너 ID (기본값: 'ptg-quiz-ui-container')
 *     @type string $question_id 문제 텍스트 요소 ID (기본값: 'ptg-quiz-ui-question-text')
 *     @type string $options_id 선택지 컨테이너 ID (기본값: 'ptg-quiz-ui-options-container')
 *     @type string $answer_name 라디오 버튼 name 속성 (기본값: 'ptg-quiz-ui-answer')
 * }
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 기본값 설정
$defaults = array(
    'question_text' => '',
    'options' => array(),
    'question_number' => '',
    'container_id' => 'ptg-quiz-ui-container',
    'question_id' => 'ptg-quiz-ui-question-text',
    'options_id' => 'ptg-quiz-ui-options-container',
    'answer_name' => 'ptg-quiz-ui-answer',
    'question_class' => 'ptg-quiz-ui-question-section',
    'question_text_class' => 'ptg-quiz-ui-question-text',
    'options_class' => 'ptg-quiz-ui-options-container',
);

$args = wp_parse_args($args, $defaults);
extract($args);
?>

<div id="<?php echo esc_attr($container_id); ?>" class="ptg-quiz-ui-wrapper">
    <!-- 문제 표시 영역 -->
    <div class="<?php echo esc_attr($question_class); ?>">
        <div class="ptg-quiz-ui-question-header">
            <h3 id="<?php echo esc_attr($question_id); ?>" class="<?php echo esc_attr($question_text_class); ?>">
                <?php if ($question_number): ?>
                    <?php echo esc_html($question_number); ?>. 
                <?php endif; ?>
                <?php echo esc_html($question_text); ?>
            </h3>
        </div>
        
        <!-- 선택지 영역 -->
        <div id="<?php echo esc_attr($options_id); ?>" class="<?php echo esc_attr($options_class); ?>">
            <!-- 선택지가 JavaScript로 동적으로 삽입됨 -->
        </div>
    </div>
</div>

