<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;

class QrscannerAction extends YesWikiAction implements RegisteredAction
{
    /** `{{qrscanner}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'qrscanner';
    }

    /** @return string */
    public function run()
    {
        $speak = $this->getService(PerformableArguments::class)->get('speak');
        if ($speak == '0' or $speak == 'false' or $speak == 'no') {
            $speak = 'false';
        } else {
            $speak = 'true';
        }

        $output = '';
        $output .= $this->render('@core/qrscanner.twig', [
            'speak' => $speak,
        ]);

        return $output;
    }
}
