<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\RuntimeConfig;

/** `{{qrcodetroc}}` -- the QR-code exchange visualisation (ticket 35, was `/PageName/qrcodetroc`). */
class QrcodetrocAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'qrcodetroc';
    }

    public function run(): string
    {
        $assets = $this->getService(AssetRegistry::class);
        $assets->addJsFile('javascripts/vendor/p5.min.js');
        $assets->addJsFile('javascripts/qrcodetroc-visualisation.js');

        $config = $this->getService(RuntimeConfig::class)['qrcode_config'] ?? [];
        $attributes = [
            'form' => $this->setting('form', is_array($config) ? ($config['relation_form_id'] ?? '') : ''),
            'formuser' => $this->setting('formuser', is_array($config) ? ($config['default_user_form'] ?? '') : ''),
            'relation' => $this->setting('relation', is_array($config) ? ($config['default_relation_type'] ?? '') : ''),
            'refresh' => $this->setting('refresh', is_array($config) ? ($config['visualisation_refresh_period'] ?? '') : ''),
        ];

        $rendered = '';
        foreach ($attributes as $name => $value) {
            $rendered .= ' data-' . $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES) . '"';
        }

        return '<main id="canvas-qrcodetroc"' . $rendered . '></main>';
    }

    /** The action's own parameter, else the query string, else the configured default. */
    private function setting(string $name, mixed $default): string
    {
        $fromAction = $this->arguments[$name] ?? '';
        if (!empty($fromAction)) {
            return (string)$fromAction;
        }

        $fromQuery = $this->getRequest()->query->get($name, '');

        return !empty($fromQuery) ? (string)$fromQuery : (string)$default;
    }
}
