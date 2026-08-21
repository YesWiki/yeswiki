<?php

namespace YesWiki\Admin\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Controller\InstallationController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Entity\Messages;

class UpdateAdminPagesService
{
    private ContainerInterface $container;
    private PageManager $pageManager;
    private ParameterBagInterface $params;

    public function __construct(
        ContainerInterface $container,
        PageManager $pageManager,
        ParameterBagInterface $params
    ) {
        $this->container = $container;
        $this->pageManager = $pageManager;
        $this->params = $params;
    }

    public function updateAll(): Messages
    {
        return $this->update($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['admin_pages_to_update'] ?? []);
    }

    /**
     * method to update admin pages.
     *
     * @param list<string> $adminPagesToUpdate ['BazaR', 'GererSite', ...]
     *
     * @return Messages messages
     */
    public function update(array $adminPagesToUpdate): Messages
    {
        $messages = new Messages();
        $defaultSQL = ltrim(InstallationController::renderSqlTemplate(
            YESWIKI_PROGRAM_DIR . '/templates/installation-default-content.sql.twig',
            ['driver' => 'mysql']
        ));
        $defaultSQLSplittedByBlock = explode('INSERT INTO', $defaultSQL);
        $blocks = [];
        for ($i = 1; $i < count($defaultSQLSplittedByBlock); $i++) {
            $block = $defaultSQLSplittedByBlock[$i];
            if (
                substr($block, 0, 1) !== '#'
                && substr($defaultSQLSplittedByBlock[$i - 1], 0, strlen('# YesWiki pages')) === '# YesWiki pages'
            ) {
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
        $rootPage = $this->params->get('root_page');
        $rootPage = is_scalar($rootPage) ? (string)$rootPage : '';
        $baseUrl = $this->params->get('base_url');
        $baseUrl = is_scalar($baseUrl) ? (string)$baseUrl : '';

        $output = '';
        foreach ($adminPagesToUpdate as $page) {
            if (isset($defaultSQLSplitted[$page])) {
                if (preg_match('/' . $page . '\',\s*(?:now\(\))?\s*,\s*\'([\S\s]*)\',\s*\'\'\s*,\s*\'{{WikiName}}\',\s*\'{{WikiName}}\', \'(?:Y|N)\', \'page\', \'\'/U', $defaultSQLSplitted[$page], $matches)) {
                    $pageContent = str_replace('\\"', '"', $matches[1]);
                    $pageContent = str_replace('\\\'', '\'', $pageContent);
                    $pageContent = str_replace('{{rootPage}}', $rootPage, $pageContent);
                    $pageContent = str_replace('{{url}}', $baseUrl, $pageContent);
                    if ($this->pageManager->save($page, [PageBody::CONTENT => $pageContent]) !== 0) {
                        $output .= (!empty($output) ? ', ' : '') . _t('NO_RIGHT_TO_WRITE_IN_THIS_PAGE') . $page;
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
