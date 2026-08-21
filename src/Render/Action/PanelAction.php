<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\GraphicalElementState;

class PanelAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{panel}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'panel';
    }

    public function components(): array
    {
        return [
            Component::for('panel')
                ->category(Category::Writing)
                ->label(_t('AB_templates_panel_label'))
                ->icon('layout-rows')
                ->previewHeight('300px')
                ->wraps(_t('AB_templates_panel_wrappedcontentexample'))
                ->settings(
                    Setting::text('title')
                        ->label(_t('AB_templates_panel_title_label'))
                        ->suggests(_t('AB_templates_panel_title_default'))
                        ->required(),
                    Setting::choice('type', [
                        'default' => _t('AB_templates_panel_type_default'),
                        'collapsible' => _t('AB_templates_panel_type_collapsible'),
                        'collapsed' => _t('AB_templates_panel_type_collapsed'),
                    ])
                        ->label('Type'),
                    Setting::choice('class', [
                        'panel-default' => _t('AB_template_actions_default'),
                        'panel-primary' => _t('AB_template_actions_primary'),
                        'panel-secondary-1' => _t('AB_template_actions_secondary_1'),
                        'panel-secondary-2' => _t('AB_template_actions_secondary_2'),
                    ])
                        ->label(_t('AB_template_actions_color'))
                        ->writesTo('class')
                        ->default('panel-default'),
                    Setting::text('class-extra')
                        ->label(_t('AB_templates_panel_custom_class_label'))
                        ->hint(_t('AB_templates_panel_custom_class_hint'))
                        ->writesTo('class'),
                ),
        ];
    }

    public function run()
    {
        ob_start();

        $title = $this->arguments['title'] ?? '';

        $class = $this->arguments['class'] ?? '';

        $type = $this->arguments['type'] ?? '';
        $pagetag = $this->getService(PageContext::class)->getTag();

        if ($this->check_end_elem('panel')) {
            $headingID = uniqid('heading');
            $collapseID = uniqid('collapse');

            $collapsed = ($type == 'collapsed');
            $collapsible = ($type == 'collapsible') || $collapsed;

            $elements = $this->getService(GraphicalElementState::class);
            $accordionID = $elements->currentAccordion($pagetag);
            if ($accordionID !== '') {
                $collapsible = true;
                // the first panel of an accordion is the open one
                $collapsed = $elements->accordionTakesAnotherPanel($pagetag);
            }

            $inAccordion = !empty($accordionID);

            if ($collapsible && $inAccordion) {
                $result = '<!-- start of panel -->'
                    . '<details class="yw-accordion__item ' . $class . '"' . ($collapsed ? '' : ' open') . '>'
                    . '<summary class="yw-accordion__summary">' . $title . '</summary>'
                    . '<div class="yw-accordion__body">';
            } elseif ($collapsible) {
                $result = '<!-- start of panel -->'
                    . '<div class="yw-panel ' . $class . '">'
                    . '<details class="yw-accordion__item"' . ($collapsed ? '' : ' open') . '>'
                    . '<summary class="yw-accordion__summary yw-panel__heading">'
                    . '<h4 class="yw-panel__title">' . $title . '</h4>'
                    . '</summary>'
                    . '<div class="yw-accordion__body yw-panel__body">';
            } else {
                $result = '<!-- start of panel -->'
                    . '<div class="yw-panel ' . $class . '">'
                    . '<div class="yw-panel__heading"><h4 class="yw-panel__title">' . $title . '</h4></div>'
                    . '<div class="yw-panel__body">';
            }

            $elements->openPanel($pagetag, $collapsible
                ? ($inAccordion ? 'accordion-item' : 'collapsible-panel')
                : 'panel');

            echo $result;
        } else {
            echo $this->generate_error_msg('panel');
        }
        $panel = ob_get_contents();
        ob_end_clean();

        return $panel;
    }

    public function end(): string
    {
        $pagetag = $this->getService(PageContext::class)->getTag();
        $shape = $this->getService(GraphicalElementState::class)->closePanel($pagetag);

        return match ($shape) {
            'accordion-item' => "\n</div>\n</details> <!-- end of panel -->\n",
            'collapsible-panel' => "\n</div>\n</details>\n</div> <!-- end of panel -->\n",
            default => "\n</div>\n</div> <!-- end of panel -->\n",
        };
    }
}
