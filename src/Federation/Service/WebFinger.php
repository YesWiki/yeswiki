<?php

namespace YesWiki\Federation\Service;

/**
 * A simple WebFinger container of data.
 */
class WebFinger
{
    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string[]
     */
    protected $aliases = [];

    /**
     * @var array
     */
    protected $links = [];

    /**
     * Construct WebFinger instance.
     *
     * @param array $data A WebFinger response
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
     * @param string $subject
     */
    public function setSubject($subject)
    {
        if (!is_string($subject)) {
            throw new \Exception('WebFinger subject must be a string');
        }

        $this->subject = $subject;
    }

    /**
     * Set aliases property.
     */
    public function setAliases(array $aliases)
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
     */
    public function setLinks(array $links)
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
     * @return array
     */
    public function toArray()
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
     * @return array
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Get links.
     *
     * @return array
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Get subject fetched from profile.
     *
     * @return string|null Subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Get subject handle fetched from profile.
     *
     * @return string|null
     */
    public function getHandle()
    {
        return substr($this->subject, 5);
    }
}
