<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Render\Service\TemplateEngine;

/**
 * The emails a wiki sends when its content changes.
 *
 * This was the other half of `Kernel\Service\Mailer`, and it was the half that made a Kernel
 * service import four feature modules (ADR-0013). Composing a notification is not a Kernel
 * concern: it renders an entry, looks a user up by email, asks who is logged in and picks a Twig
 * template. Sending one is, and that is all `Mailer` does now.
 *
 * Every caller of these five methods was already in Content -- `EntryManager`,
 * `FormPropertiesService` and `ListController` -- which is what says this is where they belong
 * rather than where they merely compile.
 */
class ContentNotifier
{
    public function __construct(
        private ContainerInterface $container,
        private AuthenticationService $authenticationService,
        private Mailer $mailer,
        private ParameterBagInterface $params,
        private TemplateEngine $templateEngine,
        private UserManager $userManager
    ) {
    }

    /**
     * @param array<string, mixed> $data the entry that was just written
     * @param bool                 $new  whether it is a creation rather than an edit
     */
    public function notifyAdmins($data, $new): void
    {
        $admins = $this->getAdminsList();

        $baseUrl = $this->mailer->getBaseUrl();
        $sujet = $this->templateEngine->render(
            '@core/notify-admins-email-subject.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'new' => $new,
            ]
        );
        $text = $this->templateEngine->render(
            '@core/notify-admins-email-text.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
            ]
        );
        $userName = $admins[0]['name'] ?? null;
        $html = $this->templateEngine->render(
            '@core/notify-admins-email-html.twig',
            [
                'style' => $this->emailStyles(),
                'entry' => $data,
                'entryHTML' => $this->renderEntry($data['tag'], $userName),
                'baseUrl' => $baseUrl,
            ]
        );

        foreach ($admins as $admin) {
            $this->mailer->sendEmailFromAdmin($admin['email'], $sujet, $text, $html);
        }
    }

    /**
     * @param string $id the tag of the list that was deleted
     */
    public function notifyAdminsListDeleted($id): void
    {
        $baseUrl = $this->mailer->getBaseUrl();
        $sujet = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-subject.twig',
            [
                'baseUrl' => $baseUrl,
                'listId' => $id,
            ]
        );
        $text = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-text.twig',
            [
                'ip' => $this->clientIp(),
                'userName' => $this->authenticationService->getLoggedUserName(),
            ]
        );
        $html = $this->templateEngine->render(
            '@core/notify-admins-list-deleted-email-html.twig',
            [
                'style' => $this->emailStyles(),
                'ip' => $this->clientIp(),
                'userName' => $this->authenticationService->getLoggedUserName(),
                'baseUrl' => $baseUrl,
            ]
        );

        foreach ($this->getAdminsList() as $admin) {
            $this->mailer->sendEmailFromAdmin($admin['email'], $sujet, $text, $html);
        }
    }

    /** Nothing on the command line, where a deletion has no visitor behind it. */
    private function clientIp(): string
    {
        if (\YesWiki\Core\YesWikiKernel::isCli()) {
            return '';
        }

        return (string)$this->container->get(CurrentRequest::class)->get()->getClientIp();
    }

    /**
     * @param string                    $email
     * @param array<string, mixed>      $data          the entry that was just written
     * @param array<string, mixed>|null $previousEntry the entry as it was before, on an edit
     */
    public function notifyEmail($email, $data, bool $isCreation = false, ?array $previousEntry = null): void
    {
        $baseUrl = $this->mailer->getBaseUrl();
        $sujet = $this->templateEngine->render(
            '@core/notify-email-subject.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'previousEntry' => $previousEntry,
                'isCreation' => $isCreation,
            ]
        );
        $text = $this->templateEngine->render(
            '@core/notify-email-text.twig',
            [
                'entry' => $data,
                'baseUrl' => $baseUrl,
                'previousEntry' => $previousEntry,
                'isCreation' => $isCreation,
            ]
        );
        $html = $this->templateEngine->render(
            '@core/notify-email-html.twig',
            [
                'style' => $this->emailStyles(),
                'entry' => $data,
                'entryHTML' => $this->renderEntry($data['tag'], $this->rendersAs($email)),
                'baseUrl' => $baseUrl,
                'mailCustomMessage' => $this->params->has('mail_custom_message') ? $this->params->get('mail_custom_message') : null,
                'previousEntry' => $previousEntry,
                'isCreation' => $isCreation,
            ]
        );

        $this->mailer->sendEmailFromAdmin($email, $sujet, $text, $html);
    }

    /**
     * @param string $wikiName
     * @param string $email
     */
    public function notifyNewUser($wikiName, $email): void
    {
        $baseUrl = $this->mailer->getBaseUrl();
        $objetmail = $this->templateEngine->render(
            '@core/notify-newuser-email-subject.twig',
            [
                'baseUrl' => $baseUrl,
                'yeswikiName' => $this->params->get('yeswiki_name'),
            ]
        );
        $messagemail = $this->templateEngine->render(
            '@core/notify-newuser-email-text.twig',
            [
                'wikiName' => $wikiName,
                'email' => $email,
                'baseUrl' => $baseUrl,
            ]
        );

        $this->mailer->sendEmailFromAdmin($email, $objetmail, $messagemail);
    }

    /**
     * @param string $email
     * @param string $mailingList the address of the list to subscribe to
     */
    public function subscribeToMailingList($email, $mailingList): void
    {
        $this->mailer->send(
            $email,
            $email,
            $mailingList,
            'inscription a la liste de discussion',
            'inscription'
        );
    }

    /**
     * Who the entry is rendered as, which decides what the recipient is allowed to see in it.
     *
     * A known user is rendered as themselves. Nobody logged in and no account for this address is
     * rendered as nobody. The remaining case -- somebody is logged in but the recipient has no
     * account -- is rendered as a name no account holds, so the entry comes out with the
     * permissions of a stranger rather than with the sender's.
     */
    private function rendersAs(string $email): ?string
    {
        $user = $this->userManager->getOneByEmail($email);
        if (!empty($user['name'])) {
            return $user['name'];
        }
        if (empty($this->authenticationService->getLoggedUser())) {
            return null;
        }

        do {
            $randomString = md5((string)rand());
        } while (!empty($this->userManager->getOneByName($randomString)));

        return $randomString;
    }

    private function renderEntry(string $tag, ?string $userName): string
    {
        return (string)$this->container->get(EntryController::class)->view($tag, '', true, $userName);
    }

    /**
     * The stylesheet the HTML emails inline, read through Storage.
     *
     * `readForeign` and not `read`: this is a Program path, and ADR-0022's tiers describe an
     * Instance's data. The stylesheet is code that ships with the release, so it is foreign to the
     * wiki the way an upload or a lease is.
     */
    private function emailStyles(): string
    {
        return $this->container->get(Storage::class)->readForeign(YESWIKI_PROGRAM_DIR . '/styles/email.css');
    }

    /**
     * @return list<\YesWiki\Identity\Entity\User>
     */
    private function getAdminsList(): array
    {
        $adminsAcl = $this->container->get(GroupOperationsService::class)->getMembersText(ADMIN_GROUP);
        $admins = [];
        foreach (explode("\n", $adminsAcl) as $line) {
            $line = trim($line);
            if (!empty($line)
                && substr($line, 0, 1) != '#'
                && substr($line, 0, 1) != '@') {
                $adminUser = $this->userManager->getOneByName($line);
                if (!empty($adminUser)) {
                    $admins[] = $adminUser;
                }
            }
        }

        return $admins;
    }
}
