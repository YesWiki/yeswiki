jQuery(function(){

//menu de gauche avec accordeon
//	$("#sidebar ul ul").hide();                                                                                                                         
	$("#sidebar ul ul").prev("a").bind("click", function(event) {
	 	window.location = this.href;  
		$(this).next("ul").toggle('fast');
		return false;
	});
	$("#sidebar ul ul ul").prev("a").unbind('click');
});
