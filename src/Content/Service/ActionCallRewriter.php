<?php

namespace YesWiki\Content\Service;

/**
 * Rewrites `{{action param="..."}}` calls in stored wiki syntax from the French names to the
 * English ones (tickets 22 and 23, migrated by ticket 33).
 *
 * ## Why this is not a string replace
 *
 * An action name is only an action name in action position. `bazarliste` in prose, in a URL, or
 * as part of a template filename is not a call, and rewriting it would corrupt content. So this
 * recognises calls the way the renderer does and rewrites only the two things that moved:
 *
 * - the action NAME, in the one position where it is a name
 * - parameter KEYS, and only those belonging to the action they were documented against
 *
 * Parameter VALUES are never touched. That is load-bearing rather than merely careful:
 * `{{moteurrecherche template="moteurrecherche_button.twig"}}` must become
 * `{{searchform template="moteurrecherche_button.twig"}}` -- the action moved, the
 * user-addressable filename did not, and a `str_replace` of the action name over the body
 * breaks the template reference in the same edit that fixes the call.
 *
 * ## The recognition rules are the renderer's, not invented here
 *
 * - a call is `{{ ... }}`, matched ungreedily across newlines -- MarkdownFormatterService's
 *   ActionExtension and `renderActionsOnly()`
 * - inside it, `^([a-zA-Z0-9_-]+)/?(.*)$` splits the name from the arguments -- ActionRunner
 * - arguments are `key="value"` pairs -- ActionRunner
 *
 * Anything those rules do not recognise as a call is returned untouched, so `{{end elem="panel"}}`,
 * `{{ }}` and Twig output tags that happen to sit in a body all pass through.
 *
 * ## Ordering: parameters are resolved against the OLD action name
 *
 * `docs/action-parameter-renames.json` is keyed by the action name as ticket 22 spelled it -- the
 * French one -- and both map files used to instruct "apply action-name-renames.json first, then
 * this file". **That order cannot work**: renaming `bazarcarto` to `entrymap` first leaves the
 * parameter map's `bazarcarto` key unable to match anything, and 9 of its 15 keyed actions are
 * names ticket 23 renames. Following the documented order would have silently left those actions'
 * parameters in French.
 *
 * This resolves both in a single pass per call, keyed on the name as found, which removes the
 * ordering question rather than answering it. The note in both JSON files was corrected.
 *
 * ## What is out of scope, and why
 *
 * - **7 parameters flagged `userTyped: false`** -- the action normalises them internally and no
 *   stored content can contain them. Rewriting text no user ever wrote is how a migration
 *   invents corruption.
 * - **2 parameters that are URL query parameters** (`?tri=`, `?…/rss&utilisateur=`). A migration
 *   cannot rewrite a link somebody already shared or bookmarked, so pretending to would be worse
 *   than leaving them: the old spelling has to keep working, or the link breaks either way.
 * - **`{{bazar}}`** keeps its name, so it is simply absent from the name map.
 * - **Template filenames and every other parameter value** -- user data.
 */
class ActionCallRewriter
{
    /**
     * old action name (lowercase) => new action name.
     *
     * @var array<string, string>
     */
    private array $actionRenames;

    /**
     * old action name (lowercase) => [old parameter key (lowercase) => new parameter key].
     *
     * @var array<string, array<string, string>>
     */
    private array $parameterRenames;

    public function __construct()
    {
        $this->actionRenames = self::loadActionRenames();
        $this->parameterRenames = self::loadParameterRenames();
    }

    /**
     * Rewrite every action call in a piece of wiki syntax.
     */
    public function rewriteText(string $text): string
    {
        return (string)preg_replace_callback(
            '/\{\{(.*?)\}\}/s',
            fn (array $matches): string => '{{' . $this->rewriteCall($matches[1]) . '}}',
            $text
        );
    }

    /**
     * Rewrite every string value in a decoded page body.
     *
     * Bodies are JSON since ticket 09, and an action call can sit in any string value in there --
     * a page's `content`, a form field's `form_text`, an entry's textelong. Walking the decoded
     * structure is what keeps this from being a regex over JSON, which is what ticket 25's
     * defect 3 looked like when it went wrong.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing in it changed, so the caller can skip the write
     */
    public function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value) || !str_contains($value, '{{')) {
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
     * A SQL fragment matching rows that could possibly contain something to rewrite, for
     * narrowing the sweep. Only ever a superset: every candidate is still parsed properly.
     *
     * @return list<string> the names to test with LIKE
     */
    public function candidateNeedles(): array
    {
        $needles = array_keys($this->actionRenames);
        foreach ($this->parameterRenames as $parameters) {
            foreach (array_keys($parameters) as $old) {
                $needles[] = $old;
            }
        }

        return array_values(array_unique($needles));
    }

    /** @return array<string, string> old => new, for reporting and tests */
    public function actionRenames(): array
    {
        return $this->actionRenames;
    }

    /**
     * Rewrite one call's interior -- what sits between the braces.
     */
    private function rewriteCall(string $inner): string
    {
        // ActionRunner's own split. A leading-whitespace group is kept so `{{ bazarliste }}`
        // round-trips with its spacing intact: this rewrites content people wrote, and
        // reflowing it is not part of the job.
        if (preg_match('/^(\s*)([a-zA-Z0-9_-]+)(\/?)(.*)$/s', $inner, $matches) !== 1) {
            return $inner;
        }
        [, $leading, $name, $slash, $arguments] = $matches;

        $key = strtolower($name);
        // parameters first, resolved against the name as found -- see the class docblock
        $arguments = $this->rewriteParameters($key, $arguments);

        return $leading . ($this->actionRenames[$key] ?? $name) . $slash . $arguments;
    }

    /**
     * Rewrite the parameter keys of one call, leaving every value alone.
     */
    private function rewriteParameters(string $actionName, string $arguments): string
    {
        $renames = $this->parameterRenames[$actionName] ?? [];
        if ($renames === []) {
            return $arguments;
        }

        // `[^"]*` for the value rather than an ungreedy `.*`: it cannot run past the closing
        // quote into the next parameter, so a value is structurally unable to be mistaken for a
        // key. The value is captured only to be put back untouched.
        return (string)preg_replace_callback(
            '/([a-zA-Z0-9_]+)(\s*=\s*")([^"]*)(")/',
            function (array $matches) use ($renames): string {
                $new = $renames[strtolower($matches[1])] ?? $matches[1];

                return $new . $matches[2] . $matches[3] . $matches[4];
            },
            $arguments
        );
    }

    /** @return array<string, string> */
    private static function loadActionRenames(): array
    {
        $map = [];
        foreach (self::readMap('action-name-renames.json') as $rename) {
            $map[strtolower((string)$rename['old'])] = (string)$rename['new'];
        }

        return $map;
    }

    /** @return array<string, array<string, string>> */
    private static function loadParameterRenames(): array
    {
        $map = [];
        foreach (self::readMap('action-parameter-renames.json') as $rename) {
            // `handler` entries are URL query parameters, not action arguments -- out of scope,
            // and the map says so per entry. `userTyped: false` never appears in stored content.
            if (!isset($rename['action']) || empty($rename['userTyped'])) {
                continue;
            }
            $action = strtolower((string)$rename['action']);
            $map[$action][strtolower((string)$rename['old'])] = (string)$rename['new'];
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function readMap(string $file): array
    {
        $path = YESWIKI_SOURCE_DIR . '/docs/' . $file;
        $raw = @file_get_contents($path);
        if ($raw === false) {
            // Deliberately fatal. A missing map means the rewrite silently does nothing, which
            // leaves a wiki that reports itself upgraded while every French action call in it
            // renders an error -- unrecoverable without knowing to look. A migration that throws
            // is reported by MigrationService, stays pending, and can simply be re-run.
            throw new \RuntimeException("Cannot read the rename map {$path}. The action rename migration cannot run " . 'without it, and skipping it would leave stored content calling actions that no longer exist.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['renames']) || !is_array($decoded['renames'])) {
            throw new \RuntimeException("The rename map {$path} has no 'renames' array.");
        }

        return array_values($decoded['renames']);
    }
}
