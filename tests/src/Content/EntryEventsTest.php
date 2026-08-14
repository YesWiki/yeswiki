<?php

namespace YesWiki\Test\Content;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Federation\Service\FederatesEntryChanges;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `entry.created`, `entry.updated` and `entry.deleted` are announced by **`EntryManager`**,
 * so every write path announces them.
 *
 * They used to come from `EntryController`, which is the form-submission path and only that.
 * An entry written by the API, an importer, a migration or a field's own side effect fired
 * none of them — so every subscriber was quietly partial, and the two mechanisms that looked
 * complete only were by accident: search reindexing survived because `PageManager` dispatches
 * `page.*` underneath, and ActivityPub survived because it was called directly rather than
 * subscribing.
 *
 * That is why this test drives `EntryManager` rather than the controller: the whole point of
 * ticket 39's consolidation is the path the controller is *not* on.
 */
class EntryEventsTest extends YesWikiTestCase
{
    protected function setUp(): void
    {
        // updating and deleting an entry are ACL-checked, and an anonymous visitor owns
        // nothing -- so these tests would fail on permissions rather than on events
        $wiki = self::getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }

    protected function tearDown(): void
    {
        self::getWiki()->services->get(AuthenticationService::class)->logout();
    }

    /**
     * Records every entry event it hears, in order.
     *
     * A named class rather than an anonymous one: phpstan can see neither
     * `EventSubscriberInterface` nor `$heard` through `object`, and a spy whose recordings the
     * analyser cannot check is a poor thing to assert on.
     */
    private static function spy(): EntryEventSpy
    {
        $spy = new EntryEventSpy();
        self::getWiki()->services->get(EventDispatcher::class)->addSubscriber($spy);

        return $spy;
    }

    /**
     * Create an entry carrying only a title, on whichever seeded form will accept one.
     *
     * Neither half of that is guessable. Which field is the title is a **role** the form
     * assigns (ticket 11) -- the directory form's is `bf_nom`, not `bf_titre` -- and the first
     * form with a title also demands an event start date, so a title-only entry is rejected on
     * validation rather than on anything these tests are about. Asking each form in turn and
     * keeping the one that accepts is shorter than reimplementing "which fields are required",
     * and it does not go stale when the seed content changes.
     *
     * @return array{array<string, mixed>, string, string} the entry, its form id, its title field
     */
    private static function createTitleOnlyEntry(string $title): array
    {
        $wiki = self::getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $properties = $wiki->services->get(FormPropertiesService::class);

        foreach ($wiki->services->get(FormManager::class)->getAll() as $form) {
            $titleField = $properties->titleFieldName($form);
            if (empty($form['id']) || !is_string($titleField) || $titleField === '') {
                continue;
            }
            try {
                $entry = $entryManager->create((string)$form['id'], [$titleField => $title, 'antispam' => 1]);
            } catch (\Throwable) {
                continue; // this form wants more than a title; the next one may not
            }
            if (!empty($entry['tag'])) {
                return [$entry, (string)$form['id'], $titleField];
            }
        }

        self::fail('no seeded form accepts an entry with only a title, so nothing can be written');
    }

    public function testEveryWritePathAnnounces(): void
    {
        $wiki = self::getWiki();
        $spy = self::spy();

        $entryManager = $wiki->services->get(EntryManager::class);

        // straight at the manager: no controller, no request, no form submission
        [$entry, , $titleField] = self::createTitleOnlyEntry('EntryEventsTest subject');
        $tag = (string)$entry['tag'];

        $entryManager->update($tag, array_merge($entry, [$titleField => 'EntryEventsTest renamed', 'antispam' => 1]), false, true);
        $entryManager->delete($tag);

        $names = array_column($spy->heard, 0);
        $this->assertSame(
            ['entry.created', 'entry.updated', 'entry.deleted'],
            $names,
            'each write announced exactly once — a duplicate means a caller is still dispatching too'
        );
    }

    /**
     * The two keys the consolidation added, and the reason they exist: a subscriber that
     * publishes outward has to know whose followers to tell, and has to stay quiet about
     * content this wiki imported rather than authored.
     */
    public function testTheEventCarriesTheFormAndWhetherItWasImported(): void
    {
        $wiki = self::getWiki();
        $spy = self::spy();

        $entryManager = $wiki->services->get(EntryManager::class);
        [$entry] = self::createTitleOnlyEntry('EntryEventsTest payload');

        [$name, $payload] = $spy->heard[0];
        $this->assertSame('entry.created', $name);
        $this->assertSame($entry['tag'], $payload['id']);
        $this->assertArrayHasKey('form', $payload, 'federation cannot tell whose followers to notify without it');
        $this->assertArrayHasKey('imported', $payload, 'without it an imported entry is re-published as our own');
        $this->assertFalse($payload['imported'], 'an entry created here is not imported');

        $entryManager->delete((string)$entry['tag']);
    }

    /** One mechanism, not two: federation is a subscriber like everything else now. */
    public function testFederationSubscribesRatherThanBeingCalled(): void
    {
        $this->assertContains(
            'entry.created',
            array_keys(FederatesEntryChanges::getSubscribedEvents()),
            'federation must hear about entries the same way every other listener does'
        );

        // the rule is about the dependency, not the vocabulary: a docblock may well explain
        // why federation is not here, and asserting on the word alone failed on exactly that
        $entryManagerSource = (string)file_get_contents(dirname(__DIR__, 3) . '/src/Content/Service/EntryManager.php');
        $this->assertDoesNotMatchRegularExpression(
            '/\\bYesWiki\\\\Federation\\\\/',
            $entryManagerSource,
            'Content must not depend on Federation (ADR-0019)'
        );
    }

    /** Guards the shape the events promise, for subscribers that read the body. */
    public function testTheAnnouncedEntryIsTheStoredOne(): void
    {
        $wiki = self::getWiki();
        $spy = self::spy();

        $entryManager = $wiki->services->get(EntryManager::class);
        [$entry, , $titleField] = self::createTitleOnlyEntry('EntryEventsTest body');

        [, $payload] = $spy->heard[0];
        $stored = $entryManager->getOne((string)$entry['tag']);
        $this->assertSame(
            $stored[$titleField] ?? null,
            $payload['data'][$titleField] ?? null,
            'the event carries the entry as written'
        );

        $entryManager->delete((string)$entry['tag']);
    }
}

/** @see EntryEventsTest */
class EntryEventSpy implements EventSubscriberInterface
{
    /** @var list<array{string, array<string, mixed>}> */
    public array $heard = [];

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            'entry.created' => 'record',
            'entry.updated' => 'record',
            'entry.deleted' => 'record',
        ];
    }

    public function record(Event $event, ?string $name = null): void
    {
        $this->heard[] = [(string)$name, $event->getData()];
    }
}
