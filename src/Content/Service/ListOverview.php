<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Field\EnumField;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HibernationService;

/** Every list a reader may see, with its options and rights. */
class ListOverview
{
    public function __construct(
        private readonly ListManager $listManager,
        private readonly HibernationService $hibernationService,
        private readonly AclService $aclService,
        private readonly FieldFactory $fieldFactory,
    ) {
    }

    /**
     * @return array{lists: array<string, mixed>, canCreate: bool}
     */
    public function all(): array
    {
        $lists = array_filter(
            $this->listManager->getAll(),
            fn ($key) => $this->aclService->hasAccess('read', $key),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($lists as $key => $list) {
            if ($list === null) {
                unset($lists[$key]);
                continue;
            }
            $lists[$key]['canEdit'] = $this->mayEdit((string)$key);
            $lists[$key]['canDelete'] = $this->mayDelete((string)$key);

            $field = $this->fieldFactory->create(['liste', $list['id'] ?? '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $lists[$key]['options'] = $field instanceof EnumField ? $field->getOptions() : [];
        }

        return ['lists' => $lists, 'canCreate' => $this->mayCreate()];
    }

    /** Who may change the lists: an admin, or -- for one list -- whoever owns it. */
    public function mayCreate(): bool
    {
        return !$this->hibernationService->isWikiHibernated() && $this->aclService->isAdmin();
    }

    public function mayEdit(string $id): bool
    {
        return $this->mayDelete($id) && $this->aclService->hasAccess('write', $id);
    }

    public function mayDelete(string $id): bool
    {
        return !$this->hibernationService->isWikiHibernated()
            && ($this->aclService->isAdmin() || $this->aclService->isOwner($id));
    }
}
