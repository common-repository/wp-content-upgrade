jQuery(function($) {

$("#cup.engageCup").click(function() {
	
	var href = $(this).attr('href');
	$(this).colorbox({
		inline:true,
		width:"50%",
		href: href,
		trapFocus: false
	});

});

});
