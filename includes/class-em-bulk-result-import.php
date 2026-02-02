<?php
defined( 'ABSPATH' ) || exit;

class EM_Bulk_Result_Import {
	
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_post_em_import_results', [ __CLASS__, 'handle_import' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'em_exam_page_em-import-results' !== $hook ) {
			return;
		}

		wp_enqueue_style( 
			'em-import-results', 
			EM_PLUGIN_URL . 'assets/css/admin-import.css', 
			[], 
			'1.0.0' 
		);

		wp_enqueue_script( 
			'em-import-results', 
			EM_PLUGIN_URL . 'assets/js/admin-import.js', 
			[ 'jquery' ], 
			'1.0.0', 
			true 
		);
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=em_exam',
			'Import Results',
			'Import Results',
			'manage_options',
			'em-import-results',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		// Display messages
		if ( isset( $_GET['import'] ) ) {
			$status = sanitize_text_field( $_GET['import'] );
			$count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
			$errors = isset( $_GET['errors'] ) ? intval( $_GET['errors'] ) : 0;
			$total_rows = isset( $_GET['total'] ) ? intval( $_GET['total'] ) : 0;
			$created_exams = isset( $_GET['created_exams'] ) ? intval( $_GET['created_exams'] ) : 0;
			$created_students = isset( $_GET['created_students'] ) ? intval( $_GET['created_students'] ) : 0;

			if ( $status === 'success' ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo '<strong>Import Successful!</strong><br>';
				echo sprintf( 'Processed %d exam(s) with %d student results.', $count, $total_rows );
				
				if ( $created_exams > 0 || $created_students > 0 ) {
					echo '<br><strong>Auto-created:</strong> ';
					$auto_created = [];
					if ( $created_exams > 0 ) {
						$auto_created[] = "$created_exams exam(s)";
					}
					if ( $created_students > 0 ) {
						$auto_created[] = "$created_students student(s)";
					}
					echo implode( ' and ', $auto_created );
				}
				
				if ( $errors > 0 ) {
					echo sprintf( '<br><strong>Warning:</strong> %d row(s) had errors and were skipped.', $errors );
				}
				echo '</p></div>';
			} elseif ( $status === 'error' ) {
				$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : 'Import failed';
				echo '<div class="notice notice-error is-dismissible"><p> ' . esc_html( $message ) . '</p></div>';
			}
		}

		// Check existing data
		$exam_count = wp_count_posts( 'em_exam' )->publish;
		$student_count = wp_count_posts( 'em_student' )->publish;
		?>
		<div class="wrap em-import-wrapper">
			<h1>Import Results (CSV)</h1>
			
			<div class="notice notice-info">
				<p><strong>Smart Import:</strong> This system automatically creates exams and students if they don't exist!</p>
				<ul>
					<li>Current exams in system: <strong><?php echo $exam_count; ?></strong></li>
					<li>Current students in system: <strong><?php echo $student_count; ?></strong></li>
				</ul>
			</div>

			<div class="em-import-container">
				<div class="em-import-instructions">
					<h2>Two Import Methods</h2>
					
					<div class="em-import-methods">
						<div class="em-method">
							<h3>Method 1: Use Student/Exam Names (Recommended)</h3>
							<p><strong>Best for:</strong> Starting fresh or when you don't know IDs</p>
							<p><strong>CSV Format:</strong> <code>exam_name,student_name,marks</code></p>
							<p><strong>How it works:</strong></p>
							<ul>
								<li>System automatically creates exams if they don't exist</li>
								<li>System automatically creates students if they don't exist</li>
								<li>If exam/student already exists by name, it uses existing one</li>
								<li>Safe to import multiple times</li>
							</ul>
							<pre class="em-csv-preview">exam_name,student_name,marks
Math Final 2026,John Smith,85.5
Math Final 2026,Jane Doe,92.0
Science Midterm 2026,John Smith,78.5</pre>
						</div>

						<div class="em-method">
							<h3>Method 2: Use Existing IDs</h3>
							<p><strong>Best for:</strong> When you already have exams/students in system</p>
							<p><strong>CSV Format:</strong> <code>exam_id,student_id,marks</code></p>
							<p><strong>How it works:</strong></p>
							<ul>
								<li>Uses exact IDs from your system</li>
								<li>Faster import (no lookup needed)</li>
								<li>Will show error if ID doesn't exist</li>
							</ul>
							<pre class="em-csv-preview">exam_id,student_id,marks
123,456,85.5
123,457,92.0
124,456,78.5</pre>
						</div>
					</div>

					<div class="em-sample-download">
						<h3> Download Sample Templates</h3>
						<a href="<?php echo esc_url( self::get_sample_csv_url( 'names' ) ); ?>" 
							class="button button-secondary" 
							download="sample-results-by-name.csv">
							Sample CSV (By Names)
						</a>
						<a href="<?php echo esc_url( self::get_sample_csv_url( 'ids' ) ); ?>" 
							class="button button-secondary" 
							download="sample-results-by-id.csv">
							Sample CSV (By IDs)
						</a>
					</div>

					<div class="em-import-tips">
						<h4>Pro Tips:</h4>
						<ul>
							<li><strong>Mix and match:</strong> You can import for multiple exams in one CSV</li>
							<li><strong>Duplicate names:</strong> System matches by exact name (case-sensitive)</li>
							<li><strong>Updates:</strong> Re-importing same exam updates the marks</li>
							<li><strong>Decimal marks:</strong> Use 85.5, 92.0, etc. for precision</li>
							<li><strong>Auto-creation:</strong> New exams/students are created as "Published"</li>
						</ul>
					</div>
				</div>

				<div class="em-import-form-container">
					<h2>Upload CSV File</h2>
					<form method="post" 
						enctype="multipart/form-data" 
						action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						id="em-import-form">
						
						<?php wp_nonce_field( 'em_import_results_nonce', 'em_import_results_nonce_field' ); ?>
						<input type="hidden" name="action" value="em_import_results">
						
						<div class="em-file-upload-wrapper">
							<label for="results_csv" class="em-file-label">
								<span class="dashicons dashicons-upload"></span>
								<span class="em-file-text">Choose CSV file or drag & drop here</span>
							</label>
							<input type="file" 
								name="results_csv" 
								id="results_csv"
								accept=".csv" 
								required>
							<div class="em-file-info" style="display: none;">
								<span class="dashicons dashicons-media-spreadsheet"></span>
								<span class="em-filename"></span>
								<button type="button" class="em-remove-file" title="Remove file">×</button>
							</div>
						</div>

						<div class="em-import-options">
							<label>
								<input type="checkbox" name="em_skip_errors" value="1" checked>
								<strong>Skip rows with errors</strong> and continue import
							</label>
							<label>
								<input type="checkbox" name="em_update_existing" value="1" checked>
								<strong>Update existing results</strong> if same exam already has results
							</label>
							<label>
								<input type="checkbox" name="em_auto_create" value="1" checked>
								<strong>Auto-create exams/students</strong> if they don't exist (for name-based import)
							</label>
						</div>

						<button type="submit" class="button button-primary button-large">
							<span class="dashicons dashicons-upload"></span>
							Import Results
						</button>
					</form>

					<div class="em-recent-imports">
						<h3>Recent Imports</h3>
						<?php self::display_recent_imports(); ?>
					</div>
				</div>
			</div>

			<div class="em-data-flow">
				<h2>How Smart Import Works</h2>
				<div class="em-flow-diagram">
					<div class="em-flow-step">
						<strong>1. CSV Upload</strong>
						<p>Names or IDs + marks</p>
					</div>
					<span class="em-arrow">→</span>
					<div class="em-flow-step">
						<strong>2. Detection</strong>
						<p>Auto-detect format</p>
					</div>
					<span class="em-arrow">→</span>
					<div class="em-flow-step">
						<strong>3. Lookup/Create</strong>
						<p>Find or create exam/student</p>
					</div>
					<span class="em-arrow">→</span>
					<div class="em-flow-step">
						<strong>4. Map & Save</strong>
						<p>Link everything together</p>
					</div>
					<span class="em-arrow">→</span>
					<div class="em-flow-step">
						<strong>5. Ready!</strong>
						<p>View in all reports</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_import() {
		// Security check
		if (
			! isset( $_POST['em_import_results_nonce_field'] ) ||
			! wp_verify_nonce( $_POST['em_import_results_nonce_field'], 'em_import_results_nonce' )
		) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to perform this action' );
		}

		if ( empty( $_FILES['results_csv']['tmp_name'] ) ) {
			self::redirect_with_error( 'No file uploaded' );
		}

		$file_type = wp_check_filetype( $_FILES['results_csv']['name'] );
		if ( $file_type['ext'] !== 'csv' ) {
			self::redirect_with_error( 'Invalid file type. Please upload a CSV file.' );
		}

		$file = fopen( $_FILES['results_csv']['tmp_name'], 'r' );
		if ( ! $file ) {
			self::redirect_with_error( 'Unable to read file' );
		}

		// Get options
		$skip_errors = isset( $_POST['em_skip_errors'] );
		$update_existing = isset( $_POST['em_update_existing'] );
		$auto_create = isset( $_POST['em_auto_create'] );

		// Read and validate header
		$header = fgetcsv( $file );
		if ( ! $header ) {
			fclose( $file );
			self::redirect_with_error( 'Empty CSV file' );
		}

		$header_normalized = array_map( 'strtolower', array_map( 'trim', $header ) );
		
		// Detect format
		$is_name_based = false;
		if ( $header_normalized === [ 'exam_name', 'student_name', 'marks' ] ) {
			$is_name_based = true;
		} elseif ( $header_normalized === [ 'exam_id', 'student_id', 'marks' ] ) {
			$is_name_based = false;
		} else {
			fclose( $file );
			self::redirect_with_error( 
				'Invalid CSV header. Expected either: exam_name,student_name,marks OR exam_id,student_id,marks'
			);
		}

		// Process rows
		$grouped_results = [];
		$error_count = 0;
		$row_number = 1;
		$errors = [];
		$total_valid_rows = 0;
		$created_exams_count = 0;
		$created_students_count = 0;
		$created_exams = [];
		$created_students = [];

		while ( ( $row = fgetcsv( $file ) ) !== false ) {
			$row_number++;

			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			if ( count( $row ) < 3 ) {
				$error_count++;
				$errors[] = "Row $row_number: Missing columns";
				if ( ! $skip_errors ) {
					fclose( $file );
					self::redirect_with_error( "Row $row_number has missing columns" );
				}
				continue;
			}

			$exam_identifier = trim( $row[0] );
			$student_identifier = trim( $row[1] );
			$marks = trim( $row[2] );

			// Get or create exam
			if ( $is_name_based ) {
				// Name-based import
				if ( empty( $exam_identifier ) ) {
					$error_count++;
					$errors[] = "Row $row_number: Exam name is empty";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Exam name cannot be empty" );
					}
					continue;
				}

				$exam_result = self::get_or_create_exam_by_name( $exam_identifier, $auto_create );
				if ( ! $exam_result || ! isset( $exam_result['id'] ) || ! $exam_result['id'] ) {
					$error_count++;
					$errors[] = "Row $row_number: Could not find/create exam '$exam_identifier' - exam_id is invalid";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Failed to create/find exam '$exam_identifier'" );
					}
					continue;
				}

				$exam_id = intval( $exam_result['id'] );
				
				// Double-check exam_id is valid
				if ( ! $exam_id || $exam_id === 0 ) {
					$error_count++;
					$errors[] = "Row $row_number: Exam ID is 0 or invalid for '$exam_identifier'";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Exam ID is invalid (0) for '$exam_identifier'" );
					}
					continue;
				}
				
				// Track newly created exams
				if ( $exam_result['created'] && ! isset( $created_exams[ $exam_id ] ) ) {
					$created_exams[ $exam_id ] = true;
					$created_exams_count++;
				}

			} else {
				// ID-based import
				$exam_id = absint( $exam_identifier );
				if ( ! $exam_id || get_post_type( $exam_id ) !== 'em_exam' ) {
					$error_count++;
					$errors[] = "Row $row_number: Exam ID $exam_identifier not found";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Exam ID $exam_identifier does not exist" );
					}
					continue;
				}
			}

			// Get or create student
			if ( $is_name_based ) {
				if ( empty( $student_identifier ) ) {
					$error_count++;
					$errors[] = "Row $row_number: Student name is empty";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Student name cannot be empty" );
					}
					continue;
				}

				$student_result = self::get_or_create_student_by_name( $student_identifier, $auto_create );
				
				// Log what we got back
				error_log( "EM Import Debug: Student '$student_identifier' returned: " . print_r( $student_result, true ) );
				
				if ( ! $student_result || ! isset( $student_result['id'] ) || ! $student_result['id'] ) {
					$error_count++;
					$errors[] = "Row $row_number: Could not find/create student '$student_identifier' - student_id is invalid";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Failed to create/find student '$student_identifier'" );
					}
					continue;
				}

				$student_id = intval( $student_result['id'] );
				
				// Log the final ID
				error_log( "EM Import Debug: Final student_id for '$student_identifier': $student_id" );
				
				// Double-check student_id is valid
				if ( ! $student_id || $student_id === 0 ) {
					$error_count++;
					$errors[] = "Row $row_number: Student ID is 0 or invalid for '$student_identifier'";
					error_log( "EM Import ERROR: Student ID is 0 for '$student_identifier' at row $row_number" );
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Student ID is invalid (0) for '$student_identifier'" );
					}
					continue;
				}
				
				// Track newly created students
				if ( $student_result['created'] && ! isset( $created_students[ $student_id ] ) ) {
					$created_students[ $student_id ] = true;
					$created_students_count++;
				}

			} else {
				$student_id = absint( $student_identifier );
				if ( ! $student_id || get_post_type( $student_id ) !== 'em_student' ) {
					$error_count++;
					$errors[] = "Row $row_number: Student ID $student_identifier not found";
					if ( ! $skip_errors ) {
						fclose( $file );
						self::redirect_with_error( "Row $row_number: Student ID $student_identifier does not exist" );
					}
					continue;
				}
			}

			// Validate marks
			if ( ! is_numeric( $marks ) || $marks < 0 || $marks > 100 ) {
				$error_count++;
				$errors[] = "Row $row_number: Invalid marks ($marks)";
				if ( ! $skip_errors ) {
					fclose( $file );
					self::redirect_with_error( "Row $row_number: Marks must be 0-100" );
				}
				continue;
			}

			$marks = floatval( $marks );
			
			if ( ! isset( $grouped_results[ $exam_id ] ) ) {
				$grouped_results[ $exam_id ] = [];
			}
			
			// Log before saving
			error_log( "EM Import Debug: About to store - Exam ID: $exam_id, Student ID: $student_id, Marks: $marks" );
			
			$grouped_results[ $exam_id ][ $student_id ] = $marks;
			$total_valid_rows++;
		}

		fclose( $file );

		if ( empty( $grouped_results ) ) {
			self::redirect_with_error( 'No valid data to import' );
		}

		// Save results
		$processed_count = 0;
		
		foreach ( $grouped_results as $exam_id => $student_marks ) {
			error_log( "EM Import Debug: Processing exam_id $exam_id with " . count($student_marks) . " students" );
			error_log( "EM Import Debug: Student marks to save: " . print_r( $student_marks, true ) );
			
			$result_id = self::get_or_create_result( $exam_id, $update_existing );
			
			if ( $result_id ) {
				update_post_meta( $result_id, 'em_exam_id', $exam_id );
				update_post_meta( $result_id, 'em_import_date', current_time( 'mysql' ) );
				
				if ( $update_existing ) {
					$existing_marks = get_post_meta( $result_id, 'em_student_marks', true );
					error_log( "EM Import Debug: Existing marks: " . print_r( $existing_marks, true ) );
					
					if ( ! is_array( $existing_marks ) ) {
						$existing_marks = [];
					}
					
					// Filter out invalid student IDs from existing marks (0, negative, non-students)
					$valid_existing_marks = [];
					foreach ( $existing_marks as $sid => $score ) {
						$sid_int = intval( $sid );
						if ( $sid_int > 0 ) {
							$student_post = get_post( $sid_int );
							if ( $student_post && $student_post->post_type === 'em_student' ) {
								$valid_existing_marks[ $sid_int ] = $score;
							}
						}
					}
					
					// FIX: Use + operator instead of array_merge to preserve numeric student ID keys.
					// array_merge() reindexes numeric keys (205,206 → 0,1), breaking the student-marks mapping.
					// The + operator keeps original keys. New marks ($student_marks) come first so they
					// overwrite existing marks for the same student ID.
					$student_marks = $student_marks + $valid_existing_marks;
					error_log( "EM Import Debug: Merged marks: " . print_r( $student_marks, true ) );
				}
				
				update_post_meta( $result_id, 'em_student_marks', $student_marks );
				error_log( "EM Import Debug: Saved to result_id $result_id" );
				$processed_count++;
			}
		}

		// Log import
		self::log_import( $processed_count, $error_count, $total_valid_rows, $errors, $created_exams_count, $created_students_count );

		// Redirect with success
		wp_redirect(
			add_query_arg(
				[
					'import'           => 'success',
					'count'            => $processed_count,
					'total'            => $total_valid_rows,
					'errors'           => $error_count,
					'created_exams'    => $created_exams_count,
					'created_students' => $created_students_count,
				],
				admin_url( 'edit.php?post_type=em_exam&page=em-import-results' )
			)
		);
		exit;
	}

	/**
	 * Get or create exam by name
	 * Returns array: ['id' => exam_id, 'created' => bool]
	 */
	private static function get_or_create_exam_by_name( $exam_name, $auto_create = true ) {
		// Look for existing exam by title - MUST match post_type in query
		global $wpdb;
		
		$exam_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_title = %s 
			AND post_type = 'em_exam' 
			AND post_status = 'publish'
			LIMIT 1",
			$exam_name
		) );

		if ( $exam_id ) {
			return [
				'id' => intval( $exam_id ),
				'created' => false
			];
		}

		if ( ! $auto_create ) {
			return false;
		}

		// Create new exam
		$new_exam_id = wp_insert_post( [
			'post_type'   => 'em_exam',
			'post_title'  => $exam_name,
			'post_status' => 'publish',
		], true ); // true = return WP_Error on failure

		// Check if creation failed
		if ( is_wp_error( $new_exam_id ) ) {
			error_log( "EM Import Error: Failed to create exam '{$exam_name}': " . $new_exam_id->get_error_message() );
			return false;
		}

		if ( ! $new_exam_id || $new_exam_id == 0 ) {
			error_log( "EM Import Error: wp_insert_post returned 0 for exam '{$exam_name}'" );
			return false;
		}

		return [
			'id' => intval( $new_exam_id ),
			'created' => true
		];
	}

	/**
	 * Get or create student by name
	 * Returns array: ['id' => student_id, 'created' => bool]
	 */
	private static function get_or_create_student_by_name( $student_name, $auto_create = true ) {
		// Look for existing student by title - MUST match post_type in query
		global $wpdb;
		
		$student_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_title = %s 
			AND post_type = 'em_student' 
			AND post_status = 'publish'
			LIMIT 1",
			$student_name
		) );

		if ( $student_id ) {
			return [
				'id' => intval( $student_id ),
				'created' => false
			];
		}

		if ( ! $auto_create ) {
			return false;
		}

		// Create new student
		$new_student_id = wp_insert_post( [
			'post_type'   => 'em_student',
			'post_title'  => $student_name,
			'post_status' => 'publish',
		], true ); // true = return WP_Error on failure

		// Check if creation failed
		if ( is_wp_error( $new_student_id ) ) {
			error_log( "EM Import Error: Failed to create student '{$student_name}': " . $new_student_id->get_error_message() );
			return false;
		}

		if ( ! $new_student_id || $new_student_id == 0 ) {
			error_log( "EM Import Error: wp_insert_post returned 0 for student '{$student_name}'" );
			return false;
		}

		return [
			'id' => intval( $new_student_id ),
			'created' => true
		];
	}

	/**
	 * Get or create result post for an exam
	 */
	private static function get_or_create_result( $exam_id, $update_existing = true ) {
		$existing = get_posts( [
			'post_type'      => 'em_result',
			'meta_key'       => 'em_exam_id',
			'meta_value'     => $exam_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		] );

		if ( ! empty( $existing ) && $update_existing ) {
			return $existing[0];
		}

		$exam_title = get_the_title( $exam_id );
		$exam_title = $exam_title ? $exam_title : "Exam #$exam_id";
		
		$result_id = wp_insert_post( [
			'post_type'   => 'em_result',
			'post_status' => 'publish',
			'post_title'  => 'Result – ' . $exam_title,
		] );

		return $result_id;
	}

	private static function redirect_with_error( $message ) {
		wp_redirect(
			add_query_arg(
				[
					'import'  => 'error',
					'message' => urlencode( $message )
				],
				admin_url( 'edit.php?post_type=em_exam&page=em-import-results' )
			)
		);
		exit;
	}

	/**
	 * Get sample CSV URL
	 */
	public static function get_sample_csv_url( $type = 'names' ) {
		$upload_dir = wp_upload_dir();
		$csv_dir = $upload_dir['basedir'] . '/exam-management';
		$csv_file = $csv_dir . "/sample-results-by-{$type}.csv";

		// Force regeneration with version 2 (updated for 2026)
		$version_file = $csv_dir . "/.csv_version_{$type}";
		$current_version = '2'; // Increment this to force regeneration
		
		$needs_regeneration = false;
		if ( ! file_exists( $csv_file ) ) {
			$needs_regeneration = true;
		} elseif ( file_exists( $version_file ) ) {
			$stored_version = file_get_contents( $version_file );
			if ( $stored_version !== $current_version ) {
				$needs_regeneration = true;
			}
		} else {
			$needs_regeneration = true;
		}

		if ( $needs_regeneration ) {
			self::create_sample_csv( $csv_file, $csv_dir, $type );
			file_put_contents( $version_file, $current_version );
		}

		return $upload_dir['baseurl'] . "/exam-management/sample-results-by-{$type}.csv";
	}

	private static function create_sample_csv( $csv_file, $csv_dir, $type = 'names' ) {
		if ( ! file_exists( $csv_dir ) ) {
			wp_mkdir_p( $csv_dir );
		}

		if ( $type === 'names' ) {
			// Name-based sample with 2025-2026 years
			$csv_content = "exam_name,student_name,marks\n";
			$csv_content .= "Math Final 2026,John Smith,85.5\n";
			$csv_content .= "Math Final 2026,Jane Doe,92.0\n";
			$csv_content .= "Math Final 2026,Bob Johnson,78.5\n";
			$csv_content .= "Science Midterm 2026,John Smith,88.0\n";
			$csv_content .= "Science Midterm 2026,Jane Doe,91.5\n";
			$csv_content .= "English Final 2025,John Smith,75.0\n";
			$csv_content .= "English Final 2025,Bob Johnson,82.5\n";
		} else {
			// ID-based sample
			$sample_exams = get_posts( [
				'post_type'      => 'em_exam',
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'post_status'    => 'publish',
			] );

			$sample_students = get_posts( [
				'post_type'      => 'em_student',
				'posts_per_page' => 3,
				'fields'         => 'ids',
				'post_status'    => 'publish',
			] );

			$exam_ids = ! empty( $sample_exams ) ? $sample_exams : [ 123, 124 ];
			$student_ids = ! empty( $sample_students ) ? $sample_students : [ 456, 457, 458 ];

			$csv_content = "exam_id,student_id,marks\n";
			$csv_content .= "{$exam_ids[0]},{$student_ids[0]},85.5\n";
			$csv_content .= "{$exam_ids[0]}," . ( isset( $student_ids[1] ) ? $student_ids[1] : 457 ) . ",92.0\n";
			$csv_content .= "{$exam_ids[0]}," . ( isset( $student_ids[2] ) ? $student_ids[2] : 458 ) . ",78.5\n";
			
			if ( isset( $exam_ids[1] ) ) {
				$csv_content .= "{$exam_ids[1]},{$student_ids[0]},88.0\n";
				$csv_content .= "{$exam_ids[1]}," . ( isset( $student_ids[1] ) ? $student_ids[1] : 457 ) . ",91.5\n";
			}
		}

		file_put_contents( $csv_file, $csv_content );
	}

	private static function log_import( $processed, $errors, $total_rows, $error_details, $created_exams, $created_students ) {
		$logs = get_option( 'em_import_logs', [] );
		
		$logs[] = [
			'timestamp'        => current_time( 'mysql' ),
			'user_id'          => get_current_user_id(),
			'processed'        => $processed,
			'total_rows'       => $total_rows,
			'errors'           => $errors,
			'created_exams'    => $created_exams,
			'created_students' => $created_students,
			'details'          => array_slice( $error_details, 0, 10 ),
		];

		$logs = array_slice( $logs, -20 );
		update_option( 'em_import_logs', $logs );
	}

	private static function display_recent_imports() {
		$logs = get_option( 'em_import_logs', [] );
		
		if ( empty( $logs ) ) {
			echo '<p class="em-no-imports">No recent imports found.</p>';
			return;
		}

		$logs = array_reverse( $logs );
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>Date</th>';
		echo '<th>User</th>';
		echo '<th>Results</th>';
		echo '<th>Created</th>';
		echo '<th>Status</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		
		foreach ( array_slice( $logs, 0, 5 ) as $log ) {
			$user = get_userdata( $log['user_id'] );
			$username = $user ? $user->display_name : 'Unknown';
			
			$created_text = '';
			if ( isset( $log['created_exams'] ) && $log['created_exams'] > 0 ) {
				$created_text .= $log['created_exams'] . ' exam(s)';
			}
			if ( isset( $log['created_students'] ) && $log['created_students'] > 0 ) {
				if ( $created_text ) $created_text .= ', ';
				$created_text .= $log['created_students'] . ' student(s)';
			}
			if ( ! $created_text ) {
				$created_text = '—';
			}
			
			$status = $log['errors'] > 0 ? 'Partial' : 'Success';
			
			echo '<tr>';
			echo '<td>' . esc_html( date( 'M j, g:i A', strtotime( $log['timestamp'] ) ) ) . '</td>';
			echo '<td>' . esc_html( $username ) . '</td>';
			echo '<td>' . intval( $log['total_rows'] ) . ' rows</td>';
			echo '<td>' . esc_html( $created_text ) . '</td>';
			echo '<td>' . $status . '</td>';
			echo '</tr>';
		}
		
		echo '</tbody></table>';
	}
}