<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

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
                    // `panel-success|info|warning|danger` are gone from what is offered.
                    // They said the same four things a callout says, and two ways to draw
                    // a coloured box is what makes a palette unusable: a **callout carries
                    // meaning, a panel carries styling** (ADR-0017). What is left is the
                    // wiki's own neutral vocabulary, plus a free class for anything else.
                    //
                    // Only the OFFER changes. The classes are in stored pages and yw-core
                    // still styles them, under ADR-0004's legacy-vocabulary rule.
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
        // Titre du panel
        $title = $this->arguments['title'] ?? '';

        // classe css pour la couleur du panel ou autre
        $class = $this->arguments['class'] ?? '';

        // collapsed: initial state is collapsed, and the panel is collapsible
        // collapsible: initial state is displayed, and the panel is collapsible
        // empty: initial state is displayed, and the panel is not collapsible
        $type = $this->arguments['type'] ?? '';
        $pagetag = $this->getService(PageContext::class)->getTag();

        if ($this->check_end_elem('panel')) {
            $headingID = uniqid('heading');
            $collapseID = uniqid('collapse');

            $collapsed = ($type == 'collapsed');
            $collapsible = ($type == 'collapsible') || $collapsed;

            if (isset($GLOBALS['check_' . $pagetag]['accordion_uniqueID'])) {
                $accordionID = $GLOBALS['check_' . $pagetag]['accordion_uniqueID'];
                $collapsible = true;
                if (!isset($GLOBALS['check_' . $pagetag]['accordion_collapsible'])) {
                    // first panel in the accordion starts open, the rest start collapsed
                    $collapsed = false;
                    $GLOBALS['check_' . $pagetag]['accordion_collapsible'] = true;
                } else {
                    $collapsed = true;
                }
            } else {
                $accordionID = '';
            }

            // A collapsible panel is a <details>; a plain one stays a plain box.
            //
            // <details> replaces the whole apparatus this used to emit -- a button, an id on
            // it, a matching id on the region, aria-expanded, aria-controls, role="region"
            // and a listener in yw-core.js keeping them in step. The browser does all of
            // that, including opening the panel when the browser's own find-in-page matches
            // text inside it, which the JS version could not do at all.
            //
            // One behaviour is deliberately lost: panels of an {{accordion}} no longer close
            // each other. `<details name="...">` would restore it natively, but it is too
            // recent to rely on, and "several panels open at once" is a far smaller surprise
            // than a panel that cannot be opened without JavaScript.
            // A panel inside an {{accordion}} IS an accordion item, and wears only the
            // accordion's clothes. Emitting both sets of classes -- which is what this did
            // first -- let the panel chrome win: `.yw-panel__heading` is `display: block`
            // with a grey background, so the chevron and the title landed on separate lines
            // over a grey bar, and the `<h4>` took the theme's h4 colour. A panel *outside*
            // an accordion keeps its own look; it is still a panel.
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
            // remembered so end() closes exactly what run() opened
            $GLOBALS['check_' . $pagetag]['panel_shape'][] = $collapsible
                ? ($inAccordion ? 'accordion-item' : 'collapsible-panel')
                : 'panel';

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
        $shape = array_pop($GLOBALS['check_' . $pagetag]['panel_shape']) ?? 'panel';

        // each shape opened a different number of elements; closing the wrong count leaves
        // the rest of the page nested inside a panel that never ends
        return match ($shape) {
            'accordion-item' => "\n</div>\n</details> <!-- end of panel -->\n",
            'collapsible-panel' => "\n</div>\n</details>\n</div> <!-- end of panel -->\n",
            default => "\n</div>\n</div> <!-- end of panel -->\n",
        };
    }
}
