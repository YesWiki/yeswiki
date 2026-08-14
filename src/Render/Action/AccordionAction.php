<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

class AccordionAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{accordion}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'accordion';
    }

    public function components(): array
    {
        return [
            Component::for('accordion')
                ->category(Category::Writing)
                ->label(_t('AB_template_action_accordion_label'))
                ->icon('list-details')
                ->previewHeight('300px')
                ->wraps(_t('AB_template_action_accordion_example'))
                ->addOnly()
                ->settings(
                    Setting::number('nb')
                        ->label('Nombre d\'encadrés dans l\'accordéon')
                        ->suggests(2)
                        ->notWritten(),
                ),
        ];
    }

    public function run()
    {
        ob_start();
        $class = $this->arguments['class'] ?? '';
        $pagetag = $this->getService(PageContext::class)->getTag();

        if ($this->check_end_elem('accordion')) {
            // interpolated into the markup below whether or not the inner branch ran, so an
            // accordion reaching here without one rendered `id=""` and a warning (ticket 40)
            $accordionID = '';
            if ($GLOBALS['check_' . $pagetag]['accordion']) {
                $accordionID = uniqid('accordion_');
                $GLOBALS['check_' . $pagetag]['accordion_uniqueID'] = $accordionID;
            }
            // The id is still emitted so a page can link or style one accordion, but the
            // panels no longer read it: <details> needs no coordination to open and close
            // (see PanelAction). Its one cost is that panels no longer close each other.
            echo '<!-- start of accordion -->' . "\n" .
            "<div class=\"yw-accordion $class\" id=\"$accordionID\">";
        } else {
            echo $this->generate_error_msg('accordion');
        }
        $accordion = ob_get_contents();
        ob_end_clean();

        return $accordion;
    }

    public function end(): string
    {
        $pagetag = $this->getService(PageContext::class)->getTag();
        unset($GLOBALS['check_' . $pagetag]['accordion_uniqueID']);

        return "\n</div> <!-- end of accordion -->\n";
    }
}
