<?php

use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\YesWikiAction;

/**
 * valeur : permet d'extraire le contenu d'une valeur de fiche bazar à partir d'une url.
 */
class ValeurAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $url = $arg['url'] ?? '';
        if (empty($url)) {
            $url = $this->wiki->GetConfigValue('source_url');
        }

        return [
            'url' => $url,
            'champ' => $arg['champ'] ?? '',
            'image' => $arg['image'] ?? '',
            'texte' => $arg['texte'] ?? '',
            'defaut' => $arg['defaut'] ?? '',
        ];
    }

    public function run(): string
    {
        $url = $this->arguments['url'];
        if (empty($url)) {
            return $this->renderError(_t('BAZAR_PARAM_URL_REQUIRED'));
        }

        $champ = $this->arguments['champ'];
        if (empty($champ)) {
            return $this->renderError(_t('BAZAR_PARAM_CHAMP_REQUIRED'));
        }
        $image = $this->arguments['image'];
        $texte = $this->arguments['texte'];

        // on garde en variable globale pour le cas ou l'action est appelée plusieurs fois
        if (!isset($GLOBALS['externalpage'][$url])) {
            $GLOBALS['externalpage'][$url] = $this->fetch($url . '/html');
        }
        $remotePage = $GLOBALS['externalpage'][$url];

        if ($remotePage === false) {
            return $this->renderError(_t('BAZAR_URL_ERROR') . ' : ' . htmlspecialchars($url) . '.');
        }

        // le titre est un cas particulier
        if ($champ == 'bf_titre') {
            $regexp = '/<h1 class="BAZ_fiche_titre">(.*)<\/h1>/Uis';
        } elseif ($champ == 'id_fiche') {
            // l'id est un cas particulier
            $urlparsed = parse_url($url);

            return htmlspecialchars(preg_replace('/(.*?)wiki=(.*?)/Ui', '$2', $urlparsed['query'] ?? ''));
        } elseif (!empty($image) && in_array($image, ['lien', '1'], true)) {
            // cas des images
            $regexp = '/<a data-id="' . $champ . '".*href="(.*)".*>\s*<img.*<\/a>/Uis';
        } else {
            $regexp = '/<div.*data-id="' . $champ . '".*>\s*<span class="BAZ_label.*">.*<\/span>\s*<span class="BAZ_texte">\s*(.*)\s*<\/span>\s*<\/div> <!-- \/.BAZ_rubrique -->/Uis';
        }

        preg_match_all($regexp, $remotePage, $matches);

        if (empty($matches[1])) {
            return $this->arguments['defaut'];
        }

        // the remote page is not trusted: purify/escape whatever it returned before rendering it
        $htmlPurifierService = $this->getService(HtmlPurifierService::class);

        if (!empty($texte) && $texte != 'lien') {
            $fragment = $htmlPurifierService->cleanHTML(trim(array_shift($matches[1])));

            return preg_replace('/<a.*href="(.*)".*>.*<\/a>/Ui', '<a href="$1">' . htmlspecialchars(trim($texte)) . '</a>', $fragment);
        }

        if (!empty($texte) && $texte == 'lien') {
            $fragment = $htmlPurifierService->cleanHTML(array_shift($matches[1]));
            $link = preg_replace('/<a.*href="(.*)".*>.*<\/a>/Ui', '$1', $fragment);

            return htmlspecialchars($link);
        }

        if ($image == '1') {
            return '<img loading="lazy" class="img-responsive" src="' . htmlspecialchars(array_shift($matches[1])) . '" alt="image ' . htmlspecialchars($champ) . '">';
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
     * A redirect is not followed: curl cannot re-run the check on the new address, and a public
     * page redirecting to an internal one is the usual way past a check made only on the first
     * URL.
     *
     * @return string|false
     */
    private function fetch(string $url)
    {
        try {
            $pin = $this->getService(SsrfUrlValidator::class)->curlPin($url, ['http', 'https']);
        } catch (Throwable $error) {
            return false;
        }

        $curl = curl_init($url);
        foreach ($pin as $option => $optionValue) {
            curl_setopt($curl, $option, $optionValue);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ($content === false || $status < 200 || $status >= 300) ? false : $content;
    }
}
