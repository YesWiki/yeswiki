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
 * @package 	publication
 * @author		Florian Schmitt <florian@outils-reseaux.org>
 *
 *
 **/

$(document).ready(function () {

    $("#checkbox-selection-container").sortable({
          connectWith: ".connectedSortableCheckbox",
          receive: function( event, ui ) {
              $(this).find('.select-page-item').click();
          }
        });
    $("ul.list-entries-to-export").sortable({
          connectWith: ".connectedSortableCheckbox",
          receive: function( event, ui ) {
              $(this).find('.remove-page-item').click();
          }
        });

	$('.btn-erase-filter').on('click', function() {
        $("#filter").val('').keyup();
    });

	$('#checkbox-selection-container').on('click', '.remove-page-break', function() {
        $(this).parent().remove();
        return false;
    });

    $('.checkbox-select-all').on('click', function(event) {
        event.stopPropagation();
        let text_nb_page = "Nombre de pages : " ;
        let extract_text = filtercount.text().substring(text_nb_page.length);
        if (extract_text == "" || 
                extract_text == $(this).parents('.export-table-container').find('.list-entries-to-export').find('.list-group-item').find('.select-page-item').length){
            $(this).parents(".yeswiki-checkbox").find(".list-entries-to-export .empty-list").show() ;
        }
        $(this).parents('.export-table-container').find('.list-entries-to-export').find('.list-group-item').not(':hidden').find('.select-page-item').click();
        return false;
    });
    $('.checkbox-remove-all').on('click', function(event) {
        event.stopPropagation();
        $(this).parents('.import-table-container').find('#checkbox-selection-container').find('.list-group-item').not(':hidden').find('.remove-page-item').click();
        $(this).parents(".yeswiki-checkbox").find("#checkbox-selection-container .empty-list").show() ;
        return false;
    });

	$('.select-page-item').on('click', function() {
        var $this = $(this);
		$this.siblings().filter('.remove-page-item').removeClass('hide');
        $this.siblings().filter(".movable").removeClass('hide');
		$this.addClass('hide');
        $this.parents(".yeswiki-checkbox").find("#checkbox-selection-container .empty-list").hide() ;
        var nb_elem_this_col = $this.parents(".yeswiki-checkbox").find(".list-entries-to-export .select-page-item").length ;
		var listitem = $this.parent();
        listitem.find("input").prop('checked', true) ;
		listitem.fadeOut("fast", function() {
			listitem.appendTo("#checkbox-selection-container").fadeIn("fast");
		});
        if (nb_elem_this_col < 1){
            $this.parents(".yeswiki-checkbox").find(".list-entries-to-export .empty-list").show() ;
        }
        return false;
	});

    $('.remove-page-item').on('click', function() {
        var $this = $(this);
        $this.siblings().filter('.select-page-item').removeClass('hide');
        $this.siblings().filter(".movable").addClass('hide');
        $this.addClass('hide');
        $this.parents(".yeswiki-checkbox").find(".list-entries-to-export .empty-list").hide() ;
        var nb_elem_this_col = $this.parents(".yeswiki-checkbox").find("#checkbox-selection-container .select-page-item").length ;
        var listitem = $this.parent();
        listitem.find("input").prop('checked', false) ;
        listitem.fadeOut("fast", function() {
            listitem.prependTo(".list-entries-to-export").fadeIn("fast");
        });
        if (nb_elem_this_col < 1){
            $this.parents(".yeswiki-checkbox").find("#checkbox-selection-container .empty-list").show() ;
        }
        return false;
    });

    var filter = $("#filter"), filtercount = $("#filter-count");
	filter.keyup(function(){
        // Retrieve the input field text and reset the count to zero
        var count = 0;

        // Loop through the comment list
        $(".export-table-container .list-group-item").not('.empty-list').each(function(){
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
        filtercount.text("Nombre de pages : "+count);
    });
});
