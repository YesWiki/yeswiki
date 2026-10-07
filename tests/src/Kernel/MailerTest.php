<?php

namespace YesWiki\Test\Kernel;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What Mailer puts on the message it hands to PHPMailer, checked without sending anything. */
class MailerTest extends YesWikiTestCase
{
    private const CONFIG_KEYS = [
        'contact_mail_func', 'contact_smtp_host', 'contact_smtp_port', 'contact_smtp_user', 'contact_smtp_verify_peer',
        'contact_from', 'contact_reply_to', 'contact_dkim_domain', 'contact_dkim_selector', 'contact_dkim_private_key',
    ];

    private RuntimeConfig $config;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<PHPMailer> */
    private array $sent = [];

    private string $keyFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->getWiki()->services->get(RuntimeConfig::class);
        foreach (self::CONFIG_KEYS as $key) {
            $this->saved[$key] = $this->config[$key];
        }
        $this->config['contact_mail_func'] = 'mail';
        foreach (['contact_from', 'contact_reply_to', 'contact_dkim_domain', 'contact_dkim_selector', 'contact_dkim_private_key'] as $key) {
            $this->config[$key] = '';
        }
    }

    protected function tearDown(): void
    {
        if ($this->keyFile !== '' && is_file($this->keyFile)) {
            unlink($this->keyFile);
        }
        foreach ($this->saved as $key => $value) {
            if ($value === null) {
                unset($this->config[$key]);
            } else {
                $this->config[$key] = $value;
            }
        }
        parent::tearDown();
    }

    private function mailer(): Mailer
    {
        $services = $this->getWiki()->services;
        $record = function (PHPMailer $message): void {
            $this->sent[] = clone $message;
        };

        return new class($services, $services->get(ParameterBagInterface::class), $record) extends Mailer {
            public function __construct(ContainerInterface $container, ParameterBagInterface $params, private \Closure $record)
            {
                parent::__construct($container, $params);
            }

            protected function newMessage(): PHPMailer
            {
                return new class($this->record) extends PHPMailer {
                    public function __construct(private \Closure $record)
                    {
                        parent::__construct(true);
                    }

                    public function send()
                    {
                        ($this->record)($this);

                        return true;
                    }
                };
            }
        };
    }

    public function testALoneRecipientIsInToAndNotInBcc(): void
    {
        $this->assertTrue($this->mailer()->send('from@example.org', 'From', 'alone@example.org', 'Subject', 'Body'));

        $this->assertCount(1, $this->sent);
        $this->assertSame([['alone@example.org', '']], $this->sent[0]->getToAddresses());
        $this->assertSame([], $this->sent[0]->getBccAddresses());
    }

    public function testSeveralRecipientsGoInBcc(): void
    {
        $this->mailer()->send('from@example.org', 'From', ['a@example.org', 'b@example.org'], 'Subject', 'Body');

        $this->assertCount(1, $this->sent);
        $this->assertSame([], $this->sent[0]->getToAddresses());
        $this->assertCount(2, $this->sent[0]->getBccAddresses());
    }

    public function testTheMessageIdCarriesTheWikiHost(): void
    {
        $mailer = $this->mailer();
        $host = parse_url($mailer->getBaseUrl(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $this->markTestSkipped('the test wiki has no base url host');
        }

        $mailer->send('from@example.org', 'From', 'alone@example.org', 'Subject', 'Body');

        $this->assertSame($host, $this->sent[0]->Hostname);
    }

    public function testPeerVerificationIsOnByDefault(): void
    {
        $this->useSmtp();
        unset($this->config['contact_smtp_verify_peer']);

        $this->mailer()->send('from@example.org', 'From', 'alone@example.org', 'Subject', 'Body');

        $this->assertSame([], $this->sent[0]->SMTPOptions);
    }

    public function testPeerVerificationCanBeTurnedOff(): void
    {
        $this->useSmtp();
        $this->config['contact_smtp_verify_peer'] = 'false';

        $this->mailer()->send('from@example.org', 'From', 'alone@example.org', 'Subject', 'Body');

        $this->assertFalse($this->sent[0]->SMTPOptions['ssl']['verify_peer']);
        $this->assertFalse($this->sent[0]->SMTPOptions['ssl']['verify_peer_name']);
    }

    public function testTheWikiAddressReplacesTheSender(): void
    {
        $this->config['contact_from'] = 'wiki@example.org';

        $this->mailer()->send('visitor@example.net', 'Visitor', 'alone@example.org', 'Subject', 'Body');

        $this->assertSame('wiki@example.org', $this->sent[0]->From);
    }

    public function testAMailingListSubscriptionKeepsTheRequesterAddress(): void
    {
        $this->config['contact_from'] = 'wiki@example.org';

        $this->mailer()->send('visitor@example.net', 'Visitor', 'list-subscribe@example.com', 'subscribe', 'subscribe', 'subscribe', true);

        $this->assertSame('visitor@example.net', $this->sent[0]->From);
    }

    public function testAConfiguredWikiSignsItsMail(): void
    {
        $this->configureDkim($this->newKeyFile());
        $this->config['contact_from'] = 'wiki@example.org';

        $message = $this->sentMessage('visitor@example.net');

        $this->assertMatchesRegularExpression('/DKIM-Signature:(?:[^\r\n]|\r\n\s)*d=example\.org;/', $message);
        $this->assertMatchesRegularExpression('/DKIM-Signature:(?:[^\r\n]|\r\n\s)*s=yeswiki;/', $message);
        $this->assertMatchesRegularExpression('/DKIM-Signature:(?:[^\r\n]|\r\n\s)*i=wiki@example\.org/', $message);
    }

    public function testAnOutsideSenderIsSignedWithoutIdentity(): void
    {
        $this->configureDkim($this->newKeyFile());

        $message = $this->sentMessage('visitor@example.net', true);

        $this->assertStringContainsString('DKIM-Signature:', $message);
        $this->assertDoesNotMatchRegularExpression('/DKIM-Signature:(?:[^\r\n]|\r\n\s)*i=/', $message);
    }

    public function testARelativeKeyIsReadFromTheInstance(): void
    {
        $storage = $this->getWiki()->services->get(Storage::class);
        $relative = 'private/keys/mailer-test-dkim.private';
        $storage->write($relative, (string)file_get_contents($this->newKeyFile()));

        try {
            $this->configureDkim($relative);
            $this->assertStringContainsString('DKIM-Signature:', $this->sentMessage('wiki@example.org'));
        } finally {
            $storage->delete($relative);
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function incompleteDkimConfigs(): array
    {
        return [
            'nothing set' => ['', '', ''],
            'no selector' => ['example.org', '', 'KEY'],
            'unreadable key file' => ['example.org', 'yeswiki', '/nonexistent/dkim.private'],
            'key outside any storage tier' => ['example.org', 'yeswiki', 'private/dkim.private'],
            'missing key in the instance' => ['example.org', 'yeswiki', 'private/keys/nonexistent-dkim.private'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteDkimConfigs')]
    public function testAnIncompleteDkimConfigSendsUnsigned(string $domain, string $selector, string $keyFile): void
    {
        $this->config['contact_dkim_domain'] = $domain;
        $this->config['contact_dkim_selector'] = $selector;
        $this->config['contact_dkim_private_key'] = $keyFile === 'KEY' ? $this->newKeyFile() : $keyFile;

        $this->assertStringNotContainsString('DKIM-Signature:', $this->sentMessage('wiki@example.org'));
    }

    private function configureDkim(string $keyFile): void
    {
        $this->config['contact_dkim_domain'] = 'example.org';
        $this->config['contact_dkim_selector'] = 'yeswiki';
        $this->config['contact_dkim_private_key'] = $keyFile;
    }

    private function newKeyFile(): string
    {
        $this->keyFile = (string)tempnam(sys_get_temp_dir(), 'yeswiki-dkim-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export_to_file($key, $this->keyFile);

        return $this->keyFile;
    }

    private function sentMessage(string $from, bool $keepSender = false): string
    {
        $this->mailer()->send($from, '', 'alone@example.org', 'Subject', 'Body', '', $keepSender);
        $this->sent[0]->preSend();

        return $this->sent[0]->getSentMIMEMessage();
    }

    private function useSmtp(): void
    {
        $this->config['contact_mail_func'] = 'smtp';
        $this->config['contact_smtp_host'] = 'smtp.example.org';
        $this->config['contact_smtp_port'] = 587;
        $this->config['contact_smtp_user'] = '';
    }
}
