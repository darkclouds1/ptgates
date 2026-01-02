<?php
/**
 * PTGates Admin PDF Export - Explanation Version
 * Fixed: Overflow issues solved by enabling native flow for long explanations.
 */

namespace PTG\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
if ( file_exists( $tcpdf_path ) ) {
    require_once $tcpdf_path;
} else {
    wp_die( "TCPDF Library not found" );
}

class PDF_Export_Expl {

    public static function generate( $year, $session, $course ) {
        global $wpdb;

        if ( ! class_exists( 'TCPDF' ) ) {
            wp_die( 'TCPDF class not loaded.' );
        }

        $questions = self::get_questions( $year, $session, $course );
        if ( empty( $questions ) ) {
            wp_die( 'No questions found.' );
        }

        $pdf = new PTG_TCPDF_Expl( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );

        $title = sprintf( '%d년도 %s회 %s (해설지)', $year, $session ?: '전체', $course );
        $pdf->custom_header_info = sprintf( '제%s회 물리치료사 모의시험 (%s) - 해설지', $session ?: '??', $course );
        
        $pdf->SetTitle( $title );
        
        // 줄간 간격 설정
        $pdf->setCellHeightRatio(1.45); 
        $pdf->SetFont( 'cid0kr', '', 10 ); 

        $pdf->SetMargins( 15, 35, 15 );
        $pdf->SetHeaderMargin( 5 );
        $pdf->SetFooterMargin( 10 );
        
        // [수정] 자동 페이지 분할 활성화 (하단 여백 20mm 확보)
        $pdf->SetAutoPageBreak( TRUE, 20 ); 

        $pdf->AddPage();
        
        // 2단 컬럼 설정
        $pdf->setEqualColumns(2, 85);

        // Intro Box
        $pdf->SetFont('cid0kr', 'B', 10);
        $pdf->setCellPaddings(1, 1, 1, 1);
        $pdf->MultiCell(0, 0, "각 문제에서 가장 적합한 답을 하나만 고르시오.", 1, 'C', 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('cid0kr', '', 10);
        
        $cat_tracker = '';
        $sub_tracker = '';

        foreach ( $questions as $q ) {
            // [수정] 긴 해설이 컬럼을 넘어갈 수 있도록 Transaction 로직 제거
            // 대신 헤더가 바뀔 때 최소한의 공간(약 20mm)이 있는지 확인하여 컬럼 전환
            if ($pdf->GetY() > 250) {
                $pdf->selectColumn(($pdf->getColumn() + 1) % 2);
                if ($pdf->getColumn() == 0) { $pdf->AddPage(); }
            }

            self::render_question_native($pdf, $q, $cat_tracker, $sub_tracker);
            
            // 문항 간 간격
            $pdf->Ln(5);
        }

        $filename = sprintf( 'PTGates_%s_%s_해설.pdf', $session ?: '전체', $course );
        $pdf->Output( $filename, 'D' );
        exit;
    }

    private static function render_question_native($pdf, $q, &$cat_tracker, &$sub_tracker) {
        // 1. 카테고리 헤더
        if ($q->subject_category !== $cat_tracker) {
            $pdf->SetFont('cid0kr', 'B', 9);
            $pdf->setCellPaddings(1, 0.1, 1, 0.1); 
            $pdf->MultiCell(0, 0, $q->subject_category, 1, 'C', 0, 1);
            $pdf->Ln(2); 
            
            $pdf->setCellPaddings(0,0,0,0);
            $pdf->SetFont('cid0kr', '', 10);
            $cat_tracker = $q->subject_category;
            $sub_tracker = ''; 
        }

        // 2. 세부 과목 헤더
        if (!empty($q->subject) && $q->subject !== $sub_tracker) {
            $pdf->SetFont('cid0kr', 'B', 9);
            $pdf->MultiCell(0, 0, '[' . $q->subject . ']', 0, 'L', 0, 1);
            $pdf->Ln(1.5);
            $pdf->SetFont('cid0kr', '', 10);
            $sub_tracker = $q->subject;
        }

        // 3. 정답 라인 (Bold)
        $pdf->SetFont('cid0kr', 'B', 10);
        $answer_text = "문제번호 {$q->question_no}: 정답: {$q->answer} 번";
        $pdf->MultiCell(0, 0, $answer_text, 0, 'L', 0, 1);
        
        // 4. 해설 라인 (Normal)
        if (!empty($q->explanation)) {
             $pdf->Ln(1);
             $pdf->SetFont('cid0kr', '', 10);
             // [중요] MultiCell의 마지막 인자를 1로 두어 내용이 길면 자동으로 컬럼/페이지를 넘기게 함
             $pdf->MultiCell(0, 0, "해설: " . $q->explanation, 0, 'L', 0, 1);
        }
    }

    private static function get_questions( $year, $session, $course ) {
        global $wpdb;
        $sql = "SELECT q.*, c.subject, c.subject_category, c.question_no FROM ptgates_questions q 
                JOIN ptgates_categories c ON q.question_id = c.question_id 
                WHERE c.exam_year = %d AND c.exam_course = %s";
        $params = [ $year, $course ];
        if ( $session ) { $sql .= " AND c.exam_session = %d"; $params[] = $session; }
        $sql .= " ORDER BY c.question_no ASC";
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }
}

class PTG_TCPDF_Expl extends \TCPDF {
    public $custom_header_info = '';

    public function Header() {
        $this->SetFont( 'cid0kr', '', 8 );
        $this->Cell( 0, 5, '물리치료사 학습 플랫폼 · https://ptgates.com © 2025 피티링크. All rights reserved.', 0, 1, 'C' );
        
        $this->SetFont( 'cid0kr', 'B', 14 );
        $this->Cell( 0, 10, $this->custom_header_info, 0, 1, 'C' );
        $this->Line(15, 28, 195, 28, array('width' => 0.3));
        
        // 중앙 구분선 (페이지 높이에 맞춰 자동 조절되도록 설정)
        $this->Line(105, 32, 105, 272, array('width' => 0.1, 'color' => array(180, 180, 180))); 
    }

    public function Footer() {
        $this->SetY( -15 ); 
        $this->SetFont( 'cid0kr', 'B', 10 );
        $this->Cell(0, 8, $this->getAliasNumPage(), 1, 0, 'C');
    }
}