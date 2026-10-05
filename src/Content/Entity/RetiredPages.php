<?php

namespace YesWiki\Content\Entity;

/** The pages Doryphore installed for running a wiki, each with the screen that does its job now. */
final class RetiredPages
{
    /** @var array<string, array{0: string, 1: array<string, string>}> */
    private const SCREENS = [
        'gerersite' => ['admin', []],
        'gererconfig' => ['admin/config', []],
        'gererdroits' => ['admin/rights', []],
        'gererdroitsactions' => ['admin/rights', []],
        'gererdroitshandlers' => ['admin/rights', []],
        'gerermisesajour' => ['admin/updates', []],
        'gerermiseajour' => ['admin/updates', []],
        'gerersauvegardes' => ['admin/backups', []],
        'gererthemes' => ['admin/layout', []],
        'gererutilisateurs' => ['admin/users', []],
        'tableaudebord' => ['dashboard', []],
        'bazar' => ['dashboard', ['view' => 'formulaire']],
        'mescontenus' => ['user/pages', []],
        'parametresutilisateur' => ['user', []],
        'recherchetexte' => ['search', []],
        'reglesdeformatage' => ['doc', []],
    ];

    /** @return list<string> */
    public static function tags(): array
    {
        return array_keys(self::SCREENS);
    }

    /** @return array{0: string, 1: array<string, string>}|null the route and its query, whatever the case $tag is written in */
    public static function screenFor(string $tag): ?array
    {
        return self::SCREENS[mb_strtolower(trim($tag))] ?? null;
    }

    /** The screen as a link a menu node or a page stores. */
    public static function linkFor(string $tag): ?string
    {
        $screen = self::screenFor($tag);
        if ($screen === null) {
            return null;
        }

        return $screen[0] . ($screen[1] === [] ? '' : '?' . http_build_query($screen[1]));
    }
}
