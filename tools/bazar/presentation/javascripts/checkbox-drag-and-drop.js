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
 * javascript for checkboxfiche dragndrop
 *
 *
 * @package 	publication > bazar
 * @author		Florian Schmitt <florian@outils-reseaux.org>
 * @author		Jérémy Dufraisse 
 *
 *
 **/

$(document).ready(function () {
    
    $(".yeswiki-checkbox").each(function(){
        if ($(this).find(".list-entries-to-export .select-page-item").length < 1){
            $(this).find(".list-entries-to-export .empty-list").show() ;
        }
    });

    $("ul.checkbox-selection-container").each(function(){
        var text_id = "ul.list-entries-to-export.group-"+$(this).data('group') ;
        $(this).sortable({
          connectWith: text_id ,
          receive: function( event, ui ) {
              $(this).find('.select-page-item').click();
          }
        })
    });
        
    $("ul.list-entries-to-export").each(function(){
        var text_id = "ul.checkbox-selection-container.group-"+$(this).data('group') ;
        $(this).sortable({
          connectWith: text_id ,
          receive: function( event, ui ) {
              $(this).find('.remove-page-item').click();
          }
        })
    });

	$('.btn-erase-filter').on('click', function() {
        $(this).parents('.input-group').find('.checkbox-filter-input').val('').keyup();
    });

    $('.checkbox-select-all').on('click', function(event) {
        event.stopPropagation();
        $(this).parents('.export-table-container').find('.list-entries-to-export .list-group-item').not(':hidden').find('.select-page-item').click();
        return false;
    });
    $('.checkbox-remove-all').on('click', function(event) {
        event.stopPropagation();
        $(this).parents('.import-table-container').find('ul.checkbox-selection-container .list-group-item').not(':hidden').find('.remove-page-item').click();
        return false;
    });

	$('.select-page-item').on('click', function() {
        var $this = $(this);
		$this.siblings().filter('.remove-page-item').removeClass('hide');
        $this.siblings().filter(".movable").removeClass('hide');
		$this.addClass('hide');
        $this.parents(".yeswiki-checkbox").find("ul.checkbox-selection-container .empty-list").hide() ;
		var listitem = $this.parent();
        listitem.find("input").prop('checked', true) ;
		listitem.fadeOut("fast", function() {
			listitem.appendTo($(this).parents(".yeswiki-checkbox").find("ul.checkbox-selection-container")).fadeIn("fast");
            if ($(this).parents(".yeswiki-checkbox").find(".list-entries-to-export .select-page-item").length < 1){
                $this.parents(".yeswiki-checkbox").find(".list-entries-to-export .empty-list").show() ;
            }
            $(this).parents(".yeswiki-checkbox").find('.checkbox-filter-input').keyup();
        });
        return false;
	});

    $('.remove-page-item').on('click', function() {
        var $this = $(this);
        $this.siblings().filter('.select-page-item').removeClass('hide');
        $this.siblings().filter(".movable").addClass('hide');
        $this.addClass('hide');
        $this.parents(".yeswiki-checkbox").find(".list-entries-to-export .empty-list").hide() ;
        var listitem = $this.parent();
        listitem.find("input").prop('checked', false) ;
        listitem.fadeOut("fast", function() {
            listitem.prependTo($(this).parents(".yeswiki-checkbox").find("ul.list-entries-to-export")).fadeIn("fast");
            if ($(this).parents(".yeswiki-checkbox").find(".checkbox-selection-container .select-page-item").length < 1){
                $this.parents(".yeswiki-checkbox").find(".checkbox-selection-container .empty-list").show() ;
            }
            $(this).parents(".yeswiki-checkbox").find('.checkbox-filter-input').keyup();
        });
        return false;
    });

    var filter = $(".checkbox-filter-input");
	filter.keyup(function(){
        // Retrieve the input field text and reset the count to zero
        var count = 0;

        // Loop through the comment list
        $(this).parents(".export-table-container").find(".list-group-item").not('.empty-list').each(function(){
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
        $(this).parents(".export-table-container").find(".checkbox-filter-count").text("Nombre de pages : "+count);
    });
});
