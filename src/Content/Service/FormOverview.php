<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\MapField;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Search\Service\SearchIndexQuery;

/** Every form with what a card says about it: its entry count, its formats, what the reader may do to it. */
class FormOverview
{
    public function __construct(
        private readonly FormManager $formManager,
        private readonly HibernationService $hibernationService,
        private readonly AclService $aclService,
        private readonly Guard $guard,
        private readonly IcalFormatter $icalFormatter,
        private readonly SearchIndexQuery $searchIndexQuery,
    ) {
    }

    /**
     * @return array{systemForms: array<string, mixed>, forms: array<string, mixed>, userIsAdmin: bool, isWikiHibernated: bool}
     */
    public function all(): array
    {
        $hibernated = $this->hibernationService->isWikiHibernated();
        $isAdmin = $this->aclService->isAdmin();
        $mayEdit = !$hibernated && $this->guard->isAllowed('saisie_formulaire');

        $values = [];
        foreach ($this->formManager->getAll() as $form) {
            $contentType = $form[ContentTypeSchema::CONTENT_TYPE] ?? null;
            $values[$form['id']] = [
                'title' => $form['label'],
                'description' => $form['description'],
                'canEdit' => $mayEdit,
                'canDelete' => !$hibernated && $isAdmin,
                'isSemantic' => !empty($form['sem_template']),
                'isActivityPubEnabled' => $form['activitypub_enable'] === '1',
                'isGeo' => !empty(array_filter($form['prepared'], fn ($field) => $field instanceof MapField)),
                'isDate' => $this->icalFormatter->isICALForm($form),
                'bookmarklet' => $form['entry_bookmarklet'] ?? null,
                'isSystem' => ContentTypeSchema::isBuiltIn($contentType),
                'contentType' => $contentType,
                'canCreateContent' => ContentCreator::supports($contentType),
            ];
        }

        $systemForms = array_filter($values, fn ($form) => $form['isSystem']);
        $declaredOrder = array_flip(ContentTypeSchema::types());
        uasort(
            $systemForms,
            fn ($a, $b) => ($declaredOrder[$a['contentType']] ?? PHP_INT_MAX) <=> ($declaredOrder[$b['contentType']] ?? PHP_INT_MAX)
        );

        return [
            'systemForms' => $this->withStats($systemForms),
            'forms' => $this->withStats(array_filter($values, fn ($form) => !$form['isSystem'])),
            'userIsAdmin' => $isAdmin,
            'isWikiHibernated' => $hibernated,
        ];
    }

    /**
     * @param array<string, mixed> $forms
     *
     * @return array<string, mixed>
     */
    private function withStats(array $forms): array
    {
        $stats = $this->searchIndexQuery->contentStats();

        foreach ($forms as $id => $form) {
            $found = ($form['isSystem'] ?? false)
                ? ($stats['byType'][(string)($form['contentType'] ?? '')] ?? null)
                : ($stats['byForm'][(string)$id] ?? null);
            $forms[$id]['stats'] = $found ?? [
                'count' => $stats['total'] > 0 ? 0 : null,
                'last' => '',
            ];
        }

        return $forms;
    }
}
