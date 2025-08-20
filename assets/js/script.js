var timer;

$(document).ready(function() {


	$(".result").on("click", function() {
		
		var id = $(this).attr("data-linkId");
		var url = $(this).attr("href");
		var searchTerm = $(this).attr("data-search-term");

		if(!id) {
			alert("data-linkId attribute not found"); //DEBUGGING
		}

		// Track click for ranking algorithm
		if (searchTerm) {
			trackClick(searchTerm, id, 'site');
		}

		increaseLinkClicks(id, url);

		return false;
	});

	// Track image clicks
	$("[data-fancybox]").on("click", function() {
		var id = $(this).attr("data-linkId");
		var searchTerm = $(this).attr("data-search-term");
		
		if (id && searchTerm) {
			trackClick(searchTerm, id, 'image');
		}
	});


	var grid = $(".imageResults");

	grid.on("layoutComplete", function() {
		$(".gridItem img").css("visibility", "visible");
	});

	grid.masonry({
		itemSelector: ".gridItem",
		columnWidth: 200,
		gutter: 5,
		isInitLayout: false
	});


	$("[data-fancybox]").fancybox({

		caption : function( instance, item ) {
	        var caption = $(this).data('caption') || '';
	        var siteUrl = $(this).data('siteurl') || '';


	        if ( item.type === 'image' ) {
	            caption = (caption.length ? caption + '<br />' : '')
	             + '<a href="' + item.src + '">View image</a><br>'
	             + '<a href="' + siteUrl + '">Visit page</a>';
	        }

	        return caption;
	    },
	    afterShow : function( instance, item ) {
	        increaseImageClicks(item.src);
	    }


	});

});

function loadImage(src, className) {

	var image = $("<img>");

	image.on("load", function() {
		$("." + className + " a").append(image);

		clearTimeout(timer);

		timer = setTimeout(function() {
			$(".imageResults").masonry();
		}, 200);

	});

	image.on("error", function() {
		
		$("." + className).remove();

		$.post("ajax/setBroken.php", {src: src});

	});

	image.attr("src", src);

}


function increaseLinkClicks(linkId, url) {

	$.post("ajax/updateLinkCount.php", {linkId: linkId})
	.done(function(result) {
		if(result != "") {
			alert(result);
			return;
		}

		window.location.href = url;
	});

}

function increaseImageClicks(imageUrl) {

	$.post("ajax/updateImageCount.php", {imageUrl: imageUrl})
	.done(function(result) {
		if(result != "") {
			alert(result);
			return;
		}
	});

}

// Track clicks for ranking algorithm
function trackClick(searchTerm, resultId, resultType) {
	var data = {
		search_term: searchTerm,
		result_id: resultId,
		result_type: resultType
	};

	$.ajax({
		url: 'ajax/track-click.php',
		type: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(response) {
			// Click tracked successfully
			console.log('Click tracked:', response);
		},
		error: function(xhr, status, error) {
			// Silent failure - don't interrupt user experience
			console.log('Click tracking failed:', error);
		}
	});
}