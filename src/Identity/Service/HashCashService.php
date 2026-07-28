<?php

namespace YesWiki\Identity\Service;

use YesWiki\Wiki;

class HashCashService
{
    /** How long a generated secret stays valid. */
    private const REFRESH = 60 * 60 * 4;

    protected $wiki;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    /**
     * Absorbed from src/wp-hashcash.lib by wave-two ticket 05 (CP3). That file declared
     * eight global constants and three global functions; only two constants and two
     * functions were ever used, all of them from this class, and every method here opened
     * with a `require_once` of it. Five constants and hashcash_verbage() were dead.
     */
    private function secretFile(): string
    {
        return YESWIKI_SOURCE_DIR . '/cache/hashcash.key';
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

    /** The current secret key. */
    private function secretValue(): string
    {
        // the original guarded this with function_exists('file_get_contents') and a
        // fopen/fread fallback -- that function has been unconditionally available for
        // the entire life of PHP 5+, let alone the ^8.3 this requires
        return (string)file_get_contents($this->secretFile());
    }

    /**
     * Verifies the submitted hashcash_value POST field against the current server-side
     * puzzle answer (see getKeyScript()). Returns true when hashcash is disabled (nothing
     * to check) or the value matches; false when enabled and the value is missing/wrong.
     * Consolidates a check previously duplicated between the edit and add-comment flows.
     */
    public function checkHashcash(): bool
    {
        if (empty($this->wiki->config['use_hashcash'])) {
            return true;
        }
        $value = $this->wiki->request->request->get('hashcash_value');

        return isset($value) && $value == $this->secretValue();
    }

    public function getJavascriptCode($formId = 'ACEditor')
    {
        if (!file_exists($this->secretFile())) {
            $handle = fopen($this->secretFile(), 'w');
            fclose($handle);
        }
        // UPDATE RANDOM SECRET
        $curr = @file_get_contents($this->secretFile());
        if (empty($curr) || (time() - @filemtime($this->secretFile())) > self::REFRESH) {
            if (is_writable($this->secretFile())) {
                // update our secret
                $fp = fopen($this->secretFile(), 'w');
                fwrite($fp, rand(21474836, 2126008810));
                fclose($fp);
            }
        }

        $scriptUrl = $this->wiki->Href('', 'api/hashcash', ['formid' => $formId]);

        return '<script type="text/javascript" src="' . $scriptUrl . '"></script><span id="hashcash-text" style="display:none" class="pull-right">' . _t('HASHCASH_ANTISPAM_ACTIVATED') . '</span>';
    }

    /**
     * The bootstrap script served at GET ?api/hashcash: inserts the hidden "hashcash_value"
     * field into the given form, then fetches the actual puzzle from getKeyScript() below.
     * Equivalent of the old tools/security/wp-hashcash-js.php, served directly by URL - which
     * doesn't exist as a physical file on farm instances (see src/bootstrap_paths.php).
     */
    public function getEnableScript(string $formId): string
    {

        $formId = htmlspecialchars(strip_tags($formId));
        $fieldId = $this->randomString(rand(6, 18));
        $enableFunctionName = $this->randomString(rand(6, 18));
        $keyUrl = $this->wiki->Href('', 'api/hashcash/key');

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
     * The actual proof-of-work puzzle served at GET ?api/hashcash/key: obfuscated JS that
     * evaluates to the current secret (see secretValue() above), so a
     * real browser (but not a naive spam bot) ends up posting the right hashcash_value.
     * Equivalent of the old tools/security/wp-hashcash-getkey.php, served directly by URL.
     */
    public function getKeyScript(): string
    {

        $expired = [];

        $functionName = $this->randomString(rand(6, 18));
        $expired[] = $functionName;

        $js = "function $functionName (){";

        $type = rand(0, 3) * 0;
        switch ($type) {
            /* Addition of n times of field value / n, + modulus:
            Time guarantee:  100 iterations or less */
            case 0:
                $eax = $this->randomString(rand(8, 10), $expired);
                $expired[] = $eax;

                $val = intval($this->secretValue());
                $inc = rand(intval($val / 100), $val - 1);
                $n = floor($val / $inc);
                $r = $val % $inc;

                $js .= "var $eax = $inc; ";
                for ($i = 0; $i < $n - 1; $i++) {
                    $js .= "$eax += $inc; ";
                }

                $js .= "$eax += $r; ";
                $js .= "return $eax; ";
                break;

                /* Conversion from binary:
                Time guarantee:  log(n) iterations or less */
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

                /* Multiplication of square roots:
                Time guarantee:  constant time */
            case 2:
                $val = intval($this->secretValue());
                $sqrt = floor(sqrt($val));
                $r = $val - ($sqrt * $sqrt);
                $js .= "return $sqrt * $sqrt + $r; ";
                break;

                /* Sum of random numbers to the final value:
                Time guarantee:  log(n) expected value */
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

        // xor all the bytes with a random key
        $key = rand(21474836, 2126008810);
        $js = self::strToLongs($js);

        for ($i = 0; $i < count($js); $i++) {
            $js[$i] = $js[$i] ^ $key;
        }

        // libs function encapsulation
        $libsName = $this->randomString(rand(6, 18), $expired);
        $expired[] = $libsName;

        $libs = "function $libsName(){";

        // write bytes to javascript, xor with key
        $dataName = $this->randomString(rand(6, 18), $expired);
        $expired[] = $dataName;

        $libs .= "var $dataName = new Array(" . count($js) . '); ';
        for ($i = 0; $i < count($js); $i++) {
            $libs .= $dataName . '[' . $i . '] = ' . $js[$i] . ' ^ ' . $key . '; ';
        }

        // convert bytes back to string
        $libs .= " var a = new Array($dataName.length); ";
        $libs .= 'for (var i=0; i<' . $dataName . '.length; i++) { ';
        $libs .= 'a[i] = String.fromCharCode(' . $dataName . '[i] & 0xFF, ' . $dataName . '[i]>>>8 & 0xFF, ';
        $libs .= $dataName . '[i]>>>16 & 0xFF, ' . $dataName . '[i]>>>24 & 0xFF); } ';
        $libs .= "return eval(a.join('')); ";

        // call libs function
        $libs .= "} $libsName();";

        return $libs;
    }

    // pack bytes
    private static function strToLongs($s)
    {
        $l = [];

        // pad $s to some multiple of 4
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
