<?php
	header('content-type:application/x-javascript');
	// Check if the server is configured to automatically compress the output
	if (!ini_get('zlib.output_compression') && !ini_get('zlib.output_handler'))
	{
		// Check if we can use ob_gzhandler (requires the zlib extension)
		if (function_exists('ob_gzhandler'))
		{
			// let ob_gzhandler do the dirty job
			// NB.: this must be done BEFORE session_start() when session.use_trans_sid is on
			ob_start('ob_gzhandler');
		}
		// else lets do the dirty job by ourselves...
		elseif (!empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode'))
		{
			ob_start ('gzencode');
			// Tell the browser the content is compressed with gzip
			header ("Content-Encoding: gzip");
		}
	}
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
    if (e) {e.appendChild(inp)};
}

function <?php echo $fn_enable_name;?>(){
	var e = document.getElementById('hashcash-text');
	createHiddenField();
	if (e) {e.style.display='block'};
	loadHashCashKey('<?php
	echo $_GET['siteurl']; ?>tools/security/wp-hashcash-getkey.php', '<?php echo $field_id; ?>');
}

function loadHashCashKey(fragment_url, e_id) {
	var xmlhttp=createXMLHttp();
	var element = document.getElementById(e_id);

	xmlhttp.open("GET", fragment_url, true);
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200 && element) {
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
