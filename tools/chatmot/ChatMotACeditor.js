	
	/* debut David modif for ACeditor */
	function wrapSelectionWithPage(txtarea) {	    
	    nouvellepage = document.ACEditor.nouvellepage.value;
	    chatmot=camelcase(nouvellepage);
	  
	    lft= "[[" + chatmot + " " + nouvellepage + "]]";
	    rgt = "";
        wrapSelectionBis(txtarea, lft, rgt);
		return;
	}	
	
	function nettoie(totrim) {

		
		var result = AccentToNoAccent(totrim); 		
		result = result.replace( /[^0-9a-zA-Z]/g , ""); // suppression caractere non autorise
		result = result.replace( /^\s+/g, "" );// strip leading
		return result.replace( /\s+$/g, "" );// strip trailing
	}
	
	function camelcase( s ) { 
	s = nettoie( s );
	s = " "+s;
	return  s.replace( /( )([0-9a-zA-Z])/g,
	function(t,a,b) { return b.toUpperCase(); } ); }
	

	// Remplace toutes les occurences d'une chaine
	function replaceAll(str, search, repl) {
	while (str.indexOf(search) != -1)
	str = str.replace(search, repl);
	return str;
	}
	
		
	
	// Remplace les caractères accentués
	function AccentToNoAccent(str) {
	var norm = new Array('À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï', 'Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','Þ','ß', 'à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ð','ñ', 'ò','ó','ô','õ','ö','ø','ù','ú','û','ü','ý','ý','þ','ÿ');
	var spec = new Array('A','A','A','A','A','A','A','C','E','E','E','E','I','I','I','I', 'D','N','O','O','O','0','O','O','U','U','U','U','Y','b','s', 'a','a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','d','n', 'o','o','o','o','o','o','u','u','u','u','y','y','b','y');
	for (var i = 0; i < spec.length; i++)
	str = replaceAll(str, norm[i], spec[i]);
	return str;
	} 
	/* fin David modif for ACeditor */


	
	