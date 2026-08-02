<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class GridAction extends YesWikiAction implements RegisteredAction
{
    /** `{{grid}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'grid';
    }

    public function run()
    {
        ob_start();
        $class = $this->arguments['class'] ?? '';
        $class = 'yw-row' . ((!empty($class)) ? ' ' . $class : '');
        if ($this->check_end_elem('grid')) {
            echo '<!-- start of grid -->' . "\n" .
            '<div class="' . $class . '">';
        } else {
            echo $this->generate_error_msg('grid');
        }
        $col = ob_get_contents();
        ob_end_clean();

        return $col;
    }

    public function end(): string
    {
        return "\n</div> <!-- end of grid -->\n";
    }
}
