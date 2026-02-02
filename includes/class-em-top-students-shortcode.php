<?php
defined( 'ABSPATH' ) || exit;

class EM_Top_Students_Shortcode {

	public static function init() {
		add_shortcode( 'em_top_students', [ __CLASS__, 'render_shortcode' ] );
	}

	public static function render_shortcode() {

		$cache_key = 'em_top_students_cache';
		$output    = get_transient( $cache_key );

		if ( false !== $output ) {
			return $output;
		}

		$terms = get_terms( [
			'taxonomy'   => 'em_term',
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
		] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '<p>No terms found.</p>';
		}

		global $wpdb;
		$postmeta = $wpdb->postmeta;
		$posts    = $wpdb->posts;

		ob_start();

		foreach ( $terms as $term ) {

			$results = $wpdb->get_results( $wpdb->prepare(
				"
				SELECT pm.meta_value AS marks, pm2.meta_value AS student_id
				FROM $posts p
				INNER JOIN $postmeta pm ON pm.post_id = p.ID AND pm.meta_key = 'em_student_marks'
				INNER JOIN $postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'em_exam_id'
				INNER JOIN $wpdb->term_relationships tr ON tr.object_id = pm2.meta_value
				WHERE tr.term_taxonomy_id = %d
				AND p.post_type = 'em_result'
				",
				$term->term_taxonomy_id
			) );

			if ( empty( $results ) ) {
				continue;
			}

			$student_scores = [];

			foreach ( $results as $row ) {
				$marks = maybe_unserialize( $row->marks );

				if ( is_array( $marks ) ) {
					foreach ( $marks as $student_id => $score ) {
						$student_scores[ $student_id ] = max(
							$student_scores[ $student_id ] ?? 0,
							$score
						);
					}
				}
			}

			arsort( $student_scores );
			$top_students = array_slice( $student_scores, 0, 3, true );

			echo '<h3>' . esc_html( $term->name ) . '</h3>';
			echo '<ol>';

			foreach ( $top_students as $student_id => $score ) {
				echo '<li>' . esc_html( get_the_title( $student_id ) ) . ' – ' . intval( $score ) . '</li>';
			}

			echo '</ol>';
		}

		$output = ob_get_clean();

		set_transient( $cache_key, $output, HOUR_IN_SECONDS );

		return $output;
	}
}
