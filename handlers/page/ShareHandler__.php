<?php

use YesWiki\Core\Service\QrCodeService;
use YesWiki\Core\YesWikiHandler;

class ShareHandler__ extends YesWikiHandler
{
    public function run()
    {
        // creation et affichage QRcode du lien de page
        $url = $this->wiki->Href();
        $cacheImage = 'cache/qrcode-'.$this->wiki->getPageTag().'-url.svg';
        $this->getService(QrCodeService::class)->generateToFile($url, $cacheImage);
        $html = '<img class="right" src="'.$cacheImage.'" title="'._t('QR_CODE_PAGE').'" alt="'.$url.'" />'."\n";

        // Agrégation du QRcode dans le buffer du handler share
        $this->output = preg_replace(
            '/<div class="page">/',
            '<div class="page">'."\n".$html."\n",
            $this->output
        );
    }
}
