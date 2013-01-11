(function( $ ){
	var upAsPercentage, title, nbvotants = 0, globalnbvote = 0, globaluppoint, globaldownpoint, globalrightpoint, pointrightpointreflectifglobal, pointrightpointreflectifglobal, ctx, code, point, style, drag = null, dPoint, lastupdate, savevoteencours = null, fullscreenmode = null;

	$.fn.convergence = function(options) {
		
		// parametres par défauts, peuvent Ítre écrasés lors de l'appel de la fonction .convergence()
		var settings = $.extend( {
		  'context'         	: '2d',
		  'backgroundcolor'		: '#FFFFFF',
		  'upaxisname'			: 'Like',
		  'downaxisname'		: 'Dislike',
		  'rightaxisname'		: 'Other idea',
		  'refresh'				: '1200',
		  'cooldowntime'		: '3000',
		  'evolution'			: '100',
		  'uppointcolor'		: 'rgba(0, 85, 2, 1)',  
		  'uppointfill'			: 'rgba(0, 85, 2, 0.5)',
		  'downpointcolor'		: 'rgba(160, 29, 18, 1)',  
		  'downpointfill'		: 'rgba(160, 29, 18, 0.5)',
		  'rightpointcolor'		: 'rgba(0, 51, 102, 1)',  
		  'rightpointfill'		: 'rgba(0, 51, 102, 0.5)',
		  'pointshadowcolor'	: 'black',
		  'pointshadowblur'		: '1',
		  'votecurvewidth'		: '1',
		  'votecurvecolor'		: '#444444',
		  'votecurvefill'		: 'rgba(220, 220, 220, 0.5)',
		  'personalvotewidth'	: '1',
		  'personalvotecolor'	: '#444444',
		  'personalvotefill'	: 'rgba(255, 255, 0, 0.5)',
		  'lineCap'				: 'round',
		  'lineJoin'			: 'round',
		  'titlefont'			: '13pt Calibri,Geneva,Arial',
		  'titlecolor'			: '#444444',
		  'nbvotesfont'			: '10pt Calibri,Geneva,Arial',
		  'nbvotescolor'		: '#444444',
		  'upaxisfont'			: '10pt Calibri,Geneva,Arial',
		  'upaxiscolor'			: 'rgba(0, 85, 2, 1)',
		  'downaxisfont'		: '10pt Calibri,Geneva,Arial',
		  'downaxiscolor'		: 'rgba(160, 29, 18, 1)',
		  'rightaxisfont'		: '10pt Calibri,Geneva,Arial',
		  'rightaxiscolor'		: 'rgba(0, 51, 102, 1)'
		}, options);

		// maniére propre de retourner la fonction pour qu'elle soit chainable
		return this.each(function() {        
			var $this = $(this);
		    canvas = $this[0];
			if (canvas.getContext) {
				ctx = canvas.getContext(settings.context);
				Init();
			}
			
			// =============== les actions des boutons ===============
			
			// plein écran
			$('#fullscreen').click(function () { 
				//canvas.width = document.width;
				//canvas.height = document.height;
				//canvas.width(window.screen.width);
				//canvas.height(window.screen.height);
				//console.log($this);
				var rect = canvas.getBoundingClientRect();
				canvas.width = rect.width;
				canvas.height = rect.height;
				var cssObj = {
				  'position' : 'absolute',
				  'z-index' : '1000',
				  'left' : '0',
				  'top' : '0',
				  'width' : '100%',
				  'height' : '100%'
				}

				$this.css(cssObj);
				fullscreenmode = true;
				DrawCanvas();
				return false;
			});
			
			// photo du canvas
			$('#savepicture').click(function () { 
				saveViaAJAX();
				return false;
			});

			// on réinitialise tous les votes
			$('#resetvote').click(function () { 
				$.ajax({
				  url: document.location+"/resetvotes",
				  success: function(data) {
					DrawCanvas();
				  }
				 });
				return false;
			});

			// on change le titre
			$('#changetitle').click(function () { 
				$.post(document.location+"/changeconfig", { "title": $('#votetitle').val(), "lastupdate": lastupdate},
				 function(data){
				 }, "json");
				DrawCanvas();
				return false;
			});	
			
		});
		
		// fonctions courantes
		
		// define initial points
		function Init() {
			
			GetGlobalVotes();
			
			// on actualise les résultats du vote global à intervale régulier
			setInterval(GetGlobalVotes, settings.refresh );
			
			point = {
				uppoint: { x: canvas.width*0.5, y: canvas.height*0.44, radius: 10, width: 1, color: settings.uppointcolor, fill: settings.uppointfill, arc1: 0, arc2: 2 * Math.PI},
				downpoint: { x: canvas.width*0.5, y: canvas.height*0.56, radius: 10, width: 1, color: settings.downpointcolor, fill: settings.downpointfill, arc1: 0, arc2: 2 * Math.PI},
				rightpoint: { x: canvas.width*0.56, y: canvas.height*0.5, radius: 10, width: 1, color: settings.rightpointcolor, fill: settings.rightpointfill, arc1: 0, arc2: 2 * Math.PI }
			};
			
			// line style defaults
			ctx.lineCap = settings.lineCap;
			ctx.lineJoin = settings.lineJoin;

			// event handlers
			canvas.onmousedown = DragStart;
			canvas.onmousemove = Dragging;
			canvas.onmouseup = DragEnd;
			canvas.onmouseout = DragEnd;
			
			canvas.ontouchstart = DragStart;
            canvas.ontouchstop = DragEnd;
            canvas.ontouchmove = Dragging;
            
           /* canvas.ontouchstart = function(e) {
var first = e.changedTouches[0];

x = first.clientX;
y = first.clientY;
ctx.moveTo(x, y);
}

canvas.ontouchend = function(e) {
x = null;
y = null;
}

canvas.ontouchmove = function(e) {
if (x == null || y == null) {
return;
}
var first = e.changedTouches[0];

x = first.pageX;
y = first.pageY;
x -= canvas.offsetLeft;
y -= canvas.offsetTop;
ctx.lineTo(x, y);
ctx.stroke();
ctx.moveTo(x, y);
} */

			DrawCanvas();
		}


		// draw canvas
		function DrawCanvas() {
			//réinitialisation du rectangle canvas
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			ctx.rect(0, 0, canvas.width, canvas.height);
			ctx.fillStyle = settings.backgroundcolor;
			ctx.fill();
			
			// Title and number of votes
			nbvotants = globalnbvote + ' votant';
			if (globalnbvote>1) nbvotants += 's';
			
			ctx.font = settings.titlefont;
			ctx.fillStyle = settings.titlecolor;
			ctx.fillText(title, canvas.width*0.014, canvas.height*0.064);
			
			ctx.font = settings.nbvotesfont;
			ctx.fillStyle = settings.nbvotescolor;
			ctx.fillText(nbvotants, canvas.width*0.02, canvas.height*0.11);
			
			// top axis and arrow
			ctx.beginPath();
			ctx.fillStyle = ctx.strokeStyle = settings.upaxiscolor;		
			ctx.moveTo(canvas.width*0.5, 0);
			ctx.lineTo(canvas.width*0.51, canvas.height*0.02);		
			ctx.lineTo(canvas.width*0.49, canvas.height*0.02);		
			ctx.lineTo(canvas.width*0.5, 0);				
			ctx.fill();
			ctx.lineTo(canvas.width*0.5,canvas.height*0.5);
			ctx.stroke();		
			// write top axis title
			ctx.font = settings.upaxisfont;
			ctx.fillText(settings.upaxisname, canvas.width*0.516, canvas.height*0.03);
			
			// right axis and arrow
			ctx.beginPath();
			ctx.fillStyle = ctx.strokeStyle = settings.rightaxiscolor;
			ctx.moveTo(canvas.width, canvas.height*0.5);
			ctx.lineTo(canvas.width*0.98, canvas.height*0.51);		
			ctx.lineTo(canvas.width*0.98, canvas.height*0.49);		
			ctx.lineTo(canvas.width, canvas.height*0.5);				
			ctx.fill();
			ctx.lineTo(canvas.width*0.5, canvas.height*0.5);
			ctx.stroke();
			// write right axis title
			ctx.font = settings.rightaxisfont;
			ctx.fillText(settings.rightaxisname, canvas.width*0.994-Math.round(ctx.measureText(settings.rightaxisname).width), canvas.height*0.484);
			
			// bottom axe and arrow
			ctx.beginPath();
			ctx.fillStyle = ctx.strokeStyle = settings.downaxiscolor;
			ctx.moveTo(canvas.width*0.5, canvas.height);
			ctx.lineTo(canvas.width*0.49, canvas.height*0.98);		
			ctx.lineTo(canvas.width*0.51, canvas.height*0.98);		
			ctx.lineTo(canvas.width*0.5, canvas.height);				
			ctx.fill();
			ctx.lineTo(canvas.width*0.5, canvas.height*0.5);		
			ctx.stroke();		
			ctx.font = settings.downaxisfont;
			ctx.fillText(settings.downaxisname, canvas.width*0.516, canvas.height*0.994);
			
			// dessin du polygone vote global			
			pointrightpointreflectifglobal = canvas.width - globalrightpoint;
			ctx.beginPath();			
			ctx.moveTo(canvas.width*0.5, globaluppoint);			
			ctx.quadraticCurveTo(2*globalrightpoint - canvas.width*0.5, canvas.height*0.5, canvas.width*0.5 + 11, globaldownpoint);			
			ctx.quadraticCurveTo(canvas.width*0.5, globaldownpoint - 18, canvas.width*0.5 - 11, globaldownpoint);
			ctx.quadraticCurveTo(2*pointrightpointreflectifglobal - canvas.width*0.5, canvas.height*0.5, canvas.width*0.5, globaluppoint);			

			//Style Courbe 
				//forme version alt : pointe en haut et bas
				/*ctx.moveTo(pointrightpointreflectifglobal, canvas.height*0.5);			
				ctx.quadraticCurveTo(canvas.width*0.5, 2*globaluppoint - canvas.height*0.5, globalrightpoint, canvas.height*0.5);
				ctx.quadraticCurveTo(canvas.width*0.5, 2*globaldownpoint - canvas.height*0.5, pointrightpointreflectifglobal, canvas.height*0.5);			
				*/
				// forme version flame avec 2 options : 1) demicercle concave en bas ou 2) plat en bas
				/*ctx.moveTo(canvas.width*0.5, globaluppoint);			
				ctx.quadraticCurveTo(2*globalrightpoint - canvas.width*0.5, canvas.height*0.5, canvas.width*0.53, globaldownpoint);
				1)	ctx.quadraticCurveTo(canvas.width*0.5, globaldownpoint - canvas.height*0.05, canvas.width*0.47, globaldownpoint);
				2)	ctx.lineTo(canvas.width*0.47, globaldownpoint);
				3) 
				ctx.quadraticCurveTo(2*pointrightpointreflectifglobal - canvas.width*0.5, canvas.height*0.5, canvas.width*0.5, globaluppoint);			
				*/
			
			ctx.fillStyle = settings.votecurvefill;
			ctx.lineWidth = settings.votecurvewidth;
			ctx.strokeStyle = settings.votecurvecolor;
			ctx.fill();
			ctx.stroke();
			
			// dessin du polygone vote perso
			pointrightpointreflectif = canvas.width*0.5 -(point.rightpoint.x - canvas.width*0.5)	
			ctx.beginPath();
			ctx.moveTo(canvas.width*0.5, point.uppoint.y);			
			ctx.quadraticCurveTo(2*point.rightpoint.x - canvas.width*0.5, canvas.height*0.5, canvas.width*0.5 + 11, point.downpoint.y);			
			ctx.quadraticCurveTo(canvas.width*0.5, point.downpoint.y - 18, canvas.width*0.5 - 11, point.downpoint.y);
			ctx.quadraticCurveTo(2*pointrightpointreflectif - canvas.width*0.5, canvas.height*0.5, canvas.width*0.5, point.uppoint.y);	
			
			//Style Courbe 
				//forme version alt : pointe en haut et bas	
			/*ctx.moveTo(pointrightpointreflectif, point.rightpoint.y);
			ctx.quadraticCurveTo(canvas.width*0.5, point.uppoint.y-(canvas.height*0.5-point.uppoint.y), point.rightpoint.x, canvas.height*0.5);
			ctx.quadraticCurveTo(canvas.width*0.5, point.downpoint.y-(canvas.height*0.5-point.downpoint.y), pointrightpointreflectif, canvas.height*0.5);
			*/
			
			ctx.fillStyle = settings.personalvotefill;
			ctx.lineWidth = settings.personalvotewidth;
			ctx.strokeStyle = settings.personalvotecolor;
			ctx.fill();
			ctx.stroke();
			

				// ronds de controle
				for (var p in point) {	
						ctx.lineWidth = point[p].width;
						ctx.strokeStyle = point[p].color;
						ctx.fillStyle = point[p].fill;
						ctx.shadowColor = settings.pointshadowcolor;
						ctx.shadowBlur = settings.pointshadowblur;
						ctx.beginPath();
						ctx.arc(point[p].x, point[p].y, point[p].radius, point[p].arc1, point[p].arc2, true);
						ctx.fill();
						ctx.stroke();
						ctx.shadowBlur = 0;
				}

		}
			
		// start dragging
		function DragStart(e) {
			var dx, dy;
			if (e.touches) {
                // Touch event
                for (var i = 1; i <= e.touches.length; i++) {
                    e = MousePos(e.touches[i - 1]); // Get info for finger #1
                }
			}
			else {
					// Mouse event
					e = MousePos(e);
			}

			for (var p in point) {			
				dx = point[p].x - e.x;
				dy = point[p].y - e.y;
				if ((dx * dx) + (dy * dy) < point[p].radius * point[p].radius) {
					drag = p;
					dPoint = e;
					canvas.style.cursor = "move";
					return;
				}
			}
			
			return false;
		}

		// dragging
		function Dragging(e) {

			if (drag) {
				e = MousePos(e);
				delta = 0;

				if (drag == 'uppoint') {
					if (e.y>canvas.height*0.5) point[drag].y = canvas.height*0.5;
					else if (e.y<0) point[drag].y = 0;
					else {
						delta = (e.y - dPoint.y);
						point[drag].y += delta;	
					}		
				}
				else if (drag == 'downpoint') {
					if (e.y<canvas.height*0.5) point[drag].y = canvas.height*0.5;
					else if (e.y>canvas.height) point[drag].y = canvas.height;
					else point[drag].y += e.y - dPoint.y;	
				}
				else if (drag == 'rightpoint') {
					if (e.x<canvas.width*0.5) point[drag].x = canvas.width*0.5;
					else if (e.x>canvas.width) point[drag].x = canvas.width;
					else point[drag].x += e.x - dPoint.x;
				}
				else {
					point[drag].x += e.x - dPoint.x;
					point[drag].y += e.y - dPoint.y;
				}
				dPoint = e;
				DrawCanvas();
			} 
			// changer le curseur lors du hover des points
			else {
				e = MousePos(e);
				var dx, dy;
				for (var p in point) {
					dx = point[p].x - e.x;
					dy = point[p].y - e.y;
					if ((dx * dx) + (dy * dy) < point[p].radius * point[p].radius) {
						canvas.style.cursor = "move";
						return;
					} 
					else {
						canvas.style.cursor = "default";
					}
				}
			}
		}


		// end dragging
		function DragEnd(e) {
			if (drag) {
				drag = null;
				canvas.style.cursor = "default";
				DrawCanvas();
				
				//calcul du %tage en fonction de la taille du canvas
				upAsPercentage = Math.round(100*(1-(2*point.uppoint.y/canvas.height)));
				downAsPercentage = Math.round(100*((2*point.downpoint.y/canvas.height)-1));
				rightAsPercentage = Math.round(100*((2*point.rightpoint.x/canvas.width)-1));
				
				savevoteencours = true;
				$.post(document.location+"/savevote", { "id": $('#sessionid').val(), "up":upAsPercentage, "down":downAsPercentage, "right":rightAsPercentage},
				 function(data){
					 savevoteencours = null;
				 }, "json");
			 }
		}

		// event parser
		function MousePos(e) {
			if (e.offsetX) {	
				//ctx.fillText('x:'+e.offsetX+' y:'+e.offsetY, 400, 100);				
                return { x: e.offsetX, y: e.offsetY };
			}
			else if (e.layerX) {
				//ctx.fillText('x:'+(e.layerX - canvas.offsetLeft)+' y:'+(e.layerY - canvas.offsetTop), 400, 200);
				return { x: e.layerX - canvas.offsetLeft, y: e.layerY - canvas.offsetTop };
			}
			else {
				//ctx.fillText('x:'+(e.pageX - canvas.offsetLeft)+' y:'+(e.pageY - canvas.offsetTop), 400, 300);
				return { x: e.pageX - canvas.offsetLeft, y: e.pageY - canvas.offsetTop };
			}
		}

		// get global votes
		function GetGlobalVotes() {
			$.post(document.location+"/globalvotes", { "evolution":  settings.evolution , 'lastupdate': lastupdate, 'cooldowntime': settings.cooldowntime},
				 function(data){
					globalnbvote = data.nbvote;
					title = data.title;
				
					if (!drag && !savevoteencours) {						
							//Calcul des points de la patateperso en fonction du canvas perso				
							point.uppoint.y = (canvas.height/2)*(1 - data.uservoteup/100);
							point.downpoint.y = (canvas.height/2)*(1 + data.uservotedown/100);
							point.rightpoint.x = (canvas.width/2)*(1 + data.uservoteright/100);	
					}	
					//Calcul des points de la globalpatate en fonction du canvas perso
					globaluppoint = (canvas.height/2)*(1 - data.globalup/100);
					globaldownpoint = (canvas.height/2)*(1 + data.globaldown/100);
					globalrightpoint = (canvas.width/2)*(1 + data.globalright/100);
					
					DrawCanvas();
				 }, "json");
		}

		// permet de sauver le png de l'image
		function saveViaAJAX() {
			var canvasData = canvas.toDataURL("image/png");
			var postData = "canvasData="+canvasData;
			var ajax = new XMLHttpRequest();
			ajax.open("POST", document.location+"/canvas2png",true);
			ajax.setRequestHeader('Content-Type', 'canvas/upload');
			ajax.onreadystatechange=function() {
				if (ajax.readyState == 4)
				{
					// Write out the filename.
					window.location.href= document.location+"/downloadpng&path="+ajax.responseText;
				}
			}
			ajax.send(postData);
		}
		
	};
})( jQuery );
