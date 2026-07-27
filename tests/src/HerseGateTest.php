<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Wiki;

require_once 'src/YesWiki.php';

/**
 * Ticket 21 (herse absorbed into core): the site-wide Basic Auth gate's
 * decision logic, formerly the herse extension's wiki.php snippet. The
 * enforcing wrapper (headers + exit) is CLI-exempt and not unit-testable;
 * the decision itself is pure and pinned here.
 */
#[CoversMethod(Wiki::class, 'herseGateAllows')]
class HerseGateTest extends TestCase
{
    public function testUnconfiguredGateAllowsEverything()
    {
        $this->assertTrue(Wiki::herseGateAllows([], []));
        $this->assertTrue(Wiki::herseGateAllows(['herse_id' => 'a'], []), 'password missing: gate off');
        $this->assertTrue(Wiki::herseGateAllows(['herse_password' => 'b'], []), 'id missing: gate off');
        $this->assertTrue(Wiki::herseGateAllows(['herse_id' => '', 'herse_password' => ''], []), 'empty defaults: gate off');
    }

    public function testConfiguredGateRequiresExactCredentials()
    {
        $config = ['herse_id' => 'door', 'herse_password' => 'sesame'];
        $this->assertFalse(Wiki::herseGateAllows($config, []), 'no credentials sent');
        $this->assertFalse(Wiki::herseGateAllows($config, ['PHP_AUTH_USER' => 'door']), 'password not sent');
        $this->assertFalse(Wiki::herseGateAllows($config, ['PHP_AUTH_USER' => 'door', 'PHP_AUTH_PW' => 'wrong']));
        $this->assertFalse(Wiki::herseGateAllows($config, ['PHP_AUTH_USER' => 'wrong', 'PHP_AUTH_PW' => 'sesame']));
        $this->assertTrue(Wiki::herseGateAllows($config, ['PHP_AUTH_USER' => 'door', 'PHP_AUTH_PW' => 'sesame']));
    }
}
