$(document).ready(function () {
    $('.export-table-form').on('submit', function() {$(this).append('<input type="hidden" name="antispam" value="1" />')})
	$("#ebook-selection-container").sortable();

	$('.btn-erase-filter').on('click', function() {$("#filter").val('').keyup();});

	$('.select-page-item').on('click', function() {
		$(this).siblings().filter('.remove-page-item').removeClass('hide');
        $(this).siblings().filter(".movable").removeClass('hide');
		$(this).addClass('hide');
		var listitem = $(this).parent();
		listitem.fadeOut("fast", function() {
			listitem.appendTo("#ebook-selection-container").fadeIn("fast");
		});
        return false;
	});
    
    $('.remove-page-item').on('click', function() {
        $(this).siblings().filter('.select-page-item').removeClass('hide');
        $(this).siblings().filter(".movable").addClass('hide');
        $(this).addClass('hide');
        var listitem = $(this).parent();
        listitem.fadeOut("fast", function() {
            listitem.prependTo("#list-pages-to-export").fadeIn("fast");
        });
        return false;
    });

    var listpages = $("#list-pages-to-export .list-group-item"), filter = $("#filter"), filtercount = $("#filter-count");
	filter.keyup(function(){ 
        // Retrieve the input field text and reset the count to zero
        var count = 0;

        // Loop through the comment list
        listpages.each(function(){ 
            // If the list item does not contain the text phrase fade it out
            if ($(this).text().search(new RegExp(filter.val(), "i")) < 0) {
                $(this).hide();
 
            // Show the list item if the phrase matches and increase the count by 1
            } else {
                $(this).show();
                count++;
            }
        });
 
        // Update the count
        var numberItems = count;
        filtercount.text(_t("TAGS_NUMBER_OF_PAGES"),{nb:count});
    });
});
