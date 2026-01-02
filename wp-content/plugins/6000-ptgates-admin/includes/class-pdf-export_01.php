<?php
/**
 * PTGates Admin PDF Export
 * 
 * "National Exam" Fidelity - Final Version
 * - Merged Question Number & Text (Zero Gap)
 * - Manual Column/Page Switching (Strict Y-Check)
 * - Regex Data Cleaning
 * - Native TCPDF Rendering
 */

namespace PTG\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
if ( file_exists( $tcpdf_path ) ) {
    require_once $tcpdf_path;
} else {
    wp_die( "TCPDF Library not found at: {$tcpdf_path}" );
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

		$title_prefix = ( $type === 'question' ) ? '문제지' : '해설지';
		$title = sprintf( '%d년도 %s회 %s (%s)', $year, $session ?: '전체', $course, $title_prefix );
        $pdf->custom_header_info = sprintf( '%d년도 제%s회 물리치료사 모의시험 (%s)', $year, $session ?: '??', $course );
		
		$pdf->SetCreator( 'PTGates Admin' );
		$pdf->SetAuthor( 'PTGates' );
		$pdf->SetTitle( $title );
        
        $pdf->setCellHeightRatio(1.1); 
        $pdf->SetFont( 'cid0kr', '', 10 ); 

		$pdf->SetMargins( 15, 35, 15 );
		$pdf->SetHeaderMargin( 5 );
		$pdf->SetFooterMargin( 10 );
		$pdf->SetAutoPageBreak( FALSE ); 
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		$pdf->AddPage();

        // Standard 2-Column Setup for Helper Functions
        $pdf->resetColumns();
        $pdf->setEqualColumns(2, 85);

        // Constants
        $Y_LIMIT = 270;
        $TOP_MARGIN = 35;

        // Intro
        $pdf->SetFont('cid0kr', 'B', 10);
        $pdf->setCellPaddings(1, 1, 1, 1);
        $pdf->MultiCell(0, 0, "각 문제에서 가장 적합한 답을 하나만 고르시오.", 1, 'C', 0, 1);
        $pdf->Ln(3);
        $pdf->SetFont('cid0kr', '', 10);
        
        $cat_tracker = '';
        $sub_tracker = '';

        foreach ( $questions as $q ) {
            // 1. Calculate Exact Height (Transaction)
            $pdf->startTransaction();
            
            // We simulate header changes based on current trackers
            $sim_cat = $cat_tracker;
            $sim_sub = $sub_tracker;
            
            $y_start = $pdf->GetY();
            self::render_question_native($pdf, $q, $sim_cat, $sim_sub, $type);
            $y_end = $pdf->GetY();
            
            // Height of this block
            $height = $y_end - $y_start;
            
            // Clean up simulation
            $pdf->rollbackTransaction(true);
            
            // 2. Manual Page/Column Check
            // Current Y + Height > Limit?
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
            
            // 3. Render Real
            // Note: render function updates trackers by reference
            self::render_question_native($pdf, $q, $cat_tracker, $sub_tracker, $type);
            
            // 4. Spacing (Ln)
            $pdf->Ln(4);
        }

		$filename = sprintf( 'PTGates_%d_%s_%s_%s.pdf', $year, $session ?: 'All', $course, $type );
		$pdf->Output( $filename, 'D' );
		exit;
	}

    // Cleaning Helper
    private static function clean_text( $text ) {
        return preg_replace( '/\s?\([a-zA-Z\s,]+\)/u', '', $text );
    }

    private static function render_question_native($pdf, $q, &$cat_tracker, &$sub_tracker, $type) {
        // Headers Checks
        $do_cat = ($q->subject_category !== $cat_tracker);
        // Subject check: New Subject OR New Category implies New Subject (usually)
        // If Category changes, we should reset subject tracker? Yes.
        // Handled below.
        
        // Minor Header Check
        $do_sub = (!empty($q->subject) && $q->subject !== $sub_tracker);

        // 1. Major Header (Category)
        if ($do_cat) {
            $pdf->SetFont('cid0kr', 'B', 10);
            $pdf->setCellPaddings(1, 0.5, 1, 0.5);
            $clean_cat = self::clean_text($q->subject_category);
            
            // Compact Box
            $pdf->MultiCell(0, 0, $clean_cat, 1, 'C', 0, 1);
            $pdf->Ln(1); // Gap
            
            $pdf->setCellPaddings(0,0,0,0);
            $pdf->SetFont('cid0kr', '', 10);
            
            $cat_tracker = $q->subject_category;
            $sub_tracker = ''; // Reset sub
        }

        // 2. Minor Header (Subject)
        if ($do_sub) {
            $pdf->SetFont('cid0kr', 'B', 10);
            $clean_sub = self::clean_text($q->subject);
            $pdf->MultiCell(0, 0, '[' . $clean_sub . ']', 0, 'L', 0, 1);
            $pdf->Ln(1);
            $pdf->SetFont('cid0kr', '', 10);
            $sub_tracker = $q->subject;
        }

        // 3. Question (Merged # + Text)
        $content = self::clean_text($q->content);
        $parts = preg_split('/(?=①)/u', $content, 2);
        $q_txt = trim($parts[0]);
        $choices = isset($parts[1]) ? $parts[1] : '';

        // Merge: "1. Question Text"
        $full_q_text = $q->question_no . '. ' . $q_txt;

        $pdf->SetFont('cid0kr', '', 10);
        // Hanging indent for Question itself? 
        // User requested "Merge to zero gap". Just MultiCell.
        $pdf->MultiCell(0, 0, $full_q_text, 0, 'L', 0, 1);
        
        // Image
        if (!empty($q->question_image)) {
             $pdf->Ln(1);
             $pdf->writeHTML('<img src="'.$q->question_image.'" width="200">', true, false, true, true, '');
        }
        
        // 4. Choices
        if (!empty($choices)) {
            $pdf->Ln(0.5); // Tiny gap
            $pattern = '/([①-⑮])/u';
            $segs = preg_split($pattern, $choices, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            
            // Logic for Choices Hanging Indent
            // User: "text-indent or manual coords"
            // Manual Coords is safer for TCPDF MultiCell text wrapping.
            
            for ($i=0; $i<count($segs); $i+=2) {
                $sym = $segs[$i];
                $txt = isset($segs[$i+1]) ? trim($segs[$i+1]) : '';
                $txt = self::clean_text($txt);
                
                $current_x = $pdf->GetX();
                $current_y = $pdf->GetY();
                
                // Symbol Column (Fixed 6mm)
                $pdf->MultiCell(6, 0, $sym, 0, 'L', 0, 0);
                
                // Text Column (Rest)
                // We need to know where the Text Cell started.
                // MultiCell 0 puts cursor at next line? No, 0=Right.
                // State: cursor is at X+6. 
                // We want Text to wrap within (Width - 6).
                // Column Width ~ 85. Text Width ~ 79.
                
                $pdf->MultiCell(0, 0, $txt, 0, 'L', 0, 1);
                // Note: The above calculates height of text and moves Y down. 
                // But Symbol MultiCell (ln=0) moves cursor to Right.
                // If text wraps, it consumes height.
                // Check if Symbol needs vertical alignment? 
                // Usually Top align is fine.
            }
        }
        
        // Explanation
        if ($type == 'explanation' && !empty($q->explanation)) {
             $pdf->Ln(1);
             $clean_expl = self::clean_text($q->explanation);
             $pdf->SetFont('cid0kr', '', 9);
             $pdf->MultiCell(0, 0, "[해설] " . $clean_expl, 1, 'L', 0, 1);
        }
    }

	private static function get_questions( $year, $session, $course ) {
		global $wpdb;
		$sql = "
			SELECT q.*, c.subject, c.subject_category, c.question_no, c.exam_year, c.exam_session
			FROM ptgates_questions q
			JOIN ptgates_categories c ON q.question_id = c.question_id
			WHERE c.exam_year = %d
			AND c.exam_course = %s
		";
        $params = [ $year, $course ];
        if ( $session ) { $sql .= " AND c.exam_session = %d"; $params[] = $session; }
		$sql .= " ORDER BY ISNULL(c.question_no), c.question_no ASC, q.question_id ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}
}

class PTG_TCPDF extends \TCPDF {
    public $custom_header_info = '';

    public function Header() {
        $this->SetFont( 'cid0kr', '', 9 );
        $this->Cell( 0, 5, '물리치료사 학습 플랫폼 :: https://ptgates.com', 0, 1, 'C', 0, '', 0, false, 'T', 'M' );
        
        $this->SetFont( 'cid0kr', 'B', 14 );
        $this->Cell( 0, 10, $this->custom_header_info, 0, 1, 'C', 0, '', 0, false, 'T', 'M' );
        
        $this->Line(15, 28, 195, 28, array('width' => 0.3));
        $this->Line(105, 32, 105, 275, array('width' => 0.1, 'color' => array(150, 150, 150))); 
    }

    public function Footer() {
        $this->SetY( -15 ); 
        $this->SetFont( 'cid0kr', 'B', 10 );
        // Absolute Center Calculation
        // Page 210.
        $this->Cell(0, 8, $this->getAliasNumPage(), 1, 0, 'C');
    }
}
