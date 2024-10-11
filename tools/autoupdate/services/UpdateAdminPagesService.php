<?php

namespace YesWiki\AutoUpdate\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\AutoUpdate\Entity\Messages;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Wiki;

class UpdateAdminPagesService
{
    private $wiki;
    private $linkTracker;
    private $pageManager;
    private $params;

    public function __construct(
        Wiki $wiki,
        LinkTracker $linkTracker,
        PageManager $pageManager,
        ParameterBagInterface $params
    ) {
        $this->wiki = $wiki;
        $this->linkTracker = $linkTracker;
        $this->pageManager = $pageManager;
        $this->params = $params;
    }

    public function updateAll(): Messages
    {
        return $this->update($this->wiki->config['admin_pages_to_update'] ?? []);
    }

    /**
     * method to update admin pages.
     *
     * @param array $adminPagesToUpdate ['BazaR',GererSite', ...]
     *
     * @return Messages messages
     */
    public function update(array $adminPagesToUpdate): Messages
    {
        $messages = new Messages();
        $defaultSQL = file_get_contents('setup/sql/default-content.sql');
        $defaultSQLSplittedByBlock = explode('INSERT INTO', $defaultSQL);
        $blocks = [];
        for ($i = 1; $i < count($defaultSQLSplittedByBlock); $i++) {
            $block = $defaultSQLSplittedByBlock[$i];
            if (
                substr($block, 0, 1) !== '#' &&
                substr($defaultSQLSplittedByBlock[$i - 1], 0, strlen('# YesWiki pages')) === '# YesWiki pages'
            ) { // only working for pages
                $typeBlock = explode('`', substr($block, strlen(' `{{prefix}}')), 2);
                if ($typeBlock[0] == 'pages') {
                    $blocks[] = $typeBlock[1];
                }
            }
        }

        $defaultSQLSplitted = [];
        foreach ($blocks as $block) {
            $splittedBlock = explode("VALUES\n('", $block, 2);
            if (count($splittedBlock) < 2) {
                $splittedBlock = explode("VALUES\r\n('", $block, 2);
                $separator = "\r\n";
            } else {
                $separator = "\n";
            }
            $splittedBlock = explode('),' . $separator . "('", $splittedBlock[1]);
            foreach ($splittedBlock as $extract) {
                $tag = explode('\'', $extract)[0];
                $defaultSQLSplitted[$tag] = $extract;
            }
        }
        $output = '';
        foreach ($adminPagesToUpdate as $page) {
            if (isset($defaultSQLSplitted[$page])) {
                if (preg_match('/' . $page . '\',\s*(?:now\(\))?\s*,\s*\'([\S\s]*)\',\s*\'\'\s*,\s*\'{{WikiName}}\',\s*\'{{WikiName}}\', \'(?:Y|N)\', \'page\', \'\'/U', $defaultSQLSplitted[$page], $matches)) {
                    $pageContent = str_replace('\\"', '"', $matches[1]);
                    $pageContent = str_replace('\\\'', '\'', $pageContent);
                    $pageContent = str_replace('{{rootPage}}', $this->params->get('root_page'), $pageContent);
                    $pageContent = str_replace('{{url}}', $this->params->get('base_url'), $pageContent);
                    if ($this->pageManager->save($page, $pageContent) !== 0) {
                        $output .= (!empty($output) ? ', ' : '') . _t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE') . $page;
                    } else {
                        // save links
                        $this->linkTracker->registerLinks($this->pageManager->getOne($page));
                    }
                }
                $messages->add($page, 'AU_OK');
            } else {
                $messages->add(str_replace('{{page}}', $page, _t('UPDATE_PAGE_NOT_FOUND_IN_DEFAULT_SQL')), 'AU_ERROR');
            }
        }

        return $messages;
    }
}
