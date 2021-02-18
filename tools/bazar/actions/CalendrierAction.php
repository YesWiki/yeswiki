<?php

use YesWiki\Core\YesWikiAction;

require_once('BazarListeAction.php');

class CalendrierAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        if (!empty($arg['class'])) {
            $classes = explode(' ', $arg['class']);
            $classes = array_combine($classes, $classes);
        }
        $minical = (isset($arg['minical']) && $arg['minical']="true") || (isset($classes) && in_array('minical', $classes)) ;
        if ($minical) {
            $classes['minical'] = 'minical';
        }
        $class = (isset($classes) && count($classes)>0) ? implode(' ', $classes) :null;

        return([
            'minical' => $minical ?? null,
            'class' => $class,
            //template - default value calendar
            'template' => (isset($arg['template']) &&
                BazarListeAction::specialActionFromTemplate($arg['template'], 'CALENDRIER_TEMPLATES'))
                ? $arg['template']
                : 'calendar.tpl.html',
        ]);
    }

    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }
}
