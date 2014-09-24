jQuery(document).ready(function(){
	jQuery('#toggleVendorAccountEntry').click(function(){
		var row = jQuery(this).closest('tr');
		row.next().show();
		row.next().next().show();
		row.hide();
	});
});
