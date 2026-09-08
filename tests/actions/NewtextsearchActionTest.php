<?php

namespace YesWiki\Test\Actions;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for the blind SQL injection in the {{newtextsearch}} action :
 * a Bazar list option's node id ($result['key']) was concatenated unescaped into
 * "body LIKE '...'" / "body REGEXP '...'" SQL fragments (actions/newtextsearch.php),
 * letting an attacker who controls a list option's id (e.g. via an anonymously
 * editable list on a default install) turn a search request into a SQL boolean
 * oracle to read arbitrary data.
 *
 * NB: a *second* {{newtextsearch}} call within the same PHP process hits an unrelated,
 * pre-existing FormManager/SearchManager caching bug in this environment (reproducible
 * even with an empty fixture and a phrase matching nothing), unrelated to this fix. Both
 * assertions therefore live in a single test method/single action call, to avoid
 * reintroducing that flake.
 */
class NewtextsearchActionTest extends YesWikiTestCase
{
    // SearchManager::searchInFormOptions() runs preg_quote() on the needle *after* it has
    // already been through SearchManager::prepareNeedleForRegexp() (which expands vowels
    // and a handful of consonants into accent-insensitive alternations, e.g. "e" becomes
    // "(e|è|é|ê|ë|E|È|É|Ê|Ë)") ; preg_quote() then escapes those parens/pipes as literal
    // characters, so the label match only succeeds if the phrase avoids every letter in
    // a,c,e,i,n,o,u,y entirely. Unrelated to this fix, just a constraint on the fixture.
    private const LIST_LABEL = 'ZZZ123WXDTESTFLAG';
    private const SECRET_MARKER = 'NEWTEXTSEARCH_SQLI_ORACLE_SECRET_MARKER';
    private const SECRET_PAGE_TAG = 'NewtextsearchSqliSecretPage';
    private const CONTROL_PAGE_TAG = 'NewtextsearchSqliControlPage';
    private const LIST_ID = 'NewtextsearchRegressionTestList';
    private const FORM_ID = '999901';
    private const FIELD_NAME = 'bf_regressiontestenum';

    public function testMaliciousListOptionIdCannotLeakDataAndLegitimateSearchStillWorks()
    {
        $wiki = $this->getWiki();
        // EntryManager::getHtmlDataAttributes() falls back to $GLOBALS['wiki'] when a form
        // isn't already in its local $formtab cache ; that global is normally populated by
        // the production HTTP bootstrap, not by the test harness. newtextsearch's underlying
        // SearchManager::searchWithLists() enumerates *every* form's EnumField options (not
        // just this test's), so any pre-existing entry-linked select field elsewhere in the
        // (shared) test DB would otherwise crash here regardless of this fix.
        $GLOBALS['wiki'] = $wiki;
        $pageManager = $wiki->services->get(PageManager::class);
        $listManager = $wiki->services->get(ListManager::class);
        $formManager = $wiki->services->get(FormManager::class);

        // a "secret" page, unrelated to the search phrase, that only a successful SQL
        // injection (boolean-oracle condition) could surface in the results
        $pageManager->save(self::SECRET_PAGE_TAG, self::SECRET_MARKER, '', true);
        // a legitimate page the search phrase genuinely matches, proving the fix doesn't
        // break normal search (and that the fixture actually exercises the query)
        $pageManager->save(self::CONTROL_PAGE_TAG, self::LIST_LABEL, '', true);

        // malicious list option id : breaks out of the "body LIKE '...'" string, OR-injects
        // a second condition true only if a page's body contains the secret marker (the
        // reported "boolean oracle" mechanism), then closes the 3 SQL parens opened before
        // this point (main AND-group, the " OR (...)" wrap, the per-needle group) and uses
        // '#' to comment out the rest of the query. Verified end-to-end against a real
        // MariaDB 11.4 connection before/after the fix.
        $maliciousKey = "x' OR body LIKE '%" . self::SECRET_MARKER . "%' ))) #";
        $listManager->create('Newtextsearch regression test list', [
            ['id' => $maliciousKey, 'label' => self::LIST_LABEL],
        ], self::LIST_ID);

        $templateRow = array_fill(0, 16, '');
        $templateRow[0] = 'liste';
        $templateRow[1] = self::LIST_ID;
        $templateRow[6] = self::FIELD_NAME;
        $formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'Newtextsearch regression test form',
            'bn_template' => implode('***', $templateRow),
            'bn_condition' => '',
        ]);

        try {
            // the phrase matches the malicious option's *label*, so $result['key'] (the
            // malicious id) gets concatenated into the query, per searchInFormOptions()
            $html = $wiki->Action('newtextsearch', 1, ['phrase' => self::LIST_LABEL]);

            $this->assertStringNotContainsString(
                self::SECRET_PAGE_TAG,
                $html,
                'the SQL injection leaked an unrelated page through the boolean-oracle condition'
            );
            // sanity check on the same response : proves the fixture/query actually ran (a
            // query that always fails would make the assertion above vacuous), and that
            // escaping didn't break normal search
            $this->assertStringContainsString(self::CONTROL_PAGE_TAG, $html);
        } finally {
            $pageManager->deleteOrphaned(self::SECRET_PAGE_TAG);
            $pageManager->deleteOrphaned(self::CONTROL_PAGE_TAG);
            // equivalent to ListManager::delete(), bypassing its UserIsAdmin()/UserIsOwner()
            // check (there's no logged-in admin in this test harness)
            $pageManager->deleteOrphaned(self::LIST_ID);
            $wiki->services->get(TripleStore::class)->delete(self::LIST_ID, TripleStore::TYPE_URI, null, '', '');
            $formManager->delete(self::FORM_ID);
            unset($GLOBALS['wiki']);
        }
    }
}
