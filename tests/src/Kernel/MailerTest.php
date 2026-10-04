<?php

namespace YesWiki\Test\Kernel;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** What Mailer puts on the message it hands to PHPMailer, checked without sending anything. */
class MailerTest extends YesWikiTestCase
{
    private const SMTP_KEYS = ['contact_mail_func', 'contact_smtp_host', 'contact_smtp_port', 'contact_smtp_user', 'contact_smtp_verify_peer'];

    private RuntimeConfig $config;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<PHPMailer> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->getWiki()->services->get(RuntimeConfig::class);
        foreach (self::SMTP_KEYS as $key) {
            $this->saved[$key] = $this->config[$key];
        }
        $this->config['contact_mail_func'] = 'mail';
    }

    protected function tearDown(): void
    {
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

    private function useSmtp(): void
    {
        $this->config['contact_mail_func'] = 'smtp';
        $this->config['contact_smtp_host'] = 'smtp.example.org';
        $this->config['contact_smtp_port'] = 587;
        $this->config['contact_smtp_user'] = '';
    }
}
