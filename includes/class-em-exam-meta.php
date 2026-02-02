<?php
defined( 'ABSPATH' ) || exit;

class EM_Exam_Meta {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post_em_exam', [ __CLASS__, 'save_meta' ] );
	}

	public static function register_meta_box() {
		add_meta_box(
			'em_exam_details',
			'Exam Details',
			[ __CLASS__, 'render_meta_box' ],
			'em_exam',
			'normal',
			'default'
		);
	}

	public static function render_meta_box( $post ) {

		wp_nonce_field( 'em_exam_meta_nonce', 'em_exam_meta_nonce_field' );

		$start_datetime = get_post_meta( $post->ID, 'em_start_datetime', true );
		$end_datetime   = get_post_meta( $post->ID, 'em_end_datetime', true );
		$subject_id     = get_post_meta( $post->ID, 'em_subject_id', true );

		$subjects = get_posts( [
			'post_type'      => 'em_subject',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		] );
		?>

		<p>
			<label for="em_start_datetime"><strong>Start Date & Time</strong></label><br>
			<input type="datetime-local"
				   id="em_start_datetime"
				   name="em_start_datetime"
				   value="<?php echo esc_attr( $start_datetime ); ?>">
		</p>

		<p>
			<label for="em_end_datetime"><strong>End Date & Time</strong></label><br>
			<input type="datetime-local"
				   id="em_end_datetime"
				   name="em_end_datetime"
				   value="<?php echo esc_attr( $end_datetime ); ?>">
		</p>

		<p>
			<label for="em_subject_id"><strong>Subject</strong></label><br>
			<select name="em_subject_id" id="em_subject_id">
				<option value="">— Select Subject —</option>
				<?php foreach ( $subjects as $subject ) : ?>
					<option value="<?php echo esc_attr( $subject->ID ); ?>"
						<?php selected( $subject_id, $subject->ID ); ?>>
						<?php echo esc_html( $subject->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p style="color:#666;">
			<strong>Academic Term:</strong> Assign using the <em>Terms</em> box on the right.
		</p>

		<?php
	}

	public static function save_meta( $post_id ) {

		if (
			! isset( $_POST['em_exam_meta_nonce_field'] ) ||
			! wp_verify_nonce( $_POST['em_exam_meta_nonce_field'], 'em_exam_meta_nonce' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['em_start_datetime'] ) ) {
			update_post_meta(
				$post_id,
				'em_start_datetime',
				sanitize_text_field( $_POST['em_start_datetime'] )
			);
		}

		if ( isset( $_POST['em_end_datetime'] ) ) {
			update_post_meta(
				$post_id,
				'em_end_datetime',
				sanitize_text_field( $_POST['em_end_datetime'] )
			);
		}

		if ( isset( $_POST['em_subject_id'] ) ) {
			update_post_meta(
				$post_id,
				'em_subject_id',
				absint( $_POST['em_subject_id'] )
			);
		}
	}
}
