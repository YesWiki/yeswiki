<?php

namespace YesWiki\Content\TemplateData;

use YesWiki\Render\Service\TemplateEngine;

/** The calendar's own classes, and its fall back to the Vue view (was `CalendarAction::formatArguments()`). */
class PrepareDataCalendar extends PrepareData
{
    public function prepare(array $arguments): array
    {
        $classes = [];
        if (!empty($arguments['class'])) {
            $names = explode(' ', (string)$arguments['class']);
            $classes = array_combine($names, $names);
        }

        $miniCalendar = (isset($arguments['minicalendar']) && $arguments['minicalendar'] == 'true')
            || isset($classes['minical']);
        if ($miniCalendar) {
            $classes['minical'] = 'minical';
        }

        $template = !empty($arguments['template']) ? basename((string)$arguments['template']) : 'calendar';
        $dynamic = $this->formatBoolean($arguments, false, 'dynamic');

        if (
            in_array($template, ['calendar', 'calendar.tpl.html', 'calendar.twig'], true)
            && !$this->getService(TemplateEngine::class)->hasTemplate('@core/calendar.twig')
        ) {
            $template = 'calendar';
            $dynamic = true;
        }

        return [
            'minicalendar' => $miniCalendar,
            'class' => $classes === [] ? null : implode(' ', $classes),
            'template' => $template,
            'dynamic' => $dynamic,
            'pagination' => -1,
        ];
    }
}
