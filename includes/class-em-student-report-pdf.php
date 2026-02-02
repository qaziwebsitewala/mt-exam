<?php
defined( 'ABSPATH' ) || exit;

class EM_Student_Report_PDF {
	
	public static function init() {
		add_action( 'admin_post_em_student_report_pdf', [ __CLASS__, 'generate_pdf' ] );
	}

	public static function generate_pdf() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized access' );
		}

		// Load TCPDF
		require_once EM_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php';

		$data = EM_Student_Report::get_report_data();
		
		if ( empty( $data ) ) {
			wp_die( 'No data available for export.' );
		}

		// Calculate summary
		$summary = self::calculate_summary( $data );

		$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
		
		$pdf->SetCreator('WordPress');
		$pdf->SetAuthor('Student Report System');
		$pdf->SetTitle('Student Statistics Report');
		$pdf->SetMargins(10, 15, 10);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 9);

		// Title
		$html = '
		<h2 style="text-align:center;">Student Statistics Report</h2>
		<p style="text-align:center;font-size:10px;">
			Generated: '. date('F j, Y g:i A') .'
		</p>
		<hr><br>
		';

		// Summary Section
		$html .= '
		<table border="0" cellpadding="4" style="margin-bottom:15px;">
			<tr>
				<td width="33%"><strong>Total Students:</strong> ' . $summary['total_students'] . '</td>
				<td width="33%"><strong>Total Exams:</strong> ' . $summary['total_exams'] . '</td>
				<td width="34%"><strong>Overall Average:</strong> ' . number_format($summary['overall_average'], 2) . '%</td>
			</tr>
			<tr>
				<td width="33%"><strong>Pass Rate:</strong> ' . number_format($summary['pass_rate'], 1) . '%</td>
				<td width="33%"><strong>Highest Score:</strong> ' . number_format($summary['highest'], 1) . '%</td>
				<td width="34%"><strong>Lowest Score:</strong> ' . number_format($summary['lowest'], 1) . '%</td>
			</tr>
		</table>
		<br>
		';

		// Table header
		$html .= '
		<table border="1" cellpadding="4">
			<tr style="background-color:#f2f2f2;font-weight:bold;">
				<th width="10%">ID</th>
				<th width="22%">Student Name</th>
				<th width="22%">Exam</th>
				<th width="18%">Term</th>
				<th width="14%">Total Marks</th>
				<th width="14%">Average Marks</th>
			</tr>
		';

		// Rows
		foreach ( $data as $row ) {
			// Format terms
			$terms_display = '';
			if ( ! empty( $row['terms'] ) ) {
				$terms_display = implode( ', ', $row['terms'] );
			} else {
				$terms_display = 'No Term';
			}

			// Determine color based on average marks
			$row_color = '';
			if ( $row['average_marks'] >= 90 ) {
				$row_color = 'background-color:#d4edda;'; // Excellent
			} elseif ( $row['average_marks'] >= 70 ) {
				$row_color = 'background-color:#d1ecf1;'; // Good
			} elseif ( $row['average_marks'] >= 50 ) {
				$row_color = 'background-color:#fff3cd;'; // Average
			} else {
				$row_color = 'background-color:#f8d7da;'; // Poor
			}

			$html .= '
			<tr style="' . $row_color . '">
				<td>' . esc_html( $row['student_id'] ) . '</td>
				<td>' . esc_html( $row['student_name'] ) . '</td>
				<td>' . esc_html( $row['exam_name'] ) . '</td>
				<td>' . esc_html( $terms_display ) . '</td>
				<td style="text-align:center;">' . number_format( $row['total_marks'], 1 ) . '%</td>
				<td style="text-align:center;font-weight:bold;">' . number_format( $row['average_marks'], 1 ) . '%</td>
			</tr>
			';
		}

		$html .= '</table>';

		// Footer note
		$html .= '
		<br><br>
		<p style="font-size:8px;color:#666;">
			<strong>Note:</strong> Total Marks = Sum of all marks across terms | Average Marks = Mean of all term marks<br>
			Color coding: Green (90%+) | Blue (70-89%) | Yellow (50-69%) | Red (Below 50%)
		</p>
		';

		$pdf->writeHTML($html, true, false, true, false, '');

		// Download PDF
		$filename = 'student-statistics-report-' . date('Y-m-d_H-i-s') . '.pdf';
		$pdf->Output( $filename, 'D' );
		exit;
	}

	/**
	 * Calculate summary statistics
	 */
	private static function calculate_summary( $data ) {
		$total_students = count( array_unique( array_column( $data, 'student_id' ) ) );
		$total_exams = count( array_unique( array_column( $data, 'exam_id' ) ) );
		
		$all_averages = array_column( $data, 'average_marks' );
		$overall_average = count( $all_averages ) > 0 ? array_sum( $all_averages ) / count( $all_averages ) : 0;
		
		$passed = count( array_filter( $all_averages, function( $mark ) {
			return $mark >= 50;
		} ) );
		$pass_rate = count( $all_averages ) > 0 ? ( $passed / count( $all_averages ) ) * 100 : 0;
		
		$highest = count( $all_averages ) > 0 ? max( $all_averages ) : 0;
		$lowest = count( $all_averages ) > 0 ? min( $all_averages ) : 0;

		return [
			'total_students'   => $total_students,
			'total_exams'      => $total_exams,
			'overall_average'  => $overall_average,
			'pass_rate'        => $pass_rate,
			'highest'          => $highest,
			'lowest'           => $lowest,
		];
	}
}