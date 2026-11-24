<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Content\Field\KeepsAMembersGroup;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\TripleStore;

/** Keeps the group a linked-entry field asks for in step with the people behind the entries it holds, and drops it with its entry. */
class EntryMembersGroups implements EventSubscriberInterface
{
    public const KEPT_GROUP_URI = 'http://outils-reseaux.org/_vocabulary/managedGroup';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly GroupManager $groups,
        private readonly TripleStore $triples,
        private readonly UserManager $users,
        private readonly PageManager $pages,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            'entry.created' => 'followChange',
            'entry.updated' => 'followChange',
            'entry.deleted' => 'followDeletion',
        ];
    }

    /** @param Event $event */
    public function followChange($event): void
    {
        $data = $event->getData();
        $entry = is_array($data['data'] ?? null) ? $data['data'] : [];
        $form = is_array($data['form'] ?? null) ? $data['form'] : [];
        if (!empty($entry['tag'])) {
            $this->sync($form, $entry);
        }
    }

    /** @param Event $event */
    public function followDeletion($event): void
    {
        $tag = (string)($event->getData()['data']['tag'] ?? $event->getData()['id'] ?? '');
        if ($tag !== '') {
            $this->drop($tag);
        }
    }

    /**
     * Brings every group the entry's fields keep in line with what the entry now holds.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    public function sync(array $form, array $entry): void
    {
        $tag = (string)$entry['tag'];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof KeepsAMembersGroup) {
                continue;
            }
            $group = $field->membersGroupOf($tag);
            if ($group === null) {
                continue;
            }
            $kept = (bool)$this->triples->exist($group, self::KEPT_GROUP_URI, $tag, '', '');
            $exists = $this->groups->groupExists($group);
            $members = $this->accountsOf($entry[$field->getPropertyName()] ?? '');
            if ($members === [] && $field->membersGroupFallsBackToOwner()) {
                $members = $this->ownerOf($tag);
            }
            if (($exists && !$kept) || (!$exists && $members === [])) {
                continue;
            }
            if ($exists) {
                $this->groups->updateMembers($group, $members);
            } else {
                $this->groups->create($group, $members);
            }
            if (!$kept) {
                $this->triples->create($group, self::KEPT_GROUP_URI, $tag, '', '');
            }
        }
    }

    /** Deletes the groups an entry kept. */
    public function drop(string $tag): void
    {
        foreach ($this->triples->getMatching(null, self::KEPT_GROUP_URI, $tag, '=', '=', '=') as $row) {
            $group = (string)$row['resource'];
            $this->groups->delete($group);
            $this->triples->delete($group, self::KEPT_GROUP_URI, $tag, '', '');
        }
    }

    /** @return list<string> the accounts behind the chosen entries: each entry's own account, or an account of that name */
    private function accountsOf(mixed $value): array
    {
        $tags = is_array($value)
            ? array_map('strval', array_keys(array_filter($value)))
            : preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
        $accounts = [];
        foreach ($tags ?: [] as $tag) {
            $linked = $this->container->get(EntryManager::class)->getOne($tag, false, null, true, true);
            $name = $linked ? (string)($linked['nomwiki'] ?? '') : '';
            $name = $name !== '' ? $name : $tag;
            if ($this->users->userExist($name)) {
                $accounts[] = $name;
            }
        }

        return array_values(array_unique($accounts));
    }

    /** @return list<string> */
    private function ownerOf(string $tag): array
    {
        $owner = (string)($this->pages->getOwner($tag) ?? '');

        return $owner !== '' && $this->users->userExist($owner) ? [$owner] : [];
    }
}
