	
	/*
	written by chris wetherell
	http://www.massless.org
	chris [THE AT SIGN] massless.org
	warning: it only works for IE4+/Win and Moz1.1+
	feel free to take it for your site
	if there are any problems, let chris know.
	*/
	
	function wrapSelectionBis(txtarea, lft, rgt) { 
	    // pareil que la wrapSelection, avec une différence dans IE
	    // qui permet à wrapSelectionBis de pouvoir insérer à l'endroit du curseur même sans avoir sélectionné des caractères !!!
	    // Pour mozilla, c'est bien la fonction Wrap standard qui est appelée, aucun changement
	    
        if (document.all) { // document.all est une infamie de IE, on détecte cette horreur !
            txtarea.focus();
	    	if (document.selection) {
	    		txtarea.focus();
	    		sel = document.selection.createRange();
	    		sel.text = lft+rgt;
	    	}
        } 
        else if (document.getElementById) {
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
	}	
	
	function wrapSelectionWithLink(txtarea) {
		var my_link = prompt("Entrez l'URL: ","http://");
		if (my_link != null) {
			lft="[[" + my_link + " ";
			rgt="]]";
			wrapSelectionBis(txtarea, lft, rgt);
		}
		return;
	}
	
	document.onkeypress = function (e) {
	  if (document.all) {
		key=event.keyCode; txtarea=thisForm.body;
		if (key == 1) wrapSelectionWithLink(txtarea);
		if (key == 2) wrapSelectionBis(txtarea,'**','**');
		if (key == 20) wrapSelectionBis(txtarea,'//','//');
	  }
	  else if (document.getElementById) {
	  	ctrl=e.ctrlKey; shft=e.shiftKey; chr=e.charCode;
	  	if (ctrl) if (shft) if (chr==65) wrapSelectionWithLink(thisForm.body);
	  	if (ctrl) if (shft) if (chr==66) wrapSelectionBis(thisForm.body,'**','**');
	  	if (ctrl) if (shft) if (chr==84) wrapSelectionBis(thisForm.body,'//','//');
	  	//if (ctrl) if (shft) if (chr==85) wrapSelectionBis(thisForm.body,'__','__');
	  }
	  return true;
	}
	/* end chris w. script */


;(function ( $, window, undefined ) {
  // Create the defaults once
  var pluginName = 'aceditor',
      document = window.document,
      defaults = {
        savebtn: false
      };

  // The actual plugin constructor
  function Plugin( element, options ) {
    this.element = element;

    // jQuery has an extend method which merges the contents of two or 
    // more objects, storing the result in the first object. The first object
    // is generally empty as we don't want to alter the default options for
    // future instances of the plugin
    this.options = $.extend( {}, defaults, options) ;

    this._defaults = defaults;
    this._name = pluginName;

    this.init();
  }

  Plugin.prototype.init = function () {
    // Place initialization logic here
    // You already have access to the DOM element and the options via the instance, 
    // e.g., this.element and this.options
    if (this.element.tagName === 'TEXTAREA') {
    	var toolbar = $('<div>').addClass("btn-toolbar aceditor-toolbar");
    	if (this.options.savebtn) {
    		toolbar.append($('<div class="btn-group"><button type="submit" name="submit" value="Sauver" class="btn btn-primary">Sauver</button></div>'));
    	}

    	// Format du texte pour les titres
    	toolbar.append(	'<div class="btn-group">' +
							'<a class="btn dropdown-toggle" data-toggle="dropdown" href="#">Format&nbsp;&nbsp;<span class="caret"></span></a>' +
							'<ul class="dropdown-menu">' +
								'<li><a title="En-tête énorme" class="aceditor-btn" data-lft="======" data-rgt="======"><h1>En-tête énorme</h1></a></li>' +
								'<li><a title="En-tête très gros" class="aceditor-btn" data-lft="=====" data-rgt="====="><h2>En-tête très gros</h2></a></li>' +
								'<li><a title="En-tête gros" class="aceditor-btn" data-lft="====" data-rgt="===="><h3>En-tête gros</h3></a></li>' +
								'<li><a title="En-tête normal" class="aceditor-btn" data-lft="===" data-rgt="==="><h4>En-tête normal</h4></a></li>' +
								'<li><a title="Petit en-tête" class="aceditor-btn" data-lft="==" data-rgt="=="><h5>Petit en-tête</h5></a></li>' +
								'<li class="divider"></li>' +
								'<li><a title="Texte agrandi" class="aceditor-btn" data-lft="&quot;&quot;<div class=&quot;lead&quot;>&quot;&quot;=" data-rgt="&quot;&quot;</div>&quot;&quot;"><div class="lead">Texte agrandi</div></a></li>' +
								'<li><a title="Mis en valeur" class="aceditor-btn" data-lft="&quot;&quot;<div class=&quot;well&quot;>&quot;&quot;" data-rgt="&quot;&quot;</div>&quot;&quot;"><div class="well">Texte mis en valeur</div></a></li>' +
								'<li><a title="Code" class="aceditor-btn" data-lft="%%" data-rgt="%%"><div class="code"><pre>Code source</pre></div></a></li>' +
							'</ul>' +
						'</div>');
			
	    // Gras italique souligné barré
    	toolbar.append(	'<div class="btn-group">' +
							'<a class="btn aceditor-btn" data-lft="**" data-rgt="**" title="Passe le texte sélectionné en gras  ( Ctrl-Maj-b )">' +
								'<span style="font-family:serif;font-weight:bold;">B</span>' +
							'</a>' +
							'<a class="btn aceditor-btn" data-lft="//" data-rgt="//" title="Passe le texte sélectionné en italique ( Ctrl-Maj-t )">' +
								'<span style="font-family:serif;font-style:italic;">I</span>' +
							'</a>' +
							'<a class="btn aceditor-btn" data-lft="__" data-rgt="__" title="Souligne le texte sélectionné ( Ctrl-Maj-u )">' +
								'<span style="font-family:serif;text-decoration:underline;">U</span>' +
							'</a>' +
							'<a class="btn aceditor-btn" data-lft="@@" data-rgt="@@" title="Barre le texte sélectionné">' +
								'<span style="font-family:serif;text-decoration:line-through;">S</span>' +
							'</a>' +
						'</div>');

	    // Ligne horizontale et liens
    	toolbar.append(	'<div class="btn-group">' +
							'<a class="btn aceditor-btn" data-lft="\n------" data-rgt="" title="Insère une ligne horizontale">' +
								'<i class="icon-minus"></i>' +
							'</a>' +
							'<a class="btn aceditor-btn" data-prompt="Entrez l\'adresse URL" data-prompt-val="http://" data-lft="[[" data-rgt="]]" title="Ajoute un lien au texte sélectionné" class="btn">' +
								'<i class="icon-share-alt"></i>&nbsp;Lien' +
							'</a>' +
						'</div>');

    	// Affichage de la barre juste avant le textarea
    	//this.element.insertAdjacentHTML("BeforeBegin", toolbar.html() );
    	//this.element.parentNode.insertBefore(toolbar.get(0),this.element);

    	// on affecte les boutons
    	toolbar.find('a.aceditor-btn').each(function() {
    		$(this).on('click', function(){
    			var prompt;
    			if ($(this).data('prompt')) {
    				prompt = window.prompt($(this).data('prompt'), $(this).data('prompt-val'));
    				if (prompt != null) {
						wrapSelectionBis(textarea[0], $(this).data('lft') + prompt + " ", $(this).data('rgt'));
					} 
    			}
				else {
					wrapSelectionBis(textarea[0], $(this).data('lft'), $(this).data('rgt'));
				}
    		})
    	});
    	var textarea = $(this.element);
    	textarea.before(toolbar);
    }

  };

  // A really lightweight plugin wrapper around the constructor, 
  // preventing against multiple instantiations
  $.fn[pluginName] = function ( options ) {
    return this.each(function () {
      if (!$.data(this, 'plugin_' + pluginName)) {
        $.data(this, 'plugin_' + pluginName, new Plugin( this, options ));
      }
    });
  }

}(jQuery, window));

// Initialisation pour le mode édition
$('#body').aceditor({savebtn : true});

// Initialisation pour les commentaires, et textelongs bazar
$('.wiki-textarea, .commentform textarea').aceditor();