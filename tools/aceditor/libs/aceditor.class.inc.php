<?php
namespace YesWiki;

class Aceditor extends \YesWiki\Wiki
{
    function FormOpen($method = '', $tag = '', $formMethod = 'post', $class='') {
		if ($method=='edit') {
			$result  = '<form id="ACEditor" name="ACEditor" enctype="multipart/form-data" action="'.$this->href($method, $tag).'" method="'.$formMethod.'"';
			$result .= ((!empty($class)) ? ' class="'.$class.'"' : '');
			$result .= '>'."\n";
		} else {
			$result = '<form action="'.$this->href($method, $tag).'" method="'.$formMethod.'"';
			$result .= ((!empty($class)) ? ' class="'.$class.'"' : '');
			$result .= '>'."\n";
		}

		if (!$this->config['rewrite_mode']) {
            $result .= '<input type="hidden" name="wiki" value="'.$this->MiniHref($method, $tag).'" />'."\n";
        }
		return $result;
	}
}
