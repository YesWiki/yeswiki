<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\CliOwnership;

/** The console runs as whoever owns the wiki, or says how to: sudo -u for anyone else, core:fix-ownership for what root already left behind. */
class CliOwnershipTest extends TestCase
{
    private const OWNER = 1001;

    private string $root = '';

    private OwnersByPath $files;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yw-cli-ownership-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/custom/extensions', 0o755, true);
        mkdir($this->root . '/files', 0o755, true);
        touch($this->root . '/files/a.webp');
        touch($this->root . '/yeswiki.config.php');
        touch($this->root . '/yeswicli');
        $this->files = new OwnersByPath(self::OWNER);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function ownership(): CliOwnership
    {
        return new CliOwnership($this->files, $this->root, $this->root . '/yeswiki.config.php');
    }

    private static function userName(int $id): string
    {
        return [0 => 'root', self::OWNER => 'labmrflospw', 1002 => 'alice'][$id] ?? (string)$id;
    }

    public function testTheOwnerRunsItFreely(): void
    {
        $this->assertNull($this->ownership()->refusal(self::OWNER, ['console', 'migrate'], self::userName(...)));
        $this->assertNull($this->ownership()->warning(self::userName(...)));
    }

    public function testAnotherUserIsToldToRunItAsTheOwner(): void
    {
        $refusal = (string)$this->ownership()->refusal(1002, ['console', 'user:create', 'Jo Doe'], self::userName(...));

        $this->assertStringContainsString('runs as alice', $refusal);
        $this->assertStringContainsString("sudo -u 'labmrflospw' '" . $this->root . "/yeswicli' 'user:create' 'Jo Doe'", $refusal);
        $this->assertStringNotContainsString(CliOwnership::FIX_COMMAND, $refusal);
    }

    public function testRootIsToldToUseSudoAndToGiveBackWhatItAlreadyOwns(): void
    {
        $this->assertStringNotContainsString(CliOwnership::FIX_COMMAND, (string)$this->ownership()->refusal(0, ['console', 'migrate'], self::userName(...)));

        $this->files->owners[$this->root . '/custom'] = 0;
        $this->files->owners[$this->root . '/custom/extensions'] = 0;
        $refusal = (string)$this->ownership()->refusal(0, ['console', 'migrate'], self::userName(...));

        $this->assertStringContainsString("sudo -u 'labmrflospw'", $refusal);
        $this->assertStringContainsString('custom, custom/extensions', $refusal);
        $this->assertStringContainsString(CliOwnership::FIX_COMMAND, $refusal);
        $this->assertNull($this->ownership()->refusal(0, ['console', CliOwnership::FIX_COMMAND], self::userName(...)), 'root may give the files back');
        $this->assertStringContainsString('custom, custom/extensions', (string)$this->ownership()->warning(self::userName(...)));
    }

    public function testFixingGivesEachMisownedPathBack(): void
    {
        $this->files->owners[$this->root . '/custom'] = 0;
        $this->files->owners[$this->root . '/files/a.webp'] = 0;

        $this->assertSame([2, []], $this->ownership()->fix());
        $this->assertSame([$this->root . '/custom', $this->root . '/files/a.webp'], $this->files->given);
        $this->assertSame([], $this->ownership()->misowned());
    }

    public function testALinkIsNeitherFollowedNorChanged(): void
    {
        symlink('/etc', $this->root . '/custom/extensions/elsewhere');
        $this->files->owners[$this->root . '/custom/extensions/elsewhere'] = 0;

        $this->assertSame([], $this->ownership()->misowned());
    }
}

/** LocalFiles over a real tree, with owners made up per path, since only root can make a file someone else's. */
class OwnersByPath extends LocalFiles
{
    /** @var array<string, int> */
    public array $owners = [];

    /** @var list<string> */
    public array $given = [];

    public function __construct(private readonly int $default)
    {
    }

    public function ownerOf(string $path): ?int
    {
        return file_exists($path) || is_link($path) ? ($this->owners[$path] ?? $this->default) : null;
    }

    public function groupOf(string $path): ?int
    {
        return $this->ownerOf($path);
    }

    public function changeOwner(string $path, int $user, int $group): bool
    {
        $this->owners[$path] = $user;
        $this->given[] = $path;

        return true;
    }
}
