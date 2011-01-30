<?php
	ob_start("ob_gzhandler");
	require_once(realpath(dirname(__FILE__) . '/') . '/secret/wp-hashcash.lib');
	
	$field_id = hashcash_random_string(rand(6,18));
	$fn_enable_name = hashcash_random_string(rand(6,18));
?>

addLoadEvent(<?php echo $fn_enable_name; ?>);

function createHiddenField(){
	var inp = document.createElement('input');
	inp.setAttribute('type', 'hidden');
	inp.setAttribute('id', '<?php echo $field_id; ?>');
	inp.setAttribute('name', 'hashcash_value');
	inp.setAttribute('value', '-1');
	
	var e = document.getElementById('<?php echo HASHCASH_FORM_ID; ?>');
    e.appendChild(inp);
}

function addVerbage(){
	var e = getElementsByClass('<?php echo HASHCASH_FORM_CLASS; ?>');
	var p = document.createElement('p');
	p.innerHTML = '<?php echo str_replace("'", "\'", hashcash_verbage()); ?>';
	e[0].appendChild(p);
}

function <?php echo $fn_enable_name;?>(){
	createHiddenField();
	addVerbage();
	loadHashCashKey('<?php 
	echo $_GET['siteurl']; ?>/tools/hashcash/wp-hashcash-getkey.php', '<?php echo $field_id; ?>');
}	

function loadHashCashKey(fragment_url, e_id) {
	var xmlhttp=createXMLHttp();
	var element = document.getElementById(e_id);

	xmlhttp.open("GET", fragment_url, true);
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			element.value = eval(xmlhttp.responseText);
		}
	}

	xmlhttp.send(null);
}

function getElementsByClass(searchClass,node,tag) {
	var classElements = new Array();
	if ( node == null )
		node = document;
	if ( tag == null )
		tag = '*';
	var els = node.getElementsByTagName(tag);
	var elsLen = els.length;
	var pattern = new RegExp("(^|\\s)"+searchClass+"(\\s|$)");
	for (i = 0, j = 0; i < elsLen; i++) {
		if ( pattern.test(els[i].className) ) {
			classElements[j] = els[i];
			j++;
		}
	}
	return classElements;
}

function createXMLHttp() {
	if (typeof XMLHttpRequest != "undefined")
		return new XMLHttpRequest();
	
	var xhrVersion = [ "MSXML2.XMLHttp.5.0", "MSXML2.XMLHttp.4.0","MSXML2.XMLHttp.3.0", "MSXML2.XMLHttp","Microsoft.XMLHttp" ];
  
	for (var i = 0; i < xhrVersion.length; i++) {
  	try {
			var xhrObj = new ActiveXObject(xhrVersion[i]);
      return xhrObj;
    } catch (e) { }
  }
  
  return null;
}

function addLoadEvent(func) {
  var oldonload = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = func;
  } else {
    window.onload = function() {
		func();
		oldonload();
    }
  }
}
