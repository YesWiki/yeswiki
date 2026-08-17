<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageType;

/** Answers "what kind of Content is this row, and which form describes it?" (ticket 10). */
class ContentTypeResolver
{
    private ContainerInterface $container;
    private PageManager $pageManager;

    public function __construct(ContainerInterface $container, PageManager $pageManager)
    {
        $this->container = $container;
        $this->pageManager = $pageManager;
    }

    /**
     * The Content type of a row, or null for one this concept does not describe -- a form, a list, a comment, a tag with no row at all.
     */
    public function typeOf(string $tag): ?string
    {
        return $this->formBacked($this->pageManager->typeOf($tag));
    }

    /** The type as a *form-backed* Content type, or null when no form describes it. */
    public function formBacked(?string $type): ?string
    {
        return PageType::isFormBacked($type) ? $type : null;
    }

    /**
     * The form describing this row: its Content type's form for a built-in type, the form its `body.form_id` names for a bazar entry, null for anything else.
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

    /**
     * The form describing this row, or -- when there is no row yet -- the form of the type it will be created with.
     *
     * @return array<string, mixed>|null
     */
    public function formForEditing(string $tag): ?array
    {
        $form = $this->formFor($tag);
        if ($form !== null || $this->pageManager->typeOf($tag) !== null) {
            return $form;
        }

        return $this->container->get(FormManager::class)->getByContentType(PageType::DEFAULT);
    }

    /**
     * A row in the shape everything downstream reads an entry in.
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
