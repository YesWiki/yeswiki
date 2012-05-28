	
	/*
	written by chris wetherell
	http://www.massless.org
	chris [THE AT SIGN] massless.org
	warning: it only works for IE4+/Win and Moz1.1+
	feel free to take it for your site
	if there are any problems, let chris know.
	*/
	
	
	var ACEditor;    /* make sure to change the onload handler of the
	 <body> tag to the form you're using!... */
	
	function mozWrap(txtarea, lft, rgt) {
		
	     // mémorisation de la position du scroll
          oldPos = txtarea.scrollTop;
          oldHght = txtarea.scrollHeight;

          // calcul de la nouvelle position du curseur
          pos = txtarea.selectionEnd + lft.length + rgt.length;

          // calculs de la position de l'insertion
		
		var selLength = txtarea.textLength;
		var selStart = txtarea.selectionStart;
		var selEnd = txtarea.selectionEnd;
		if (selEnd==1 || selEnd==2) selEnd=selLength;
		var s1 = (txtarea.value).substring(0,selStart);
		var s2 = (txtarea.value).substring(selStart, selEnd)
		var s3 = (txtarea.value).substring(selEnd, selLength);
		txtarea.value = s1 + lft + s2 + rgt + s3;
		
		   // Placement du curseur après le tag fermant
          txtarea.selectionEnd = pos;

          // calcul et application de la nouvelle bonne postion du scroll
          newHght = txtarea.scrollHeight - oldHght;
          txtarea.scrollTop = oldPos + newHght;
          txtarea.focus();
		
	}
	
	function IEWrap(lft, rgt) {
		strSelection = document.selection.createRange().text;
		if (strSelection!="") {
		    document.selection.createRange().text = lft + strSelection + rgt;
		}
	}	
	// Cette fonction permet de faire fonctionner l'insertion de tag image dans un textarea de IE sans sélection initiale, 
	// à la position du curseur

	function IEWrap2(txtarea,lft, rgt) {
	    txtarea.focus();
    	if (document.selection) {
    		txtarea.focus();
    		sel = document.selection.createRange();
    		sel.text = lft+rgt;
    	}
	}
	
	function wrapSelection(txtarea, lft, rgt) {
		if (document.all) {IEWrap(lft, rgt);}
		else if (document.getElementById) {mozWrap(txtarea, lft, rgt);}
	}
	
	function wrapSelectionBis(txtarea, lft, rgt) { 
	    // pareil que la wrapSelection, avec une différence dans IE
	    // qui permet à wrapSelectionBis de pouvoir insérer à l'endroit du curseur même sans avoir sélectionné des caractères !!!
	    // Pour mozilla, c'est bien la fonction Wrap standard qui est appelée, aucun changement
	    
        if (document.all) { // document.all est une infamie de IE, on détecte cette horreur !
            IEWrap2(txtarea,lft, rgt); // Attention, un parametre de plus que IEWrap
        } else if (document.getElementById) {
            mozWrap(txtarea, lft, rgt); // là on est chez les gentils
        }	
	}	
	
	function wrapSelectionWithLink(txtarea) {
		var my_link = prompt("Entrez l'URL: ","http://");
		if (my_link != null) {
			lft="[[" + my_link + " ";
			rgt="]]";
			wrapSelection(txtarea, lft, rgt);
		}
		return;
	}
	/* Aaaxl modif for ACeditor */
	function wrapSelectionWithImage(txtarea) {	    
	    nom = document.ACEditor.filename.value;
	    descript = document.ACEditor.description.value;
	    align = document.ACEditor.alignment.value;

	    lft= "{{attach file=\"" + nom + "\" desc=\"" + descript + "\" class=\"" + align + "\" }}";
	    rgt = "";
        wrapSelectionBis(txtarea, lft, rgt);
		return;
	}	
	
	document.onkeypress = function (e) {
	  if (document.all) {
		key=event.keyCode; txtarea=thisForm.body;
		if (key == 1) wrapSelectionWithLink(txtarea);
		if (key == 2) wrapSelection(txtarea,'**','**');
		if (key == 20) wrapSelection(txtarea,'//','//');
	  }
	  else if (document.getElementById) {
	  	ctrl=e.ctrlKey; shft=e.shiftKey; chr=e.charCode;
	  	if (ctrl) if (shft) if (chr==65) wrapSelectionWithLink(thisForm.body);
	  	if (ctrl) if (shft) if (chr==66) wrapSelection(thisForm.body,'**','**');
	  	if (ctrl) if (shft) if (chr==84) wrapSelection(thisForm.body,'//','//');
	  	//if (ctrl) if (shft) if (chr==85) wrapSelection(thisForm.body,'__','__');
	  }
	  return true;
	}
	/* end chris w. script */
	

	
	/*
	written by meg hourihan
	http://www.megnut.com
	meg@megnut.com
	
	warning: it only works for IE4+/Win and Moz1.1+
	feel free to take it for your site
	but leave this text in place.
	any problems, let meg know.
	*/
	function mouseover(el) {
		el.className = "raise";
	}	
	function mouseout(el) {
		el.className = "buttons";
	}	
	function mousedown(el) {
		el.className = "press";
	}	
	function mouseup(el) {
		el.className = "raise";
	}
	/* end meg script */