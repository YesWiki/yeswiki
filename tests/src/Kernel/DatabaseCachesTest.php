<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CachesTheDatabase;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\RequestScope;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 01: what a service caches of the database lasts one request, or one message of a consumer, since another process may write in between. */
class DatabaseCachesTest extends YesWikiTestCase
{
    private const PAGE = 'DatabaseCachesProbe';

    private string $user = '';

    private string $admins = '';

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->admins = $services->get(GroupOperationsService::class)->getMembersText(ADMIN_GROUP);
        $this->user = 'Cached' . bin2hex(random_bytes(4));
        $services->get(UserManager::class)->create($this->user, strtolower($this->user) . '@example.com', 'a-solid-password');
        $services->get(PageManager::class)->save(self::PAGE, [PageBody::CONTENT => 'first'], '', true);
    }

    protected function tearDown(): void
    {
        $services = self::getWiki()->services;
        $services->get(GroupOperationsService::class)->setMembersFromAclText(ADMIN_GROUP, $this->admins);
        $users = $services->get(UserManager::class);
        $user = $users->getOneByName($this->user);
        if (!empty($user)) {
            $users->delete($user);
        }
        $services->get(PageManager::class)->deleteOrphaned(self::PAGE);
        parent::tearDown();
    }

    public function testTheNextRequestSeesWhatAnotherProcessWrote(): void
    {
        $services = self::getWiki()->services;
        $this->assertSame('first', $this->bodyOfThePage());
        $this->assertFalse($services->get(AclService::class)->isAdmin($this->user));

        $this->anotherProcessRewritesThePage('second');
        $this->anotherProcessMakesTheUserAnAdmin();
        $services->get(RequestScope::class)->startNewRequest();

        $this->assertSame('second', $this->bodyOfThePage(), 'the page is read again');
        $this->assertTrue($services->get(AclService::class)->isAdmin($this->user), 'and so is the admins group');
    }

    public function testAConsumerForgetsTheDatabaseBetweenTwoMessages(): void
    {
        $services = self::getWiki()->services;
        $this->assertSame('first', $this->bodyOfThePage());

        $this->anotherProcessRewritesThePage('second');
        $services->get(QueueConsumer::class)->onMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'queue'));

        $this->assertSame('second', $this->bodyOfThePage());
    }

    public function testEveryServiceThatCachesRowsSaysSo(): void
    {
        $offenders = [];
        foreach (self::serviceClasses() as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isInterface() || $reflection->implementsInterface(CachesTheDatabase::class)) {
                continue;
            }
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->getDeclaringClass()->getName() === $class && preg_match('/[cC]ache$/', $property->getName()) === 1) {
                    $offenders[] = $class . '::$' . $property->getName();
                }
            }
        }

        $this->assertSame([], $offenders, 'a property named *Cache keeps rows across requests unless its service implements CachesTheDatabase');
    }

    /** @return list<class-string> every class of a module's Service folder */
    private static function serviceClasses(): array
    {
        $classes = [];
        foreach (glob('src/*/Service', GLOB_ONLYDIR) ?: [] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || preg_match('/^namespace ([^;]+);/m', (string)file_get_contents($file->getPathname()), $namespace) !== 1) {
                    continue;
                }
                $class = $namespace[1] . '\\' . $file->getBasename('.php');
                if (class_exists($class) || interface_exists($class)) {
                    $classes[] = $class;
                }
            }
        }
        sort($classes);

        return $classes;
    }

    private function bodyOfThePage(): string
    {
        $page = self::getWiki()->services->get(PageManager::class)->getOne(self::PAGE);

        if (!is_array($page)) {
            return '';
        }
        $body = $page['body'];

        return PageBody::content(is_array($body) ? $body : PageBody::decode(is_string($body) ? $body : null));
    }

    private function anotherProcessRewritesThePage(string $content): void
    {
        $other = new DbService(self::getWiki()->services->get(ParameterBagInterface::class));
        $other->query(
            'UPDATE' . $other->prefixTable('pages') . "SET body = ? WHERE tag = ? AND latest = 'Y'",
            [PageBody::encode([PageBody::CONTENT => $content]), self::PAGE]
        );
    }

    private function anotherProcessMakesTheUserAnAdmin(): void
    {
        exec('./yeswicli user:add-to-group ' . escapeshellarg($this->user) . ' ' . escapeshellarg(ADMIN_GROUP) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }
}
