<?php

namespace YesWiki\Content\TemplateData;

/** The calendar's own classes: `minicalendar` and the `minical` class are one setting. */
class PrepareDataCalendar extends PrepareData
{
    public function prepare(array $arguments): array
    {
        $classes = [];
        if (!empty($arguments['class'])) {
            $names = explode(' ', (string)$arguments['class']);
            $classes = array_combine($names, $names);
        }

        $miniCalendar = $this->formatBoolean($arguments, false, 'minicalendar') || isset($classes['minical']);
        if ($miniCalendar) {
            $classes['minical'] = 'minical';
        }

        return [
            'minicalendar' => $miniCalendar,
            'class' => $classes === [] ? null : implode(' ', $classes),
            'pagination' => -1,
        ];
    }
}
