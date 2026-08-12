<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateHelperService;

/**
 * `{{include}}` -- converted from the procedural actions/include.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class IncludeAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /**
     * The three phases below were three files sharing one scope: runFileInBuffer()
     * extract()ed the parameter array by reference and fed each file's get_defined_vars()
     * back into it, so the after-callback read what the before-callback and the body had
     * set. Methods have their own scope, so the two values that are computed rather than
     * read straight from a parameter travel as properties instead.
     */
    private string $incPageName = '';
    private string $class = 'include';

    public static function performableName(): string
    {
        return 'include';
    }

    public function components(): array
    {
        return [
            Component::for('include')
                ->category(Category::Writing)
                ->label(_t('AB_advanced_action_include_label'))
                ->icon('copy')
                ->previewHeight('200px')
                ->settings(
                    Setting::page('page')
                        ->label(_t('AB_advanced_action_backlinks_page_label'))
                        ->required(),
                ),
        ];
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emitBefore();
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
     * Ran as a before-callback until ticket 06 merged it in.
     */
    private function emitBefore(): void
    {
        // merged from actions/__include.php (ticket 06: core does not hook itself)
        $pageincluded = $this->getService(PerformableArguments::class)->get('page');

        // if metadata exists to change included page, we take the value of it
        if (isset($this->getService(PageContext::class)->getMetadata()[$pageincluded])) {
            $oldpageincluded = $pageincluded;
            $pageincluded = $this->getService(PageContext::class)->getMetadata()[$pageincluded];
            $this->getService(PerformableArguments::class)->set('page', $pageincluded);
            // to prevent errors in actions order in Performer
            if ($this->getService(PageContext::class)->getTag() == trim($oldpageincluded)) { // case /attach/actions/___include before this
                // redo tools\attach\actions\__include.php without changing oldpage
                $this->getService(PageContext::class)->setTag(trim($pageincluded));
                $includedPage = $this->getService(PageManager::class)->getCached($this->getService(PageContext::class)->getTag());
                $this->getService(PageContext::class)->setPage(!empty($includedPage) ? $includedPage : $this->getService(PageManager::class)->getOne($this->getService(PageContext::class)->getTag()));
            }
        }
        $class = $this->getService(PerformableArguments::class)->get('class');
        $this->getService(PerformableArguments::class)->set('class', empty($class) ? 'include' : 'include ' . $class);
        $this->class = $this->getService(PerformableArguments::class)->get('class');

        // Page translation (formerly tools/lang's __include before-callback): filter the
        // included page's body to the visitor's {{lang="xx"}} section and refresh the
        // page cache so the include action below renders the filtered version
        require_once YESWIKI_SOURCE_DIR . '/src/Kernel/lang.functions.php';
        $langIncludedPage = $this->getService(PageManager::class)->getOne(trim($this->getService(PerformableArguments::class)->get('page')));
        if (!empty($langIncludedPage['body'])) {
            $langBody = PageBody::content($langIncludedPage['body']);
            $langFilteredBody = filterBodyByLanguage(
                $langBody,
                $GLOBALS['prefered_language'],
                $this->getService(RuntimeConfig::class)['default_language']
            );
            if ($langFilteredBody !== $langBody) {
                $langIncludedPage['body'][PageBody::CONTENT] = $langFilteredBody;
                // Hack : mise a jour du cache avec la nouvelle version.
                $this->getService(PageManager::class)->cache($langIncludedPage);
            }
        }
    }

    /**
     * Ran as an after-callback until ticket 06 merged it in. Receives the rendered output
     * as $plugin_output_new -- the name the hooks already used -- because several rewrite
     * it rather than appending.
     */
    private function emitAfter(string $plugin_output_new): string
    {
        ob_start();

        $incPageName = $this->incPageName;
        $class = $this->class;
        // parameters, so still readable the way the shared scope exposed them -- including
        // $oldpage, which a procedural before-callback (attach's, historically) publishes by
        // defining it, since those land in the shared PerformableArguments
        $active = $this->getService(PerformableArguments::class)->get('active');
        $doubleClick = $this->getService(PerformableArguments::class)->get('doubleclick');
        $clear = $this->getService(PerformableArguments::class)->get('clear');
        $oldpage = $this->getService(PerformableArguments::class)->get('oldpage');

        // merged from actions/include__.php (ticket 06: core does not hook itself)
        // relocated from tools/bazar/actions/include__.php (ticket 24): if the included page is a
        // bazar entry, show the entry instead of formatting the page as plain wiki content.
        $entryManager = $this->getService(EntryManager::class);
        if ($entryManager->isEntry($incPageName)) {
            $plugin_output_new = '<div class="' . $class . '">' . "\n" . renderEntryView(0, $incPageName) . "\n" . '</div>' . "\n";
        } else {
            $type = '';
        }

        // si la page inclue n'existe pas, on propose de la créer
        if (!$incPage = $this->getService(PageManager::class)->getOne($incPageName)) {
            $plugin_output_new = $this->getService(LinkRenderer::class)->linkTo($incPageName);

            return $plugin_output_new . (string)ob_get_clean();
        }

        // si le lien correspond à l'url, on rajoute une classe "active-link"
        if (!empty($active) && $active == '1') {
            $page_active = $this->getService(PageContext::class)->getTag();
            if ($oldpage != '') {
                // si utilisation de l'extension attach
                $page_active = $oldpage;
            }
            // d'abord les liens avec des attributs class
            $plugin_output_new = (string)preg_replace(
                '~<a href="' . preg_quote($this->getService(RuntimeConfig::class)['base_url'] . $page_active, '~') . '" class="(.*)"~Ui',
                '<a class="active-link $1" href="' . $this->getService(RuntimeConfig::class)['base_url'] . $page_active . '"',
                $plugin_output_new
            );

            // ensuite les liens restants (ceux avec une classe avant ne sont pas pris en compte)
            $plugin_output_new = $this->getService(TemplateHelperService::class)->strIreplacement(
                '<a href="' . $this->getService(RuntimeConfig::class)['base_url'] . $page_active . '"',
                '<a class="active-link" href="' . $this->getService(RuntimeConfig::class)['base_url'] . $page_active . '"',
                $plugin_output_new
            );
        }

        // rajoute le javascript pour le double clic si la configuration l'autorise, si le parametre est activé et les droits en écriture existent
        if (
            !empty($this->getService(RuntimeConfig::class)['allow_doubleclic']) && in_array($this->getService(RuntimeConfig::class)['allow_doubleclic'], ['1', 'yes', true])
            && !empty($doubleClick) && $doubleClick == '1' && $this->getService(AclService::class)->hasAccess('write', $incPageName)
        ) {
            $doubleClickAttribute = ' ondblclick="document.location=\'' . $this->getService(UrlFormatter::class)->href('edit', $incPageName) . '\';"';
        } else {
            $doubleClickAttribute = '';
        }
        $plugin_output_new = str_replace('<div class="include ', '<div' . $doubleClickAttribute . ' class="', $plugin_output_new);

        // on enleve le préfixe include_ des classes pour que le parametre passé
        // et le nom de classe CSS soient bien identiques
        $plugin_output_new = str_replace('include_', '', $plugin_output_new);

        // a page included as the top menu gets the nav classes and its nested lists turned
        // into dropdowns. Asked for by class rather than by page name since ticket 30: the
        // squelette's own navbar is `layout_*` configuration now and no longer comes through
        // here, so `PageMenuHaut` is just a page someone may still be including themselves.
        if (strstr($class, 'topnavpage') && !strstr($class, 'horizontal-dropdown-menu')) {
            $plugin_output_new = (string)preg_replace('/\<ul\>/Ui', '<ul class="yw-nav">', $plugin_output_new, 1);

            // TODO: a faire pour toutes les pages ou juste le menu???
            if (YW_CHARSET != 'ISO-8859-1' && YW_CHARSET != 'ISO-8859-15') {
                // tip to replace mb_convert_encoding($plugin_output_new, 'HTML-ENTITIES', 'UTF-8')
                // from https://stackoverflow.com/questions/37215388/what-is-a-replacement-for-mb-convert-encodingstring-utf-8-html-entities
                $plugin_output_new = (string)preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
                    $char = current($m);
                    $utf = iconv('UTF-8', 'UCS-4', $char);

                    return sprintf('&#x%s;', ltrim(strtoupper(bin2hex($utf)), '0'));
                }, $plugin_output_new);
            }

            $dom = new \DOMDocument();
            @$dom->loadHTML($plugin_output_new);
            $xpath = new \DOMXPath($dom);

            // DOMXPath::query() returns false on a malformed expression, and a DOMNodeList
            // yields DOMNode -- only a DOMElement has setAttribute(). Both were assumed
            // rather than checked; a null parentNode was too.
            foreach ($this->elementsMatching($xpath, '*/div/ul/li/ul') as $element) {
                $element->setAttribute('class', 'yw-dropdown__menu');
                if ($element->parentNode instanceof \DOMElement) {
                    $element->parentNode->setAttribute('class', 'yw-dropdown');
                }
            }

            foreach ($this->elementsMatching($xpath, '*/div/ul//li/ul/..') as $element) {
                foreach (iterator_to_array($element->childNodes) as $node) {
                    // we search for #text child or a link, if we accessed the dropdown menu, we break
                    if ($node->nodeName == 'ul') {
                        break;
                    }

                    // we add trigger for dropdown
                    if ($node->nodeName == 'a' && $node instanceof \DOMElement) {
                        $node->setAttribute('data-yw-dropdown-toggle', '');
                        $caret = $dom->createElement('b');
                        $caret->setAttribute('class', 'yw-dropdown__caret');
                        $node->appendChild($caret);
                        continue;
                    }

                    // a bare text label, which needs the <a> built around it. Was
                    // `!trim($node->nodeValue) == ''`, which reads as "(not the text) equals
                    // empty string" and happened to be true for non-empty text -- right
                    // answer, unreadable reason.
                    $label = trim((string)$node->nodeValue);
                    if ($node->nodeName == '#text' && $label !== '' && $node->parentNode !== null) {
                        $a = $dom->createElement('a');
                        $a->setAttribute('data-yw-dropdown-toggle', '');
                        $a->setAttribute('href', '#');
                        $a->nodeValue = $label;
                        $node->nodeValue = '';
                        $caret = $dom->createElement('b');
                        $caret->setAttribute('class', 'yw-dropdown__caret');
                        $a->appendChild($caret);
                        $node->parentNode->insertBefore($a, $node);
                    }
                }
            }

            foreach ($this->elementsMatching($xpath, "//a[contains(@class, 'active-link')]") as $activelink) {
                if ($activelink->parentNode instanceof \DOMElement) {
                    $class = $activelink->parentNode->getAttribute('class');
                    $activelink->parentNode->setAttribute('class', $class . ' active');
                }
            }

            $plugin_output_new = (string)preg_replace(
                '/^<!DOCTYPE.+?>/',
                '',
                str_replace(
                    ['<html>', '</html>', '<body>', '</body>'],
                    '',
                    (string)$dom->saveHTML()
                )
            ) . "\n";
        } elseif (strstr($class, 'menu-unstyled')) {
            // add style to remove bullets on all ul
            $plugin_output_new = (string)preg_replace('/\<ul\>/Ui', '<ul class="yw-list-unstyled">', $plugin_output_new);

            // remove list-unstyled class for level 2 ul
            $plugin_output_new = (string)preg_replace('/\<\/a>\s+<ul class="yw-list-unstyled">/Ui', "</a>\n<ul>", $plugin_output_new);
        }

        // on rajoute une div clear pour mettre le flow css en dessous des éléments flottants
        $plugin_output_new = (!empty($clear) && $clear == '1') ?
            $plugin_output_new . '<div class="clearfix"></div>' . "\n" :
            $plugin_output_new;

        $plugin_output_new = $this->getService(TemplateHelperService::class)->postFormat($plugin_output_new);

        return $plugin_output_new . (string)ob_get_clean();
    }

    private function emit(): void
    {
        /* Paramétres :
         -- page : nom wiki de la page a inclure (obligatoire)
         -- class : nom de la classe de style é inclure (facultatif)
         -- auth : option d'affichage dans le cas d'un utilisateur non autorisé (facultatif)
            -- par défaut : ne fait rien
            -- valeur "noError" : n'affiche aucun message d'erreur
         -- edit : option d'accés en édition é la page incluse (facultatif)
            -- par défaut : ne fait rien
            -- valeur "show" :  ajoute un lien "[édition]" en haut é droite de la boite
        */

        // récuperation du nom de la page é inclure
        $incPageName = $this->incPageName = trim($this->getService(PerformableArguments::class)->get('page'));

        /*
        * @todo améliorer le traitement des classes css
        */
        if ($this->getService(PerformableArguments::class)->get('class')) {
            $array_classes = explode(' ', $this->getService(PerformableArguments::class)->get('class'));
            $classes = '';
            foreach ($array_classes as $c) {
                if ($c && preg_match('`^[A-Za-z0-9-_]+$`', $c)) {
                    $classes .= ($classes ? ' ' : '') . "include_$c";
                }
            }
        }

        // Affichage de la page ou d'un message d'erreur
        //
        if (empty($incPageName)) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . _t('MISSING_PAGE_PARAMETER') . '.</div>' . "\n";
        } elseif ($this->getService(InclusionStack::class)->isIncludedBy($incPageName)) {
            $inclusions = $this->getService(InclusionStack::class)->getAll();
            $pg = strtolower($incPageName); // on l'effectue avant le for sinon il sera recalculé é chaque pas
            $err = '[[' . $pg . ']]';
            for ($i = 0; $inclusions[$i] != $pg; $i++) {
                $err = '[[' . $inclusions[$i] . ']] > ' . $err;
            }
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . _t('IMPOSSIBLE_FOR_THIS_PAGE') . ' ' . $incPageName . ' ' . _t('TO_INCLUDE_ITSELF')
                 . ($i ? ':<br /><strong>' . _t('INCLUSIONS_CHAIN') . '</strong> : ' . $pg . ' > ' . $err : '') . '</div>' . "\n"; // si $i = 0, alors c'est une page qui s'inclut elle-méme directement...
        } elseif (!$this->getService(AclService::class)->hasAccess('read', $incPageName) && $this->getService(PerformableArguments::class)->get('auth') != 'noError') {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> :  ' . _t('READING_OF_INCLUDED_PAGE') . ' ' . $incPageName . ' ' . _t('NOT_ALLOWED') . '.</div>' . "\n";
        } elseif (!$incPage = $this->getService(PageManager::class)->getOne($incPageName)) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR') . ' ' . _t('ACTION') . ' Include</strong> : ' . _t('INCLUDED_PAGE') . ' ' . $incPageName . ' ' . _t('DOESNT_EXIST') . '...</div>' . "\n";
        }
        // Affichage de la page quand il n'y a pas d'erreur
        elseif ($this->getService(AclService::class)->hasAccess('read', $incPageName)) {
            $this->getService(InclusionStack::class)->register($incPageName);
            $output = $this->getService(MarkdownFormatterService::class)->format(PageBody::content($incPage['body']));
            if (isset($classes)) {
                if ($this->getService(PerformableArguments::class)->get('edit') == 'show') {
                    $editLink = '<div class="include_editlink"><a href="' . $this->getService(UrlFormatter::class)->href('edit', $incPageName) . '">[' . _t('EDITION') . "]</a></div>\n";
                } else {
                    $editLink = '';
                }
                // Affichage
                echo '<div class="include ' . $classes . "\">\n" . $editLink . $output . "</div>\n";
            } else {
                echo $output;
            }
            $this->getService(InclusionStack::class)->unregisterLast();
        }
    }

    /**
     * The DOMElements an XPath expression selects, and nothing else.
     *
     * DOMXPath::query() answers `false` for a malformed expression, and a DOMNodeList
     * yields DOMNode -- which has no setAttribute(). Filtering here is what lets the
     * callers above read as the DOM manipulation they are.
     *
     * @return list<\DOMElement>
     */
    private function elementsMatching(\DOMXPath $xpath, string $expression): array
    {
        $found = $xpath->query($expression);
        if ($found === false) {
            return [];
        }

        $elements = [];
        foreach ($found as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
