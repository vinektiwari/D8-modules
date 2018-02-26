(function ($) {
  Drupal.behaviors.nebm = {
    attach: function (context) {
    	$( "#edit-field-event-date-0-value-date, #edit-field-event-date-0-end-value-date" ).datepicker({
    		minDate: 0,
    		dateFormat: "yy-mm-dd",
    	});
    	//Resolved chrome showing 2 types of calendar
    	$('#edit-field-event-date-0-value-date, #edit-field-event-date-0-end-value-date').attr('type', 'text');
    	$('#edit-field-event-date-0-value-date, #edit-field-event-date-0-end-value-date').prop('readonly', true)
    }
  };
})(jQuery);