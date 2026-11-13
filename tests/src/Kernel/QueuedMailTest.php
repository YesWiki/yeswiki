<?php

namespace YesWiki\Test\Kernel;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use YesWiki\Kernel\Message\SendMail;
use YesWiki\Kernel\Message\SendMailHandler;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A notification leaves the request as SendMail messages, ten recipients each, and the queue sends them, retrying a refusal (background-work 05). */
class QueuedMailTest extends YesWikiTestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    private int $connections = 0;

    private function mailer(): Mailer
    {
        $services = $this->getWiki()->services;
        $record = function (object $message): void {
            $this->dispatched[] = $message;
        };
        $bus = new class($record) implements MessageBusInterface {
            public function __construct(private \Closure $record)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                ($this->record)($message);

                return new Envelope($message, $stamps);
            }
        };
        $container = new class($services, $bus) implements ContainerInterface {
            public function __construct(private ContainerInterface $services, private MessageBusInterface $bus)
            {
            }

            public function get(string $id): mixed
            {
                return $id === MessageBusInterface::class ? $this->bus : $this->services->get($id);
            }

            public function has(string $id): bool
            {
                return $id === MessageBusInterface::class || $this->services->has($id);
            }
        };
        $count = function (): void {
            $this->connections++;
        };

        return new class($container, $services->get(ParameterBagInterface::class), $count) extends Mailer {
            public function __construct(ContainerInterface $container, ParameterBagInterface $params, private \Closure $count)
            {
                parent::__construct($container, $params);
            }

            protected function newMessage(): PHPMailer
            {
                ($this->count)();

                return new PHPMailer(true);
            }
        };
    }

    /** @return list<SendMail> */
    private function mails(): array
    {
        return array_values(array_filter($this->dispatched, static fn (object $message): bool => $message instanceof SendMail));
    }

    public function testANotificationToFortyPeopleIsFourMessagesAndNoConnection(): void
    {
        $recipients = array_map(static fn (int $i): string => "reader{$i}@example.tld", range(1, 40));

        $this->mailer()->queue('wiki@example.tld', 'Le wiki', [...$recipients, 'not an address'], 'Une fiche a changé', 'texte', '<p>html</p>');

        $this->assertSame(0, $this->connections, 'nothing is sent while the person who saved waits');
        $this->assertCount(4, $this->dispatched, 'one message per ten recipients, so a retry resends only its batch');
        $this->assertContainsOnlyInstancesOf(SendMail::class, $this->dispatched);
        $this->assertSame($recipients, array_merge(...array_map(static fn (SendMail $mail): array => $mail->recipients, $this->mails())), 'every valid recipient once, the invalid one dropped');
        $this->assertSame('<p>html</p>', $this->mails()[0]->html);
    }

    public function testAMailFromTheAdminIsPreparedInTheRequest(): void
    {
        $this->mailer()->queueEmailFromAdmin('reader@example.tld', 'Fiche créée', 'texte');

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('Fiche creee', $this->mails()[0]->subject, 'the subject loses its accents before it is queued, as sendEmailFromAdmin() does');
        $this->assertSame(['reader@example.tld'], $this->mails()[0]->recipients);
    }

    public function testTheHandlerSendsAndThrowsWhatTheServerSaid(): void
    {
        $services = $this->getWiki()->services;
        $refusing = new class($services, $services->get(ParameterBagInterface::class)) extends Mailer {
            /** @var list<array<int, mixed>> */
            public array $calls = [];

            public bool $accept = true;

            public function send($mailSender, $nameSender, $mailReceiver, string $subject, string $messageTxt, string $messageHtml = '', bool $keepSender = false): bool
            {
                $this->calls[] = [$mailSender, $mailReceiver, $subject, $keepSender];

                return $this->accept;
            }

            public function lastError(): string
            {
                return '421 too busy';
            }
        };
        $handler = new SendMailHandler($refusing);

        $handler(new SendMail('wiki@example.tld', 'Le wiki', ['one@example.tld'], 'Sujet', 'texte', '', true));
        $this->assertSame([['wiki@example.tld', 'one@example.tld', 'Sujet', true]], $refusing->calls, 'one recipient is sent to directly, keeping the sender when asked');

        $refusing->accept = false;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('421 too busy');
        $handler(new SendMail('wiki@example.tld', 'Le wiki', ['one@example.tld', 'two@example.tld'], 'Sujet', 'texte'));
    }

    public function testTheQueueKnowsWhoSendsAMail(): void
    {
        $this->getWiki();
        self::assertHandled(SendMail::class, null, 'a queued mail has a handler, so the consumer sends it');
    }
}
