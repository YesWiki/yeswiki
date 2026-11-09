<?php

namespace YesWiki\Test\Core;

use PHPMailer\PHPMailer\Exception;
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

    /** Starts a throwaway SMTP server taking one connection per RCPT reply; returns its process, its output pipe and its port. */
    private function fakeSmtpServer(array $rcptReplies, string $dataReply = '250 queued'): array
    {
        $script = <<<'PHP'
            [$rcptReplies, $dataReply] = json_decode($argv[1], true);
            $server = stream_socket_server('tcp://127.0.0.1:0');
            echo parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT), "\n";
            foreach ($rcptReplies as $rcptReply) {
                $client = @stream_socket_accept($server, 5);
                if (!$client) {
                    break;
                }
                fwrite($client, "220 fake\r\n");
                $inData = false;
                while (($line = fgets($client)) !== false) {
                    $line = rtrim($line);
                    echo $line, "\n";
                    if ($inData) {
                        if ($line === '.') {
                            $inData = false;
                            fwrite($client, "$dataReply\r\n");
                        }
                        continue;
                    }
                    $reply = match (strtoupper(substr($line, 0, 4))) {
                        'RCPT' => $rcptReply,
                        'DATA' => '354 go',
                        'QUIT' => '221 bye',
                        default => '250 ok',
                    };
                    $inData = str_starts_with($reply, '354');
                    fwrite($client, "$reply\r\n");
                    if (str_starts_with($reply, '221')) {
                        break;
                    }
                }
                fclose($client);
            }
            PHP;
        $process = proc_open(['php', '-r', $script, json_encode([$rcptReplies, $dataReply])], [1 => ['pipe', 'w']], $pipes);

        return [$process, $pipes[1], (int)trim(fgets($pipes[1]))];
    }

    private function subscriptionMail(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Hostname = 'wiki.example.org';
        $mail->setFrom('visitor@example.net');
        $mail->addAddress('sympa@example.com');
        $mail->Subject = 'subscribe mylist';
        $mail->Body = 'subscribe';

        return $mail;
    }

    public function testASubscriptionReachesTheListServerUnderTheRequesterAddress()
    {
        [$process, $output, $port] = $this->fakeSmtpServer(['250 ok']);

        $delivered = deliverToMailServer($this->subscriptionMail(), 'sympa@example.com', ['127.0.0.1'], $port);
        $dialogue = stream_get_contents($output);
        proc_close($process);

        $this->assertTrue($delivered);
        $this->assertStringContainsString('EHLO wiki.example.org', $dialogue);
        $this->assertStringContainsString('MAIL FROM:<>', $dialogue);
        $this->assertStringContainsString('RCPT TO:<sympa@example.com>', $dialogue);
        $this->assertStringContainsString('From: visitor@example.net', $dialogue);
        $this->assertStringContainsString('Subject: subscribe mylist', $dialogue);
    }

    public function testTheNextServerIsTriedWhenOneRefuses()
    {
        [$process, $output, $port] = $this->fakeSmtpServer(['421 try later', '250 ok']);

        $delivered = deliverToMailServer($this->subscriptionMail(), 'sympa@example.com', ['127.0.0.1', '127.0.0.1'], $port);
        $dialogue = stream_get_contents($output);
        proc_close($process);

        $this->assertTrue($delivered);
        $this->assertSame(2, substr_count($dialogue, 'RCPT TO:'));
        $this->assertSame(1, substr_count($dialogue, "\n.\n"));
    }

    public function testNoServerAcceptingMeansNotDelivered()
    {
        [$process, $output, $port] = $this->fakeSmtpServer(['250 ok'], '550 5.7.1 refused');

        $delivered = deliverToMailServer($this->subscriptionMail(), 'sympa@example.com', ['127.0.0.1'], $port);
        stream_get_contents($output);
        proc_close($process);

        $this->assertFalse($delivered);
    }

    public function testAFailureReportsWhatTheServerAnswered()
    {
        [$process, $output, $port] = $this->fakeSmtpServer(['250 ok'], '550 5.7.1 Sender mismatch');
        $mail = $this->subscriptionMail();
        $mail->Host = '127.0.0.1';
        $mail->Port = $port;
        $mail->SMTPAutoTLS = false;

        try {
            $mail->send();
            $this->fail('The server refused this mail');
        } catch (Exception $e) {
            $message = mailFailureMessage($mail, $e);
        }
        $mail->smtpClose();
        stream_get_contents($output);
        proc_close($process);

        $this->assertStringContainsString('data not accepted', $message);
        $this->assertStringContainsString('Sender mismatch', $message);
    }
}
