<?php

use YesWiki\Core\YesWikiAction;

class ColAction extends YesWikiAction
{
    public function run()
   {
       ob_start();
       $size = $this->arguments['size'];
       if (empty($size)) {
           echo '<div><div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_COL') . '</strong> : '
               . _t('TEMPLATE_SIZE_PARAMETER_REQUIRED') . '.</div>' . "\n";
           return;
       }

       $class = $this->arguments['class'] ?? '';
        if ($this->check_end_elem('col')) {
           echo '<!-- start of col -->' . "\n" .
           '<div class="span' . $size . ' col-md-' . $size . (isset($class) ? ' ' . $class : '')
               . '">';

        } else {
            echo $this->generate_error_msg('col');
        }
       $col = ob_get_contents();
       ob_end_clean();
       return $col;
   }


   public function end(): string {
       return "\n</div> <!-- end of col -->\n";
   }
}
