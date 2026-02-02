<?php
defined( 'ABSPATH' ) || exit;

class EM_Term_Meta {
	
	public static function init() {
		add_action( 'em_term_add_form_fields', [ __CLASS__, 'add_term_fields' ] );
		add_action( 'em_term_edit_form_fields', [ __CLASS__, 'edit_term_fields' ] );
		add_action( 'created_em_term', [ __CLASS__, 'save_term_meta' ] );
		add_action( 'edited_em_term', [ __CLASS__, 'save_term_meta' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_scripts' ] );
		
		// Add columns to term list table
		add_filter( 'manage_edit-em_term_columns', [ __CLASS__, 'add_term_columns' ] );
		add_filter( 'manage_em_term_custom_column', [ __CLASS__, 'populate_term_columns' ], 10, 3 );
	}

	public static function enqueue_admin_scripts( $hook ) {
		// Only load on taxonomy pages
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}
		
		// Check if we're on the em_term taxonomy page
		$screen = get_current_screen();
		if ( ! $screen || $screen->taxonomy !== 'em_term' ) {
			return;
		}
		
		// Add inline CSS for better styling
		?>
		<style>
			.em-term-date-fields {
				margin-bottom: 15px;
			}
			.em-term-date-fields label {
				display: block;
				margin-bottom: 5px;
				font-weight: 600;
			}
			.em-term-date-fields input[type="date"] {
				width: 100%;
				max-width: 300px;
			}
			.em-term-help-text {
				font-size: 12px;
				color: #666;
				margin-top: 5px;
			}
		</style>
		<?php
	}

	public static function add_term_fields() {
		?>
		<div class="form-field em-term-date-fields">
			<label for="em_term_start_date">Start Date *</label>
			<input type="date" name="em_term_start_date" id="em_term_start_date" required>
			<p class="em-term-help-text">The date when this academic term begins.</p>
		</div>
		<div class="form-field em-term-date-fields">
			<label for="em_term_end_date">End Date *</label>
			<input type="date" name="em_term_end_date" id="em_term_end_date" required>
			<p class="em-term-help-text">The date when this academic term ends.</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			// Validate that end date is after start date
			$('#em_term_start_date, #em_term_end_date').on('change', function() {
				var startDate = $('#em_term_start_date').val();
				var endDate = $('#em_term_end_date').val();
				
				if (startDate && endDate && startDate > endDate) {
					alert('End date must be after start date');
					$('#em_term_end_date').val('');
				}
			});
		});
		</script>
		<?php
	}

	public static function edit_term_fields( $term ) {
		$start_date = get_term_meta( $term->term_id, 'em_term_start_date', true );
		$end_date   = get_term_meta( $term->term_id, 'em_term_end_date', true );
		?>
		<tr class="form-field em-term-date-fields">
			<th scope="row"><label for="em_term_start_date">Start Date *</label></th>
			<td>
				<input type="date" name="em_term_start_date" id="em_term_start_date"
					value="<?php echo esc_attr( $start_date ); ?>" required>
				<p class="description">The date when this academic term begins.</p>
			</td>
		</tr>
		<tr class="form-field em-term-date-fields">
			<th scope="row"><label for="em_term_end_date">End Date *</label></th>
			<td>
				<input type="date" name="em_term_end_date" id="em_term_end_date"
					value="<?php echo esc_attr( $end_date ); ?>" required>
				<p class="description">The date when this academic term ends.</p>
			</td>
		</tr>
		<script>
		jQuery(document).ready(function($) {
			// Validate that end date is after start date
			$('#em_term_start_date, #em_term_end_date').on('change', function() {
				var startDate = $('#em_term_start_date').val();
				var endDate = $('#em_term_end_date').val();
				
				if (startDate && endDate && startDate > endDate) {
					alert('End date must be after start date');
					$('#em_term_end_date').val('');
				}
			});
		});
		</script>
		<?php
	}

	public static function save_term_meta( $term_id ) {
		// Security check - verify nonce (WordPress handles this for taxonomy forms)
		
		if ( isset( $_POST['em_term_start_date'] ) ) {
			$start_date = sanitize_text_field( $_POST['em_term_start_date'] );
			
			// Validate date format
			if ( self::validate_date( $start_date ) ) {
				update_term_meta( $term_id, 'em_term_start_date', $start_date );
			}
		}
		
		if ( isset( $_POST['em_term_end_date'] ) ) {
			$end_date = sanitize_text_field( $_POST['em_term_end_date'] );
			
			// Validate date format
			if ( self::validate_date( $end_date ) ) {
				update_term_meta( $term_id, 'em_term_end_date', $end_date );
			}
		}
		
		// Additional validation: ensure end date is after start date
		$start_date = get_term_meta( $term_id, 'em_term_start_date', true );
		$end_date   = get_term_meta( $term_id, 'em_term_end_date', true );
		
		if ( $start_date && $end_date && $start_date > $end_date ) {
			// Swap dates or delete end date
			delete_term_meta( $term_id, 'em_term_end_date' );
		}
	}

	/**
	 * Validate date format (YYYY-MM-DD)
	 */
	private static function validate_date( $date ) {
		if ( empty( $date ) ) {
			return false;
		}
		
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Add custom columns to term list table
	 */
	public static function add_term_columns( $columns ) {
		// Add new columns after 'name'
		$new_columns = [];
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( $key === 'name' ) {
				$new_columns['start_date'] = 'Start Date';
				$new_columns['end_date']   = 'End Date';
				$new_columns['status']     = 'Status';
			}
		}
		return $new_columns;
	}

	/**
	 * Populate custom columns in term list table
	 */
	public static function populate_term_columns( $content, $column_name, $term_id ) {
		switch ( $column_name ) {
			case 'start_date':
				$start_date = get_term_meta( $term_id, 'em_term_start_date', true );
				return $start_date ? date( 'M d, Y', strtotime( $start_date ) ) : '—';
				
			case 'end_date':
				$end_date = get_term_meta( $term_id, 'em_term_end_date', true );
				return $end_date ? date( 'M d, Y', strtotime( $end_date ) ) : '—';
				
			case 'status':
				return self::get_term_status( $term_id );
				
			default:
				return $content;
		}
	}

	/**
	 * Get term status (Upcoming, Active, or Completed)
	 */
	private static function get_term_status( $term_id ) {
		$start_date = get_term_meta( $term_id, 'em_term_start_date', true );
		$end_date   = get_term_meta( $term_id, 'em_term_end_date', true );
		
		if ( ! $start_date || ! $end_date ) {
			return '<span style="color: #999;">Not Set</span>';
		}
		
		$today = current_time( 'Y-m-d' );
		
		if ( $today < $start_date ) {
			return '<span style="color: #0073aa;"> Upcoming</span>';
		} elseif ( $today > $end_date ) {
			return '<span style="color: #999;"> Completed</span>';
		} else {
			return '<span style="color: #46b450;"> Active</span>';
		}
	}
}