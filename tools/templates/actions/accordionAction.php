<?php

use YesWiki\Core\YesWikiAction;

class AccordionAction extends YesWikiAction
{
    public function run()
    {
        ob_start();
        $class = $this->arguments['class'] ?? '';
        $pagetag = $this->wiki->GetPageTag();

        if ($this->check_end_elem('accordion')) {
            if ($GLOBALS['check_' . $pagetag]['accordion']) {
                $accordionID = uniqid('accordion_');
                $GLOBALS['check_' . $pagetag]['accordion_uniqueID'] = $accordionID;
            }
            echo '<!-- start of accordion -->' . "\n" .
            "<div class=\"panel-group $class \" role=\"tablist\" aria-multiselectable=\"true\" id=\"$accordionID\">";
        } else {
            echo $this->generate_error_msg('accordion');
        }
        $accordion = ob_get_contents();
        ob_end_clean();

        return $accordion;
    }

    public function end(): string
    {
        return "\n</div> <!-- end of accordion -->\n";
        $pagetag = $this->wiki->GetPageTag();
        unset($GLOBALS['check_' . $pagetag]['accordion_uniqueID']);
    }
}
