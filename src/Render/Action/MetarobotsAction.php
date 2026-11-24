<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\PageLayout;
use YesWiki\Render\Service\TemplateHelperService;

/** `{{metarobots}}` -- converted from the procedural actions/metarobots.php by ticket 06. */
class MetarobotsAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'metarobots';
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
        $context = $this->getService(PageContext::class);
        $config = $this->getService(RuntimeConfig::class);
        if ($context->getMethod() != 'show' || empty($context->getPage())) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";

            return;
        }
        if (isset($config['meta']['robots'])) {
            echo '<meta name="robots" content="' . self::attribute((string)$config['meta']['robots']) . '">' . "\n";
        }

        $urlFormatter = $this->getService(UrlFormatter::class);
        $url = $urlFormatter->href('', $context->getTag());
        echo '<link rel="canonical" href="' . self::attribute($url) . '">' . "\n";

        $own = $this->getService(PageManager::class)->getMetadata($context->getTag()) ?? [];
        $utils = $this->getService(TemplateHelperService::class);
        $siteName = (string)$config['yeswiki_name'];
        $bodyTitle = $utils->getTitleFromBody($context->getPage());
        $title = self::ownText($own, PageLayout::OG_TITLE) ?? (!empty($bodyTitle) ? $bodyTitle : $siteName);
        $description = self::ownText($own, PageLayout::OG_DESCRIPTION) ?? $utils->getDescriptionFromBody($context->getPage(), $bodyTitle);

        echo "\n" . '  <!-- opengraph -->' . "\n";
        echo '  <meta property="og:site_name" content="' . self::attribute($siteName) . '" />' . "\n";
        echo '  <meta property="og:title" content="' . self::attribute($title) . '" />' . "\n";
        if ($description !== '') {
            echo '  <meta property="og:description" content="' . self::attribute($description) . '" />' . "\n";
        }
        echo '  <meta property="og:type" content="article" />' . "\n";
        echo '  <meta property="og:url" content="' . self::attribute($url) . '" />' . "\n";

        $w = 1200;
        $h = 630;
        $ownImage = self::ownText($own, PageLayout::OG_IMAGE);
        $img = match (true) {
            $ownImage === null => $utils->getImageFromBody($context->getPage(), strval($w), strval($h)),
            PageLayout::isHttpsUrl($ownImage) => $ownImage,
            default => $urlFormatter->href('', 'api/files/' . rawurlencode($ownImage) . '/download'),
        };
        if (!empty($img)) {
            echo '  <meta property="og:image" content="' . self::attribute($img) . '" />' . "\n";
            if ($ownImage === null) {
                echo '  <meta property="og:image:width" content="' . $w . '" />' . "\n";
                echo '  <meta property="og:image:height" content="' . $h . '" />' . "\n";
            }
        }
    }

    /** @param array<mixed> $metadata */
    private static function ownText(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function attribute(string $value): string
    {
        return htmlspecialchars(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
