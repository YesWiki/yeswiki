<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
        private readonly ParameterBagInterface $params,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    private function dataSourcesOf(): array
    {
        $sources = $this->params->has('dataSources') ? $this->params->get('dataSources') : [];

        return is_array($sources) ? $sources : [];
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

            // Where the values came from, and whether this wiki has agreed to let that replace
            // them: the badge is the feature, following is a separate decision (ticket 64).
            $origin = $this->listManager->originOf((string)$key);
            $lists[$key]['origin'] = $origin;
            $lists[$key]['followed'] = $origin !== '' && $this->isFollowed((string)$key);
        }

        return ['lists' => $lists, 'canCreate' => $this->mayCreate()];
    }

    /** Whether a data source already resyncs this list, which is what Follow creates. */
    private function isFollowed(string $id): bool
    {
        foreach ($this->dataSourcesOf() as $options) {
            if (is_array($options) && ($options['listId'] ?? null) === $id) {
                return true;
            }
        }

        return false;
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
