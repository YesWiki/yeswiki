<?php

use YesWiki\Core\YesWikiAction;

class GridAction extends YesWikiAction
{

    public function run()
   {
       ob_start();
       $class = $this->arguments['class'] ?? '';
       $class = 'row-fluid row' . ((!empty($class)) ? ' ' . $class : '');
       if ($this->check_end_elem('grid')) {
           echo '<!-- start of grid -->' . "\n" .
           '<div' . ' class="' . $class .'">';
       }
       $col = ob_get_contents();
       ob_end_clean();
       return $col;
   }


   public function end(): string {
       return "\n</div> <!-- end of grid -->\n";
   }
}
