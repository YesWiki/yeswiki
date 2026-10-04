<?php

namespace YesWiki\Test\Core;

use PHPMailer\PHPMailer\PHPMailer;

require_once 'tests/YesWikiTestCase.php';

/**
 * Outgoing mail is DKIM-signed once a domain, a selector and a private key file are configured, and only then.
 */
class EmailDkimTest extends YesWikiTestCase
{
    private string $keyFile;

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        $this->keyFile = tempnam(sys_get_temp_dir(), 'yeswiki-dkim-');
        openssl_pkey_export_to_file(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $this->keyFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->keyFile)) {
            unlink($this->keyFile);
        }
    }

    private function builtMessage(array $config): array
    {
        $mail = new PHPMailer(true);
        $mail->setFrom('wiki@example.org', 'Wiki');
        $mail->addAddress('someone@example.net');
        $mail->Subject = 'DKIM test';
        $mail->Body = 'Hello';
        $signed = signWithDkim($mail, $config);
        $mail->preSend();

        return [$signed, $mail->getSentMIMEMessage()];
    }

    public function testAConfiguredWikiSignsItsMail()
    {
        [$signed, $message] = $this->builtMessage([
            'contact_dkim_domain' => 'example.org',
            'contact_dkim_selector' => 'yeswiki',
            'contact_dkim_private_key' => $this->keyFile,
        ]);

        $this->assertTrue($signed);
        $this->assertStringContainsString('DKIM-Signature:', $message);
        $this->assertMatchesRegularExpression('/DKIM-Signature:[^\r\n]*(\r\n\s[^\r\n]*)*d=example\.org;/', $message);
        $this->assertStringContainsString('s=yeswiki;', $message);
    }

    public static function incompleteConfigs(): array
    {
        return [
            'nothing set' => [[]],
            'no selector' => [['contact_dkim_domain' => 'example.org', 'contact_dkim_private_key' => 'KEY']],
            'unreadable key file' => [['contact_dkim_domain' => 'example.org', 'contact_dkim_selector' => 'yeswiki', 'contact_dkim_private_key' => '/nonexistent/dkim.private']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteConfigs')]
    public function testAnIncompleteConfigSendsUnsigned(array $config)
    {
        $config = array_map(fn ($value) => $value === 'KEY' ? $this->keyFile : $value, $config);
        [$signed, $message] = $this->builtMessage($config);

        $this->assertFalse($signed);
        $this->assertStringNotContainsString('DKIM-Signature:', $message);
    }
}
