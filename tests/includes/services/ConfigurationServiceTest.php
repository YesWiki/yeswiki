<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(ConfigurationService::class, 'writeAtomically')]
class ConfigurationServiceTest extends YesWikiTestCase
{
    private string $folder;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
    }

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/yeswiki_config_test_' . bin2hex(random_bytes(6));
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->folder}/{,.}*", GLOB_BRACE) as $path) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        rmdir($this->folder);
    }

    public function testAWriterKilledHalfwayLeavesThePreviousConfigWhole()
    {
        $file = "{$this->folder}/wakka.config.php";
        $previous = "<?php\n\n\$wakkaConfig = ['wakka_name' => 'still here'];\n";
        file_put_contents($file, $previous);
        $script = sprintf(
            'require %s; require %s; (new YesWiki\Core\Service\ConfigurationService())->writeAtomically(%s, str_repeat("x", 64 * 1024));',
            var_export(getcwd() . '/vendor/autoload.php', true),
            var_export(getcwd() . '/includes/services/ConfigurationService.php', true),
            var_export($file, true)
        );

        $process = new Process(['bash', '-c', 'ulimit -f 8 && exec "$0" -r "$1"', PHP_BINARY, $script]);
        try {
            $process->run();
            $this->fail('the writer should have run out of room as a full quota would make it');
        } catch (ProcessSignaledException) {
        }
        $this->assertSame($previous, file_get_contents($file));
    }

    public function testAWriteReplacesTheFileAndKeepsItsPermissions()
    {
        $file = "{$this->folder}/wakka.config.php";
        file_put_contents($file, 'old');
        chmod($file, 0640);

        $this->assertTrue((new ConfigurationService())->writeAtomically($file, 'new'));

        $this->assertSame('new', file_get_contents($file));
        $this->assertSame(0640, fileperms($file) & 0777);
        $this->assertSame(['wakka.config.php'], array_values(array_diff(scandir($this->folder), ['.', '..'])), 'no temporary file is left behind');
    }

    public function testAWriteThroughASymlinkReplacesItsTarget()
    {
        $target = "{$this->folder}/real.config.php";
        $link = "{$this->folder}/wakka.config.php";
        file_put_contents($target, 'old');
        symlink($target, $link);

        $this->assertTrue((new ConfigurationService())->writeAtomically($link, 'new'));

        $this->assertTrue(is_link($link), 'the link stays a link');
        $this->assertSame('new', file_get_contents($target));
    }
}
