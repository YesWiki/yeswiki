<?php

use YesWiki\Core\YesWikiAction;

class GridAction extends YesWikiAction
{
    public function run()
   {
       $class = $this->arguments['class'] ?? '';
       $class = 'row-fluid row' . ((!empty($class)) ? ' ' . $class : '');
       if ($this->check_end_elem('grid')) {
           echo '<!-- start of grid -->' . '\n' .
           '<div' . ' class="' . $class .'">';
       }
   }


   public function end(): string {
       return "\n</div> <!-- end of grid -->\n";
   }
}
