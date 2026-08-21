<?php

use YesWiki\Core\YesWikiAction;

class LabelAction extends YesWikiAction
{
    public function run()
    {
        ob_start();
        $id = $this->arguments['id'] ?? '';

        $class = $this->arguments['class'] ?? 'label-default';
        if ($this->check_end_elem('label')) {
            echo '<!-- start of label -->' . "\n" .
            '<span' . (!empty($id) ? ' id="' . $id . '"' : '') . ' class="label ' . $class
                . '">';
        } else {
            echo $this->generate_error_msg('label');
        }
        $label = ob_get_contents();
        ob_end_clean();

        return $label;
    }

    public function end(): string
    {
        return '</span>';
    }
}
