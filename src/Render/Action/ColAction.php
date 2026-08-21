<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class ColAction extends YesWikiAction implements RegisteredAction
{
    /** `{{col}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'col';
    }

    /**
     * @return string opening markup for the column, or an error message when `size` is
     *                missing or the closing `{{end elem="col"}}` is
     */
    public function run()
    {
        $size = $this->arguments['size'];
        if (empty($size)) {
            return '<div><div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_COL') . '</strong> : '
                . _t('TEMPLATE_SIZE_PARAMETER_REQUIRED') . '.</div>' . "\n";
        }

        $class = $this->arguments['class'] ?? '';
        $percent = round($size / 12 * 100, 4);
        if (!$this->check_end_elem('col')) {
            return $this->generate_error_msg('col');
        }

        return '<!-- start of col -->' . "\n"
            . '<div class="yw-col' . (!empty($class) ? ' ' . $class : '') . '" style="width:' . $percent . '%;">';
    }

    public function end(): string
    {
        return "\n</div> <!-- end of col -->\n";
    }
}
