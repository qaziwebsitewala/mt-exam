<?php
defined( 'ABSPATH' ) || exit;

class EM_Result_Meta {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post_em_result', [ __CLASS__, 'save_meta' ] );
	}

	public static function register_meta_box() {
		add_meta_box(
			'em_result_details',
			'Result Details',
			[ __CLASS__, 'render_meta_box' ],
			'em_result',
			'normal',
			'default'
		);
	}

	public static function render_meta_box( $post ) {

		wp_nonce_field( 'em_result_meta_nonce', 'em_result_meta_nonce_field' );

		$selected_exam   = get_post_meta( $post->ID, 'em_exam_id', true );
		$saved_marks     = get_post_meta( $post->ID, 'em_student_marks', true );
		$saved_marks     = is_array( $saved_marks ) ? $saved_marks : [];

		$exams = get_posts( [
			'post_type'      => 'em_exam',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		] );

		$students = get_posts( [
			'post_type'      => 'em_student',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		] );
		?>

		<p>
			<label for="em_exam_id"><strong>Select Exam</strong></label><br>
			<select name="em_exam_id" id="em_exam_id">
				<option value="">— Select Exam —</option>
				<?php foreach ( $exams as $exam ) : ?>
					<option value="<?php echo esc_attr( $exam->ID ); ?>"
						<?php selected( $selected_exam, $exam->ID ); ?>>
						<?php echo esc_html( $exam->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<hr>

		<h4>Student Marks (out of 100)</h4>

		<?php if ( empty( $students ) ) : ?>
			<p>No students found.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Student</th>
						<th>Marks</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $students as $student ) : ?>
					<tr>
						<td><?php echo esc_html( $student->post_title ); ?></td>
						<td>
							<input type="number"
								   name="em_student_marks[<?php echo esc_attr( $student->ID ); ?>]"
								   value="<?php echo esc_attr( $saved_marks[ $student->ID ] ?? '' ); ?>"
								   min="0"
								   max="100"
								   step="1">
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
	}

	public static function save_meta( $post_id ) {

		if (
			! isset( $_POST['em_result_meta_nonce_field'] ) ||
			! wp_verify_nonce( $_POST['em_result_meta_nonce_field'], 'em_result_meta_nonce' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['em_exam_id'] ) ) {
			update_post_meta(
				$post_id,
				'em_exam_id',
				absint( $_POST['em_exam_id'] )
			);
		}

		if ( isset( $_POST['em_student_marks'] ) && is_array( $_POST['em_student_marks'] ) ) {

			$clean_marks = [];

			foreach ( $_POST['em_student_marks'] as $student_id => $marks ) {
				if ( $marks === '' ) {
					continue;
				}

				$clean_marks[ absint( $student_id ) ] = min( 100, max( 0, intval( $marks ) ) );
			}

			update_post_meta( $post_id, 'em_student_marks', $clean_marks );
		}
	}
}
