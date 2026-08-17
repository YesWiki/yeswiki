<?php

namespace YesWiki\Search\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Search\Entity\IndexedContent;

/** Turns a `pages` row into the rows the search index holds (ticket 18 / ADR-0015). */
class SearchableTextExtractor
{
    public const TYPE_FORM = PageType::FORM;
    public const TYPE_COMMENT = PageType::COMMENT;

    private ContainerInterface $container;
    private ParameterBagInterface $params;

    public function __construct(ContainerInterface $container, ParameterBagInterface $params)
    {
        $this->container = $container;
        $this->params = $params;
    }

    /**
     * @param array<string, mixed> $row a raw `pages` row: tag, type, body, owner, time,
     *                                  metadata, parent. Since ticket 27 the row states its
     *                                  own type, so nothing is looked up or translated here.
     *
     * @return IndexedContent|null null when the row contributes nothing at all
     */
    public function extract(array $row): ?IndexedContent
    {
        $tag = (string)($row['tag'] ?? '');
        if ($tag === '') {
            return null;
        }

        $body = is_array($row['body'] ?? null) ? $row['body'] : PageBody::decode($row['body'] ?? null);
        $contentType = (string)($row['type'] ?? PageType::DEFAULT);

        $buckets = $this->bucketsFor($contentType, $body);

        $content = new IndexedContent(
            tag: $tag,
            contentType: $contentType,
            formId: $contentType === self::TYPE_FORM
                ? (string)($body['id'] ?? '')
                : (string)($body['form_id'] ?? ''),
            title: $this->titleOf($contentType, $body, $tag),
            pageReadAcl: $this->readAclOf($row),
            owner: (string)($row['owner'] ?? ''),
            updatedAt: (string)($row['time'] ?? date('Y-m-d H:i:s')),
            buckets: $buckets,
            keywords: $this->keywordsOf($body),
        );

        return $content->isEmpty() ? null : $content;
    }

    /**
     * The Content's keywords, for the `tags=` filter (ticket 35).
     *
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private function keywordsOf(array $body): array
    {
        $keywords = $body[PageBody::KEYWORDS] ?? null;
        if (!is_array($keywords)) {
            return [];
        }

        $cleaned = [];
        foreach ($keywords as $keyword) {
            $keyword = trim((string)$keyword);
            if ($keyword !== '') {
                $cleaned[$keyword] = true;
            }
        }

        return array_keys($cleaned);
    }

    /**
     * Field ACL expression => the text of the fields it guards.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, string>
     */
    private function bucketsFor(string $contentType, array $body): array
    {
        $form = $this->formFor($contentType, $body);
        if ($form === null) {
            return $this->bucketsWithoutAForm($contentType, $body);
        }

        $buckets = [];
        foreach ($form['prepared'] ?? [] as $field) {
            $text = trim($field->searchableText($body));
            if ($text === '') {
                continue;
            }
            $acl = self::bucketKeyFor((string)$field->getReadAccess());
            $buckets[$acl] = trim(($buckets[$acl] ?? '') . ' ' . $text);
        }

        return $buckets;
    }

    /** The ACL bucket a field's `read_access` belongs in. */
    private static function bucketKeyFor(string $readAccess): string
    {
        $entries = array_filter(array_map('trim', preg_split('/[\n,]+/', $readAccess) ?: []));

        return $entries === [] || $entries === ['*'] || array_values($entries) === ['*'] ? '' : trim($readAccess);
    }

    /**
     * Rows no form describes: a form itself, a list, or a legacy row whose form has gone.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, string>
     */
    private function bucketsWithoutAForm(string $contentType, array $body): array
    {
        if ($contentType === self::TYPE_FORM) {
            $text = trim((string)($body['label'] ?? '') . ' ' . (string)($body['description'] ?? ''));

            return $text === '' ? [] : ['' => $text];
        }

        $text = TextareaField::stripMarkupForIndex(PageBody::content($body));
        $keywords = $body[PageBody::KEYWORDS] ?? null;
        if (is_array($keywords)) {
            $text = trim($text . ' ' . implode(' ', array_map('strval', $keywords)));
        }

        return $text === '' ? [] : ['' => $text];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null
     */
    private function formFor(string $contentType, array $body): ?array
    {
        $formManager = $this->container->get(FormManager::class);

        if ($contentType === ContentTypeSchema::TYPE_ENTRY) {
            $formId = (string)($body['form_id'] ?? '');

            return $formId === '' ? null : $formManager->getOne($formId);
        }

        if ($contentType === self::TYPE_COMMENT) {
            $contentType = ContentTypeSchema::TYPE_PAGE;
        }

        return ContentTypeSchema::isBuiltIn($contentType)
            ? $formManager->getByContentType($contentType)
            : null;
    }

    /**
     * What the Content goes by in a result list.
     *
     * @param array<string, mixed> $body
     */
    private function titleOf(string $contentType, array $body, string $tag): string
    {
        if ($contentType === self::TYPE_FORM) {
            $title = (string)($body['label'] ?? '');
        } else {
            $title = (string)($body[PageBody::TITLE] ?? '');
        }

        return trim($title) === '' ? $tag : trim($title);
    }

    /**
     * The row's **effective** read ACL, defaults already resolved.
     *
     * @param array<string, mixed> $row
     */
    private function readAclOf(array $row): string
    {
        $metadata = $row['metadatas'] ?? null;
        if (!is_array($metadata)) {
            $decoded = json_decode((string)($row['metadata'] ?? ''), true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $acl = $metadata['acls']['read'] ?? '';
        if (trim((string)$acl) !== '') {
            return (string)$acl;
        }

        $default = $this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : '*';

        return is_scalar($default) ? (string)$default : '*';
    }
}
