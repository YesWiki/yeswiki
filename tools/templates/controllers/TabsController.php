<?php

namespace YesWiki\Templates\Controller;

use YesWiki\Bazar\Field\Tabsfield;
use YesWiki\Core\YesWikiController;
use YesWiki\Templates\Service\TabsService;

class TabsController extends YesWikiController
{
    protected $tabsService;

    public function __construct(
        TabsService $tabsService
    ) {
        $this->tabsService = $tabsService;
    }

    /**
     * change tag.
     *
     * @return string $output
     */
    public function changeTab(string $mode = 'action'): string
    {
        $output = "\n{$this->closeATab($mode)}\n";
        $params = $this->getParams($mode, false);
        if ($params['counter'] === false) {
            $output .= $this->closeTabs($mode);
        } else {
            $output .= $this->openATab($mode);
        }

        return $output;
    }

    /**
     * open tabs.
     *
     * @param array|Tabsfield $data
     *
     * @return string $output
     */
    public function openTabs(string $mode, $data): string
    {
        $showFirst = false;
        $selectedtab = 1;
        if ($data instanceof TabsField && in_array($mode, ['view', 'form'])) {
            if ($mode == 'view') {
                $titles = $data->getViewTitles();
                $this->tabsService->setViewTitles($data);
            } else {
                $titles = $data->getFormTitles();
                $this->tabsService->setFormTitles($data);
            }
            $showFirst = true;
        } elseif ($mode === 'action' && is_array($data) && !empty($data['titles'])) {
            $titles = $data['titles'];
            $this->tabsService->setActionTitles($data);
            $selectedtab = $data['selectedtab'] ?? 1;
        } else {
            return '';
        }

        return empty($titles) ? '' : $this->render('@templates/tabs.twig', [
            'titles' => $titles,
            'selectedtab' => $selectedtab,
            'slugs' => $this->tabsService->getSlugs($mode),
        ]) . ($showFirst ? $this->openATab($mode) : '');
    }

    /**
     * close tabs.
     *
     * @return string $output
     */
    public function closeTabs(string $mode = 'action'): string
    {
        $params = $this->getParams($mode, false);
        $output = '';
        if ($params['isClosed'] === true) {
            return '';
        }
        // close not opened tabs
        if ($params['counter'] !== false) {
            for ($i = $params['counter'] - 1; $i < count($params['titles']); $i++) {
                if ($params['tabOpened'] === false) {
                    $output .= "\n    " . $this->openATab($mode);
                }
                $output .= "\n    " . $this->closeATab($mode);
                $params = $this->getParams($mode, false);
            }
        }
        if ($params['counter'] === false && $params['isClosed'] === false) {
            $this->tabsService->registerClose($mode);
            $output .= "\n</div><!-- close all tabs -->";
        }

        return $output;
    }

    /**
     * open a tab.
     *
     * @return string $output
     */
    public function openATab(string $mode = 'action'): string
    {
        $params = $this->getParams($mode, false);
        if ($params['counter'] === false || $params['tabOpened'] === true) {
            return '';
        }
        $this->tabsService->openTab($mode);

        return $this->render('@templates/tab-open.twig', array_merge($params, [
            'selected' => ($params['counter'] == $params['selectedtab']),
        ]));
    }

    /**
     * close a tab.
     *
     * @return string $output
     */
    public function closeATab(string $mode = 'action'): string
    {
        $params = $this->getParams($mode, true);
        if ($params['counter'] === false) {
            return '';
        }

        return $this->render('@templates/tab-close.twig', $params);
    }

    protected function getParams(string $mode, bool $increment = true): array
    {
        return ($mode === 'form')
            ? $this->tabsService->getFormData($increment)
            : (
                ($mode === 'view')
                ? $this->tabsService->getViewData($increment)
                : $this->tabsService->getActionData($increment)
            );
    }
}
