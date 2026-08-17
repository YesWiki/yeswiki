<?php

namespace YesWiki\Content\Service;

/** `{{listpages}}` becomes `{{entrylist}}` over the Pages form. */
class ListpagesRewriter
{
    /** ActionRunner's own split of a call into a name and its arguments. */
    private const CALL = '/\{\{(\s*)listpages(\s[^}]*?|\s*)\}\}/i';

    /** `key="value"` pairs, as ActionRunner reads them. */
    private const ARGUMENT = '/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/';

    /** What no entry list can be told, and what the author loses by the rewrite. */
    public const DROPPED = ['user', 'exclude'];

    /**
     * @var list<string> the parameters dropped by the last rewrite, for the report
     */
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
     * Rewrite every string in a decoded body.
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
                break;
        }

        $owner = trim($found['owner'] ?? '');
        if ($owner !== '') {
            $rewritten['query'] = 'owner=' . ($owner === 'owner' ? '[user.name]' : $owner);
        }

        foreach (self::DROPPED as $parameter) {
            if (trim($found[$parameter] ?? '') !== '') {
                $this->dropped[] = $parameter . '="' . $found[$parameter] . '"';
            }
        }

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
