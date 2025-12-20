<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$user_id = get_current_user_id();
if ( ! $user_id ) {
    echo '<p>로그인이 필요합니다.</p>';
    return;
}

$table_history = 'ptgates_mock_history';
$results = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $table_history WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
    $user_id
) );

$dashboard_url = get_permalink(); 
// Assumes shortcode is on a page. To link to detail, we append ?result_id=X
?>

<div class="ptg-mock-results-container">
    <h2>모의고사 응시 결과</h2>
    
    <?php if ( empty( $results ) ): ?>
        <div class="ptg-no-results">
            <p>응시 내역이 없습니다. 모의고사를 시작해보세요!</p>
            <a href="/ptg_quiz/?mode=mock" class="ptgates-btn ptgates-btn-primary">모의시험 보러가기</a>
        </div>
    <?php else: ?>
        <table class="ptg-results-table">
            <thead>
                <tr>
                    <th>응시일</th>
                    <th>회차</th>
                    <th>점수</th>
                    <th>결과</th>
                    <th>상세</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $results as $row ): ?>
                    <?php 
                        $date = date( 'Y-m-d H:i', strtotime( $row->created_at ) );
                        $session_label = ($row->session_code - 1000) . '회차'; 
                        $score = number_format( $row->total_score, 1 );
                        $pass_label = $row->is_pass ? '<span class="ptg-badge pass">합격</span>' : '<span class="ptg-badge fail">불합격</span>';
                        $detail_url = add_query_arg( 'result_id', $row->history_id, $dashboard_url );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $date ); ?></td>
                        <td><?php echo esc_html( $session_label ); ?></td>
                        <td><?php echo esc_html( $score ); ?></td>
                        <td><?php echo $pass_label; ?></td>
                        <td>
                            <a href="<?php echo esc_url( $detail_url ); ?>" class="ptgates-btn ptgates-btn-sm">상세보기</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.ptg-mock-results-container {
    max-width: 800px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.ptg-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.ptg-results-table th, .ptg-results-table td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #e0e0e0;
}
.ptg-results-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}
.ptg-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}
.ptg-badge.pass {
    background-color: #e6f4ea;
    color: #1e8e3e;
}
.ptg-badge.fail {
    background-color: #fce8e6;
    color: #d93025;
}
.ptgates-btn {
    display: inline-block;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}
.ptgates-btn-primary {
    background-color: #1a73e8;
    color: #fff;
}
.ptgates-btn-sm {
    padding: 4px 8px;
    background-color: #f1f3f4;
    color: #3c4043;
    font-size: 12px;
}
.ptg-no-results {
    text-align: center;
    padding: 40px;
    background: #f9f9f9;
    border-radius: 8px;
}
</style>
