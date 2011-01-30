/**
 * jQuery jqGalScroll Plugin
 * Examples and documentation at: http://benjaminsterling.com/jquery-jqgalscroll-photo-gallery/
 *
 * @author: Benjamin Sterling
 * @version: 2.1
 * @copyright (c) 2007 Benjamin Sterling, KenzoMedia
 * @extendThanks Koesmanto Bong http://www.koesbong.com/
 *		Koes put a fire under my butt to improve this plugin
 *		and when I took too long he took what I had and added
 *		the horizontal scroll and in turn I ripped it from his
 *		hands and made it better :)
 *
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *   http://www.gnu.org/licenses/gpl.html
 *   
 * @requires jQuery v1.2.1 or later
 * @optional jQuery Easing v1.2
 *
 * @name jqGalScroll
 * @example $('ul').jqGalScroll();
 * 
 * @Semantic requirements:
 * 				The structure fairly simple; the structure should consist
 * 				of a ul > li > img structure.
 * 
 * 	<ul>
 *		<li><img src="common/img/dsc_0003.thumbnail.JPG"/></li>
 *		<li><img src="common/img/dsc_0012.thumbnail.JPG"/></li>
 *	</ul>
 *
 * @param String ease
 *					refer to http://gsgd.co.uk/sandbox/jquery.easing.php for values
 * 
 * @example $('#gallery').jqGalScroll({speed:1000});
 
 * @param String speed
 * 					fast, slow, 1000, ext..
 * 
 * @example $('#gallery').jqGalScroll({speed:1000});
 * 
 * @param String height
 * 					the default height of your wrapper
 * 
 * @example $('#gallery').jqGalScroll({height:490});
 * 
 * @param String titleOpacity
 * 					the opacity of your title bar (if present)
 * 
 * @example $('#gallery').jqGalScroll({titleOpacity:.70});
 * 
 * @param String direction 
 *					vertical horizontal diagonal
 * 
 * @example $('#gallery').jqGalScroll({direction:'vertical'});
 * 			
 */
(function($) {
	$.fn.jqGalScroll = function(options){
		return this.each(function(i){
			var el = this
			el.curImage = 0;
			el.jqthis = $(this).css({position:'relative'});
			el.jqchildren = el.jqthis.children();
			el.opts = $.extend({}, jqGalScroll, options);
			el.index = i;
			el.totalChildren = el.jqchildren.size();
			var width,height;
			
			switch(el.opts.direction){
				case 'horizontal':
					width = el.totalChildren *el.opts.width;
					height = el.opts.height;
					break;
				case 'vertical':
					width = el.opts.width;
					height = el.totalChildren *el.opts.height;
					break;
				default:
					width = el.totalChildren *el.opts.width;
					height = el.totalChildren *el.opts.height;
					break;
			};
			
			el.container = $('<div id="jqGS'+i+'" class="jqGSContainer">').css({position:'relative'});
			el.ImgContainer = $('<div class="jqGSImgContainer" style="height:'+el.opts.height+'px;position:relative;overflow:hidden">')
								.css({height:el.opts.height,width:el.opts.width,position:'relative',overflow:'hidden'});
			el.jqthis.css({height:height,width:width});
			
			el.jqthis.wrap(el.container);
			el.jqthis.wrap(el.ImgContainer);
			el.pagination = $('<div class="jqGSPagination">');
			el.jqthis.parent().parent().append(el.pagination);
			var jqul = $('<ul>').appendTo(el.pagination);
			var pos = {x:0,y:0};
			
			el.jqchildren
			.each(function(j){
				var selected = '';
				if(j == 0) selected = 'selected';
				
				var $a = $('<a href="#'+(j)+'" class="'+selected+'">'+(j+1)+'</a>').click(function(){
					var href = this.index;//href.replace(/^.*#/, '');
					el.pagination.find('.selected').removeClass('selected');
					$(this).addClass('selected');
					var params = {};
					if( el.opts.direction == 'diagonal'){
						params = {right:(el.opts.width*href),bottom:(el.opts.height*href)}
					}
					else if( el.opts.direction == 'vertical'){
						params = {bottom:(el.opts.height*href)}
					}
					else if( el.opts.direction == 'horizontal'){
						params = {right:(el.opts.width*href)}
					};
					
					el.jqthis.stop().animate(params,el.opts.speed, el.opts.ease);
					index = href;
					return false;
				});

				var n = $a.get(0);

				n.index = j;

				$('<li>').appendTo(jqul).append($a);

				if( el.opts.direction == 'diagonal'){
					pos.x = j * el.opts.width;
					pos.y = j * el.opts.height;
				}
				else if( el.opts.direction == 'horizontal'){
					pos.x = j * el.opts.width;
				}
				else if( el.opts.direction == 'vertical'){
					pos.y = j * el.opts.height;
				};

				var jqchild = $(this).css({height:el.opts.height,width:el.opts.width,position:'absolute',left:pos.x, top:pos.y});

				var jqimg = jqchild.find('img').hide()
				
				if(jqimg.parent().is('a')){
					var p = jqimg.parent();
					jqimg.get(0).linkHref = p.attr('href');
					p.remove();
					jqimg.appendTo(jqchild);
				};

				jqimg.click(function(){
					var next = n.index + 1;
					if((n.index + 1) == el.totalChildren ){
						el.pagination.find('[href$=#0]').click();
					}
					else{
						el.pagination.find('[href$=#'+next+']').click();
					}
				});

				var $loader = $('<div class="jqGSLoader">').appendTo(jqchild);
				var $titleHolder = $('<div class="jqGSTitle">').appendTo(jqchild).css({opacity:el.opts.titleOpacity}).hide();
				var image = new Image();
				image.onload = function(){
					image.onload = null;
					$loader.fadeOut();
					jqimg.css({marginLeft:-image.width*.5,marginTop:-image.height*.5,position:'absolute',left:'50%',top:'50%'}).fadeIn();
					var alt = jqimg.attr('alt');
					if(typeof alt != 'undefined'){
						$titleHolder.text(alt).fadeIn();
					}
				};
				image.src = jqimg.attr('src');
			});

			
		}); // end : this.each(function()
	};  // end : $.fn.jqGalScroll
	jqGalScroll = {
		ease: null,
		speed:0,
		height: 500,
		width: 500,
		titleOpacity : .60,
		direction : 'horizontal' // vertical horizontal diagonal
	};
})(jQuery);