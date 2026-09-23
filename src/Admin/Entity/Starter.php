<?php

namespace YesWiki\Admin\Entity;

/** One choice on the onboarding screen: a form with its value lists, pages, menu and navbar entry. */
final class Starter
{
    /**
     * @param array<string, mixed>                                               $form
     * @param list<array{tag: string, title: string, nodes: list<array<mixed>>}> $lists
     * @param array<string, string>                                              $pages
     * @param list<array{tag: string, title: string, nodes: list<array<mixed>>}> $menus
     * @param array{label: string, link: string}                                 $navigation
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $description,
        public readonly string $icon,
        public readonly array $form,
        public readonly array $lists,
        public readonly array $pages,
        public readonly array $menus,
        public readonly array $navigation,
    ) {
    }

    /** The Starter a JSON file describes, or null when the file does not describe one. */
    public static function fromJson(string $slug, string $json): ?self
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !is_array($data['form'] ?? null) || !is_array($data['pages'] ?? null) || !is_array($data['navigation'] ?? null)) {
            return null;
        }

        $pages = array_filter($data['pages'], 'is_string');
        $navigation = $data['navigation'];
        if (!is_string($navigation['label'] ?? null) || !is_string($navigation['link'] ?? null)) {
            return null;
        }

        return new self(
            $slug,
            is_string($data['label'] ?? null) ? $data['label'] : $slug,
            is_string($data['description'] ?? null) ? $data['description'] : '',
            is_string($data['icon'] ?? null) ? $data['icon'] : 'file',
            $data['form'],
            self::rows($data['lists'] ?? []),
            array_combine(array_map('strval', array_keys($pages)), array_values($pages)),
            self::rows($data['menus'] ?? []),
            ['label' => $navigation['label'], 'link' => $navigation['link']],
        );
    }

    /** @return list<array{tag: string, title: string, nodes: list<array<mixed>>}> */
    private static function rows(mixed $rows): array
    {
        $valid = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && is_string($row['tag'] ?? null) && is_string($row['title'] ?? null) && is_array($row['nodes'] ?? null)) {
                $valid[] = ['tag' => $row['tag'], 'title' => $row['title'], 'nodes' => array_values(array_filter($row['nodes'], 'is_array'))];
            }
        }

        return $valid;
    }
}
