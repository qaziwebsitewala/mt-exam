<?php
defined( 'ABSPATH' ) || exit;

class EM_Ajax_Exams {
	
	public static function init() {
		add_action( 'wp_ajax_em_get_exams', [ __CLASS__, 'get_exams' ] );
		add_action( 'wp_ajax_nopriv_em_get_exams', [ __CLASS__, 'get_exams' ] );
	}

	public static function get_exams() {
		// Verify nonce for security
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'em_get_exams_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token' ], 403 );
		}

		$page     = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? min( 50, max( 1, absint( $_GET['per_page'] ) ) ) : 10;
		$offset   = ( $page - 1 ) * $per_page;
		
		$now = current_time( 'Y-m-d H:i:s' );

		global $wpdb;
		$postmeta = $wpdb->postmeta;
		$posts    = $wpdb->posts;

		// Get total count for pagination
		$total_query = $wpdb->prepare(
			"
			SELECT COUNT(DISTINCT p.ID)
			FROM $posts p
			INNER JOIN $postmeta sm ON sm.post_id = p.ID AND sm.meta_key = 'em_start_datetime'
			INNER JOIN $postmeta em ON em.post_id = p.ID AND em.meta_key = 'em_end_datetime'
			WHERE p.post_type = 'em_exam'
			AND p.post_status = 'publish'
			"
		);
		$total_exams = $wpdb->get_var( $total_query );

		// Get exams with status
		$query = $wpdb->prepare(
			"
			SELECT 
				p.ID, 
				p.post_title,
				sm.meta_value AS start_datetime,
				em.meta_value AS end_datetime,
				CASE
					WHEN sm.meta_value <= %s AND em.meta_value >= %s THEN 'ongoing'
					WHEN sm.meta_value > %s THEN 'upcoming'
					ELSE 'past'
				END AS exam_status,
				CASE
					WHEN sm.meta_value <= %s AND em.meta_value >= %s THEN 1
					WHEN sm.meta_value > %s THEN 2
					ELSE 3
				END AS status_order
			FROM $posts p
			INNER JOIN $postmeta sm ON sm.post_id = p.ID AND sm.meta_key = 'em_start_datetime'
			INNER JOIN $postmeta em ON em.post_id = p.ID AND em.meta_key = 'em_end_datetime'
			WHERE p.post_type = 'em_exam'
			AND p.post_status = 'publish'
			ORDER BY status_order ASC, sm.meta_value ASC
			LIMIT %d OFFSET %d
			",
			$now, $now, $now,  // for exam_status CASE
			$now, $now, $now,  // for status_order CASE
			$per_page, 
			$offset
		);

		$results = $wpdb->get_results( $query );

		// Format the results with additional information
		$formatted_results = [];
		foreach ( $results as $exam ) {
			$formatted_results[] = [
				'id'             => intval( $exam->ID ),
				'title'          => $exam->post_title,
				'start_datetime' => $exam->start_datetime,
				'end_datetime'   => $exam->end_datetime,
				'status'         => $exam->exam_status,
				'start_formatted' => self::format_datetime( $exam->start_datetime ),
				'end_formatted'   => self::format_datetime( $exam->end_datetime ),
				'duration'        => self::calculate_duration( $exam->start_datetime, $exam->end_datetime ),
				'permalink'       => get_permalink( $exam->ID ),
			];
		}

		// Prepare response with pagination metadata
		$response = [
			'exams'        => $formatted_results,
			'pagination'   => [
				'page'         => $page,
				'per_page'     => $per_page,
				'total_exams'  => intval( $total_exams ),
				'total_pages'  => ceil( $total_exams / $per_page ),
				'has_next'     => $page < ceil( $total_exams / $per_page ),
				'has_previous' => $page > 1,
			],
			'timestamp'    => current_time( 'mysql' ),
		];

		wp_send_json_success( $response );
	}

	/**
	 * Format datetime for display
	 */
	private static function format_datetime( $datetime ) {
		if ( empty( $datetime ) ) {
			return '';
		}
		
		$timestamp = strtotime( $datetime );
		return date_i18n( 'M j, Y g:i A', $timestamp );
	}

	/**
	 * Calculate duration between two datetimes
	 */
	private static function calculate_duration( $start, $end ) {
		if ( empty( $start ) || empty( $end ) ) {
			return '';
		}

		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );
		$diff       = $end_time - $start_time;

		if ( $diff < 0 ) {
			return 'Invalid duration';
		}

		$hours   = floor( $diff / 3600 );
		$minutes = floor( ( $diff % 3600 ) / 60 );

		if ( $hours > 0 ) {
			return sprintf( '%d hour%s %d min%s', $hours, $hours > 1 ? 's' : '', $minutes, $minutes > 1 ? 's' : '' );
		} else {
			return sprintf( '%d minute%s', $minutes, $minutes > 1 ? 's' : '' );
		}
	}

	/**
	 * Helper function to generate nonce for frontend
	 */
	public static function get_nonce() {
		return wp_create_nonce( 'em_get_exams_nonce' );
	}
}