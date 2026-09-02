<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\MapField;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Search\Service\SearchIndexQuery;

/** Every form with what a card says about it. */
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
        $values = [];
        foreach ($this->formManager->getAll() as $form) {
            $values[$form['id']] = $this->describe($form);
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
            'userIsAdmin' => $this->aclService->isAdmin(),
            'isWikiHibernated' => $this->hibernationService->isWikiHibernated(),
        ];
    }

    /**
     * One form, described the way its card is (ticket 63: a form's own screen shares the card's header).
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public function one(array $form): array
    {
        $described = $this->withStats([$form['id'] => $this->describe($form)]);

        return $described[$form['id']];
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    private function describe(array $form): array
    {
        $hibernated = $this->hibernationService->isWikiHibernated();
        $contentType = $form[ContentTypeSchema::CONTENT_TYPE] ?? null;

        return [
            'title' => $form['label'],
            'description' => $form['description'],
            'canEdit' => !$hibernated && $this->guard->isAllowed('saisie_formulaire'),
            'canDelete' => !$hibernated && $this->aclService->isAdmin(),
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
