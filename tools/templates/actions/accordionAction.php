<?php

use YesWiki\Core\YesWikiAction;

class AccordionAction extends YesWikiAction
{

    public function run()
    {
       ob_start();
       $class = $this->arguments['class'] ?? '';
       $data = $this->wiki->services->get(\YesWiki\Templates\Service\Utils::class)->getDataParameter();
       $pagetag = $this->wiki->GetPageTag();

       if ($this->check_end_elem('accordion')) {
           if ($GLOBALS['check_' . $pagetag]['accordion']) {
               $accordionID = uniqid('accordion_');
               $GLOBALS['check_' . $pagetag]['accordion_uniqueID'] = $accordionID;

               $data = '';
               if (is_array($data)) {
                   foreach ($data as $key => $value) {
                       $data .= ' data-' . $key . '="' . $value . '"';
                   }
               }
           }
           echo '<!-- start of accordion -->' . "\n" .
           "<div class=\"panel-group $class \" role=\"tablist\" aria-multiselectable=\"true\" id=\"$accordionID\" $data>";
       } else {
           echo $this->generate_error_msg('accordion');
       }
       $accordion = ob_get_contents();
       ob_end_clean();
       return $accordion;
    }


    public function end(): string {
       return "\n</div> <!-- end of accordion -->\n";
       unset($GLOBALS['check_' . $pagetag]['accordion_uniqueID']);
    }
}
