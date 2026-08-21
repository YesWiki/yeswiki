<?php

use YesWiki\Bazar\Service\EntryChecker;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\YesWikiAction;

class CheckcontentAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $post = $request->request->all();
        $selected = $post['checkcontent-repair'] ?? [];
        $picked = $post['checkcontent-value'] ?? [];

        return [
            'id' => $request->get('id_typeannonce') ?? $arg['id'] ?? $arg['idtypeannonce'] ?? '',
            'repair' => is_array($selected) ? array_values(array_filter($selected, 'is_string')) : [],
            'pickedValues' => is_array($picked) ? $picked : [],
            'textreplace' => is_scalar($arg['textreplace'] ?? null)
                ? strval($arg['textreplace'])
                : _t('BAZ_CHECKCONTENT_TEXT_REPLACEMENT'),
            'forcevalues' => $this->forcedValues($arg['forcevalues'] ?? null),
            'params' => $request->query->has('debug') ? ['debug' => 'yes'] : [],
        ];
    }

    /**
     * Read `name=value` pairs, a name repeated across pairs holding several values.
     */
    private function forcedValues($param): array
    {
        $forced = [];
        foreach ($this->formatArray($param) as $pair) {
            if (!is_string($pair) || !str_contains($pair, '=')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $pair, 2));
            if ($name === '' || $value === '') {
                continue;
            }
            $forced[$name] = isset($forced[$name]) ? $forced[$name] . ',' . $value : $value;
        }

        return $forced;
    }

    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL())) {
            return $aclMessage;
        }

        $formManager = $this->getService(FormManager::class);
        $entryChecker = $this->getService(EntryChecker::class);

        $forms = $formManager->getAll();
        $id = strval($this->arguments['id']);
        $selectedForm = $forms[$id] ?? null;

        if (empty($selectedForm)) {
            return $this->render('@bazar/checkcontent.twig', [
                'forms' => $forms,
                'id' => null,
                'params' => $this->arguments['params'],
            ]);
        }

        $repairResult = null;
        if (!empty($this->arguments['repair'])) {
            if ($this->isWikiHibernated()) {
                return $this->getMessageWhenHibernated();
            }
            $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false);
            $repairResult = $entryChecker->repair(
                $id,
                $this->arguments['repair'],
                $this->arguments['pickedValues'],
                $this->arguments['textreplace'],
                $this->arguments['forcevalues']
            );
        }

        $result = $entryChecker->check($id, $this->arguments['textreplace'], $this->arguments['forcevalues']);

        return $this->render('@bazar/checkcontent.twig', [
            'forms' => $forms,
            'id' => $id,
            'selectedForm' => $selectedForm,
            'entriesCount' => $result['entriesCount'],
            'problems' => $result['problems'],
            'unchecked' => $result['unchecked'],
            'problemsCount' => array_sum(array_map('count', $result['problems'])),
            'repairResult' => $repairResult,
            'params' => $this->arguments['params'],
        ]);
    }
}
