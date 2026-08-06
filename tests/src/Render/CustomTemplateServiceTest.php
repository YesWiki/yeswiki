<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\CustomTemplateService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Template overrides as something the wiki can show and edit (ticket 30).
 *
 * There is no sandbox here and that is measured, not an oversight -- Twig's sandbox
 * propagates into `{% extends %}`, so an override cannot be sandboxed without sandboxing the
 * core template it extends. What replaces a policy is the four things that actually go wrong,
 * and they are what this covers: writes that escape the directory, a template that does not
 * compile, a revert that does not revert, and a copy that starts from nothing.
 *
 * Every write goes under a probe name in `custom/templates/`, and the whole probe tree is
 * removed afterwards -- these tests run against the developer's own instance directory, so
 * anything left behind would be an override on their wiki.
 */
class CustomTemplateServiceTest extends YesWikiTestCase
{
    /** Under the real directory, because the confinement being tested is about that path. */
    private const PROBE_DIR = CustomTemplateService::DIRECTORY . '/core/__probe__';

    private const PROBE = 'core/__probe__/probe.twig';

    protected function tearDown(): void
    {
        foreach (glob(self::PROBE_DIR . '/*') ?: [] as $leftover) {
            @unlink($leftover);
        }
        @rmdir(self::PROBE_DIR);
        parent::tearDown();
    }

    private function service(): CustomTemplateService
    {
        return $this->getWiki()->services->get(CustomTemplateService::class);
    }

    // ------------------------------------------------------------- confinement

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

    /**
     * An absolute path is refused, and above all it is not *reinterpreted*.
     *
     * This case passed for the wrong reason first time round. `/tmp/escape.twig` has no `..`
     * and does end in `.twig`, so the only thing standing between it and the disk was a
     * `trim($relative, '/')` -- which quietly turned it into `custom/templates/tmp/escape.twig`
     * and wrote it. Nothing escaped the directory, and the test asserting "not in /tmp" went
     * green over a file that had just been created under a name nobody asked for.
     */
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

    // ---------------------------------------------------------- the compile check

    public function testATemplateThatDoesNotCompileIsRefusedAndNothingIsWritten(): void
    {
        $service = $this->service();

        try {
            $service->write(self::PROBE, '{% block body %}never closed');
            $this->fail('a template that cannot parse must not be accepted');
        } catch (\RuntimeException $refused) {
            // Twig's own message, which names the line -- the point of reporting it verbatim
            $this->assertStringContainsString('block', $refused->getMessage());
        }

        $this->assertFalse($service->exists(self::PROBE), 'the wiki still renders what it rendered');
    }

    /**
     * The check has to run in the wiki's own Twig environment.
     *
     * Twig 3 resolves filters and functions at *parse* time, so a throwaway environment would
     * reject `{{ _t(…) }}`, `{{ icon(…) }}` and every other helper as unknown -- reporting a
     * syntax error in templates that are perfectly correct, which would make the screen
     * refuse to save almost anything real.
     */
    public function testTheWikisOwnHelpersAreNotSyntaxErrors(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, "{{ _t('SAVE') }}{{ icon('star') }}{{ url({tag: 'x'}) }}");

        $this->assertTrue($service->exists(self::PROBE));
    }

    public function testATemplateThatExtendsACoreOneIsAccepted(): void
    {
        // the ordinary shape of an override, and the one a sandbox could never have allowed
        $service = $this->service();
        $service->write(self::PROBE, "{% extends '@core/dashboard/layout.twig' %}{% block dashboard_content %}hi{% endblock %}");

        $this->assertTrue($service->exists(self::PROBE));
    }

    // ------------------------------------------------------ write / read / revert

    public function testAnOverrideIsWrittenReadBackAndListed(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, 'hello');

        $this->assertSame('hello', $service->read(self::PROBE));

        $listed = array_column($service->overrides(), null, 'path');
        $this->assertArrayHasKey(self::PROBE, $listed);
        // there is no `templates/__probe__/probe.twig`, so it is listed as matching nothing --
        // which is exactly how a misspelled override path shows up
        $this->assertFalse($listed[self::PROBE]['shipped']);
        $this->assertSame('core', $listed[self::PROBE]['namespace']);
    }

    public function testRevertingRemovesTheFileAndIsSafeToRepeat(): void
    {
        $service = $this->service();
        $service->write(self::PROBE, 'hello');

        $service->delete(self::PROBE);
        $this->assertFalse($service->exists(self::PROBE));

        // deleting what is already gone is what a double-click on Revert does
        $service->delete(self::PROBE);
        $this->assertFalse($service->exists(self::PROBE));
    }

    // -------------------------------------------------------------- copy to start

    public function testStartingAnOverrideCopiesTheShippedTemplateVerbatim(): void
    {
        $service = $this->service();
        $target = 'admin/custom-templates.twig';
        $relative = 'core/' . $target;

        // a real override of a real template, so it is removed whatever happens below
        try {
            $this->assertFalse($service->exists($relative), 'the premise: this wiki does not override it');
            $service->copyFromShipped($target);

            $this->assertSame(
                file_get_contents(YESWIKI_SOURCE_DIR . '/templates/' . $target),
                $service->read($relative),
                'byte-identical: an override that starts different starts by changing something'
            );

            // ...and a second copy refuses rather than overwriting what is now yours
            $this->expectException(\RuntimeException::class);
            $service->copyFromShipped($target);
        } finally {
            $service->delete($relative);
            // delete() removes the file, not the directory it had to create for it
            @rmdir(CustomTemplateService::DIRECTORY . '/core/admin');
        }
    }

    public function testCopyingSomethingThatIsNotAShippedTemplateIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->copyFromShipped('../../yeswiki.config.php');
    }

    // ---------------------------------------------------- the one that must not break

    /**
     * `@core` takes an override; `@shipped` never does. Same file, same engine, one run.
     *
     * This is the safety net the whole design rests on. A broken override shows up as an
     * error on every page that renders that template -- and if the screen that *removes*
     * overrides is one of them, the only way back is FTP. `admin/custom-templates.twig`
     * therefore renders through `@shipped`, which `custom/templates/` is not on.
     *
     * Both namespaces are asked about the same overridden file, because "the override did
     * not show up" is also what you see when overriding does not work at all. The `@core`
     * half is the control that makes the `@shipped` half mean something.
     *
     * It goes through a **freshly constructed** TemplateEngine, and that is not ceremony:
     * Twig's FilesystemLoader caches each template's resolved path for the life of the
     * process, so an override written after that template has already been rendered is
     * invisible to the engine that rendered it. Harmless in production, where every request
     * is a new process -- and the reason the first version of this test passed alone and
     * failed in the full suite, where DashboardRoutesTest had already rendered the screen.
     */
    public function testTheShippedNamespaceIgnoresOverridesAndCoreDoesNot(): void
    {
        $service = $this->service();
        $services = $this->getWiki()->services;
        $marker = 'YW-OVERRIDE-MARKER-' . __LINE__;

        // no variables of its own and no includes, so what it renders is decided entirely by
        // which file the namespace resolved to
        $probe = 'norepo.twig';

        try {
            $service->write('core/' . $probe, $marker);

            $engine = new \YesWiki\Render\Service\TemplateEngine(
                $services,
                $services->get(\Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class),
                $services->get(\YesWiki\Kernel\Service\AssetRegistry::class),
                $services->get(\Symfony\Component\Security\Csrf\CsrfTokenManager::class),
                $services->get(\YesWiki\Kernel\Service\UrlFormatter::class)
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

    /**
     * ...and the screen is actually on that namespace.
     *
     * The test above proves `@shipped` ignores overrides; this one proves the screen uses it.
     * Without it, changing the controller back to `@core/admin/custom-templates.twig` would
     * pass every test in this file while removing the whole safety net -- and it would pass
     * *because* Twig caches a resolved path per process, so an in-process render of the
     * screen cannot see an override written after it.
     */
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
