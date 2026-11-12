<?php

/* The template overrides an admin edits from the screens, kept as `template` Contents so every replica reads the same ones (ticket 18). */

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RequestScopedState;

class TemplateOverrides implements RequestScopedState
{
    public const ACLS = ['read' => '@admins', 'write' => '@admins', 'comment' => 'comments-closed'];

    /** @var array<string, int>|null */
    private ?array $revisions = null;

    public function __construct(
        private readonly DbService $db,
        private readonly PageManager $pages,
        private readonly ContainerInterface $container,
    ) {
    }

    public function startNewRequest(): void
    {
        $this->revisions = null;
    }

    /** `core/fiche-1.twig` is `core.fiche-1`; anything that is not a `.twig` path under `custom/templates/` is refused. */
    public static function tagFor(string $path): string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        $last = (string)array_pop($segments);
        if (!str_ends_with(strtolower($last), '.twig') || strlen($last) <= 5) {
            throw new \InvalidArgumentException("'$path' is not a .twig template");
        }
        $segments[] = substr($last, 0, -5);
        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*$/', $segment) !== 1) {
                throw new \InvalidArgumentException("'$path' is not a template path under custom/templates/");
            }
        }

        return implode('.', $segments);
    }

    /** @return array<string, int> each override's tag and the id of its current revision, read once per request */
    public function revisions(): array
    {
        if ($this->revisions === null) {
            $this->revisions = [];
            foreach ($this->db->loadAll('SELECT tag, id FROM' . $this->db->prefixTable('pages') . "WHERE type = ? AND latest = 'Y'", [PageType::TEMPLATE]) as $row) {
                $this->revisions[(string)$row['tag']] = (int)$row['id'];
            }
        }

        return $this->revisions;
    }

    public function revisionOf(string $path): ?int
    {
        return $this->revisions()[self::tagFor($path)] ?? null;
    }

    /** The override's Twig, or null when the database has none for that path. */
    public function read(string $path): ?string
    {
        if ($this->revisionOf($path) === null) {
            return null;
        }
        $body = $this->pages->getOne(self::tagFor($path), null, false, true)['body'] ?? [];

        return is_string($body['twig'] ?? null) ? $body['twig'] : null;
    }

    /** Saves a new revision once it compiles; refuses a path, a tag or Twig that is not this override's with an InvalidArgumentException. */
    public function save(string $path, string $twig): void
    {
        $tag = self::tagFor($path);
        $type = $this->pages->typeOf($tag);
        if ($type !== null && $type !== PageType::TEMPLATE) {
            throw new \InvalidArgumentException("'$tag' already names another Content, so '$path' cannot be saved");
        }
        $current = $type === null ? null : ($this->pages->getOne($tag, null, false, true)['body']['path'] ?? null);
        if ($current !== null && $current !== $path) {
            throw new \InvalidArgumentException("'$tag' already holds '$current', so '$path' cannot be saved");
        }
        $this->check($path, $twig);

        $this->pages->save($tag, ['path' => $path, 'twig' => $twig], '', true, null, PageType::TEMPLATE, self::ACLS);
        $this->revisions = null;
    }

    /** Refuses Twig that does not compile with the wiki's own functions and filters, with Twig's message, which names the line. */
    public function check(string $path, string $twig): void
    {
        try {
            $this->container->get(TemplateEngine::class)->parseTemplateSource($path, $twig);
        } catch (\Twig\Error\Error $broken) {
            throw new \InvalidArgumentException($broken->getMessage(), 0, $broken);
        }
    }

    /** Takes the override away with every revision, so the file on disk or the program's template shows again. */
    public function delete(string $path): void
    {
        $tag = self::tagFor($path);
        if ($this->pages->typeOf($tag) === PageType::TEMPLATE) {
            $this->pages->deleteOrphaned($tag);
        }
        $this->revisions = null;
    }

    /** @return list<array{path: string, tag: string, id: int, time: string, user: string}> */
    public function all(): array
    {
        $user = $this->db->quoteIdentifier('user');
        $time = $this->db->quoteIdentifier('time');
        $found = [];
        foreach ($this->db->loadAll("SELECT tag, id, body, $time AS time, $user AS user FROM" . $this->db->prefixTable('pages') . "WHERE type = ? AND latest = 'Y'", [PageType::TEMPLATE]) as $row) {
            $body = json_decode((string)$row['body'], true);
            if (is_array($body) && is_string($body['path'] ?? null)) {
                $found[] = ['path' => $body['path'], 'tag' => (string)$row['tag'], 'id' => (int)$row['id'], 'time' => (string)$row['time'], 'user' => (string)$row['user']];
            }
        }
        usort($found, fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $found;
    }
}
