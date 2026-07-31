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

    /**
     * A row in the shape everything downstream reads an entry in.
     *
     * Only a bazar entry names its own form (`body.form_id`) and repeats its own tag. A
     * page, an account and a file carry neither: which form describes them is their type
     * triple, their tag is the row's, and a locked field that restates the tag (an
     * account's `username`) is filled in here rather than stored twice. A Content that
     * never got a title still names itself.
     *
     * @param array<string, mixed> $page          a page row whose `body` is already decoded
     * @param string|null          $contentType   when the caller already knows it, to save a query
     * @param bool                 $nameIfUnnamed fill `title` in when the Content has none.
     *                                            A list needs every row to have a name, and
     *                                            falls back to the tag. A rendered *view*
     *                                            does not: the title field would then draw a
     *                                            heading saying the tag, on every page that
     *                                            never had a title.
     *
     * @return array<string, mixed>|null null when no form describes this row -- a form, a list
     */
    public function asEntry(array $page, ?string $contentType = null, bool $nameIfUnnamed = true): ?array
    {
        $body = is_array($page['body'] ?? null) ? $page['body'] : [];
        if (isset($body['form_id'])) {
            return $body;
        }

        $tag = (string)($page['tag'] ?? '');
        $contentType ??= $this->typeOf($tag);
        $form = $contentType === null
            ? null
            : $this->container->get(FormManager::class)->getByContentType($contentType);
        if ($form === null) {
            return null;
        }

        $body['form_id'] = $form['id'];
        $body['tag'] = $tag;
        $mirror = ContentTypeSchema::tagMirrorField($contentType);
        if ($mirror !== null) {
            $body[$mirror] = $tag;
        }
        if ($nameIfUnnamed || trim((string)($body['title'] ?? '')) !== '') {
            $body['title'] = $this->container->get(FormPropertiesService::class)->titleOf($form, $body);
        }

        return $body;
    }
}
