<?php

use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiAction;

class CalendrierAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        if (!empty($arg['class'])) {
            $classes = explode(' ', $arg['class']);
            $classes = array_combine($classes, $classes);
        }
        $minical = (isset($arg['minical']) && $arg['minical'] == 'true') || (isset($classes) && in_array('minical', $classes));
        if ($minical) {
            $classes['minical'] = 'minical';
        }
        $class = (isset($classes) && count($classes) > 0) ? implode(' ', $classes) : null;

        $template = !empty($arg['template']) ? basename($arg['template']) : 'calendar.tpl.html';
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');
        $templateEngine = $this->getService(TemplateEngine::class);
        if (($template === 'calendar.tpl.html' && !$templateEngine->hasTemplate("@bazar/{$template}")) ||
            ($template === 'calendar' && !$templateEngine->hasTemplate("@bazar/{$template}.tpl.html"))) {
            $template = 'calendar';
            $dynamic = true;
        }

        return [
            'minical' => $minical ?? null,
            'class' => $class,
            //template - default value calendar
            'template' => $template,
            'dynamic' => $dynamic,
            'pagination' => -1, // disable pagination
        ];
    }

    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }
}
