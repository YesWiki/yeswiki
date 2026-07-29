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

/**
 * `{{linkrss}}` -- converted from the procedural actions/linkrss.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
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
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return $this->emitAfter((string)ob_get_clean());
    }

    /**
     * Ran as an after-callback until ticket 06 merged it in. Receives the rendered output
     * as $plugin_output_new -- the name the hooks already used -- because several rewrite
     * it rather than appending.
     */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        // merged from actions/linkrss__.php (ticket 06: core does not hook itself)
        // relocated from tools/bazar/actions/linkrss__.php (ticket 24)
        $forms = $this->wiki->services->get(FormManager::class)->getAll();
        $liste = '';

        if ($this->getService(ModuleAclService::class)->checkModuleAcl('rss', 'handler')) {
            if (is_array($forms) && count($forms) > 0) {
                foreach ($forms as $form) {
                    $liste .= '  <link rel="alternate" type="application/rss+xml" '
                        . 'title="' . htmlspecialchars($form['label'] ?? '') . '" '
                        . 'href="' . $this->getService(UrlFormatter::class)->href('rss', $this->getService(PageContext::class)->getTag(), 'id=' . $form['id']) . '">' . "\n";
                }
            }

            echo '  <link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(_t('BAZ_FLUX_RSS_GENERAL')) . '" '
                . 'href="' . $this->getService(UrlFormatter::class)->href('rss') . '">' . "\n" . $liste;
        }

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        $displayLastChanges = $this->wiki->services->get(PageManager::class)->getOne('DerniersChangementsRSS') && $this->getService(AclService::class)->hasAccess('read', 'DerniersChangementsRSS');
        $displayLastComments = $this->wiki->services->get(PageManager::class)->getOne('DerniersCommentairesRSS') && $this->getService(AclService::class)->hasAccess('read', 'DerniersCommentairesRSS');

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
