/*
Script: Core.js
	MooTools - My Object Oriented JavaScript Tools.

License:
	MIT-style license.

Copyright:
	Copyright (c) 2006-2007 Valerio Proietti, <http://mad4milk.net/>

Code & Documentation:
	The MooTools production team <http://mootools.net/developers/>.

Inspiration:
	- Class implementation inspired by Base.js <http://dean.edwards.name/weblog/2006/03/base/> Copyright (c) 2006 Dean Edwards, GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
	- Some functionality inspired by Prototype.js <http://prototypejs.org> Copyright (c) 2005-2007 Sam Stephenson, MIT License <http://opensource.org/licenses/mit-license.php>
*/

var MooTools = {
	'version': '1.2dev',
	'build': '1.2b2'
};

var Native = function(options){
	options = options || {};

	var afterImplement = options.afterImplement || function(){};
	var generics = options.generics;
	generics = (generics !== false);
	var legacy = options.legacy;
	var initialize = options.initialize;
	var protect = options.protect;
	var name = options.name;

	var object = initialize || legacy;

	object.constructor = Native;
	object.$family = {name: 'native'};
	if (legacy && initialize) object.prototype = legacy.prototype;
	object.prototype.constructor = object;

	if (name){
		var family = name.toLowerCase();
		object.prototype.$family = {name: family};
		Native.typize(object, family);
	}

	var add = function(obj, name, method, force){
		if (!protect || force || !obj.prototype[name]) obj.prototype[name] = method;
		if (generics) Native.genericize(obj, name, protect);
		afterImplement.call(obj, name, method);
		return obj;
	};

	object.implement = function(a1, a2, a3){
		if (typeof a1 == 'string') return add(this, a1, a2, a3);
		for (var p in a1) add(this, p, a1[p], a2);
		return this;
	};

	object.alias = function(existing, property, force){
		existing = this.prototype[existing];
		if (existing) add(this, property, existing, force);
		return this;
	};

	return object;
};

Native.implement = function(objects, properties){
	for (var i = 0, l = objects.length; i < l; i++) objects[i].implement(properties);
};

Native.genericize = function(object, property, check){
	if ((!check || !object[property]) && typeof object.prototype[property] == 'function') object[property] = function(){
		var args = Array.prototype.slice.call(arguments);
		return object.prototype[property].apply(args.shift(), args);
	};
};

Native.typize = function(object, family){
	if (!object.type) object.type = function(item){
		return ($type(item) === family);
	};
};

(function(objects){
	for (var name in objects) Native.typize(objects[name], name.toLowerCase());
})({'Boolean': Boolean, 'Native': Native, 'Object': Object});

(function(objects){
	for (var name in objects) new Native({name: name, initialize: objects[name], protect: true});
})({'String': String, 'Function': Function, 'Number': Number, 'Array': Array, 'RegExp': RegExp, 'Date': Date});

(function(object, methods){
	for (var i = 0, l = methods.length; i < l; i++) Native.genericize(object, methods[i], true);
	return arguments.callee;
})
(Array, ['pop', 'push', 'reverse', 'shift', 'sort', 'splice', 'unshift', 'concat', 'join', 'slice', 'toString', 'valueOf', 'indexOf', 'lastIndexOf'])
(String, ['charAt', 'charCodeAt', 'concat', 'indexOf', 'lastIndexOf', 'match', 'replace', 'search', 'slice', 'split', 'substr', 'substring', 'toLowerCase', 'toUpperCase', 'valueOf']);

function $chk(obj){
	return !!(obj || obj === 0);
};

function $clear(timer){
	clearTimeout(timer);
	clearInterval(timer);
	return null;
};

function $defined(obj){
	return (obj != undefined);
};

function $empty(){};

function $arguments(i){
	return function(){
		return arguments[i];
	};
};

function $lambda(value){
	return (typeof value == 'function') ? value : function(){
		return value;
	};
};

function $extend(original, extended){
	for (var key in (extended || {})) original[key] = extended[key];
	return original;
};

function $unlink(object){
	var unlinked = null;
	
	switch ($type(object)){
		case 'object':
			unlinked = {};
			for (var p in object) unlinked[p] = $unlink(object[p]);
		break;
		case 'array':
			unlinked = [];
			for (var i = 0, l = object.length; i < l; i++) unlinked[i] = $unlink(object[i]);
		break;
		default: return object;
	}
	
	return unlinked;
};

function $merge(){
	var mix = {};
	for (var i = 0, l = arguments.length; i < l; i++){
		var object = arguments[i];
		if ($type(object) != 'object') continue;
		for (var key in object){
			var op = object[key], mp = mix[key];
			mix[key] = (mp && $type(op) == 'object' && $type(mp) == 'object') ? $merge(mp, op) : $unlink(op);
		}
	}
	return mix;
};

function $pick(){
	for (var i = 0, l = arguments.length; i < l; i++){
		if ($defined(arguments[i])) return arguments[i];
	}
	return null;
};

function $random(min, max){
	return Math.floor(Math.random() * (max - min + 1) + min);
};

function $splat(obj){
	var type = $type(obj);
	return (type) ? ((type != 'array' && type != 'arguments') ? [obj] : obj) : [];
};

var $time = Date.now || function(){
	return new Date().getTime();
};

function $try(fn, bind, args){
	try {
		return fn.apply(bind, $splat(args));
	} catch(e){
		return false;
	}
};

function $type(obj){
	if (obj == undefined) return false;
	if (obj.$family) return (obj.$family.name == 'number' && !isFinite(obj)) ? false : obj.$family.name;
	if (obj.nodeName){
		switch (obj.nodeType){
			case 1: return 'element';
			case 3: return (/\S/).test(obj.nodeValue) ? 'textnode' : 'whitespace';
		}
	} else if (typeof obj.length == 'number'){
		if (obj.callee) return 'arguments';
		else if (obj.item) return 'collection';
	}
	return typeof obj;
};

var Hash = new Native({

	name: 'Hash',

	initialize: function(object){
		if ($type(object) == 'hash') object = $unlink(object.getClean());
		for (var key in object){
			if (!this[key]) this[key] = object[key];
		}
		return this;
	}

});

Hash.implement({
	
	getLength: function(){
		var length = 0;
		for (var key in this){
			if (this.hasOwnProperty(key)) length++;
		}
		return length;
	},

	forEach: function(fn, bind){
		for (var key in this){
			if (this.hasOwnProperty(key)) fn.call(bind, this[key], key, this);
		}
	},
	
	getClean: function(){
		var clean = {};
		for (var key in this){
			if (this.hasOwnProperty(key)) clean[key] = this[key];
		}
		return clean;
	}

});

Hash.alias('forEach', 'each');

function $H(object){
	return new Hash(object);
};

Array.implement({

	forEach: function(fn, bind){
		for (var i = 0, l = this.length; i < l; i++) fn.call(bind, this[i], i, this);
	}

});

Array.alias('forEach', 'each');

function $A(iterable){
	if ($type(iterable) == 'collection'){
		var array = [];
		for (var i = 0, l = iterable.length; i < l; i++) array[i] = iterable[i];
		return array;
	}
	return Array.prototype.slice.call(iterable);
};

function $each(iterable, fn, bind){
	var type = $type(iterable);
	((type == 'arguments' || type == 'collection' || type == 'array') ? Array : Hash).each(iterable, fn, bind);
};


/*
Script: Browser.js
	The Browser Core. Contains Browser initialization, Window and Document, and the Browser Hash.

License:
	MIT-style license.
*/

var Browser = new Hash({
	Engine: {name: 'unknown', version: ''},
	Platform: {name: (navigator.platform.match(/mac|win|linux|nix/i) || ['other'])[0].toLowerCase()},
	Features: {xhr: !!(window.XMLHttpRequest), xpath: !!(document.evaluate), air: !!(window.runtime)}
});

if (window.opera) Browser.Engine.name = 'presto';
else if (window.ActiveXObject) Browser.Engine = {name: 'trident', version: (Browser.Features.xhr) ? 5 : 4};
else if (!navigator.taintEnabled) Browser.Engine = {name: 'webkit', version: (Browser.Features.xpath) ? 420 : 419};
else if (document.getBoxObjectFor != null) Browser.Engine.name = 'gecko';
Browser.Engine[Browser.Engine.name] = Browser.Engine[Browser.Engine.name + Browser.Engine.version] = true;
Browser.Platform[Browser.Platform.name] = true;

function $exec(text){
	if (!text) return text;
	if (window.execScript){
		window.execScript(text);
	} else {
		var script = document.createElement('script');
		script.setAttribute('type', 'text/javascript');
		script.text = text;
		document.head.appendChild(script);
		document.head.removeChild(script);
	}
	return text;
};

Native.UID = 0;

var Window = new Native({

	name: 'Window',

	legacy: window.Window,

	initialize: function(win){
		if (!win.Element){
			win.Element = $empty;
			if (Browser.Engine.webkit) win.document.createElement("iframe"); //fixes safari 2
			win.Element.prototype = (Browser.Engine.webkit) ? window["[[DOMElement.prototype]]"] : {};
		}
		win.uid = Native.UID++;
		return $extend(win, Window.Prototype);
	},

	afterImplement: function(property, value){
		window[property] = Window.Prototype[property] = value;
	}

});

Window.Prototype = {$family: {name: 'window'}};

new Window(window);

var Document = new Native({

	name: 'Document',

	legacy: window.Document,

	initialize: function(doc){
		doc.head = doc.getElementsByTagName('head')[0];
		doc.html = doc.getElementsByTagName('html')[0];
		doc.window = doc.defaultView || doc.parentWindow;
		if (Browser.Engine.trident4) $try(function(){
			doc.execCommand("BackgroundImageCache", false, true);
		});
		doc.uid = Native.UID++;
		return $extend(doc, Document.Prototype);
	},

	afterImplement: function(property, value){
		document[property] = Document.Prototype[property] = value;
	}

});

Document.Prototype = {$family: {name: 'document'}};

new Document(document);


/*
Script: Array.js
	Contains Array Prototypes like copy, each, contains, and remove.

License:
	MIT-style license.
*/

Array.implement({

	every: function(fn, bind){
		for (var i = 0, l = this.length; i < l; i++){
			if (!fn.call(bind, this[i], i, this)) return false;
		}
		return true;
	},

	filter: function(fn, bind){
		var results = [];
		for (var i = 0, l = this.length; i < l; i++){
			if (fn.call(bind, this[i], i, this)) results.push(this[i]);
		}
		return results;
	},
	
	clean: function() {
		return this.filter($arguments(0));
	},

	indexOf: function(item, from){
		var len = this.length;
		for (var i = (from < 0) ? Math.max(0, len + from) : from || 0; i < len; i++){
			if (this[i] === item) return i;
		}
		return -1;
	},

	map: function(fn, bind){
		var results = [];
		for (var i = 0, l = this.length; i < l; i++) results[i] = fn.call(bind, this[i], i, this);
		return results;
	},

	some: function(fn, bind){
		for (var i = 0, l = this.length; i < l; i++){
			if (fn.call(bind, this[i], i, this)) return true;
		}
		return false;
	},

	associate: function(keys){
		var obj = {}, length = Math.min(this.length, keys.length);
		for (var i = 0; i < length; i++) obj[keys[i]] = this[i];
		return obj;
	},

	link: function(object){
		var result = {};
		for (var i = 0, l = this.length; i < l; i++){
			for (var key in object){
				if (object[key](this[i])){
					result[key] = this[i];
					delete object[key];
					break;
				}
			}
		}
		return result;
	},

	contains: function(item, from){
		return this.indexOf(item, from) != -1;
	},

	extend: function(array){
		for (var i = 0, j = array.length; i < j; i++) this.push(array[i]);
		return this;
	},

	getLast: function(){
		return (this.length) ? this[this.length - 1] : null;
	},

	getRandom: function(){
		return (this.length) ? this[$random(0, this.length - 1)] : null;
	},

	include: function(item){
		if (!this.contains(item)) this.push(item);
		return this;
	},

	merge: function(array){
		for (var i = 0, l = array.length; i < l; i++) this.include(array[i]);
		return this;
	},

	remove: function(item){
		for (var i = this.length; i--; i){
			if (this[i] === item) this.splice(i, 1);
		}
		return this;
	},

	empty: function(){
		this.length = 0;
		return this;
	},

	flatten: function(){
		var array = [];
		for (var i = 0, l = this.length; i < l; i++){
			var type = $type(this[i]);
			if (!type) continue;
			array = array.concat((type == 'array' || type == 'collection' || type == 'arguments') ? Array.flatten(this[i]) : this[i]);
		}
		return array;
	},

	hexToRgb: function(array){
		if (this.length != 3) return null;
		var rgb = this.map(function(value){
			if (value.length == 1) value += value;
			return value.toInt(16);
		});
		return (array) ? rgb : 'rgb(' + rgb + ')';
	},

	rgbToHex: function(array){
		if (this.length < 3) return null;
		if (this.length == 4 && this[3] == 0 && !array) return 'transparent';
		var hex = [];
		for (var i = 0; i < 3; i++){
			var bit = (this[i] - 0).toString(16);
			hex.push((bit.length == 1) ? '0' + bit : bit);
		}
		return (array) ? hex : '#' + hex.join('');
	}

});


/*
Script: Function.js
	Contains Function Prototypes like create, bind, pass, and delay.

License:
	MIT-style license.
*/

Function.implement({

	extend: function(properties){
		for (var property in properties) this[property] = properties[property];
		return this;
	},

	create: function(options){
		var self = this;
		options = options || {};
		return function(event){
			var args = options.arguments;
			args = $defined(args) ? $splat(args) : Array.slice(arguments, (options.event) ? 1 : 0);
			if (options.event) args = [event || window.event].extend(args);
			var returns = function(){
				return self.apply(options.bind || null, args);
			};
			if (options.delay) return setTimeout(returns, options.delay);
			if (options.periodical) return setInterval(returns, options.periodical);
			if (options.attempt) return $try(returns);
			return returns();
		};
	},

	pass: function(args, bind){
		return this.create({'arguments': args, 'bind': bind});
	},

	attempt: function(args, bind){
		return this.create({'arguments': args, 'bind': bind, 'attempt': true})();
	},

	bind: function(bind, args){
		return this.create({'bind': bind, 'arguments': args});
	},

	bindWithEvent: function(bind, args){
		return this.create({'bind': bind, 'event': true, 'arguments': args});
	},

	delay: function(delay, bind, args){
		return this.create({'delay': delay, 'bind': bind, 'arguments': args})();
	},

	periodical: function(interval, bind, args){
		return this.create({'periodical': interval, 'bind': bind, 'arguments': args})();
	},

	run: function(args, bind){
		return this.apply(bind, $splat(args));
	}

});


/*
Script: Number.js
	Contains Number Prototypes like limit, round, times, and ceil.

License:
	MIT-style license.
*/

Number.implement({

	limit: function(min, max){
		return Math.min(max, Math.max(min, this));
	},

	round: function(precision){
		precision = Math.pow(10, precision || 0);
		return Math.round(this * precision) / precision;
	},

	times: function(fn, bind){
		for (var i = 0; i < this; i++) fn.call(bind, i, this);
	},

	toFloat: function(){
		return parseFloat(this);
	},

	toInt: function(base){
		return parseInt(this, base || 10);
	}

});

Number.alias('times', 'each');

(function(math){
	var methods = {};
	math.each(function(name){
		if (!Number[name]) methods[name] = function(){
			return Math[name].apply(null, [this].concat($A(arguments)));
		};
	});
	Number.implement(methods);
})(['abs', 'acos', 'asin', 'atan', 'atan2', 'ceil', 'cos', 'exp', 'floor', 'log', 'max', 'min', 'pow', 'sin', 'sqrt', 'tan']);


/*
Script: String.js
	Contains String Prototypes like camelCase, capitalize, test, and toInt.

License:
	MIT-style license.
*/

String.implement({

	test: function(regex, params){
		return ((typeof regex == 'string') ? new RegExp(regex, params) : regex).test(this);
	},

	contains: function(string, separator){
		return (separator) ? (separator + this + separator).indexOf(separator + string + separator) > -1 : this.indexOf(string) > -1;
	},

	trim: function(){
		return this.replace(/^\s+|\s+$/g, '');
	},

	clean: function(){
		return this.replace(/\s+/g, ' ').trim();
	},

	camelCase: function(){
		return this.replace(/-\D/g, function(match){
			return match.charAt(1).toUpperCase();
		});
	},

	hyphenate: function(){
		return this.replace(/[A-Z]/g, function(match){
			return ('-' + match.charAt(0).toLowerCase());
		});
	},

	capitalize: function(){
		return this.replace(/\b[a-z]/g, function(match){
			return match.toUpperCase();
		});
	},

	escapeRegExp: function(){
		return this.replace(/([-.*+?^${}()|[\]\/\\])/g, '\\$1');
	},

	toInt: function(base){
		return parseInt(this, base || 10);
	},

	toFloat: function(){
		return parseFloat(this);
	},

	hexToRgb: function(array){
		var hex = this.match(/^#?(\w{1,2})(\w{1,2})(\w{1,2})$/);
		return (hex) ? hex.slice(1).hexToRgb(array) : null;
	},

	rgbToHex: function(array){
		var rgb = this.match(/\d{1,3}/g);
		return (rgb) ? rgb.rgbToHex(array) : null;
	},

	stripScripts: function(option){
		var scripts = '';
		var text = this.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, function(){
			scripts += arguments[1] + '\n';
			return '';
		});
		if (option === true) $exec(scripts);
		else if ($type(option) == 'function') option(scripts, text);
		return text;
	}

});


/*
Script: Hash.js
	Contains Hash Prototypes. Provides a means for overcoming the JavaScript practical impossibility of extending native Objects.

License:
	MIT-style license.
*/

Hash.implement({

	has: Object.prototype.hasOwnProperty,

	keyOf: function(value){
		for (var key in this){
			if (this.hasOwnProperty(key) && this[key] === value) return key;
		}
		return null;
	},

	hasValue: function(value){
		return (Hash.keyOf(this, value) !== null);
	},

	extend: function(properties){
		Hash.each(properties, function(value, key){
			Hash.set(this, key, value);
		}, this);
		return this;
	},

	merge: function(properties){
		Hash.each(properties, function(value, key){
			Hash.include(this, key, value);
		}, this);
		return this;
	},

	remove: function(key){
		if (this.hasOwnProperty(key)) delete this[key];
		return this;
	},

	get: function(key){
		return (this.hasOwnProperty(key)) ? this[key] : null;
	},

	set: function(key, value){
		if (!this[key] || this.hasOwnProperty(key)) this[key] = value;
		return this;
	},

	empty: function(){
		Hash.each(this, function(value, key){
			delete this[key];
		}, this);
		return this;
	},

	include: function(key, value){
		var k = this[key];
		if (!$defined(k)) this[key] = value;
		return this;
	},

	map: function(fn, bind){
		var results = new Hash;
		Hash.each(this, function(value, key){
			results.set(key, fn.call(bind, value, key, this));
		}, this);
		return results;
	},

	filter: function(fn, bind){
		var results = new Hash;
		Hash.each(this, function(value, key){
			if (fn.call(bind, value, key, this)) results.set(key, value);
		}, this);
		return results;
	},

	every: function(fn, bind){
		for (var key in this){
			if (this.hasOwnProperty(key) && !fn.call(bind, this[key], key)) return false;
		}
		return true;
	},

	some: function(fn, bind){
		for (var key in this){
			if (this.hasOwnProperty(key) && fn.call(bind, this[key], key)) return true;
		}
		return false;
	},

	getKeys: function(){
		var keys = [];
		Hash.each(this, function(value, key){
			keys.push(key);
		});
		return keys;
	},

	getValues: function(){
		var values = [];
		Hash.each(this, function(value){
			values.push(value);
		});
		return values;
	},

	toQueryString: function(){
		var queryString = [];
		Hash.each(this, function(value, key){
			$splat(value).each(function(val){
				queryString.push(key + '=' + encodeURIComponent(val));
			});
		});
		return queryString.join('&');
	}

});

Hash.alias('keyOf', 'indexOf').alias('hasValue', 'contains').alias('remove', 'erase');


/*
Script: Event.js
	Contains the Event Native, to make the event object completely crossbrowser.

License:
	MIT-style license.
*/

var Event = new Native({

	name: 'Event',

	initialize: function(event, win){
		win = win || window;
		event = event || win.event;
		if (event.$extended) return event;
		this.$extended = true;
		var type = event.type;
		var target = event.target || event.srcElement;
		while (target && target.nodeType == 3) target = target.parentNode;
		
		if (type.match(/DOMMouseScroll|mousewheel/)){
			var wheel = (event.wheelDelta) ? event.wheelDelta / 120 : -(event.detail || 0) / 3;
		} else if (type.test(/key/)){
			var code = event.which || event.keyCode;
			var key = Event.Keys.keyOf(code);
			if (type == 'keydown'){
				var fKey = code - 111;
				if (fKey > 0 && fKey < 13) key = 'f' + fKey;
			}
			key = key || String.fromCharCode(code).toLowerCase();
		} else if (type.match(/(click|mouse|menu)/i)){
			var page = {
				x: event.pageX || event.clientX + win.document.documentElement.scrollLeft,
				y: event.pageY || event.clientY + win.document.documentElement.scrollTop
			};
			var client = {
				x: event.pageX ? event.pageX - win.pageXOffset : event.clientX,
				y: event.pageY ? event.pageY - win.pageYOffset : event.clientY
			};
			var rightClick = (event.which == 3) || (event.button == 2);
			var related = null;
			if (type.match(/over|out/)){
				switch (type){
					case 'mouseover': related = event.relatedTarget || event.fromElement; break;
					case 'mouseout': related = event.relatedTarget || event.toElement;
				}
				if ((function(){
					while (related && related.nodeType == 3) related = related.parentNode;
				}).create({attempt: Browser.Engine.gecko})() === false) related = false;
			}
		}

		return $extend(this, {
			event: event,
			type: type,
			
			page: page,
			client: client,
			rightClick: rightClick,
			
			wheel: wheel,
			
			relatedTarget: related,
			target: target,
			
			code: code,
			key: key,
			
			shift: event.shiftKey,
			control: event.ctrlKey,
			alt: event.altKey,
			meta: event.metaKey
		});
	}

});

Event.Keys = new Hash({
	'enter': 13,
	'up': 38,
	'down': 40,
	'left': 37,
	'right': 39,
	'esc': 27,
	'space': 32,
	'backspace': 8,
	'tab': 9,
	'delete': 46
});

Event.implement({

	stop: function(){
		return this.stopPropagation().preventDefault();
	},

	stopPropagation: function(){
		if (this.event.stopPropagation) this.event.stopPropagation();
		else this.event.cancelBubble = true;
		return this;
	},

	preventDefault: function(){
		if (this.event.preventDefault) this.event.preventDefault();
		else this.event.returnValue = false;
		return this;
	}

});

/*
Script: Class.js
	Contains the Class Function for easily creating, extending, and implementing reusable Classes.

License:
	MIT-style license.
*/

var Class = new Native({

	name: 'Class',

	initialize: function(properties){

		properties = properties || {};

		var klass = function(){
			for (var property in this) this[property] = $unlink(this[property]);
			
			this.parent = null;
			
			['Implements', 'Extends'].each(function(Property){
				if (!this[Property]) return;
				Class[Property](this, this[Property]);
				delete this[Property];
			}, this);

			this.constructor = klass;

			var self = (arguments[0] !== $empty && this.initialize) ? this.initialize.apply(this, arguments) : this;
			if (this.options && this.options.initialize) this.options.initialize.call(this);
			return self;
		};

		$extend(klass, this);
		klass.constructor = Class;
		klass.prototype = properties;
		return klass;
	}

});

Class.implement({

	implement: function(){
		Class.Implements(this.prototype, Array.slice(arguments));
		return this;
	}

});

Class.Implements = function(self, klasses){
	$splat(klasses).each(function(klass){
		$extend(self, ($type(klass) == 'class') ? new klass($empty) : klass);
	});
};

Class.Extends = function(self, klass){
	klass = new klass($empty);
	for (var property in klass){
		var kp = klass[property];
		var sp = self[property];
		self[property] = (function(previous, current){
			if ($defined(current) && previous != current){
				var type = $type(current);
				if (type != $type(previous)) return current;
				switch (type){
					case 'function':
						return function(){
							current.parent = self.parent = previous.bind(this);
							var value = current.apply(this, arguments);
							self.parent = current.parent;
							return value;
						};
					case 'object': return $merge(previous, current);
					default: return current;
				}
			}
			return previous;
		})(kp, sp);
	}
};

//legacy .extend support

Class.prototype.extend = function(properties){
	properties.Extends = this;
	return new Class(properties);
};

/*
Script: Class.Extras.js
	Contains Utility Classes that can be implemented into your own Classes to ease the execution of many common tasks.

License:
	MIT-style license.
*/

var Chain = new Class({

	chain: function(){
		this.$chain = (this.$chain || []).extend(arguments);
		return this;
	},

	callChain: function(){
		if (this.$chain && this.$chain.length) this.$chain.shift().apply(this, arguments);
		return this;
	},

	clearChain: function(){
		if (this.$chain) this.$chain.empty();
		return this;
	}

});

var Events = new Class({

	addEvent: function(type, fn, internal){
		if (fn != $empty){
			this.$events = this.$events || {};
			this.$events[type] = this.$events[type] || [];
			this.$events[type].include(fn);
			if (internal) fn.internal = true;
		}
		return this;
	},

	addEvents: function(events){
		for (var type in events) this.addEvent(type, events[type]);
		return this;
	},

	fireEvent: function(type, args, delay){
		if (!this.$events || !this.$events[type]) return this;
		this.$events[type].each(function(fn){
			fn.create({'bind': this, 'delay': delay, 'arguments': args})();
		}, this);
		return this;
	},

	removeEvent: function(type, fn){
		if (!this.$events || !this.$events[type]) return this;
		if (!fn.internal) this.$events[type].remove(fn);
		return this;
	},

	removeEvents: function(type){
		for (var e in this.$events){
			if (type && type != e) continue;
			var fns = this.$events[e];
			for (var i = fns.length; i--; i) this.removeEvent(e, fns[i]);
		}
		return this;
	}

});

var Options = new Class({

	setOptions: function(){
		this.options = $merge.run([this.options].extend(arguments));
		if (!this.addEvent) return this;
		for (var option in this.options){
			if ($type(this.options[option]) != 'function' || !(/^on[A-Z]/).test(option)) continue;
			this.addEvent(option, this.options[option]);
			delete this.options[option];
		}
		return this;
	}

});


/*
Script: Element.js
	One of the most important items in MooTools. Contains the dollar function, the dollars function, and an handful of cross-browser,
	time-saver methods to let you easily work with HTML Elements.

License:
	MIT-style license.
*/

Document.implement({
	
	newElement: function(tag, props){
		if (Browser.Engine.trident && props){
			['name', 'type', 'checked'].each(function(attribute){
				if (!props[attribute]) return;
				tag += ' ' + attribute + '="' + props[attribute] + '"';
				if (attribute != 'checked') delete props[attribute];
			});
			tag = '<' + tag + '>';
		}
		return $.element(this.createElement(tag)).set(props);
	},
	
	newTextNode: function(text){
		return this.createTextNode(text);
	},
	
	getDocument: function(){
		return this;
	},
	
	getWindow: function(){
		return this.defaultView || this.parentWindow;
	}

});

var Element = new Native({

	name: 'Element',

	legacy: window.Element,

	initialize: function(tag, props){
		var konstructor = Element.Constructors.get(tag);
		if (konstructor) return konstructor(props);
		if (typeof tag == 'string') return document.newElement(tag, props);
		return $(tag).set(props);
	},

	afterImplement: function(key, value){
		if (!Array[key]) Elements.implement(key, Elements.multi(key));
		Element.Prototype[key] = value;
	}

});

Element.Prototype = {$family: {name: 'element'}};

Element.Constructors = new Hash;

var IFrame = new Native({

	name: 'IFrame',

	generics: false,

	initialize: function(){
		Native.UID++;
		var params = Array.link(arguments, {properties: Object.type, iframe: $defined});
		var props = params.properties || {};
		var iframe = $(params.iframe) || false;
		var onload = props.onload || $empty;
		delete props.onload;
		props.id = props.name = $pick(props.id, props.name, iframe.id, iframe.name, 'IFrame_' + Native.UID);
		((iframe = iframe || new Element('iframe'))).set(props);
		var onFrameLoad = function(){
			var host = $try(function(){
				return iframe.contentWindow.location.host;
			});
			if (host && host == window.location.host){
				iframe.window = iframe.contentWindow;
				var win = new Window(iframe.window);
				var doc = new Document(iframe.window.document);
				$extend(win.Element.prototype, Element.Prototype);
			}
			onload.call(iframe.contentWindow);
		};
		(!window.frames[props.id]) ? iframe.addListener('load', onFrameLoad) : onFrameLoad();
		return iframe;
	}

});

var Elements = new Native({

	initialize: function(elements, options){
		options = $extend({ddup: true, cash: true}, options);
		elements = elements || [];
		if (options.ddup || options.cash){
			var uniques = {};
			var returned = [];
			for (var i = 0, l = elements.length; i < l; i++){
				var el = $.element(elements[i], !options.cash);
				if (options.ddup){
					if (uniques[el.uid]) continue;
					uniques[el.uid] = true;
				}
				returned.push(el);
			}
			elements = returned;
		}
		return (options.cash) ? $extend(elements, this) : elements;
	}

});

Elements.implement({

	filterBy: function(filter){
		if (!filter) return this;
		return new Elements(this.filter((typeof filter == 'string') ? function(item){
			return item.match(filter);
		} : filter));
	}

});

Elements.multi = function(property){
	return function(){
		var items = [];
		var elements = true;
		for (var i = 0, j = this.length; i < j; i++){
			var returns = this[i][property].apply(this[i], arguments);
			items.push(returns);
			if (elements) elements = ($type(returns) == 'element');
		}
		return (elements) ? new Elements(items) : items;
	};
};

Window.implement({

	$: function(el, notrash){
		if (el && el.$attributes) return el;
		var type = $type(el);
		return ($[type]) ? $[type](el, notrash, this.document) : null;
	},

	$$: function(selector){
		if (arguments.length == 1 && typeof selector == 'string') return this.document.getElements(selector);
		var elements = [];
		var args = Array.flatten(arguments);
		for (var i = 0, l = args.length; i < l; i++){
			var item = args[i];
			switch ($type(item)){
				case 'element': item = [item]; break;
				case 'string': item = this.document.getElements(item, true); break;
				default: item = false;
			}
			if (item) elements.extend(item);
		}
		return new Elements(elements);
	},
	
	getDocument: function(){
		return this.document;
	},
	
	getWindow: function(){
		return this;
	}

});

$.string = function(id, notrash, doc){
	id = doc.getElementById(id);
	return (id) ? $.element(id, notrash) : null;
};

$.element = function(el, notrash){
	el.uid = el.uid || [Native.UID++];
	if (!notrash && Garbage.collect(el) && !el.$family) $extend(el, Element.Prototype);
	return el;
};

$.textnode = $.window = $.document = $arguments(0);

$.number = function(uid){
	return Garbage.Elements[uid] || null;
};

Native.implement([Element, Document], {

	getElement: function(selector, notrash){
		return $(this.getElements(selector, true)[0] || null, notrash);
	},

	getElements: function(tags, nocash){
		tags = tags.split(',');
		var elements = [];
		var ddup = (tags.length > 1);
		tags.each(function(tag){
			var partial = this.getElementsByTagName(tag.trim());
			(ddup) ? elements.extend(partial) : elements = partial;
		}, this);
		return new Elements(elements, {ddup: ddup, cash: !nocash});
	}

});

Element.Storage = {

	get: function(uid){
		return (this[uid] = this[uid] || {});
	}

};

Element.Inserters = new Hash({

	before: function(context, element){
		if (element.parentNode) element.parentNode.insertBefore(context, element);
	},

	after: function(context, element){
		if (!element.parentNode) return;
		var next = element.nextSibling;
		(next) ? element.parentNode.insertBefore(context, next) : element.parentNode.appendChild(context);
	},

	bottom: function(context, element){
		element.appendChild(context);
	},

	top: function(context, element){
		var first = element.firstChild;
		(first) ? element.insertBefore(context, first) : element.appendChild(context);
	}

});

Element.Inserters.inside = Element.Inserters.bottom;

Element.Inserters.each(function(value, key){
	
	var Key = key.capitalize();
	
	Element.implement('inject' + Key, function(el){
		Element.Inserters[key](this, $(el, true));
		return this;
	});
	
	Element.implement('grab' + Key, function(el){
		Element.Inserters[key]($(el, true), this);
		return this;
	});
	
});

Element.implement({
	
	getDocument: function(){
		return this.ownerDocument;
	},
	
	getWindow: function(){
		return this.ownerDocument.getWindow();
	},

	getElementById: function(id, nocash){
		var el = this.ownerDocument.getElementById(id);
		if (!el) return null;
		for (var parent = el.parentNode; parent != this; parent = parent.parentNode){
			if (!parent) return null;
		}
		return $.element(el, nocash);
	},

	set: function(prop, value){
		switch ($type(prop)){
			case 'object':
				for (var p in prop) this.set(p, prop[p]);
				break;
			case 'string':
				var property = Element.Properties.get(prop);
				(property && property.set) ? property.set.apply(this, Array.slice(arguments, 1)) : this.setProperty(prop, value);
		}
		return this;
	},

	get: function(prop){
		var property = Element.Properties.get(prop);
		return (property && property.get) ? property.get.apply(this, Array.slice(arguments, 1)) : this.getProperty(prop);
	},

	erase: function(prop){
		var property = Element.Properties.get(prop);
		(property && property.erase) ? property.erase.apply(this, Array.slice(arguments, 1)) : this.removeProperty(prop);
		return this;
	},

	match: function(tag){
		return (!tag || Element.get(this, 'tag') == tag);
	},

	inject: function(el, where){
		Element.Inserters.get(where || 'bottom')(this, $(el, true));
		return this;
	},

	wraps: function(el, where){
		el = $(el, true);
		return this.replaces(el).grab(el);
	},

	grab: function(el, where){
		Element.Inserters.get(where || 'bottom')($(el, true), this);
		return this;
	},

	appendText: function(text, where){
		return this.grab(this.getDocument().newTextNode(text), where);
	},

	adopt: function(){
		Array.flatten(arguments).each(function(element){
			this.appendChild($(element, true));
		}, this);
		return this;
	},

	dispose: function(){
		return this.parentNode.removeChild(this);
	},

	clone: function(contents){
		var temp = new Element('div').grab(this.cloneNode(contents !== false));
		Array.each(temp.getElementsByTagName('*'), function(element){
			if (element.id) element.removeAttribute('id');
		});
		return new Element('div').set('html', temp.innerHTML).getFirst();
	},

	replaces: function(el){
		el = $(el, true);
		el.parentNode.replaceChild(this, el);
		return this;
	},

	hasClass: function(className){
		return this.className.contains(className, ' ');
	},

	addClass: function(className){
		if (!this.hasClass(className)) this.className = (this.className + ' ' + className).clean();
		return this;
	},

	removeClass: function(className){
		this.className = this.className.replace(new RegExp('(^|\\s)' + className + '(?:\\s|$)'), '$1').clean();
		return this;
	},

	toggleClass: function(className){
		return this.hasClass(className) ? this.removeClass(className) : this.addClass(className);
	},

	getComputedStyle: function(property){
		var result = null;
		if (this.currentStyle){
			result = this.currentStyle[property.camelCase()];
		} else {
			var computed = this.getWindow().getComputedStyle(this, null);
			if (computed) result = computed.getPropertyValue([property.hyphenate()]);
		}
		return result;
	},

	empty: function(){
		var elements = $A(this.getElementsByTagName('*'));
		elements.each(function(element){
			$try(Element.prototype.dispose, element);
		});
		Garbage.trash(elements);
		$try(Element.prototype.set, this, ['html', '']);
		return this;
	},

	destroy: function(){
		Garbage.kill(this.empty().dispose());
		return null;
	},

	toQueryString: function(){
		var queryString = [];
		this.getElements('input, select, textarea', true).each(function(el){
			var name = el.name, type = el.type, value = Element.get(el, 'value');
			if (value === false || !name || el.disabled) return;
			$splat(value).each(function(val){
				queryString.push(name + '=' + encodeURIComponent(val));
			});
		});
		return queryString.join('&');
	},

	getProperty: function(attribute){
		var EA = Element.Attributes, key = EA.Props[attribute];
		var value = (key) ? this[key] : this.getAttribute(attribute);
		return (EA.Bools[attribute]) ? !!value : value;
	},

	getProperties: function(){
		var args = $A(arguments);
		return args.map(function(attr){
			return this.getProperty(attr);
		}, this).associate(args);
	},

	setProperty: function(attribute, value){
		var EA = Element.Attributes, key = EA.Props[attribute], hasValue = $defined(value);
		if (key && EA.Bools[attribute]) value = (value || !hasValue) ? true : false;
		else if (!hasValue) return this.removeProperty(attribute);
		(key) ? this[key] = value : this.setAttribute(attribute, value);
		return this;
	},

	setProperties: function(attributes){
		for (var attribute in attributes) this.setProperty(attribute, attributes[attribute]);
		return this;
	},

	removeProperty: function(attribute){
		var EA = Element.Attributes, key = EA.Props[attribute], isBool = (key && EA.Bools[attribute]);
		(key) ? this[key] = (isBool) ? false : '' : this.removeAttribute(attribute);
		return this;
	},

	removeProperties: function(){
		Array.each(arguments, this.removeProperty, this);
		return this;
	}

});

(function(){

var walk = function(element, walk, start, match, all, nocash){
	var el = element[start || walk];
	var elements = [];
	while (el){
		if (el.nodeType == 1 && Element.match(el, match)){
			elements.push(el);
			if (!all) break;
		}
		el = el[walk];
	}
	return (all) ? new Elements(elements, {ddup: false, cash: !nocash}) : $(elements[0], nocash);
};

Element.implement({

	getPrevious: function(match, nocash){
		return walk(this, 'previousSibling', null, match, false, nocash);
	},

	getAllPrevious: function(match, nocash){
		return walk(this, 'previousSibling', null, match, true, nocash);
	},

	getNext: function(match, nocash){
		return walk(this, 'nextSibling', null, match, false, nocash);
	},

	getAllNext: function(match, nocash){
		return walk(this, 'nextSibling', null, match, true, nocash);
	},

	getFirst: function(match, nocash){
		return walk(this, 'nextSibling', 'firstChild', match, false, nocash);
	},

	getLast: function(match, nocash){
		return walk(this, 'previousSibling', 'lastChild', match, false, nocash);
	},

	getParent: function(match, nocash){
		return walk(this, 'parentNode', null, match, false, nocash);
	},

	getParents: function(match, nocash){
		return walk(this, 'parentNode', null, match, true, nocash);
	},

	getChildren: function(match, nocash){
		return walk(this, 'nextSibling', 'firstChild', match, true, nocash);
	},

	hasChild: function(el){
		if (!(el = $(el, true))) return false;
		return Element.getParents(el, this.get('tag'), true).contains(this);
	}

});

})();

Element.alias('dispose', 'remove').alias('getLast', 'getLastChild');

Element.Properties = new Hash;

Element.Properties.style = {

	set: function(style){
		this.style.cssText = style;
	},

	get: function(){
		return this.style.cssText;
	},

	erase: function(){
		this.style.cssText = '';
	}

};

Element.Properties.value = {get: function(){
	switch (Element.get(this, 'tag')){
		case 'select':
			var values = [];
			Array.each(this.options, function(option){
				if (option.selected) values.push(option.value);
			});
			return (this.multiple) ? values : values[0];
		case 'input': if (['checkbox', 'radio'].contains(this.type) && !this.checked) return false;
		default: return $pick(this.value, false);
	}
}};

Element.Properties.tag = {get: function(){
	return this.tagName.toLowerCase();
}};

Element.Properties.html = {set: function(){
	return this.innerHTML = Array.flatten(arguments).join('');
}};

Element.implement({

	getText: function(){
		return this.get('text');
	},

	setText: function(text){
		return this.set('text', text);
	},

	setHTML: function(){
		return this.set('html', arguments);
	},
	
	getHTML: function(){
		return this.get('html');
	},

	getTag: function(){
		return this.get('tag');
	}

});

Native.implement([Element, Window, Document], {

	addListener: function(type, fn){
		if (this.addEventListener) this.addEventListener(type, fn, false);
		else this.attachEvent('on' + type, fn);
		return this;
	},

	removeListener: function(type, fn){
		if (this.removeEventListener) this.removeEventListener(type, fn, false);
		else this.detachEvent('on' + type, fn);
		return this;
	},

	retrieve: function(property, dflt){
		var storage = Element.Storage.get(this.uid);
		var prop = storage[property];
		if ($defined(dflt) && !$defined(prop)) prop = storage[property] = dflt;
		return $pick(prop);
	},

	store: function(property, value){
		var storage = Element.Storage.get(this.uid);
		storage[property] = value;
		return this;
	},

	eliminate: function(property){
		var storage = Element.Storage.get(this.uid);
		delete storage[property];
		return this;
	}

});

Element.Attributes = new Hash({
	Props: {'html': 'innerHTML', 'class': 'className', 'for': 'htmlFor', 'text': (Browser.Engine.trident) ? 'innerText' : 'textContent'},
	Bools: ['compact', 'nowrap', 'ismap', 'declare', 'noshade', 'checked', 'disabled', 'readonly', 'multiple', 'selected', 'noresize', 'defer'],
	Camels: ['value', 'accessKey', 'cellPadding', 'cellSpacing', 'colSpan', 'frameBorder', 'maxLength', 'readOnly', 'rowSpan', 'tabIndex', 'useMap']
});

(function(EA){

	var EAB = EA.Bools, EAC = EA.Camels;
	EA.Bools = EAB = EAB.associate(EAB);
	Hash.extend(Hash.merge(EA.Props, EAB), EAC.associate(EAC.map(function(v){
		return v.toLowerCase();
	})));
	EA.remove('Camels');

})(Element.Attributes);

var Garbage = {

	Elements: {},

	ignored: {object: 1, embed: 1, OBJECT: 1, EMBED: 1},

	collect: function(el){
		if (el.$attributes) return true;
		if (Garbage.ignored[el.tagName]) return false;
		Garbage.Elements[el.uid] = el;
		el.$attributes = {};
		return true;
	},

	trash: function(elements){
		for (var i = elements.length, el; i--; i) Garbage.kill(elements[i]);
	},

	kill: function(el){
		if (!el || !el.$attributes) return;
		delete Garbage.Elements[el.uid];
		if (el.retrieve('events')) el.removeEvents();
		for (var p in el.$attributes) el.$attributes[p] = null;
		if (Browser.Engine.trident){
			for (var d in Element.Prototype) el[d] = null;
		}
		el.$attributes = el.uid = null;
	},

	empty: function(){
		for (var uid in Garbage.Elements) Garbage.kill(Garbage.Elements[uid]);
	}

};

window.addListener('beforeunload', function(){
	window.addListener('unload', Garbage.empty);
	if (Browser.Engine.trident) window.addListener('unload', CollectGarbage);
});


/*
Script: Element.Event.js
	Contains Element methods for dealing with events, and custom Events.

License:
	MIT-style license.
*/

Element.Properties.events = {set: function(events){
	this.addEvents(events);
}};

Native.implement([Element, Window, Document], {

	addEvent: function(type, fn){
		var events = this.retrieve('events', {});
		events[type] = events[type] || {'keys': [], 'values': []};
		if (events[type].keys.contains(fn)) return this;
		events[type].keys.push(fn);
		var realType = type, custom = Element.Events.get(type), condition = fn, self = this;
		if (custom){
			if (custom.onAdd) custom.onAdd.call(this, fn);
			if (custom.condition){
				condition = function(event){
					if (custom.condition.call(this, event)) return fn.call(this, event);
					return false;
				};
			}
			realType = custom.base || realType;
		}
		var defn = function(){
			return fn.call(self);
		};
		var nativeEvent = Element.NativeEvents[realType] || 0;
		if (nativeEvent){
			if (nativeEvent == 2){
				defn = function(event){
					event = new Event(event, self.getWindow());
					if (condition.call(self, event) === false) event.stop();
				};
			}
			this.addListener(realType, defn);
		}
		events[type].values.push(defn);
		return this;
	},

	removeEvent: function(type, fn){
		var events = this.retrieve('events');
		if (!events || !events[type]) return this;
		var pos = events[type].keys.indexOf(fn);
		if (pos == -1) return this;
		var key = events[type].keys.splice(pos, 1)[0];
		var value = events[type].values.splice(pos, 1)[0];
		var custom = Element.Events.get(type);
		if (custom){
			if (custom.onRemove) custom.onRemove.call(this, fn);
			type = custom.base || type;
		}
		return (Element.NativeEvents[type]) ? this.removeListener(type, value) : this;
	},

	addEvents: function(events){
		for (var event in events) this.addEvent(event, events[event]);
		return this;
	},

	removeEvents: function(type){
		var events = this.retrieve('events');
		if (!events) return this;
		if (!type){
			for (var evType in events) this.removeEvents(evType);
			events = null;
		} else if (events[type]){
			while (events[type].keys[0]) this.removeEvent(type, events[type].keys[0]);
			events[type] = null;
		}
		return this;
	},

	fireEvent: function(type, args, delay){
		var events = this.retrieve('events');
		if (!events || !events[type]) return this;
		events[type].keys.each(function(fn){
			fn.create({'bind': this, 'delay': delay, 'arguments': args})();
		}, this);
		return this;
	},

	cloneEvents: function(from, type){
		from = $(from);
		var fevents = from.retrieve('events');
		if (!fevents) return this;
		if (!type){
			for (var evType in fevents) this.cloneEvents(from, evType);
		} else if (fevents[type]){
			fevents[type].keys.each(function(fn){
				this.addEvent(type, fn);
			}, this);
		}
		return this;
	}

});

Element.NativeEvents = {
	'click': 2, 'dblclick': 2, 'mouseup': 2, 'mousedown': 2, 'contextmenu': 2, //mouse buttons
	'mousewheel': 2, 'DOMMouseScroll': 2, //mouse wheel
	'mouseover': 2, 'mouseout': 2, 'mousemove': 2, 'selectstart': 2, 'selectend': 2, //mouse movement
	'keydown': 2, 'keypress': 2, 'keyup': 2, //keyboard
	'focus': 2, 'blur': 2, 'change': 2, 'reset': 2, 'select': 2, 'submit': 2, //form elements
	'load': 1, 'unload': 1, 'beforeunload': 1, 'resize': 1, 'move': 1, 'DOMContentLoaded': 1, 'readystatechange': 1, //window
	'error': 1, 'abort': 1, 'scroll': 1 //misc
};

(function(){

var checkRelatedTarget = function(event){
	var related = event.relatedTarget;
	if (!related) return true;
	return ($type(this) != 'document' && related != this && related.prefix != 'xul' && !this.hasChild(related));
};

Element.Events = new Hash({

	mouseenter: {

		base: 'mouseover',

		condition: checkRelatedTarget

	},

	mouseleave: {

		base: 'mouseout',

		condition: checkRelatedTarget

	},

	mousewheel: {

		base: (Browser.Engine.gecko) ? 'DOMMouseScroll' : 'mousewheel'

	}

});

})();

/*
Script: Element.Style.js
	Contains methods for interacting with the styles of Elements in a fashionable way.

License:
	MIT-style license.
*/

Element.Properties.styles = {set: function(styles){
	this.setStyles(styles);
}};

Element.Properties.opacity = {

	set: function(opacity, novisibility){
		if (!novisibility){
			if (opacity == 0){
				if (this.style.visibility != 'hidden') this.style.visibility = 'hidden';
			} else {
				if (this.style.visibility != 'visible') this.style.visibility = 'visible';
			}
		}
		if (!this.currentStyle || !this.currentStyle.hasLayout) this.style.zoom = 1;
		if (Browser.Engine.trident) this.style.filter = (opacity == 1) ? '' : 'alpha(opacity=' + opacity * 100 + ')';
		this.style.opacity = opacity;
		this.store('opacity', opacity);
	},

	get: function(){
		return this.retrieve('opacity', 1);
	}

};

Element.implement({
	
	setOpacity: function(value){
		return this.set('opacity', value, true);
	},
	
	getOpacity: function(){
		return this.get('opacity');
	},

	setStyle: function(property, value){
		switch (property){
			case 'opacity': return this.set('opacity', parseFloat(value));
			case 'float': property = (Browser.Engine.trident) ? 'styleFloat' : 'cssFloat';
		}
		property = property.camelCase();
		if ($type(value) != 'string'){
			var map = (Element.Styles.get(property) || '@').split(' ');
			value = $splat(value).map(function(val, i){
				if (!map[i]) return '';
				return ($type(val) == 'number') ? map[i].replace('@', Math.round(val)) : val;
			}).join(' ');
		} else if (value == String(Number(value))){
			value = Math.round(value);
		}
		this.style[property] = value;
		return this;
	},

	getStyle: function(property){
		switch (property){
			case 'opacity': return this.get('opacity');
			case 'float': property = (Browser.Engine.trident) ? 'styleFloat' : 'cssFloat';
		}
		property = property.camelCase();
		var result = this.style[property];
		if (!$chk(result)){
			result = [];
			for (var style in Element.ShortStyles){
				if (property != style) continue;
				for (var s in Element.ShortStyles[style]) result.push(this.getStyle(s));
				return result.join(' ');
			}
			result = this.getComputedStyle(property);
		}
		if (result){
			result = String(result);
			var color = result.match(/rgba?\([\d\s,]+\)/);
			if (color) result = result.replace(color[0], color[0].rgbToHex());
		}
		if (Browser.Engine.presto || (Browser.Engine.trident && !$chk(parseInt(result)))){
			if (property.test(/^(height|width)$/)){
				var values = (property == 'width') ? ['left', 'right'] : ['top', 'bottom'], size = 0;
				values.each(function(value){
					size += this.getStyle('border-' + value + '-width').toInt() + this.getStyle('padding-' + value).toInt();
				}, this);
				return this['offset' + property.capitalize()] - size + 'px';
			}
			if (Browser.Engine.presto && String(result).test('px')) return result;
			if (property.test(/(border(.+)Width|margin|padding)/)) return '0px';
		}
		return result;
	},

	setStyles: function(styles){
		for (var style in styles) this.setStyle(style, styles[style]);
		return this;
	},

	getStyles: function(){
		var result = {};
		Array.each(arguments, function(key){
			result[key] = this.getStyle(key);
		}, this);
		return result;
	}

});

Element.Styles = new Hash({
	width: '@px', height: '@px', left: '@px', top: '@px', bottom: '@px', right: '@px', maxWidth: '@px', maxHeight: '@px',
	backgroundColor: 'rgb(@, @, @)', backgroundPosition: '@px @px', color: 'rgb(@, @, @)',
	fontSize: '@px', letterSpacing: '@px', lineHeight: '@px', clip: 'rect(@px @px @px @px)',
	margin: '@px @px @px @px', padding: '@px @px @px @px', border: '@px @ rgb(@, @, @) @px @ rgb(@, @, @) @px @ rgb(@, @, @)',
	borderWidth: '@px @px @px @px', borderStyle: '@ @ @ @', borderColor: 'rgb(@, @, @) rgb(@, @, @) rgb(@, @, @) rgb(@, @, @)',
	zIndex: '@', 'zoom': '@', fontWeight: '@',
	textIndent: '@px', opacity: '@'
});

Element.ShortStyles = {'margin': {}, 'padding': {}, 'border': {}, 'borderWidth': {}, 'borderStyle': {}, 'borderColor': {}};

['Top', 'Right', 'Bottom', 'Left'].each(function(direction){
	var Short = Element.ShortStyles;
	var All = Element.Styles;
	['margin', 'padding'].each(function(style){
		var sd = style + direction;
		Short[style][sd] = All[sd] = '@px';
	});
	var bd = 'border' + direction;
	Short.border[bd] = All[bd] = '@px @ rgb(@, @, @)';
	var bdw = bd + 'Width', bds = bd + 'Style', bdc = bd + 'Color';
	Short[bd] = {};
	Short.borderWidth[bdw] = Short[bd][bdw] = All[bdw] = '@px';
	Short.borderStyle[bds] = Short[bd][bds] = All[bds] = '@';
	Short.borderColor[bdc] = Short[bd][bdc] = All[bdc] = 'rgb(@, @, @)';
});


/*
Script: Element.Dimensions.js
	Contains methods to work with size, scroll, or positioning of Elements and the window object.

License:
	MIT-style license.

Note:
	Dimensions requires an XHTML doctype.
*/

(function(){

function $body(el){
	return el.tagName.toLowerCase() == 'body';
};

Element.implement({
	
	positioned: function(){
		if ($body(this)) return true;
		return (Element.getComputedStyle(this, 'position') != 'static');
	},
	
	getOffsetParent: function(){
		if ($body(this)) return null;
		if (!Browser.Engine.trident) return $(this.offsetParent);
		var el = this;
		while ((el = el.parentNode)){
			if (Element.positioned(el)) return $(el);
		}
		return null;
	},
	
	getSize: function(){
		if ($body(this)) return this.getWindow().getSize();
		return {x: this.offsetWidth, y: this.offsetHeight};
	},
	
	getScrollSize: function(){
		if ($body(this)) return this.getWindow().getScrollSize();
		return {x: this.scrollWidth, y: this.scrollHeight};
	},
	
	getScroll: function(){
		if ($body(this)) return this.getWindow().getScroll();
		return {x: this.scrollLeft, y: this.scrollTop};
	},
	
	scrollTo: function(x, y){
		if ($body(this)) return this.getWindow().scrollTo(x, y);
		this.scrollLeft = x;
		this.scrollTop = y;
		return this;
	},
	
	getPosition: function(relative){
		if ($body(this)) return {x: 0, y: 0};
		var el = this, position = {x: 0, y: 0};
		while (el){
			position.x += el.offsetLeft;
			position.y += el.offsetTop;
			el = el.offsetParent;
		}
		var rpos = (relative) ? $(relative).getPosition() : {x: 0, y: 0};
		return {x: position.x - rpos.x, y: position.y - rpos.y};
	},
	
	getCoordinates: function(element){
		if ($body(this)) return this.getWindow().getCoordinates();
		var position = this.getPosition(element), size = this.getSize();
		var obj = {'top': position.y, 'left': position.x, 'width': size.x, 'height': size.y};
		obj.right = obj.left + obj.width;
		obj.bottom = obj.top + obj.height;
		return obj;
	},
	
	getRelativePosition: function(){
		return this.getPosition(this.getOffsetParent());
	},
	
	computePosition: function(obj){
		return {
			left: obj.x - (this.getComputedStyle('margin-left').toInt() || 0),
			top: obj.y - (this.getComputedStyle('margin-top').toInt() || 0)
		};
	},

	position: function(obj){
		return this.setStyles(this.computePosition(obj));
	}
	
});

})();

Native.implement([Window, Document], {
	
	getSize: function(){
		var body = this.getDocument().body, html = this.getDocument().documentElement;
		if (Browser.Engine.webkit419) return {x: this.innerWidth, y: this.innerHeight};
		return {x: html.clientWidth, y: html.clientHeight};
	},

	getScroll: function(){
		var html = this.getDocument().documentElement;
		return {x: $pick(this.pageXOffset, html.scrollLeft), y: $pick(this.pageYOffset, html.scrollTop)};
	},

	getScrollSize: function(){
		var html = this.getDocument().documentElement, body = this.getDocument().body;
		if (Browser.Engine.trident) return {x: Math.max(html.clientWidth, html.scrollWidth), y: Math.max(html.clientHeight, html.scrollHeight)};
		if (Browser.Engine.webkit) return {x: body.scrollWidth, y: body.scrollHeight};
		return {x: html.scrollWidth, y: html.scrollHeight};
	},
	
	getPosition: function(){
		return {x: 0, y: 0};
	},
	
	getCoordinates: function(){
		var size = this.getSize();
		return {top: 0, left: 0, height: size.y, width: size.x, bottom: size.y, right: size.x};
	}
	
});

Native.implement([Window, Document, Element], {
	
	getHeight: function(){
		return this.getSize().y;
	},
	
	getWidth: function(){
		return this.getSize().x;
	},
	
	getScrollTop: function(){
		return this.getScroll().y;
	},
	
	getScrollLeft: function(){
		return this.getScroll().x;
	},
	
	getScrollHeight: function(){
		return this.getScrollSize().y;
	},
	
	getScrollWidth: function(){
		return this.getScrollSize().x;
	},
	
	getTop: function(){
		return this.getPosition().y;
	},
	
	getLeft: function(){
		return this.getPosition().x;
	}
	
});

/*
Script: Selectors.js
	Adds advanced CSS Querying capabilities for selecting elements.

License:
	MIT-style license.
*/

Native.implement([Element, Document], {

	getElements: function(selectors, nocash){
		var Local = {};
		selectors = selectors.split(',');
		var elements = [], j = selectors.length;
		var ddup = (j > 1);
		for (var i = 0; i < j; i++){
			var selector = selectors[i], items = [], separators = [];
			selector = selector.trim().replace(Selectors.sRegExp, function(match){
				if (match.charAt(2)) match = match.trim();
				separators.push(match.charAt(0));
				return ':)' + match.charAt(1);
			}).split(':)');
			for (var k = 0, l = selector.length; k < l; k++){
				var sel = Selectors.parse(selector[k]);
				if (!sel) return [];
				var temp = Selectors.Method.getParam(items, separators[k - 1] || false, this, sel, Local);
				if (!temp) break;
				items = temp;
			}
			var partial = Selectors.Method.getItems(items, this);
			elements = (ddup) ? elements.concat(partial) : partial;
		}
		return new Elements(elements, {ddup: ddup, cash: !nocash});
	}

});

Window.implement({

	$E: function(selector){
		return this.document.getElement(selector);
	}

});

var Selectors = {

	regExp: (/:([^-:(]+)[^:(]*(?:\((["']?)(.*?)\2\))?|\[(\w+)(?:([!*^$~|]?=)(["']?)(.*?)\6)?\]|\.[\w-]+|#[\w-]+|\w+|\*/g),

	sRegExp: (/\s*([+>~\s])[a-zA-Z#.*\s]/g)

};

Selectors.parse = function(selector){
	var params = {tag: '*', id: null, classes: [], attributes: [], pseudos: []};
	selector = selector.replace(Selectors.regExp, function(bit){
		switch (bit.charAt(0)){
			case '.': params.classes.push(bit.slice(1)); break;
			case '#': params.id = bit.slice(1); break;
			case '[': params.attributes.push([arguments[4], arguments[5], arguments[7]]); break;
			case ':':
				var xparser = Selectors.Pseudo.get(arguments[1]);
				if (!xparser){
					params.attributes.push([arguments[1], arguments[3] ? '=' : '', arguments[3]]);
					break;
				}
				var pseudo = {'name': arguments[1], 'parser': xparser, 'argument': (xparser.parser) ? xparser.parser(arguments[3]) : arguments[3]};
				params.pseudos.push(pseudo);
			break;
			default: params.tag = bit;
		}
		return '';
	});
	return params;
};

Selectors.Pseudo = new Hash;

Selectors.XPath = {

	getParam: function(items, separator, context, params){
		var temp = '';
		switch (separator){
			case ' ': temp += '//'; break;
			case '>': temp += '/'; break;
			case '+': temp += '/following-sibling::*[1]/self::'; break;
			case '~': temp += '/following-sibling::'; break;
		}
		temp += (context.namespaceURI) ? 'xhtml:' + params.tag : params.tag;
		var i;
		for (i = params.pseudos.length; i--; i){
			var pseudo = params.pseudos[i];
			if (pseudo.parser && pseudo.parser.xpath) temp += pseudo.parser.xpath(pseudo.argument);
			else temp += ($chk(pseudo.argument)) ? '[@' + pseudo.name + '="' + pseudo.argument + '"]' : '[@' + pseudo.name + ']';
		}
		if (params.id) temp += '[@id="' + params.id + '"]';
		for (i = params.classes.length; i--; i) temp += '[contains(concat(" ", @class, " "), " ' + params.classes[i] + ' ")]';
		for (i = params.attributes.length; i--; i){
			var bits = params.attributes[i];
			switch (bits[1]){
				case '=': temp += '[@' + bits[0] + '="' + bits[2] + '"]'; break;
				case '*=': temp += '[contains(@' + bits[0] + ', "' + bits[2] + '")]'; break;
				case '^=': temp += '[starts-with(@' + bits[0] + ', "' + bits[2] + '")]'; break;
				case '$=': temp += '[substring(@' + bits[0] + ', string-length(@' + bits[0] + ') - ' + bits[2].length + ' + 1) = "' + bits[2] + '"]'; break;
				case '!=': temp += '[@' + bits[0] + '!="' + bits[2] + '"]'; break;
				case '~=': temp += '[contains(concat(" ", @' + bits[0] + ', " "), " ' + bits[2] + ' ")]'; break;
				case '|=': temp += '[contains(concat("-", @' + bits[0] + ', "-"), "-' + bits[2] + '-")]'; break;
				default: temp += '[@' + bits[0] + ']';
			}
		}
		items.push(temp);
		return items;
	},

	getItems: function(items, context){
		var elements = [];
		var doc = context.getDocument();
		var xpath = doc.evaluate('.//' + items.join(''), context, Selectors.XPath.resolver, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
		for (var i = 0, j = xpath.snapshotLength; i < j; i++) elements[i] = xpath.snapshotItem(i);
		return elements;
	},

	resolver: function(prefix){
		return (prefix == 'xhtml') ? 'http://www.w3.org/1999/xhtml' : false;
	}

};

Selectors.Filter = {

	getParam: function(items, separator, context, params, Local){
		var found = [];
		var tag = params.tag;
		if (separator){
			var uniques = {}, child, children, item, k, l;
			var add = function(child){
				child.uid = child.uid || [Native.UID++];
				if (!uniques[child.uid] && Selectors.Filter.match(child, params, Local)){
					uniques[child.uid] = true;
					found.push(child);
					return true;
				}
				return false;
			};
			for (var i = 0, j = items.length; i < j; i++){
				item = items[i];	
				switch(separator){
					case ' ':
						children = item.getElementsByTagName(tag);
						params.tag = false;
						for (k = 0, l = children.length; k < l; k++) add(children[k]);
					break;
					case '>':
						children = item.childNodes;
						for (k = 0, l = children.length; k < l; k++){
							if (children[k].nodeType == 1) add(children[k]);
						}
					break;
					case '+':
						while ((item = item.nextSibling)){
							if (item.nodeType == 1){
								add(item);
								break;
							}
						}
					break;
					case '~':
						while ((item = item.nextSibling)){
							if (item.nodeType == 1 && add(item)) break;
						}
					break;
				}
			}	
			return found;
		}
		if (params.id){
			el = context.getElementById(params.id, true);
			params.id = false;
			return (el && Selectors.Filter.match(el, params, Local)) ? [el] : false;
		} else {
			items = context.getElementsByTagName(tag);
			params.tag = false;
			for (var m = 0, n = items.length; m < n; m++){
				if (Selectors.Filter.match(items[m], params, Local)) found.push(items[m]);
			}
		}
		return found;
	},

	getItems: $arguments(0)

};

Selectors.Filter.match = function(el, params, Local){
	Local = Local || {};

	if (params.id && params.id != el.id) return false;
	if (params.tag && params.tag != '*' && params.tag != el.tagName.toLowerCase()) return false;

	var i;

	for (i = params.classes.length; i--; i){
		if (!el.className || !el.className.contains(params.classes[i], ' ')) return false;
	}

	for (i = params.attributes.length; i--; i){
		var bits = params.attributes[i];
		var result = Element.prototype.getProperty.call(el, bits[0]);
		if (!result) return false;
		if (!bits[1]) continue;
		var condition;
		switch (bits[1]){
			case '=': condition = (result == bits[2]); break;
			case '*=': condition = (result.contains(bits[2])); break;
			case '^=': condition = (result.substr(0, bits[2].length) == bits[2]); break;
			case '$=': condition = (result.substr(result.length - bits[2].length) == bits[2]); break;
			case '!=': condition = (result != bits[2]); break;
			case '~=': condition = result.contains(bits[2], ' '); break;
			case '|=': condition = result.contains(bits[2], '-');
		}

		if (!condition) return false;
	}

	for (i = params.pseudos.length; i--; i){
		if (!params.pseudos[i].parser.filter.call(el, params.pseudos[i].argument, Local)) return false;
	}

	return true;
};

Selectors.Method = (Browser.Features.xpath) ? Selectors.XPath : Selectors.Filter;

Element.implement({

	match: function(selector){
		return (!selector || Selectors.Filter.match(this, Selectors.parse(selector)));
	}

});


/*
Script: Selectors.Pseudo.js
	Adds CSS3 and other custom pseudo selectors support for selecting elements.

License:
	MIT-style license.

See Also:
	<http://www.w3.org/TR/2005/WD-css3-selectors-20051215/#pseudo-classes>
*/

Selectors.Pseudo.enabled = {

	xpath: function(){
		return '[not(@disabled)]';
	},

	filter: function(){
		return !(this.disabled);
	}
};


Selectors.Pseudo.empty = {

	xpath: function(){
		return '[not(node())]';
	},

	filter: function(){
		return !(this.innerText || this.textContent || '').length;
	}

};


Selectors.Pseudo.contains = {

	xpath: function(argument){
		return '[contains(text(), "' + argument + '")]';
	},

	filter: function(argument){
		for (var i = this.childNodes.length; i--; i){
			var child = this.childNodes[i];
			if (child.nodeName && child.nodeType == 3 && child.nodeValue.contains(argument)) return true;
		}
		return false;
	}

};

Selectors.Pseudo.nth = {

	parser: function(argument){
		argument = (argument) ? argument.match(/^([+-]?\d*)?([devon]+)?([+-]?\d*)?$/) : [null, 1, 'n', 0];
		if (!argument) return false;
		var inta = parseInt(argument[1]);
		var a = ($chk(inta)) ? inta : 1;
		var special = argument[2] || false;
		var b = parseInt(argument[3]) || 0;
		b = b - 1;
		while (b < 1) b += a;
		while (b >= a) b -= a;
		switch (special){
			case 'n': return {'a': a, 'b': b, 'special': 'n'};
			case 'odd': return {'a': 2, 'b': 0, 'special': 'n'};
			case 'even': return {'a': 2, 'b': 1, 'special': 'n'};
			case 'first': return {'a': 0, 'special': 'index'};
			case 'last': return {'special': 'last'};
			case 'only': return {'special': 'only'};
			default: return {'a': (a - 1), 'special': 'index'};
		}
	},

	xpath: function(argument){
		switch (argument.special){
			case 'n': return '[count(preceding-sibling::*) mod ' + argument.a + ' = ' + argument.b + ']';
			case 'last': return '[count(following-sibling::*) = 0]';
			case 'only': return '[not(preceding-sibling::* or following-sibling::*)]';
			default: return '[count(preceding-sibling::*) = ' + argument.a + ']';
		}
	},

	filter: function(argument, Local){
		var count = 0, el = this;
		switch (argument.special){
			case 'n':
				Local.Positions = Local.Positions || {};
				if (!Local.Positions[this.uid]){
					var children = this.parentNode.childNodes;
					for (var i = 0, l = children.length; i < l; i++){
						var child = children[i];
						if (child.nodeType != 1) continue;
						child.uid = child.uid || [Native.UID++];
						Local.Positions[child.uid] = count++;
					}
				}
				return (Local.Positions[this.uid] % argument.a == argument.b);
			case 'last':
				while ((el = el.nextSibling)){
					if (el.nodeType == 1) return false;
				}
				return true;
			case 'only':
				var prev = el;
				while((prev = prev.previousSibling)){
					if (prev.nodeType == 1) return false;
				}
				var next = el;
				while ((next = next.nextSibling)){
					if (next.nodeType == 1) return false;
				}
				return true;
			case 'index':
				while ((el = el.previousSibling)){
					if (el.nodeType == 1 && ++count > argument.a) return false;
				}
				return true;
		}
		return false;
	}

};

Selectors.Pseudo.extend({

	'even': {
		parser: function(){
			return {'a': 2, 'b': 1, 'special': 'n'};
		},
		xpath: Selectors.Pseudo.nth.xpath,
		filter: Selectors.Pseudo.nth.filter
	},

	'odd': {
		parser: function(){
			return {'a': 2, 'b': 0, 'special': 'n'};
		},
		xpath: Selectors.Pseudo.nth.xpath,
		filter: Selectors.Pseudo.nth.filter
	},

	'first': {
		parser: function(){
			return {'a': 0, 'special': 'index'};
		},
		xpath: Selectors.Pseudo.nth.xpath,
		filter: Selectors.Pseudo.nth.filter
	},

	'last': {
		parser: function(){
			return {'special': 'last'};
		},
		xpath: Selectors.Pseudo.nth.xpath,
		filter: Selectors.Pseudo.nth.filter
	},

	'only': {
		parser: function(){
			return {'special': 'only'};
		},
		xpath: Selectors.Pseudo.nth.xpath,
		filter: Selectors.Pseudo.nth.filter
	}

});


/*
Script: Domready.js
	Contains the domready custom event.

License:
	MIT-style license.
*/

Element.Events.domready = {

	onAdd: function(fn){
		
		if (Browser.loaded) return fn.call(this);
		
		var self = this, win = this.getWindow(), doc = this.getDocument();
		
		var domready = function(){
			if (!arguments.callee.done){
				arguments.callee.done = true;
				fn.call(self);
			};
			return true;
		};
		
		var states = (Browser.Engine.webkit) ? ['loaded', 'complete'] : 'complete';
		
		var check = function(context){
			if (states.contains(context.readyState)) return domready();
			return false;
		};
		
		if (doc.readyState && Browser.Engine.webkit){
			
			(function(){
				if (!check(doc)) arguments.callee.delay(50);
			})();
			
		} else if (doc.readyState && Browser.Engine.trident){
			
			var script = $('ie_domready');
			if (!script){
				var src = (win.location.protocol == 'https:') ? '//:' : 'javascript:void(0)';
				doc.write('<script id="ie_domready" defer src="' + src + '"></script>');
				script = $('ie_domready');
			}
			if (!check(script)) script.addEvent('readystatechange', check.pass(script));
			
		} else {
			
			win.addEvent('load', domready);
			doc.addEvent('DOMContentLoaded', domready);
			
		}
		
		return null;
	}

};

window.addEvent('domready', function(){
	Browser.loaded = true;
});

/*
Script: JSON.js
	JSON encoder and decoder.

License:
	MIT-style license.

See Also:
	<http://www.json.org/>
*/

var JSON = new Hash({

	encode: function(obj){
		switch ($type(obj)){
			case 'string':
				return '"' + obj.replace(/[\x00-\x1f\\"]/g, JSON.$replaceChars) + '"';
			case 'array':
				return '[' + String(obj.map(JSON.encode).filter($defined)) + ']';
			case 'object': case 'hash':
				var string = [];
				Hash.each(obj, function(value, key){
					var json = JSON.encode(value);
					if (json) string.push(JSON.encode(key) + ':' + json);
				});
				return '{' + String(string) + '}';
			case 'number': case 'boolean': return String(obj);
			case false: return 'null';
		}
		return null;
	},

	$specialChars: {'\b': '\\b', '\t': '\\t', '\n': '\\n', '\f': '\\f', '\r': '\\r', '"' : '\\"', '\\': '\\\\'},

	$replaceChars: function(chr){
		return JSON.$specialChars[chr] || '\\u00' + Math.floor(chr.charCodeAt() / 16).toString(16) + (chr.charCodeAt() % 16).toString(16);
	},

	decode: function(string, secure){
		if ($type(string) != 'string' || !string.length) return null;
		if (secure && !(/^[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]*$/).test(string.replace(/\\./g, '@').replace(/"[^"\\\n\r]*"/g, ''))) return null;
		return eval('(' + string + ')');
	}

});

Native.implement([Hash, Array, String, Number], {

	toJSON: function(){
		return JSON.encode(this);
	}

});


/*
Script: Cookie.js
	Class for creating, loading, and saving browser Cookies.

License:
	MIT-style license.

Credits:
	Based on the functions by Peter-Paul Koch (http://quirksmode.org).
*/

var Cookie = new Class({
	
	Implements: Options,
	
	options: {
		path: false,
		domain: false,
		duration: false,
		secure: false,
		document: document
	},
	
	initialize: function(key, options){
		this.key = key;
		this.setOptions(options);
	},
	
	write: function(value){
		value = encodeURIComponent(value);
		if (this.options.domain) value += '; domain=' + this.options.domain;
		if (this.options.path) value += '; path=' + this.options.path;
		if (this.options.duration){
			var date = new Date();
			date.setTime(date.getTime() + this.options.duration * 24 * 60 * 60 * 1000);
			value += '; expires=' + date.toGMTString();
		}
		if (this.options.secure) value += '; secure';
		this.options.document.cookie = this.key + '=' + value;
		return this;
	},
	
	read: function(){
		var value = this.options.document.cookie.match('(?:^|;)\\s*' + this.key.escapeRegExp() + '=([^;]*)');
		return value ? decodeURIComponent(value[1]) : null;
	},

	erase: function(){
		new Cookie(this.key, $merge(this.options, {duration: -1})).write('');
		return this;
	}
	
});

Cookie.set = function(key, value, options){
	return new Cookie(key, options).write(value);
};

Cookie.get = function(key){
	return new Cookie(key).read();
};

Cookie.remove = function(key, options){
	return new Cookie(key, options).erase();
};

/*
Script: Color.js
	Class for creating and manipulating colors in JavaScript. Supports HSB -> RGB Conversions and vice versa.

License:
	MIT-style license.
*/

var Color = new Native({
  
	initialize: function(color, type){
		if (arguments.length >= 3){
			type = "rgb"; color = Array.slice(arguments, 0, 3);
		} else if (typeof color == 'string'){
			if (color.match(/rgb/)) color = color.rgbToHex().hexToRgb(true);
			else if (color.match(/hsb/)) color = color.hsbToRgb();
			else color = color.hexToRgb(true);
		}
		type = type || 'rgb';
		switch (type){
			case 'hsb':
				var old = color;
				color = color.hsbToRgb();
				color.hsb = old;
			break;
			case 'hex': color = color.hexToRgb(true); break;
		}
		color.rgb = color.slice(0, 3);
		color.hsb = color.hsb || color.rgbToHsb();
		color.hex = color.rgbToHex();
		return $extend(color, this);
	}

});

Color.implement({

	mix: function(){
		var colors = Array.slice(arguments);
		var alpha = ($type(colors.getLast()) == 'number') ? colors.pop() : 50;
		var rgb = this.slice();
		colors.each(function(color){
			color = new Color(color);
			for (var i = 0; i < 3; i++) rgb[i] = Math.round((rgb[i] / 100 * (100 - alpha)) + (color[i] / 100 * alpha));
		});
		return new Color(rgb, 'rgb');
	},

	invert: function(){
		return new Color(this.map(function(value){
			return 255 - value;
		}));
	},

	setHue: function(value){
		return new Color([value, this.hsb[1], this.hsb[2]], 'hsb');
	},

	setSaturation: function(percent){
		return new Color([this.hsb[0], percent, this.hsb[2]], 'hsb');
	},

	setBrightness: function(percent){
		return new Color([this.hsb[0], this.hsb[1], percent], 'hsb');
	}

});

function $RGB(r, g, b){
	return new Color([r, g, b], 'rgb');
};

function $HSB(h, s, b){
	return new Color([h, s, b], 'hsb');
};

function $HEX(hex){
	return new Color(hex, 'hex');
};

Array.implement({

	rgbToHsb: function(){
		var red = this[0], green = this[1], blue = this[2];
		var hue, saturation, brightness;
		var max = Math.max(red, green, blue), min = Math.min(red, green, blue);
		var delta = max - min;
		brightness = max / 255;
		saturation = (max != 0) ? delta / max : 0;
		if (saturation == 0){
			hue = 0;
		} else {
			var rr = (max - red) / delta;
			var gr = (max - green) / delta;
			var br = (max - blue) / delta;
			if (red == max) hue = br - gr;
			else if (green == max) hue = 2 + rr - br;
			else hue = 4 + gr - rr;
			hue /= 6;
			if (hue < 0) hue++;
		}
		return [Math.round(hue * 360), Math.round(saturation * 100), Math.round(brightness * 100)];
	},

	hsbToRgb: function(){
		var br = Math.round(this[2] / 100 * 255);
		if (this[1] == 0){
			return [br, br, br];
		} else {
			var hue = this[0] % 360;
			var f = hue % 60;
			var p = Math.round((this[2] * (100 - this[1])) / 10000 * 255);
			var q = Math.round((this[2] * (6000 - this[1] * f)) / 600000 * 255);
			var t = Math.round((this[2] * (6000 - this[1] * (60 - f))) / 600000 * 255);
			switch (Math.floor(hue / 60)){
				case 0: return [br, t, p];
				case 1: return [q, br, p];
				case 2: return [p, br, t];
				case 3: return [p, q, br];
				case 4: return [t, p, br];
				case 5: return [br, p, q];
			}
		}
		return false;
	}

});

String.implement({

	rgbToHsb: function(){
		var rgb = this.match(/\d{1,3}/g);
		return (rgb) ? hsb.rgbToHsb() : null;
	},
	
	hsbToRgb: function(){
		var hsb = this.match(/\d{1,3}/g);
		return (hsb) ? hsb.hsbToRgb() : null;
	}

});


/*
Script: Swiff.js
	Wrapper for embedding SWF movies. Supports (and fixes) External Interface Communication.

License:
	MIT-style license.

Credits:
	Flash detection & Internet Explorer + Flash Player 9 fix inspired by SWFObject.
*/

var Swiff = function(path, options){
	if (!Swiff.fixed) Swiff.fix();
	var instance = 'Swiff_' + Native.UID++;
	options = $merge({
		id: instance,
		height: 1,
		width: 1,
		container: null,
		properties: {},
		params: {
			quality: 'high',
			allowScriptAccess: 'always',
			wMode: 'transparent',
			swLiveConnect: true
		},
		events: {},
		vars: {}
	}, options);
	var params = options.params, vars = options.vars, id = options.id;
	var properties = $extend({height: options.height, width: options.width}, options.properties);
	Swiff.Events[instance] = {};
	for (var event in options.events){
		Swiff.Events[instance][event] = function(){
			options.events[event].call($(options.id));
		};
		vars[event] = 'Swiff.Events.' + instance + '.' + event;
	}
	params.flashVars = Hash.toQueryString(vars);
	if (Browser.Engine.trident){
		properties.classid = 'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000';
		params.movie = path;
	} else {
		properties.type = 'application/x-shockwave-flash';
		properties.data = path;
	}
	var build = '<object id="' + options.id + '"';
	for (var property in properties) build += ' ' + property + '="' + properties[property] + '"';
	build += '>';
	for (var param in params) build += '<param name="' + param + '" value="' + params[param] + '" />';
	build += '</object>';
	return ($(options.container) || new Element('div')).set('html', build).firstChild;
};

Swiff.extend({

	Events: {},

	remote: function(obj, fn){
		var rs = obj.CallFunction('<invoke name="' + fn + '" returntype="javascript">' + __flash__argumentsToXML(arguments, 2) + '</invoke>');
		return eval(rs);
	},

	getVersion: function(){
		if (!$defined(Swiff.pluginVersion)){
			var version;
			if (navigator.plugins && navigator.mimeTypes.length){
				version = navigator.plugins["Shockwave Flash"];
				if (version && version.description) version = version.description;
			} else if (Browser.Engine.trident){
				version = $try(function(){
					return new ActiveXObject("ShockwaveFlash.ShockwaveFlash").GetVariable("$version");
				});
			}
			Swiff.pluginVersion = (typeof version == 'string') ? parseInt(version.match(/\d+/)[0]) : 0;
		}
		return Swiff.pluginVersion;
	},

	fix: function(){
		Swiff.fixed = true;
		window.addEvent('beforeunload', function(){
			__flash_unloadHandler = __flash_savedUnloadHandler = $empty;
		});
		if (!Browser.Engine.trident) return;
		window.addEvent('unload', function(){
			Array.each(document.getElementsByTagName('object'), function(obj){
				obj.style.display = 'none';
				for (var p in obj){
					if (typeof obj[p] == 'function') obj[p] = $empty;
				}
			});
		});
	}

});


/*
Script: Group.js
	Class for monitoring collections of events

License:
	MIT-style license.
*/

var Group = new Class({

	initialize: function(){
		this.instances = Array.flatten(arguments);
		this.events = {};
		this.checker = {};
	},

	addEvent: function(type, fn){
		this.checker[type] = this.checker[type] || {};
		this.events[type] = this.events[type] || [];
		if (this.events[type].contains(fn)) return false;
		else this.events[type].push(fn);
		this.instances.each(function(instance, i){
			instance.addEvent(type, this.check.bind(this, [type, instance, i]));
		}, this);
		return this;
	},

	check: function(type, instance, i){
		this.checker[type][i] = true;
		var every = this.instances.every(function(current, j){
			return this.checker[type][j] || false;
		}, this);
		if (!every) return;
		this.checker[type] = {};
		this.events[type].each(function(event){
			event.call(this, this.instances, instance);
		}, this);
	}

});


/*
Script: Fx.js
	Contains the basic animation logic to be extended by all other Fx Classes.

License:
	MIT-style license.
*/

var Fx = new Class({

	Implements: [Chain, Events, Options],

	options: {
		/*
		onStart: $empty,
		onCancel: $empty,
		onComplete: $empty,
		*/
		fps: 50,
		unit: false,
		duration: 500,
		link: 'ignore',
		transition: function(p){
			return -(Math.cos(Math.PI * p) - 1) / 2;
		}
	},

	initialize: function(options){
		this.pass = this.pass || this;
		this.setOptions(options);
		this.options.duration = Fx.Durations[this.options.duration] || this.options.duration.toInt();
		var wait = this.options.wait;
		if (wait === false) this.options.link = 'cancel';
	},

	step: function(){
		var time = $time();
		if (time < this.time + this.options.duration){
			var delta = this.options.transition((time - this.time) / this.options.duration);
			this.set(this.compute(this.from, this.to, delta));
		} else {
			this.set(this.compute(this.from, this.to, 1));
			this.complete();
		}
	},

	set: function(now){
		return now;
	},

	compute: function(from, to, delta){
		return Fx.compute(from, to, delta);
	},

	check: function(){
		if (!this.timer) return true;
		switch (this.options.link){
			case 'cancel': this.cancel(); return true;
			case 'chain': this.chain(this.start.bind(this, arguments)); return false;
		}
		return false;
	},

	start: function(from, to){
		if (!this.check(from, to)) return this;
		this.from = from;
		this.to = to;
		this.time = 0;
		this.startTimer();
		this.onStart();
		return this;
	},

	complete: function(){
		return (!this.stopTimer()) ? this : this.onComplete();
	},

	cancel: function(){
		return (!this.stopTimer()) ? this : this.onCancel();
	},

	onStart: function(){
		return this.fireEvent('onStart', this.pass);
	},

	onComplete: function(){
		return this.fireEvent('onComplete', this.pass).callChain();
	},

	onCancel: function(){
		return this.fireEvent('onCancel', this.pass).clearChain();
	},

	pause: function(){
		this.stopTimer();
		return this;
	},

	resume: function(){
		this.startTimer();
		return this;
	},

	stopTimer: function(){
		if (!this.timer) return false;
		this.time = $time() - this.time;
		this.timer = $clear(this.timer);
		return true;
	},

	startTimer: function(){
		if (this.timer) return false;
		this.time = $time() - this.time;
		this.timer = this.step.periodical(Math.round(1000 / this.options.fps), this);
		return true;
	}

});

Fx.compute = function(from, to, delta){
	return (to - from) * delta + from;
};

Fx.Durations = {'short': 250, 'normal': 500, 'long': 1000};


/*
Script: Fx.CSS.js
	Contains the CSS animation logic. Used by Fx.Tween, Fx.Morph, Fx.Elements.

License:
	MIT-style license.
*/

Fx.CSS = new Class({

	Extends: Fx,

	//prepares the base from/to object

	prepare: function(element, property, values){
		values = $splat(values);
		var values1 = values[1];
		if (!$chk(values1)){
			values[1] = values[0];
			values[0] = element.getStyle(property);
		}
		var parsed = values.map(this.parse);
		return {from: parsed[0], to: parsed[1]};
	},

	//parses a value into an array

	parse: function(value){
		value = $lambda(value)();
		value = (typeof value == 'string') ? value.split(' ') : $splat(value);
		return value.map(function(val){
			val = String(val);
			var found = false;
			Fx.CSS.Parsers.each(function(parser, key){
				if (found) return;
				var parsed = parser.parse(val);
				if ($chk(parsed)) found = {'value': parsed, 'parser': parser};
			});
			found = found || {value: val, parser: Fx.CSS.Parsers.String};
			return found;
		});
	},

	//computes by a from and to prepared objects, using their parsers.

	compute: function(from, to, delta){
		var computed = [];
		(Math.min(from.length, to.length)).times(function(i){
			computed.push({'value': from[i].parser.compute(from[i].value, to[i].value, delta), 'parser': from[i].parser});
		});
		computed.$family = {name: 'fx:css:value'};
		return computed;
	},

	//serves the value as settable

	serve: function(value, unit){
		if ($type(value) != 'fx:css:value') value = this.parse(value);
		var returned = [];
		value.each(function(bit){
			returned = returned.concat(bit.parser.serve(bit.value, unit));
		});
		return returned;
	},

	//renders the change to an element

	render: function(element, property, value){
		element.setStyle(property, this.serve(value, this.options.unit));
	},

	//searches inside the page css to find the values for a selector

	search: function(selector){
		var to = {};
		Array.each(document.styleSheets, function(sheet, j){
			var rules = sheet.rules || sheet.cssRules;
			Array.each(rules, function(rule, i){
				if (!rule.style || !rule.selectorText || !rule.selectorText.test('^' + selector + '$')) return;
				Element.Styles.each(function(value, style){
					if (!rule.style[style] || Element.ShortStyles[style]) return;
					value = rule.style[style];
					to[style] = (value.test(/^rgb/)) ? value.rgbToHex() : value;
				});
			});
		});
		return to;
	}

});

Fx.CSS.Parsers = new Hash({

	Color: {

		parse: function(value){
			if (value.match(/^#[0-9a-f]{3,6}$/i)) return value.hexToRgb(true);
			return ((value = value.match(/(\d+),\s*(\d+),\s*(\d+)/))) ? [value[1], value[2], value[3]] : false;
		},

		compute: function(from, to, delta){
			return from.map(function(value, i){
				return Math.round(Fx.compute(from[i], to[i], delta));
			});
		},

		serve: function(value){
			return value.map(Number);
		}

	},

	Number: {

		parse: function(value){
			return parseFloat(value);
		},

		compute: function(from, to, delta){
			return Fx.compute(from, to, delta);
		},

		serve: function(value, unit){
			return (unit) ? value + unit : value;
		}

	},

	String: {

		parse: $lambda(false),

		compute: $arguments(1),

		serve: $arguments(0)

	}

});


/*
Script: Fx.Tween.js
	Formerly Fx.Style, effect to transition any CSS property for an element.

License:
	MIT-style license.
*/

Fx.Tween = new Class({

	Extends: Fx.CSS,

	initialize: function(element, property, options){
		this.element = this.pass = $(element);
		this.property = property;
		arguments.callee.parent(options);
	},

	set: function(now){
		this.render(this.element, this.property, now);
		return this;
	},

	start: function(){
		var fromto = Array.slice(arguments);
		if (!this.check(fromto)) return this;
		var parsed = this.prepare(this.element, this.property, fromto);
		return arguments.callee.parent(parsed.from, parsed.to);
	}

});

Element.Properties.tween = {

	set: function(options){
		var tween = this.retrieve('tween');
		if (tween) tween.cancel();
		return this.store('tween', new Fx.Tween(this, null, $extend({link: 'cancel'}, options)));
	},

	get: function(property, options){
		if (options || !this.retrieve('tween')) this.set('tween', options);
		var tween = this.retrieve('tween');
		tween.property = property;
		return tween;
	}

};

Element.implement({

	tween: function(property){
		var tween = this.get('tween', property);
		tween.start.apply(tween, Array.slice(arguments, 1));
		return this;
	},

	fade: function(how){
		var fade = this.get('tween', 'opacity');
		how = $pick(how, 'toggle');
		switch (how){
			case 'in': fade.start(1); break;
			case 'out': fade.start(0); break;
			case 'show': fade.set(1); break;
			case 'hide': fade.set(0); break;
			case 'toggle': fade.start((function(){
				return (this.getStyle('visibility') == 'hidden') ? 1 : 0;
			}).bind(this)); break;
			default: fade.start.apply(fade, arguments);
		}
		return this;
	},

	highlight: function(start, end){
		if (!end){
			var style = this.getStyle('background-color');
			end = (style == 'transparent') ? '#ffffff' : style;
		}
		this.get('tween', 'background-color').start(start || '#ffff88', end);
		return this;
	},

	effect: function(property, options){
		return new Fx.Tween(this, property, options);
	}

});


/*
Script: Fx.Morph.js
	Formerly Fx.Styles, effect to transition any number of CSS properties for an element using an object of rules, or CSS based selector rules.

License:
	MIT-style license.
*/

Fx.Morph = new Class({

	Extends: Fx.CSS,

	initialize: function(element, options){
		this.element = this.pass = $(element);
		arguments.callee.parent(options);
	},

	set: function(now){
		if (typeof now == 'string') now = this.search(now);
		for (var p in now) this.render(this.element, p, now[p]);
		return this;
	},

	compute: function(from, to, delta){
		var now = {};
		for (var p in from) now[p] = arguments.callee.parent(from[p], to[p], delta);
		return now;
	},

	start: function(properties){
		if (!this.check(properties)) return this;
		if (typeof properties == 'string') properties = this.search(properties);
		var from = {}, to = {};
		for (var p in properties){
			var parsed = this.prepare(this.element, p, properties[p]);
			from[p] = parsed.from;
			to[p] = parsed.to;
		}
		return arguments.callee.parent(from, to);
	}

});

Element.Properties.morph = {

	set: function(options){
		var morph = this.retrieve('morph');
		if (morph) morph.cancel();
		return this.store('morph', new Fx.Morph(this, $extend({link: 'cancel'}, options)));
	},

	get: function(options){
		if (options || !this.retrieve('morph')) this.set('morph', options);
		return this.retrieve('morph');
	}

};

Element.implement({

	morph: function(props){
		this.get('morph').start(props);
		return this;
	},
	
	effects: function(options){
		return new Fx.Morph(this, options);
	}

});


/*
Script: Fx.Slide.js
	Effect to slide an element in and out of view.

License:
	MIT-style license.

Note:
	Fx.Slide requires an XHTML doctype.
*/

Fx.Slide = new Class({

	Extends: Fx,

	options: {
		mode: 'vertical'
	},

	initialize: function(element, options){
		this.addEvent('onComplete', function(){
			this.open = (this.wrapper['offset' + this.layout.capitalize()] != 0);
			if (this.open){
				this.wrapper.setStyle(this.layout, 'auto');
				if (Browser.Engine.webkit419) this.element.dispose().inject(this.wrapper);
			}
		}, true);
		this.element = this.pass = $(element);
		arguments.callee.parent(options);
		var wrapper = this.element.retrieve('wrapper');
		this.wrapper = wrapper || new Element('div', {
			styles: $extend(this.element.getStyles('margin', 'position'), {'overflow': 'hidden'})
		}).wraps(this.element);
		this.element.store('wrapper', this.wrapper).setStyle('margin', 0);
		this.now = [];
		this.open = true;
	},

	vertical: function(){
		this.margin = 'margin-top';
		this.layout = 'height';
		this.offset = this.element.offsetHeight;
	},

	horizontal: function(){
		this.margin = 'margin-left';
		this.layout = 'width';
		this.offset = this.element.offsetWidth;
	},

	set: function(now){
		this.element.setStyle(this.margin, now[0]);
		this.wrapper.setStyle(this.layout, now[1]);
		return this;
	},

	compute: function(from, to, delta){
		var now = [];
		(2).times(function(i){
			now[i] = Fx.compute(from[i], to[i], delta);
		});
		return now;
	},

	start: function(how, mode){
		if (!this.check(how, mode)) return this;
		this[mode || this.options.mode]();
		var margin = this.element.getStyle(this.margin).toInt();
		var layout = this.wrapper.getStyle(this.layout).toInt();
		var caseIn = [[margin, layout], [0, this.offset]];
		var caseOut = [[margin, layout], [-this.offset, 0]];
		var start;
		switch(how){
			case 'in': start = caseIn; break;
			case 'out': start = caseOut; break;
			case 'toggle': start = (this.wrapper['offset' + this.layout.capitalize()] == 0) ? caseIn : caseOut;
		}
		return arguments.callee.parent(start[0], start[1]);
	},

	slideIn: function(mode){
		return this.start('in', mode);
	},

	slideOut: function(mode){
		return this.start('out', mode);
	},

	hide: function(mode){
		this[mode || this.options.mode]();
		this.open = false;
		return this.set([-this.offset, 0]);
	},

	show: function(mode){
		this[mode || this.options.mode]();
		this.open = true;
		return this.set([0, this.offset]);
	},

	toggle: function(mode){
		return this.start('toggle', mode);
	}

});

Element.Properties.slide = {

	set: function(options){
		var slide = this.retrieve('slide');
		if (slide) slide.cancel();
		return this.store('slide', new Fx.Slide(this, $extend({link: 'cancel'}, options)));
	},

	get: function(options){
		if (options || !this.retrieve('slide')) this.set('slide', options);
		return this.retrieve('slide');
	}

};

Element.implement({

	slide: function(how){
		how = how || 'toggle';
		var slide = this.get('slide');
		switch(how){
			case 'hide': slide.hide(); break;
			case 'show': slide.show(); break;
			default: slide.start(how);
		}
		return this;
	}

});


/*
Script: Fx.Scroll.js
	Effect to smoothly scroll any element, including the window.

License:
	MIT-style license.

Note:
	Fx.Scroll requires an XHTML doctype.
*/

Fx.Scroll = new Class({

	Extends: Fx,

	options: {
		offset: {'x': 0, 'y': 0},
		wheelStops: true
	},

	initialize: function(element, options){
		this.element = this.pass = $(element);
		arguments.callee.parent(options);
		var cancel = this.cancel.bind(this, false);

		if ($type(this.element) != 'element') this.element = $(this.element.getDocument().body);

		var stopper = this.element;

		if (this.options.wheelStops){
			this.addEvent('onStart', function(){
				stopper.addEvent('mousewheel', cancel);
			}, true);
			this.addEvent('onComplete', function(){
				stopper.removeEvent('mousewheel', cancel);
			}, true);
		}
	},

	set: function(){
		var now = Array.flatten(arguments);
		this.element.scrollTo(now[0], now[1]);
	},

	compute: function(from, to, delta){
		var now = [];
		(2).times(function(i){
			now.push(Fx.compute(from[i], to[i], delta));
		});
		return now;
	},

	start: function(x, y){
		if (!this.check(x, y)) return this;
		var offsetSize = this.element.getSize(), scrollSize = this.element.getScrollSize(), scroll = this.element.getScroll(), values = {'x': x, 'y': y};
		for (var z in values){
			var max = scrollSize[z] - offsetSize[z];
			if ($chk(values[z])) values[z] = ($type(values[z]) == 'number') ? values[z].limit(0, max) : max;
			else values[z] = scroll[z];
			values[z] += this.options.offset[z];
		}
		return arguments.callee.parent([scroll.x, scroll.y], [values.x, values.y]);
	},

	toTop: function(){
		return this.start(false, 0);
	},

	toLeft: function(){
		return this.start(0, false);
	},

	toRight: function(){
		return this.start('right', false);
	},

	toBottom: function(){
		return this.start(false, 'bottom');
	},

	toElement: function(el){
		var position = $(el).getPosition(this.element);
		return this.start(position.x, position.y);
	}

});


/*
Script: Fx.Transitions.js
	Contains a set of advanced transitions to be used with any of the Fx Classes.

License:
	MIT-style license.

Credits:
	Easing Equations by Robert Penner, <http://www.robertpenner.com/easing/>, modified and optimized to be used with MooTools.
*/

(function(){

	var old = Fx.prototype.initialize;

	Fx.prototype.initialize = function(options){
		old.call(this, options);
		var trans = this.options.transition;
		if (typeof trans == 'string' && (trans = trans.split(':'))){
			var base = Fx.Transitions;
			base = base[trans[0]] || base[trans[0].capitalize()];
			if (trans[1]) base = base['ease' + trans[1].capitalize() + (trans[2] ? trans[2].capitalize() : '')];
			this.options.transition = base;
		}
	};

})();

Fx.Transition = function(transition, params){
	params = $splat(params);
	return $extend(transition, {
		easeIn: function(pos){
			return transition(pos, params);
		},
		easeOut: function(pos){
			return 1 - transition(1 - pos, params);
		},
		easeInOut: function(pos){
			return (pos <= 0.5) ? transition(2 * pos, params) / 2 : (2 - transition(2 * (1 - pos), params)) / 2;
		}
	});
};

Fx.Transitions = new Hash({

	linear: $arguments(0)

});

Fx.Transitions.extend = function(transitions){
	for (var transition in transitions) Fx.Transitions[transition] = new Fx.Transition(transitions[transition]);
};

Fx.Transitions.extend({

	Pow: function(p, x){
		return Math.pow(p, x[0] || 6);
	},

	Expo: function(p){
		return Math.pow(2, 8 * (p - 1));
	},

	Circ: function(p){
		return 1 - Math.sin(Math.acos(p));
	},

	Sine: function(p){
		return 1 - Math.sin((1 - p) * Math.PI / 2);
	},

	Back: function(p, x){
		x = x[0] || 1.618;
		return Math.pow(p, 2) * ((x + 1) * p - x);
	},

	Bounce: function(p){
		var value;
		for (var a = 0, b = 1; 1; a += b, b /= 2){
			if (p >= (7 - 4 * a) / 11){
				value = - Math.pow((11 - 6 * a - 11 * p) / 4, 2) + b * b;
				break;
			}
		}
		return value;
	},

	Elastic: function(p, x){
		return Math.pow(2, 10 * --p) * Math.cos(20 * p * Math.PI * (x[0] || 1) / 3);
	}

});

['Quad', 'Cubic', 'Quart', 'Quint'].each(function(transition, i){
	Fx.Transitions[transition] = new Fx.Transition(function(p){
		return Math.pow(p, [i + 2]);
	});
});


/*
Script: Request.js
	Powerful all purpose Request Class. Uses XMLHTTPRequest.

License:
	MIT-style license.
*/

var Request = new Class({

	Implements: [Chain, Events, Options],

	options: {/*
		onRequest: $empty,
		onSuccess: $empty,
		onFailure: $empty,
		onException: $empty,*/
		url: '',
		data: '',
		headers: {},
		async: true,
		method: 'post',
		link: 'ignore',
		isSuccess: null,
		emulation: true,
		urlEncoded: true,
		encoding: 'utf-8',
		evalScripts: false,
		evalResponse: false
	},

	getXHR: function(){
		return (window.XMLHttpRequest) ? new XMLHttpRequest() : ((window.ActiveXObject) ? new ActiveXObject('Microsoft.XMLHTTP') : false);
	},

	initialize: function(options){
		if (!(this.xhr = this.getXHR())) return;
		this.setOptions(options);
		this.options.isSuccess = this.options.isSuccess || this.isSuccess;
		this.headers = new Hash(this.options.headers).extend({
			'X-Requested-With': 'XMLHttpRequest',
			'Accept': 'text/javascript, text/html, application/xml, text/xml, */*'
		});
	},

	onStateChange: function(){
		if (this.xhr.readyState != 4 || !this.running) return;
		this.running = false;
		this.status = 0;
		$try(function(){
			this.status = this.xhr.status;
		}, this);
		if (this.options.isSuccess.call(this, this.status)){
			this.response = {text: this.xhr.responseText, xml: this.xhr.responseXML};
			this.success(this.response.text, this.response.xml);
		} else {
			this.response = {text: null, xml: null};
			this.failure();
		}
		this.xhr.onreadystatechange = $empty;
	},

	isSuccess: function(){
		return ((this.status >= 200) && (this.status < 300));
	},

	processScripts: function(text){
		if (this.options.evalResponse || (/(ecma|java)script/).test(this.getHeader('Content-type'))) return $exec(text);
		return text.stripScripts(this.options.evalScripts);
	},

	success: function(text, xml){
		this.onSuccess(this.processScripts(text), xml);
	},
	
	onSuccess: function(){
		this.fireEvent('onComplete', arguments).fireEvent('onSuccess', arguments).callChain();
	},
	
	failure: function(){
		this.onFailure();
	},

	onFailure: function(){
		this.fireEvent('onComplete').fireEvent('onFailure', this.xhr);
	},

	setHeader: function(name, value){
		this.headers.set(name, value);
		return this;
	},

	getHeader: function(name){
		return $try(function(){
			return this.getResponseHeader(name);
		}, this.xhr) || null;
	},

	check: function(){
		if (!this.running) return true;
		switch (this.options.link){
			case 'cancel': this.cancel(); return true;
			case 'chain': this.chain(this.send.bind(this, arguments)); return false;
		}
		return false;
	},

	send: function(options){
		if (!this.check(options)) return this;
		this.running = true;

		var type = $type(options);
		if (type == 'string' || type == 'element') options = {data: options};

		var old = this.options;
		options = $extend({data: old.data, url: old.url, method: old.method}, options);
		var data = options.data, url = options.url, method = options.method;

		switch($type(data)){
			case 'element': data = $(data).toQueryString(); break;
			case 'object': case 'hash': data = Hash.toQueryString(data);
		}

		if (this.options.emulation && ['put', 'delete'].contains(method)){
			var _method = '_method=' + method;
			data = (data) ? _method + '&' + data : _method;
			method = 'post';
		}

		if (this.options.urlEncoded && method == 'post'){
			var encoding = (this.options.encoding) ? '; charset=' + this.options.encoding : '';
			this.headers.set('Content-type', 'application/x-www-form-urlencoded' + encoding);
		}

		if (data && method == 'get'){
			url = url + (url.contains('?') ? '&' : '?') + data;
			data = null;
		}

		this.xhr.open(method.toUpperCase(), url, this.options.async);

		this.xhr.onreadystatechange = this.onStateChange.bind(this);

		this.headers.each(function(value, key){
			try{
				this.xhr.setRequestHeader(key, value);
			} catch(e){
				this.fireEvent('onException', [e, key, value]);
			}
		}, this);

		this.fireEvent('onRequest');
		this.xhr.send(data);
		if (!this.options.async) this.onStateChange();
		return this;
	},

	cancel: function(){
		if (!this.running) return this;
		this.running = false;
		this.xhr.abort();
		this.xhr.onreadystatechange = $empty;
		this.xhr = this.getXHR();
		this.fireEvent('onCancel');
		return this;
	}

});

(function(){

var methods = {};
['get', 'post', 'GET', 'POST', 'PUT', 'DELETE'].each(function(method){
	methods[method] = function(){
		var params = Array.link(arguments, {url: String.type, data: $defined});
		return this.send($extend(params, {method: method.toLowerCase()}));
	};
});

Request.implement(methods);

})();

Element.Properties.send = {

	get: function(options){
		if (options || !this.retrieve('send')) this.set('send', options);
		return this.retrieve('send');
	},

	set: function(options){
		var send = this.retrieve('send');
		if (send) send.cancel();
		return this.store('send', new Request($extend({
			data: this, link: 'cancel', method: this.get('method') || 'post', url: this.get('action')
		}, options)));
	}

};

Element.implement({

	send: function(url){
		var sender = this.get('send');
		sender.send({data: this, url: url || sender.options.url});
		return this;
	}

});


/*
Script: Request.HTML.js
	Extends the basic Request Class with additional methods for interacting with HTML responses.

License:
	MIT-style license.
*/

Request.HTML = new Class({

	Extends: Request,

	options: {
		update: false,
		evalScripts: true,
		filter: false
	},

	processHTML: function(text){
		var match = text.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
		return (match) ? match[1] : text;
	},

	success: function(text){
		var opts = this.options, res = this.response;
		res.html = this.processHTML(text).stripScripts(function(script){
			res.javascript = script;
		});
		var node = new Element('div', {html: res.html});
		res.elements = node.getElements('*');
		res.tree = (opts.filter) ? res.elements.filterBy(opts.filter) : $A(node.childNodes).filter(function(el){
			return ($type(el) != 'whitespace');
		});
		if (opts.update) $(opts.update).empty().adopt(res.tree);
		if (opts.evalScripts) $exec(res.javascript);
		this.onSuccess(res.tree, res.elements, res.html, res.javascript);
	}

});

Element.Properties.load = {

	get: function(options){
		if (options || !this.retrieve('load')) this.set('load', options);
		return this.retrieve('load');
	},

	set: function(options){
		var load = this.retrieve('load');
		if (load) load.cancel();
		return this.store('load', new Request.HTML($extend({link: 'cancel', update: this, method: 'get'}, options)));
	}

};

Element.implement({
	
	load: function(){
		this.get('load').send(Array.link(arguments, {data: Object.type, url: String.type}));
		return this;
	}

});


/*
Script: Request.JSON.js
	Extends the basic Request Class with additional methods for sending and receiving JSON data.

License:
	MIT-style license.
*/

Request.JSON = new Class({

	Extends: Request,

	options: {
		secure: true
	},

	initialize: function(options){
		arguments.callee.parent(options);
		this.headers.extend({'Accept': 'application/json', 'X-Request': 'JSON'});
	},

	success: function(text){
		this.response.json = JSON.decode(text, this.options.secure);
		this.onSuccess(this.response.json, text);
	}

});

/*
Script: Drag.js
	The base Drag Class. Can be used to drag and resize Elements using mouse events.

License:
	MIT-style license.

Note:
	This Script requires an XHTML doctype.
*/

var Drag = new Class({

	Implements: [Events, Options],

	options: {/*
		onBeforeStart: $empty,
		onStart: $empty,
		onDrag: $empty,
		onCancel: $empty,
		onComplete: $empty,*/
		snap: 6,
		unit: 'px',
		grid: false,
		limit: false,
		handle: false,
		modifiers: {x: 'left', y: 'top'}
	},

	initialize: function(){
		var params = Array.link(arguments, {'options': Object.type, 'element': $defined});
		this.element = $(params.element);
		this.document = this.element.getDocument();
		this.setOptions(params.options || {});
		var htype = $type(this.options.handle);
		this.handles = (htype == 'array' || htype == 'collection') ? $$(this.options.handle) : $(this.options.handle) || this.element;
		this.mouse = {'now': {}, 'pos': {}};
		this.value = {'start': {}, 'now': {}};
		
		this.selection = (Browser.Engine.trident) ? 'selectstart' : 'mousedown';
		
		this.bound = {
			start: this.start.bind(this),
			check: this.check.bind(this),
			drag: this.drag.bind(this),
			stop: this.stop.bind(this),
			cancel: this.cancel.bind(this),
			eventStop: $lambda(false)
		};
		this.attach();
	},

	attach: function(){
		this.handles.addEvent('mousedown', this.bound.start);
		return this;
	},

	detach: function(){
		this.handles.removeEvent('mousedown', this.bound.start);
		return this;
	},

	start: function(event){
		this.fireEvent('onBeforeStart', this.element);
		this.mouse.start = event.page;
		var limit = this.options.limit;
		this.limit = {'x': [], 'y': []};
		for (var z in this.options.modifiers){
			if (!this.options.modifiers[z]) continue;
			this.value.now[z] = this.element.getStyle(this.options.modifiers[z]).toInt();
			this.mouse.pos[z] = event.page[z] - this.value.now[z];
			if (limit && limit[z]){
				for (var i = 2; i--; i){
					if ($chk(limit[z][i])) this.limit[z][i] = $lambda(limit[z][i])();
				}
			}
		}
		if ($type(this.options.grid) == 'number') this.options.grid = {'x': this.options.grid, 'y': this.options.grid};
		this.document.addEvents({mousemove: this.bound.check, mouseup: this.bound.cancel});
		this.document.addEvent(this.selection, this.bound.eventStop);
	},

	check: function(event){
		var distance = Math.round(Math.sqrt(Math.pow(event.page.x - this.mouse.start.x, 2) + Math.pow(event.page.y - this.mouse.start.y, 2)));
		if (distance > this.options.snap){
			this.cancel();
			this.document.addEvents({
				mousemove: this.bound.drag,
				mouseup: this.bound.stop
			});
			this.fireEvent('onStart', this.element).fireEvent('onSnap', this.element);
		}
	},

	drag: function(event){
		this.mouse.now = event.page;
		for (var z in this.options.modifiers){
			if (!this.options.modifiers[z]) continue;
			this.value.now[z] = this.mouse.now[z] - this.mouse.pos[z];
			if (this.options.limit && this.limit[z]){
				if ($chk(this.limit[z][1]) && (this.value.now[z] > this.limit[z][1])){
					this.value.now[z] = this.limit[z][1];
				} else if ($chk(this.limit[z][0]) && (this.value.now[z] < this.limit[z][0])){
					this.value.now[z] = this.limit[z][0];
				}
			}
			if (this.options.grid[z]) this.value.now[z] -= (this.value.now[z] % this.options.grid[z]);
			this.element.setStyle(this.options.modifiers[z], this.value.now[z] + this.options.unit);
		}
		this.fireEvent('onDrag', this.element);
	},

	cancel: function(event){
		this.document.removeEvent('mousemove', this.bound.check);
		this.document.removeEvent('mouseup', this.bound.cancel);
		if (event){
			this.document.removeEvent(this.selection, this.bound.eventStop);
			this.fireEvent('onCancel', this.element);
		}
	},

	stop: function(event){
		this.document.removeEvent(this.selection, this.bound.eventStop);
		this.document.removeEvent('mousemove', this.bound.drag);
		this.document.removeEvent('mouseup', this.bound.stop);
		if (event) this.fireEvent('onComplete', this.element);
	}

});

Element.implement({
	
	makeResizable: function(options){
		return new Drag(this, $merge({modifiers: {'x': 'width', 'y': 'height'}}, options));
	}

});

/*
Script: Drag.Move.js
	A Drag extension that provides support for the constraining of draggables to containers and droppables.

License:
	MIT-style license.

Note:
	Drag.Move requires an XHTML doctype.
*/

Drag.Move = new Class({

	Extends: Drag,

	options: {
		droppables: [],
		container: false
	},

	initialize: function(element, options){
		arguments.callee.parent(element, options);
		this.droppables = $$(this.options.droppables);
		this.container = $(this.options.container);
		var position = (this.element.positioned()) ? this.element.getStyle('position') : 'absolute';
		this.element.position(this.element.getRelativePosition()).setStyle('position', position);
	},

	start: function(event){
		if (this.overed){
			this.overed.fireEvent('leave', [this.element, this]);
			this.overed = null;
		}
		if (this.container){
			var el = this.element, cont = this.container, ccoo = cont.getCoordinates(el.getOffsetParent()), cps = {}, ems = {};

			['top', 'right', 'bottom', 'left'].each(function(pad){
				cps[pad] = cont.getStyle('padding-' + pad).toInt();
				ems[pad] = el.getStyle('margin-' + pad).toInt();
			}, this);

			var width = el.offsetWidth + ems.left + ems.right, height = el.offsetHeight + ems.top + ems.bottom;
			var x = [ccoo.left + cps.left, ccoo.right - cps.right - width];
			var y = [ccoo.top + cps.top, ccoo.bottom - cps.bottom - height];

			this.options.limit = {x: x, y: y};
		}
		arguments.callee.parent(event);
	},

	checkAgainst: function(el){
		el = el.getCoordinates();
		var now = this.mouse.now;
		return (now.x > el.left && now.x < el.right && now.y < el.bottom && now.y > el.top);
	},

	checkDroppables: function(){
		var overed = this.droppables.filter(this.checkAgainst, this).getLast();
		if (this.overed != overed){
			if (this.overed) this.overed.fireEvent('leave', [this.element, this]);
			this.overed = overed ? overed.fireEvent('over', [this.element, this]) : null;
		}
	},

	drag: function(event){
		arguments.callee.parent(event);
		if (this.droppables.length) this.checkDroppables();
	},

	stop: function(event){
		this.checkDroppables();
		if (this.overed) this.overed.fireEvent('drop', [this.element, this]);
		else this.element.fireEvent('emptydrop', this);
		return arguments.callee.parent(event);
	}

});

Element.implement({

	makeDraggable: function(options){
		return new Drag.Move(this, options);
	}

});


/*
Script: Selectors.Children.js
	Adds the :children selector for selecting ranges of children of an element.

License:
	MIT-style license.
*/

Selectors.Pseudo.children = {

	parser: function(argument){
		argument = (argument) ? argument.match(/^([-+]?\d*)?([\-+:])?([-+]?\d*)?$/) : [null, 0, false, 0];
		if (!argument) return false;
		argument[1] = parseInt(argument[1]) || 0;
		var int1 = parseInt(argument[3]);
		argument[3] = ($chk(int1)) ? int1 : 0;
		switch (argument[2]){
			case '-': case '+': case ':': return {'a': argument[1], 'b': argument[3], 'special': argument[2]};
			default: return {'a': argument[1], 'b': 0, 'special': 'index'};
		}
	},

	xpath: function(argument){
		var include = '';
		var len = 'count(../child::*)';
		var a = argument.a + ' + ' + ((argument.a < 0) ? len : 0);
		var b = argument.b + ' + ' + ((argument.b < 0) ? len : 0);
		var pos = 'position()';
		switch (argument.special){
			case '-':
				b = '((' + a + ' - ' + b + ') mod (' + len + '))';
				a += ' + 1';
				b += ' + 1';
				include = '(' + b + ' < 1 and (' + pos + ' <= ' + a + ' or ' + pos + ' >= (' + b + ' + ' + len + ')' + ')) or (' + pos + ' <= ' + a + ' and ' + pos + ' >= ' + b + ')';
			break;
			case '+': b = '((' + a + ' + ' + b + ') mod ( ' + len + '))';
			case ':':
				a += ' + 1';
				b += ' + 1';
				include = '(' + b + ' < ' + a + ' and (' + pos + ' >= ' + a + ' or ' + pos + ' <= ' + b + ')) or (' + pos + ' >= ' + a + ' and ' + pos + ' <= ' + b + ')';
			break;
			default: include = (a + ' + 1');
		}
		return '[' + include + ']';
	},

	filter: function(argument, Local){
		Local.i = Local.i || 0;
		Local.all = Local.all || this.parentNode.childNodes;
		Local.len = Local.len || Local.all.length;
		var i = Local.i;
		var len = Local.len;
		var all = Local.all;
		var include = false;
		var a = argument.a + ((argument.a < 0) ? len : 0);
		var b = argument.b + ((argument.b < 0) ? len : 0);
		switch (argument.special){
			case '-':
				b = (a - b) % len;
				include = (b < 0) ? (i <= a || i >= (b + len)) : (i <= a && i >= b);
			break;
			case '+': b = (b + a) % len;
			case ':': include = (b < a) ? (i >= a || i <= b) : (i >= a && i <= b); break;
			default: include = (all[a] == this);
		}
		Local.i++;
		return include;
	}
};


/*
Script: Hash.Cookie.js
	Class for creating, reading, and deleting Cookies in JSON format.

License:
	MIT-style license.
*/

Hash.Cookie = new Class({

	Extends: Cookie,

	options: {
		autoSave: true
	},

	initialize: function(name, options){
		this.parent(name, options);
		this.load();
	},

	save: function(){
		var value = JSON.encode(this.hash);
		if (value.length > 4096) return false; //cookie would be truncated!
		if (value.length == 2) this.erase();
		else this.write(value);
		return true;
	},

	load: function(){
		this.hash = new Hash(JSON.decode(this.read(), true));
		return this;
	}

});

(function(){

var methods = {};
Hash.each(Hash.prototype, function(method, name){
	methods[name] = function(){
		var value = method.apply(this.hash, arguments);
		if (this.options.autoSave) this.save();
		return value;
	};
});
Hash.Cookie.implement(methods);

})();

/*
Script: Sortables.js
	Class for creating a drag and drop sorting interface for lists of items.

License:
	MIT-style license.

Note:
	Sortables requires an XHTML doctype.
*/

var Sortables = new Class({

	Implements: [Events, Options],

	options: {/*
		onSort: $empty,
		onStart: $empty,
		onComplete: $empty,*/
		snap: 4,
		handle: false,
		revert: false,
		constrain: false,
		cloneOpacity: 0.7,
		elementOpacity: 0.3
	},

	initialize: function(lists, options){
		this.setOptions(options);
		this.elements = [];
		this.lists = [];
		this.idle = true;

		this.addLists($$($(lists) || lists));
		if (this.options.revert) this.effect = new Fx.Morph(null, $merge({duration: 250, link: 'cancel'}, this.options.revert));
	},

	attach: function(){
		this.addLists(this.lists);
		return this;
	},

	detach: function(){
		this.lists = this.removeLists(this.lists);
		return this;
	},

	addItems: function(){
		Array.flatten(arguments).each(function(element){
			this.elements.push(element);
			var start = element.retrieve('sortables:start', this.start.bindWithEvent(this, element));
			var insert = element.retrieve('sortables:insert', this.insert.bind(this, element));
			(this.options.handle ? element.getElement(this.options.handle) || element : element).addEvent('mousedown', start);
			element.addEvent('over', insert);
		}, this);
		return this;
	},

	addLists: function(){
		Array.flatten(arguments).each(function(list){
			this.lists.push(list);
			this.addItems(list.getChildren());
			list.addEvent('over', list.retrieve('sortables:insert', this.insert.bind(this, [list, 'inside'])));
		}, this);
		return this;
	},

	removeItems: function(){
		var elements = [];
		Array.flatten(arguments).each(function(element){
			elements.push(element);
			this.elements.remove(element);
			var start = element.retrieve('sortables:start');
			var insert = element.retrieve('sortables:insert');
			(this.options.handle ? element.getElement(this.options.handle) || element : element).removeEvent('mousedown', start);
			element.removeEvent('over', insert);
		}, this);
		return elements;
	},

	removeLists: function(){
		var lists = [];
		Array.flatten(arguments).each(function(list){
			lists.push(list);
			this.lists.remove(list);
			this.removeItems(list.getChildren());
			list.removeEvent('over', list.retrieve('sortables:insert'));
		}, this);
		return lists;
	},

	getClone: function(element){
		return element.clone(true).setStyles({
			'margin': '0px',
			'position': 'absolute',
			'visibility': 'hidden'
		}).inject(this.list).position(element.getRelativePosition());
	},

	getDroppables: function(){
		var droppables = this.list.getChildren();
		if (!this.options.constrain) droppables = this.lists.concat(droppables).remove(this.list);
		return droppables.remove(this.clone).remove(this.element);
	},

	insert: function(element, where){
		if (where) {
			this.list = element;
			this.drag.droppables = this.getDroppables();
		}
		where = where || (this.element.getAllPrevious().contains(element) ? 'before' : 'after');
		this.element.inject(element, where);
		this.fireEvent('onSort', [this.element, this.clone]);
	},

	start: function(event, element){
		if (!this.idle) return;
		this.idle = false;

		this.element = element;
		this.opacity = element.get('opacity');
		this.list = element.getParent();
		this.clone = this.getClone(element);

		this.drag = this.clone.makeDraggable({
			snap: this.options.snap,
			container: this.options.constrain && this.clone.getParent(),
			droppables: this.getDroppables(),
			onStart: function(){
				event.stop();
				this.clone.set('opacity', this.options.cloneOpacity);
				this.element.set('opacity', this.options.elementOpacity);
				this.fireEvent('onStart', [this.element, this.clone]);
			}.bind(this),
			onCancel: this.reset.bind(this),
			onComplete: this.end.bind(this)
		});

		this.drag.start(event);
	},

	end: function(){
		this.element.set('opacity', this.opacity);
		this.drag.detach();
		if (this.effect){
			var dim = this.element.getStyles('width', 'height');
			var pos = this.clone.computePosition(this.element.getPosition(this.clone.offsetParent), this.clone.getParent().positioned());
			this.effect.element = this.clone;
			this.effect.start({
				'top': pos.top,
				'left': pos.left,
				'width': dim.width,
				'height': dim.height,
				'opacity': 0.25
			}).chain(this.reset.bind(this));
		} else {
			this.reset();
		}
	},

	reset: function(){
		this.idle = true;
		this.clone.destroy();
		this.fireEvent('onComplete', this.element);
	},

	serialize: function(index, modifier){
		var serial = this.lists.map(function(list){
			return list.getChildren().map(modifier || function(element, index){
				return element.get('id');
			}, this);
		}, this);

		if (this.lists.length == 1) index = 0;
		return $chk(index) && index >= 0 && index < this.lists.length ? serial[index] : serial;
	}

});

/*
Script: Tips.js
	Class for creating nice tooltips that follow the mouse cursor when hovering over an element.

License:
	MIT-style license.

Note:
	Tips requires an XHTML doctype.
*/

var Tips = new Class({

	Implements: [Events, Options],

	options: {
		onShow: function(tip){
			tip.setStyle('visibility', 'visible');
		},
		onHide: function(tip){
			tip.setStyle('visibility', 'hidden');
		},
		maxTitleChars: 30,
		showDelay: 100,
		hideDelay: 100,
		className: 'tool',
		offsets: {'x': 16, 'y': 16},
		fixed: false
	},

	initialize: function(elements, options){
		this.setOptions(options);
		elements = $$(elements);
		this.document = (elements.length) ? elements[0].ownerDocument : document;
		this.toolTip = new Element('div', {
			'class': this.options.className + '-tip',
			'styles': {
				'position': 'absolute',
				'top': '0',
				'left': '0',
				'visibility': 'hidden'
			}
		}, this.document).inject(this.document.body);
		this.wrapper = new Element('div').inject(this.toolTip);
		elements.each(this.build, this);
	},

	build: function(el){
		el.$attributes.myTitle = (el.href && el.get('tag') == 'a') ? el.href.replace('http://', '') : (el.rel || false);
		if (el.title){
			var dual = el.title.split('::');
			if (dual.length > 1){
				el.$attributes.myTitle = dual[0].trim();
				el.$attributes.myText = dual[1].trim();
			} else {
				el.$attributes.myText = el.title;
			}
			el.removeProperty('title');
		} else {
			el.$attributes.myText = false;
		}
		if (el.$attributes.myTitle && el.$attributes.myTitle.length > this.options.maxTitleChars)
			el.$attributes.myTitle = el.$attributes.myTitle.substr(0, this.options.maxTitleChars - 1) + "&hellip;";
		el.addEvent('mouseenter', function(event){
			this.start(el);
			if (!this.options.fixed) this.locate(event);
			else this.position(el);
		}.bind(this));
		if (!this.options.fixed) el.addEvent('mousemove', this.locate.bind(this));
		var end = this.end.bind(this);
		el.addEvent('mouseleave', end);
	},

	start: function(el){
		this.wrapper.empty();
		if (el.$attributes.myTitle){
			this.title = new Element('span').inject(
				new Element('div', {'class': this.options.className + '-title'}
			).inject(this.wrapper)).set('html', el.$attributes.myTitle);
		}
		if (el.$attributes.myText){
			this.text = new Element('span').inject(
				new Element('div', {'class': this.options.className + '-text'}
			).inject(this.wrapper)).set('html', el.$attributes.myText);
		}
		$clear(this.timer);
		this.timer = this.show.delay(this.options.showDelay, this);
	},

	end: function(event){
		$clear(this.timer);
		this.timer = this.hide.delay(this.options.hideDelay, this);
	},

	position: function(element){
		var pos = element.getPosition();
		this.toolTip.setStyles({
			'left': pos.x + this.options.offsets.x,
			'top': pos.y + this.options.offsets.y
		});
	},

	locate: function(event){
		var doc = this.document.getSize();
		var scroll = this.document.getScroll();
		var tip = {'x': this.toolTip.offsetWidth, 'y': this.toolTip.offsetHeight};
		var prop = {'x': 'left', 'y': 'top'};
		for (var z in prop){
			var pos = event.page[z] + this.options.offsets[z];
			if ((pos + tip[z] - scroll[z]) > doc[z]) pos = event.page[z] - this.options.offsets[z] - tip[z];
			this.toolTip.setStyle(prop[z], pos);
		}
	},

	show: function(){
		if (this.options.timeout) this.timer = this.hide.delay(this.options.timeout, this);
		this.fireEvent('onShow', [this.toolTip]);
	},

	hide: function(){
		this.fireEvent('onHide', [this.toolTip]);
	}

});


/*
Script: SmoothScroll.js
	Class for creating a smooth scrolling effect to all internal links on the page.

License:
	MIT-style license.

Note:
	SmoothScroll requires an XHTML doctype.
*/

var SmoothScroll = new Class({

	Extends: Fx.Scroll,

	initialize: function(options, element){
		element = $(element);
		var doc = element.getDocument(), win = element.getWindow();
		arguments.callee.parent(doc, options);
		this.links = (this.options.links) ? $$(this.options.links) : $$(doc.links);
		var location = win.location.href.match(/^[^#]*/)[0] + '#';
		this.links.each(function(link){
			if (link.href.indexOf(location) != 0) return;
			var anchor = link.href.substr(location.length);
			if (anchor && $(anchor)) this.useLink(link, anchor);
		}, this);
		if (!Browser.Engine.webkit419) this.addEvent('onComplete', function(){
			win.location.hash = this.anchor;
		}, true);
	},

	useLink: function(link, anchor){
		link.addEvent('click', function(event){
			this.anchor = anchor;
			this.toElement(anchor);
			event.stop();
		}.bind(this));
	}

});


/*
Script: Slider.js
	Class for creating horizontal and vertical slider controls.

License:
	MIT-style license.

Note:
	Slider requires an XHTML doctype.
*/

var Slider = new Class({

	Implements: [Events, Options],

	options: {/*
		onChange: $empty,
		onComplete: $empty,*/
		onTick: function(position){
			if(this.options.snap) position = this.toPosition(this.step);
			this.knob.setStyle(this.property, position);
		},
		snap: false,
		offset: 0,
		range: false,
		wheel: false,
		steps: 100,
		mode: 'horizontal'
	},

	initialize: function(element, knob, options){
		this.setOptions(options);
		this.element = $(element);
		this.knob = $(knob);
		this.previousChange = this.previousEnd = this.step = -1;
		this.element.addEvent('mousedown', this.clickedElement.bind(this));
		if (this.options.wheel) this.element.addEvent('mousewheel', this.scrolledElement.bindWithEvent(this));
		var offset, limit = {}, modifiers = {'x': false, 'y': false};
		switch (this.options.mode){
			case 'vertical':
				this.axis = 'y';
				this.property = 'top';
				offset = 'offsetHeight';
				break;
			case 'horizontal':
				this.axis = 'x';
				this.property = 'left';
				offset = 'offsetWidth';
		}
		this.half = this.knob[offset] / 2;
		this.full = this.element[offset] - this.knob[offset] + (this.options.offset * 2);
		this.min = $chk(this.options.range[0]) ? this.options.range[0] : 0;
		this.max = $chk(this.options.range[1]) ? this.options.range[1] : this.options.steps;
		this.range = this.max - this.min;
		this.steps = this.options.steps || this.full;
		this.stepSize = Math.abs(this.range) / this.steps;
		this.stepWidth = this.stepSize * this.full / Math.abs(this.range) ;
		
		this.knob.setStyle('position', 'relative').setStyle(this.property, - this.options.offset);
		modifiers[this.axis] = this.property;
		limit[this.axis] = [- this.options.offset, this.full - this.options.offset];
		this.drag = new Drag(this.knob, {
			snap: 0,
			limit: limit,
			modifiers: modifiers,
			onDrag: this.draggedKnob.bind(this),
			onStart: this.draggedKnob.bind(this),
			onComplete: function(){
				this.draggedKnob();
				this.end();
			}.bind(this)
		});
		if (this.options.snap) {
			this.drag.options.grid = Math.ceil(this.stepWidth);
			this.drag.options.limit[this.axis][1] = this.full;
		}
	},

	set: function(step){
		if (!((this.range > 0) ^ (step < this.min))) step = this.min;
		if (!((this.range > 0) ^ (step > this.max))) step = this.max;
		
		this.step = Math.round(step);
		this.checkStep();
		this.end();
		this.fireEvent('onTick', this.toPosition(this.step));
		return this;
	},

	clickedElement: function(event){
		var dir = this.range < 0 ? -1 : 1;
		var position = event.page[this.axis] - this.element.getRelativePosition()[this.axis] - this.half;
		position = position.limit(-this.options.offset, this.full -this.options.offset);
		
		this.step = Math.round(this.min + dir * this.toStep(position));
		this.checkStep();
		this.end();
		this.fireEvent('onTick', position);
	},
	
	scrolledElement: function(event){
		var mode = (this.options.mode == 'horizontal') ? (event.wheel < 0) : (event.wheel > 0);
		this.set(mode ? this.step - this.stepSize : this.step + this.stepSize);
		event.stop();
	},

	draggedKnob: function(){
		var dir = this.range < 0 ? -1 : 1;
		var position = this.drag.value.now[this.axis];
		position = position.limit(-this.options.offset, this.full -this.options.offset);
		this.step = Math.round(this.min + dir * this.toStep(position));
		this.checkStep();
	},

	checkStep: function(){
		if (this.previousChange != this.step){
			this.previousChange = this.step;
			this.fireEvent('onChange', this.step);
		}
	},

	end: function(){
		if (this.previousEnd !== this.step){
			this.previousEnd = this.step;
			this.fireEvent('onComplete', this.step + '');
		}
	},

	toStep: function(position){
		var step = (position + this.options.offset) * this.stepSize / this.full * this.steps;
		return this.options.steps ? Math.round(step -= step % this.stepSize) : step;
	},

	toPosition: function(step){
		return (this.full * Math.abs(this.min - step)) / (this.steps * this.stepSize) - this.options.offset;
	}

});


/*
Script: Scroller.js
	Class which scrolls the contents of any Element (including the window) when the mouse reaches the Element's boundaries.

License:
	MIT-style license.

Note:
	Scroller requires an XHTML doctype.
*/

var Scroller = new Class({

	Implements: [Events, Options],

	options: {
		area: 20,
		velocity: 1,
		onChange: function(x, y){
			this.element.scrollTo(x, y);
		}
	},

	initialize: function(element, options){
		this.setOptions(options);
		this.element = $(element);
		this.listener = ($type(this.element) != 'element') ? $(this.element.getDocument().body) : this.element;
		this.timer = null;
	},

	start: function(){
		this.coord = this.getCoords.bind(this);
		this.listener.addEvent('mousemove', this.coord);
	},

	stop: function(){
		this.listener.removeEvent('mousemove', this.coord);
		this.timer = $clear(this.timer);
	},

	getCoords: function(event){
		this.page = (this.listener.get('tag') == 'body') ? event.client : event.page;
		if (!this.timer) this.timer = this.scroll.periodical(50, this);
	},

	scroll: function(){
		var size = this.element.getSize(), scroll = this.element.getScroll(), pos = this.element.getPosition(), change = {'x': 0, 'y': 0};
		for (var z in this.page){
			if (this.page[z] < (this.options.area + pos[z]) && scroll[z] != 0)
				change[z] = (this.page[z] - this.options.area - pos[z]) * this.options.velocity;
			else if (this.page[z] + this.options.area > (size[z] + pos[z]) && size[z] + size[z] != scroll[z])
				change[z] = (this.page[z] - size[z] + this.options.area - pos[z]) * this.options.velocity;
		}
		if (change.y || change.x) this.fireEvent('onChange', [scroll.x + change.x, scroll.y + change.y]);
	}

});


/*
Script: Assets.js
	Provides methods to dynamically load JavaScript, CSS, and Image files into the document.

License:
	MIT-style license.
*/

var Asset = new Hash({

	javascript: function(source, properties){
		properties = $extend({
			onload: $empty,
			document: document,
			check: $lambda(true)
		}, properties);
		
		var script = new Element('script', {'src': source, 'type': 'text/javascript'});
		
		var load = properties.onload.bind(script), check = properties.check, doc = properties.document;
		delete properties.onload; delete properties.check; delete properties.document;
		
		script.addEvents({
			load: load,
			readystatechange: function(){
				if (this.readyState == 'complete') load();
			}
		}).setProperties(properties);
		
		
		if (Browser.Engine.webkit419) var checker = (function(){
			if (!$try(check)) return;
			$clear(checker);
			load();
		}).periodical(50);
		
		return script.inject(doc.head);
	},

	css: function(source, properties){
		return new Element('link', $merge({
			'rel': 'stylesheet', 'media': 'screen', 'type': 'text/css', 'href': source
		}, properties)).inject(document.head);
	},

	image: function(source, properties){
		properties = $merge({
			'onload': $empty,
			'onabort': $empty,
			'onerror': $empty
		}, properties);
		var image = new Image();
		var element = $(image) || new Element('img');
		['load', 'abort', 'error'].each(function(name){
			var type = 'on' + name;
			var event = properties[type];
			delete properties[type];
			image[type] = function(){
				if (!image) return;
				if (!element.parentNode){
					element.width = image.width;
					element.height = image.height;
				}
				image = image.onload = image.onabort = image.onerror = null;
				event.delay(1, element, element);
				element.fireEvent(name, element, 1);
			};
		});
		image.src = element.src = source;
		if (image && image.complete) image.onload.delay(1);
		return element.setProperties(properties);
	},

	images: function(sources, options){
		options = $merge({
			onComplete: $empty,
			onProgress: $empty
		}, options);
		if (!sources.push) sources = [sources];
		var images = [];
		var counter = 0;
		sources.each(function(source){
			var img = new Asset.image(source, {
				'onload': function(){
					options.onProgress.call(this, counter, sources.indexOf(source));
					counter++;
					if (counter == sources.length) options.onComplete();
				}
			});
			images.push(img);
		});
		return new Elements(images);
	}

});


/*
Script: Fx.Elements.js
	Effect to change any number of CSS properties of any number of Elements.

License:
	MIT-style license.
*/

Fx.Elements = new Class({

	Extends: Fx.CSS,

	initialize: function(elements, options){
		this.elements = this.pass = $$(elements);
		arguments.callee.parent(options);
	},

	compute: function(from, to, delta){
		var now = {};
		for (var i in from){
			var iFrom = from[i], iTo = to[i], iNow = now[i] = {};
			for (var p in iFrom) iNow[p] = arguments.callee.parent(iFrom[p], iTo[p], delta);
		}
		return now;
	},

	set: function(now){
		for (var i in now){
			var iNow = now[i];
			for (var p in iNow) this.render(this.elements[i], p, iNow[p]);
		}
		return this;
	},

	start: function(obj){
		if (!this.check(obj)) return this;
		var from = {}, to = {};
		for (var i in obj){
			var iProps = obj[i], iFrom = from[i] = {}, iTo = to[i] = {};
			for (var p in iProps){
				var parsed = this.prepare(this.elements[i], p, iProps[p]);
				iFrom[p] = parsed.from;
				iTo[p] = parsed.to;
			}
		}
		return arguments.callee.parent(from, to);
	}

});


/*
Script: Accordion.js
	An Fx.Elements extension which allows you to easily create accordion type controls.

License:
	MIT-style license.

Note:
	Accordion requires an XHTML doctype.
*/

var Accordion = new Class({

	Extends: Fx.Elements,

	options: {/*
		onActive: $empty,
		onBackground: $empty,*/
		display: 0,
		show: false,
		height: true,
		width: false,
		opacity: true,
		fixedHeight: false,
		fixedWidth: false,
		wait: false,
		alwaysHide: false
	},

	initialize: function(){
		var params = Array.link(arguments, {'container': Element.type, 'options': Object.type, 'togglers': $defined, 'elements': $defined});
		arguments.callee.parent(params.elements, params.options);
		this.togglers = $$(params.togglers);
		this.container = $(params.container);
		this.previous = -1;
		if (this.options.alwaysHide) this.options.wait = true;
		if ($chk(this.options.show)){
			this.options.display = false;
			this.previous = this.options.show;
		}
		if (this.options.start){
			this.options.display = false;
			this.options.show = false;
		}
		this.effects = {};
		if (this.options.opacity) this.effects.opacity = 'fullOpacity';
		if (this.options.width) this.effects.width = this.options.fixedWidth ? 'fullWidth' : 'offsetWidth';
		if (this.options.height) this.effects.height = this.options.fixedHeight ? 'fullHeight' : 'scrollHeight';
		for (var i = 0, l = this.togglers.length; i < l; i++) this.addSection(this.togglers[i], this.elements[i]);
		this.elements.each(function(el, i){
			if (this.options.show === i){
				this.fireEvent('onActive', [this.togglers[i], el]);
			} else {
				for (var fx in this.effects) el.setStyle(fx, 0);
			}
		}, this);
		if ($chk(this.options.display)) this.display(this.options.display);
	},

	addSection: function(toggler, element, pos){
		toggler = $(toggler);
		element = $(element);
		var test = this.togglers.contains(toggler);
		var len = this.togglers.length;
		this.togglers.include(toggler);
		this.elements.include(element);
		if (len && (!test || pos)){
			pos = $pick(pos, len - 1);
			toggler.inject(this.togglers[pos], 'before');
			element.inject(toggler, 'after');
		} else if (this.container && !test){
			toggler.inject(this.container);
			element.inject(this.container);
		}
		var idx = this.togglers.indexOf(toggler);
		toggler.addEvent('click', this.display.bind(this, idx));
		if (this.options.height) element.setStyles({'padding-top': 0, 'border-top': 'none', 'padding-bottom': 0, 'border-bottom': 'none'});
		if (this.options.width) element.setStyles({'padding-left': 0, 'border-left': 'none', 'padding-right': 0, 'border-right': 'none'});
		element.fullOpacity = 1;
		if (this.options.fixedWidth) element.fullWidth = this.options.fixedWidth;
		if (this.options.fixedHeight) element.fullHeight = this.options.fixedHeight;
		element.setStyle('overflow', 'hidden');
		if (!test){
			for (var fx in this.effects) element.setStyle(fx, 0);
		}
		return this;
	},

	display: function(index){
		index = ($type(index) == 'element') ? this.elements.indexOf(index) : index;
		if ((this.timer && this.options.wait) || (index === this.previous && !this.options.alwaysHide)) return this;
		this.previous = index;
		var obj = {};
		this.elements.each(function(el, i){
			obj[i] = {};
			var hide = (i != index) || (this.options.alwaysHide && (el.offsetHeight > 0));
			this.fireEvent(hide ? 'onBackground' : 'onActive', [this.togglers[i], el]);
			for (var fx in this.effects) obj[i][fx] = hide ? 0 : el[this.effects[fx]];
		}, this);
		return this.start(obj);
	}

});
