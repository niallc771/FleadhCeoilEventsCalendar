<?php

/*
Plugin Name: Fleadh Cheoil Event Calendar
Plugin URI: http://wordpress.org/extend/plugins/
Description: A fully localized calendar that allows authorized users to manage their own specific events in custom categories.
Version: 1.0
Author: Fleadh Cheoil na hEireann
License: GPL2 <-> ok to created new plugin
*/

// disallow direct access to file

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
	die('Sorry, but you cannot access this page directly.');
}

if (version_compare(PHP_VERSION, '5', '<')) {
	$out = "<div id='message' style='width:94%' class='message error'>";
	$out .= sprintf("<p><strong>Your PHP version is '%s'.<br>The Ajax Event Calendar WordPress plugin requires PHP 5 or higher.</strong></p><p>Ask your web host how to enable PHP 5 on your site.</p>", PHP_VERSION);
	$out .= "</div>";
	print $out;
}

define('AEC_MENU_POSITION', null);  //previously 30
define('AEC_VERSION', '1.0.4');
define('AEC_FILE', basename(__FILE__));
define('AEC_NAME', str_replace('.php', '', AEC_FILE));
define('AEC_PATH', plugin_dir_path(__FILE__));
define('AEC_URL', plugin_dir_url(__FILE__));
define('AEC_EVENT_TABLE', 'aec_event');                          // Database
define('AEC_ORGANISER_TABLE', 'aec_organiser');
define('AEC_VENUE_TABLE', 'aec_venue');
define('AEC_LOCATION_TABLE', 'aec_location');             // Cathal's Work
define('AEC_CATEGORY_TABLE', 'aec_event_category');
define('AEC_HOMEPAGE', 'http://wordpress.org/extend/plugins/' . AEC_NAME . '/');
define('AEC_WP_DATE_FORMAT', get_option('date_format'));
define('AEC_WP_TIME_FORMAT', get_option('time_format'));
define('AEC_DB_DATETIME_FORMAT', 'Y-m-d H:i:s');
define('AEC_LOCALE', substr(get_locale(), 0, 2));	// for javascript localization scripts

// if uncommented, overrides the location of the WP error log to the AEC plugin root
// @ini_set('error_log', AEC_PATH . 'aec_debug.log');

if (!class_exists('ajax_event_calendar')) {
	class ajax_event_calendar {
        private $calendar_name;
		private $required_fields  = array();
		private $shortcode_params = array();
		private $admin_messages	  = array();
		private $plugin_defaults  = array(
									'filter_label'		=> 'Show Type',
									'limit' 			=> '0',
									'show_weekends'		=> '1',
									'show_map_link'		=> '1',
									'menu' 				=> '1',
									'make_links'		=> '0',
									'popup_links'		=> '1',
									'step_interval'		=> '30',
									'addy_format'		=> '0',
									'scroll'			=> '1',
									'title' 			=> '2',
									'venue' 			=> '1',
									'address'			=> '2',
									'city' 				=> '2',
									'state' 			=> '2',
									'zip'				=> '2',
									'country'			=> '1',
									'link' 				=> '1',
									'description' 		=> '2',
									'contact' 			=> '2',
									'contact_info' 		=> '2',
									'accessible' 		=> '0',
									'rsvp' 				=> '0',
									'reset' 			=> '0'
								);

		function __construct() {
		    $this->calendar_name = "Fleadh Event Calendar"; // the name of the calendar
		        
			$this->get_venue_info();
			add_action('plugins_loaded', array($this, 'version_patches'));
		    add_action('init', array($this, 'localize_plugin'));
			add_action('admin_menu', array($this, 'render_admin_menu'));
			add_action('admin_init', array($this, 'admin_options_initialize'));
			add_action('wp_print_styles', array($this, 'calendar_styles'));
			add_action('wp_print_scripts', array($this, 'frontend_calendar_scripts'));
			add_action('delete_user', array($this, 'delete_events_by_user'));
			add_action('admin_notices', array(&$this, 'admin_alert'));
			
			// ajax hooks
			add_action('wp_ajax_nopriv_get_events', array($this, 'render_calendar_events'));
			add_action('wp_ajax_get_events', array($this, 'render_calendar_events'));
            
			add_action('wp_ajax_nopriv_get_event', array($this, 'render_frontend_modal'));
			add_action('wp_ajax_get_event', array($this, 'render_frontend_modal'));
			add_action('wp_ajax_admin_event', array($this, 'render_admin_modal'));
			add_action('wp_ajax_add_event', array($this, 'add_event'));
			add_action('wp_ajax_copy_event', array($this, 'copy_event'));
			add_action('wp_ajax_update_event', array($this, 'update_event'));
			add_action('wp_ajax_delete_event', array($this, 'delete_event'));
			add_action('wp_ajax_move_event', array($this, 'move_event'));
			add_action('wp_ajax_add_category', array($this, 'add_category'));
			add_action('wp_ajax_update_filter_label', array($this, 'update_filter_label'));
			add_action('wp_ajax_update_category', array($this, 'update_category'));
			add_action('wp_ajax_delete_category', array($this, 'confirm_delete_category'));
			add_action('wp_ajax_reassign_category', array($this, 'reassign_category'));
            
			// -----------------------------------------------------------------------------
			// VENUE AJAX HOOKS
			add_action('wp_ajax_add_venue', array($this, 'add_venue'));
			add_action('wp_ajax_update_venue', array($this, 'update_venue'));
			add_action('wp_ajax_delete_venue', array($this, 'delete_venue'));

			// wordpress overrides
			add_filter('manage_users_columns', array($this, 'add_events_column'));
			add_filter('manage_users_custom_column', array($this, 'add_events_column_data'), 10, 3);
			add_filter('plugin_action_links', array($this, 'settings_link'), 10, 2);
			add_filter('option_page_capability_aec_plugin_options', array($this, 'set_option_page_capability'));
            
           // add_filter('show_admin_bar','__return_false'); // when a calendar contributor logs in to the page the will not be able to see the wordpress menu bar at the top and cant access the back-end
            
			add_shortcode('calendar', array($this, 'render_shortcode_edit_calendar'));
			add_shortcode('eventlist', array($this, 'render_shortcode_eventlist'));

			// activate shortcode in text widgets
			add_filter('widget_text', 'shortcode_unautop');
			add_filter('widget_text', 'do_shortcode');
		}
		// added this method to get the venue_id and names from the database and place them in the storageto be used later to create the venue drop down in the add event form
		// see admin event.php for where it is used
        function get_venue_info()
		{
			global $wpdb;
			$table = $wpdb->prefix . AEC_VENUE_TABLE;
			$sql = "SELECT * FROM $table";
			$result = $wpdb->get_results($sql);
			update_option('venue_info', $result); /**/
		}
		
		function install() {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');           // creates the venue table below, deleted the oringal venue string with venue_id now
			global $wpdb;
			if ($wpdb->get_var('SHOW TABLES LIKE "' . $wpdb->prefix . AEC_EVENT_TABLE . '"') != $wpdb->prefix . AEC_EVENT_TABLE) {
				$sql = 'CREATE TABLE ' . $wpdb->prefix . AEC_EVENT_TABLE . ' (
						  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						  `user_id` int(10) unsigned NOT NULL,
						  `venue_id` bigint(20) unsigned NOT NULL,
						  `title` varchar(100) NOT NULL,
						  `start` datetime NOT NULL,
						  `end` datetime NOT NULL,
						  `repeat_freq` tinyint(4) unsigned DEFAULT 0,
						  `repeat_int` tinyint(4) unsigned DEFAULT 0,
						  `repeat_end` date DEFAULT NULL,
						  `allDay` tinyint(1) unsigned DEFAULT 0,
						  `category_id` tinyint(4) unsigned NOT NULL,
						  `description` varchar(2500) DEFAULT NULL,
						  `link` varchar(2000) DEFAULT NULL,
						  `address` varchar(100) DEFAULT NULL,
						  `city` varchar(50) DEFAULT NULL,
						  `state` varchar(50) DEFAULT NULL,
						  `zip` varchar(10) DEFAULT NULL,
						  `country` varchar(50) DEFAULT NULL,
						  `contact` varchar(50) DEFAULT NULL,
						  `contact_info` varchar(50) DEFAULT NULL,
						  `access` tinyint(1) unsigned DEFAULT 0,
						  `rsvp` tinyint(1) unsigned DEFAULT 0,
						  PRIMARY KEY (`id`),
						  KEY `venue_id` (`venue_id`)
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8';
				dbDelta($sql);
				
			}
			if ($wpdb->get_var('SHOW TABLES LIKE "' . $wpdb->prefix . AEC_ORGANISER_TABLE . '"') != $wpdb->prefix . AEC_ORGANISER_TABLE) {
				$sql = 'CREATE TABLE ' . $wpdb->prefix . AEC_ORGANISER_TABLE . ' (
						id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
						user_id INT(10) UNSIGNED NOT NULL,
						venue_id INT(10) NOT NULL,
						location VARCHAR(100) NOT NULL,
						address1 VARCHAR(100) NOT NULL,
						address2 VARCHAR(100) NOT NULL,
						city VARCHAR(50) NOT NULL,
						state VARCHAR(50) NOT NULL,
						zip VARCHAR(50) NOT NULL,
						phone INT(11) NOT NULL,
						fax VARCHAR(50) NOT NULL,
						website VARCHAR(50) NOT NULL)
						CHARSET=utf8;';
				dbDelta($sql);
			}
			if ($wpdb->get_var('SHOW TABLES LIKE "' . $wpdb->prefix . AEC_VENUE_TABLE . '"') != $wpdb->prefix . AEC_VENUE_TABLE) {
				$sql = 'CREATE TABLE ' . $wpdb->prefix . AEC_VENUE_TABLE . ' (
						venue_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
						venue_name VARCHAR(50) NOT NULL,
						street VARCHAR(100) NOT NULL,
						city VARCHAR(100) NOT NULL,
						state VARCHAR(100) NOT NULL,
						zip VARCHAR(100) NOT NULL,
						website VARCHAR(50) NOT NULL)
						CHARSET=utf8;
						##DEFAULT VENUES
						INSERT INTO ' . $wpdb->prefix . AEC_VENUE_TABLE . ' (venue_id, venue_name, street, city, state, zip, website) VALUES
						(NULL, "The Model", "", "", "", "", ""),
                        (NULL, "Summerhill College", "", "", "", "", ""),
						(NULL, "The Peace III Gig Rig", "Lower Quay Street", "Sligo", "Ireland", "", ""),
                        (NULL, "Hawks Well Theatre", "", "", "", "", ""),
                        (NULL, "Southern Hotel", "", "", "", "", ""),
				dbDelta($sql); // Add some values
			};';
            
            // Cathal's Work
            
            // LOCATION TABLE
            //if ($wpdb->get_var('SHOW TABLES LIKE "' . $wpdb->prefix . AEC_LOCATION_TABLE . '"') != $wpdb->prefix . AEC_LOCATION_TABLE) {
            //    $sql = 'CREATE TABLE ' . $wpdb->prefix . AEC_LOCATION_TABLE . ' (
            //    l_id INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            //    location_name VARCHAR(100) NOT NULL)
            //    CHARSET=utf8;
                
            //    ## DEFAULT LOCATIONS
                
            //    INSERT INTO ' . $wpdb->prefix . AEC_LOCATION_TABLE . ' (l_id, location_name)
            //    VALUES (NULL, "The Model"),
            //    (NULL, "Drumcliffe Church of Ireland"),
            //    (NULL, "Summerhill College"),
            //    (NULL, "Lower Quay Street to the Peace III Gig Rig"),
            //    (NULL, "Peace III Gig Rig"),
            //    (NULL, "Hawks Well Theatre"),
            //    (NULL, "Southern Hotel");';
            //    dbDelta($sql);
            }
            
            // added this constraint to create the foregin key between wp_aec_event.venue_id(foregin key) and wp_aec_venue.id(primary key))(name of the tables)
			$sql = 'ALTER TABLE `wp_aec_event` ADD CONSTRAINT `eventhasvenue` FOREIGN KEY (`venue_id`) REFERENCES `wp_aec_venue` (`venue_id`) ON DELETE CASCADE ON UPDATE CASCADE;';
			$wpdb->query($sql);
			if ($wpdb->get_var('SHOW TABLES LIKE "' . $wpdb->prefix . AEC_CATEGORY_TABLE . '"') != $wpdb->prefix . AEC_CATEGORY_TABLE) {
				$sql = 'CREATE TABLE ' . $wpdb->prefix . AEC_CATEGORY_TABLE . ' (
							id TINYINT(4) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
							category VARCHAR(25) NOT NULL,
							bgcolor CHAR(6) NOT NULL,
							fgcolor CHAR(6) NOT NULL
						) CHARSET=utf8;
						## DEFAULT CATEGORIES
						INSERT INTO ' . $wpdb->prefix . AEC_CATEGORY_TABLE . ' (id, category, bgcolor, fgcolor)
						VALUES 	(NULL, "Event", "517ed6", "FFFFFF"),
								(NULL, "Deadline", "e3686c", "FFFFFF"),
								(NULL, "Volunteer", "8fc9b0", "FFFFFF");';
				dbDelta($sql); // Add some values
			}

			// added a new role here so if someone signs up they are a contributor not an admin and the can only access the front-end
			add_role('calendar_contributor', 'Calendar Contributor', array(
				'read' 				=> 1,
				'aec_add_events' 	=> 1
			));

			// add calendar capabilities to administrator
			$role = get_role('administrator');
			$role->add_cap('aec_add_events');
			$role->add_cap('aec_manage_events');
			$role->add_cap('aec_manage_calendar');

		}
		
		function version_patches() {
			$plugin_updated = false;
			$options 		= get_option('aec_options');
			$current 		= get_option('aec_version');

			// initial and manual option initialization
			if (!is_array($options) || !isset($options['reset']) || $options['reset'] == '1') {
				update_option('aec_options', $this->plugin_defaults);
			}

			if (version_compare($current, AEC_VERSION, '==')) {
				return;
			}

			// set version for new plugin installations
			if ($current === false) {
				update_option('aec_version', AEC_VERSION);
				$current = get_option('aec_version');
				$plugin_updated = true;
			}

		// < 0.9.6
			if (version_compare($current, '0.9.6', '<')) {
				// set title as a required field
				$options 		= $this->insert_option('title', 2);

				// update database fields in event table
                
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				global $wpdb;
				$sql = 'ALTER TABLE ' . $wpdb->prefix . AEC_EVENT_TABLE . ' '
					. 'modify city VARCHAR(50),'
					. 'modify state CHAR(2),'
					. 'modify zip VARCHAR(10),'
					. 'modify contact VARCHAR(50),'
					. 'modify contact_info VARCHAR(50);';
				$wpdb->query($sql);
				$plugin_updated = true;
			}

		// < 0.9.8.1
			if (version_compare($current, '0.9.8.1', '<')) {
				// add calendar weekend toggle, and event detail map link toggle
				$options 		= $this->insert_option('show_weekends', 1);
				$options 		= $this->insert_option('show_map_link', 1);
				$plugin_updated = true;
			}

		// < 0.9.8.5
			if (version_compare($current, '0.9.8.5', '<')) {

				// update tables to UTF8 and modify category table
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				global $wpdb;
				$sqla = 'ALTER TABLE ' . $wpdb->prefix . AEC_EVENT_TABLE . ' CONVERT TO CHARACTER SET utf8;';
				$wpdb->query($sqla);
				$sqlb = 'ALTER TABLE ' . $wpdb->prefix . AEC_CATEGORY_TABLE . ' CONVERT TO CHARACTER SET utf8;';
				$wpdb->query($sqlb);
				$sqlc = 'ALTER TABLE ' . $wpdb->prefix . AEC_CATEGORY_TABLE . ' '
						. 'modify category VARCHAR(100) NOT NULL;';
				$wpdb->query($sqlc);

				// remove sidebar option
				$this->decommission_options(array('sidebar'));

				// remove retired administrator capability
				$role = get_role('administrator');
				$role->remove_cap('aec_run_reports');

				// remove retired role
				remove_role('blog_calendar_contributor');

				// remove outdated widget option
				delete_option('widget_upcoming_events');
				delete_option('widget_contributor_list');
				$plugin_updated = true;
			}

		// < 0.9.9.1
			if (version_compare($current, '0.9.9.1', '<')) {
				// update table to support Aussi state length
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				global $wpdb;
				$sql = 'ALTER TABLE ' . $wpdb->prefix . AEC_EVENT_TABLE
					 . ' MODIFY state CHAR(3);';
				$wpdb->query($sql);

				// add filter label, description link options and address format options
				$options 		= $this->insert_option('filter_label', 'Show Type');
				$options		= $this->insert_option('make_links', 0);
				$options		= $this->insert_option('popup_links', 1);
				$options		= $this->insert_option('addy_format', 0);
				$plugin_updated = true;
			}

		// < 1.0
			if (version_compare($current, '1.0', '<')) {
				// update table with repeating event fields, country, larger description, and larger state/province
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				global $wpdb;
				$sql = 'ALTER TABLE ' . $wpdb->prefix . AEC_EVENT_TABLE
					. ' MODIFY description VARCHAR(2500),'
					. ' MODIFY state VARCHAR(50),'
					. ' ADD country VARCHAR(50),'
					. ' ADD repeat_freq TINYINT(4) UNSIGNED DEFAULT 0,'
					. ' ADD repeat_int TINYINT(4) UNSIGNED DEFAULT 0,'
					. ' ADD repeat_end DATE;';
				$wpdb->query($sql);

				// add mousescroll control, country field, and step d options
				$options		= $this->insert_option('scroll', 1);
				$options		= $this->insert_option('country', 0);
				$options		= $this->insert_option('step_interval', 30);
				$plugin_updated = true;
			}
			
		// < 1.0.1
		if (version_compare($current, '1.0.1', '<')) {
			// update to accomodate long urls
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			global $wpdb;
			$sql = 'ALTER TABLE ' . $wpdb->prefix . AEC_EVENT_TABLE
				. ' MODIFY link VARCHAR(2000);';
			$wpdb->query($sql);
			$plugin_updated = true;
		}

			// update routines completed
			if ($plugin_updated) {

				// update plugin version
				update_option('aec_version', AEC_VERSION);

				// add sample event
				$_POST['event']['user_id'] = 0;	// system id
				$_POST['event']['title'] = "Ajax Event Calendar [v" . AEC_VERSION . "] successfully installed!";
				$_POST['event']['start_date'] = date('Y-m-d');
				$_POST['event']['start_time'] = '00:00:00';
				$_POST['event']['end_date'] = date('Y-m-d');
				$_POST['event']['end_time'] = '00:00:00';
				$_POST['event']['allDay'] = 1;
				$_POST['event']['repeat_freq'] = 0;
				$_POST['event']['repeat_int'] = 0;
				$_POST['event']['repeat_end'] = null;
				$_POST['event']['category_id'] = 1;
				$_POST['event']['description'] = "Ajax Event Calendar WordPress Plugin is a fully localized (including RTL language support) community calendar that allows authorized users to add, edit, copy, move, resize and delete events into custom categories.  Highly customized calendars can be added to pages, posts or text widgets using the <strong>[calendar]</strong> shortcode.  Similarly, a list view of events can be added using the <strong>[eventlist]</strong> shortcode.  Click on the event link to learn about available the shortcode options.";
				$_POST['event']['link'] = AEC_HOMEPAGE;
				$_POST['event']['venue_id'] = 1;
				$_POST['event']['address'] = '201 East Randolph Street';
				$_POST['event']['city'] = 'Chicago';
				$_POST['event']['state'] = 'Illinois';
				$_POST['event']['zip'] = '60601-6530';
				$_POST['event']['country'] = 'United States';
				$_POST['event']['contact'] = 'Eran Miller';
				$_POST['event']['contact_info'] = '123-123-1234';
				$_POST['event']['access'] = 1;
				$_POST['event']['rsvp'] = 0;

				// removes previously created release events and creates a new one
				$_POST['user_id'] = $_POST['event']['user_id'];
				$this->delete_events_by_user();
				$this->db_insert_event($this->cleanse_event_input($_POST['event']), false);
			}
		}

		function set_admin_alert($message) {
			array_push($this->admin_messages, print_r($message, true));
		}
		
		// display admin alerts
		function admin_alert() {
			$alerts = $this->admin_messages;
			if (count($alerts)) {
				foreach ($alerts as $alert) {
					printf('<div class="updated">%s</div>', $alert);
				}
			}
		}
		
	    function localize_plugin($page) {
			load_plugin_textdomain(AEC_NAME, false, AEC_NAME . '/locale/');
			$timezone = get_option('timezone_string');
			if ($timezone) {
				date_default_timezone_set($timezone);
			} else {
				// TODO: look into converting gmt_offset into timezone_string
				date_default_timezone_set('UTC');
			}
			
			// localization: date/time
			if (get_option('timezone_string')) {
				$this->timezone = get_option('timezone_string');
			} else {
				$this->set_admin_alert(sprintf('<p>Ajax Event Calendar %s.<br>%s... <a href="http://www.travelmath.com/time-zone/" target="_blank"><strong>%s</strong></a>.</p>
					<h3><a href="' . admin_url() . 'options-general.php">%s</a></h3>'
				, __('requires a city value for the Timezone setting', AEC_NAME)
				, __("Not all cities are listed. Can't find your city in the timezone dropdown?", AEC_NAME)
				, __('Search for your standardized timezone.', AEC_NAME)
				, __('Update Blog Settings', AEC_NAME)));
			}
			
			// get_magic_quotes_gpc issue
			if (get_magic_quotes_gpc()) {
				$this->set_admin_alert(sprintf('<p>%s %s <br>%s <a href="http://wordpress.org/support/topic/plugin-ajax-event-calendar-ajax-event-calendar-dont-like-the-apostrophes?replies=11#post-2259386" target="_blank"> <strong>%s</strong></a>. %s</p>'
				, __('Your server has PHP magic_quotes_gpc set to active.', AEC_NAME)
				, __('This produces formatting errors in the Ajax Event Calendar plugin.', AEC_NAME)
				, __('Learn how to disable this setting', AEC_NAME)
				, __('in this forum thread', AEC_NAME)
				, __('Ask your host provider for help.', AEC_NAME)));
			}

			// register scripts
			wp_register_script('fullcalendar', AEC_URL . 'js/jquery.fullcalendar.min.js', array('jquery'), '1.5.3', true);
			wp_register_script('addevent', AEC_URL . 'js/jquery.add.event.js', array('jquery'), '1.0', true);
			wp_register_script('simplemodal', AEC_URL . 'js/jquery.simplemodal.1.4.3.min.js', array('jquery'), '1.4.3', true);
			wp_register_script('jquery-ui-datepicker', AEC_URL . 'js/jquery.ui.datepicker.min.js', array('jquery-ui-core'), '1.8.5', true);
			wp_register_script('datepicker-locale', AEC_URL . 'js/i18n/jquery.ui.datepicker-' . substr(get_locale(), 0, 2) . '.js', array('jquery-ui-datepicker'), '1.8.5', true);
			wp_register_script('timepicker', AEC_URL . 'js/jquery.timePicker.min.js', array('jquery'), '5195', true);
			wp_register_script('growl', AEC_URL . 'js/jquery.jgrowl.min.js', array('jquery'), '1.2.5', true);
			wp_register_script('miniColors', AEC_URL . 'js/jquery.miniColors.min.js', array('jquery'), '0.1', true);
			wp_register_script('jeditable', AEC_URL . 'js/jquery.jeditable.min.js', array('jquery'), '1.7.1', true);
			wp_register_script('mousewheel', AEC_URL . 'js/jquery.mousewheel.min.js', array('jquery'), '3.0.6', true);
			wp_register_script('init_admin_calendar', AEC_URL . 'js/jquery.init_admin_calendar.js', array('jquery'), AEC_VERSION, true);
			wp_register_script('init_show_calendar', AEC_URL . 'js/jquery.init_show_calendar.js', array('jquery'), AEC_VERSION, true);
			wp_register_script('init_show_category', AEC_URL . '/js/jquery.init_admin_category.js', array('jquery'), AEC_VERSION, true);
			wp_register_script('tweet', 'http://platform.twitter.com/widgets.js', false, '1.0', true);
			wp_register_script('scriptjc', AEC_URL .'js/jquery.fullcalendar.min.js', false, '1.0', true);
			wp_register_script('scriptjc1', AEC_URL .'js/jquery.init_admin_category.js', false, '1.0', true);
			wp_register_script('scriptjc2', AEC_URL .'js/jquery.jeditable.min.js', false, '1.0', true);
			wp_register_script('scriptjc3', AEC_URL .'js/jquery.init_show_calendar.js', false, '1.0', true);
			wp_register_script('scriptjc4', AEC_URL .'js/jquery.simplemodal.1.4.3.min.js', false, '1.0', true);
			wp_register_script('scriptjc5', AEC_URL .'js/jquery.timePicker.min.js', false, '1.0', true);
			wp_register_script('scriptjc6', AEC_URL .'js/jquery.ui.datepicker.min.js', false, '1.0', true);
			wp_register_script('scriptjc7', AEC_URL .'js/jquery.jgrowl.min.js', false, '1.0', true);
			wp_register_script('scriptjc8', AEC_URL .'js/jquery.mousewheel.min.js', false, '1.0', true);
		//	wp_register_script('jquery-cal', AEC_URL .'/calendar/jquery/jquery.js', false, '1.10.2', true);
			wp_register_script('jquery-ui-cal', AEC_URL .'js/jquery.init_admin_calendar.js', false, '1.11.2', true);
			wp_register_script('facebook', 'http://connect.facebook.net/en_US/all.js#xfbml=1', false, '1.0', true);
            
			// This will be for venue
			// Register the venue scripts
			wp_register_script('init_show_venue', AEC_URL . '/js/jquery.init_admin_venue.js', array('jquery'), AEC_VERSION, true);

			// register styles
			wp_register_style('custom', AEC_URL . 'css/custom.css', null, AEC_VERSION);
			wp_register_style('custom_rtl', AEC_URL . 'css/custom_rtl.css', null, AEC_VERSION);
		//	wp_register_style('jq_ui_css-cal', AEC_URL . '/calendar/jquery/jquery-ui.css', null, '1.11.2');
			wp_register_style('jq_ui_css', AEC_URL . 'css/jquery-ui-1.8.16.custom.css', null, '1.8.16');
		}

		function render_admin_menu() {
  
			if (function_exists('add_options_page')) {
				// main menu page: calendar
				$page = add_menu_page('Ajax Event Calendar',  __('Calendar', AEC_NAME), 'aec_add_events', AEC_FILE, array($this, 'render_admin_calendar'), AEC_URL . 'css/images/em-icon-16.png', AEC_MENU_POSITION);

				// calendar admin specific scripts and styles
				add_action("admin_print_scripts-$page", array($this, 'admin_calendar_scripts'));
				add_action("admin_print_styles-$page", array($this, 'calendar_styles'));

				if (current_user_can('aec_manage_calendar')) {
					// sub menu page: category management
					$sub_category = add_submenu_page(AEC_FILE, 'Categories', __('Categories', AEC_NAME), 'aec_manage_calendar', 'aec_manage_categories', array($this, 'render_admin_category'));

					// category admin specific scripts and styles
					add_action("admin_print_scripts-$sub_category", array($this, 'admin_category_scripts'));
					add_action("admin_print_scripts-$sub_category", array($this, 'admin_social_scripts'));
					add_action("admin_print_styles-$sub_category", array($this, 'admin_category_styles'));

					// sub menu page: activity report
					$sub_report = add_submenu_page(AEC_FILE, 'Activity Report', __('Activity Report', AEC_NAME), 'aec_manage_calendar', 'aec_activity_report', array($this, 'render_activity_report'));
					add_action("admin_print_scripts-$sub_report", array($this, 'admin_social_scripts'));
					add_action("admin_print_styles-$sub_report", array($this, 'calendar_styles'));

					// sub menu page: calendar options
					$sub_options = add_submenu_page(AEC_FILE, 'Options', __('Options', AEC_NAME), 'aec_manage_calendar', 'aec_calendar_options', array($this, 'render_calendar_options'));
					add_action("admin_print_scripts-$sub_options", array($this, 'admin_social_scripts'));
					add_action("admin_print_styles-$sub_options", array($this, 'calendar_styles'));
                  
					// sub menu page: add venue
					$sub_venue = add_submenu_page(AEC_FILE, 'Add Venue', __('Add Venue', AEC_NAME), 'aec_manage_calendar', 'aec_manage_venues', array($this, 'render_admin_venue'));

					// venue admin scripts
					add_action("admin_print_scripts-$sub_venue", array($this, 'admin_venue_scripts'));
					add_action("admin_print_scripts-$sub_venue", array($this, 'admin_social_scripts'));
                    
					// sub menu page: addexport
					$sub_export = add_submenu_page(AEC_FILE, 'Export Venues', __('Export Venues', AEC_NAME), 'aec_manage_calendar', 'aec_manage_export', array($this, 'render_admin_export'));
                    
					// sub menu page: addexport
					$sub_export = add_submenu_page(AEC_FILE, 'Export Events', __('Export Events', AEC_NAME), 'aec_manage_calendar', 'aec_manage_export_events', array($this, 'render_admin_export_events'));
				}
			}
		}

		// STYLES, SCRIPTS & VIEWS
		function localized_variables() {
			$options = get_option('aec_options');

			// initialize required form fields
			foreach ($options as $option => $value) {
				if ($value == 2) {
					$this->add_required_field($option);
				}
			}

			$isEuroDate	= $this->parse_date_format(AEC_WP_DATE_FORMAT);
			$is24HrTime	= $this->parse_time_format(AEC_WP_TIME_FORMAT);

			return array(
				'is_rtl'					=> is_rtl(),
				'locale'					=> AEC_LOCALE,
				'start_of_week' 			=> get_option('start_of_week'),
				'step_interval'				=> intval($options['step_interval'], 10),
				'datepicker_format' 		=> ($isEuroDate) ? 'dd-mm-yy' : 'mm/dd/yy',		// jquery datepicker format
				'is24HrTime'				=> $is24HrTime,
				'show_weekends'				=> $options['show_weekends'],
				'agenda_time_format' 		=> ($is24HrTime) ? 'H:mm{ - H:mm}' : 'h:mmt{ - h:mmt}',
				'other_time_format' 		=> ($is24HrTime) ? 'H:mm' : 'h:mmt',
				'axis_time_format' 			=> ($is24HrTime) ? 'HH:mm' : 'h:mmt',
				'limit' 					=> $options['limit'],
				'today'						=> __('Today', AEC_NAME),
				'all_day'					=> __('All Day', AEC_NAME),
				'years'						=> __('Years', AEC_NAME),
				'year'						=> __('Year', AEC_NAME),
				'months'					=> __('Months', AEC_NAME),
				'month'						=> __('Month', AEC_NAME),
				'weeks'						=> __('Weeks', AEC_NAME),
				'week'						=> __('Week', AEC_NAME),
				'days'						=> __('Days', AEC_NAME),
				'day'						=> __('Day', AEC_NAME),
				'hours'						=> __('Hours', AEC_NAME),
				'hour'						=> __('Hour', AEC_NAME),
				'minutes'					=> __('Minutes', AEC_NAME),
				'minute'					=> __('Minute', AEC_NAME),
				'january' 					=> __('January', AEC_NAME),
				'february'					=> __('February', AEC_NAME),
				'march' 					=> __('March', AEC_NAME),
				'april' 					=> __('April', AEC_NAME),
				'may' 						=> __('May', AEC_NAME),
				'june' 						=> __('June', AEC_NAME),
				'july' 						=> __('July', AEC_NAME),
				'august' 					=> __('August', AEC_NAME),
				'september'					=> __('September', AEC_NAME),
				'october' 					=> __('October', AEC_NAME),
				'november' 					=> __('November', AEC_NAME),
				'december'					=> __('December', AEC_NAME),
				'jan' 						=> __('Jan', AEC_NAME),
				'feb' 						=> __('Feb', AEC_NAME),
				'mar' 						=> __('Mar', AEC_NAME),
				'apr' 						=> __('Apr', AEC_NAME),
				'may_short' 				=> _x('May', 'Three-letter month name abbreviation', AEC_NAME),
				'jun' 						=> __('Jun', AEC_NAME),
				'jul' 						=> __('Jul', AEC_NAME),
				'aug' 						=> __('Aug', AEC_NAME),
				'sep' 						=> __('Sep', AEC_NAME),
				'oct' 						=> __('Oct', AEC_NAME),
				'nov' 						=> __('Nov', AEC_NAME),
				'dec'						=> __('Dec', AEC_NAME),
				'sunday'					=> __('Sunday', AEC_NAME),
				'monday'					=> __('Monday', AEC_NAME),
				'tuesday'					=> __('Tuesday', AEC_NAME),
				'wednesday'					=> __('Wednesday', AEC_NAME),
				'thursday'					=> __('Thursday', AEC_NAME),
				'friday'					=> __('Friday', AEC_NAME),
				'saturday'					=> __('Saturday', AEC_NAME),
				'sun'						=> __('Sun', AEC_NAME),
				'mon'						=> __('Mon', AEC_NAME),
				'tue'						=> __('Tue', AEC_NAME),
				'wed'						=> __('Wed', AEC_NAME),
				'thu'						=> __('Thu', AEC_NAME),
				'fri'						=> __('Fri', AEC_NAME),
				'sat'						=> __('Sat', AEC_NAME),
				'close_event_form'			=> __('Close Event Form', AEC_NAME),
				'loading_event_form'		=> __('Loading Event Form...', AEC_NAME),
				'update_btn'				=> __('Update', AEC_NAME),
				'delete_btn'				=> __('Delete', AEC_NAME),
				'category_type'				=> __('Category type', AEC_NAME),
				'venue_type'				=> __('Venue ', AEC_NAME),
				'hide_all_notifications'	=> __('hide all notifications', AEC_NAME),
				'has_been_created'			=> __('has been created.', AEC_NAME),
				'has_been_modified'			=> __('has been modified.', AEC_NAME),
				'has_been_deleted'			=> __('has been deleted.', AEC_NAME),
				'add_event'					=> __('Add Event', AEC_NAME),
				'edit_event'				=> __('Edit Event', AEC_NAME),
				'delete_event'				=> __('Delete this event?', AEC_NAME),
				'loading'					=> __('Loading Events...', AEC_NAME),
				'category_filter_label'		=> __('Category filter label', AEC_NAME),
				'repeats_every'				=> __('Repeats Every', AEC_NAME),
				'until'						=> __('Until', AEC_NAME),
				'success'					=> __('Success!', AEC_NAME),
				'whoops'					=> __('Whoops!', AEC_NAME)
			);
		}

		function admin_calendar_variables() {
			$is_admin	= (current_user_can('aec_manage_events')) ? 1 : 0;
			$options	= get_option('aec_options');
			return array_merge($this->localized_variables(),
				array(
					'admin' 					=> $is_admin,
					'scroll'					=> $options['scroll'],
					'required_fields'			=> join(",", $this->get_required_fields()),
					'editable'					=> true,
					'error_no_rights'			=> __('You cannot edit events created by other users.', AEC_NAME),
					'error_past_create'			=> __('You cannot create events in the past.', AEC_NAME),
					'error_future_create'		=> __('You cannot create events more than a year in advance.', AEC_NAME),
					'error_past_resize'			=> __('You cannot resize expired events.', AEC_NAME),
					'error_past_move'			=> __('You cannot move events into the past.', AEC_NAME),
					'error_past_edit'			=> __('You cannot edit expired events.', AEC_NAME),
					'error_invalid_duration'	=> __('Invalid duration.', AEC_NAME)
				)
			);
		}

		function frontend_calendar_variables() {
			return array_merge($this->localized_variables(),
					array(
						'ajaxurl'               => admin_url('admin-ajax.php', is_ssl() ? 'https' : 'http'),
						'editable'				=> false
					)
			);
		}

		function admin_category_variables() {
			return array_merge($this->localized_variables(),
				array(
					'error_blank_category'		=> __('Category type cannot be a blank value.', AEC_NAME),
					'confirm_category_delete'	=> __('Are you sure you want to delete this category type?', AEC_NAME),
					'confirm_category_reassign'	=> __('Several events are associated with this category. Click OK to reassign these events to the default category.', AEC_NAME),
					'events_reassigned'			=> __('Events have been reassigned to the default category.', AEC_NAME)
				)
			);
		}
        
        function admin_venue_variables() {
			return array_merge($this->localized_variables(),
				array(
					'error_blank_venue'			=> __('Venue cannot be a blank value.', AEC_NAME),
					'confirm_venue_delete'		=> __('Are you sure you want to delete this Venue?', AEC_NAME),
					'error_venue'				=> __('Venue has not been deleted', AEC_NAME)

				)
			);
		}

		function calendar_styles() {
			// disabled: until I can conditionally load these scripts in text widgets with shortcodes
			// if ($this->has_shortcode('calendar') || $this->has_shortcode('eventlist')) {
			wp_enqueue_style('jq_ui_css');
			//wp_enqueue_style('jq_ui_css-cal');
			wp_enqueue_style('categories');
			wp_enqueue_style('custom');
			if (is_rtl()) {
				wp_enqueue_style('custom_rtl');
			}
			//}
		}

		function admin_category_styles() {
			wp_enqueue_style('categories');
			wp_enqueue_style('custom');
			if (is_rtl()) {
				wp_enqueue_style('custom_rtl');
			}
		}

		function admin_calendar_scripts() {
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('jquery-ui-resizable');
			wp_enqueue_script('jquery-ui-datepicker');
			if (AEC_LOCALE != 'en') {
				wp_enqueue_script('datepicker-locale');	// if not in English, load localization
			}
			wp_enqueue_script('fullcalendar');
			wp_enqueue_script('simplemodal');
			wp_enqueue_script('growl');
			wp_enqueue_script('timepicker');
			wp_enqueue_script('mousewheel');
			wp_enqueue_script('init_admin_calendar');
			wp_localize_script('init_admin_calendar', 'custom', $this->admin_calendar_variables());
		}

		function frontend_calendar_scripts() {
			if (is_admin()) {
				return;
			}
			wp_enqueue_script('jquery');
			wp_enqueue_script('fullcalendar');
			wp_enqueue_script('simplemodal');
			wp_enqueue_script('mousewheel');
			wp_enqueue_script('growl');
		//	wp_enqueue_script('jquery-cal');
			wp_enqueue_script('jquery-ui-cal');
			wp_enqueue_script('scriptjc');
			wp_enqueue_script('jquery-ui-datepicker');
			if (AEC_LOCALE != 'en') {
				wp_enqueue_script('datepicker-locale');	// if not in English, load localization
			}
			wp_enqueue_script('init_show_calendar');
			wp_localize_script('init_show_calendar', 'custom', $this->frontend_calendar_variables());
		}

		function admin_social_scripts() {
			wp_enqueue_script('tweet');
			wp_enqueue_script('facebook');
		}

		function admin_category_scripts() {
			wp_enqueue_script('jquery');
			wp_enqueue_script('growl');
			wp_enqueue_script('miniColors');
			wp_enqueue_script('jeditable');
			wp_enqueue_script('init_show_category');
			wp_localize_script('init_show_category', 'custom', $this->admin_category_variables());
		}

		// VENUE SCRIPTS
		function admin_venue_scripts() {
			wp_enqueue_script('jQuery');
			wp_enqueue_script('growl');
			wp_enqueue_script('jeditable');
			wp_enqueue_script('init_show_venue');
			wp_localize_script('init_show_venue', 'custom', $this->admin_venue_variables());
		}

		function render_aec_version() {
			return "<span class='aec-credit'>AEC v" . AEC_VERSION . "</span>\n";
		}
		
		function render_admin_calendar() {
			if (!current_user_can('aec_add_events')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			$options = get_option('aec_options');
			$out  = $this->generate_css();
			$out .= "<div class='wrap'>\n";
			$out .= "<a href='". AEC_HOMEPAGE . "' target='_blank'><span class='em-icon icon32'></span></a>\n";
			$out .= $this->render_category_filter($options);
			$out .= "<h2>" . __($this->calendar_name, AEC_NAME) . "</h2>\n";
			$out .= "<div id='aec-calendar'></div>\n";
			$out .= $this->render_aec_version();
			$out .= "</div>\n";
			echo $out;
		}
		
		
        function render_shortcode_edit_calendar($atts)
        {
            $out  = '<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>';
            $out .= '<div id="div1"></div><script type="text/javascript">';
            $out .= '$.ajax({url:"/wordpress/fleadh/s.html",success:function(result){$("#div1").html(result);}});';
            $out .= '</script>';
                
                
            echo $out;
        }
		function render_shortcode_calendar($atts) {
			extract(shortcode_atts(array(
				'categories'	=> '',
				'excluded'		=> 0,
				'filter'		=> 'all',
				'month'			=> date('m'),
				'year'			=> date('Y'),
				'view' 			=> 'month',
				'views' 		=> 'month,agendaWeek,agendaDay',
				'nav'			=> 'prev,next',
				'height'		=> '',
				'scroll'		=> 0,
				'mini'			=> 0
			), $atts));

			// shortcode input validation
			$isTrue 		= array("true", "1");
			$isFalse		= array("false", "0");
			$viewopts 		= array("month", "agendaWeek", "agendaDay", "basicWeek", "basicDay");
			$categories 	= $this->cleanse_shortcode_input($categories);
			$excluded		= ($categories && $excluded) ? $excluded : 0;

			if (in_array($filter, $isFalse, true)) {
				$filter = false;
			} elseif (intval($filter)) {
				$filter = 'cat' . intval($filter);
			} else {
				$filter = 'all';
			}
			// romo
			$month = intval($month)-1;
			
			if ($year != date('Y')) {
				$year = intval($year);
			}
			
			if (!in_array($view, $viewopts)) {
				$view = 'month';
			}

			if (!intval($height)) {
				$height = '';
			}
			
			if (in_array($scroll, $isTrue, true)) {
				$scroll = 1;
			}
			
			if (in_array($mini, $isTrue, true)) {
				$mini = 1;
				$views = '';
				$filter = 0;
				$height = 200;
			}

			$out  = $this->generate_css();
			
			// pass shortcode parameters to javascript
			$out .= "<script type='text/javascript'>\n";
			$out .= "var shortcode = {\n";
			$out .= "categories: '{$categories}',\n";
			$out .= "excluded: {$excluded},\n";
			$out .= "filter: '{$filter}',\n";
			$out .= "view: '{$view}',\n";
			$out .= "month: {$month},\n";
			$out .= "year: {$year},\n";
			$out .= "views: '{$views}',\n";
			$out .= "nav: '{$nav}',\n";
			$out .= "height: '{$height}',\n";
			$out .= "scroll: {$scroll},\n";
			$out .= "mini: {$mini}\n";
			$out .= "};\n";
			$out .= "</script>\n";

			$out .= "<div id='aec-container'>\n";
			$out .= "<div id='aec-header'>\n";
			$options = get_option('aec_options');
			if ($options['menu']) {
				$out .= "<div id='aec-menu'>\n";
				$out .= "<input type='button' id='myBtn' onclick='showEventForm()' value='Add Event'>";
				$out .= "</div>\n";
			}
			if ($filter) {
				$out .= $this->render_category_filter($options, $categories, $excluded);
			}
			$out .= "</div>\n";
			$out .= "<div id='aec-calendar'></div>\n";
			$out .= $this->render_aec_version();
			$out .= "</div>\n";
			return $out;
		}

		function render_shortcode_eventlist($atts) {
			// shortcode defaults
			extract(shortcode_atts(array(
				'categories'	=> false,
				'excluded'		=> false,
				'start'			=> date('Y-m-d'),
				'end'			=> date('Y-m-d', mktime(0, 0, 0, date("m"), date("d"), date("Y")+1)),
				'limit'			=> 4,
				'whitelabel'	=> false,
				'noresults'		=> __('No upcoming events', AEC_NAME)
			), $atts));

			// shortcode input validation
			$categories	 	= (isset($categories)) ? $this->cleanse_shortcode_input($categories) : false;
			$excluded		= ($categories && isset($excluded)) ? $excluded : false;
			$limit 			= ($limit == "none") ? false : intval($limit);
			$whitelabel 	= ($whitelabel == "true") ? true : false;
			$start			= date('Y-m-d', strtotime($start));
			$end			= date('Y-m-d', strtotime($end));

			$events			= $this->db_query_events($start, $end, $categories, $excluded, $limit);
			$events 		= $this->process_events($events, $start, $end, true);
			$out = $this->generate_css();
			$out .= "<ul class='aec-eventlist'>";
			if ($events) {
				$out .= $this->render_eventlist_events($events, $whitelabel, $limit);
			} else {
				$out .= "<li>{$noresults}</li>";
			}
			$out .= "</ul>\n";
			$out .= $this->render_aec_version();
			return $out;
		}

		function render_calendar_events() {
			$categories	 	= (isset($_POST['categories'])) ? $this->cleanse_shortcode_input($_POST['categories']) : false;
			$excluded		= ($categories && isset($_POST['excluded'])) ? $_POST['excluded'] : false;
			$start			= date('Y-m-d', $_POST['start']);
			$end			= date('Y-m-d', $_POST['end']);
			$readonly		= (isset($_POST['readonly'])) ? true : false;

			$events			= $this->db_query_events($start, $end, $categories, $excluded);
            
			$this->render_json($this->process_events($events, $start, $end, $readonly));
		}
        
		function render_eventlist_events($events, $whitelabel, $limit) {
			usort($events, array($this, 'array_compare_order'));
			$rows = $this->convert_array_to_object($events);
			$out = '';
			foreach ($rows as $count => $row) {
				//if ($count < $limit) {
				if (!$limit || $count < $limit) {
				
					// split database formatted datetime value into display formatted date and time values
					$row->start_date	= $this->convert_date($row->start, AEC_DB_DATETIME_FORMAT, AEC_WP_DATE_FORMAT);
					$row->start_time 	= $this->convert_date($row->start, AEC_DB_DATETIME_FORMAT, AEC_WP_TIME_FORMAT);
					$row->end_date 		= $this->convert_date($row->end, AEC_DB_DATETIME_FORMAT, AEC_WP_DATE_FORMAT);
					$row->end_time 		= $this->convert_date($row->end, AEC_DB_DATETIME_FORMAT, AEC_WP_TIME_FORMAT);

					// link to event
					$class = ($whitelabel) ? '' : ' ' . $row->className;
					$out .= '<li class="fc-event round5' . $class . '" onClick="jQuery.aecDialog({\'id\':' . $row->id . ',\'start\':\'' . $row->start . '\',\'end\':\'' . $row->end . '\'});">';
					$out .= '<span class="fc-event-time">';
					$out .= $row->start_date;

					if (!$row->allDay) {
						$out .= ' ' . $row->start_time;
					}
					$out .= '</span>';
					$out .= '<span class="fc-event-title">' . $this->render_i18n_data($row->title) . '</span>';
					$out .= '</li>';
				}
			}
			return $out;
		}
        // added code for Add event on the top left
		function render_category_filter($options, $categories=false, $excluded=false) { // renders the filter buttons at the top
			$categories = $this->db_query_categories($categories, $excluded);
			if (sizeof($categories) > 1) {
				$out .= "<ul id='aec-filter' style='width: 100%;'>\n";
				$out .= "<li style=\"display:none\">" . $this->render_i18n_data($options['filter_label']) . "</li>\n"; // the display:none lines up the two lines of categories together, this also lines up the add event button with the categories
				$out .= '<li style="float:left;position: relative;left: -10px;top:-1px;"><button onclick="addEvent();" class="round5 addEvent ui-state-defaul" data-handler="selectDay" data-event="click" data-month="11" data-year="2014">Add Event</button></li>';
/* Venue Drop*/ $out .= '<li>';
/* NC        */ $out .= '<select name="venue_id" style="width:160px; margin-right:25px; margin-left:15px" id="venue_id" onchange="fillAddress(this.value)"><option value="-1"> SELECT VENUE</option>';
                $out .= '<option value="1">Blue Lagoon</option><option value="2">Caf� Grappa</option><option value="3">Caf� Osta </option><option value="4">Caf� Slice The Model</option><option value="5">Calry Church</option><option value="6">Carpark in Strandhill</option><option value="7">Carraroe Church</option><option value="8">Clarence Hotel</option><option value="9">Clarion Canis Major</option><option value="10">Costa Cafe</option><option value="11">Drumcliffe Church of Ireland</option><option value="12">Gaiety Cinema</option><option value="13">Gilhooly Hall</option><option value="14">Grammar</option><option value="15">Grammar Calry Hall</option><option value="16">Grammar Gym</option><option value="17">Grammar Sports Hall</option><option value="18">Hamilton Gallery</option><option value="19">Hawk\'s Well Theatre</option><option value="20">Ionad na Gaeilge Sligo VEC</option><option value="21">IT A004</option><option value="22">IT A005</option><option value="23">IT A006</option><option value="24">IT B1040</option><option value="25">IT B1050</option><option value="26">IT B1058</option><option value="27">IT C1004</option><option value="28">IT C1006</option><option value="29">IT D1001</option><option value="30">IT D1001</option><option value="31">IT D1003</option><option value="32">IT E0011</option><option value="33">IT E0017</option><option value="34">Knocknarea Arena</option><option value="35">Knocknarea public car-park</option><option value="36">Lyons\' Cafe </option><option value="37">McLynns Pub</option><option value="38">Mercy College Gym</option><option value="39">Methodist Church</option><option value="40">O\'Connell Street</option><option value="41">Oifig na Fleidhe</option><option value="42">Peace III Gig Rig</option><option value="43">Radisson Blu Hotel</option><option value="44">Riverside</option><option value="45">Saint Marys GAA Club</option><option value="46">Shennanigans</option><option value="47">Sligo County Museum</option><option value="48">Sligo Gaol</option><option value="49">Sligo Park Hotel</option><option value="50">Sligo Town</option><option value="51">Sligo Youth Theatre</option><option value="52">Southern Hotel</option><option value="53">St Anne\'s Club</option><option value="54">St Edward\'s School</option><option value="55">St John\'s Sports Hall</option><option value="56">St Marys Catherdral</option><option value="57">Summerhill Secondary School</option><option value="58">Super Valu Car Park Grange</option><option value="59">The Cat &amp; The Moon</option><option value="60">The Crib</option><option value="61">The Glasshouse Hotel</option><option value="62">The Mercy College Gymnasium</option><option value="63">The Mercy Secondary School</option><option value="64">The Model</option><option value="65">The Southern Hotel</option><option value="66">Tourist Information Office</option><option value="67">Ursuline College</option><option value="68">Yeats Building</option><option value="69">Sligo IT Hume Hall</option>';
                $out .= '</select>';
				$out .= '</li>';
                foreach (array_reverse($categories) as $category) { // changes the categories back to normal format
					$out .= '<li><a class="round5 cat' . $category->id . '">' . $this->render_i18n_data($category->category) . '</a></li>' . "\n";
				}				
				$out .= '<li class="active"><a class="round5 all">' . __('All', AEC_NAME) . '</a></li>' . "\n";
                $out .= "</ul>\n";
				return $out;
			}
		}

		function render_admin_modal() {
			if (!current_user_can('aec_add_events')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			require_once AEC_PATH . 'inc/admin-event.php';
			exit();
		}

		function render_frontend_modal() {
			require_once AEC_PATH . 'inc/show-event.php';
		}

		function render_admin_category() {
			if (!current_user_can('aec_manage_calendar')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			$add  = $this->generate_css();
			$add .= $this->add_wrap(__('To add a new category, enter a category label and select a background color.', AEC_NAME), "<p>", "</p>");
			$add .= "<form id='aec-category-form'>\n";
			$add .= "<input type='hidden' id='fgcolor' name='fgcolor' class='fg ltr' value='#FFFFFF' />";
			$add .= "<p><input type='text' id='category' name='category' value='' /> ";
			$add .= "<input class='bg colors ltr' type='text' id='bgcolor' name='bgcolor' value='#005294' size='7' maxlength='7' autocomplete='off' /> ";
			$add .= $this->add_wrap(__('Add', AEC_NAME), "<button class='add button-primary'>", "</button></p>");
			$add .= "</form>\n";

			$aec_options = get_option('aec_options');
			$add .= $this->add_wrap(__('Category filter label', AEC_NAME), "<p>", "</p>");
			$add .= $this->add_wrap("<input type='text' name='filter_label' id='filter_label' value='" . esc_attr($this->render_i18n_data($aec_options['filter_label'])) . "' />", "<p>", "");
			$add .= $this->add_wrap(__('Update', AEC_NAME), "<button id='filter_update' class='filter-update button-secondary'>", "</button></p>");

			$add .= $this->add_wrap(__('Category IDs are displayed in color bubbles at the beginning of each row.' , AEC_NAME), "<p>", "<br/>");
			$add  .= $this->add_wrap(__('To change category color, click the color swatch or edit the hex color value and click Update.', AEC_NAME), "", "</p>");
			$add .= "<form id='aec-category-list'>\n";
			$categories = $this->db_query_categories();
			foreach ($categories as $category) {
				$add .= "<p id='id_{$category->id}'>\n";
				$add .= "<input type='hidden' name='fgcolor' value='#{$category->fgcolor}' class='fg ltr' />\n";
				$add .= "<span class='round5 cat{$category->id}'>{$category->id}</span> ";
				$add .= "<input type='text' name='category' value='" . $this->render_i18n_data($category->category) . "' class='edit' />\n";
				$add .= "<input type='text' name='bgcolor' size='7' maxlength='7' autocomplete='off' value='#{$category->bgcolor}' class='bg colors ltr' />\n";
				$add .= $this->add_wrap(__('Update', AEC_NAME), "<button class='update button-secondary'>", "</button>");
				if ($category->id > 1) {
					$add .= $this->add_wrap(__('Delete', AEC_NAME), "<button class='button-secondary delete'>", "</button>");
				}
				$add .= "</p>\n";
			}
			$add .= "</form>\n";
			$out = $this->add_panel(__('Manage Categories', AEC_NAME), $add);
			$top = "<a href='http://". AEC_HOMEPAGE . "' target='_blank'><span class='em-icon icon32'></span></a>\n";
			$top .= $this->add_wrap(__('Categories', AEC_NAME), "<h2>", "</h2>");

			$out = $this->add_wrap($out, "<div class='postbox-container' style='width:65%'>", "</div>");
			$out .= $this->add_sidebar();
			echo $this->add_wrap($out, "<div class='wrap'>{$top}", "</div>");
		}
        
        // VENUE ------------------------------------------------------------
		// Render add a new venue
		function render_admin_venue() {
			if (!current_user_can('aec_manage_calendar')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			$add = $this->generate_css_venue();
			$add .= $this->add_wrap(__('Add a New Venue', AEC_NAME), "<p>", "</p>");
			$add .= $this->add_wrap(__('To add a new venue, enter venue name and click "Add Venue"', AEC_NAME), "<p>", "</p>");
			$add .= "<form action='http://localhost/wordpress/wp-content/plugins/ajax-event-calendar-changes/location-output.php' method='post' id='aec-venue-form'>\n";
			// Venue
			$add .= $this->add_wrap(__('<p>Venue:', AEC_NAME));
			$add .= "<input type='text' id='venue-name' name='venue-name' value='' class='alignvenue' /> ";
			// Street
			$add .= $this->add_wrap(__('<p>Street:', AEC_NAME));
			$add .= "<input type='text' id='street-name' name='street-name' value='' class='alignvenue' /> ";
			// City
			$add .= $this->add_wrap(__('<p>City:', AEC_NAME));
			$add .= "<input type='text' id='city-name' name='city-name' value='' class='alignvenue'/> ";
			// State
			$add .= $this->add_wrap(__('<p>State:', AEC_NAME));
			$add .= "<input type='text' id='state-name' name='state-name' value='' class='alignvenue'/> ";
			// Zip
			$add .= $this->add_wrap(__('<p>Zip:', AEC_NAME));
			$add .= "<input type='text' id='zip-name' name='zip-name' value='' class='alignvenue'/> ";
			// Website
			$add .= $this->add_wrap(__('<p>Website:', AEC_NAME));
			$add .= "<input type='text' id='website-name' name='website-name' value='' class='alignvenue'/> ";
			// Add Venue 
			$add .= $this->add_wrap(__('Add Venue', AEC_NAME), "<br><br><button type='submit' id='updatebtn' class='add button-primary'>", "</button></p>");
			$add .= "</form>\n";
			echo($add);
			
			$add = "<form action='http://wordpress/wp-content/plugins/ajax-event-calendar-changes/inc/admin-export.php' method='post' id='aec-venue-list'>\n";
			$venues = $this->db_query_venues();
			foreach ($venues as $avenue) {
				$add .= "<p id='id_{$avenue->venue_id}'>\n";
				$add .= "<input type='text' name='venue-name' value='" . $this->render_i18n_data($avenue->venue_name) . "' class='edit' />\n";
				$add .= "<input type='text' name='venue-name' value='" . $this->render_i18n_data($avenue->street) . "' class='edit' />\n";
				$add .= "<input type='text' name='venue-name' value='" . $this->render_i18n_data($avenue->city) . "' class='edit' />\n";
				$add .= "<input type='text' name='venue-name' style='width:7%' value='" . $this->render_i18n_data($avenue->state) . "' class='edit' />\n";
				$add .= "<input type='text' name='venue-name' style='width:5%' value='" . $this->render_i18n_data($avenue->zip) . "' class='edit' />\n";
				$add .= "<input type='text' name='venue-name' value='" . $this->render_i18n_data($avenue->website) . "' class='edit' />\n";
				$add .= $this->add_wrap(__('Update', AEC_NAME), "<button type='submit' class='update button-secondary'>", "</button>");
				if ($avenue->venue_id >= 1) {
					$add .= $this->add_wrap(__('Delete', AEC_NAME), "<button type='submit' class='button-secondary delete'>", "</button>");
				}
				$add .= "</p>\n";
				
			}
			$add .= "</form>\n";
			$out = $this->add_panel(__('Manage Venues', AEC_NAME), $add);
			$top = "<a href='http://". AEC_HOMEPAGE . "' target='_blank'><span class='em-icon icon32'></span></a>\n";
			$top .= $this->add_wrap(__('Venues', AEC_NAME), "<h2>", "</h2>");

			$out = $this->add_wrap($out, "<div class='postbox-container' style='width:100%'>", "</div>");
			//$out .= $this->add_sidebar();
			echo $this->add_wrap($out, "<div class='wrap'>{$top}", "</div>");

		}

		function render_activity_report() {
			if (!current_user_can('aec_manage_calendar')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}

			$rows = $this->db_query_monthly_activity();
			$out  = $this->generate_css();
			if (count($rows)) {
				foreach ($rows as $row) {
					$out .= "<p class='round5 cat{$row->category_id}'>{$row->cnt} ";
					$out .= $this->render_i18n_data($row->category);
					$out .= " " . _n('Event', 'Events', $row->cnt, AEC_NAME);
					$out .= "</p>\n";
				}
				$out .= $this->add_wrap(__('NOTE: Each repeating event is counted once.', AEC_NAME), "<p><em>", "</em></p>");
			} else {
				$out .= $this->add_wrap(__('No events this month.', AEC_NAME), "<p><em>", "</em></p>");
			}

			$out = $this->add_panel(__('Number of events scheduled for the current month, by category:', AEC_NAME), $out);

			$top = "<a href='http://". AEC_HOMEPAGE . "' target='_blank'><span class='em-icon icon32'></span></a>\n";
			$top .= $this->add_wrap(__('Activity Report', AEC_NAME), "<h2>", "</h2>");

			$out = $this->add_wrap($out, "<div class='postbox-container' style='width:65%'>", "</div>");
			$out .= $this->add_sidebar();
			echo $this->add_wrap($out, "<div class='wrap'>{$top}", "</div>");
		}

		function render_calendar_options() {
			if (!current_user_can('aec_manage_calendar')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			require_once AEC_PATH . 'inc/admin-options.php';
		}
		
		// EXPORT VENUES---------------------------------------------
		function render_admin_export() {
			if (!current_user_can('aec_manage_calendar')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			
			//$add = $this->add_wrap(__('<h1>Export Events</h1>', AEC_NAME));
			//$add .= "<form action='http://localhost/wordpress/wp-content/plugins/ajax-event-calendar/inc/admin-export.php' method='post' id='aec-export-form'>\n";
			//$add .= $this->add_wrap(__('Create CSV File', AEC_NAME), "<br><br><button type='submit' class='add button-primary'>", "</button></p>");
			//$add .= "</form>\n";
			//echo($add);
			require_once AEC_PATH . '/inc/admin-export.php';
		}
        
        // EXPORT EVENTS ---------------------------------------------
		function render_admin_export_events() {
			if (!current_user_can('aec_manage_calendar')) {
				wp_die(__('You do not have sufficient permissions to access this page.', AEC_NAME));
			}
			
			//$add = $this->add_wrap(__('<h1>Export Events</h1>', AEC_NAME));
			//$add .= "<form action='http://localhost/wordpress/wp-content/plugins/ajax-event-calendar/inc/admin-export.php' method='post' id='aec-export-form'>\n";
			//$add .= $this->add_wrap(__('Create CSV File', AEC_NAME), "<br><br><button type='submit' class='add button-primary'>", "</button></p>");
			//$add .= "</form>\n";
			//echo($add);
			require_once AEC_PATH . '/inc/admin-export-events.php';
		}

		function process_events($events, $start, $end, $readonly) {
			if ($events) {
				$output	= array();
				foreach ($events as $event) {
					$event->view_start = $start;
					$event->view_end = $end;
					$event = $this->process_event($event, $readonly, true);
					if (is_array($event)) {
						foreach ($event as $repeat) {
							array_push($output, $repeat);
						}
					} else {
						array_push($output, $event);
					}
				}
				return $output;
			}
		}

		function process_event($input, $readonly=false, $queue=false) {
			$output = array();
			if ($repeats = $this->generate_repeating_event($input)) {
				foreach ($repeats as $repeat) {
					array_push($output, $this->generate_event($repeat, $this->return_auth($readonly), $queue));
				}
			} else {
				array_push($output, $this->generate_event($input, $this->return_auth($readonly), $queue));
			}

			if ($queue) {
				return $output;
			}
			$this->render_json($output);
		}

		function generate_repeating_event($event) {
			if ($event->repeat_freq) {
				$event_start	= strtotime($event->start);
				$event_end		= strtotime($event->end);
				$repeat_end		= strtotime($event->repeat_end) + 86400;
				$view_start		= strtotime($event->view_start);
				$view_end		= strtotime($event->view_end);
				$repeats		= array();

				while($event_start < $repeat_end) {
					if ($event_start >= $view_start && $event_start <= $view_end) {
						$event 		 	= clone $event;	// clone event details and override dates
						$event->start 	= date(AEC_DB_DATETIME_FORMAT, $event_start);
						$event->end 	= date(AEC_DB_DATETIME_FORMAT, $event_end);
						array_push($repeats, $event);
					}
					$event_start 	= $this->get_next_date($event_start, $event->repeat_freq, $event->repeat_int);
					$event_end 		= $this->get_next_date($event_end, $event->repeat_freq, $event->repeat_int);
				}
				return $repeats;
			}
			return false;
		}

		function get_next_date($date, $freq, $int) {
			if ($int == 0) return strtotime("+" . $freq . " days", $date);
			if ($int == 1) return strtotime("+" . $freq . " weeks", $date);
			if ($int == 2) return $this->get_next_month($date, $freq);
			if ($int == 3) return $this->get_next_year($date, $freq);
		}

		function get_next_month($date, $n = 1) {
			$newDate = strtotime("+{$n} months", $date);
			// adjustment for events that repeat on the 29th, 30th and 31st of a month
			if (date('j', $date) !== (date('j', $newDate))) {
				$newDate = strtotime("+" . $n+1 . " months", $date);
			}
			return $newDate;
		}

		function get_next_year($date, $n = 1) {
			$newDate = strtotime("+{$n} years", $date);
			// adjustment for events that repeat on february 29th
			if (date('j', $date) !== (date('j', $newDate))) {
				$newDate = strtotime("+" . $n+3 . " years", $date);
			}
			return $newDate;
		}

		function generate_event($input, $user_id) {
			$permissions = new stdClass();
			$permissions 	= $this->get_event_permissions($input, $user_id);
			$repeats		= ($input->repeat_freq) ? ' aec-repeating' : '';
			$output 		= array(
				'id'	 	=> $input->id,
				'title'  	=> $input->title,
				'start'		=> $input->start,
				'end'		=> $input->end,
				'allDay' 	=> ($input->allDay) ? true : false,
				'className'	=> "cat{$input->category_id}{$permissions->cssclass}{$repeats}",
				'editable'	=> $permissions->editable,
				'repeat_i'	=> $input->repeat_int,
				'repeat_f'	=> $input->repeat_freq,
				'repeat_e'	=> $input->repeat_end,
				'clash_list'	=> $input->clash_list,
				'clash_ids'	=> $input->clash_ids
			);
			return $output;
		}

		function get_event_permissions($input, $user_id) {
			$permissions = new stdClass();
			// users that are not logged-in see all events
			if ($user_id == -1) {
				$permissions->editable = false;
				$permissions->cssclass = '';
			} else {
				// users with aec_manage_events capability can edit all events
				// users with aec_add_events capability can edit events only they create
				if ($input->user_id == $user_id || $user_id == false) {
					$permissions->editable = true;
					$permissions->cssclass = '';
				} else {
					$permissions->editable = false;
					$permissions->cssclass = ' fc-event-disabled';
				}
			}
			return $permissions;
		}

		// OPTION CONTROLS
		function add_panel($name, $content) {
			$before = "<div class='metabox-holder'>\n";
			$before .= "<div class='postbox'>\n";
			$before .= "<h3 style='cursor:default'><span>{$name}</span></h3>\n";
			$before .= "<div class='inside'>\n";
			$after = str_repeat("</div>\n", 3);
			return $this->add_wrap($content, $before, $after);
		}

		function add_hidden_field($field, $value=false) {
			if ($value === false) {
				$aec_options 	= get_option('aec_options');
				$value 			= $aec_options[$field];
			}
			return "<input type='hidden' name='aec_options[{$field}]' value='{$value}' />\n";
		}

		function add_text_field($field, $label, $tip=false) {
			$aec_options = get_option('aec_options');
			$out = "<label for='{$field}' class='semi'>{$label}</label>\n";
			$out .= "<input type='text' name='aec_options[{$field}]' id='{$field}' value='" . esc_attr($aec_options[$field]) . "' />\n";
			if ($tip) {
				$out .= "<span class='description'>{$tip}</span>\n";
			}
			return $this->add_wrap($out, '<p class="hhh">', '</p>');
		}

		function add_checkbox_field($field, $label, $tip=false) {
			$aec_options = get_option('aec_options');
			$checked = ($aec_options[$field]) ? ' checked="checked" ' : ' ';
			$out  = $this->add_hidden_field($field, 0);
			$out .= "<input type='checkbox' name='aec_options[{$field}]' id='{$field}' value='1' {$checked} />\n";
			$out .= "<label for='{$field}' class='auto'>{$label}</label>\n";
			if ($tip) {
				$out .= "<span class='description'>{$tip}</span>\n";
			}

			return $this->add_wrap($out, '<p class="hhh">', '</p>');
		}

		function add_select_field($field, $label, $options, $values=false) {
			$aec_options = get_option('aec_options');
			$out = "<label for='{$field}'>{$label}</label>\n";
			$out .= "<select id='{$field}' class='semi' name='aec_options[{$field}]'>";
			foreach ($options as $option => $value) {
				if (is_numeric($value)) $option = $value;
				if ($values) {
					$option = $values[$option];
				}
				$out .= "<option value='{$option}' name='aec_options[{$field}]' " . selected($aec_options[$field], $option, false) . ">{$value}</option>\n";
			}
			$out .= "</select>\n";
			return $this->add_wrap($out, '<p class="hhh">', '</p>');
		}

		function add_wrap($content, $before='', $after='') {
			return "{$before}{$content}{$after}\n";
		}

		function add_sidebar() {
			$help = $this->add_wrap(__('Read about installation and options', AEC_NAME), "<p><a href='" . AEC_HOMEPAGE . "installation/' target='_blank'>", "</a>.</p>");
			$help .= $this->add_wrap(__('Review the FAQ', AEC_NAME), "<p><a href='" . AEC_HOMEPAGE . "faq/' target='_blank'>", "</a>.</p>");
			$help .= $this->add_wrap(__('Ask for help in the WordPress forum', AEC_NAME), "<p><a href='http://wordpress.org/tags/ajax-event-calendar-changes' target='_blank'>", "</a>.</p>");
			$help .= $this->add_wrap(__('Use the issue tracker', AEC_NAME), "<p><a href='http://code.google.com/p/wp-aec/issues/list' target='_blank'>", "</a> ");
			$help .= $this->add_wrap(__('to track and report bugs, feature requests, and to submit', AEC_NAME), "", "");
			$help .= $this->add_wrap(__('poEdit', AEC_NAME), " (<a href='http://weblogtoolscollection.com/archives/2007/08/27/localizing-a-wordpress-plugin-using-poedit/' target='_blank'>", "</a>)");
			$help .= $this->add_wrap(__('translation files', AEC_NAME), "", ".</p>");

			$like  = $this->add_wrap(__('Give the plugin a good rating', AEC_NAME), "<p><a href='" . AEC_HOMEPAGE . "' target='_blank'>", "</a>.</p>");
			$like .= $this->add_wrap(__('Make a donation in recognition of countless hours spent making this plugin', AEC_NAME), "<p>", "</p>");
			$like .= $this->add_wrap("<form style='text-align:center' action='https://www.paypal.com/cgi-bin/webscr' method='post'><input type='hidden' name='cmd' value='_s-xclick' /><input type='hidden' name='hosted_button_id' value='NCDKRE46K2NBA' /><input type='image' src='https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif' name='submit' alt='PayPal - The safer, easier way to pay online!' /><img alt='' border='0' src='https://www.paypalobjects.com/en_US/i/scr/pixel.gif' width='1' height='1' /></form>", "<p>", "</p>");

			$social = $this->add_wrap(__('Create a blog review or an instructional video about the Calendar and share it', AEC_NAME), "<p>", ".</p>");
			$social .= $this->add_wrap("<a href='http://twitter.com/share' class='twitter-share-button' data-url='" . AEC_HOMEPAGE . "' data-count='horizontal' data-via='Ajax Event Calendar WordPress Plugin'>Tweet</a>", "<p>", "</p>");
			$social .= $this->add_wrap("<fb:like href='" . AEC_HOMEPAGE . "'; layout='standard' show_faces='true' width='150' font='arial'></fb:like>", "<p>", "</p>");

			$sidebar  = $this->add_panel(__('Support', AEC_NAME), $help);
			$sidebar .= $this->add_panel(__('Share the Love', AEC_NAME), $like);
			$sidebar .= $this->add_panel(__('Spread the Word', AEC_NAME), $social);
			return $this->add_wrap($sidebar, "<div class='postbox-container' style='margin-left:10px; width:20%'>", "</div>");
		}

		// USER ACTIONS
		function add_event() {
			if (!isset($_POST['event'])) {
				return;
			}
			$this->db_insert_event($this->cleanse_event_input($_POST['event']), true);
		}

		function copy_event() {
			if (!isset($_POST['event'])) {
				return;
			}
			$input = $this->cleanse_event_input($_POST['event']);
			$input->user_id = $this->return_current_user_id();
			$this->db_insert_event($input, true);
		}

		function move_event() {
			if (!isset($_POST['event']))
				return;
			$input 				= $this->convert_array_to_object($_POST['event']);
			$offset				= $input->dayDelta*86400 + $input->minuteDelta*60;
			$event 				= $this->db_query_event($input->id);
			$twoHours			= 7200;

			$event->allDay		= $input->allDay;
			$event->end			= date(AEC_DB_DATETIME_FORMAT, strtotime($event->end) + $offset);
			$event->repeat_end	= date(AEC_DB_DATETIME_FORMAT, strtotime($event->repeat_end) + $offset);
			if ($input->resize) { $offset = 0; }
			$event->start 		= date(AEC_DB_DATETIME_FORMAT, strtotime($event->start) + $offset);
			// add an end date for events with a null end date
			if (isset($input->end)) {
			//if (!$input->allDay && !isset($input->end)) {
				$event->end		= date(AEC_DB_DATETIME_FORMAT, strtotime($event->start) + $twoHours);
			}
			
			$event->view_start	= $input->view_start;
			$event->view_end 	= $input->view_end;
			$this->db_update_event($event);
		}

		function update_event() {
			if (!isset($_POST['event'])) {
				return;
			}
			$this->db_update_event($this->cleanse_event_input($_POST['event']));
		}

		function delete_event() {
			if (!isset($_POST['id'])) {
				return;
			}
			$this->db_delete_event($_POST['id']);
		}

		function delete_events_by_user() {
			if (!isset($_POST['user_id'])) {
				return;
			}
			$this->db_delete_events_by_user($_POST['user_id']);
		}

		function update_filter_label() {
			if (!isset($_POST['label'])) {
				return;
			}
			$this->overwrite_option('filter_label', $this->cleanse_data_input($_POST['label']));
		}

		// outputs added/updated category as json
		function render_category($input) {
			$output = array(
				'id'	 	=> $input->id,
				'category'  => $input->category,
				'bgcolor'	=> $input->bgcolor,
				'fgcolor'	=> $input->fgcolor
			);
			$this->render_json($output);
		}

		function add_category() {
			if (!isset($_POST['category_data'])) {
				return;
			}
			$this->db_insert_category($this->cleanse_category_input($_POST['category_data']));
		}

		function update_category() {
			if (!isset($_POST['category_data'])) {
				return;
			}
			$this->db_update_category($this->cleanse_category_input($_POST['category_data']));
		}

		function reassign_category() {
			if (!isset($_POST['id'])) {
				return;
			}
			$this->db_reassign_category($_POST['id']);
		}

		function confirm_delete_category() {
			if (!isset($_POST['id'])) {
				return;
			}
			if ($this->db_query_events_by_category($_POST['id'])) {
				$this->render_json('false');
			}
			$this->db_delete_category($_POST['id']);
		}
        
        // VENUE ---------------------------------
		
		// Cathal's code
		
		
		// ADD 
		function add_venue() {
			if (!isset($_POST['venue_data'])) {
				return;
			}
			$this->db_insert_venue($_POST['venue_data']);
		}
		
		// UPDATE
		function update_venue() {
			if (!isset($_POST['venue_data'])) {
				return;
			}
			$this->db_update_venue($_POST['venue_data']);
		}
		
		// DELETE
		function delete_venue() {
			if (!isset($_POST['v_id'])) {
				return;
			}
			
			$this->db_delete_venue($_POST['v_id']);
		}
		
		// outputs added/updated category as json
		function render_venue($input) {
			$output = array(
				'v_id'	 			=> $input->v_id,
				'venue_name' 		=> $input->venue_name,
				'street'			=> $input->street,
				'city'				=> $input->city,
				'state'				=> $input->state,
				'zip'				=> $input->zip,
				'website'			=> $input->website
			);
			$this->render_json($output);
		}
		// --------------------------------


		// DATABASE QUERIES
		function db_query_monthly_activity() {
			global $wpdb;
			$result = $wpdb->get_results('SELECT COUNT(a.category_id) AS cnt, a.category_id, b.category FROM ' .
										$wpdb->prefix . AEC_EVENT_TABLE . ' AS a ' .
										'INNER JOIN ' . $wpdb->prefix . AEC_CATEGORY_TABLE . ' AS b ' .
										'ON a.category_id = b.id ' .
										'WHERE MONTH(start) = MONTH(NOW()) ' .
										'GROUP BY category_id ' .
										'ORDER BY cnt DESC;'
									);
			return $this->return_result($result);
		}

		function db_query_event($id) {
			global $wpdb;
			$result = $wpdb->get_row($wpdb->prepare('SELECT *
									FROM ' . $wpdb->prefix . AEC_EVENT_TABLE . '
									WHERE id = %d ORDER BY start;', $id));
			return $this->return_result($result);
		}
        
        function db_query_events($start, $end, $category_id, $excluded, $limit=false) {
			global $wpdb;
			$excluded = ($excluded) ? 'NOT IN' : 'IN';
			$andcategory = ($category_id) ? " AND category_id {$excluded}({$category_id})" : '';
			$limit = ($limit) ? " LIMIT {$limit}" : "";
			$sql = ("SELECT e1.id, e1.user_id, e1.title, e1.start, e1.end, e1.allDay, e1.repeat_int, e1.repeat_freq, e1.repeat_end, e1.category_id, e1.venue_id, v.venue_name, group_concat(e2.id) as clash_ids  
                                FROM wp_aec_event e1
                                LEFT OUTER JOIN wp_aec_venue v ON v.venue_id = e1.venue_id
                                LEFT OUTER JOIN wp_aec_event e2 ON (e1.venue_id = e2.venue_id
                                                                    AND e1.id != e2.id
                                                                    AND ((e1.start <= e2.start AND e1.end >= e2.start)
                                                                        OR (e1.start <= e2.end AND e1.end >= e2.end) 
                                                                        OR (e1.start <= e2.start AND e1.end >= e2.end)
                                                                        OR (e1.start >= e2.start AND e1.end <= e2.end)))
                                WHERE (e1.start <= '2014-08-20 00:00:00' AND e1.end >= '2014-08-10 00:00:00')
                                GROUP BY e1.id, e1.user_id, e1.title, e1.start, e1.end, e1.allDay, e1.repeat_int,
                                         e1.repeat_freq, e1.repeat_end, e1.category_id, e1.venue_id, v.venue_name
                                {$andcategory} ORDER BY e1.venue_id, e1.id, e2.id{$limit};");

            $result = $wpdb->get_results($sql);
			return $this->return_result($result);
		}

		function db_query_events_by_user($user_id) {
			global $wpdb;
			$result = $wpdb->get_var($wpdb->prepare('SELECT count(id)
									 FROM ' . $wpdb->prefix . AEC_EVENT_TABLE . '
									 WHERE user_id = %d;', $user_id));
			return $this->return_result($result);
		}

		function db_query_events_by_category($id) {
			global $wpdb;
			$result = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) as count
									FROM ' . $wpdb->prefix . AEC_EVENT_TABLE . '
									WHERE category_id = %d;', $id));
			return $this->return_result($result);
		}

		function db_insert_event($input, $render) {
			global $wpdb;
			$result = $wpdb->insert($wpdb->prefix . AEC_EVENT_TABLE,
									array('user_id' 		=> $input->user_id,
										  'title'	 		=> $input->title,
										  'start'			=> $input->start,
										  'end'				=> $input->end,
										  'allDay'			=> $input->allDay,
										  'repeat_freq'		=> $input->repeat_freq,
										  'repeat_int'		=> $input->repeat_int,
										  'repeat_end'		=> $input->repeat_end,
										  'category_id'		=> $input->category_id,
										  'description'		=> $input->description,
										  'link'			=> $input->link,
										  'venue_id'		=> $input->venue_id, // has to be an int so it'll will point to the venue table has to be the same name here as in the database and the code for adding evnet in form
										  'address'			=> $input->address,
										  'city'			=> $input->city,
										  'state'			=> $input->state,
										  'zip'				=> $input->zip,
										  'country'			=> $input->country,
										  'contact'			=> $input->contact,
										  'contact_info'	=> $input->contact_info,
										  'access'			=> $input->access,
										  'rsvp'			=> $input->rsvp
										),
									array('%d',				// user_id
										  '%s',				// title
										  '%s',				// start
										  '%s',				// end
										  '%d',				// allDay
										  '%d',				// repeat_freq
										  '%d',				// repeat_int
										  '%s',				// repeat_end
										  '%d',				// category_id
										  '%s',				// description
										  '%s',				// link
										  '%d',				// venue_id
										  '%s',				// address
										  '%s',				// city
										  '%s',				// state
										  '%s',				// zip
										  '%s',				// country
										  '%s',				// contact
										  '%s',				// contact_info
										  '%d',				// access
										  '%d' 				// rsvp
										)
								);
			if ($this->return_result($result)) {
				if ($render) {
					$input->id = $wpdb->insert_id;		// id of newly created row
					$this->process_event($input);
				}
			}
		}

		function db_update_event($input) {
			global $wpdb;
			$result = $wpdb->update($wpdb->prefix . AEC_EVENT_TABLE,
									array('user_id' 		=> $input->user_id,
										  'title'	 		=> $input->title,
										  'start'			=> $input->start,
										  'end'				=> $input->end,
										  'allDay'			=> $input->allDay,
										  'repeat_freq'		=> $input->repeat_freq,
										  'repeat_int'		=> $input->repeat_int,
										  'repeat_end'		=> $input->repeat_end,
										  'category_id'		=> $input->category_id,
										  'description'		=> $input->description,
										  'link'			=> $input->link,
										  'venue_id'		=> $input->venue_id, //had an error here it has to be venue_id not venue has to be the same name here as in the database and the code for adding evnet in form
										  'address'			=> $input->address,
										  'city'			=> $input->city,
										  'state'			=> $input->state,
										  'zip'				=> $input->zip,
										  'country'			=> $input->country,
										  'contact'			=> $input->contact,
										  'contact_info'	=> $input->contact_info,
										  'access'			=> $input->access,
										  'rsvp'			=> $input->rsvp
										),
									
									array('id' 				=> $input->id),
									
									array('%d',				// user_id
										  '%s',				// title
										  '%s',				// start
										  '%s',				// end
										  '%d',				// allDay
										  '%d',				// repeat_freq
										  '%d',				// repeat_int
										  '%s',				// repeat_end
										  '%d',				// category_id
										  '%s',				// description
										  '%s',				// link
										  '%d',				// venue_id
										  '%s',				// address
										  '%s',				// city
										  '%s',				// state
										  '%s',				// zip
										  '%s',				// country
										  '%s',				// contact
										  '%s',				// contact_info
										  '%d',				// access
										  '%d' 				// rsvp
										),
									array ('%d') 			// id
								);
			if ($this->return_result($result)) {
				$this->process_event($input);
			}
		}

		function db_delete_event() {
			global $wpdb;
			$result = $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . AEC_EVENT_TABLE . ' WHERE id = %d;', $_POST['id']));
			$this->render_json($this->return_result($result));
		}

		function db_delete_events_by_user($id) {
			global $wpdb;
			$result = $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . AEC_EVENT_TABLE . ' WHERE user_id = %d;', $id));
			return $this->return_result($result);
		}

		function db_query_categories($category_id=false, $excluded=false) {
			global $wpdb;
			$excluded = ($excluded) ? 'NOT IN' : 'IN';
			$wherecategory = ($category_id) ? " WHERE id {$excluded}({$category_id})" : '';
			$result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . AEC_CATEGORY_TABLE . $wherecategory . ' ORDER BY id;');
			return $this->return_result($result);
		}

		function db_insert_category($input) {
			global $wpdb;
			$result = $wpdb->insert($wpdb->prefix . AEC_CATEGORY_TABLE,
									array('category'	=> $input->category,
										  'bgcolor'		=> $input->bgcolor,
										  'fgcolor' 	=> $input->fgcolor
										),
									array('%s',
										  '%s',
										  '%s'
										)
								);
			if ($this->return_result($result)) {
				$input->id = $wpdb->insert_id;	// id of newly created row
				$this->render_category($input);
			}
		}

		function db_update_category($input) {
			global $wpdb;
			$result = $wpdb->update($wpdb->prefix . AEC_CATEGORY_TABLE,
									array('category'	=> $input->category,
										  'bgcolor'		=> $input->bgcolor,
										  'fgcolor'		=> $input->fgcolor
									),
									array('id' 			=> $input->id),
									array('%s',
										  '%s',
										  '%s'
									),
									array ('%d') //id
								);
			if ($this->return_result($result)) {
				$this->render_category($input);
			}
		}

		function db_reassign_category($id) {
			global $wpdb;
			$result = $wpdb->update($wpdb->prefix . AEC_EVENT_TABLE,
								array('category_id'	=> 1),
								array('category_id' => $id),
								array('%d'),
								array('%d')
							);
			if ($this->return_result($result)) {
				$this->db_delete_category($id);
			}
		}

		function db_delete_category($id) {
			global $wpdb;
			$result = $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . AEC_CATEGORY_TABLE . ' WHERE id = %d;', $id));
			if ($this->return_result($result)) {
				$this->generate_css();
				$this->render_json($result);
			}
		}
        // VENUES CODE
		// Cathal's code
		
		// Query Venues 
		function db_query_venues($venue_id=false, $excluded=false)	{
			global $wpdb;
			$excluded = ($excluded)	?	'NOT IN' :	'IN';
			$wherevenue = ($venue_id) ? " WHERE venue_id {$excluded} ({$venue_id})" : '';
			$results = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . AEC_VENUE_TABLE . $wherevenue . ' ORDER BY venue_id;');
			return $this->return_result($results);
		}
		
		// INSERT VENUE
        function db_insert_venue($input) {
            global $wpdb;
            $result = $wpdb->insert($wpdb->prefix . AEC_VENUE_TABLE,
									array('venue_name'		=> $input->venue_name,
										  'street'			=> $input->street,
										  'city'			=> $input->city,
										  'state'			=> $input->state,
										  'zip'				=> $input->zip,
										  'website'			=> $input->website
										),
									array('%s',
										  '%s',
										  '%s',
										  '%s',
										  '$s',
										  '$s'
										)
            );
            if ($this->return_result($result)){
				$input->venue_id = $wpdb->insert_id; // id of newly created venue
				$this->render_venue($input);
				header("Location: http://localhost/wordpress/");
            }
        }
		
		// UPDATE VENUE
		function db_update_venue($input) {
			global $wpdb;
			$result = $wpdb->update($wpdb->prefix . AEC_VENUE_TABLE,
									array('venue_name'		=> $input->venue_name,
										  'street'			=> $input->street,
										  'city'			=> $input->city,
										  'state'			=> $input->state,
										  'zip'				=> $input->zip,
										  'website'			=> $input->website
										),
									array('venue_id' 			=> $input->venue_id),
									array('%s',
										  '%s',
										  '%s',
										  '%s',
										  '$s',
										  '$s'
										),
									array('%d') //id
								);
								
			if ($this->return_result($result)) {
				$this->render_venue($input);
			}
		}
		
		// DELETE VENUE
		function db_delete_venue($venue_id) {
			global $wpdb;
			$result = $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . AEC_VENUE_TABLE . ' WHERE id = %d;', $venue_id));
			if ($this->return_result($result)) {
				
				$this->render_json($result);
			}
		}
		
		// EXPORT 
		
		function db_export_events($id) {
			global $wpdb;
			$result = $wpdb->query($wpdb->prepare('SELECT * FROM' . $wpdb->prefix . AEC_EVENT_TABLE));
		}

        

		// UTILITIES
		function parse_input($input) {
			if (!is_array($input)) {
				parse_str($input, $array);	// convert serialized form into array
				$input = $array;
			}
			array_walk($input, create_function('&$val', '$val = trim($val);'));	// trim whitespace from input
			return $input;
		}

		// removes slashes from strings and arrays
		function cleanse_output($output) {
			if (is_array($output)) {
				array_walk_recursive($output, create_function('&$val', '$val = trim(stripslashes($val));'));
			} else {
				$output = stripslashes($output);
			}
			return $output;
		}

		function cleanse_data_input($input) {
			return trim($input);
		}

		function cleanse_event_input($input) {
			$clean 				= $this->convert_array_to_object($this->parse_input($input));
			if ($clean->allDay) {
				$clean->start_time	= '00:00:00';
				$clean->end_time	= '00:00:00';
			}
			$clean->start		= $this->merge_date_time($clean->start_date, $clean->start_time);
			$clean->end			= $this->merge_date_time($clean->end_date, $clean->end_time);
			$clean->repeat_end	= substr($this->merge_date_time($clean->repeat_end, '00:00:00'),0,-9);
			return $clean;
		}

		function cleanse_category_input($input) {
			$clean 			= $this->convert_array_to_object($this->parse_input($input));
			$clean->bgcolor = str_replace('#', '', $clean->bgcolor);	// strip '#' for storage
			$clean->fgcolor = str_replace('#', '', $clean->fgcolor);
			return $clean;
		}

		// convert category string input into array, force integer values, return serialized
		function cleanse_shortcode_input($input) {
			$input = explode(',', $input);
			array_walk($input, create_function('&$val', '$val = intval($val);'));
			return join(',', $input);
		}

		// set required fields on admin event detail form
		function add_required_field($field) {
			array_push($this->required_fields, $field);
		}

		function get_required_fields() {
			if (count($this->required_fields)) {
				return $this->required_fields;
			}
			return;
		}

		function convert_object_to_array($object) {
			$array = array();
			foreach ($object as $key => $value) {
				$array[$key] = $value;
			}
			return $array;
		}

		function convert_array_to_object($array = array()) {
			$return = new stdClass();
			foreach ($array as $key => $val) {
				if (is_array($val)) {
					$return->$key = $this->convert_array_to_object($val);
				} else {
					$return->{$key} = $val;
				}
			}
			return $return;
		}

		function return_result($result) {
			if ($result === false) {
				global $wpdb;
				$this->log($wpdb->print_error());
				return false;
			}
			return $result;
		}

		function return_current_user_id() {
			global $current_user;
			get_currentuserinfo();
			return $current_user->ID;
		}

		function return_auth($readonly = false) {
			if ($readonly) {
				return "-1";
			}
			return (current_user_can('aec_manage_events')) ? false : $this->return_current_user_id();
		}

		function render_i18n_data($data) {
			return htmlentities(stripslashes($data), ENT_COMPAT, 'UTF-8');
		}

		function render_json($output) {
			header("Content-Type: application/json");
			echo json_encode($this->cleanse_output($output));
			exit;
		}

		function generate_css() {
			$categories = $this->db_query_categories();
			
			$out 	  = "<style>";
			foreach ($categories as $category) { // spits out the categories by their id's eg. cat1 cat2 
				$out .= ".cat{$category->id}";
				$out .= ",.cat{$category->id} .fc-event-skin";
				$out .= ",.fc-agenda .cat{$category->id}";
				$out .= ",a.cat{$category->id}";
				$out .= ",a.cat{$category->id}:active";
				$out .= ",a.cat{$category->id}:visited{";
				$out .= "color:#{$category->fgcolor} !important;";
				$out .= "background-color:#{$category->bgcolor} !important;";
				$out .= "border-color:#{$category->bgcolor} !important}\n";
				$out .= "a.cat{$category->id}:hover{-moz-box-shadow:0 0 2px #000;-webkit-box-shadow:0 0 2px #000;box-shadow:0 0 2px #000;";
				$out .= "color:#{$category->fgcolor} !important;";
				$out .= "background-color:#{$category->bgcolor} !important;";
				$out .= "border-color:#{$category->fgcolor} !important}\n";
			}
            /*Code for add event button effect*/
    		$out .= '.addEvent,.addEvent .fc-event-skin,.fc-agenda .addEvent,a.addEvent,a.addEvent:active,a.addEvent:visited{position:relative; left:6px; color:#FFFFFF !important;background-color:#808080 !important;border-color:#808080 !important}';
			$out .= 'a.addEvent:hover{-moz-box-shadow:0 0 2px #000;-webkit-box-shadow:0 0 2px #000;box-shadow:0 0 2px #000;color:#FFFFFF !important;background-color:#808080 !important;border-color:#FFFFFF !important}';
			$out .= '#aec-filter li{float:left; margin:1px;}';
            $out .= '#start_date{position:relative;}';
			$out .= "</style>";
			return $out;
		}

		// Venue CSS
		function generate_css_venue() {
			$venues = $this->db_query_venues();

			$out 	= "<style>";
			foreach ($venues as $venue) {
				$out .= ".ven{$venue->v_id}";
				$out .= ",.ven{$venue->v_id} .ven-skin";
			}
			$out .= "</style>";
			return $out;
		}

		// overwrite option, preserving other serialized options
		function overwrite_option($key, $value) {
			$options = get_option('aec_options');
			$options[$key] = $value;
			update_option('aec_options', $options);
		}

		// if not present, add options
		function insert_option($key, $value) {
			$options = get_option('aec_options');
			if (!array_key_exists($key, $options)) {
				$options[$key] = $value;
			}
			update_option('aec_options', $options);
		}

		function decommission_options($keys) {
			$options = get_option('aec_options');
			foreach ($keys as $key) {
				if (array_key_exists($key, $options)) {
					unset($options[$key]);
				}
			}
			update_option('aec_options', $options);
		}

		function log($message) {
			if (is_array($message) || is_object($message)) {
				error_log(print_r($message, true));
			} else {
				error_log($message);
			}
			return;
		}

		function convert_date($date, $from, $to=false) {
			// if date format is d/m/Y, modify token to 'd-m-Y' so strtotime parses date correctly
			if (strpos($from, 'd') == 0) {
				$date = str_replace("/", "-", $date);
			}
			if ($to) {
				return date_i18n($to, strtotime($date));
			}
			return strtotime($date);
		}

		function parse_date_format($format) {
			// d | j	 1 | 01, day of the month
			// m | n	 3 | 03, month of the year
			// if date format begins with d or j assign Euro format, otherwise US format
			return (strpos($format, 'd') === 0 || strpos($format, 'j') === 0) ? true : false;
		}

		function parse_time_format($format) {
			// H | G	 24-hour, with | without leading zeros
			// g | H	 24-hour, with | without leading zeros
			return (strpos($format, 'G') !== false || strpos($format, 'H') !== false) ? true : false;
		}

		// restricts jquery datepicker format (based on WP date format) to ensure accurate localization
		function get_wp_date_format() {
			return ($this->parse_date_format(AEC_WP_DATE_FORMAT)) ? 'd-m-Y' : 'm/d/Y';
		}

		// restricts jquery timepicker format (based on WP time format) to ensure accurate localization
		function get_wp_time_format() {
			return ($this->parse_time_format(AEC_WP_TIME_FORMAT)) ? 'H:i' : 'h:i A';
		}

		// split datetime fields
		function split_datetime($datetime) {
			$out = array();
			array_push($out, $this->convert_date($datetime, AEC_DB_DATETIME_FORMAT, $this->get_wp_date_format()));
			array_push($out, $this->convert_date($datetime, AEC_DB_DATETIME_FORMAT, $this->get_wp_time_format()));
			return $out;
		}

		// merge date and time fields, and convert to database format
		function merge_date_time($date, $time) {
			$datetime 	= "{$date} {$time}";
			$format 	= "{$this->get_wp_date_format()} {$this->get_wp_time_format()}";
			return $this->convert_date($datetime, $format, AEC_DB_DATETIME_FORMAT);
		}

		function array_compare_order($a, $b) {
			return strtotime($a['start']) - strtotime($b['start']);
		}

		// CUSTOMIZE WORDPRESS
		// adds column field label to WordPress users page
		function add_events_column($columns) {
			$columns['calendar_events'] = __('Events', AEC_NAME);
			return $columns;
		}

		// adds column field value to WordPress users page
		function add_events_column_data($empty='', $column_name, $user_id) {
			if ($column_name == 'calendar_events') {
				return $this->db_query_events_by_user($user_id);
			}
		}

		// displays the "settings" link beside the plugin on the WordPress plugins page
		function settings_link($links, $file) {
			if ($file == plugin_basename(__FILE__)) {
				$settings = '<a href="' . get_admin_url() . 'admin.php?page=aec_calendar_options">' . __('Settings', AEC_NAME) . '</a>';
				array_unshift($links, $settings);	// make the 'Settings' link appear first
			}
			return $links;
		}

		// changes the permissions for using the calendar settings page
		function set_option_page_capability($capability) {
			return 'aec_manage_calendar';
		}

		function admin_options_initialize() {
			register_setting('aec_plugin_options', 'aec_options', array($this, 'admin_options_validate'));
		}

		function admin_options_validate($input) {
			// validation placeholder
			return $input;
		}
	}
}

register_activation_hook(__FILE__, array('ajax_event_calendar', 'install'));

if (class_exists('ajax_event_calendar')) {
	require_once AEC_PATH . 'inc/widget-contributors.php';
	require_once AEC_PATH . 'inc/widget-upcoming.php';
	$aec = new ajax_event_calendar();
}

?>