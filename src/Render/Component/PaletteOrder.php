<?php

/* The palette's order: each category's components, most useful first. A component listed here is shown in that category whatever it declares; one left out keeps its own, after these. */

namespace YesWiki\Render\Component;

use YesWiki\Kernel\Component\Category;

final class PaletteOrder
{
    public const ORDER = [
        'writing' => ['section', 'button', 'grid', 'panel', 'accordion', 'tabs', 'label', 'include'],
        'media' => ['video', 'qrcode', 'document', 'qrcard', 'sonogramme'],
        'lists' => [
            'presentation-card', 'presentation-list', 'presentation-table', 'bazarannuaire', 'entrymap',
            'bazarmapandtable', 'bazarcarousel', 'bazarlistephotobox', 'bazarcalendar', 'bazaragenda',
            'presentation-timeline', 'bazarlisteliens', 'bazarblog',
        ],
        'navigation' => ['nav', 'tagcloud', 'toc'],
        'forms' => ['bazar', 'reactions', 'search', 'contact', 'subscribe', 'unsubscribe'],
        'admin' => [
            'adminreactions', 'mychanges', 'mypages', 'userreactions', 'listusers', 'pageindex',
            'pageonlyindex', 'recentcomments', 'recentchanges',
        ],
        'lms' => ['coursemenu', 'learnerdashboard'],
    ];

    /** The category this component is shown in, when the order above places it. */
    public static function categoryOf(string $id): ?Category
    {
        foreach (self::ORDER as $category => $ids) {
            if (in_array($id, $ids, true)) {
                return Category::from($category);
            }
        }

        return null;
    }

    /** Where this component comes within its category: its place above, or after all of them. */
    public static function rankOf(string $id): int
    {
        foreach (self::ORDER as $ids) {
            $rank = array_search($id, $ids, true);
            if ($rank !== false) {
                return $rank;
            }
        }

        return PHP_INT_MAX;
    }
}
