<?php
defined( 'ABSPATH' ) || exit;

class EM_Exam_List_Shortcode {
	
	public static function init() {
		add_shortcode( 'em_exam_list', [ __CLASS__, 'render_exam_list' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	public static function enqueue_scripts() {
		if ( ! is_singular() && ! is_page() ) {
			return;
		}

		// Only enqueue if shortcode is present
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'em_exam_list' ) ) {
			return;
		}

		wp_enqueue_style( 
			'em-exam-list', 
			EM_PLUGIN_URL . 'assets/css/exam-list.css', 
			[], 
			'1.0.0' 
		);

		wp_enqueue_script( 
			'em-exam-list', 
			EM_PLUGIN_URL . 'assets/js/exam-list.js', 
			[ 'jquery' ], 
			'1.0.0', 
			true 
		);

		wp_localize_script( 'em-exam-list', 'emExamList', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => EM_Ajax_Exams::get_nonce(),
		] );
	}

	public static function render_exam_list( $atts ) {
		$atts = shortcode_atts( [
			'per_page' => 10,
		], $atts );

		ob_start();
		?>
		<div class="em-exam-list-wrapper" data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>">
			<div class="em-exam-list-loading">
				<p>Loading exams...</p>
			</div>
			<div class="em-exam-list-container" style="display: none;">
				<div class="em-exam-filters">
					<button class="em-filter-btn active" data-filter="all">All</button>
					<button class="em-filter-btn" data-filter="ongoing">Ongoing</button>
					<button class="em-filter-btn" data-filter="upcoming">Upcoming</button>
					<button class="em-filter-btn" data-filter="past">Past</button>
				</div>
				<div class="em-exam-list"></div>
				<div class="em-exam-pagination"></div>
			</div>
			<div class="em-exam-list-error" style="display: none;">
				<p>Failed to load exams. Please try again.</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}