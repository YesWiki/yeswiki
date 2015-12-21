/**
* hoverIntent r6 // 2011.02.26 // jQuery 1.5.1+
* <http://cherne.net/brian/resources/jquery.hoverIntent.html>
* 
* @param  f  onMouseOver function || An object with configuration options
* @param  g  onMouseOut function  || Nothing (use configuration options object)
* @author    Brian Cherne brian(at)cherne(dot)net
*/
(function($){$.fn.hoverIntent=function(f,g){var cfg={sensitivity:7,interval:100,timeout:0};cfg=$.extend(cfg,g?{over:f,out:g}:f);var cX,cY,pX,pY;var track=function(ev){cX=ev.pageX;cY=ev.pageY};var compare=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);if((Math.abs(pX-cX)+Math.abs(pY-cY))<cfg.sensitivity){$(ob).unbind("mousemove",track);ob.hoverIntent_s=1;return cfg.over.apply(ob,[ev])}else{pX=cX;pY=cY;ob.hoverIntent_t=setTimeout(function(){compare(ev,ob)},cfg.interval)}};var delay=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);ob.hoverIntent_s=0;return cfg.out.apply(ob,[ev])};var handleHover=function(e){var ev=jQuery.extend({},e);var ob=this;if(ob.hoverIntent_t){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t)}if(e.type=="mouseenter"){pX=ev.pageX;pY=ev.pageY;$(ob).bind("mousemove",track);if(ob.hoverIntent_s!=1){ob.hoverIntent_t=setTimeout(function(){compare(ev,ob)},cfg.interval)}}else{$(ob).unbind("mousemove",track);if(ob.hoverIntent_s==1){ob.hoverIntent_t=setTimeout(function(){delay(ev,ob)},cfg.timeout)}}};return this.bind('mouseenter',handleHover).bind('mouseleave',handleHover)}})(jQuery);


/* Author: Florian Schmitt <florian@outils-reseaux.org> under GPL licence */ 
// polyfills pour IE, chargés en premier
// polyfill responsive media queries
if ( ! Modernizr.mq('only all') ) {
  document.write('<script src="tools/templates/libs/respond.min.js"><\/script>');
}

// polyfill placeholder
(function($){
    // check placeholder browser support
    if (!Modernizr.input.placeholder)
    {
        var $placeholders = $('[placeholder]');
        // set placeholder values
        $placeholders.each(function()
        {
            var $placeholder = $(this);
            if ($placeholder.val() === '') // if field is empty
            {
                $placeholder.val( $placeholder.attr('placeholder') ).addClass('placeholder');
            }
        });
 		
 		// focus and blur of placeholders
        $placeholders.focus(function()
        {
            var $placeholder = $(this);
            if ($placeholder.val() == $placeholder.attr('placeholder'))
            {
                $placeholder.val('');
                $placeholder.removeClass('placeholder');
            }
        }).blur(function()
        {
            var $placeholder = $(this);
            if ($placeholder.val() === '' || $placeholder.val() == $placeholder.attr('placeholder'))
            {
                $placeholder.val($placeholder.attr('placeholder'));
                $placeholder.addClass('placeholder');
            }
        });
 
        // remove placeholders on submit
        $placeholders.closest('form').submit(function()
        {
            var $placeholder = $(this);
            $placeholder.find('[placeholder]').each(function()
            {
                var $this = $(this);
                if ($this.val() == $this.attr('placeholder'))
                {
                    $this.val('');
                }
            });
        });
    }

	// gestion des classes actives pour les menus
	$("a.active-link").parent().addClass('active-list').parents("ul").prev("a").addClass('active-parent-link').parent().addClass('active-list');

	// fenetres modales
	$('a.modalbox, .modalbox a').on('click', function(e) {
        console.log($(this));
		e.stopPropagation();
		var $this = $(this);
		var text = $this.attr('title');
		if (text.length>0) {
			text = '<h3>' + $.trim(text) + '</h3>';
		} else {
			text = '<h3></h3>';
		}
		$('body').append('<div class="modal fade" id="YesWikiModal">'+
							'<div class="modal-dialog">'+
								'<div class="modal-content">'+
								    '<div class="modal-header">'+
								    '<button type="button" class="close" data-dismiss="modal">&times;</button>'+
								    text +
								    '</div>'+
					    			'<div class="modal-body">'+
					    			'</div>'+
					    		'</div>'+
					    	'</div>'+
				    	'</div>');
		
		var modal = $('#YesWikiModal');
		modal.find('.modal-body').load($this.attr('href') + ' .page', function(response, status, xhr) {
			modal.modal({
				keyboard: false
			}).modal('show').on('hidden hidden.bs.modal', function () {
				modal.remove();
			});
			return false;
		});
	    
	    return false;
	});

	//hack retro compatibilite bs2
	if (!(typeof $().emulateTransitionEnd == 'function')) {$('.row.row-fluid').removeClass('row');}

	// login dans un dropdown
	$('.dropdown-menu form').on('click', function (e) {
	    e.stopPropagation()
	  });

	// Menus déroulants horizontaux
	var confighorizontal = {    
		sensitivity: 3,    
		interval: 100,    
		over: function() { //show submenu
			$(this).addClass('hover').find("ul:first").show();
		},    
		timeout: 100,    
		out: function() { //hide submenu
			$(this).removeClass('hover').find("ul").hide();
		}
	};
	var nav = $(".horizontal-dropdown-menu > ul");

	/* on ajoute des flèches pour signaler les sous menus et on gère le menu déroulant */
	nav.each(function() {
		var $nav = $(this);
		var nbmainlist = 1;
		$nav.find("li").each(function(i) {
			var $list = $(this);
			if ($list.parents("ul").length <= 1) { $list.addClass('list-'+nbmainlist); nbmainlist++;}

			// s'il y a des sous menus
			if ($list.find("ul").length > 0) {
        var arrow;
				// selon la hierarchie des menu, on change le sens et la forme de la fleche
				if ($list.parents("ul").length <= 1) {
					arrow = $("<span>").addClass('arrow arrow-level1').html("&#9660;");
				}
				else {
					arrow = $("<span>").addClass('arrow arrow-level'+$list.parents("ul").length).html("&#9658;");
				}
				
				var firstsublist = $list.find('ul:first');
				if (firstsublist.length > 0) { 
					if ($list.find('>a').length===0) {
						$list.contents().first().wrap('<a />');
						$list.find('a:not([href])').attr('href', '#');
					}
					firstsublist.prev().append(arrow);	
				}
				else { 
					$list.prev().prepend(arrow); 
				}
				
				$list.hoverIntent(confighorizontal);
			}
		});
		$nav.find("li:last").addClass('last');
	});
	
	
	// Menus déroulants verticaux
	var configvertical = {    
		 sensitivity: 3, // number = sensitivity threshold (must be 1 or higher)    
		 interval: 100, // number = milliseconds for onMouseOver polling interval    
		 over: 	function() {
					// on ferme les menus deroulants deja ouverts
					var listes = $(this).siblings('li');
					listes.removeClass('hover').find('ul').slideUp('fast');
					listes.find(".arrow").html("&#9658;");
					
					//on deroule et on tourne la fleche
					$(this).addClass('hover').find('ul:first').slideDown('fast');
					$(this).find(".arrow:first").html("&#9660;");
				},
		 timeout: 100, // number = milliseconds delay before onMouseOut    
		 out: 	function() { 
				 	return false; 
				}
		};

	//pour les menus qui possèdent des sous menus, on affiche une petite flèche pour indiquer
	var arrowright = $("<span>").addClass('arrow arrow-level1').html("&#9658;");
	var submenu = $(".vertical-dropdown-menu li:has(ul)");
	submenu.hoverIntent( configvertical );
	if (submenu.find("> a").length > 0) {
		submenu.find("> a").prepend(arrowright);
	} else {
		submenu.prepend(arrowright);
	}
	

	//deroule le deuxieme niveau pour la PageMenu, si elle contient le lien actif
	var listesderoulables = $(".vertical-dropdown-menu > ul > li.active-list:has(ul)");
	listesderoulables.addClass('hover').find('ul:first').slideDown('fast');
	listesderoulables.find(".arrow:first").html("&#9660;");
	
	// on change l'icone de l'accordeon
	$('.accordion-trigger').on('click', function () { 
	    if ($(this).next().find('.collapse').hasClass('in')) {
	        $(this).find('.arrow').html('&#9658;');
	    }
	    else {
	        $(this).find('.arrow').html('&#9660;');
	    }
	});

	//on enleve la fonction doubleclic dans des cas ou cela pourrait etre indesirable
	$(".no-dblclick, form, .page a, button, .dropdown-menu").on('dblclick', function(e) {
		return false;
	});


	// Pour l'apercu des themes, on recharge la page avec le theme selectionne
	$("#form_theme_selector select").on('change', function(){
		if ($(this).attr('id') === 'changetheme') {
			// On change le theme dynamiquement
			var val = $(this).val();
			// pour vider la liste
			var squelette = $("#changesquelette")[0];
			squelette.options.length=0;
      var i;
			for (i=0; i<tab1[val].length; i++){
				o = new Option(tab1[val][i],tab1[val][i]);
				squelette.options[squelette.options.length] = o;				
			}
			var style = $("#changestyle")[0];
			style.options.length=0;
			for (i=0; i<tab2[val].length; i++){
				o = new Option(tab2[val][i],tab2[val][i]);
				style.options[style.options.length]=o;				
			}
		}			

		var url = window.location.toString();
    	var urlAux = url.split('&theme=');
		window.location = urlAux[0] + '&theme=' + $('#changetheme').val() + '&squelette=' + $('#changesquelette').val() + '&style=' + $('#changestyle').val();
	});
	
	/* tooltips */
	$("[data-toggle='tooltip']").tooltip();


	// on ajoute un "espion" qui detecte quand on scrolle en dessus de la barre horizontale, afin de la fixer en haut
	var topnav = $('#topnav.fixable');
	if (topnav.length > 0) {
		var topoffset = topnav.offset().top;
		topnav.affix({'offset':topoffset});
	}

  // moteur de recherche utilisé dans un template 
  $('a[href="#search"]').on('click', function(event) {
      event.preventDefault();
      $('#search').addClass('open');
      $('#search .search-query').focus();
  });

  $('#search, #search button.close-search').on('click keyup', function(event) {
      if (event.target == this || $(event.target).hasClass('close-search') || event.keyCode == 27) {
          $(this).removeClass('open');
      }
  });

      // double clic
    $('.navbar').on('dblclick', function(e) {
        e.stopPropagation();
        $('body').append('<div class="modal fade" id="YesWikiModal">'+
                            '<div class="modal-dialog">'+
                                '<div class="modal-content">'+
                                    '<div class="modal-header">'+
                                    '<button type="button" class="close" data-dismiss="modal">&times;</button>'+
                                    '<h3>Editer une zone du menu horizontal</h3>' +
                                    '</div>'+
                                    '<div class="modal-body">'+
                                    '</div>'+
                                '</div>'+
                            '</div>'+
                        '</div>');
        
        var editmodal = $('#YesWikiModal');
        $(this).find('.include').each(function() {
            var href = $(this).attr('ondblclick')
              .replace("document.location='", '')
              .replace("';", '');
            var pagewiki = href.replace("/edit", '').replace("http://yeswiki.dev/wakka.php?wiki=", '');
            editmodal.find('.modal-body').append('<a href="'+href+'" class="btn btn-default btn-block"><i class="glyphicon glyphicon-pencil"></i> Editer la page '+pagewiki+'</a>');
           
        });
        editmodal.find('.modal-body').append('<a href="#" data-dismiss="modal" class="btn btn-warning btn-xs btn-block">En fait, je ne voulais pas double-cliquer...</a>');

        editmodal.modal({
            keyboard: true
        })
        .modal('show')
        .on('hidden hidden.bs.modal', function () {
            editmodal.remove();
        });
        
        return false;
        
    });
	
})(jQuery);
