<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\CoreAssets;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/PageName/render` -- converted from the procedural handlers/page/render.php by ticket 06.
 */
class RenderHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'render';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $output = '<body class="yeswiki-render">' . "\n"
            . '<div class="container">' . "\n"
            . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclick iframe="1"}}') . '>' . "\n";

        $this->getService(PageContext::class)->setPageField('body', [PageBody::CONTENT => strip_tags($_GET['content'])]); // fake Page for actions and handlers, all html is striped

        $output .= $this->getService(MarkdownFormatterService::class)->format(PageBody::content(($this->getService(PageContext::class)->getPage() ?? [])['body']));
        $output .= '</div><!-- end .page-widget -->' . "\n";
        // the wiki's <head> without the theme's chrome, rendered after the content so it
        // carries what that content declared (ticket 15)
        echo $this->getService(TemplateEngine::class)->renderHead()
            . "<body>\n"
            // the `wiki` global, which the skeleton emits through its `page_state` helper
            // and this hand-built document has to emit for itself. Without it every script
            // in here reads `wiki.baseUrl` as undefined and calls `undefined?api/...`:
            // an entry list previewed in the actions builder fetched a page of HTML,
            // failed to parse it as JSON, and showed nothing at all.
            . '<script>' . $this->getService(CoreAssets::class)->pageStateScript() . '</script>' . "\n"
            . $output . "\n</body>\n</html>";
    }
}
