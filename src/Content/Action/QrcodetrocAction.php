<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * `{{qrcodetroc}}` -- the QR-code exchange visualisation (ticket 35, was `/PageName/qrcodetroc`).
 *
 * A p5.js canvas that draws the relations between users, configured by `qrcode_config`. It was a
 * handler, which is for ways of *looking at a page*, and this looks at the whole wiki: the page it
 * hung off contributed nothing but a URL to reach it by.
 *
 * ## What changed by moving it
 *
 * The handler rendered a standalone document -- `renderHead()`, then its own `<body>` -- so the
 * canvas filled the window with no wiki chrome around it. An action returns a fragment, so it now
 * appears inside the page's layout like anything else. That is a deliberate trade: an author can
 * put a heading and an explanation around it, and a bare presentation is available by giving the
 * page a minimal squelette, which is configuration since ticket 30 rather than something a
 * handler has to hard-code.
 *
 * ## Parameters
 *
 * Each falls back to `qrcode_config`, and the query string is still honoured in between so a link
 * written against the old handler keeps its meaning when repointed at a page:
 *
 *   form=       the relation form id           (relation_form_id)
 *   formuser=   the user form id               (default_user_form)
 *   relation=   the relation type              (default_relation_type)
 *   refresh=    refresh period, in seconds     (visualisation_refresh_period)
 */
class QrcodetrocAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'qrcodetroc';
    }

    public function run(): string
    {
        // Declared before rendering: ticket 15 emits every asset in the head, so a registration
        // made while the body is being built arrives too late. The handler had the same note --
        // it only ever worked there because the footer was the flush point.
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

    /**
     * The action's own parameter, else the query string, else the configured default.
     */
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
