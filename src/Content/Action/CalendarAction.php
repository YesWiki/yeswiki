<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\TemplateEngine;

class CalendarAction extends YesWikiAction implements RegisteredAction
{
    /** `{{calendar}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'calendar';
    }

    public function formatArguments($arg)
    {
        if (!empty($arg['class'])) {
            $classes = explode(' ', $arg['class']);
            $classes = array_combine($classes, $classes);
        }
        // ticket 22 renamed the PARAMETER; `minical` stays as the CSS class, which
        // styles/bazar/calendar.css targets in nine rules and BazarCalendar.js reads. A
        // class name is a value, not a parameter name, and is out of this ticket's scope.
        $minicalendar = (isset($arg['minicalendar']) && $arg['minicalendar'] == 'true') || (isset($classes) && in_array('minical', $classes));
        if ($minicalendar) {
            $classes['minical'] = 'minical';
        }
        $class = (isset($classes) && count($classes) > 0) ? implode(' ', $classes) : null;

        $template = !empty($arg['template']) ? basename($arg['template']) : 'calendar';
        $dynamic = $this->formatBoolean($arg, false, 'dynamic');
        $templateEngine = $this->getService(TemplateEngine::class);
        // 'calendar' has no static template: without one shipped, it is dynamic-only
        if (in_array($template, ['calendar', 'calendar.tpl.html', 'calendar.twig'], true)
            && !$templateEngine->hasTemplate('@core/calendar.twig')) {
            $template = 'calendar';
            $dynamic = true;
        }

        return [
            'minicalendar' => $minicalendar,
            'class' => $class,
            // template - default value calendar
            'template' => $template,
            'dynamic' => $dynamic,
            'pagination' => -1, // disable pagination
        ];
    }

    public function run()
    {
        return $this->callAction('entrylist', $this->arguments);
    }
}
