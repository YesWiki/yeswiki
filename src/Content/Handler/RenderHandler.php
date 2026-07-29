<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
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
            . '<div class="yeswiki-page-widget page-widget page" ' . $this->getService(MarkdownFormatterService::class)->format('{{doubleclic iframe="1"}}') . '>' . "\n";

        $this->getService(PageContext::class)->setPageField('body', [PageBody::CONTENT => strip_tags($_GET['content'])]); // fake Page for actions and handlers, all html is striped

        $output .= $this->getService(MarkdownFormatterService::class)->format(PageBody::content(($this->getService(PageContext::class)->getPage() ?? [])['body']));
        $output .= '</div><!-- end .page-widget -->' . "\n";
        // ajout des en-têtes en pieds de page

        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->getService(TemplateEngine::class)->header());
        $output = $header[0] . $output;
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', '<script', $this->getService(TemplateEngine::class)->footer());
        echo $output;
    }
}
