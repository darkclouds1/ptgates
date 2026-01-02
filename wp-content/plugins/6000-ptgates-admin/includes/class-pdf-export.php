<?php
/**
 * PTGates Admin PDF Export - Final National Exam Fidelity v2 (Fixed Image Overlap)
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

class PDF_Export {

    public static function generate( $year, $session, $course, $type = 'question' ) {
        global $wpdb;

        if ( ! class_exists( 'TCPDF' ) ) {
            wp_die( 'TCPDF class not loaded.' );
        }

        $questions = self::get_questions( $year, $session, $course );
        if ( empty( $questions ) ) {
            wp_die( 'No questions found.' );
        }

        $pdf = new PTG_TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );

        $title = sprintf( '%d년도 %s회 %s', $year, $session ?: '전체', $course );
        $pdf->custom_header_info = sprintf( '제%s회 물리치료사 모의시험 (%s)', $session ?: '??', $course );
        
        $pdf->SetTitle( $title );
        $pdf->setCellHeightRatio(1.45); 
        $pdf->SetFont( 'cid0kr', '', 10 ); 

        $pdf->SetMargins( 15, 35, 15 );
        $pdf->SetHeaderMargin( 5 );
        $pdf->SetFooterMargin( 10 );
        $pdf->SetAutoPageBreak( FALSE ); 

        $pdf->AddPage();
        $pdf->setEqualColumns(2, 85);

        $Y_LIMIT = 265; 
        $TOP_MARGIN = 35;

        $pdf->SetFont('cid0kr', 'B', 10);
        $pdf->setCellPaddings(1, 1, 1, 1);
        $pdf->MultiCell(0, 0, "각 문제에서 가장 적합한 답을 하나만 고르시오.", 1, 'C', 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('cid0kr', '', 10);
        
        $cat_tracker = '';
        $sub_tracker = '';

        foreach ( $questions as $q ) {
            $pdf->startTransaction();
            $sim_cat = $cat_tracker;
            $sim_sub = $sub_tracker;
            $y_start = $pdf->GetY();
            self::render_question_native($pdf, $q, $sim_cat, $sim_sub, $type);
            $y_end = $pdf->GetY();
            $height = $y_end - $y_start;
            $pdf->rollbackTransaction(true);
            
            if ( ($pdf->GetY() + $height) > $Y_LIMIT ) {
                if ( $pdf->getColumn() == 0 ) {
                    $pdf->selectColumn(1);
                    $pdf->SetY($TOP_MARGIN);
                } else {
                    $pdf->AddPage();
                    $pdf->setEqualColumns(2, 85);
                    $pdf->selectColumn(0);
                    $pdf->SetY($TOP_MARGIN);
                }
            }
            
            self::render_question_native($pdf, $q, $cat_tracker, $sub_tracker, $type);
            $pdf->Ln(10);
        }

        $suffix = ( $type === 'question' ) ? '문제' : '해설';
        $filename = sprintf( 'PTGates_%s_%s_%s.pdf', $session ?: '전체', $course, $suffix );
        $pdf->Output( $filename, 'D' );
        exit;
    }

    private static function clean_text( $text ) {
        return preg_replace( '/\s?\([a-zA-Z\s,]+\)/u', '', $text );
    }

    private static function render_question_native($pdf, $q, &$cat_tracker, &$sub_tracker, $type) {
        if ($q->subject_category !== $cat_tracker) {
            $pdf->SetFont('cid0kr', 'B', 9);
            $pdf->setCellPaddings(1, 0.1, 1, 0.1); 
            $clean_cat = self::clean_text($q->subject_category);
            $pdf->MultiCell(0, 0, $clean_cat, 1, 'C', 0, 1);
            $pdf->Ln(2); 
            
            $pdf->setCellPaddings(0,0,0,0);
            $pdf->SetFont('cid0kr', '', 10);
            $cat_tracker = $q->subject_category;
            $sub_tracker = ''; 
        }

        if (!empty($q->subject) && $q->subject !== $sub_tracker) {
            $pdf->SetFont('cid0kr', 'B', 9);
            $clean_sub = self::clean_text($q->subject);
            $pdf->MultiCell(0, 0, '[' . $clean_sub . ']', 0, 'L', 0, 1);
            $pdf->Ln(1.5);
            $pdf->SetFont('cid0kr', '', 10);
            $sub_tracker = $q->subject;
        }

        $content = self::clean_text($q->content);
        $parts = preg_split('/(?=①)/u', $content, 2);
        $q_txt = trim($parts[0]);
        $choices = isset($parts[1]) ? $parts[1] : '';

        // 1. 문제 번호와 본문 출력
        $full_q_text = $q->question_no . '. ' . $q_txt;
        $pdf->MultiCell(0, 0, $full_q_text, 0, 'L', 0, 1);
        
        // 2. 이미지 처리 (겹침 방지 로직 적용)
        if (!empty($q->question_image)) {
            // 이미지 절대 경로 구성
            $base_path = '/var/www/ptgates/wp-content/uploads/ptgates-questions/';
            $full_image_path = $base_path . $q->exam_year . '/' . $q->exam_session . '/' . $q->question_image;
            
            if ( file_exists($full_image_path) ) {
                $pdf->Ln(2); // 본문과 이미지 사이 간격
                
                // 이미지 크기 계산 (비율 유지하며 너비 80mm에 맞춤)
                list($img_w, $img_h) = getimagesize($full_image_path);
                $render_w = 80; 
                $render_h = ($img_h * $render_w) / $img_w;

                // 높이가 너무 크면 60mm로 제한하고 너비를 역산
                if ($render_h > 60) {
                    $render_h = 60;
                    $render_w = ($img_w * $render_h) / $img_h;
                }

                // 이미지 삽입 (현재 Y좌표에 삽입)
                $current_y = $pdf->GetY();
                $pdf->Image($full_image_path, '', $current_y, $render_w, $render_h, '', '', '', false, 300, 'C', false, false, 0, true);
                
                // 커서를 이미지 하단으로 강제 이동
                $pdf->SetY($current_y + $render_h + 1);

                // 이미지 캡션 (파일명)
                $pdf->SetFont('cid0kr', '', 7);
                $pdf->MultiCell(0, 0, '[그림: ' . $q->question_image . ']', 0, 'C', 0, 1);
                $pdf->SetFont('cid0kr', '', 10); // 폰트 원복
                
                $pdf->Ln(2); // 이미지 블록과 다음 텍스트(선택지) 사이 간격
            }
        }
        
        // 3. 선택지 출력
        if (!empty($choices)) {
            $pdf->Ln(1);
            $pattern = '/([①-⑮])/u';
            $segs = preg_split($pattern, $choices, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            
            for ($i=0; $i<count($segs); $i+=2) {
                $sym = $segs[$i];
                $txt = self::clean_text(isset($segs[$i+1]) ? trim($segs[$i+1]) : '');
                
                $pdf->MultiCell(6, 0, $sym, 0, 'L', 0, 0);
                $pdf->MultiCell(0, 0, $txt, 0, 'L', 0, 1);
            }
        }
    }

    private static function get_questions( $year, $session, $course ) {
        global $wpdb;
        // 이미지 경로 구성을 위해 exam_year, exam_session 추가 SELECT [cite: 535, 536]
        $sql = "SELECT q.*, c.subject, c.subject_category, c.question_no, c.exam_year, c.exam_session 
                FROM ptgates_questions q 
                JOIN ptgates_categories c ON q.question_id = c.question_id 
                WHERE c.exam_year = %d AND c.exam_course = %s";
        $params = [ $year, $course ];
        if ( $session ) { $sql .= " AND c.exam_session = %d"; $params[] = $session; }
        $sql .= " ORDER BY c.question_no ASC";
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }
}

class PTG_TCPDF extends \TCPDF {
    public $custom_header_info = '';

    public function Header() {
        $this->SetFont( 'cid0kr', '', 8 );
        $this->Cell( 0, 5, '물리치료사 학습 플랫폼 · https://ptgates.com © 2025 피티링크. All rights reserved.', 0, 1, 'C' );
        
        $this->SetFont( 'cid0kr', 'B', 14 );
        $this->Cell( 0, 10, $this->custom_header_info, 0, 1, 'C' );
        $this->Line(15, 28, 195, 28, array('width' => 0.3));
        $this->Line(105, 32, 105, 272, array('width' => 0.1, 'color' => array(180, 180, 180))); 
    }

    public function Footer() {
        $this->SetY( -15 ); 
        $this->SetFont( 'cid0kr', 'B', 10 );
        $this->Cell(0, 8, $this->getAliasNumPage(), 1, 0, 'C');
    }
}