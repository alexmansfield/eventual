<?php
/**
 * Plugin Name: Eventual
 * Plugin URI: https://modularwp.com/eventual
 * Description: A WordPress plugin for handling eventual functionality.
 * Version: 0.1.0
 * Author: Alex Mansfield
 * Author URI: https://alexmansfield.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eventual
 * Domain Path: /languages
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Main Eventual Plugin Class
 */
class Eventual_Plugin {
	private $config;
	
	/**
	 * Constructor
	 */
	public function __construct() {

		/**
         * Filters the plugin configuration options.
         *
         * @since 0.1.0
         *
         * @param array $config {
         *     Plugin configuration options.
         *
         *     @type string $date_format 		The date format to use for the event dates. Default 'datetime'.
         *     @type string $start_date_key 	The meta key for the start date meta field. Default 'event_start_date'.
         *     @type string $end_date_key 		The meta key for the end date meta field. Default 'event_end_date'.
         * }
         */
		$this->config = apply_filters('eventual_config', array(
			'date_format' => 'datetime',
			'start_date_key' => 'event_start_date',
			'end_date_key' => 'event_end_date',
			'calendar_link_to_events' => true,
			'navigation_links' => true
		));


		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		add_shortcode('eventual', array($this, 'eventual_shortcode'));
		
		// Add AJAX actions
		add_action('wp_ajax_eventual_get_month', array($this, 'ajax_get_month'));
		add_action('wp_ajax_nopriv_eventual_get_month', array($this, 'ajax_get_month'));
	}
	
	/**
	 * Get file modification time for cache busting
	 */
	private function get_file_version($file_path) {
		$full_path = plugin_dir_path(__FILE__) . $file_path;
		return file_exists($full_path) ? filemtime($full_path) : '1.0.0';
	}
	
	/**
	 * Enqueue scripts and styles for frontend
	 */
	public function enqueue_scripts() {
		// Enqueue CSS file
		wp_enqueue_style(
			'eventual-css',
			plugin_dir_url(__FILE__) . 'css/eventual.css',
			array(),
			$this->get_file_version('css/eventual.css')
		);
		
		// Enqueue JavaScript file
		wp_enqueue_script(
			'eventual-js',
			plugin_dir_url(__FILE__) . 'js/eventual.js',
			array(),
			$this->get_file_version('js/eventual.js'),
			true
		);
		
		// Localize script with AJAX URL
		wp_localize_script('eventual-js', 'eventual_ajax', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('eventual_nonce')
		));
	}
	
	/**
	 * Enqueue scripts and styles for admin
	 */
	public function enqueue_admin_scripts() {
		// Enqueue CSS file for admin
		wp_enqueue_style(
			'eventual-admin-css',
			plugin_dir_url(__FILE__) . 'css/eventual-admin.css',
			array(),
			$this->get_file_version('css/eventual-admin.css')
		);
		
		// Enqueue JavaScript file for admin
		wp_enqueue_script(
			'eventual-admin-js',
			plugin_dir_url(__FILE__) . 'js/eventual-admin.js',
			array(),
			$this->get_file_version('js/eventual-admin.js'),
			true
		);
	}
	
	/**
	 * Eventual shortcode handler
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string Shortcode output
	 */
	public function eventual_shortcode($atts) {
		// Parse attributes
		$atts = shortcode_atts(array(
			'timeframe' => '',
			'date' => ''
		), $atts, 'eventual');
		
		// Check for URL query parameters to override date
		$url_date = $this->get_date_from_url_params();
		if ($url_date) {
			$atts['date'] = $url_date;
		}
		
		// Convert date to DateTime object if provided
		$datetime = null;
		if ( ! empty( $atts['date'] ) ) {
			try {
				$datetime = new DateTime( $atts['date'] );
			} catch ( Exception $e ) {
				// If date parsing fails, return error message
				return esc_html( 'Invalid date format: ' . $atts['date'] );
			}
		}

		$output = '';

		if ( $datetime ) {
			// $output = $datetime->format( 'Y-m-d H:i:s' );
			switch ( $atts['timeframe'] ) {
				case 'month':
					$output = $this->display_month( $atts );
					break;
				default:
					$output = esc_html__( 'Invalid timeframe', 'eventual' );
			}
		}
		
		// Return the output
		return $output;
	}

	/**
	 * Get date from URL query parameters
	 * 
	 * @return string|false Date string in Y-m-d format or false if invalid
	 */
	private function get_date_from_url_params() {
		// Check if both 'eventual_month' and 'eventual_year' parameters are present
		$month_param = isset($_GET['eventual-m']) ? sanitize_text_field($_GET['eventual-m']) : '';
		$year_param = isset($_GET['eventual-y']) ? sanitize_text_field($_GET['eventual-y']) : '';
		
		// If either parameter is missing, return false
		if (empty($month_param) || empty($year_param)) {
			return false;
		}
		
		// Validate year parameter (must be 4 digits)
		if (!preg_match('/^\d{4}$/', $year_param)) {
			return false;
		}
		
		// Validate month parameter (must be 2 digits, 01-12)
		if (!preg_match('/^(0[1-9]|1[0-2])$/', $month_param)) {
			return false;
		}
		
		// Construct date string in Y-m-d format (using first day of month)
		$date_string = $year_param . '-' . $month_param . '-01';
		
		// Validate the constructed date
		try {
			$test_date = new DateTime($date_string);
			return $date_string;
		} catch (Exception $e) {
			return false;
		}
	}

	

	/**
	 * Get event grid position
	 * 
	 * @param DateTime $event_start_date The event start date
	 * @param int $first_day_of_week The day of week for the first day of the month (0=Sunday, 6=Saturday)
	 * @param DateTime $event_end_date The event end date
	 * @return array Array of position arrays with 'grid_row', 'grid_column', and 'grid_column_span' keys
	 */
	public function get_event_position( $event_start_date, $first_day_of_week, $event_end_date ) {
		$positions = array();
		
		// Validate end date - if empty, invalid, or before start date, default to single day
		if ( ! $event_end_date || ! ( $event_end_date instanceof DateTime ) || $event_end_date < $event_start_date ) {
			$event_end_date = clone $event_start_date;
		}
		
		// Calculate start and end days of the month
		$start_day_of_month = (int) $event_start_date->format( 'j' );
		$end_day_of_month = (int) $event_end_date->format( 'j' );
		
		// Calculate which weeks the event spans
		$start_week = ceil( ( $start_day_of_month + $first_day_of_week ) / 7 );
		$end_week = ceil( ( $end_day_of_month + $first_day_of_week ) / 7 );
		
		// For each week the event spans
		for ( $week = $start_week; $week <= $end_week; $week++ ) {
			$grid_row = $week;
			
			// Calculate column start for this week
			if ( $week === $start_week ) {
				// First week - start from event start day
				$day_of_week = (int) $event_start_date->format( 'w' );
				$grid_column = $day_of_week + 1;
			} else {
				// Subsequent weeks - start from Sunday (column 1)
				$grid_column = 1;
			}
			
			// Calculate column span for this week
			if ( $week === $end_week ) {
				// Last week - end at event end day
				$day_of_week = (int) $event_end_date->format( 'w' );
				$grid_column_span = $day_of_week + 1;
			} else {
				// Middle weeks - span full week
				$grid_column_span = 7;
			}
			
			// Adjust span to account for start column
			$grid_column_span = $grid_column_span - $grid_column + 1;
			
			$positions[] = array(
				'grid_row' => $grid_row,
				'grid_column' => $grid_column,
				'grid_column_span' => $grid_column_span
			);
		}
		
		return $positions;
	}

	/**
	 * Get month content (for both full page and AJAX requests)
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function get_month_content( $atts ) {
		$date = new DateTime( $atts['date'] );

		$first_day_of_week = $date->format( 'w' );

		// Clone the start date to get last day info
		$last_day = clone $date;
		$last_day->modify( 'last day of this month' );
		$last_day_of_week = $last_day->format( 'w' );

		$end = new DateTime( $atts['date'] );
		$end->modify( 'last day of this month' )->modify( '+1 day' );

		$period = new DatePeriod( $date, new DateInterval( 'P1D' ), $end );
		$month_name = $date->format( 'F Y' );

		$period_array = iterator_to_array($period);
		$total_days = $first_day_of_week + count($period_array) + (6 - $last_day_of_week);
		$total_rows = ceil($total_days / 7);

		// Calculate month boundaries for events query
		$start_of_month = clone $date;
		$start_of_month->modify( 'first day of this month' );
		$end_of_month = clone $date;
		$end_of_month->modify( 'last day of this month' );

		// Get events for this month
		$events = $this->get_events_for_month( $start_of_month, $end_of_month );

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'templates/month-content.php';
		return ob_get_clean();
	}

	/**
	 * Display month template
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function display_month( $atts ) {
		$date = new DateTime( $atts['date'] );
		$month_name = $date->format( 'F' );
		$year = $date->format( 'Y' );
		
		// Calculate navigation URLs
		$prev_url = '';
		$next_url = '';
		
		if ( $this->config['navigation_links'] ) {
			// Check if we should use URL parameters for navigation calculation
			$url_date = $this->get_date_from_url_params();
			$nav_date = $url_date ? new DateTime( $url_date ) : $date;
			
			// Calculate previous month
			$prev_date = clone $nav_date;
			$prev_date->modify( '-1 month' );
			$prev_month = $prev_date->format( 'm' );
			$prev_year = $prev_date->format( 'Y' );
			$prev_url = add_query_arg( array(
				'eventual-m' => $prev_month,
				'eventual-y' => $prev_year
			), get_permalink() );
			
			// Calculate next month
			$next_date = clone $nav_date;
			$next_date->modify( '+1 month' );
			$next_month = $next_date->format( 'm' );
			$next_year = $next_date->format( 'Y' );
			$next_url = add_query_arg( array(
				'eventual-m' => $next_month,
				'eventual-y' => $next_year
			), get_permalink() );
		}
		
		ob_start();
		include plugin_dir_path( __FILE__ ) . 'templates/month-display.php';
		return ob_get_clean();
	}

	/**
	 * Get events for a specific date range
	 * 
	 * @param DateTime $start_date The start date
	 * @param DateTime $end_date The end date
	 * @return array Array of WP_Post objects
	 */
	public function get_events_for_month( $start_of_month, $end_of_month ) {

		$events_query = new WP_Query( array(
			'post_type' => 'event',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => $this->config['start_date_key'],
					'value' => array(
						$start_of_month->format( 'Y-m-d' ),
						$end_of_month->format( 'Y-m-d' )
					),
					'compare' => 'BETWEEN',
					'type' => 'DATE'
				)
			),
			'orderby' => 'meta_value',
			'meta_key' => $this->config['start_date_key'],
			'order' => 'ASC'
		) );

		
		return $events_query->posts;
	}
	
	/**
	 * AJAX handler for getting month content
	 */
	public function ajax_get_month() {
		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'eventual_nonce')) {
			wp_die('Security check failed');
		}
		
		// Get month and year from POST data
		$month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
		$year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
		
		// Validate parameters
		if (empty($month) || empty($year)) {
			wp_send_json_error('Missing month or year parameter');
		}
		
		if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
			wp_send_json_error('Invalid month format');
		}
		
		if (!preg_match('/^\d{4}$/', $year)) {
			wp_send_json_error('Invalid year format');
		}
		
		// Construct date string
		$date_string = $year . '-' . $month . '-01';
		
		// Validate the date
		try {
			$test_date = new DateTime($date_string);
		} catch (Exception $e) {
			wp_send_json_error('Invalid date');
		}
		
		// Create attributes array for get_month_content
		$atts = array(
			'date' => $date_string
		);
		
		// Get the month content
		$content = $this->get_month_content($atts);
		
		// Get month name for title
		$date_obj = new DateTime($date_string);
		$month_name = $date_obj->format('F');
		$year_formatted = $date_obj->format('Y');
		
		// Send JSON response
		wp_send_json_success(array(
			'content' => $content,
			'month' => $month,
			'year' => $year,
			'month_name' => $month_name,
			'year_formatted' => $year_formatted
		));
	}
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    new Eventual_Plugin();
});
