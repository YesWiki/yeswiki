<?php

namespace YesWiki\Content\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\RuntimeConfig;

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

        $output = '';
        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->wiki->Header());
        $output .= $header[0] . '<body>' . "\n";
        $output .= '<main id="canvas-qrcodetroc" data-form="' . htmlspecialchars($form) . '" data-formuser="' . htmlspecialchars($formuser) . '" data-relation="' . htmlspecialchars($relation) . '" data-refresh="' . htmlspecialchars($refresh) . '"></main>';
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/vendor/p5.min.js');
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/qrcodetroc-visualisation.js');

        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->wiki->Footer());

        return $output;
    }
}
