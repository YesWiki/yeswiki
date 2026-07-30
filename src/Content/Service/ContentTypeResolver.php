<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Identity\Service\UserManager;

/**
 * Answers "what kind of Content is this row, and which form describes it?" (ticket 10).
 *
 * A bazar entry names its own form in `body.form_id`. Nothing else does: a page, an
 * account and an uploaded file are identified by their `TYPE_URI` triple -- and a page by
 * the **absence** of one, which is what being untyped means. Every caller that needs the
 * form behind a row went and re-derived that rule; this is the one place that knows it.
 */
class ContentTypeResolver
{
    /** Triple value => Content type. The empty key is the untyped rows, which are pages. */
    private const TYPES_BY_TRIPLE = [
        '' => ContentTypeSchema::TYPE_PAGE,
        UserManager::TRIPLES_USER_TYPE => ContentTypeSchema::TYPE_USER,
        FileManager::TRIPLES_FILE_TYPE => ContentTypeSchema::TYPE_FILE,
        EntryManager::TRIPLES_ENTRY_ID => ContentTypeSchema::TYPE_ENTRY,
    ];

    private ContainerInterface $container;
    private TripleStore $tripleStore;

    public function __construct(ContainerInterface $container, TripleStore $tripleStore)
    {
        $this->container = $container;
        $this->tripleStore = $tripleStore;
    }

    /**
     * The Content type of a row, or null for one this concept does not describe -- a form,
     * a list, a migration marker.
     */
    public function typeOf(string $tag): ?string
    {
        return $this->typeOfTriple($this->tripleOf($tag));
    }

    /** The Content type named by a `TYPE_URI` triple value ('' meaning no triple at all). */
    public function typeOfTriple(?string $tripleValue): ?string
    {
        return self::TYPES_BY_TRIPLE[(string)$tripleValue] ?? null;
    }

    /**
     * The form describing this row: its Content type's form for a built-in type, the form
     * its `body.form_id` names for a bazar entry, null for anything else.
     *
     * @return array<string, mixed>|null
     */
    public function formFor(string $tag): ?array
    {
        $formManager = $this->container->get(FormManager::class);
        $type = $this->typeOf($tag);

        if ($type === ContentTypeSchema::TYPE_ENTRY) {
            $entry = $this->container->get(EntryManager::class)->getOne($tag);

            return empty($entry['form_id']) ? null : $formManager->getOne($entry['form_id']);
        }

        return $type === null ? null : $formManager->getByContentType($type);
    }

    private function tripleOf(string $tag): string
    {
        return (string)$this->tripleStore->getOne($tag, TripleStore::TYPE_URI, '', '');
    }
}
