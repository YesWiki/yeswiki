<?php

namespace YesWiki\Content\Service;

/**
 * `{{listpages}}` becomes `{{entrylist}}` over the Pages form.
 *
 * A page is an entry since ADR-0011 -- one of the Pages form's -- so "list the pages" and
 * "list a form's entries" stopped being two questions, and the wiki was answering them with
 * two actions, two palette cards and two Sources. This is the rewrite that lets the second
 * one go (ticket 37's `listpages` Source was itself the proof that adding one is cheap; it
 * turns out the honest thing to prove was that removing one is).
 *
 * ## What maps, and what is dropped
 *
 * | `{{listpages}}`   | becomes                              |
 * |-------------------|--------------------------------------|
 * | (nothing)         | `id="<Pages form>"`                  |
 * | `template=`       | kept as it is                        |
 * | `sort="tag"`      | nothing -- an entry list sorts by title already |
 * | `sort="time"`     | `field="updated_at" order="desc"`    |
 * | `sort="owner"`    | `field="owner"`                      |
 * | `owner="owner"`   | `query="owner=[user.name]"`          |
 * | `owner="Jean"`    | `query="owner=Jean"`                 |
 * | `sort="user"`     | **dropped** -- an Item has no last editor to sort on |
 * | `user="…"`        | **dropped** -- "took part in" is a question about revisions, which no list can ask |
 * | `exclude="…"`     | **dropped** -- a query names a field's value, and a page's tag is not one |
 *
 * The three that cannot be expressed are dropped rather than left in place: a call nothing
 * answers renders an error where a list used to be, and a list that is slightly wider than
 * it was still shows the pages. Every one of them is reported by name so the author can
 * decide what to do -- see the migration.
 *
 * ## Scope, which changes
 *
 * `{{listpages}}` listed every row of the pages table -- accounts, files, forms and bazar
 * entries included, since they are all rows. `{{entrylist id="<Pages form>"}}` lists the
 * Contents whose type is `page`. On a wiki with entries the new list is *shorter*, and
 * that is the intended reading of "list the pages" rather than a loss.
 */
class ListpagesRewriter
{
    /** ActionRunner's own split of a call into a name and its arguments. */
    private const CALL = '/\{\{(\s*)listpages(\s[^}]*?|\s*)\}\}/i';

    /** `key="value"` pairs, as ActionRunner reads them. */
    private const ARGUMENT = '/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/';

    /** What no entry list can be told, and what the author loses by the rewrite. */
    public const DROPPED = ['user', 'exclude'];

    /** @var list<string> the parameters dropped by the last rewrite, for the report */
    private array $dropped = [];

    /**
     * Rewrite every `{{listpages}}` call in a piece of wiki syntax.
     *
     * @param int|string $pagesFormId the Pages form -- looked up by the caller, because a
     *                                rewriter that asked the database would be a rewriter
     *                                nothing could test on a string
     */
    public function rewriteText(string $text, int|string $pagesFormId): string
    {
        return (string)preg_replace_callback(
            self::CALL,
            function (array $matches) use ($pagesFormId): string {
                return '{{' . $matches[1] . 'entrylist'
                    . $this->rewriteArguments($matches[2], (string)$pagesFormId) . '}}';
            },
            $text
        );
    }

    /**
     * Rewrite every string in a decoded body. Bodies are JSON since ticket 09 and a call can
     * sit in any string in one -- a page's prose, a form field's help text, an entry's
     * textelong -- so this walks the structure rather than regexing the JSON.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing changed, so the caller can skip the write
     */
    public function rewriteBody(array $body, int|string $pagesFormId): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use ($pagesFormId, &$changed): void {
            if (!is_string($value) || stripos($value, 'listpages') === false) {
                return;
            }
            $rewritten = $this->rewriteText($value, $pagesFormId);
            if ($rewritten !== $value) {
                $value = $rewritten;
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }

    /**
     * The parameters the rewrites so far could not express, each named once.
     *
     * @return list<string>
     */
    public function droppedParameters(): array
    {
        return array_values(array_unique($this->dropped));
    }

    public function forgetDropped(): void
    {
        $this->dropped = [];
    }

    private function rewriteArguments(string $arguments, string $pagesFormId): string
    {
        $found = [];
        if (preg_match_all(self::ARGUMENT, $arguments, $matches, PREG_SET_ORDER) !== 0) {
            foreach ($matches as $match) {
                $found[strtolower($match[1])] = $match[2];
            }
        }

        // the form is what makes this a page list at all, so it goes first -- the position
        // the rail writes `id` in too
        $rewritten = ['id' => $pagesFormId];

        if (isset($found['template']) && $found['template'] !== '') {
            $rewritten['template'] = $found['template'];
        }

        switch (strtolower($found['sort'] ?? '')) {
            case 'time':
                $rewritten['field'] = 'updated_at';
                $rewritten['order'] = 'desc';
                break;

            case 'owner':
                $rewritten['field'] = 'owner';
                break;

            case 'user':
                $this->dropped[] = 'sort="user"';
                break;

            default:
                // `tag`, empty, or something invalid: an entry list is sorted by title, which
                // for a page IS its tag unless it has been given one of its own
                break;
        }

        // "belonging to me" is a condition on a field, which is what a query is. `[user.name]`
        // is resolved per reader by SearchManager::parseQuery, so the page stays shareable.
        $owner = trim($found['owner'] ?? '');
        if ($owner !== '') {
            $rewritten['query'] = 'owner=' . ($owner === 'owner' ? '[user.name]' : $owner);
        }

        foreach (self::DROPPED as $parameter) {
            if (trim($found[$parameter] ?? '') !== '') {
                $this->dropped[] = $parameter . '="' . $found[$parameter] . '"';
            }
        }

        // anything else the author wrote is passed through untouched: `class`, `nb` and the
        // rest mean the same thing to an entry list, and a parameter this does not know
        // about is not a parameter it may throw away
        $known = ['template', 'sort', 'owner', 'user', 'exclude', 'id'];
        foreach ($found as $key => $value) {
            if (!in_array($key, $known, true) && !isset($rewritten[$key])) {
                $rewritten[$key] = $value;
            }
        }

        $written = '';
        foreach ($rewritten as $key => $value) {
            $written .= ' ' . $key . '="' . $value . '"';
        }

        return $written;
    }
}
