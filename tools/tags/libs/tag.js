$(document).ready(function() {
	$('.tag-label').hover(function(){
		$(this).addClass('label-primary');
		$(this).removeClass('label-info');
	}, function(){
		if(!$(this).hasClass('label-active')){
			$(this).addClass('label-info');
			$(this).removeClass('label-primary');
		}
	});

	//nuage de mots clés : tooltip
	$('.tag-link').popover({html:true,placement:'top',trigger:'click'})
	$('.nuage').on( "click", ".btn-close-popover",  function() {
		$(this).parents('.popover').prev('.tag-link').popover('hide'); 
		return false;
	});

	//nettoyage des formulaires
	$.fn.clearForm = function() {
	    return this.each(function() {
	      var type = this.type, tag = this.tagName.toLowerCase();
	      if (tag == 'form')
	        return $(':input',this).clearForm();
	      if (type == 'text' || type == 'password' || tag == 'textarea')
	        this.value = '';
	      else if (type == 'checkbox' || type == 'radio')
	        this.checked = false;
	      else if (tag == 'select')
	        this.selectedIndex = -1;
	    });
	};
});