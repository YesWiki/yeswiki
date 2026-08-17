<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class QrCardscanAction extends YesWikiAction implements RegisteredAction
{
    /** `{{qrcardscan}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'qrcardscan';
    }

    public function formatArguments($args): array
    {
        return [
            'speak' => (empty($args['speak']) || $args['speak'] == '0' || $args['speak'] == 'false' || $args['speak'] == 'no') ? 'false' : 'true',
        ];
    }

    public function run(): string
    {
        $output = '';
        $output .= $this->render('@core/qrcardscan.twig', [
            'speak' => $this->arguments['speak'],
        ]);

        return $output;
    }
}
