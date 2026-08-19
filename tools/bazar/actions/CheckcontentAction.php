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
            'params' => $request->query->has('debug') ? ['debug' => 'yes'] : [],
        ];
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
                $this->arguments['textreplace']
            );
        }

        $result = $entryChecker->check($id, $this->arguments['textreplace']);

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
