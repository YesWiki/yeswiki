<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\QrCodeService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

class QrcodeAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{qrcode}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'qrcode';
    }

    /**
     * One setting, and it is the one the action refuses to run without -- which is exactly why it belongs in the palette rather than being typed by hand: `{{qrcode}}` with no `text` renders an error box.
     */
    public function components(): array
    {
        return [
            Component::for('qrcode')
                ->category(Category::Media)
                ->label(_t('AB_qrcode_label'))
                ->icon('qrcode')
                ->description(_t('AB_qrcode_description'))
                ->previewHeight('250px')
                ->settings(
                    Setting::text('text')
                        ->label(_t('AB_qrcode_text_label'))
                        ->hint(_t('AB_qrcode_text_hint'))
                        ->required()
                        ->full(),
                ),
        ];
    }

    /** @return string */
    public function run()
    {
        $this->arguments['text'] = !empty($this->arguments['text']) ?
            $this->arguments['text'] :
            null;

        if (empty($this->arguments['text'])) {
            return '<div class="yw-alert yw-alert--danger">' . _t('QR_CODE_ERROR_MISSING_PARAM') . '</div>' . "\n";
        }
        $cacheImage = 'cache' . DIRECTORY_SEPARATOR . 'qrcode-' . $this->getService(PageContext::class)->getTag() . '-' . md5($this->arguments['text']) . '.svg';
        $this->getService(QrCodeService::class)->generateToFile($this->arguments['text'], $cacheImage);

        return '<img src="' . $cacheImage . '" alt="' . htmlspecialchars($this->arguments['text']) . '" class="qrcode-img" />' . "\n";
    }
}
