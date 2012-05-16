<?php
/*
    This file is part of Hyla
    Copyright (c) 2004-2010 Charles Rincheval.
    All rights reserved

    Hyla is free software; you can redistribute it and/or modify it
    under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License,
    or (at your option) any later version.

    Hyla is distributed in the hope that it will be useful, but
    WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Hyla; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 *  Refer to http://www.digitalspirit.org/ or http://www.hyla-project.org/ for update
 *  Standalone version 0.7.0
 */

class Hyla_Tpl {

    private $path;
    private $file;

    private $current_file;
    private $tmp_current_file;
    private $current_parsed_file;

    private $remove_unknow_var;
    private $display_error;

    private $errors;

    private $block_cache;
    private $block_parsed;

    private $vars;

    private $functions;
    private $user_functions;

    private $var_functions;
    private $user_var_functions;

    private $l10n_callback;

    const VERSION = '0.7.0';

    function __construct($path = '.') {

        $this->path = $path;

        $this->file = null;
        $this->current_file = null;
        $this->tmp_current_file = null;
        $this->current_parsed_file = null;

        $this->remove_unknow_var = true;
        $this->display_error = true;
        $this->log_error = false;

        $this->errors = array();

        $this->block_cache = array();
        $this->block_parsed = array();

        $this->l10n_callback = array('self', '_l10n');

        $this->vars = array();

        $this->user_var_functions = array();
        $this->var_functions = array(
            'ucfirst'   => 'ucfirst',
            'ucwords'   => 'ucwords',
            'lower'     => 'strtolower',
            'upper'     => 'strtoupper',
            'trim'      => 'trim',
            'rtrim'     => 'rtrim',
            'ltrim'     => 'ltrim',
            'escape'    => 'htmlspecialchars',
            'test'      => array('self', '_func_test'),
        );

        $this->user_functions = array();
        $this->functions = array(
            'cycle'     => array('self', '_func_cycle'),
            'include'   => array($this, '_getFileContent'),
            'import'    => array($this, '_func_import'),
            'errors'    => array($this, '_func_getErrors'),
            'setvar'    => array($this, 'setVar'),
            'l10n'      => &$this->l10n_callback,
        );
    }

    /**
     *  Get library version
     */
    public function getVersion() {
        return self::VERSION;
    }

    /**
     *  Import new file
     *  @param  string  $name   File handler
     *  @param  string  $name   Filename
     *  @param  string  $path   Path
     */
    public function importFile($name, $file = null, $path = null) {
        $ret = null;
        $path = $path ? $path : $this->path;
        $file = ($file) ? $file : $name;

        if ($this->_testFile($path . '/' . $file)) {
            $this->file[$name] = $this->_getFileContent($file, $path);
            $ret = $name;

            // Now, current file is this new file
            $this->current_file = $ret;
        }
        return $ret;
    }

    /**
     *  Set current file
     *  @param mixed    $file   New current file
     */
    public function setCurrentFile($file) {
        $ret = false;
        if (array_key_exists($file, $this->file)) {
            $this->current_file = $file;
        }
        return $ret;
    }

    /**
     *  Set var
     *  @param  string  $name   Variable name
     *  @param  string  $value  Variable value
     */
    public function setVar($name, $value) {
        if (is_array($value) || is_object($value)) {
            foreach ($value as $key => $val) {
                if (is_array($val)) {
                    $this->setVar($name . '.' . $key, $val);
                } else {
                    $this->vars[$name . '.' . $key] = $val;
                }
            }
        } else {
            $this->vars[$name] = $value;
        }
    }

    /**
     *  Set multiple vars
     *  @param  array   $vars   Variable array
     */
    public function setVars(array $vars) {
        foreach ($vars as $key => $val) {
            $this->setVar($key, $val);
        }
    }

    /**
     *  Remove unknow var ?
     *  @param  bool    $bool   Yes or no
     */
    public function removeUnknowVar($bool) {
        return ($this->remove_unknow_var = $bool);
    }

    /**
     *  Display error ?
     *  @param  bool    $bool   Yes or no
     */
    public function displayError($bool) {
        return ($this->display_error = $bool);
    }

    /**
     *  Log error ?
     *  @param  bool    $bool   Yes or no
     */
    public function logError($bool) {
        return ($this->log_error = $bool);
    }

    /**
     *  Set l10n callback function
     *  @param  string  $function   Function
     */
    public function setL10nCallback($function) {
        $ret = false;
        if (is_callable($function)) {
            $this->l10n_callback = $function;
            $ret = true;
        }
        return $ret;
    }

    /**
     *  Get block content
     *  @param  string  $block_name Block name
     */
    public function get($block_name = null) {
        return $this->render($block_name, false);
    }

    /**
     *  Render block
     *  @param  string  $block_name Block name
     *  @param  bool    $render     Render (private)
     */
    public function render($block_name = null, $render = true) {

        // Load block content...
        $data = $this->_loadBlock($block_name, $block_path);

        // A-t-on un block contenu ?
        if (strpos($data, '<!-- BEGIN') !== false) {
            $reg = "/<!-- BEGIN ([a-zA-Z0-9\._]*) -->(\s*?\n?\s*.*?\n?\s*)<!-- END \\1 -->/sm";
            $data = preg_replace_callback($reg, array('self', '_pregGetBlockContent'), $data);
        }

        // Variable replace
        if ($data) {
            $this->_prepareReplaceArray($search, $replace);

            // Replace var
            $data = str_replace($search, $replace, $data);

            // Run function on var
            // First, run set var function (&xxx)
            $data = preg_replace('/{&([a-zA-Z_\-0-9]*)\:((\\\\}|\\\\|[^}])*)}/e', "\$this->setVar('$1', self::_skipQuote(stripslashes('$2')))", $data);

            $data = preg_replace('/{([$|!|_|#])(([a-zA-Z_\-0-9]*)\:?((\\\\}|\\\\|[^}])*))}/e', "\$this->_parseFuncVar('$2', '$1')", $data);
        }

        // Get content and add it !
        if ($render) {
            if (!array_key_exists($block_path, $this->block_parsed) || $this->block_parsed[$block_path] == -1) {
                $this->block_parsed[$block_path] = null;
            }

            $this->block_parsed[$block_path] .= $data;
            $data = $this->block_parsed[$block_path];
        }

        return $data;
    }

    /**
     *  Register a user variable function tpl
     *  @param  string  $name   Name
     *  @param  string  $func   Function
     */
    public function registerVarFunction($name, $func) {
        $ret = false;
        if (is_callable($func)) {
            $this->user_var_functions[$name] = $func;
            $ret = true;
        }
        return $ret;
    }

    /**
     *  Register a user function tpl
     *  @param  string  $name   Name
     *  @param  string  $func   Function
     *  @param  bool    $var    Register also as var function
     */
    public function registerFunction($name, $func, $var = false) {
        $ret = false;
        if (is_callable($func)) {
            $this->user_functions[$name] = $func;
            $ret = true;

            if ($var) {
                $ret = $this->registerVarFunction($name, $func);
            }
        }

        return $ret;
    }

    /**
     *  Get the available function in tpl
     *  @param  bool    $user_func  With user function if true
     */
    public function getFunctionList($user_func = false) {
        return array_keys(($user_func) ? array_merge($this->var_functions, $this->user_var_functions) : $this->var_functions);
    }

    /**
     *  Test if file exists
     *  @param  string  $file   File
     *  @param  bool    $error  Print error if file not found if true
     */
    private function _testFile($file, $error = true) {
        if (!($status = file_exists($file))) {
            if ($error) {
                $this->_error('File "%s" not found !', $file);
            }
        }
        return $status;
    }

    /**
     *  Preg wrapper for _getFileContent
     */
    private function _getFileContentWrapper($var) {
        return $this->_getFileContent($var[1]);
    }

    /**
     *  Get file content
     *  @param string $file  File
     */
    private function _getFileContent($file, $path = null) {

        $content = null;
        $file = self::_skipQuote($file);

        /**
         *  Scan :
         *   1. Scan first in current path
         *   2. Scan in Tpl root
         */
        $try = array(
            dirname($this->current_parsed_file),
            (($path) ? $path : $this->path),
        );

        $i = 1;
        foreach ($try as $f) {

            if (!$f) {
                continue;
            }

            $pfile = $f . '/' . $file;

            // File exists ? Print error only for last test !
            if ($this->_testFile($pfile, ($i == count($try)))) {
                $old = $this->current_parsed_file;
                $this->current_parsed_file = $pfile;
                $content = file_get_contents($pfile);
                $content = preg_replace_callback('/\{\!include\:([^}]+)(\[a-Z|]?)\}/', array($this, '_getFileContentWrapper'), $content);
                $this->current_parsed_file = $old;
                break;
            }
    
            $i++;
        }

        return $content;
    }

    /**
     *  Resolve path block
     *  Block can be in other file, in this case, use the selector ":"
     *  Example :
     *      - Access to toto block in current file :
     *          " toto "
     *      - Access to bar block in foo.tpl :
     *          " foo.tpl:bar "
     *  @param  string  $path           Path to resolve
     *  @param  string  &$file          Reference to file variable
     *  @param  string  &$block_name    Reference to block name
     *  @param  string  &$block_path    Reference to block path
     */
    private function _resolveBlock($path, &$file, &$block_name, &$block_path = null) {
        if (($pos = strpos($path, ':')) === false) {
            $file = $this->current_file;
        } else {
            // File
            $file = substr($path, 0, $pos);
            $block_name = substr($path, $pos + 1);
        }

        $block_path = $file . ':' . $block_name;
    }

    /**
     *  Load block content
     */
    private function _loadBlock($block_name, &$block_path = null) {

        // Test file...
        $this->_resolveBlock($block_name, $file, $block_name, $block_path);

        $this->tmp_current_file = $file;

        if (!array_key_exists($block_path, $this->block_cache)) {
            if ($block_name) {
                $reg = "/[ \t]*<!-- BEGIN " . preg_quote($block_name) . " -->\s*?\n?(\s*.*?\n?)\s*<!-- E(LSE|ND) "
                                            . preg_quote($block_name) . " -->\s*?\n?/sm";
                if (!preg_match($reg, $this->file[$file], $match)) {
                    $this->_error('Invalid "%s" block : not found !', $block_name);
                    return null;
                }

                $data = &$match[1];
            } else {
                $data = &$this->file[$file];
                $block_name = '.';
            }
        } else {
            $data = $this->block_cache[$block_path];
        }

        $this->block_cache[$block_path] = $data;

        return $data;
    }

    /**
     *  Parse func var
     *  @param  string  $str    Variable with func
     *  @param  int     $pos    Offset while start func
     */
    private function _parseFuncVar($val, $type) {

        static $cache = array();
        $out = null;

        switch ($type) {
            // Variable
            case '$':

                // Get default value
                if (!preg_match("/^([a-zA-Z0-9\-\.\_]+)[\s]*(_)?(\([\S\s]*\))*([\s]*\|[\s]*(.*?))*$/iUs", $val, $m)) {
                    return null;
                }

                $name = isset($m[1]) ? $m[1] : null;
                $l10n = isset($m[2]) ? ($m[2] == '_') : null;
                $default = isset($m[3]) ? $m[3] : null;
                $funcs = isset($m[4]) ? $m[4] : null;

                // Format default
                if ($default) {
                    $default = trim(stripslashes($default));
                    if ($default[0] == '(' && $default[strlen($default) - 1] == ')') {
                        $default = substr($default, 1, strlen($default) - 2);
                    }

                    if ($l10n) {
                        $default = call_user_func($this->l10n_callback, $default);
                    }
                }

                // Variable exists ?
                if (array_key_exists($name, $this->vars)) {
                    $value = $this->vars[$name];
                } else {
                    if ($default) {
                        $value = $default;
                    } else {
                        return ($this->remove_unknow_var) ? null : '{$' . $name . '}';
                    }
                }

            // Function
            case '!':
                if ($type == '!') {
                    $funcs = $val;
                    $value = null;
                }

                if ($funcs) {
                    $crc = crc32($funcs);
                    if (!array_key_exists($crc, $cache)) {
                        $funcs = self::_extract($funcs);
                        $cache[$crc] = $funcs;
                    } else {
                        $funcs = $cache[$crc];
                    }
                }

                $out = $value;

                if ($funcs) {
                    $i = 0;
                    foreach ($funcs as $func => $args) {

                        // Replace args
                        if (count($args)) {
                            foreach ($args as &$arg) {
                                if ($arg == '$0') {
                                    $arg = $value;
                                } else if ($arg == '$1') {
                                    $arg = $out;
                                }
                            }
                        }

                        if ($type == '$') {
                            array_unshift($args, $out);
                        } else if (!count($args) && $out) {
                            $args[] = $out;
                        }

                        $out = $this->_runFunc(substr($func, 1), $args, ($type == '$' || $i));
                        $i++;
                    }
                }

                break;
            // L10n
            case '_':
                $out = call_user_func($this->l10n_callback, $val);
                break;
            // Comment, setVar
            case '#':
            case '&':
        }

        return $out;
    }

    /**
     *  Extract functions and params
     *  @param  string  $in     Data in
     */
    private static function _extract($in) {
        $out = null;
        $in = str_replace('\"', '"', $in);

        // Split funcs and args
        if (preg_match_all('/((["\']).*?[^\\\]\\2)|((\|)*[\s]*[\w$]+)/s', $in, $m)) {
            $out = array();
            $i = $f = 0;

            $func = $f . ($m[0][0][0] == '|' ? substr($m[0][0], 1) : $m[0][0]);

            if (count($m[0]) > 1) {
                foreach ($m[0] as $item) {

                    $item = stripslashes(trim($item));

                    if ($item[0] == '"' || $item[0] == "'") {
                        $out[$func][] = self::_skipQuote($item);
                    } else if ($item[0] == '|') {
                        $func = trim(substr($item, 1));
                        $func = $f . $func;
                        $out[$func] = array();
                        $f++;

                    // $0 is the first variable
                    } else if ($item == '$0' || $item == '$1') {
                        $out[$func][] = $item;
                    } else {
                        if ($i) {
                            $out[$func][] = self::_skipQuote($item);
                        }
                    }

                    $i++;
                }
            } else {
                $out[$func] = array();
            }
        }

        return $out;
    }

    /**
     *  Extract parameter from string
     */
    private function _extractParam($str, $original = null, $alternate = null) {

        $param = null;

        if ($str[0] == '\\') {
            $str = substr($str, 1);
        }

        // Explode on ,
        if (preg_match_all("/(['\"])([^\\1]|(\\1(?!,|$))?)*\\1|[^,]+/", $str, $param)) {
            $param = $param[0];
            foreach ($param as &$f) {
                switch ($f) {
                    // $0 is the first variable
                    case '$0':  $f = $alternate; break;
                    // $1 is the return var from last function
                    case '$1':  $f = $original;  break;
                    default:
                        $f = self::_skipQuote($f);
                        break;
                }
            }
        }

        return $param;
    }

    private static function _skipQuote($str) {
        // Delete quote
        $str = trim($str);
        if ($str[0] == "'" && $str[strlen($str) - 1] == "'" ||
            $str[0] == '"' && $str[strlen($str) - 1] == '"') {
            $str = substr($str, 1, strlen($str) - 2);
        }
        return stripslashes($str);
    }

    /**
     *  Run internal function
     *  @param  string  $name   Function name
     *  @param  array   $param  Parameter
     */
    private function _runFunc($name, $param = null, $type = true) {
        $var = null;
        $functions = ($type) ? array_merge($this->var_functions, $this->user_var_functions) : array_merge($this->functions, $this->user_functions);

        if (array_key_exists($name, $functions) && is_callable($functions[$name])) {
            if ($param) {
                $param = !is_array($param) ? array($param) : $param;
                $var = call_user_func_array($functions[$name], $param);
            } else {
                $var = call_user_func($functions[$name]);
            }
        } else {
            $this->_error('Invalid "%s" function !', $name);
        }

        return $var;
    }

    /**
     *  Callback function for get content block
     *  @param  array   $match  Preg search content
     */
    private function _pregGetBlockContent($match) {

        $out = null;
        $else = false;
        $block_name = $match[1];

        $block_path = $this->tmp_current_file . ':' . $block_name;

        // Block exits ?
        if (array_key_exists($block_path, $this->block_parsed)) {

            if ($this->block_parsed[$block_path] == -1) {
                $else = true;
            } else {
                $out = $this->block_parsed[$block_path];
            }

        } else {
            $else = true;
        }

        // Get else block content
        if ($else && ($pos = strpos($match[2], '<!-- ELSE '.$block_name.' -->')) !== false) {
            $out = substr($match[2], $pos + strlen('<!-- ELSE '.$block_name.' -->'));
        }

        $this->block_parsed[$block_path] = -1;
        return $out;
    }

    /**
     *  Prepare var array
     *  @param  array   $search     Search array
     *  @param  array   $replace    Replace array
     */
    private function _prepareReplaceArray(&$search, &$replace) {

        $i = 0;
        $search = array();
        $replace = array();

        foreach ($this->vars as $key => $val) {
            $search[] = '{$'.$key.'}';
            $replace[] = $val;
            $i++;
        }

        return $i;
    }

    /**
     *  L10n default function
     *  Call setL10nCallback method to define your l10n function...
     *  @param  string  $str    String
     */
    private static function _l10n($str) {
        return $str;
    }

    /**
     *  Include tpl file not in tpl root path
     *  @param  string  $file   File to include
     */
    private function _func_import($file) {
        $pos = strrpos($file, '/');
        $path = './';
        if ($pos !== false) {
            $path = substr($file, 0, $pos + 1);
            $file = substr($file, $pos + 1);
        }
        return $this->_getFileContent($file, $path);
    }

    /**
     *  Test function
     */
    private static function _func_test($var, $test, $out, $else = null) {
        return ($var == $test ? $out : $else);
    }

    /**
     *  Cycle function
     */
    private static function _func_cycle($even, $odd, $cycle = 2) {
        static $a = array();
        $key = $even . ' - ' . $odd;
        if (!array_key_exists($key, $a)) {
            $a[$key] = 0;
        }
        return (++$a[$key] % $cycle) ? $even : $odd;
    }

    /**
     *  Get errors
     */
    private function _func_getErrors($html = true) {
        $str = null;
        foreach ($this->errors as $error) {
            $str .= $error;
            if ($html) {
                $str .= '<br />';
            }
        }
        return $str;
    }

    /**
     *  Print error
     */
    private function _error() {

        if ($this->log_error) {
            $param = func_get_args();
            $this->errors[] = call_user_func_array('sprintf', $param);
        }

        if ($this->display_error) {
            $param = func_get_args();
            echo '<strong>' . __CLASS__ . ' error : </strong>' . call_user_func_array('sprintf', $param) . "<br />\n";
        }
    }
}

