<?php

namespace YesWiki\Render\Action;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class LabelAction extends YesWikiAction implements RegisteredAction
{
    /** `{{label}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'label';
    }

    public function run()
   {
       ob_start();
       $id = $this->arguments['id'] ?? '';

       // $class is a raw, page-content-supplied CSS class name (historically a Bootstrap
       // "label-*" color variant) -- kept as a pass-through, not remapped, so existing page
       // bodies using it keep working on themes that still style it
       $class = $this->arguments['class'] ?? 'label-default';
        if ($this->check_end_elem('label')) {
           echo '<!-- start of label -->' . "\n" .
           '<span' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="yw-label ' . $class
               . '">';
        } else {
            echo $this->generate_error_msg('label');
        }
       $label = ob_get_contents();
       ob_end_clean();
       return $label;
   }


   public function end(): string {
       return '</span>';
   }
}
