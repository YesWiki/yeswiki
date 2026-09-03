<?php

namespace YesWiki\Content\Entity;

/**
 * One entry of a menu: what it says, where it goes, and what it looks like (ticket 64 / ADR-0028).
 *
 * `id` is an opaque handle and never the link, so a parent may open a dropdown pointing nowhere and
 * two entries may lead to the same page. The link is a page tag, a url or an anchor.
 */
final class MenuNode
{
    /** Where a node's icon comes from. */
    public const ICON_SPRITE = 'sprite';
    public const ICON_EMOJI = 'emoji';
    public const ICON_FILE = 'file';

    /** @var list<string> */
    public const ICON_SOURCES = [self::ICON_SPRITE, self::ICON_EMOJI, self::ICON_FILE];

    /**
     * @param list<MenuNode> $children never more than one level deep: ADR-0028 commits every
     *                                 renderer to exactly two, so nothing has to truncate anything
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $link = '',
        public readonly ?string $iconSource = null,
        public readonly string $iconValue = '',
        public readonly array $children = [],
    ) {
    }

    /**
     * @param array<string, mixed> $node    as stored in a menu body
     * @param bool                 $nesting whether children are read: false for a second level,
     *                                      which is where the two-level rule is enforced
     */
    public static function fromArray(array $node, bool $nesting = true): ?self
    {
        $label = is_string($node['label'] ?? null) ? trim($node['label']) : '';
        $link = is_string($node['link'] ?? null) ? trim($node['link']) : '';
        if ($label === '' && $link === '') {
            return null;
        }

        $children = [];
        if ($nesting) {
            foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
                $childNode = is_array($child) ? self::fromArray($child, false) : null;
                if ($childNode !== null) {
                    $children[] = $childNode;
                }
            }
        }

        [$source, $value] = self::iconOf($node['icon'] ?? null);

        return new self(
            id: self::idOf($node),
            label: $label,
            link: $link,
            iconSource: $source,
            iconValue: $value,
            children: $children,
        );
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function iconOf(mixed $icon): array
    {
        if (!is_array($icon)) {
            return [null, ''];
        }
        $source = is_string($icon['source'] ?? null) ? $icon['source'] : '';
        $value = is_string($icon['value'] ?? null) ? trim($icon['value']) : '';

        return (in_array($source, self::ICON_SOURCES, true) && $value !== '') ? [$source, $value] : [null, ''];
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function idOf(array $node): string
    {
        $id = is_string($node['id'] ?? null) ? trim($node['id']) : '';

        return $id !== '' ? $id : uniqid('n');
    }

    public function hasIcon(): bool
    {
        return $this->iconSource !== null && $this->iconValue !== '';
    }

    /**
     * @return array<string, mixed> the node as a menu body holds it
     */
    public function toArray(): array
    {
        $node = ['id' => $this->id, 'label' => $this->label, 'link' => $this->link];
        if ($this->hasIcon()) {
            $node['icon'] = ['source' => $this->iconSource, 'value' => $this->iconValue];
        }
        if ($this->children !== []) {
            $node['children'] = array_map(static fn (self $child): array => $child->toArray(), $this->children);
        }

        return $node;
    }
}
