<?php

namespace YesWiki\Render\Service;

use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

/** A part of the screen a writer opens in the editor with a double-click, or with a pencil on a touch screen (end-users 27). */
class EditZone
{
    public function __construct(
        private readonly AclService $acls,
        private readonly AuthenticationService $authentication,
        private readonly PageContext $pageContext,
        private readonly UrlFormatter $urls,
        private readonly RuntimeConfig $config,
    ) {
    }

    /** The editor that zone opens, or null when the user may not write it, turned double-click editing off, or the wiki allows it only on the page itself. */
    public function href(string $tag = '', bool $iframe = false, bool $included = false): ?string
    {
        $tag = $tag === '' ? (string)$this->pageContext->getTag() : $tag;
        if ($this->pageContext->getMethod() !== 'show' || !$this->acls->hasAccess('write', $tag)) {
            return null;
        }
        $user = $this->authentication->getLoggedUser();
        if (is_array($user) && strtoupper((string)($user['doubleclickedit'] ?? 'Y')) === 'N') {
            return null;
        }
        if ($included && !in_array($this->config['allow_doubleclic'] ?? false, ['1', 'yes', 'true', true, 1], true)) {
            return null;
        }

        return $this->urls->href($iframe ? 'editiframe' : 'edit', $tag, null, false);
    }

    /** The attributes that make an element an edit zone, or '' when it is not one. */
    public function attributes(string $tag = '', bool $iframe = false, bool $included = false): string
    {
        $href = $this->href($tag, $iframe, $included);
        if ($href === null) {
            return '';
        }
        $tag = $tag === '' ? (string)$this->pageContext->getTag() : $tag;

        return ' data-yw-edit-href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
            . ' data-yw-edit-label="' . htmlspecialchars(_t('EDIT_ZONE_LABEL', ['tag' => $tag]), ENT_QUOTES) . '"';
    }
}
