<?php

namespace YesWiki\Kernel\Service;

/**
 * The settings an admin edits, kept in the wiki's database so that every replica reads the same ones and the configuration file is
 * never written (replicas 04): one triple per setting, its value as JSON, `null` for a setting taken away, and a version every
 * process compares to know it read an old one. Written with SQL rather than TripleStore, since a hibernated wiki must still lift its status.
 */
class WikiSettings implements RequestScopedState
{
    public const RESOURCE = 'yeswiki_settings';

    public const VERSION = 'version';

    private const PREFIX = 'setting:';

    /** @var array<string, mixed>|null */
    private ?array $read = null;

    private ?string $version = null;

    public function __construct(private readonly DbService $db)
    {
    }

    public function startNewRequest(): void
    {
        $this->read = null;
        $this->version = null;
    }

    /**
     * What the database says, by key; a key whose value is null was taken away.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->read === null) {
            $this->load();
        }

        return $this->read ?? [];
    }

    /** What changes whenever a setting does, '' for a wiki that never stored one. */
    public function version(): string
    {
        if ($this->version === null) {
            $this->load();
        }

        return $this->version ?? '';
    }

    /** The version as the database says it now, whatever this request read before: how a long-lived worker learns another replica changed a setting. */
    public function freshVersion(): string
    {
        try {
            return (string)$this->db->scalar('SELECT value FROM' . $this->db->prefixTable('triples') . 'WHERE resource = ? AND property = ?', '', [self::RESOURCE, self::VERSION]);
        } catch (\Throwable) {
            return $this->version();
        }
    }

    /**
     * Those settings over a configuration: a stored value replaces the configuration's, a stored null takes the key away.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function over(array $config): array
    {
        foreach ($this->all() as $key => $value) {
            if ($value === null) {
                unset($config[$key]);
            } else {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * Stores those settings, null taking one away, forgets the ones the configuration file says already, and moves the version on.
     *
     * @param array<string, mixed> $settings
     * @param list<string> $forget
     */
    public function store(array $settings, array $forget = []): void
    {
        $forget = array_values(array_filter($forget, fn (string $key): bool => array_key_exists($key, $this->all())));
        if ($settings === [] && $forget === []) {
            return;
        }
        $table = $this->db->prefixTable('triples');
        $this->db->transactional(function () use ($settings, $forget, $table): void {
            foreach ($forget as $key) {
                $this->db->query("DELETE FROM {$table} WHERE resource = ? AND property = ?", [self::RESOURCE, self::PREFIX . $key]);
            }
            foreach ($settings as $key => $value) {
                $this->db->query("DELETE FROM {$table} WHERE resource = ? AND property = ?", [self::RESOURCE, self::PREFIX . $key]);
                $this->db->query("INSERT INTO {$table} (resource, property, value) VALUES (?, ?, ?)", [self::RESOURCE, self::PREFIX . $key, (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            $this->db->query("DELETE FROM {$table} WHERE resource = ? AND property = ?", [self::RESOURCE, self::VERSION]);
            $this->db->query("INSERT INTO {$table} (resource, property, value) VALUES (?, ?, ?)", [self::RESOURCE, self::VERSION, bin2hex(random_bytes(8))]);
        }, 3);
        $this->read = null;
        $this->version = null;
    }

    private function load(): void
    {
        $this->read = [];
        $this->version = '';
        try {
            $rows = $this->db->loadAll('SELECT property, value FROM' . $this->db->prefixTable('triples') . 'WHERE resource = ?', [self::RESOURCE]);
        } catch (\Throwable) {
            return;
        }
        foreach ($rows as $row) {
            $property = (string)$row['property'];
            if ($property === self::VERSION) {
                $this->version = (string)$row['value'];
            } elseif (str_starts_with($property, self::PREFIX)) {
                $this->read[substr($property, strlen(self::PREFIX))] = json_decode((string)$row['value'], true);
            }
        }
    }
}
