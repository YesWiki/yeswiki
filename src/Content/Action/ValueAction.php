<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\RuntimeConfig;

/**
 * valeur : permet d'extraire le contenu d'une valeur de fiche bazar à partir d'une url.
 */
class ValueAction extends YesWikiAction implements RegisteredAction
{
    /** `{{valeur}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'valeur';
    }

    public function formatArguments($arg)
    {
        $url = $arg['url'] ?? '';
        if (empty($url)) {
            $url = $this->getService(RuntimeConfig::class)->getValue('source_url');
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

        if (!$this->isUrlSafeToFetch($url)) {
            return $this->renderError(_t('BAZAR_URL_ERROR') . ' : ' . htmlspecialchars($url) . '.');
        }

        $champ = $this->arguments['champ'];
        if (empty($champ)) {
            return $this->renderError(_t('BAZAR_PARAM_CHAMP_REQUIRED'));
        }
        $image = $this->arguments['image'];
        $texte = $this->arguments['texte'];

        // on garde en variable globale pour le cas ou l'action est appelée plusieurs fois
        if (!isset($GLOBALS['externalpage'][$url])) {
            $GLOBALS['externalpage'][$url] = @file_get_contents($url . '/html');
        }
        $remotePage = $GLOBALS['externalpage'][$url];

        if ($remotePage === false) {
            return $this->renderError(_t('BAZAR_URL_ERROR') . ' : ' . htmlspecialchars($url) . '.');
        }

        // le titre est un cas particulier
        if ($champ == 'bf_titre') {
            $regexp = '/<h1 class="BAZ_fiche_titre">(.*)<\/h1>/Uis';
        } elseif ($champ == 'tag') {
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
     * SSRF guard: only allow fetching http(s) URLs that resolve to public,
     * non-loopback/private/link-local/reserved addresses (mirrors HttpSignatureService::validateKeyIdUrl).
     */
    private function isUrlSafeToFetch(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Strip IPv6 brackets (e.g. [::1] → ::1)
        $host = trim($parts['host'], '[]');

        // Collect all IPs the host resolves to so every address family is checked.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = [];
            $ipv4 = gethostbyname($host);
            if ($ipv4 !== $host) {
                $ips[] = $ipv4;
            }
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            if (empty($ips)) {
                return false;
            }
        }

        foreach ($ips as $ip) {
            // Rejects 127.x, 10.x, 172.16-31.x, 192.168.x, 169.254.x, 0.x, 240.x, ::1, fc00::/7
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            // fe80::/10 (IPv6 link-local) is not blocked by FILTER_FLAG_NO_RES_RANGE in all PHP versions
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && stripos($ip, 'fe80') === 0) {
                return false;
            }
        }

        return true;
    }
}
