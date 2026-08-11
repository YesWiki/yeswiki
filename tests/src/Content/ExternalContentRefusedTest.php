<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 34: a list may no longer name another wiki.
 *
 * `{{entrylist id="https://other.wiki|4"}}` fetched that wiki's entries over HTTP on every page
 * view. Content from elsewhere is imported now, so it is this wiki's -- searchable, in the search
 * index, under this wiki's ACLs, and there when the source wiki is not.
 *
 * The refusal has to be *visible*, which is why these tests care about the exception type as much
 * as the fact that it throws: Performer renders an HttpException as its message alone, so the
 * reader gets an explanation in place of the list and the rest of the page still works. A plain
 * \Exception would come out wrapped in PERFORMABLE_ERROR and a stack trace.
 */
#[CoversMethod(BazarListService::class, 'getForms')]
#[CoversMethod(BazarListService::class, 'getEntries')]
class ExternalContentRefusedTest extends YesWikiTestCase
{
    private function service(): BazarListService
    {
        return $this->getWiki()->services->get(BazarListService::class);
    }

    /** @return array<string, array{string}> */
    public static function externalIdProvider(): array
    {
        return [
            'a remote form' => ['https://other.wiki|4'],
            'several remote forms' => ['https://other.wiki|4,5'],
            'parenthesised' => ['https://other.wiki|(4,5)'],
            'mapped onto a local form' => ['https://other.wiki|4->2'],
            'http as well as https' => ['http://other.wiki|4'],
        ];
    }

    #[DataProvider('externalIdProvider')]
    public function testGetFormsRefusesAnIdNamingAnotherWiki(string $id): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->service()->getForms(['id' => $id]);
    }

    #[DataProvider('externalIdProvider')]
    public function testGetEntriesRefusesAnIdNamingAnotherWiki(string $id): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->service()->getEntries(['id' => $id]);
    }

    /** The message has to be usable: it names what was asked for and what to do instead. */
    public function testTheRefusalExplainsItselfAndNamesTheSource(): void
    {
        try {
            $this->service()->getForms(['id' => 'https://other.wiki|7']);
            $this->fail('an external id must be refused');
        } catch (BadRequestHttpException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('other.wiki', $message, 'the reader must see which wiki was asked');
            $this->assertStringContainsString('7', $message, 'and which form');
            $this->assertNotSame(
                'BAZ_EXTERNAL_IDS_REMOVED',
                $message,
                'the translation key leaked instead of the sentence'
            );
        }
    }

    /** A local id is the ordinary case and must be entirely unaffected. */
    public function testALocalIdIsUntouched(): void
    {
        // whether form 1 exists on this wiki is not the point; getting an answer at all is
        $forms = $this->service()->getForms(['id' => '1']);

        $this->assertArrayNotHasKey('externals', $forms);
    }

    public function testAnEmptyIdListsEveryLocalForm(): void
    {
        // '' means "every local form", so this must not throw and must not be keyed by anything
        // resembling the old externals bucket
        $this->assertArrayNotHasKey('externals', $this->service()->getForms(['id' => '']));
    }

    /**
     * The parser stays, and that is deliberate: telling a url-form id from a local one is what
     * makes the notice possible at all. Deleting the parse would turn a refusal with an
     * explanation into "Invalid ID".
     */
    public function testTheIdParserStillDistinguishesLocalFromExternal(): void
    {
        $parsed = $this->service()->getIDs('https://other.wiki|4');

        $this->assertSame([], $parsed['locals']);
        $this->assertCount(1, $parsed['externals']);
        $this->assertSame('4', $parsed['externals'][0]['id']);
        $this->assertStringContainsString('other.wiki', $parsed['externals'][0]['url']);
    }

    /**
     * Nothing under src/ may reach an external site to render a page any more. Asserted against
     * the source rather than by behaviour, because the failure this guards against is someone
     * reintroducing a fetch in a code path no test happens to cover.
     */
    public function testNoFieldFetchesARemoteSiteToRenderItself(): void
    {
        // Fields only. Services legitimately reach the network outside rendering -- ImportService
        // and ImportFilesManager download during an import, DuplicationManager copies a whole
        // wiki -- and lumping them in would make this assertion something to be suppressed rather
        // than kept. A *field* renders, so a field has no business doing it at all.
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(YESWIKI_SOURCE_DIR . '/src/Content/Field')
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            // Tokenised, not grepped. A regex over the source also matches the words inside a
            // comment -- including the comment in LinkedEntryField explaining that the fetch was
            // removed, which made this fail while the code was correct. A guard that trips on
            // prose is a guard someone deletes.
            foreach (token_get_all((string)file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token) || $token[0] !== T_STRING) {
                    continue;
                }
                if (in_array(strtolower($token[1]), ['file_get_contents', 'curl_exec', 'curl_init', 'fopen'], true)) {
                    $offenders[] = $file->getFilename();
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'a field must not fetch over the network to render: ticket 34 removed render-time '
            . 'dependencies on other sites. This is what caught LinkedEntryField, which reached a '
            . 'remote form with a bare file_get_contents() without going through '
            . 'ExternalBazarService -- so deleting that service would not have found it.'
        );
    }
}
