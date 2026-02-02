<?php
defined( 'ABSPATH' ) || exit;

class EM_Student_Report {
	
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=em_result',
			'Student Statistics',
			'Student Statistics',
			'manage_options',
			'em-student-report',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'em_result_page_em-student-report' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'em-student-report',
			EM_PLUGIN_URL . 'assets/css/admin-student-report.css',
			[],
			'1.0.0'
		);
	}

	public static function render_page() {
		$data = self::get_report_data();
		
		// TEMPORARY DEBUG - Remove after fixing
		if ( isset( $_GET['debug'] ) && current_user_can( 'manage_options' ) ) {
			echo '<pre style="background: #f0f0f0; padding: 20px; overflow: auto;">';
			echo "=== DEBUG MODE ===\n\n";
			
			global $wpdb;
			$raw_results = $wpdb->get_results(
				"SELECT p.ID as result_id, p.post_title, 
				pm_exam.meta_value as exam_id,
				pm_marks.meta_value as marks_raw
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_exam ON pm_exam.post_id = p.ID AND pm_exam.meta_key = 'em_exam_id'
				LEFT JOIN {$wpdb->postmeta} pm_marks ON pm_marks.post_id = p.ID AND pm_marks.meta_key = 'em_student_marks'
				WHERE p.post_type = 'em_result' AND p.post_status = 'publish'
				ORDER BY p.ID DESC",
				ARRAY_A
			);
			
			echo "Total Results Found: " . count( $raw_results ) . "\n\n";
			
			foreach ( $raw_results as $result ) {
				echo "Result ID: {$result['result_id']}\n";
				echo "Title: {$result['post_title']}\n";
				echo "Exam ID: {$result['exam_id']}\n";
				
				// Check if exam is valid
				if ( $result['exam_id'] ) {
					$exam_post = get_post( $result['exam_id'] );
					if ( $exam_post && $exam_post->post_type === 'em_exam' ) {
						echo "Exam Valid: YES - {$exam_post->post_title}\n";
					} else {
						echo "Exam Valid: NO\n";
					}
				}
				
				echo "Marks Raw:\n";
				$marks = maybe_unserialize( $result['marks_raw'] );
				if ( is_array( $marks ) ) {
					foreach ( $marks as $sid => $score ) {
						$student_post = get_post( intval( $sid ) );
						$valid = ( $student_post && $student_post->post_type === 'em_student' ) ? 'YES' : 'NO';
						echo "  Student ID: {$sid} (valid: {$valid}) => Score: {$score}\n";
						if ( $valid === 'YES' ) {
							echo "    Student Name: {$student_post->post_title}\n";
						}
					}
				} else {
					echo "  (NOT AN ARRAY)\n";
				}
				echo "\n---\n";
			}
			
			echo "\n\n=== FILTERED DATA ARRAY ===\n";
			echo "Total Entries: " . count( $data ) . "\n\n";
			print_r( $data );
			echo '</pre>';
			return;
		}
		
		// Debug: Check if we have results but no data after filtering
		global $wpdb;
		$result_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'em_result' AND post_status = 'publish'" );
		
		if ( empty( $data ) ) {
			?>
			<div class="wrap">
				<h1>Student Statistics Report</h1>
				<div class="notice notice-warning">
					<p>No results data found. Please import results via CSV first.</p>
					<?php if ( $result_count > 0 ) : ?>
						<p><strong>Debug Info:</strong> Found <?php echo $result_count; ?> result(s) in database, but no valid student-exam mappings after validation. This might indicate:</p>
						<ul>
							<li>Student IDs in results are not valid em_student posts</li>
							<li>Exam IDs in results are not valid em_exam posts</li>
							<li>Data needs to be re-imported with the fixed import file</li>
						</ul>
						<p><a href="?page=em-student-report&debug=1" class="button">View Debug Data</a></p>
					<?php endif; ?>
					<p>
						<a href="<?php echo admin_url( 'edit.php?post_type=em_exam&page=em-import-results' ); ?>" class="button button-primary">
							Go to Import Results
						</a>
						<a href="<?php echo admin_url( 'edit.php?post_type=em_student' ); ?>" class="button">
							View Students
						</a>
						<a href="<?php echo admin_url( 'edit.php?post_type=em_exam' ); ?>" class="button">
							View Exams
						</a>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		?>
		<div class="wrap em-report-wrapper">
			<h1>📊 Student Statistics Report</h1>
			
			<div class="em-report-actions">
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=em_student_report_pdf' ) ); ?>"
				   class="button button-primary">
					📄 Export as PDF
				</a>
				<button type="button" class="button" onclick="window.print()">
					🖨️ Print Report
				</button>
			</div>

			<div class="em-report-info">
				<p><strong>Total Students:</strong> <?php echo count( $data ); ?></p>
				<p><strong>Report Generated:</strong> <?php echo current_time( 'F j, Y g:i A' ); ?></p>
			</div>

			<table class="widefat striped em-report-table">
				<thead>
					<tr>
						<th class="em-col-id">ID</th>
						<th class="em-col-student">Student Name</th>
						<th class="em-col-exam">Exam</th>
						<th class="em-col-term">Term</th>
						<th class="em-col-total">Total Marks</th>
						<th class="em-col-average">Average Marks</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $data as $row ) : ?>
					<tr class="<?php echo $row['average_marks'] >= 50 ? 'em-passed' : 'em-failed'; ?>">
						<td class="em-col-id">
							<?php echo esc_html( $row['student_id'] ); ?>
						</td>
						<td class="em-col-student">
							<strong><?php echo esc_html( $row['student_name'] ); ?></strong>
						</td>
						<td class="em-col-exam">
							<?php echo esc_html( $row['exam_name'] ); ?>
						</td>
						<td class="em-col-term">
							<?php if ( ! empty( $row['terms'] ) ) : ?>
								<?php foreach ( $row['terms'] as $term ) : ?>
									<span class="em-term-badge"><?php echo esc_html( $term ); ?></span>
								<?php endforeach; ?>
							<?php else : ?>
								<span class="em-no-term">No Term</span>
							<?php endif; ?>
						</td>
						<td class="em-col-total">
							<span class="em-marks-badge <?php echo self::get_marks_class( $row['total_marks'] ); ?>">
								<?php echo number_format( $row['total_marks'], 1 ); ?>%
							</span>
						</td>
						<td class="em-col-average">
							<span class="em-marks-badge <?php echo self::get_marks_class( $row['average_marks'] ); ?>">
								<?php echo number_format( $row['average_marks'], 1 ); ?>%
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="em-report-summary">
				<h3>Summary Statistics</h3>
				<?php 
				$summary = self::calculate_summary( $data );
				?>
				<div class="em-summary-grid">
					<div class="em-summary-item">
						<span class="em-summary-label">Total Students:</span>
						<span class="em-summary-value"><?php echo $summary['total_students']; ?></span>
					</div>
					<div class="em-summary-item">
						<span class="em-summary-label">Total Exams:</span>
						<span class="em-summary-value"><?php echo $summary['total_exams']; ?></span>
					</div>
					<div class="em-summary-item">
						<span class="em-summary-label">Overall Average:</span>
						<span class="em-summary-value"><?php echo number_format( $summary['overall_average'], 2 ); ?>%</span>
					</div>
					<div class="em-summary-item">
						<span class="em-summary-label">Pass Rate:</span>
						<span class="em-summary-value"><?php echo number_format( $summary['pass_rate'], 1 ); ?>%</span>
					</div>
					<div class="em-summary-item">
						<span class="em-summary-label">Highest Score:</span>
						<span class="em-summary-value"><?php echo number_format( $summary['highest'], 1 ); ?>%</span>
					</div>
					<div class="em-summary-item">
						<span class="em-summary-label">Lowest Score:</span>
						<span class="em-summary-value"><?php echo number_format( $summary['lowest'], 1 ); ?>%</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get report data - One row per student-exam combination with aggregated term data
	 */
	public static function get_report_data() {
		global $wpdb;

		// Get all results with their associated data (most recent first via ORDER BY p.ID DESC)
		$results = $wpdb->get_results(
			"
			SELECT 
				p.ID AS result_id,
				pm_marks.meta_value AS marks,
				pm_exam.meta_value AS exam_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_marks
				ON pm_marks.post_id = p.ID AND pm_marks.meta_key = 'em_student_marks'
			INNER JOIN {$wpdb->postmeta} pm_exam
				ON pm_exam.post_id = p.ID AND pm_exam.meta_key = 'em_exam_id'
			WHERE p.post_type = 'em_result'
			AND p.post_status = 'publish'
			ORDER BY p.ID DESC
			",
			ARRAY_A
		);

		// First, collect all data by student-exam combination
		$grouped_data = [];

		foreach ( $results as $result ) {
			$marks = maybe_unserialize( $result['marks'] );
			$exam_id = intval( $result['exam_id'] );
			$result_id = intval( $result['result_id'] );

			if ( ! is_array( $marks ) ) {
				continue;
			}

			// Verify this is actually a valid exam post
			$exam_post = get_post( $exam_id );
			if ( ! $exam_post || $exam_post->post_type !== 'em_exam' ) {
				continue;
			}

			// Get exam details
			$exam_name = $exam_post->post_title;
			
			// Get term name (if exists)
			$term_name = self::get_exam_term( $exam_id );

			// Group by student and exam
			foreach ( $marks as $student_id => $score ) {
				// Convert student_id to integer (handles both string and int keys)
				$student_id = intval( $student_id );
				
				// Skip invalid student IDs
				if ( ! $student_id || $student_id == 0 ) {
					continue;
				}
				
				// Verify this is actually a valid student post
				$student_post = get_post( $student_id );
				
				// Skip if post doesn't exist
				if ( ! $student_post ) {
					continue;
				}
				
				// Check if it's a student post - if not, skip
				if ( $student_post->post_type !== 'em_student' ) {
					continue;
				}
				
				$student_name = $student_post->post_title;
				
				// Skip if student doesn't have a name or is auto draft
				if ( ! $student_name || $student_name === 'Auto Draft' ) {
					continue;
				}

				$key = $student_id . '-' . $exam_id;

				if ( ! isset( $grouped_data[ $key ] ) ) {
					$grouped_data[ $key ] = [
						'student_id'   => $student_id,
						'student_name' => $student_name,
						'exam_id'      => $exam_id,
						'exam_name'    => $exam_name,
						'result_id'    => $result_id,
						'terms'        => [],
						'marks'        => [],
					];
				}

				// Add term if it exists and not already added
				if ( $term_name && ! in_array( $term_name, $grouped_data[ $key ]['terms'] ) ) {
					$grouped_data[ $key ]['terms'][] = $term_name;
				}

				// Add marks
				$grouped_data[ $key ]['marks'][] = floatval( $score );
			}
		}

		// Now convert to final format with calculated totals and averages
		$data = [];
		foreach ( $grouped_data as $item ) {
			$total_marks = array_sum( $item['marks'] );
			$average_marks = count( $item['marks'] ) > 0 ? $total_marks / count( $item['marks'] ) : 0;

			$data[] = [
				'student_id'    => $item['student_id'],
				'student_name'  => $item['student_name'],
				'exam_id'       => $item['exam_id'],
				'exam_name'     => $item['exam_name'],
				'result_id'     => $item['result_id'],
				'terms'         => $item['terms'],
				'total_marks'   => $total_marks,
				'average_marks' => $average_marks,
			];
		}

		// Sort by most recent first: highest result_id = most recently imported
		usort( $data, function( $a, $b ) {
			return $b['result_id'] - $a['result_id'];
		} );

		return $data;
	}

	/**
	 * Get term name for an exam
	 */
	private static function get_exam_term( $exam_id ) {
		$terms = wp_get_post_terms( $exam_id, 'em_term', [ 'fields' => 'names' ] );
		
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return $terms[0]; // Return first term name
	}

	/**
	 * Get CSS class for marks
	 */
	private static function get_marks_class( $marks ) {
		if ( $marks >= 90 ) return 'em-marks-excellent';
		if ( $marks >= 70 ) return 'em-marks-good';
		if ( $marks >= 50 ) return 'em-marks-average';
		return 'em-marks-poor';
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