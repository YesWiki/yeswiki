<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;

/** `/PageName/raw` prints a page's markup; `/PageName/raw&format=json` prints its whole stored body, translations included. */
class RawHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'raw';
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

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        if ($this->getService(AclService::class)->hasAccess('read')) {
            if (!$this->getService(PageContext::class)->getPage()) {
                return;
            }
            $body = $this->getService(PageContext::class)->getPage()['body'];
            if ($this->getRequest()->query->get('format') === 'json') {
                header('Content-type: application/json; charset=' . YW_CHARSET);
                echo PageBody::encode($body);

                return;
            }
            header('Content-type: text/plain; charset=' . YW_CHARSET);

            echo self::text($body);
        } else {
            return;
        }
    }

    /**
     * A page's wiki markup, or an entry's whole body as JSON.
     *
     * @param array<string, mixed> $body
     */
    public static function text(array $body): string
    {
        return array_key_exists(PageBody::CONTENT, $body) ? PageBody::content($body) : PageBody::encode($body);
    }
}
