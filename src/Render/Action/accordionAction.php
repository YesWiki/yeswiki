<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

class AccordionAction extends YesWikiAction implements RegisteredAction
{
    /** `{{accordion}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'accordion';
    }

    public function run()
    {
        ob_start();
        $class = $this->arguments['class'] ?? '';
        $pagetag = $this->getService(PageContext::class)->getTag();

        if ($this->check_end_elem('accordion')) {
            if ($GLOBALS['check_' . $pagetag]['accordion']) {
                $accordionID = uniqid('accordion_');
                $GLOBALS['check_' . $pagetag]['accordion_uniqueID'] = $accordionID;
            }
            // The id is still emitted so a page can link or style one accordion, but the
            // panels no longer read it: <details> needs no coordination to open and close
            // (see PanelAction). Its one cost is that panels no longer close each other.
            echo '<!-- start of accordion -->' . "\n" .
            "<div class=\"yw-accordion $class\" id=\"$accordionID\">";
        } else {
            echo $this->generate_error_msg('accordion');
        }
        $accordion = ob_get_contents();
        ob_end_clean();

        return $accordion;
    }

    public function end(): string
    {
        $pagetag = $this->getService(PageContext::class)->getTag();
        unset($GLOBALS['check_' . $pagetag]['accordion_uniqueID']);

        return "\n</div> <!-- end of accordion -->\n";
    }
}
