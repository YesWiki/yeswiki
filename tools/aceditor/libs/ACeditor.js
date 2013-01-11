/*
written by chris wetherell odernised by Florian Schmitt
http://www.massless.org
chris [THE AT SIGN] massless.org
warning: it only works for IE4+/Win and Moz1.1+
feel free to take it for your site
if there are any problems, let chris know.
*/

;function wrapSelection(txtarea, lft, rgt, prompt) { 
    // pareil que la wrapSelection, avec une différence dans IE
    // qui permet à wrapSelection de pouvoir insérer à l'endroit du curseur même sans avoir sélectionné des caractères !!!
    // Pour mozilla, c'est bien la fonction Wrap standard qui est appelée, aucun changement
    
    if (document.all) { // document.all est une infamie de IE, on détecte cette horreur !  
        strSelection = document.selection.createRange().text;       
		if (strSelection!="") {
		    document.selection.createRange().text = lft + strSelection + rgt;
		    txtarea.focus();
		}
    	else if (prompt && document.selection) {
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
    return true;
}	
/* end chris w. script */


;(function ( $, window, undefined ) {
  // Create the defaults once
  var pluginName = 'aceditor',
      document = window.document,
      defaults = {
        savebtn: false,
        lang: 'fr',
        syntax: 'yeswiki'
      },
      lang = { 
      	fr : {
	      	'ACEDITOR_SAVE'				: 'Sauver',
	      	'ACEDITOR_FORMAT'			: 'Format',
	      	'ACEDITOR_TITLE1'			: 'En-t&ecirc;te &eacute;norme',
	      	'ACEDITOR_TITLE2'			: 'En-t&ecirc;te tr&egrave;s gros',
	      	'ACEDITOR_TITLE3'			: 'En-t&ecirc;te gros',
	      	'ACEDITOR_TITLE4'			: 'En-t&ecirc;te normal',
	      	'ACEDITOR_TITLE5'			: 'Petit en-t&ecirc;te',
	      	'ACEDITOR_BIGGER_TEXT'		: 'Texte agrandi',
	      	'ACEDITOR_HIGHLIGHT_TEXT'	: 'Texte mis en valeur',
	      	'ACEDITOR_SOURCE_CODE'		: 'Code source',
	      	'ACEDITOR_BOLD_TEXT'		: 'Passe le texte s&eacute;lectionn&eacute; en gras  ( Ctrl-b )',
	      	'ACEDITOR_ITALIC_TEXT'		: 'Passe le texte s&eacute;lectionn&eacute; en italique ( Ctrl-i )',
	      	'ACEDITOR_UNDERLINE_TEXT'	: 'Souligne le texte s&eacute;lectionn&eacute; ( Ctrl-u )',
	      	'ACEDITOR_STRIKE_TEXT'		: 'Barre le texte s&eacute;lectionn&eacute; ( Ctrl-y )',
	      	'ACEDITOR_LINE'				: 'Ins&egrave;re une ligne horizontale ( Ctrl-h )',
	      	'ACEDITOR_LINK'				: 'Lien',
	      	'ACEDITOR_LINK_PROMPT'		: 'Entrez l\'adresse URL',
	      	'ACEDITOR_LINK_TITLE'		: 'Ajoute un lien au texte s&eacute;lectionn&eacute; ( Ctrl-l )'
	      },
	    en : {
	    	'ACEDITOR_SAVE'				: 'Save',
	      	'ACEDITOR_FORMAT'			: 'Format',
	      	'ACEDITOR_TITLE1'			: 'Huge title',
	      	'ACEDITOR_TITLE2'			: 'Very big title',
	      	'ACEDITOR_TITLE3'			: 'Big title',
	      	'ACEDITOR_TITLE4'			: 'Basic title',
	      	'ACEDITOR_TITLE5'			: 'Small title',
	      	'ACEDITOR_BIGGER_TEXT'		: 'Bigger text',
	      	'ACEDITOR_HIGHLIGHT_TEXT'	: 'Highlighted text',
	      	'ACEDITOR_SOURCE_CODE'		: 'Source code',
	      	'ACEDITOR_BOLD_TEXT'		: 'Bold text ( Ctrl-b )',
	      	'ACEDITOR_ITALIC_TEXT'		: 'Italic text ( Ctrl-i )',
	      	'ACEDITOR_UNDERLINE_TEXT'	: 'Underline the selected text ( Ctrl-u )',
	      	'ACEDITOR_STRIKE_TEXT'		: 'Stroke the selected text ( Ctrl-y )',
	      	'ACEDITOR_LINE'				: 'Insert horizontal line ( Ctrl-h )',
	      	'ACEDITOR_LINK'				: 'Link',
	      	'ACEDITOR_LINK_PROMPT'		: 'Enter the link adress',
	      	'ACEDITOR_LINK_TITLE'		: 'Add a link to selected text ( Ctrl-l )'
	    }
	  },
	  syntax = { 
      	yeswiki : {
	      	'TITLE1_LFT'			: '======',
	      	'TITLE1_RGT'			: '======',
	      	'TITLE2_LFT'			: '=====',
	      	'TITLE2_RGT'			: '=====',
	      	'TITLE3_LFT'			: '====',
	      	'TITLE3_RGT'			: '====',
	      	'TITLE4_LFT'			: '===',
	      	'TITLE4_RGT'			: '===',
	      	'TITLE5_LFT'			: '==',
	      	'TITLE5_RGT'			: '==',
	      	'LEAD_LFT'				: '&quot;&quot;<div class=&quot;lead&quot;>&quot;&quot;',
	      	'LEAD_RGT'				: '&quot;&quot;</div>&quot;&quot;',
	      	'HIGHLIGHT_LFT'			: '&quot;&quot;<div class=&quot;well&quot;>&quot;&quot;',
	      	'HIGHLIGHT_RGT'			: '&quot;&quot;</div>&quot;&quot;',	      	
	      	'CODE_LFT'				: '%%',
	      	'CODE_RGT'				: '%%',
	      	'BOLD_LFT'				: '**',
	      	'BOLD_RGT'				: '**',
	      	'ITALIC_LFT'			: '//',
	      	'ITALIC_RGT'			: '//',
	      	'UNDERLINE_LFT'			: '__',
	      	'UNDERLINE_RGT'			: '__',
	      	'STRIKE_LFT'			: '@@',
	      	'STRIKE_RGT'			: '@@',
	      	'LINE_LFT'				: '\n------\n',
	      	'LINE_RGT'				: '',
	      	'LINK_LFT'				: '[[',
	      	'LINK_RGT'				: ']]'
	      },
	    html : {
	      	'TITLE1_LFT'			: '<h1>',
	      	'TITLE1_RGT'			: '</h1>',
	      	'TITLE2_LFT'			: '<h2>',
	      	'TITLE2_RGT'			: '</h2>',
	      	'TITLE3_LFT'			: '<h3>',
	      	'TITLE3_RGT'			: '</h3>',
	      	'TITLE4_LFT'			: '<h4>',
	      	'TITLE4_RGT'			: '</h4>',
	      	'TITLE5_LFT'			: '<h5>',
	      	'TITLE5_RGT'			: '</h5>',
	      	'LEAD_LFT'				: '<div class=&quot;lead&quot;>',
	      	'LEAD_RGT'				: '</div>',
	      	'HIGHLIGHT_LFT'			: '<div class=&quot;well&quot;>',
	      	'HIGHLIGHT_RGT'			: '</div>',	      	
	      	'CODE_LFT'				: '<pre>',
	      	'CODE_RGT'				: '</pre>',
	      	'BOLD_LFT'				: '<strong>',
	      	'BOLD_RGT'				: '</strong>',
	      	'ITALIC_LFT'			: '<em>',
	      	'ITALIC_RGT'			: '</em>',
	      	'UNDERLINE_LFT'			: '__',
	      	'UNDERLINE_RGT'			: '__',
	      	'STRIKE_LFT'			: '@@',
	      	'STRIKE_RGT'			: '@@',
	      	'LINE_LFT'				: '<hr />',
	      	'LINE_RGT'				: '',
	      	'LINK_LFT'				: '<a href=&quot;#&quot;>',
	      	'LINK_RGT'				: '</a>'
	      }
	  };

	

  // The actual plugin constructor
  function Plugin( element, options ) {
    this.element = element;

    // jQuery has an extend method which merges the contents of two or 
    // more objects, storing the result in the first object. The first object
    // is generally empty as we don't want to alter the default options for
    // future instances of the plugin
    this.options = $.extend( {}, defaults, options) ;
    
    this.lang = lang;
    this.syntax = syntax;

    this._defaults = defaults;
    this._name = pluginName;

    // gestion du multilinguisme
	var htmllang = $('html').attr('lang');
	if (htmllang !== 'undefined' && htmllang in this.lang) {
	  	this.options.lang = htmllang;
	} else {
	  	this.options.lang = 'fr';
	}

    this.init();
  }

  Plugin.prototype.init = function () {
    // Place initialization logic here
    // You already have access to the DOM element and the options via the instance, 
    // e.g., this.element and this.options
    if (this.element.tagName === 'TEXTAREA') {
    	var toolbar = $('<div>').addClass("btn-toolbar aceditor-toolbar");
    	if (this.options.savebtn) {
    		toolbar.append($('<div class="btn-group"><button type="submit" name="submit" value="Sauver" class="aceditor-btn-save btn btn-primary">'+this.lang[this.options.lang]['ACEDITOR_SAVE']+'</button></div>'));
    	}

    	// Format du texte pour les titres
    	toolbar.append(	'<div class="btn-group">' +
							'<a class="btn dropdown-toggle" data-toggle="dropdown" href="#">'+this.lang[this.options.lang]['ACEDITOR_FORMAT']+'&nbsp;&nbsp;<span class="caret"></span></a>' +
							'<ul class="dropdown-menu">' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_TITLE1']+'" class="aceditor-btn aceditor-btn-title1" data-lft="'+this.syntax[this.options.syntax]['TITLE1_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['TITLE1_RGT']+'"><h1>'+this.lang[this.options.lang]['ACEDITOR_TITLE1']+'</h1></a></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_TITLE2']+'" class="aceditor-btn aceditor-btn-title2" data-lft="'+this.syntax[this.options.syntax]['TITLE2_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['TITLE2_RGT']+'"><h2>'+this.lang[this.options.lang]['ACEDITOR_TITLE2']+'</h2></a></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_TITLE3']+'" class="aceditor-btn aceditor-btn-title3" data-lft="'+this.syntax[this.options.syntax]['TITLE3_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['TITLE3_RGT']+'"><h3>'+this.lang[this.options.lang]['ACEDITOR_TITLE3']+'</h3></a></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_TITLE4']+'" class="aceditor-btn aceditor-btn-title4" data-lft="'+this.syntax[this.options.syntax]['TITLE4_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['TITLE4_RGT']+'"><h4>'+this.lang[this.options.lang]['ACEDITOR_TITLE4']+'</h4></a></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_TITLE5']+'" class="aceditor-btn aceditor-btn-title5" data-lft="'+this.syntax[this.options.syntax]['TITLE5_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['TITLE5_RGT']+'"><h5>'+this.lang[this.options.lang]['ACEDITOR_TITLE5']+'</h5></a></li>' +
								'<li class="divider"></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_BIGGER_TEXT']+'" class="aceditor-btn aceditor-btn-lead" data-lft="'+this.syntax[this.options.syntax]['LEAD_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['LEAD_RGT']+'"><div class="lead">'+this.lang[this.options.lang]['ACEDITOR_BIGGER_TEXT']+'</div></a></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_HIGHLIGHT_TEXT']+'" class="aceditor-btn aceditor-btn-well" data-lft="'+this.syntax[this.options.syntax]['HIGHLIGHT_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['HIGHLIGHT_RGT']+'"><div class="well">'+this.lang[this.options.lang]['ACEDITOR_HIGHLIGHT_TEXT']+'</div></a></li>' +
								'<li><a title="'+this.lang[this.options.lang]['ACEDITOR_SOURCE_CODE']+'" class="aceditor-btn aceditor-btn-code" data-lft="'+this.syntax[this.options.syntax]['CODE_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['CODE_RGT']+'"><div class="code"><pre>'+this.lang[this.options.lang]['ACEDITOR_SOURCE_CODE']+'</pre></div></a></li>' +
							'</ul>' +
						'</div>');
			
	    // Gras italique souligné barré
    	toolbar.append(	'<div class="btn-group">' +
							'<a class="btn aceditor-btn aceditor-btn-bold" data-lft="'+this.syntax[this.options.syntax]['BOLD_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['BOLD_RGT']+'" title="'+this.lang[this.options.lang]['ACEDITOR_BOLD_TEXT']+'">' +
								'<span style="font-family:serif;font-weight:bold;">B</span>' +
							'</a>' +
							'<a class="btn aceditor-btn aceditor-btn-italic" data-lft="'+this.syntax[this.options.syntax]['ITALIC_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['ITALIC_RGT']+'" title="'+this.lang[this.options.lang]['ACEDITOR_ITALIC_TEXT']+'">' +
								'<span style="font-family:serif;font-style:italic;">I</span>' +
							'</a>' +
							'<a class="btn aceditor-btn aceditor-btn-underline" data-lft="'+this.syntax[this.options.syntax]['UNDERLINE_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['UNDERLINE_RGT']+'" title="'+this.lang[this.options.lang]['ACEDITOR_UNDERLINE_TEXT']+'">' +
								'<span style="font-family:serif;text-decoration:underline;">U</span>' +
							'</a>' +
							'<a class="btn aceditor-btn aceditor-btn-strike" data-lft="'+this.syntax[this.options.syntax]['STRIKE_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['STRIKE_RGT']+'" title="'+this.lang[this.options.lang]['ACEDITOR_STRIKE_TEXT']+'">' +
								'<span style="font-family:serif;text-decoration:line-through;">S</span>' +
							'</a>' +
						'</div>');

	    // Ligne horizontale et liens
    	toolbar.append(	'<div class="btn-group">' +
							'<a class="btn aceditor-btn aceditor-btn-line" data-lft="'+this.syntax[this.options.syntax]['LINE_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['LINE_RGT']+'" title="'+this.lang[this.options.lang]['ACEDITOR_LINE']+'">' +
								'<i class="icon-minus"></i>' +
							'</a>' +
							'<a class="btn aceditor-btn aceditor-btn-link" data-prompt="' + this.lang[this.options.lang]['ACEDITOR_LINK_PROMPT'] + '" data-prompt-val="http://" data-lft="'+this.syntax[this.options.syntax]['LINK_LFT']+'" data-rgt="'+this.syntax[this.options.syntax]['LINK_RGT']+'" title="' + this.lang[this.options.lang]['ACEDITOR_LINK_TITLE'] + '" class="btn">' +
								'<i class="icon-share-alt"></i>&nbsp;' + this.lang[this.options.lang]['ACEDITOR_LINK'] +
							'</a>' +
						'</div>');

    	// On affecte les boutons
    	toolbar.find('a.aceditor-btn').each(function() {
    		$(this).on('click', function(e){
				return setTimeout('', 300);
    		}).on('mousedown', function(e){
    			if ($(this).data('prompt')) {
    				var prompt = window.prompt($(this).data('prompt'), $(this).data('prompt-val'));
    				if (prompt != null) {
						wrapSelection(textarea[0], $(this).data('lft') + prompt + " ", $(this).data('rgt'), true);
					} 
    			}
				else {
					wrapSelection(textarea[0], $(this).data('lft'), $(this).data('rgt'), false);
				}
    			return setTimeout('', 300);
    		})
    	});

    	// Affichage de la barre juste avant le textarea
    	var textarea = $(this.element);
    	textarea.before(toolbar);

    	// Gestion des raccourcis claviers
    	var isCtrl = false;
    	var isAlt = false;

    	textarea.keyup(function(e) {
    		var keyCode = e.which;
    		if (keyCode == 17) {
				isCtrl = false;
			}
			if (keyCode == 18) {
				isAlt = false;
			}
    	});

    	textarea.keydown(function(e) {
    		var keyCode = e.which;
			if (keyCode == 17) {
				isCtrl = true;
			}
			if (keyCode == 18) {
				isAlt = true;
			}
			if (isCtrl == true && isAlt == false) {
				// title 1
				if (keyCode == 49) {
					$('.aceditor-btn-title1').mousedown();e.preventDefault();
				}
				// title 2
				else if (keyCode == 50) {
					$('.aceditor-btn-title2').mousedown();e.preventDefault();
				}
				// title 3
				else if (keyCode == 51) {
					$('.aceditor-btn-title3').mousedown();e.preventDefault();
				}
				// title 4
				else if (keyCode == 52) {
					$('.aceditor-btn-title4').mousedown();e.preventDefault();
				}
				// title 5
				else if (keyCode == 53) {
					$('.aceditor-btn-title5').mousedown();e.preventDefault();
				}
				// bold
				else if (keyCode == 66) {
					$('.aceditor-btn-bold').mousedown();e.preventDefault();
				}
				// italic
				else if (keyCode == 73) {
					$('.aceditor-btn-italic').mousedown();e.preventDefault();
				}
				// underline
				else if (keyCode == 85) {
					$('.aceditor-btn-underline').mousedown();e.preventDefault();
				}
				// strike
				else if (keyCode == 89) {
					$('.aceditor-btn-strike').mousedown();e.preventDefault();
				}
				// line
				else if (keyCode == 72) {
					$('.aceditor-btn-line').mousedown();e.preventDefault();
				}
				// link
				else if (keyCode == 76) {
					$('.aceditor-btn-link').mousedown();e.preventDefault(); isCtrl = false; isAlt = false;
				}
				// save page
				else if (keyCode == 83) {
			    	$('.aceditor-btn-save').click();e.preventDefault();
			    }
			    return;
			}
		});
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
$('.html-textarea').aceditor({syntax:'html'});