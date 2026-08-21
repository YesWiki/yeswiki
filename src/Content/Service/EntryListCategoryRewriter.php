<?php

namespace YesWiki\Content\Service;

/**
 * `{{entrylistcategory}}` becomes `{{entrylist}}` grouped on a field (ticket 49).
 *
 * The action it replaces grouped a form's entries under the values of one of its list fields
 * and drew each group as a collapsible section. `{{entrylist}}` has done that since facets
 * arrived: `groups` names the field, and `liste_accordeon` is the accordion.
 *
 * **The call being rewritten does not work.** It took `idtypeannonce` for the form and `id`
 * for the grouping field; ticket 06's conversion to a class read `id` for both, so whichever
 * spelling a page uses, one of the two is wrong and the action prints "Undefined array key"
 * where the list should be. The rewrite therefore reads what the *call* means rather than
 * what the class did with it, which is the only reading under which these pages ever worked.
 */
class EntryListCategoryRewriter
{
    /**
     * ActionRunner's own split of a call into a name and its arguments.
     *
     * Both spellings: ticket 23's rename migration turns `bazarlistecategorie` into
     * `entrylistcategory` in stored bodies and runs first, but matching both means this
     * rewrite does not depend on that having happened.
     */
    private const CALL = '/\{\{(\s*)(?:entrylistcategory|bazarlistecategorie)(\s[^}]*?|\s*)\}\}/i';

    /** `key="value"` pairs, as ActionRunner reads them. */
    private const ARGUMENT = '/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/';

    /** The accordion, which is what this action drew. */
    public const TEMPLATE = 'liste_accordeon';

    /**
     * @var list<string> the parameters dropped by the rewrites so far, for the report
     */
    private array $dropped = [];

    public function rewriteText(string $text): string
    {
        return (string)preg_replace_callback(
            self::CALL,
            fn (array $matches): string => '{{' . $matches[1] . 'entrylist' . $this->rewriteArguments($matches[2]) . '}}',
            $text
        );
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing changed, so the caller can skip the write
     */
    public function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value) || (stripos($value, 'entrylistcategory') === false && stripos($value, 'bazarlistecategorie') === false)) {
                return;
            }
            $rewritten = $this->rewriteText($value);
            if ($rewritten !== $value) {
                $value = $rewritten;
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }

    /**
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

    private function rewriteArguments(string $arguments): string
    {
        $found = [];
        if (preg_match_all(self::ARGUMENT, $arguments, $matches, PREG_SET_ORDER) !== 0) {
            foreach ($matches as $match) {
                $found[strtolower($match[1])] = $match[2];
            }
        }

        [$formId, $groupField] = $this->readFormAndField($found);

        $rewritten = [];
        if ($formId !== null) {
            $rewritten['id'] = $formId;
        }
        if ($groupField !== null) {
            $rewritten['groups'] = $groupField;
        } else {
            // Without a field to group on this is an ordinary list, which is more than the
            // action rendered, and the author is told rather than left to notice.
            $this->dropped[] = 'groups';
        }
        $rewritten['template'] = self::TEMPLATE;
        if (!empty($found['order'])) {
            $rewritten['order'] = $found['order'];
        }

        // `list` named the wiki page holding the list's values. A facet reads them from the
        // field itself, so there is nothing for the parameter to say.
        foreach (['list', 'template'] as $gone) {
            if (isset($found[$gone]) && $found[$gone] !== '') {
                $this->dropped[] = $gone;
            }
        }

        $written = '';
        foreach ($rewritten as $key => $value) {
            $written .= ' ' . $key . '="' . $value . '"';
        }

        return $written;
    }

    /**
     * Which argument is the form and which is the field it groups on.
     *
     * @param array<string, string> $found
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function readFormAndField(array $found): array
    {
        $id = $found['id'] ?? '';

        // The call as it was written before ticket 22 renamed the parameter: two arguments,
        // and unambiguous.
        if (!empty($found['idtypeannonce'])) {
            return [$found['idtypeannonce'], $id === '' ? null : $id];
        }

        // One argument left, and the class read it as both. A form id is a number and a field
        // name is not, so which one the author meant can still be told apart.
        if ($id === '') {
            return [null, null];
        }

        return ctype_digit($id) ? [$id, null] : [null, $id];
    }
}
