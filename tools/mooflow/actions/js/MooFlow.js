/* -----------------------------------------------------------------
Script: 
	MooFlow.js v.0.2dev
	2008-04-12

Copyright:
	Copyright (c) 2007 Tobias Wetzel (ToBSn), <http://outcut.de/>

License:
	MIT-style license
	
ChangeLog:
	Added {
		Reflection via JS
		Load Images via JSON
		Load Images form HTML-Soure with Filter
		onClickView Callback - returns a object obj{'coordinates', 'src','alt','...'} all image attributes and parent a href, rel and target
	}
	Changed {
		Class Initialization
		Improved Speed-Up
	}
	Fixed {
		Slider inside click
		blocked key input
		set fullscreen / useWindowResize
	}
	
Probs:
	Safari 1/2 canvas must be added to body before can paint the reflection :(

Tested:
	Safari 3 / Safari 2(no reflection)
	Firefox
	Opera 9
	IE 6
----------------------------------------------------------------- */
var SliderEx = new Class({
	Extends: Slider,
	set: function(step){
		this.step = step.limit(0, this.options.steps);
		this.fireEvent('onTick', this.toPosition(this.step));
		return this;
    },
	clickedElement: function(event){
		var dir = this.range < 0 ? -1 : 1;
		var position = event.page[this.axis] - this.element.getPosition()[this.axis] - this.half;
		position = position.limit(-this.options.offset, this.full -this.options.offset);
		this.step = Math.round(this.min + dir * this.toStep(position));
		this.checkStep();
		this.fireEvent('onTick', position);
	}
});

Fx.TweenEx = new Class({
	Extends: Fx.Tween,
	render: function(element, property, value){
		this.fireEvent('onMotionChange', value[0].value);
		element.setStyle(property, this.serve(value, this.options.unit));
	}
});

Element.implement({
	reflect: function(arg){
		var i = new Element('img').setProperty('src', arg.src);
		if (Browser.Engine.trident) {
			i.style.filter = 'flipv progid:DXImageTransform.Microsoft.Alpha(opacity=30, style=1, finishOpacity=0, startx=0, starty=0, finishx=0, finishy='+arg.height*0.3+')';
			i.setStyles({'width':'100%', 'height':'100%'});
			return new Element('div').adopt(i);
		} else {
			var can = new Element('canvas').setProperties({'width':arg.width, 'height':arg.height});
			if (can.getContext && !Browser.Engine.webkit419 ) {
				var ctx = can.getContext("2d");
				ctx.save();
				ctx.translate(0,arg.height-1);
				ctx.scale(1,-1);
				ctx.drawImage(i, 0, 0, arg.width, arg.height);
				ctx.restore();
				ctx.globalCompositeOperation = "destination-out";
				ctx.fillStyle = '#000';
				ctx.fillRect(0, arg.height*0.5, arg.width, arg.height);
				var gra = ctx.createLinearGradient(0, 0, 0, arg.height*0.5);					
				gra.addColorStop(1, "rgba(255, 255, 255, 1.0)");
				gra.addColorStop(0, "rgba(255, 255, 255, "+(1-0.3)+")");
				ctx.fillStyle = gra;
				ctx.fillRect(0, 0, arg.width, arg.height);
			}
			return can;
		}
	}
});

var MooFlow = new Class({

	Implements: [Events, Options],
	
	options: {
		onStart: Class.empty,
		onComplete: Class.empty,
		onCancel: Class.empty,
		onClickView: Class.empty,
		onAutoPlay: Class.empty,
		onAutoStop: Class.empty,
		reflection: 0.5,
		heightRatio: 0.6,
		startIndex: 0,
		interval: 3000,
		factor: 115,
		bgColor: '#000',
		stylePath: 'MooFlow.css',
		useCaption: false,
		useResize: false,
		useSlider: false,
		useWindowResize: false,
		useMouseWheel: false,
		useKeyInput: false,
		useViewer: false
	},
	
	initialize: function(element, options){
		this.MooFlow = element;
		this.setOptions(options);
		this.foc = 150;
		this.index = this.options.startIndex;
		this.factor = this.options.factor;
		this.isFull = false;
		this.isAutoPlay = false;
		this.isLoading = false;
		
		this.MooFlow.addClass('mf').setStyles({
			'overflow':'hidden',
			'background-color':this.options.bgColor,
			'visibility':'hidden',
			'position':'relative'
		});
		
		if(!$chk($('mfCSS'))){new Asset.css(this.options.stylePath,{id:'mfCSS'});}
		if(this.options.useWindowResize){window.addEvent('resize', this.update.bind(this, true));}
		if(this.options.useMouseWheel){this.MooFlow.addEvent('mousewheel', this.wheelTo.bind(this));}
		if(this.options.useKeyInput){document.addEvent('keydown', this.keyTo.bind(this));}
		
		this.getElements(this.MooFlow);
	},
	
	getElements: function(el){
		this.master = {'images':[]};
		var els = el.getChildren();	
		if(els.length<=0) return;
		$$(els).each(function(e){
			var hash = $H(e.getElement('img').getProperties('src','title','alt','longdesc'));
			if(e.get('tag') == 'a'){hash.merge(e.getProperties('href','rel','target'));}
			hash = hash.getClean();
			this.master['images'].push(hash);
		}, this);
		this.clearMain();
	},
	
	loadJSON: function(url){
		if(!url || this.isLoading) return;
		this.isLoading = true;
		new Request.JSON({
			onComplete: function(data){
				if($chk(data)){
					this.master = data;
					this.index = this.options.startIndex;
					this.clearMain();
				}
			}.bind(this),
			onFailure: function(){
				this.isLoading = false;
				this.fireEvent('onChancel', 'Can not load JSON-Data!');
			}.bind(this)
		}, this).get(url);
	},
	
	loadHTML: function(url, filter){
		if(!url || !filter || this.isLoading) return;
		this.isLoading = true;
		new Request.HTML({
			onSuccess: function(tree, els, htm){
				this.getElements(new Element('div', {html: htm}).getChildren(filter));
				this.index = this.options.startIndex;
			}.bind(this),
			onFailure: function(){
				this.isLoading = false;
				this.fireEvent('onChancel', 'Can not load Remote Images!');
			}.bind(this)
		}, this).get(url);
	},
	
	clearMain: function(){
		if(this.nav){
			new Fx.Tween(this.nav, 'bottom', {
				onComplete: function(){
					this.nav.dispose();
					if(this.cap) this.cap.dispose();
					this.MooFlow.empty();
					this.createAniObj();
				}.bind(this)
			}).start(-50);
		}
		if(this.cap){this.cap.fade(0);}
		if(!this.nav && !this.cap){
			this.MooFlow.empty();
			this.createAniObj();
		}
	},
	
	getMooFlowElements: function(key){
		var els = [];
		this.master.images.each(function(el){ 
			els.push(el[key]); 
		});
		return els;
	},
	
	createAniObj: function(){
		this.aniObj = new Element('div').inject(this.MooFlow);
		this.aniFx = new Fx.TweenEx(this.aniObj, 'left', {
			transition: Fx.Transitions.Expo.easeOut,
			link: 'cancel',
			duration: 750,
			onMotionChange: this.process.bind(this),
			onStart: this.flowStart.bind(this),
			onComplete: this.flowComplete.bind(this)
		});
		this.addLoader();
	},
	
	addLoader: function(){
		this.MooFlow.store('height', this.MooFlow.getSize().x*this.options.heightRatio);
		this.MooFlow.addClass('load').setStyle('visibility', 'visible');
		new Fx.Tween(this.MooFlow, 'height', {
			duration: 800,
			onComplete: this.preloadImg.bind(this)
		}).start(this.MooFlow.retrieve('height'));
	},
	
	preloadImg: function(){
		this.loader = new Element('div').addClass('loader').inject(this.MooFlow);
		var imgs = this.getMooFlowElements('src');
		this.loadedImages = new Asset.images(imgs, {
		    onComplete: this.loaded.bind(this),
			onProgress: this.createMooFlowElement.bind(this)
		});
	},
	
	createMooFlowElement: function(counter, index){
		var object = this.getCurrent(index);
		object['width'] = this.loadedImages[index].width;
		object['height'] = this.loadedImages[index].height;

		var div = new Element('div').setStyles({
			'position':'absolute',
			'display':'none',
			'height': this.MooFlow.getSize().y
		});
		var con = new Element('div').inject(div);
		var img = new Element('img', {
			'src': object.src,
			'styles':{
				'vertical-align':'bottom',
				'width':'100%',
				'height':'50%'
			}
		}).inject(con);

		var ref = new Element('img').reflect({
			'src': object.src,
			'ref': this.options.reflection,
			'height': object.height,
			'width': object.width
		}).inject(con).setStyles({'width':'100%','height':'50%','background-color': this.options.bgColor});
		div.inject(this.MooFlow);

		img.addEvent('click', this.clickTo.bind(this, index));
		if(!this.options.useViewer) img.addEvent('dblclick', this.viewCallBack.bind(this, index));

		object['div'] = div;
		object['img'] = img;
		object['con'] = con;
		
		this.loader.set('html', (counter+1) + ' / ' + this.loadedImages.length);
	},
	
	loaded: function(){
		this.iL = this.master.images.length-1;
		new Fx.Tween(this.loader, 'opacity', {
			duration : 1000,
			onComplete: this.createUI.bind(this)
		}).start(0);
	},
	
	createUI: function(){
		this.MooFlow.removeClass('load');
		this.loader.dispose();
		if(this.options.useCaption){
			this.cap = new Element('div').addClass('caption').set('opacity',0);
			this.MooFlow.adopt(this.cap);
		}
		
		this.nav = new Element('div').addClass('MooFlowNav').setStyle('bottom','-50px');
		var autoPlayCon = new Element('div').addClass('autoPlayCon');
		var sliderCon = new Element('div').addClass('sliderCon');
		var resizeCon = new Element('div').addClass('resizeCon');		
		if(this.options.useAutoPlay){
			var play = new Element('a').addClass('play').addEvent('click', this.play.bind(this));
			var stop = new Element('a').addClass('stop').addEvent('click', this.stop.bind(this));
			autoPlayCon.adopt(stop, play);
		}
		if(this.options.useSlider){
			this.sliPrev = new Element('a').addClass('sliderNext');
			this.sliNext = new Element('a').addClass('sliderPrev');
			this.slider = new Element('div').addClass('slider');
			this.knob = new Element('div').addClass('knob');
			this.knob.adopt(new Element('div').addClass('knobleft'));
			this.slider.adopt(this.knob);
			sliderCon.adopt(this.sliPrev,this.slider,this.sliNext);
			this.slider.store('parentWidth', sliderCon.getSize().x-this.sliPrev.getSize().x-this.sliNext.getSize().x);
		}
		if(this.options.useResize){
			var resize = new Element('a').addClass('resize');
			resize.addEvent('click', this.setScreen.bind(this));
			resizeCon.adopt(resize);
		}		
		this.nav.adopt(autoPlayCon,sliderCon,resizeCon);
		this.MooFlow.adopt(this.nav);	
		this.showUI();
	},
	
	showUI: function(){
		if(this.cap) this.cap.fade(1);
		this.nav.tween('bottom', 20);
		this.fireEvent('onStart');
		this.update();
	},
	
	update: function(e){
		if(e) return;
		this.oW = this.MooFlow.getSize().x;
		this.sz = this.oW * 0.5;
		if(this.options.useSlider){	
			this.slider.setStyle('width',this.slider.getParent().getSize().x-this.sliPrev.getSize().x-this.sliNext.getSize().x-1);
			this.knob.setStyle('width',(this.slider.getSize().x/this.iL));
			this.sli = new SliderEx(this.slider, this.knob, {steps: this.iL}).set(this.index);
			this.sli.addEvent('onChange', this.glideTo.bind(this));
			this.sliNext.addEvent('click', this.next.bind(this));
			this.sliPrev.addEvent('click', this.prev.bind(this));
		}
		this.glideTo(this.index);
		this.isLoading = false;
	},
	
	setScreen: function(){
		this.isFull = !this.isFull;
		if(this.isFull){
			this.holder = new Element('div').inject(this.MooFlow,'after');
			this.MooFlow.wraps(new Element('div').inject(document.body));
			this.MooFlow.setStyles({'position':'absolute','z-index':'100','top':'0','left':'0','width':window.getSize().x,'height':window.getSize().y});
			if(this.options.useWindowResize){
				this._initResize = this.initResize.bind(this);
				window.addEvent('resize', this._initResize);
			}
		} else {
			this.MooFlow.wraps(this.holder);
			delete this.holder;
			window.removeEvent('resize', this._initResize);
			this.MooFlow.setStyles({'position':'relative','z-index':'','top':'','left':'','width':'','height':this.MooFlow.retrieve('height')});
			this.slider.setStyle('width',this.slider.retrieve('parentWidth'));
		}
		this.update();
	},
	
	initResize: function(){
		this.MooFlow.setStyles({'width':window.getSize().x,'height':window.getSize().y});
		this.update();
	},
	
	getCurrent: function(index){
		return this.master.images[$chk(index) ? index : this.index];
	},
	
	flowStart: $empty,
	
	flowComplete: $empty,
	
	viewCallBack: function(index){
		if(this.index != index) return;
		var el = $H(this.getCurrent());
		var callBackObject = {};
		callBackObject['coords'] = el.img.getCoordinates();
		el.each(function(v, k){
			if($type(v) == 'number' || $type(v) == 'string') callBackObject[k] = v;
		}, this);
		this.fireEvent('onClickView', callBackObject);
	},
	prev: function(){
		if(this.index > 0) this.clickTo(this.index-1);
	},
	stop: function(){
		$clear(this.autoPlay);
		this.isAutoPlay = false;
		this.fireEvent('onAutoStop');
	},
	play: function(){
		this.autoPlay = this.auto.periodical(this.options.interval, this);
		this.isAutoPlay = true;
		this.fireEvent('onAutoPlay');
	},
	auto: function(){
		if(this.index < this.iL)
		this.next();
		else if(this.index == this.iL)
		this.clickTo(0);
	},
	next: function(){
		if(this.index < this.iL) this.clickTo(this.index+1);
	},
	keyTo: function(e){
		e = new Event(e);
		switch (e.code){
			case 37:
				e.stop();
				this.prev();
				break;
			case 39:
				e.stop();
				this.next();
		}
	},
	wheelTo: function(e){
		e = new Event(e).stop();
		var d = e.wheel;
		if(e.preventDefault) e.preventDefault();		
		if(d > 0) this.prev();
		if(d < 0) this.next();
	},
	clickTo: function(index){
		if(this.index == index) return;
		this.aniFx.cancel();
		if(this.sli) this.sli.set(index);
		this.glideTo(index);
	},
	glideTo: function(index){
		this.index = index;
		if(this.cap) this.cap.set('html', this.getCurrent().title);
		this.aniFx.start(index*-this.foc);
	},
	process: function(x){
		var zI=this.iL,z,W,H,foc=this.foc,f=this.factor,sz=this.sz,oW=this.oW,div;
		with (Math) {
			this.master.images.each(function(el){
				div = el.div;
				if(x>-foc*6 && x<foc*6){
					z = sqrt(10000 + x * x) + 100;
					H = round((el.height / el.width * f) / z * sz);
					W = round(el.width * H / el.height);
					if(H >= el.width * 0.5)	W = round(f / z * sz);
					
					el.con.setStyle('height', H*2 + 'px');		
					div.setStyle('width', W + 'px');
					div.setStyle('left', round(((x / z * sz) + sz) - (f * 0.5) / z * sz) + 'px');
					div.setStyle('top', round(oW * 0.4 - H) + 'px');							
					div.setStyle('z-index', x < 0 ? zI++ : zI--);
					div.setStyle('display', 'block');
				} else {
					div.setStyle('display', 'none');
				}
				x += foc;
			});
		}
	}
});

window.addEvent('domready', function(){
	$$('.MooFlowieze').each(function(mooflow){
		new MooFlow(mooflow);
	});
});