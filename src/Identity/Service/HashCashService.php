<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\UrlFormatter;

class HashCashService
{
    protected UrlFormatter $urlFormatter;

    /** How long a generated secret stays valid. */
    private const REFRESH = 60 * 60 * 4;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, UrlFormatter $urlFormatter)
    {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
    }

    /** Absorbed from src/wp-hashcash.lib by wave-two ticket 05 (CP3). */
    private function secretFile(): string
    {
        return YESWIKI_INSTANCE_DIR . '/cache/hashcash.key';
    }

    /**
     * Produce a random string of length $length, avoiding any value in $exclude.
     *
     * @param list<string> $exclude
     */
    private function randomString(int $length, array $exclude = []): string
    {
        if ($length < 1) {
            return '';
        }
        $str = '';
        while (in_array($str, $exclude) || strlen($str) < $length) {
            $str = '';
            while (strlen($str) < $length) {
                $str .= chr(rand(65, 90) + rand(0, 1) * 32);
            }
        }

        return $str;
    }

    /** The current secret key, or '' when there is none to be had. */
    private function secretValue(): string
    {
        return (string)@file_get_contents($this->secretFile());
    }

    /** Write a fresh secret, and say whether it could be written at all. */
    private function refreshSecret(): bool
    {
        return @file_put_contents($this->secretFile(), (string)rand(21474836, 2126008810)) !== false;
    }

    /** Whether there is a usable puzzle -- a secret this wiki can read and refresh. */
    private function hasSecret(): bool
    {
        $file = $this->secretFile();
        $current = @file_get_contents($file);
        if ($current === false || $current === '' || (time() - (int)@filemtime($file)) > self::REFRESH) {
            return $this->refreshSecret();
        }

        return true;
    }

    /**
     * Verifies the submitted hashcash_value POST field against the current server-side puzzle answer (see getKeyScript()).
     */
    public function checkHashcash(): bool
    {
        if (empty($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['use_hashcash'])) {
            return true;
        }

        if (!$this->hasSecret()) {
            return true;
        }
        $value = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->get('hashcash_value');

        return isset($value) && $value == $this->secretValue();
    }

    public function getJavascriptCode($formId = 'ACEditor')
    {
        if (!$this->hasSecret()) {
            return '';
        }

        $scriptUrl = $this->urlFormatter->href('', 'api/hashcash', ['formid' => $formId]);

        return '<script type="text/javascript" src="' . $scriptUrl . '"></script><span id="hashcash-text" style="display:none" class="pull-right">' . _t('HASHCASH_ANTISPAM_ACTIVATED') . '</span>';
    }

    /**
     * The bootstrap script served at GET ?api/hashcash: inserts the hidden "hashcash_value" field into the given form, then fetches the actual puzzle from getKeyScript() below.
     */
    public function getEnableScript(string $formId): string
    {
        $formId = htmlspecialchars(strip_tags($formId));
        $fieldId = $this->randomString(rand(6, 18));
        $enableFunctionName = $this->randomString(rand(6, 18));
        $keyUrl = $this->urlFormatter->href('', 'api/hashcash/key');

        return <<<JS
            addLoadEvent({$enableFunctionName});

            function createHiddenField(){
            	var inp = document.createElement('input');
            	inp.setAttribute('type', 'hidden');
            	inp.setAttribute('id', '{$fieldId}');
            	inp.setAttribute('name', 'hashcash_value');
            	inp.setAttribute('value', '-1');

            	var e = document.getElementById('{$formId}');
            	if (e) {e.appendChild(inp)};
            }

            function {$enableFunctionName}(){
            	var e = document.getElementById('hashcash-text');
            	createHiddenField();
            	if (e) {e.style.display='block'};
            	loadHashCashKey('{$keyUrl}', '{$fieldId}');
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
            JS;
    }

    /**
     * The actual proof-of-work puzzle served at GET ?api/hashcash/key: obfuscated JS that evaluates to the current secret (see secretValue() above), so a real browser (but not a naive spam bot) ends up posting the right hashcash_value.
     */
    public function getKeyScript(): string
    {
        $expired = [];

        $functionName = $this->randomString(rand(6, 18));
        $expired[] = $functionName;

        $js = "function $functionName (){";

        $type = rand(0, 3) * 0;
        switch ($type) {
            case 0:
                $eax = $this->randomString(rand(8, 10), $expired);
                $expired[] = $eax;

                $val = intval($this->secretValue());

                $inc = $val > 1 ? max(1, rand(intval($val / 100), $val - 1)) : 1;
                $n = floor($val / $inc);
                $r = $val % $inc;

                $js .= "var $eax = $inc; ";
                for ($i = 0; $i < $n - 1; $i++) {
                    $js .= "$eax += $inc; ";
                }

                $js .= "$eax += $r; ";
                $js .= "return $eax; ";
                break;

            case 1:
                $eax = $this->randomString(rand(8, 10), $expired);
                $expired[] = $eax;

                $ebx = $this->randomString(rand(8, 10), $expired);
                $expired[] = $ebx;

                $ecx = $this->randomString(rand(8, 10), $expired);
                $expired[] = $ecx;

                $val = intval($this->secretValue());
                $binval = strrev(base_convert($val, 10, 2));
                $js .= "var $eax = \"$binval\"; ";
                $js .= "var $ebx = 0; ";
                $js .= "var $ecx = 0; ";
                $js .= "while($ecx < $eax.length){ ";
                $js .= "if($eax.charAt($ecx) == \"1\") { ";
                $js .= "$ebx += Math.pow(2, $ecx); ";
                $js .= '} ';
                $js .= "$ecx++; ";
                $js .= '} ';
                $js .= "return $ebx; ";

                break;

            case 2:
                $val = intval($this->secretValue());
                $sqrt = floor(sqrt($val));
                $r = $val - ($sqrt * $sqrt);
                $js .= "return $sqrt * $sqrt + $r; ";
                break;

            case 3:
                $val = intval($this->secretValue());
                $js .= 'return ';

                $i = 0;
                while ($val > 0) {
                    if ($i++ > 0) {
                        $js .= '+';
                    }

                    $temp = rand(1, $val);
                    $val -= $temp;
                    $js .= $temp;
                }

                $js .= ';';
                break;
        }

        $js .= "} $functionName ();";

        $key = rand(21474836, 2126008810);
        $js = self::strToLongs($js);

        for ($i = 0; $i < count($js); $i++) {
            $js[$i] = $js[$i] ^ $key;
        }

        $libsName = $this->randomString(rand(6, 18), $expired);
        $expired[] = $libsName;

        $libs = "function $libsName(){";

        $dataName = $this->randomString(rand(6, 18), $expired);
        $expired[] = $dataName;

        $libs .= "var $dataName = new Array(" . count($js) . '); ';
        for ($i = 0; $i < count($js); $i++) {
            $libs .= $dataName . '[' . $i . '] = ' . $js[$i] . ' ^ ' . $key . '; ';
        }

        $libs .= " var a = new Array($dataName.length); ";
        $libs .= 'for (var i=0; i<' . $dataName . '.length; i++) { ';
        $libs .= 'a[i] = String.fromCharCode(' . $dataName . '[i] & 0xFF, ' . $dataName . '[i]>>>8 & 0xFF, ';
        $libs .= $dataName . '[i]>>>16 & 0xFF, ' . $dataName . '[i]>>>24 & 0xFF); } ';
        $libs .= "return eval(a.join('')); ";

        $libs .= "} $libsName();";

        return $libs;
    }

    private static function strToLongs($s)
    {
        $l = [];

        $s = preg_split('//', $s, -1, PREG_SPLIT_NO_EMPTY);

        while (count($s) % 4 != 0) {
            $s[] = ' ';
        }

        for ($i = 0; $i < ceil(count($s) / 4); $i++) {
            $l[$i] = ord($s[$i * 4]) + (ord($s[$i * 4 + 1]) << 8) + (ord($s[$i * 4 + 2]) << 16) + (ord($s[$i * 4 + 3]) << 24);
        }

        return $l;
    }
}
