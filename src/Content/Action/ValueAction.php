<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\RemotePageCache;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\SsrfUrlValidator;

/** valeur : permet d'extraire le contenu d'une valeur de fiche bazar à partir d'une url. */
class ValueAction extends YesWikiAction implements RegisteredAction
{
    /** `{{value}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'value';
    }

    public function formatArguments($arg)
    {
        $url = $arg['url'] ?? '';
        if (empty($url)) {
            $url = $this->getService(RuntimeConfig::class)->getValue('source_url');
        }

        return [
            'url' => $url,
            'field' => $arg['field'] ?? '',
            'image' => $arg['image'] ?? '',
            'text' => $arg['text'] ?? '',
            'default' => $arg['default'] ?? '',
        ];
    }

    public function run(): string
    {
        $url = $this->arguments['url'];
        if (empty($url)) {
            return $this->renderError(_t('BAZAR_PARAM_URL_REQUIRED'));
        }

        $field = $this->arguments['field'];
        if (empty($field)) {
            return $this->renderError(_t('BAZAR_PARAM_CHAMP_REQUIRED'));
        }
        $image = $this->arguments['image'];
        $text = $this->arguments['text'];

        $remotePage = $this->getService(RemotePageCache::class)->get(
            $url,
            fn () => $this->fetch($url . '/html')
        );

        if ($remotePage === false) {
            return $this->renderError(_t('BAZAR_URL_ERROR') . ' : ' . htmlspecialchars($url) . '.');
        }

        if ($field == 'bf_titre') {
            $regexp = '/<h1 class="BAZ_fiche_titre">(.*)<\/h1>/Uis';
        } elseif ($field == 'tag') {
            $urlparsed = parse_url($url);

            return htmlspecialchars((string)preg_replace('/(.*?)wiki=(.*?)/Ui', '$2', $urlparsed['query'] ?? ''));
        } elseif (!empty($image) && in_array($image, ['lien', '1'], true)) {
            $regexp = '/<a data-id="' . preg_quote($field, '/') . '".*href="(.*)".*>\s*<img.*<\/a>/Uis';
        } else {
            $regexp = '/<div[^>]*data-id="' . preg_quote($field, '/') . '"[^>]*>\s*(?:<span class="BAZ_label[^"]*">.*<\/span>\s*)?'
                . '<(?:span|div) class="BAZ_texte[^"]*">\s*(.*)\s*<\/(?:span|div)>\s*<\/div>/Uis';
        }

        preg_match_all($regexp, $remotePage, $matches);

        if (empty($matches[1])) {
            return $this->arguments['default'];
        }

        $htmlPurifierService = $this->getService(HtmlPurifierService::class);

        if (!empty($text) && $text != 'lien') {
            $fragment = $htmlPurifierService->cleanHTML(trim(array_shift($matches[1])));

            return (string)preg_replace('/<a.*href="(.*)".*>.*<\/a>/Ui', '<a href="$1">' . htmlspecialchars(trim($text)) . '</a>', $fragment);
        }

        if (!empty($text) && $text == 'lien') {
            $fragment = $htmlPurifierService->cleanHTML(array_shift($matches[1]));
            $link = (string)preg_replace('/<a.*href="(.*)".*>.*<\/a>/Ui', '$1', $fragment);

            return htmlspecialchars($link);
        }

        if ($image == '1') {
            return '<img loading="lazy" class="img-responsive" src="' . htmlspecialchars(array_shift($matches[1])) . '" alt="image ' . htmlspecialchars($field) . '">';
        }

        return $htmlPurifierService->cleanHTML(trim(array_shift($matches[1])));
    }

    private function renderError(string $message): string
    {
        return '<div class="alert alert-danger alert-error"><strong>' . _t('BAZAR_ACTION_VALEUR') . '</strong> : ' . $message . '</div>' . "\n";
    }

    /**
     * Reads a remote page, once its address has been checked and pinned.
     *
     * A redirect is not followed: curl cannot re-run the check on the new address, and a public page redirecting to an internal one is the usual way past a check made only on the first URL.
     *
     * @return string|false
     */
    private function fetch(string $url)
    {
        try {
            $pin = $this->getService(SsrfUrlValidator::class)->curlPin($url, ['http', 'https']);
        } catch (\Throwable $error) {
            return false;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return false;
        }
        foreach ($pin as $option => $optionValue) {
            curl_setopt($curl, $option, $optionValue);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return is_string($content) && $status >= 200 && $status < 300 ? $content : false;
    }
}
