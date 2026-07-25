<?php
/**
 * Qrcode action for yeswiki, for displaying a qrcode image with given text
 *
 * @category Wiki
 * @package  YesWikiQrcode
 * @author   2011  Francois Labastie <flabastie@hotmail.com>
 * @author   2018-2021 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU AFFERO GENERAL PUBLIC LICENSE version 3
 * @link     https://yeswiki.net
 */
use YesWiki\Core\Service\QrCodeService;
use YesWiki\Core\YesWikiAction;

class QrcodeAction extends YesWikiAction
{
    public function run()
    {
        // Lecture des parametres de l'action
        $this->arguments['text'] = !empty($this->arguments['text']) ?
            $this->arguments['text'] :
            null;

        // si pas de texte, on affiche une erreur
        if (empty($this->arguments['text'])) {
            return '<div class="yw-alert yw-alert--danger">'._t('QR_CODE_ERROR_MISSING_PARAM').'</div>'."\n";
        } else {
            $cacheImage = 'cache'.DIRECTORY_SEPARATOR.'qrcode-'.$this->wiki->getPageTag().'-'.md5($this->arguments['text']).'.svg';
            $this->getService(QrCodeService::class)->generateToFile($this->arguments['text'], $cacheImage);
            return '<img src="'.$cacheImage.'" alt="'.htmlspecialchars($this->arguments['text']).'" class="qrcode-img" />'."\n";
        }
    }
}
