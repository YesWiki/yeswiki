<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\SessionRequirement;
use YesWiki\Kernel\Service\SessionSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 16: core starts no session, and an extension that still needs one asks for it and gets it in the database. */
#[CoversClass(SessionRequirement::class)]
class SessionRequirementTest extends YesWikiTestCase
{
    public function testTheSessionCookieKeepsTheNameCoreGaveIt(): void
    {
        $this->assertSame('YesWiki-main', SessionRequirement::nameFor('/'));
        $this->assertSame('YesWiki-wikis-mine', SessionRequirement::nameFor('/wikis/mine/'));
    }

    public function testAnExtensionThatAsksGetsASessionInTheDatabase(): void
    {
        $config = self::getWiki()->services->get(ParameterBagInterface::class)->all();
        $file = sys_get_temp_dir() . '/yw-require-' . bin2hex(random_bytes(6));
        file_put_contents($file, serialize($config));
        $code = 'define("YESWIKI_INSTANCE_DIR", ' . var_export(YESWIKI_INSTANCE_DIR, true) . '); require "vendor/autoload.php";'
            . ' $params = new Symfony\Component\DependencyInjection\ParameterBag\ParameterBag(unserialize(file_get_contents($argv[1])));'
            . ' $request = new YesWiki\Kernel\Service\CurrentRequest(); $request->replace(new Symfony\Component\HttpFoundation\Request());'
            . ' $sessions = new YesWiki\Kernel\Service\SessionRequirement($params, new YesWiki\Kernel\Service\WikiCookies($params, $request), new YesWiki\Kernel\Service\PersonalResponse());'
            . ' echo $sessions->start() ? session_id() : "refused"; $_SESSION["publication"] = "kept"; session_write_close();';
        $id = trim((string)shell_exec(implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, '-r', $code, '--', $file])) . ' 2>&1'));
        unlink($file);

        $db = self::getWiki()->services->get(DbService::class);
        $table = $db->prefixTable(SessionSchema::TABLE);
        try {
            $this->assertMatchesRegularExpression('/^[\w,-]+$/', $id, $id);
            $data = $db->scalar("SELECT sess_data FROM {$table} WHERE sess_id = ?", null, [$id]);
            $this->assertStringContainsString('publication|s:4:"kept"', is_resource($data) ? (string)stream_get_contents($data) : (string)$data);
        } finally {
            $db->query("DELETE FROM {$table} WHERE sess_id = ?", [$id]);
        }
    }
}
