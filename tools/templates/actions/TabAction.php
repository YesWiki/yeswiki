<?php

use YesWiki\Core\YesWikiAction;

class TabAction extends YesWikiAction
{
  public function formatArguments($arg){
    return [
      'class' => 'yeswiki-tab'.(!empty($arg['class']) ? ' '.$arg['class'] : ''),
      'id' => (!empty($arg['id']) ? $arg['id'] : null),
      'data' => getDataParameter()
    ];
  }

  public function run()
  {
    $output = '';
    $pagetag = $this->wiki->GetPageTag();
    if (!isset($GLOBALS['check_'.$pagetag])) {
        $GLOBALS['check_'.$pagetag] = [];
    }
    if (!isset($GLOBALS['check_' . $pagetag]['tab'])) {
        $GLOBALS['check_' . $pagetag]['tab'] = check_graphical_elements('tab', $pagetag, $this->wiki->page['body'] ?? '');
    }
    if ($GLOBALS['check_' . $pagetag]['tab']) {
        $output .= '<div'.(!empty($this->arguments['id']) ? ' id="'.$this->arguments['id'].'"' : '').' class="'.$this->arguments['class'].'"';
        if (is_array($this->arguments['data'])) {
            foreach ($this->arguments['data'] as $key => $value) {
                $output .= ' data-'.$key.'="'.$value.'"';
            }
        }
        $output .=  '>'."\n";
    } else {
        $output .=  '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_TAB') . '</strong> : '
            . _t('TEMPLATE_ELEM_TAB_NOT_CLOSED') . '.</div>' . "\n";
        return;
    }              
  }
}
