<?php

namespace YesWiki\Kernel\Component;

/** A titled block of settings shown as its own panel in the rail, and shared by several Components. */
final class SettingGroup
{
    /**
     * @var list<Setting>
     */
    private array $settings;

    private ?string $width = null;

    private function __construct(private readonly string $title, Setting ...$settings)
    {
        $this->settings = array_values($settings);
    }

    public static function named(string $title, Setting ...$settings): self
    {
        return new self($title, ...$settings);
    }

    /** How wide the block's inputs sit in the rail -- `33%` gives three to a row. */
    public function width(string $width): self
    {
        $clone = clone $this;
        $clone->width = $width;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $properties = [];
        foreach ($this->settings as $setting) {
            $properties[$setting->name()] = $setting->toArray();
        }

        return array_filter([
            'title' => $this->title,
            'width' => $this->width,
            'properties' => $properties,
        ], static fn ($value) => $value !== null);
    }
}
