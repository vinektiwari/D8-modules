(function ($, Drupal, window, document) {
    Drupal.behaviors.enewsletters = {
		attach: function(context, settings) {
			$('main', context).once('enewsletters');

			// Making base uri with path provided by controller file
			var baseUri = window.location.origin;
			var pathName = window.location.pathname;
			var absolutePath = baseUri+pathName;
			
			// dataTable assigning events
			$('#subscriber-table').DataTable({
				"columnDefs": [
					{ "order": [[ 1, 'desc' ]] },
					{ "targets": [2,4], "orderable": false }
				]
			});
			$('#unsubscriber-table').DataTable({
				"columnDefs": [ 
					{ "order": [[ 1, 'desc' ]] },
					{ "targets": [3], "orderable": false }
				]
			});
			
			// Un-subscription event
			$('button#btnUnsubscribe').click(function() {
				if (confirm('Are you sure you want to cancel the subscription for this subscriber?')) {
				    var subscriberId = $(this).attr("data-id");
					$.ajax({
						type: 'POST',
						url: absolutePath+'/ajax_unsubscribe',
						data: { 'subid':subscriberId },
						success: function(response) {
							location.reload();
						}
					});
				} else {
					return FALSE;
				}
			})

			// Subscription event
			$('button#btnSubscribe').click(function() {
				if (confirm('Are you sure you want to active the subscription for this un-subscriber?')) {
				    var subscriberId = $(this).attr("data-id");
					$.ajax({
						type: 'POST',
						url: absolutePath+'/ajax_subscribe',
						data: { 'subid':subscriberId },
						success: function(response) {
							location.reload();
						}
					});
				} else {
					return FALSE;
				}
			})

			// Subscription delete event
			$('button#btnDelete').click(function() {
				if (confirm('Are you sure you want to delete this un-subscriber?')) {
				    var subscriberId = $(this).attr("data-id");
					$.ajax({
						type: 'POST',
						url: absolutePath+'/ajax_delete',
						data: { 'subid':subscriberId },
						success: function(response) {
							location.reload();
						}
					});
				} else {
					return FALSE;
				}
			})
		}
	};
}(jQuery, Drupal, this, this.document));
