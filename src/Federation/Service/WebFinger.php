<?php

namespace YesWiki\Federation\Service;

/**
 * A simple WebFinger container of data.
 */
class WebFinger
{
    /** Never set until a response supplies one, which getSubject() has always admitted. */
    protected ?string $subject = null;

    /** @var list<string> */
    protected array $aliases = [];

    /** @var list<array<string, mixed>> */
    protected array $links = [];

    /**
     * Construct WebFinger instance.
     *
     * @param array<string, mixed>|null $data A WebFinger response
     */
    public function __construct(?array $data = null)
    {
        if (isset($data)) {
            foreach (['subject', 'aliases', 'links'] as $key) {
                $value = $data[$key] ?? $this->$key;
                $method = 'set' . ucfirst($key);
                $this->$method($value);
            }
        }
    }

    /**
     * Set subject property.
     *
     * `mixed`, not `string`: this is handed a value decoded straight out of a remote
     * server's JSON, and the guard below is the only thing that says so.
     */
    public function setSubject(mixed $subject): void
    {
        if (!is_string($subject)) {
            throw new \Exception('WebFinger subject must be a string');
        }

        $this->subject = $subject;
    }

    /**
     * Set aliases property.
     *
     * @param array<mixed> $aliases as decoded from a remote response, hence unvalidated
     */
    public function setAliases(array $aliases): void
    {
        foreach ($aliases as $alias) {
            if (!is_string($alias)) {
                throw new \Exception('WebFinger aliases must be an array of strings');
            }

            $this->aliases[] = $alias;
        }
    }

    /**
     * Set links property.
     *
     * @param array<mixed> $links as decoded from a remote response, hence unvalidated
     */
    public function setLinks(array $links): void
    {
        foreach ($links as $link) {
            if (!is_array($link)) {
                throw new \Exception('WebFinger links must be an array of objects');
            }

            if (!isset($link['rel'])) {
                throw new \Exception("WebFinger links object must contain 'rel' property");
            }

            $tmp = [];
            $tmp['rel'] = $link['rel'];

            foreach (['type', 'href', 'template'] as $key) {
                if (isset($link[$key]) && is_string($link[$key])) {
                    $tmp[$key] = $link[$key];
                }
            }

            $this->links[] = $tmp;
        }
    }

    /**
     * Get ActivityPhp profile id URL, or null when the response declares no self link.
     *
     * Declared `@return string` and falling off the end of the loop, so "no such link" came
     * back as null from something every caller treated as a string (ticket 40).
     */
    public function getProfileId(): ?string
    {
        foreach ($this->links as $link) {
            if (isset($link['rel'], $link['type'], $link['href'])) {
                if ($link['rel'] == 'self'
                    && $link['type'] == 'application/activity+json'
                ) {
                    return $link['href'];
                }
            }
        }

        return null;
    }

    /** Get interaction url, or null when the response declares no subscribe template. */
    public function getInteractionUrl(): ?string
    {
        foreach ($this->links as $link) {
            if (isset($link['rel'], $link['template'])) {
                if ($link['rel'] == 'http://ostatus.org/schema/1.0/subscribe') {
                    return $link['template'];
                }
            }
        }

        return null;
    }

    /**
     * Get WebFinger response as an array.
     *
     * @return array{subject: string|null, aliases: list<string>, links: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'aliases' => $this->aliases,
            'links' => $this->links,
        ];
    }

    /**
     * Get aliases.
     *
     * @return list<string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get links.
     *
     * @return list<array<string, mixed>>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Get subject fetched from profile.
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Get subject handle fetched from profile, i.e. the subject with its `acct:` scheme
     * stripped -- null when no response ever supplied a subject.
     */
    public function getHandle(): ?string
    {
        return $this->subject === null ? null : substr($this->subject, 5);
    }
}
