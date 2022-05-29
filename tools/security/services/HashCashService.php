<?php

namespace YesWiki\Security\Service;

use YesWiki\Wiki;

class HashCashService
{
    private const HASHCASH_FORM_CLASS = 'page';
    private const HASHCASH_REFRESH = 60*60*4;
    private const HASHCASH_IP_EXPIRE = 60*60*24*7;
    private const HASHCASH_VERSION = 3.2;

    protected $wiki;
    protected $hashcashKey;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
        $this->hashcashKey = $this->wiki->getLocalPath('cache').'/hashcash.key';
    }

    // Produce random unique strings
    public function hashcash_random_string($l, $exclude = array()) {
        // Sanity check
        if($l < 1){
            return '';
        }

        $str = '';
        while(in_array($str, $exclude) || strlen($str) < $l){
            $str = '';
            while(strlen($str) < $l){
                $str .= chr(rand(65, 90) + rand(0, 1) * 32);
            }
        }

        return $str;
    }

    // looks up the secret key
    public function hashcash_field_value(){
        return file_get_contents($this->hashcashKey);
    }

    public function getJavascriptCode($formId="ACEditor")
    {
        if (!file_exists($this->hashcashKey)) {
            $handle = fopen($this->hashcashKey, 'w');
            fclose($handle);
        }
        // UPDATE RANDOM SECRET
        $curr = @file_get_contents($this->hashcashKey);
        if (empty($curr) || (time() - @filemtime($this->hashcashKey)) > self::HASHCASH_REFRESH) {
            if (is_writable($this->hashcashKey)) {
                //update our secret
                $fp = fopen($this->hashcashKey, 'w');
                fwrite($fp, rand(21474836, 2126008810));
                fclose($fp);
            }
        }

        $field_id = $this->hashcash_random_string(rand(6, 18));
        $fn_enable_name = $this->hashcash_random_string(rand(6, 18));
        $siteUrl = $this->wiki->href('hashcash');
        $js = <<<js
addLoadEvent($fn_enable_name);

function createHiddenField(){
    var inp = document.createElement('input');
    inp.setAttribute('type', 'hidden');
    inp.setAttribute('id', '$field_id');
    inp.setAttribute('name', 'hashcash_value');
    inp.setAttribute('value', '-1');

    var e = document.getElementById('$formId');
    if (e) {e.appendChild(inp)};
}

function $fn_enable_name(){
    var e = document.getElementById('hashcash-text');
    createHiddenField();
    if (e) {e.style.display='block'};
    loadHashCashKey('$siteUrl', '$field_id');
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
js;
        return '<script>'.$js.'</script><span id="hashcash-text" style="display:none" class="pull-right">'._t('HASHCASH_ANTISPAM_ACTIVATED').'</span>';
    }
}
