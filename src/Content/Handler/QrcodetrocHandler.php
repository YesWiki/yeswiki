<?php

namespace YesWiki\Content\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\TemplateEngine;

class QrcodetrocHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/qrcodetroc` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'qrcodetroc';
    }

    public function run()
    {
        // allow to pass get parameters, fallback on default config
        $relation = empty($_GET['relation']) ?
            $this->getService(RuntimeConfig::class)['qrcode_config']['default_relation_type'] :
            $_GET['relation'];
        $formuser = empty($_GET['formuser']) ?
            $this->getService(RuntimeConfig::class)['qrcode_config']['default_user_form'] :
            $_GET['formuser'];
        $refresh = empty($_GET['refresh']) ?
            $this->getService(RuntimeConfig::class)['qrcode_config']['visualisation_refresh_period'] :
            $_GET['refresh'];
        $form = empty($_GET['form']) ?
            $this->getService(RuntimeConfig::class)['qrcode_config']['relation_form_id'] :
            $_GET['form'];

        // declared before the head is rendered: ticket 15 emits every asset there, so a
        // registration made afterwards would arrive too late (these used to sit between
        // header() and footer(), which worked only because the footer was the flush point)
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/vendor/p5.min.js');
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/qrcodetroc-visualisation.js');

        $body = '<main id="canvas-qrcodetroc" data-form="' . htmlspecialchars($form)
            . '" data-formuser="' . htmlspecialchars($formuser)
            . '" data-relation="' . htmlspecialchars($relation)
            . '" data-refresh="' . htmlspecialchars($refresh) . '"></main>';

        return $this->getService(TemplateEngine::class)->renderHead()
            . "<body>\n" . $body . "\n</body>\n</html>";
    }
}
