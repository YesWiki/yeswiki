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

/**
 * Turns a `pages` row into the rows the search index holds (ticket 18 / ADR-0015).
 *
 * The rule it implements, and the reason it is a class rather than a loop over a body: the
 * index is built by **asking each field what is searchable about it**, never by walking the
 * body's values. Some values are envelope as surely as the keys are -- `form_id`, a
 * timestamp, a `stored_filename` UUID -- and indexing those reproduces the defect this
 * ticket exists to fix ("search 2026, match everything edited this year") one layer down.
 *
 * Fields are grouped by the Field ACL guarding them, so a Content becomes one index row per
 * distinct ACL rather than one document with a single visibility. See IndexedContent.
 */
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
            // a form has no `form_id` -- it has an `id`, and that is what a result needs in
            // order to link to the form's entry list rather than to the form's own page
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
     * They stay in the free-text bucket too, so searching the word still finds the page -- what
     * this adds is the ability to ask for an *exact* keyword. Before, folding them into `text` was
     * the only thing that happened, so tag navigation could only ever be a fuzzy text match:
     * `?q=Recette` also matched every page that merely mentioned the word.
     *
     * Trimmed and de-duplicated, and empties dropped: a keyword list that round-trips through a
     * text field arrives with stray commas.
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

    /**
     * The ACL bucket a field's `read_access` belongs in.
     *
     * `''` and `'*'` mean the same thing -- `BazarField::canRead()` grants both to everyone --
     * so they must land in the *same* bucket. Storing them apart looks harmless and is not:
     * it splits a Content's text across two rows for no reason, and it defeats the
     * single-bucket fast path in SearchIndexQuery on essentially every real wiki, because the
     * seeded Annuaire and Agenda forms ship `"read_access":"*"` on every field. That showed
     * up as a 500k-row benchmark taking the expensive GROUP BY path when it had no restricted
     * field anywhere.
     */
    private static function bucketKeyFor(string $readAccess): string
    {
        $entries = array_filter(array_map('trim', preg_split('/[\n,]+/', $readAccess) ?: []));

        return $entries === [] || $entries === ['*'] || array_values($entries) === ['*'] ? '' : trim($readAccess);
    }

    /**
     * Rows no form describes: a form itself, a list, or a legacy row whose form has gone.
     *
     * A form is indexed by what a webmaster would look for it under -- its label and
     * description -- and deliberately **not** by its template: matching a form on its field
     * labels means searching the schema, and would put every form in the results for the
     * word "Description".
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

        // last resort: the prose, stripped the way the field would have stripped it
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

        // a comment is a page in every way that matters here: prose under `content`
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
     * Read from the body rather than recomputed: the title is already computed at save from
     * the form's `entry_title_template` (ADR-0010) and stored there, and recomputing it here
     * would mean resolving a template per row on a reindex of millions.
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

        // every Content has a name, and an untitled one goes by its tag (see CONTEXT's
        // "Content title") -- which is also what a visitor typed if they are looking for it
        return trim($title) === '' ? $tag : trim($title);
    }

    /**
     * The row's **effective** read ACL, defaults already resolved.
     *
     * Resolved at index time rather than left absent, so the query's predicate has no
     * "no explicit ACL means the configured default" branch to get wrong -- the index is
     * rewritten whenever an ACL changes anyway, because an ACL write creates a revision.
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
