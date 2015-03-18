<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-config.php'); // These three lines help with the connection to the admin calendar so the categories are the same
include_once($_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-load.php');
include_once($_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/ajax-event-calendar.php');

// code here is used from the admin calendar

?>
<!DOCTYPE html>
<html style="padding-top:0; background-color:white;" xmlns="http://www.w3.org/1999/xhtml" class="wp-toolbar" lang="en-GB">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Fleadh Cheoil Event's Calendar</title>
     <link rel="stylesheet" href="//code.jquery.com/ui/1.11.3/themes/smoothness/jquery-ui.css">
	<script type="text/javascript">
		addLoadEvent = function (func) { if (typeof jQuery != "undefined") jQuery(document).ready(func); else if (typeof wpOnload != 'function') { wpOnload = func; } else { var oldonload = wpOnload; wpOnload = function () { oldonload(); func(); } } };
		var ajaxurl = '/wordpress/wp-admin/admin-ajax.php',
			pagenow = 'toplevel_page_ajax-event-calendar',
			typenow = '',
			adminpage = 'toplevel_page_ajax-event-calendar',
			thousandsSeparator = ',',
			decimalPoint = '.',
			isRtl = 0;

    function filterVenues(v)
    {
        var opt = document.getElementById("venues").options;
        var len = opt.length;
        var s = "";
        for(var i = 1; i<len;i++)
        {
            var o = opt[i].value;
            if(v == -1) // All Venues Selected
            {
                //var elms = document.getElementsByClassName(o);
                //for(var j=0;j<elms.length;j++)
                //    elms[j].style.display = "inline";
                //alert();
                filter($(selectedCategory));
                break;
            }
            else if(o != v) // Hides any elements not equal to selected venue
            {
                var elms = document.getElementsByClassName(o);
                for(var j=0;j<elms.length;j++) 
                    if(selectedCatName == "all" || $(elms[j]).hasClass( selectedCatName ))
                        elms[j].style.display = "none";
            }
            else // Shows any elements not equal to selected venue
            {
                var elms = document.getElementsByClassName(o);
                for(var j=0;j<elms.length;j++)
                    if(selectedCatName == "all" || $(elms[j]).hasClass( selectedCatName )) // Only show items from the currently selected category
                        elms[j].style.display = "block";
            }
        }
    }

	</script>
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<link rel="stylesheet" href="/wordpress/wp-admin/load-styles.php?c=1&amp;dir=ltr&amp;load=dashicons,admin-bar,wp-admin,buttons,wp-auth-check&amp;ver=4.0.1" type="text/css" media="all">
	<link rel="stylesheet" id="open-sans-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300italic%2C400italic%2C600italic%2C300%2C400%2C600&amp;subset=latin%2Clatin-ext&amp;ver=4.0.1" type="text/css" media="all">
	<!--[if lte IE 7]>
	<link rel='stylesheet' id='ie-css'  href='/wordpress/wp-admin/css/ie.min.css?ver=4.0.1' type='text/css' media='all' />
	<![endif]-->
	<link rel="stylesheet" id="jq_ui_css-css" href="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/css/jquery-ui-1.8.16.custom.css?ver=1.8.16" type="text/css" media="all">
	<link rel="stylesheet" id="custom-css" href="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/css/custom.css?ver=1.0.4" type="text/css" media="all">

	<script type="text/javascript">
		/* <![CDATA[ */
		var userSettings = { "url": "\/wordpress\/", "uid": "1", "time": "1418514133", "secure": "" };/* ]]> */

		function addEvent() {
			var e = new Object();
			e["allDay"] = "1";
			eventDialog(e, "Add Event");
		}
	</script>
	<script type="text/javascript">

		var hidePopupHack = function () {
        if(document.getElementsByClassName("jGrowl-close")[0])
			document.getElementsByClassName("jGrowl-close")[0].click();
		};
		setTimeout(hidePopupHack, 500);

	</script>
	<script type="text/javascript" src="/wordpress/wp-admin/load-scripts.php?c=1&amp;load%5B%5D=jquery-core,jquery-migrate,utils&amp;ver=4.0.1"></script>
	<script type="text/javascript">var _wpColorScheme = { "icons": { "base": "#999", "focus": "#2ea2cc", "current": "#fff" } };</script>

	<style type="text/css" media="print">
		#wpadminbar {
			display: none;
		}
	</style>
</head>
<body class="wp-admin wp-core-ui js
		   auto-fold admin-bar branch-4 version-4-0-1 admin-color-fresh locale-en-gb customize-support sticky-menu svg">
           
	<script type="text/javascript">
		document.body.className = document.body.className.replace('no-js', 'js');
	</script>

	<script type="text/javascript">
		(function () {
			var request, b = document.body, c = 'className', cs = 'customize-support', rcs = new RegExp('(^|\\s+)(no-)?' + cs + '(\\s+|$)');

			request = true;

			b[c] = b[c].replace(rcs, ' ');
			b[c] += (window.postMessage && request ? ' ' : ' no-') + cs;
		}());
	</script>

	<div id="wpwrap">
		<div id="wpcontent" style="margin-left:0">

			<div id="wpbody">


				<div id="wpbody-content" aria-label="Main content" tabindex="0">
					<div id="screen-meta" class="metabox-prefs">

						<div id="contextual-help-wrap" class="hidden no-sidebar" tabindex="-1" aria-label="Contextual Help Tab">
							<div id="contextual-help-back"></div>
							<div id="contextual-help-columns">
								<div class="contextual-help-tabs">
									<ul></ul>
								</div>

								<div class="contextual-help-tabs-wrap">
								</div>
							</div>
						</div>
					</div>
		   <?php 
    		   $aec->render_admin_calendar(); // helps render the cetgories 
		   ?>

						<div class="fc" id="aec-calendar">
                        
						</div>
					</div>

					<div class="clear"></div>
				</div><!-- wpbody-content -->
				<div class="clear"></div>
			</div><!-- wpbody -->

			<div class="clear"></div>
		</div><!-- wpcontent -->

		<script type="text/javascript">
			/* <![CDATA[ */
			var custom = { "is_rtl": "", "locale": "en", "start_of_week": "1", "step_interval": "30", "datepicker_format": "dd-mm-yy", "is24HrTime": "", "show_weekends": "1", "agenda_time_format": "h:mmt{ - h:mmt}", "other_time_format": "h:mmt", "axis_time_format": "h:mmt", "limit": "0", "today": "Today", "all_day": "All Day", "years": "Years", "year": "Year", "months": "Months", "month": "Month", "weeks": "Weeks", "week": "Week", "days": "Days", "day": "Day", "hours": "Hours", "hour": "Hour", "minutes": "Minutes", "minute": "Minute", "january": "January", "february": "February", "march": "March", "april": "April", "may": "May", "june": "June", "july": "July", "august": "August", "september": "September", "october": "October", "november": "November", "december": "December", "jan": "Jan", "feb": "Feb", "mar": "Mar", "apr": "Apr", "may_short": "May", "jun": "Jun", "jul": "Jul", "aug": "Aug", "sep": "Sep", "oct": "Oct", "nov": "Nov", "dec": "Dec", "sunday": "Sunday", "monday": "Monday", "tuesday": "Tuesday", "wednesday": "Wednesday", "thursday": "Thursday", "friday": "Friday", "saturday": "Saturday", "sun": "Sun", "mon": "Mon", "tue": "Tue", "wed": "Wed", "thu": "Thu", "fri": "Fri", "sat": "Sat", "close_event_form": "Close Event Form", "loading_event_form": "Loading Event Form...", "update_btn": "Update", "delete_btn": "Delete", "category_type": "Category type", "hide_all_notifications": "hide all notifications", "has_been_created": "has been created.", "has_been_modified": "has been modified.", "has_been_deleted": "has been deleted.", "add_event": "Add Event", "edit_event": "Edit Event", "delete_event": "Delete this event?", "loading": "Loading Events...", "category_filter_label": "Category filter label", "repeats_every": "Repeats Every", "until": "Until", "success": "Success!", "whoops": "Whoops!", "admin": "1", "scroll": "1", "required_fields": "title,address,city,state,zip,description,contact,contact_info", "editable": "1", "error_no_rights": "You cannot edit events created by other users.", "error_past_create": "You cannot create events in the past.", "error_future_create": "You cannot create events more than a year in advance.", "error_past_resize": "You cannot resize expired events.", "error_past_move": "You cannot move events into the past.", "error_past_edit": "You cannot edit expired events.", "error_invalid_duration": "Invalid duration." };
			/* ]]> */
		</script>

		<script type="text/javascript">
			/* <![CDATA[ */
			var commonL10n = { "warnDelete": "You are about to permanently delete the selected items.\n  'Cancel' to stop, 'OK' to delete." };
			var heartbeatSettings = { "nonce": "96c59091c6" };
			var authcheckL10n = { "beforeunload": "Your session has expired. You can log in again from this page or go to the login page.", "interval": "180" };/* ]]> */
		</script>
		<script type="text/javascript" src="/wordpress/wp-admin/load-scripts.php?c=1&amp;load%5B%5D=hoverIntent,common,admin-bar,svg-painter,heartbeat,wp-auth-check,jquery-ui-core,jquery-ui-widget,jquery-ui-mouse,jquery-ui-dragg&amp;load%5B%5D=able,jquery-ui-resizable,jquery-ui-datepicker&amp;ver=4.0.1"></script>
		<script type="text/javascript" src="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/js/jquery.fullcalendar.min.js?ver=1.5.3"></script>
		<script type="text/javascript" src="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/js/jquery.simplemodal.1.4.3.min.js?ver=1.4.3"></script>
		<script type="text/javascript" src="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/js/jquery.jgrowl.min.js?ver=1.2.5"></script>
		<script type="text/javascript" src="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/js/jquery.timePicker.min.js?ver=5195"></script>
		<script type="text/javascript" src="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/js/jquery.mousewheel.min.js?ver=3.0.6"></script>
		<script type="text/javascript" src="/wordpress/wp-content/plugins/fleadh-cheoil-events-calendar/js/jquery.init_admin_calendar.js?ver=1.0.4"></script>
          <script src="//code.jquery.com/ui/1.11.3/jquery-ui.js"></script>
        <script type="text/javascript"> // Calendar Date Picker
                    $(function() {
                    $( ".picker" ).datepicker();
                    });
                </script>
        
		<script type="text/javascript">if (typeof wpOnload == 'function') wpOnload();</script>

		<div class="clear"></div>
	</div><!-- wpwrap -->
</body>
</html>
