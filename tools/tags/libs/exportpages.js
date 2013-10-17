/** 
 * +------------------------------------------------------------------------------------------------------+
 * | Copyright (C) 2013 Outils-Reseaux (accueil@outils-reseaux.org)                                       |
 * +------------------------------------------------------------------------------------------------------+
 * | This library is free software; you can redistribute it and/or                                        |
 * | modify it under the terms of the GNU Lesser General Public                                           |
 * | License as published by the Free Software Foundation; either                                         |
 * | version 2.1 of the License, or (at your option) any later version.                                   |
 * |                                                                                                      |
 * | This library is distributed in the hope that it will be useful,                                      |
 * | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
 * | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
 * | Lesser General Public License for more details.                                                      |
 * |                                                                                                      |
 * | You should have received a copy of the GNU Lesser General Public                                     |
 * | License along with this library; if not, write to the Free Software                                  |
 * | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
 * +------------------------------------------------------------------------------------------------------+
 *
 * javascript for pages export
 *
 *
 * @package 	tags
 * @author		Florian Schmitt <florian@outils-reseaux.org>
 * 
 * 
 **/

$(document).ready(function () {
	//$("#list-pages-to-export").sortable({ connectWith: "#ebook-selection-container" });
	$("#ebook-selection-container").sortable();


	$('.btn-erase-filter').on('click', function() {$("#filter").val('').keyup();});
	$('.select-page-item').on('click', function() {
		$(this).siblings('.remove-page-item').show();
		$(this).hide();
		var listitem = $(this).parent();
		listitem.fadeOut("fast", function() {
			listitem.appendTo("#ebook-selection-container").fadeIn("fast");
		});
	});

	$("#filter").keyup(function(){
 
        // Retrieve the input field text and reset the count to zero
        var filter = $(this).val(), count = 0;
 
        // Loop through the comment list
        $("#list-pages-to-export .list-group-item").each(function(){
 
            // If the list item does not contain the text phrase fade it out
            if ($(this).text().search(new RegExp(filter, "i")) < 0) {
                $(this).hide();
                //$(this).next().hide();
 
            // Show the list item if the phrase matches and increase the count by 1
            } else {
                $(this).show();
                //$(this).next().show();
                count++;
            }
        });
 
        // Update the count
        var numberItems = count;
        $("#filter-count").text("Nombre de pages : "+count);
    });
});
