<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryListCategoryRewriter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 49: `{{entrylistcategory}}` is retired, and the list actions answer to one ACL. */
class RetireEntryListCategory extends YesWikiMigration
{
    /** Deprecated spellings of `{{entrylist}}`, which is now what they are checked against. */
    private const ALIASES = ['entrymap', 'entrytable', 'entryuserpage', 'calendar'];

    public function run()
    {
        $this->rewriteStoredCalls();
        $this->cleanActionAcls();
    }

    private function rewriteStoredCalls(): void
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');

        $bodyAsText = $db->jsonAsText('body');
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE {$bodyAsText} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX
            . " OR {$bodyAsText} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains('entrylistcategory'), SqlParameters::likeContains('bazarlistecategorie')]
        );

        $rewriter = new EntryListCategoryRewriter();
        $rewritten = [];
        $lost = [];
        foreach ($rows as $row) {
            $rewriter->forgetDropped();
            $changed = $rewriter->rewriteBody(PageBody::decode((string)$row['body']));
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
            $dropped = $rewriter->droppedParameters();
            if ($dropped !== []) {
                $lost[(string)$row['tag']] = $dropped;
            }
        }

        if ($rewritten === []) {
            return;
        }

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        $this->say(
            'entrylistcategory is retired: an entry list grouped on a field is what it drew, so it '
            . 'is written {{entrylist groups="..." template="' . EntryListCategoryRewriter::TEMPLATE
            . '"}} now. Rewritten in ' . count($rewritten) . ' page(s), across all revisions: '
            . implode(', ', array_keys($rewritten)) . '. The action had not worked since its '
            . 'conversion to a class -- it read one argument as both the form and the grouping '
            . 'field -- so these lists should show more than they did, not less.'
        );

        if ($lost !== []) {
            $described = [];
            foreach ($lost as $tag => $parameters) {
                $described[] = $tag . ' (' . implode(', ', $parameters) . ')';
            }
            $this->say(
                'These calls said something a grouped entry list has no way to say: '
                . implode('; ', $described)
                . '. "list" named the page holding the list values, which a facet reads from the '
                . 'field itself; "template" named the template each group was drawn with, and the '
                . 'accordion draws them now; "groups" means the call never named a field to group '
                . 'on, so that page shows an ungrouped list. Check those pages by hand.'
            );
        }
    }

    /** Action ACLs that no longer address anything. */
    private function cleanActionAcls(): void
    {
        $params = $this->services->get(ParameterBagInterface::class);
        $permissions = $params->has('permissions') ? $params->get('permissions') : [];
        if (!is_array($permissions) || empty($permissions['action']) || !is_array($permissions['action'])) {
            return;
        }

        $actions = $permissions['action'];
        $dropped = [];
        foreach (array_merge(['entrylistcategory'], self::ALIASES) as $name) {
            if (!array_key_exists($name, $actions)) {
                continue;
            }
            $dropped[] = $name . ' ("' . $actions[$name] . '")';
            unset($actions[$name]);
        }

        if ($dropped === []) {
            return;
        }

        $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $stored = $config['permissions'] ?? [];
        $stored['action'] = $actions;
        $config['permissions'] = $stored;
        $config->write();

        $this->say(
            'These action permissions no longer address anything and were removed: '
            . implode(', ', $dropped)
            . '. entrylistcategory is retired; entrymap, entrytable, entryuserpage and calendar are '
            . 'deprecated spellings of entrylist and are checked against its permission (ticket 49). '
            . 'Set the permission on entrylist if one of these was restricting who could list entries.'
        );
    }
}
