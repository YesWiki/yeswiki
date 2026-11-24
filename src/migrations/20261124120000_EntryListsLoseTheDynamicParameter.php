<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** `dynamic` leaves the list actions: the template's folder says how it renders, and the way a list drawn in the browser opened an entry is written out for the server one. */
class EntryListsLoseTheDynamicParameter extends YesWikiMigration
{
    private const ACTIONS = 'entrylist|entrymap|calendar|entrytable|entryuserpage|bazarliste|bazarcarto|bazarcalendar|bazartableau';

    private const CALL = '/\{\{(\s*)(' . self::ACTIONS . ')(\s(?:"[^"]*"|[^}"])*)\}\}/i';

    private const ARGUMENT = '/\s*\b([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/';

    private const TRUE = ['1', 'true', 'oui', 'yes'];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');
        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE " . $db->jsonAsText('body') . " LIKE '%dynamic%'");

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = false;
            array_walk_recursive($body, function (&$value) use (&$changed): void {
                if (!is_string($value)) {
                    return;
                }
                $after = self::rewrite($value);
                if ($after !== $value) {
                    $value = $after;
                    $changed = true;
                }
            });
            if ($changed) {
                $db->query("UPDATE {$pages} SET body = ? WHERE id = ?", [PageBody::encode($body), (string)$row['id']]);
                $rewritten[(string)$row['tag']] = true;
            }
        }
        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        if ($rewritten !== []) {
            $this->say('Lists no longer take `dynamic`: it was taken out of ' . implode(', ', array_keys($rewritten)) . '.');
        }
    }

    /** Drops `dynamic` from the list calls of $content, keeping how a browser-drawn card, timeline or list opened its entries. */
    public static function rewrite(string $content): string
    {
        return (string)preg_replace_callback(self::CALL, static function (array $call): string {
            if (stripos($call[3], 'dynamic') === false) {
                return $call[0];
            }
            $arguments = [];
            preg_match_all(self::ARGUMENT, $call[3], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $arguments[strtolower($match[1])] = $match[2];
            }
            if (!array_key_exists('dynamic', $arguments)) {
                return $call[0];
            }

            $wasDynamic = in_array(strtolower(trim($arguments['dynamic'])), self::TRUE, true);
            unset($arguments['dynamic']);
            $template = (string)preg_replace('/\.(twig|tpl\.html)$/', '', $arguments['template'] ?? '');
            if ($wasDynamic && in_array($template, ['list', 'liste_accordeon'], true)) {
                $arguments['template'] = 'list';
                $arguments['entrydisplay'] ??= 'accordion';
            } elseif ($wasDynamic && in_array($template, ['card', 'timeline'], true)) {
                $arguments['entrydisplay'] ??= 'modal';
            }

            $written = '';
            foreach ($arguments as $name => $value) {
                $written .= ' ' . $name . '="' . $value . '"';
            }

            return '{{' . $call[1] . $call[2] . $written . '}}';
        }, $content);
    }
}
