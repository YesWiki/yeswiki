/*
 Rangy Text Inputs, a cross-browser textarea and text input library plug-in for jQuery.
 http://code.google.com/p/rangyinputs/
*/
;(function($){var UNDEF="undefined";var getSelection,setSelection,deleteSelectedText,deleteText,insertText;var replaceSelectedText,surroundSelectedText,extractSelectedText,collapseSelection;function isHostMethod(object,property){var t=typeof object[property];return t==="function"||!!(t=="object"&&object[property])||t=="unknown"}function isHostProperty(object,property){return typeof object[property]!=UNDEF}function isHostObject(object,property){return!!(typeof object[property]=="object"&&object[property])}
function fail(reason){if(window.console&&window.console.log)window.console.log("RangyInputs not supported in your browser. Reason: "+reason)}function adjustOffsets(el,start,end){if(start<0)start+=el.value.length;if(typeof end==UNDEF)end=start;if(end<0)end+=el.value.length;return{start:start,end:end}}function makeSelection(el,start,end){return{start:start,end:end,length:end-start,text:el.value.slice(start,end)}}function getBody(){return isHostObject(document,"body")?document.body:document.getElementsByTagName("body")[0]}
$(document).ready(function(){var testTextArea=document.createElement("textarea");getBody().appendChild(testTextArea);if(isHostProperty(testTextArea,"selectionStart")&&isHostProperty(testTextArea,"selectionEnd")){getSelection=function(el){var start=el.selectionStart,end=el.selectionEnd;return makeSelection(el,start,end)};setSelection=function(el,startOffset,endOffset){var offsets=adjustOffsets(el,startOffset,endOffset);el.selectionStart=offsets.start;el.selectionEnd=offsets.end};collapseSelection=
function(el,toStart){if(toStart)el.selectionEnd=el.selectionStart;else el.selectionStart=el.selectionEnd}}else if(isHostMethod(testTextArea,"createTextRange")&&isHostObject(document,"selection")&&isHostMethod(document.selection,"createRange")){getSelection=function(el){var start=0,end=0,normalizedValue,textInputRange,len,endRange;var range=document.selection.createRange();if(range&&range.parentElement()==el){len=el.value.length;normalizedValue=el.value.replace(/\r\n/g,"\n");textInputRange=el.createTextRange();
textInputRange.moveToBookmark(range.getBookmark());endRange=el.createTextRange();endRange.collapse(false);if(textInputRange.compareEndPoints("StartToEnd",endRange)>-1)start=end=len;else{start=-textInputRange.moveStart("character",-len);start+=normalizedValue.slice(0,start).split("\n").length-1;if(textInputRange.compareEndPoints("EndToEnd",endRange)>-1)end=len;else{end=-textInputRange.moveEnd("character",-len);end+=normalizedValue.slice(0,end).split("\n").length-1}}}return makeSelection(el,start,end)};
var offsetToRangeCharacterMove=function(el,offset){return offset-(el.value.slice(0,offset).split("\r\n").length-1)};setSelection=function(el,startOffset,endOffset){var offsets=adjustOffsets(el,startOffset,endOffset);var range=el.createTextRange();var startCharMove=offsetToRangeCharacterMove(el,offsets.start);range.collapse(true);if(offsets.start==offsets.end)range.move("character",startCharMove);else{range.moveEnd("character",offsetToRangeCharacterMove(el,offsets.end));range.moveStart("character",
startCharMove)}range.select()};collapseSelection=function(el,toStart){var range=document.selection.createRange();range.collapse(toStart);range.select()}}else{getBody().removeChild(testTextArea);fail("No means of finding text input caret position");return}getBody().removeChild(testTextArea);deleteText=function(el,start,end,moveSelection){var val;if(start!=end){val=el.value;el.value=val.slice(0,start)+val.slice(end)}if(moveSelection)setSelection(el,start,start)};deleteSelectedText=function(el){var sel=
getSelection(el);deleteText(el,sel.start,sel.end,true)};extractSelectedText=function(el){var sel=getSelection(el),val;if(sel.start!=sel.end){val=el.value;el.value=val.slice(0,sel.start)+val.slice(sel.end)}setSelection(el,sel.start,sel.start);return sel.text};insertText=function(el,text,index,moveSelection){var val=el.value,caretIndex;el.value=val.slice(0,index)+text+val.slice(index);if(moveSelection){caretIndex=index+text.length;setSelection(el,caretIndex,caretIndex)}};replaceSelectedText=function(el,
text){var sel=getSelection(el),val=el.value;el.value=val.slice(0,sel.start)+text+val.slice(sel.end);var caretIndex=sel.start+text.length;setSelection(el,caretIndex,caretIndex)};surroundSelectedText=function(el,before,after){if(typeof after==UNDEF)after=before;var sel=getSelection(el),val=el.value;el.value=val.slice(0,sel.start)+before+sel.text+after+val.slice(sel.end);var startIndex=sel.start+before.length;var endIndex=startIndex+sel.length;setSelection(el,startIndex,endIndex)};function jQuerify(func,
returnThis){return function(){var el=this.jquery?this[0]:this;var nodeName=el.nodeName.toLowerCase();if(el.nodeType==1&&(nodeName=="textarea"||nodeName=="input"&&el.type=="text")){var args=[el].concat(Array.prototype.slice.call(arguments));var result=func.apply(this,args);if(!returnThis)return result}if(returnThis)return this}}$.fn.extend({getSelection:jQuerify(getSelection,false),setSelection:jQuerify(setSelection,true),collapseSelection:jQuerify(collapseSelection,true),deleteSelectedText:jQuerify(deleteSelectedText,
true),deleteText:jQuerify(deleteText,true),extractSelectedText:jQuerify(extractSelectedText,false),insertText:jQuerify(insertText,true),replaceSelectedText:jQuerify(replaceSelectedText,true),surroundSelectedText:jQuerify(surroundSelectedText,true)});$.fn.rangyInputs={getSelection:getSelection,setSelection:setSelection,collapseSelection:collapseSelection,deleteSelectedText:deleteSelectedText,deleteText:deleteText,extractSelectedText:extractSelectedText,insertText:insertText,replaceSelectedText:replaceSelectedText,
surroundSelectedText:surroundSelectedText}})})(jQuery);


/*
 Aceditor, rewrited for YesWiki from Florian Schmitt <florian@outils-reseaux.org>
*/
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

    	var lastFocus;
    	// On affecte les boutons
    	toolbar.find('a.aceditor-btn').each(function() {
    		$(this).on('click', function(e){
    			e.preventDefault();
			    e.stopPropagation();
			    if (lastFocus) {
			        setTimeout(function() {lastFocus.focus()}, 10);
			    }
			    return(false);
    		}).on('mousedown', function(e){
    			e.preventDefault();
			    e.stopPropagation();
    			if ($(this).data('prompt')) {
    				var prompt = window.prompt($(this).data('prompt'), $(this).data('prompt-val'));
    				if (prompt != null) {
						textarea.surroundSelectedText($(this).data('lft') + prompt + " ", $(this).data('rgt'), true);
					} 
    			}
				else {
					textarea.surroundSelectedText($(this).data('lft'), $(this).data('rgt'), true);
				}
				if (lastFocus) {
			        setTimeout(function() {lastFocus.focus()}, 10);
			    }
			    return(false);
    		})
    	});

    	// Affichage de la barre juste avant le textarea
    	var textarea = $(this.element);
    	textarea.before(toolbar);

    	// Test du blur
		textarea.blur(function() {
		    lastFocus = textarea;
		});

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