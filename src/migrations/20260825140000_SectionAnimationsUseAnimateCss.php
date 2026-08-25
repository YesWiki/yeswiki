<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** `{{section animation="wow bounce"}}` becomes animate.css 4's own class names. */
class SectionAnimationsUseAnimateCss extends YesWikiMigration
{
    /** Old class pair => the animate.css 4 one. */
    private const RENAMES = [
        'wow bounce' => 'animate__animated animate__bounce',
        'wow flash' => 'animate__animated animate__flash',
        'wow pulse' => 'animate__animated animate__pulse',
        'wow rubberBand' => 'animate__animated animate__rubberBand',
        'wow shakeX' => 'animate__animated animate__shakeX',
        'wow shakeY' => 'animate__animated animate__shakeY',
        'wow headShake' => 'animate__animated animate__headShake',
        'wow swing' => 'animate__animated animate__swing',
        'wow tada' => 'animate__animated animate__tada',
        'wow wobble' => 'animate__animated animate__wobble',
        'wow jello' => 'animate__animated animate__jello',
        'wow heartBeat' => 'animate__animated animate__heartBeat',
    ];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE " . $db->jsonAsText('body')
            . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains('animation=')]
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $content = PageBody::content($body);
            if ($content === '') {
                continue;
            }

            $updated = self::rewrite($content);
            if ($updated === $content) {
                continue;
            }

            $body[PageBody::CONTENT] = $updated;
            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($body), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        if ($rewritten !== []) {
            $log->log(
                'migration',
                'section animations were rewritten from the WOW.js class names to animate.css 4 in '
                . count($rewritten) . ' page(s), across all revisions: '
                . implode(', ', array_keys($rewritten))
            );
        }
    }

    /** Rewrite only an `animation="..."` value, so the words are never touched in prose. */
    public static function rewrite(string $content): string
    {
        return (string)preg_replace_callback(
            '/(\banimation\s*=\s*)(["\'])(.*?)\2/s',
            static function (array $match): string {
                $value = trim($match[3]);

                return isset(self::RENAMES[$value])
                    ? $match[1] . $match[2] . self::RENAMES[$value] . $match[2]
                    : $match[0];
            },
            $content
        );
    }
}
