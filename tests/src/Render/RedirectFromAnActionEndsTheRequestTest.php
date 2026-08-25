<?php

namespace YesWiki\Test\Render;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * An action that redirects ends the request, even though Twig has re-wrapped the exception saying so.
 *
 * Found through the Journal (ticket 51), which is a fair advertisement for it. `Performer` caught
 * the redirect as an ordinary failure and re-rendered the whole page as an error page, running
 * every action in the chrome a second time -- so a failed sign-in was recorded twice and its
 * password checked twice, and every other action that redirects from inside a template did its
 * work twice too.
 */
class RedirectFromAnActionEndsTheRequestTest extends YesWikiTestCase
{
    public function testARedirectSurvivesBeingWrappedByTwig(): void
    {
        $redirect = new ExitException('');
        $asTwigRethrowsIt = new \Twig\Error\RuntimeError('An exception has been thrown', -1, null, $redirect);

        $this->assertSame($redirect, ExitException::in($asTwigRethrowsIt));
        $this->assertSame($redirect, ExitException::in($redirect));
    }

    public function testAnOrdinaryFailureIsStillAFailure(): void
    {
        $this->assertNull(ExitException::in(new \RuntimeException('something actually broke')));
        $this->assertNull(ExitException::in(
            new \Twig\Error\RuntimeError('An exception has been thrown', -1, null, new \RuntimeException('boom'))
        ));
    }

    /**
     * The whole point, end to end: one attempt, one entry.
     *
     * Run as the `show` handler, which is the level the bug was at. The login action throws a bare
     * `ExitException`, the Performer running it rethrows, Twig wraps it on the way out of the
     * chrome template, and it is the Performer running the *handler* that used to catch the
     * wrapper and render the page again. Rendering the chrome alone reproduces none of that.
     */
    public function testAFailedSignInIsRecordedOnce(): void
    {
        $wiki = self::getWiki();
        $db = $wiki->services->get(DbService::class);
        $table = $db->quoteIdentifier($wiki->services->get(JournalSchema::class)->table());
        $action = $db->quoteIdentifier('action');

        $before = $wiki->services->get(CurrentRequest::class)->get();
        $wasOn = $wiki->services->get(PageContext::class)->getTag();
        // The action ignores a `login` it was not asked for on the page it is rendering.
        $wiki->services->get(PageContext::class)->setTag('PagePrincipale');
        $db->query("DELETE FROM {$table} WHERE {$action} = 'login.failed'");

        $wiki->services->get(CurrentRequest::class)->replace(Request::create(
            '/?PagePrincipale',
            'POST',
            ['action' => 'login', 'context' => 'PagePrincipale', 'name' => 'NoSuchPersonAtAll', 'password' => 'nope'],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.7']
        ));

        try {
            $wiki->services->get(Performer::class)->run('show', 'handler');
            $this->fail('the redirect must reach the runtime, not be swallowed into an error page');
        } catch (\Throwable $thrown) {
            $this->assertNotNull(ExitException::in($thrown), 'a redirect, however Twig wrapped it');
        } finally {
            $wiki->services->get(CurrentRequest::class)->replace($before);
            $wiki->services->get(PageContext::class)->setTag($wasOn);
        }

        $rows = $db->loadAll("SELECT * FROM {$table} WHERE {$action} = 'login.failed'");
        $db->query("DELETE FROM {$table} WHERE {$action} = 'login.failed'");

        $this->assertCount(1, $rows, 'one attempt is one entry');
        $this->assertSame('NoSuchPersonAtAll', $rows[0]['target'], 'whoever they tried to be');
        $this->assertSame('warning', $rows[0]['level'], 'an act, at a level that says it did not work');
    }
}
