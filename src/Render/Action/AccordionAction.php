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

    /**
     * @return string opening markup for the accordion, or the unclosed-element error message
     */
    public function run()
    {
        $class = $this->arguments['class'] ?? '';
        $pagetag = $this->getService(PageContext::class)->getTag();

        if (!$this->check_end_elem('accordion')) {
            return $this->generate_error_msg('accordion');
        }

        $accordionID = uniqid('accordion_');
        $this->getService(GraphicalElementState::class)->openAccordion($pagetag, $accordionID);

        return '<!-- start of accordion -->' . "\n"
            . '<div class="yw-accordion ' . $class . '" id="' . $accordionID . '">';
    }

    public function end(): string
    {
        $pagetag = $this->getService(PageContext::class)->getTag();
        $this->getService(GraphicalElementState::class)->closeAccordion($pagetag);

        return "\n</div> <!-- end of accordion -->\n";
    }
}
