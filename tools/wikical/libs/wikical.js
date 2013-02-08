$(function() {
	
	//Charge le calendrier apres l'affichage de la page
	//Fonctionne pour 1 calendrier...
	/*$(document).ready(function() {
		var htmlcal = $('.today').first().attr('href') + ' .calendar_content';
		var calheight = $('.today').first().parents('.calendar').height();
		$('.today').first().parents('.calendar').html('<div style="height:'+calheight+'px;background:transparent url(tools/wikical/presentation/images/loading.gif) no-repeat center center;"></div>').load(htmlcal);
		return false;
	});*/



	$(document).ready(function() {
		$('.cal_now').map(function( index ) {
			//console.log( index );
			var htmlcal = $(this).attr('href') + ' .calendar_content';
			var calheight = $(this).parents('.calendar').height();
			$(this).parents('.calendar').html('<div style="height:'+calheight+'px;background:transparent url(tools/wikical/presentation/images/loading.gif) no-repeat center center;"></div>').load(htmlcal);
			return false;
		})		
	});




	//Faire pour plusieurs...


	//liens pour se déplacer dans le calendrier
	$(".next_month, .prev_month, .today, .select_item").live('click', function() {
		var htmlcal = $(this).attr('href') + ' .calendar_content';
		var calheight = $(this).parents('.calendar').height();
		$(this).parents('.calendar').html('<div style="height:'+calheight+'px;background:transparent url(tools/wikical/presentation/images/loading.gif) no-repeat center center;"></div>').load(htmlcal);
		return false;
	});
	
	//listes déroulantes de sélection de date
	$(".select_annee, .select_mois").live('change', function() {
		var htmlcal = $(this).find("option:selected").val() + ' .calendar_content';
		var calheight = $(this).parents('.calendar').height();
		$(this).parents('.calendar').html('<div style="height:'+calheight+'px;background:transparent url(tools/wikical/presentation/images/loading.gif) no-repeat center center;"></div>').load(htmlcal);
		return false;
	});
});
