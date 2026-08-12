<?php

namespace YesWiki\Kernel\Component;

/**
 * Something a webmaster can insert into page content: the palette's unit.
 *
 * A Component is not an Action, and the two lists have never matched -- 105 registered
 * actions against 70 palette entries when this was written, disagreeing in both directions.
 * One Component may write more than one action (`Cards` writes `{{entrylist}}` over a form
 * and `{{syndication}}` over a feed), and several may write the same action pinned to
 * different settings (`bazarcard` and `bazartableau` are both `{{entrylist}}`). What a
 * Component always does is write a `{{tag}}` -- that is the whole of the rule, which is why
 * the `:::info` callouts are a toolbar item rather than a Component, and why matching a tag
 * back to the Component that wrote it needs no second case (see ADR-0017).
 *
 * Immutable: `Setting`s and `SettingGroup`s are shared between Components on purpose, and a
 * builder that mutated in place would let one of them edit another's.
 */
final class Component
{
    /** @var list<string> */
    private array $tags;

    /** @var array<string, string> */
    private array $pins = [];

    /** @var list<Setting> */
    private array $settings = [];

    /** @var list<SettingGroup> */
    private array $groups = [];

    private Category $category = Category::Other;

    private string $label = '';

    private ?string $icon = null;

    private ?string $description = null;

    private ?string $hint = null;

    private ?string $doclink = null;

    private ?string $previewHeight = null;

    private bool $adminOnly = false;

    private bool $offered = true;

    private bool $wrapper = false;

    private bool $addOnly = false;

    private ?string $wrappedExample = null;

    private function __construct(private readonly string $id)
    {
        // the common case by far: the Component is named after the one action it writes
        $this->tags = [$id];
    }

    /**
     * @param string $id what this Component is called in the palette and in a rail's state.
     *                   Also the tag it writes, unless `writes()` says otherwise.
     */
    public static function for(string $id): self
    {
        return new self($id);
    }

    /**
     * The action tags this Component can write -- more than one when the same look is
     * available over different Sources.
     *
     * Recognition starts here: a tag in a page body is only ever matched against Components
     * that list its name.
     */
    public function writes(string ...$tags): self
    {
        return $this->with(function (self $c) use ($tags): void {
            $c->tags = array_values($tags);
        });
    }

    /**
     * A parameter this Component always writes, and which identifies it on the way back.
     *
     * `bazarcard` pins `template=card`: inserting it writes that parameter, and finding it
     * in a tag is what says the tag is a `bazarcard` rather than a bare `{{entrylist}}`.
     * A pin is not editable -- change it and you have a different Component.
     */
    public function pin(string $name, string $value): self
    {
        return $this->with(function (self $c) use ($name, $value): void {
            $c->pins[$name] = $value;
        });
    }

    public function label(string $label): self
    {
        return $this->with(fn (self $c) => $c->label = $label);
    }

    /** A sprite icon name, e.g. `layout-grid`. Per Component, not per category. */
    public function icon(string $icon): self
    {
        return $this->with(fn (self $c) => $c->icon = $icon);
    }

    public function category(Category $category): self
    {
        return $this->with(fn (self $c) => $c->category = $category);
    }

    /** A sentence under the name in the palette. */
    public function description(string $description): self
    {
        return $this->with(fn (self $c) => $c->description = $description);
    }

    /** Advice shown in the settings rail, above the parameters. */
    public function hint(string $hint): self
    {
        return $this->with(fn (self $c) => $c->hint = $hint);
    }

    public function documentedAt(string $url): self
    {
        return $this->with(fn (self $c) => $c->doclink = $url);
    }

    /** How tall the rail's live preview of this component should be, e.g. `350px`. */
    public function previewHeight(string $height): self
    {
        return $this->with(fn (self $c) => $c->previewHeight = $height);
    }

    /** Offered only to those who can administer the wiki. */
    public function adminOnly(): self
    {
        return $this->with(fn (self $c) => $c->adminOnly = true);
    }

    /**
     * Editable but not offered: the rail opens on one already written in the page, and the
     * palette does not list it.
     *
     * This is what the YAML's group-level `onlyEdit` was, and it is right for anything
     * inserted by a route other than the palette -- `{{attach}}` comes from the file
     * picker, so offering it twice would be offering two ways to do one thing.
     */
    public function notOffered(): self
    {
        return $this->with(fn (self $c) => $c->offered = false);
    }

    /**
     * Two halves with editable content between them: `{{grid}}` … `{{end elem="grid"}}`.
     *
     * @param string|null $example content written between the halves on insertion, so that
     *                             what lands in the page is a working example rather than
     *                             an empty pair of tags
     */
    public function wraps(?string $example = null): self
    {
        return $this->with(function (self $c) use ($example): void {
            $c->wrapper = true;
            $c->wrappedExample = $example;
        });
    }

    /**
     * A wrapper the rail can insert but not re-open: its parameters describe how many
     * halves to write (`{{tabs titles="a,b,c"}}`), so editing them afterwards would mean
     * rewriting content the webmaster has since put inside.
     */
    public function addOnly(): self
    {
        return $this->with(fn (self $c) => $c->addOnly = true);
    }

    public function settings(Setting ...$settings): self
    {
        return $this->with(function (self $c) use ($settings): void {
            $c->settings = array_merge($c->settings, array_values($settings));
        });
    }

    /** A shared block of settings, shown as its own panel. */
    public function group(SettingGroup ...$groups): self
    {
        return $this->with(function (self $c) use ($groups): void {
            $c->groups = array_merge($c->groups, array_values($groups));
        });
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    /** @return array<string, string> */
    public function pins(): array
    {
        return $this->pins;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    public function categoryOf(): Category
    {
        return $this->category;
    }

    /** @return list<Setting> */
    public function ownSettings(): array
    {
        return $this->settings;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $properties = [];
        foreach ($this->settings as $setting) {
            $properties[$setting->name()] = $setting->toArray();
        }

        return array_filter([
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'category' => $this->category->value,
            'tags' => $this->tags,
            'pins' => $this->pins,
            'description' => $this->description,
            'hint' => $this->hint,
            'doclink' => $this->doclink,
            'previewHeight' => $this->previewHeight,
            'offered' => $this->offered,
            'isWrapper' => $this->wrapper,
            'addOnly' => $this->addOnly,
            'wrappedContentExample' => $this->wrappedExample,
            'properties' => $properties,
            'groups' => array_map(static fn (SettingGroup $g) => $g->toArray(), $this->groups),
        ], static fn ($value) => !($value === null || $value === false || $value === []));
    }

    private function with(callable $mutate): self
    {
        $clone = clone $this;
        $mutate($clone);

        return $clone;
    }
}
