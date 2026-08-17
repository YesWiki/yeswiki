<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{linkrss}}` -- converted from the procedural actions/linkrss.php by ticket 06. */
class LinkrssAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'linkrss';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return $this->emitAfter((string)ob_get_clean());
    }

    /** Ran as an after-callback until ticket 06 merged it in. */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        $list = '';

        if ($this->getService(ModuleAclService::class)->checkModuleAcl('rss', 'handler')) {
            foreach ($this->getService(FormManager::class)->getAllLabels() as $formId => $label) {
                $list .= '  <link rel="alternate" type="application/rss+xml" '
                    . 'title="' . htmlspecialchars($label) . '" '
                    . 'href="' . $this->getService(UrlFormatter::class)->href('rss', $this->getService(PageContext::class)->getTag(), 'id=' . $formId) . '">' . "\n";
            }

            echo '  <link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(_t('BAZ_FLUX_RSS_GENERAL')) . '" '
                . 'href="' . $this->getService(UrlFormatter::class)->href('rss') . '">' . "\n" . $list;
        }

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        $displayLastChanges = $this->getService(PageManager::class)->getOne('DerniersChangementsRSS') && $this->getService(AclService::class)->hasAccess('read', 'DerniersChangementsRSS');
        $displayLastComments = $this->getService(PageManager::class)->getOne('DerniersCommentairesRSS') && $this->getService(AclService::class)->hasAccess('read', 'DerniersCommentairesRSS');

        if ($displayLastChanges || $displayLastComments) {
            echo "\n" . '  <!-- RSS links -->' . "\n";
        }
        if ($displayLastChanges) {
            echo '  <link rel="alternate" type="application/rss+xml" title="' . _t('TEMPLATE_RSS_LAST_CHANGES') . '" href="' . $this->getService(UrlFormatter::class)->href('xml', 'DerniersChangementsRSS') . '" />' . "\n";
        }
        if ($displayLastComments) {
            echo '  <link rel="alternate" type="application/rss+xml" title="' . _t('TEMPLATE_RSS_LAST_COMMENTS') . '" href="' . $this->getService(UrlFormatter::class)->href('xml', 'DerniersCommentairesRSS') . '" />' . "\n";
        }
    }
}
