/**
 * Handle: init_admin_venue
 * Version: 1.0
 * Deps: jQuery
 * Enqueue: true
 */

jQuery(document).ready(function ($) {

$('#aec-venue-list').delegate('.update', 'click', function (e) {
	e.preventDefault();
	var row 	= $(this).parent()[0],
		html_id = row.id,
		id		= html_id.replace('id_', ''),
		ven 	= $.trim($('.edit', row).val()),
		json	= { 'v_id': id, 'venue_name': ven };
	if (ven.length >= 1) {
		$.post(ajaxurl, { action: 'update_venue', 'venue_data': json }, function (info) {
			if (info) {
				$("span", row).removeClass('ven' + id);
				$.jGrowl(custom.venue_type + '<strong>' + ven + '</strong> ' + custom.has_been_modified, { header: custom.success });

			}
		});
	} else {
		$.jGrowl(custom.error_blank_venue, { header: custom.whoops});
	}
});

$('#aec-venue-list').delegate('.delete', 'click', function (e) {
				e.preventDefault();
				var row		= $(this).parent()[0],
					html_id = row.id,
					id		= html_id.replace('id_', ''),
					ven 	= $('.edit', row).val();
				if (confirm(custom.confirm_venue_delete)) {
					$.post(ajaxurl, { action: 'delete_venue', 'v_id': id }, function (info) {
						if (info) {
								$(row).remove();
								$.jGrowl(custom.venue_type + ' <strong>' + ven + '</strong> ' + custom.has_been_deleted, { header: custom.success });
						}
						else
						{
							$.jGrowl(custom.error_venue, { header: custom.whoops});
						}
					});
				}
			});
});