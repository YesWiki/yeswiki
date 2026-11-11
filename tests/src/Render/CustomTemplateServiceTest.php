<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\CustomTemplateService;
use YesWiki\Render\Service\TemplateOverrides;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Template overrides as something the wiki can show and edit (ticket 30), saved in the database over the operator's files (ticket 18). */
class CustomTemplateServiceTest extends YesWikiTestCase
{
    /** Under the real directory, because the confinement being tested is about that path. */
    private const PROBE_DIR = CustomTemplateService::DIRECTORY . '/core/__probe__';

    private const PROBE = 'core/__probe__/probe.twig';

    /** @var list<string> */
    private array $missingDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->missingDirectories = array_values(array_filter(
            [CustomTemplateService::DIRECTORY . '/core', CustomTemplateService::DIRECTORY],
            static fn (string $directory) => !is_dir($directory)
        ));
    }

    protected function tearDown(): void
    {
        $database = $this->getWiki()->services->get(TemplateOverrides::class);
        foreach ($database->all() as $override) {
            if (str_starts_with($override['path'], 'core/__probe__/') || str_starts_with($override['path'], 'core/admin/')) {
                $database->delete($override['path']);
            }
        }
        foreach (glob(self::PROBE_DIR . '/*') ?: [] as $leftover) {
            @unlink($leftover);
        }
        @rmdir(self::PROBE_DIR);
        foreach ($this->missingDirectories as $directory) {
            @rmdir($directory);
        }
        parent::tearDown();
    }

    private function service(): CustomTemplateService
    {
        return $this->getWiki()->services->get(CustomTemplateService::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedPathProvider(): array
    {
        return [
            'traversal' => ['core/../../../evil.twig'],
            'traversal in the middle' => ['core/../../evil.twig'],
            'a bare ..' => ['../evil.twig'],
            'an absolute path' => ['/etc/passwd'],
            'not a twig file' => ['core/evil.php'],
            'no extension at all' => ['core/evil'],
            'nothing' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedPathProvider')]
    public function testAWriteThatWouldEscapeTheDirectoryIsRefused(string $path): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->write($path, 'nothing here');
    }

    /** An absolute path is refused, and above all it is not *reinterpreted*. */
    public function testAnAbsolutePathIsRefusedRatherThanReinterpreted(): void
    {
        $outside = '/tmp/yeswiki-probe-escape.twig';
        $reinterpreted = CustomTemplateService::DIRECTORY . '/tmp/yeswiki-probe-escape.twig';

        try {
            $this->service()->write($outside, 'escaped');
            $this->fail('an absolute path must be refused');
        } catch (\RuntimeException) {
        }

        $this->assertFileDoesNotExist($outside);
        $this->assertFileDoesNotExist($reinterpreted, 'and not quietly relativised into the directory either');
    }

    public function testATemplateThatDoesNotCompileIsRefusedAndNothingIsWritten(): void
    {
        $service = $this->service();

        try {
            $service->write(self::PROBE, '{% block body %}never closed');
            $this->fail('a template that cannot parse must not be accepted');
        } catch (\RuntimeException $refused) {
            $this->assertStringContainsString('block', $refused->getMessage());
        }

        $this->assertFalse($service->exists(self::PROBE), 'the wiki still renders what it rendered');
    }

    /** The check has to run in the wiki's own Twig environment. */
    public function testTheWikisOwnHelpersAreNotSyntaxErrors(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, "{{ _t('SAVE') }}{{ icon('star') }}{{ url({tag: 'x'}) }}");

        $this->assertTrue($service->exists(self::PROBE));
    }

    public function testATemplateThatExtendsACoreOneIsAccepted(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, "{% extends '@core/dashboard/layout.twig' %}{% block dashboard_content %}hi{% endblock %}");

        $this->assertTrue($service->exists(self::PROBE));
    }

    public function testAnOverrideIsWrittenReadBackAndListed(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, 'hello');

        $this->assertSame('hello', $service->read(self::PROBE));

        $listed = array_column($service->overrides(), null, 'path');
        $this->assertArrayHasKey(self::PROBE, $listed);

        $this->assertFalse($listed[self::PROBE]['shipped']);
        $this->assertSame('core', $listed[self::PROBE]['namespace']);
    }

    public function testAnOverrideGoesToTheDatabaseAndNoFileIsWritten(): void
    {
        $this->service()->write(self::PROBE, 'in the database');

        $this->assertFileDoesNotExist(CustomTemplateService::DIRECTORY . '/' . self::PROBE);
        $this->assertSame('in the database', $this->getWiki()->services->get(TemplateOverrides::class)->read(self::PROBE));
        $this->assertSame(CustomTemplateService::IN_THE_DATABASE, array_column($this->service()->overrides(), null, 'path')[self::PROBE]['source']);
    }

    public function testTheOperatorsFileIsListedAndOnlyTheDatabaseCanTakeItsPlace(): void
    {
        @mkdir(self::PROBE_DIR, 0o755, true);
        file_put_contents(CustomTemplateService::DIRECTORY . '/' . self::PROBE, 'from the operator');
        $listed = array_column($this->service()->overrides(), null, 'path')[self::PROBE];
        $this->assertSame(CustomTemplateService::ON_DISK, $listed['source']);
        $this->assertSame('from the operator', $this->service()->read(self::PROBE));

        try {
            $this->service()->delete(self::PROBE);
            $this->fail('the screens removed a file the operator ships');
        } catch (\RuntimeException $refused) {
            $this->assertFileExists(CustomTemplateService::DIRECTORY . '/' . self::PROBE);
        }

        $this->service()->write(self::PROBE, 'from the admin');
        $listed = array_column($this->service()->overrides(), null, 'path')[self::PROBE];
        $this->assertSame(CustomTemplateService::IN_THE_DATABASE, $listed['source']);
        $this->assertTrue($listed['onDisk'], 'the screen can say it overrides the operator\'s file');
        $this->assertSame('from the admin', $this->service()->read(self::PROBE));

        $this->service()->delete(self::PROBE);
        $this->assertSame('from the operator', $this->service()->read(self::PROBE));
    }

    public function testRevertingRemovesTheOverrideAndIsSafeToRepeat(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, 'hello');

        $service->delete(self::PROBE);
        $this->assertFalse($service->exists(self::PROBE));

        $service->delete(self::PROBE);
        $this->assertFalse($service->exists(self::PROBE));
    }

    public function testStartingAnOverrideCopiesTheShippedTemplateVerbatim(): void
    {
        $service = $this->service();
        $target = 'admin/custom-templates.twig';
        $relative = 'core/' . $target;

        try {
            $this->assertFalse($service->exists($relative), 'the premise: this wiki does not override it');
            $service->copyFromShipped($target);

            $this->assertSame(
                file_get_contents(YESWIKI_PROGRAM_DIR . '/templates/' . $target),
                $service->read($relative),
                'byte-identical: an override that starts different starts by changing something'
            );

            $this->expectException(\RuntimeException::class);
            $service->copyFromShipped($target);
        } finally {
            $service->delete($relative);

            @rmdir(CustomTemplateService::DIRECTORY . '/core/admin');
        }
    }

    public function testCopyingSomethingThatIsNotAShippedTemplateIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->copyFromShipped('../../yeswiki.config.php');
    }

    /** `@core` takes an override; `@shipped` never does. */
    public function testTheShippedNamespaceIgnoresOverridesAndCoreDoesNot(): void
    {
        $service = $this->service();
        $services = $this->getWiki()->services;
        $marker = 'YW-OVERRIDE-MARKER-' . __LINE__;

        $probe = 'norepo.twig';

        try {
            $service->write('core/' . $probe, $marker);

            $engine = new \YesWiki\Render\Service\TemplateEngine(
                $services,
                $services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
                $services->get(\YesWiki\Kernel\Service\AssetRegistry::class),
                $services->get(\Symfony\Component\Security\Csrf\CsrfTokenManager::class),
                $services->get(\YesWiki\Kernel\Service\UrlFormatter::class),
                $services->get(\YesWiki\Render\Service\TwigSearchPath::class),
                $services->get(\YesWiki\Files\Service\Storage::class),
            );

            $this->assertStringContainsString(
                $marker,
                $engine->render('@core/' . $probe),
                'the control: @core does take an override, or the assertion below proves nothing'
            );
            $this->assertStringNotContainsString(
                $marker,
                $engine->render('@shipped/' . $probe),
                '@shipped must render the template as shipped, whatever is in custom/templates/'
            );
        } finally {
            $service->delete('core/' . $probe);
        }
    }

    /** ...and the screen is actually on that namespace. */
    public function testTheScreenItselfRendersFromTheShippedNamespace(): void
    {
        $this->assertStringStartsWith(
            '@shipped/',
            CustomTemplateService::SCREEN_TEMPLATE,
            'the screen that removes overrides must not be overridable'
        );
    }

    public function testTheShippedListIsTemplatesOnlyAndRelative(): void
    {
        $shipped = $this->service()->shipped();

        $this->assertContains('admin/custom-templates.twig', $shipped);
        foreach ($shipped as $name) {
            $this->assertStringEndsWith('.twig', $name);
            $this->assertStringNotContainsString('..', $name);
            $this->assertStringStartsNotWith('/', $name);
        }
    }
}
