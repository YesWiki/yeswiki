<?php

namespace YesWiki\Test\Core;

use PHPMailer\PHPMailer\PHPMailer;

require_once 'tests/YesWikiTestCase.php';

/**
 * Mail goes out from the wiki's address, except when a mailing list must see the requester's own one.
 */
class EmailSenderTest extends YesWikiTestCase
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

    public function testTheWikiAddressIsUsedWhenConfigured()
    {
        $this->assertSame('wiki@example.org', mailSenderAddress('visitor@example.net', ['contact_from' => 'wiki@example.org']));
    }

    public function testASubscriptionKeepsTheRequesterAddress()
    {
        $this->assertSame('visitor@example.net', mailSenderAddress('visitor@example.net', ['contact_from' => 'wiki@example.org'], true));
    }

    public function testWithoutContactFromTheSenderIsKept()
    {
        $this->assertSame('visitor@example.net', mailSenderAddress('visitor@example.net', []));
    }

    private function signedMessageFrom(string $from): string
    {
        $mail = new PHPMailer(true);
        $mail->setFrom($from);
        $mail->addAddress('list@example.com');
        $mail->Subject = 'subscribe';
        $mail->Body = 'subscribe';
        signWithDkim($mail, [
            'contact_dkim_domain' => 'example.org',
            'contact_dkim_selector' => 'yeswiki',
            'contact_dkim_private_key' => $this->keyFile,
        ]);
        $mail->preSend();

        return $mail->getSentMIMEMessage();
    }

    public function testTheDkimIdentityIsTheSenderOfTheSigningDomain()
    {
        $this->assertMatchesRegularExpression('/DKIM-Signature:(?:[^\r\n]|\r\n\s)*i=wiki@example\.org/', $this->signedMessageFrom('wiki@example.org'));
    }

    public function testAnOutsideSenderIsSignedWithoutIdentity()
    {
        $message = $this->signedMessageFrom('visitor@example.net');

        $this->assertStringContainsString('DKIM-Signature:', $message);
        $this->assertDoesNotMatchRegularExpression('/DKIM-Signature:(?:[^\r\n]|\r\n\s)*i=/', $message);
    }
}
