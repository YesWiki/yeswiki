<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\PageContext;

/**
 * External-referrer logging for page views (historic Wiki::LogReferrer()/PurgeReferrers()),
 * backed by the `{prefix}referrers` table.
 */
class ReferrerService
{
    protected DbService $dbService;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;
    protected PageContext $pageContext;

    public function __construct(
        DbService $dbService,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        PageContext $pageContext
    ) {
        $this->dbService = $dbService;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->pageContext = $pageContext;
    }

    /**
     * Record the HTTP referrer of the current request when it comes from another site.
     * Only http(s) referrers are kept (XSS guard against javascript: and friends).
     */
    public function log(string $tag = '', string $referrer = ''): void
    {
        // fill values
        if (!$tag = trim($tag)) {
            $tag = $this->pageContext->getTag();
        }

        if (!$referrer = trim($referrer) and isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        // check if it's coming from another site
        $baseUrl = $this->params->get('base_url');
        $baseUrl = is_scalar($baseUrl) ? (string)$baseUrl : '';
        if ($referrer && !preg_match('/^' . preg_quote($baseUrl, '/') . '/', $referrer)) {
            // avoid XSS (with urls like "javascript:alert()" and co)
            // by forcing http/https prefix
            // NB.: this does NOT exempt to htmlspecialchars() the collected URIs !
            if (!preg_match('`^https?://`', $referrer)) {
                return;
            }

            $this->dbService->query(
                'INSERT INTO ' . $this->dbService->prefixTable('referrers')
                . " (page_tag, referrer, time) VALUES ('" . $this->dbService->escape($tag) . "', '"
                . $this->dbService->escape($referrer) . "', " . $this->dbService->now() . ')'
            );
        }
    }

    /** Drop referrers older than the configured `referrers_purge_time` (in days). */
    public function purge(): void
    {
        $days = $this->params->has('referrers_purge_time') ? $this->params->get('referrers_purge_time') : null;
        $days = is_scalar($days) ? intval($days) : 0;
        if ($days && !$this->hibernationService->isWikiHibernated()) {
            $this->dbService->query(
                'delete from ' . $this->dbService->prefixTable('referrers')
                . ' where time < ' . $this->dbService->dateSubDays($days)
            );
        }
    }
}
