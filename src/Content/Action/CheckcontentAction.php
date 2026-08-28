<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\EntryChecker;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

class CheckcontentAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{checkcontent}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'checkcontent';
    }

    public function components(): array
    {
        return [
            Component::for('checkcontent')
                ->category(Category::Admin)
                ->label(_t('AB_checkcontent_label'))
                ->description(_t('AB_checkcontent_action_description'))
                ->icon('check-circle')
                ->previewHeight('400px')
                ->adminOnly()
                ->settings(
                    Setting::text('textreplace')
                        ->label(_t('AB_checkcontent_textreplace_label'))
                        ->hint(_t('AB_checkcontent_textreplace_hint')),
                    Setting::text('forcevalues')
                        ->label(_t('AB_checkcontent_forcevalues_label'))
                        ->hint(_t('AB_checkcontent_forcevalues_hint'))
                        ->multiple(),
                ),
        ];
    }

    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $post = $request->request->all();
        $selected = $post['checkcontent-repair'] ?? [];
        $picked = $post['checkcontent-value'] ?? [];

        return [
            'id' => $request->get('form_id') ?? $arg['id'] ?? $arg['idtypeannonce'] ?? '',
            'repair' => is_array($selected) ? array_values(array_filter($selected, 'is_string')) : [],
            'pickedValues' => is_array($picked) ? $picked : [],
            'textreplace' => is_scalar($arg['textreplace'] ?? null)
                ? strval($arg['textreplace'])
                : _t('BAZ_CHECKCONTENT_TEXT_REPLACEMENT'),
            'forcevalues' => $this->forcedValues($arg['forcevalues'] ?? null),
            'truncated' => $this->postWasTruncated($request),
            'params' => $request->query->has('debug') ? ['debug' => 'yes'] : [],
        ];
    }

    /**
     * Read `name=value` pairs, a name repeated across pairs holding several values.
     *
     * @param mixed $param
     *
     * @return array<string, string>
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

    /** PHP drops the tail of a POST holding more fields than max_input_vars, csrf token and form id included, so a submission missing its closing marker never arrived whole. */
    private function postWasTruncated(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        if (!$request->isMethod('POST') || $request->request->has('checkcontent-complete')) {
            return false;
        }

        return $request->request->has('checkcontent-repair')
            || ($request->request->count() === 0 && intval($request->headers->get('Content-Length')) > 0);
    }

    /** @return string */
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
            return $this->render('@core/checkcontent.twig', [
                'forms' => $forms,
                'id' => null,
                'truncated' => $this->arguments['truncated'],
                'params' => $this->arguments['params'],
            ]);
        }

        $repairResult = null;
        if (!empty($this->arguments['repair']) && !$this->arguments['truncated']) {
            if ($this->isWikiHibernated()) {
                return $this->getMessageWhenHibernated();
            }
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
            $repairResult = $entryChecker->repair(
                $id,
                $this->arguments['repair'],
                $this->arguments['pickedValues'],
                $this->arguments['textreplace'],
                $this->arguments['forcevalues']
            );
        }

        $result = $entryChecker->check($id, $this->arguments['textreplace'], $this->arguments['forcevalues']);

        return $this->render('@core/checkcontent.twig', [
            'forms' => $forms,
            'id' => $id,
            'selectedForm' => $selectedForm,
            'entriesCount' => $result['entriesCount'],
            'problems' => $result['problems'],
            'unchecked' => $result['unchecked'],
            'problemsCount' => array_sum(array_map('count', $result['problems'])),
            'repairResult' => $repairResult,
            'truncated' => $this->arguments['truncated'],
            'params' => $this->arguments['params'],
        ]);
    }
}
