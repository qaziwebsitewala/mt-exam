<?php
/**
 * Plugin Name: Exam Management
 * Plugin URI: https://www.linkedin.com/in/hasan-qazi/
 * Description: A WordPress plugin for screening senior developer applicants with custom post types for students, exams, results.
 * Version: 1.0.0
 * Author: Hasan Alam Qazi
 * Author URI: https://www.linkedin.com/in/hasan-qazi/
 * License: GPL-2.0+
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'EM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load all classes
$includes = [
    'includes/class-em-term-meta.php',
    'includes/class-em-exam-meta.php',
    'includes/class-em-result-meta.php',
    'includes/class-em-ajax-exams.php',
    'includes/class-em-top-students-shortcode.php',
    'includes/class-em-bulk-result-import.php',
    'includes/class-em-student-report.php',
    'includes/class-em-student-report-pdf.php',
	'includes/class-em-exam-list-shortcode.php',
];

foreach ( $includes as $file ) {
    $filepath = EM_PLUGIN_DIR . $file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
}

// Custom Post Types and Taxonomy

class EM_CPT {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_cpts' ) );
        add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
        
        // Add ID column to all CPTs
        add_action( 'admin_init', array( __CLASS__, 'add_id_columns' ) );
    }

    public static function register_cpts() {
        // Students CPT
        register_post_type( 'em_student', array(
            'labels'       => array(
                'name'          => 'Students',
                'singular_name' => 'Student',
            ),
            'public'       => true,
            'capability_type' => 'post',
            'supports'     => array( 'title', 'editor' ),
            'menu_icon'    => 'dashicons-groups',
            'show_in_rest' => true,
        ) );

        // Subjects CPT
        register_post_type( 'em_subject', array(
            'labels'       => array(
                'name'          => 'Subjects',
                'singular_name' => 'Subject',
            ),
            'public'       => true,
            'capability_type' => 'post',
            'supports'     => array( 'title' ),
            'menu_icon'    => 'dashicons-book-alt',
            'show_in_rest' => true,
        ) );

        // Exams CPT
        register_post_type( 'em_exam', array(
            'labels'       => array(
                'name'          => 'Exams',
                'singular_name' => 'Exam',
            ),
            'public'       => true,
            'capability_type' => 'post',
            'supports'     => array( 'title', 'editor' ),
            'menu_icon'    => 'dashicons-book',
            'show_in_rest' => true,
        ) );

        // Results CPT
        register_post_type( 'em_result', array(
            'labels'       => array(
                'name'          => 'Results',
                'singular_name' => 'Result',
            ),
            'public'       => true,
            'capability_type' => 'post',
            'supports'     => array( 'title' ),
            'menu_icon'    => 'dashicons-performance',
            'show_in_rest' => true,
        ) );
    }

    public static function register_taxonomy() {
        // Terms Taxonomy
        register_taxonomy( 'em_term', array( 'em_exam' ), array(
            'labels'       => array(
                'name'          => 'Terms',
                'singular_name' => 'Term',
            ),
            'public'       => true,
            'hierarchical' => false,
            'show_ui'      => true,
            'show_in_rest' => true,
        ) );
    }

    /**
     * Add ID columns to all custom post types
     */
    public static function add_id_columns() {
        $post_types = array( 'em_student', 'em_subject', 'em_exam', 'em_result' );
        
        foreach ( $post_types as $post_type ) {
            // Add column
            add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_id_column' ) );
            
            // Populate column
            add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'populate_id_column' ), 10, 2 );
            
            // Make column sortable
            add_filter( "manage_edit-{$post_type}_sortable_columns", array( __CLASS__, 'make_id_column_sortable' ) );
        }
        
        // Enqueue assets
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_id_column_assets' ) );
        
        // Handle sorting
        add_action( 'pre_get_posts', array( __CLASS__, 'id_column_orderby' ) );
    }

    /**
     * Enqueue CSS and JS for ID column
     */
    public static function enqueue_id_column_assets( $hook ) {
        // Only load on post list pages
        if ( 'edit.php' !== $hook ) {
            return;
        }

        $screen = get_current_screen();
        
        // Only load on our CPT pages
        if ( ! $screen || ! in_array( $screen->post_type, array( 'em_student', 'em_subject', 'em_exam', 'em_result' ) ) ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'em-id-column',
            EM_PLUGIN_URL . 'assets/css/admin-id-column.css',
            array(),
            '1.0.0'
        );

        // Enqueue JS
        wp_enqueue_script(
            'em-id-column',
            EM_PLUGIN_URL . 'assets/js/admin-id-column.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );
    }

    /**
     * Add ID column to the post list table
     */
    public static function add_id_column( $columns ) {
        $new_columns = array();
        
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            
            if ( $key === 'cb' ) {
                $new_columns['em_id'] = 'ID';
            }
        }
        
        return $new_columns;
    }

    /**
     * Populate the ID column with copy functionality
     */
    public static function populate_id_column( $column, $post_id ) {
        if ( $column === 'em_id' ) {
            ?>
            <span class="em-id-wrapper" title="Click to copy ID">
                <strong class="em-id-badge" data-id="<?php echo esc_attr( $post_id ); ?>">
                    <?php echo esc_html( $post_id ); ?>
                </strong>
                <span class="em-id-copied" style="display: none;">✓ Copied!</span>
            </span>
            <?php
        }
    }

    /**
     * Make ID column sortable
     */
    public static function make_id_column_sortable( $columns ) {
        $columns['em_id'] = 'ID';
        return $columns;
    }

    /**
     * Handle sorting by ID
     */
    public static function id_column_orderby( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $orderby = $query->get( 'orderby' );
        
        if ( 'ID' === $orderby ) {
            $query->set( 'orderby', 'ID' );
        }
    }
}

// Plugin initialization
function em_init() {
    // Register CPTs and Taxonomy
    EM_CPT::init();

    // Initialize non-admin features
    if ( class_exists( 'EM_Term_Meta' ) ) {
        EM_Term_Meta::init();
    }
    if ( class_exists( 'EM_Exam_Meta' ) ) {
        EM_Exam_Meta::init();
    }
    if ( class_exists( 'EM_Result_Meta' ) ) {
        EM_Result_Meta::init();
    }
    if ( class_exists( 'EM_Ajax_Exams' ) ) {
        EM_Ajax_Exams::init();
    }
    if ( class_exists( 'EM_Top_Students_Shortcode' ) ) {
        EM_Top_Students_Shortcode::init();
    }
	if ( class_exists( 'EM_Exam_List_Shortcode' ) ) {
    EM_Exam_List_Shortcode::init();
	}

    // Initialize admin-only features
    if ( is_admin() ) {
        if ( class_exists( 'EM_Bulk_Result_Import' ) ) {
            EM_Bulk_Result_Import::init();
        }
        if ( class_exists( 'EM_Student_Report' ) ) {
            EM_Student_Report::init();
        }
        if ( class_exists( 'EM_Student_Report_PDF' ) ) {
            EM_Student_Report_PDF::init();
        }
    }
}
add_action( 'plugins_loaded', 'em_init' );

// Activation hook - flush rewrite rules
register_activation_hook( __FILE__, 'em_activation' );
function em_activation() {
    EM_CPT::register_cpts();
    EM_CPT::register_taxonomy();
    flush_rewrite_rules();
}

// Deactivation hook - flush rewrite rules
register_deactivation_hook( __FILE__, 'em_deactivation' );
function em_deactivation() {
    flush_rewrite_rules();
}