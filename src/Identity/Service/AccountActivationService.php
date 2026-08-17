<?php

namespace YesWiki\Identity\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Exception\BadActivationKeyException;
use YesWiki\Identity\Exception\UserNameDoesNotExistException;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * accountactivationbyemail, absorbed into core (ticket 07): activation status/key are fields on the user's Content body (not standalone triples), gated the same way the password hash is -- BODY_KEY_STATUS/BODY_KEY_KEY are in Guard::USER_ALWAYS_HIDDEN_FIELDS, so they're never surfaced via a generic page-read path, only through this service's own internal (ACL-bypassing) reads -- same pattern as UserManager reading the real password hash for auth purposes.
 */
class AccountActivationService
{
    public const BODY_KEY_STATUS = 'activation_status';
    public const BODY_KEY_KEY = 'activation_key';
    public const ACTIVATED = 'Y';
    public const NOT_ACTIVATED = 'N';

    protected $params;
    protected $templateEngine;
    protected $userManager;
    protected ContainerInterface $container;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        UserManager $userManager,
        ContainerInterface $container,
        UrlFormatter $urlFormatter
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->userManager = $userManager;
        $this->container = $container;
    }

    /**
     * @throws UserNameDoesNotExistException
     * @throws BadActivationKeyException
     * @throws \Exception
     */
    public function activate(string $userName, string $key, bool $force = false): void
    {
        $this->checkValidity($userName, $key, $force);

        if ($this->isActivated($userName)) {
            return;
        }
        if (!$force && !$this->isValidActivationKey($userName, $key)) {
            throw new BadActivationKeyException("The activation key for user $userName is invalid or expired.");
        }

        $this->writeActivationFields($userName, [
            self::BODY_KEY_STATUS => self::ACTIVATED,
            self::BODY_KEY_KEY => '',
        ]);
    }

    /**
     * @throws UserNameDoesNotExistException
     * @throws \Exception
     */
    public function inactivate(string $userName): void
    {
        $this->checkValidity($userName, '', true);

        $this->writeActivationFields($userName, [self::BODY_KEY_STATUS => self::NOT_ACTIVATED]);
    }

    public function isActivated(string $userName): bool
    {
        return $this->readActivationField($userName, self::BODY_KEY_STATUS) === self::ACTIVATED;
    }

    /**
     * @throws \Exception
     */
    public function sendActivationLink(?string $userName): string
    {
        if (empty($userName)) {
            throw new \Exception('Cannot send an activation email for an empty userName');
        }
        $user = $this->userManager->getOneByName($userName);
        if (empty($user['name'])) {
            throw new \Exception("Cannot send an activation email for a non-existent user ($userName)");
        }

        $link = $this->getActivationLink($user['name']);
        $mailer = $this->container->get(Mailer::class);

        $baseUrl = $this->getBaseUrl();
        $context = ['userName' => $user['name'], 'baseUrl' => $baseUrl, 'link' => $link];

        $subject = $this->templateEngine->render('@core/emailactivation-email-subject.twig', $context);
        $text = $this->templateEngine->render('@core/emailactivation-email-text.twig', $context);
        $html = $this->templateEngine->render('@core/emailactivation-email-html.twig', $context);

        $mailer->sendEmailFromAdmin($user['email'], $subject, $text, $html);

        return $link;
    }

    /**
     * Clears any activation key past UserManager::KEY_TTL, the same TTL used for password-recovery keys -- called from the same periodic maintenance pass as UserManager::purgeExpiredPasswordRecoveryKeys() (YesWiki::Maintenance()).
     */
    public function purgeExpiredActivationKeys(): void
    {
        foreach ($this->userManager->getAllUserTags() as $tag) {
            $raw = $this->readActivationField($tag, self::BODY_KEY_KEY);
            if (empty($raw)) {
                continue;
            }
            $parts = explode(UserManager::KEY_VALUE_SEPARATOR, $raw);
            $issuedAt = count($parts) === 2 ? (int)$parts[1] : 0;
            if (time() - $issuedAt > UserManager::KEY_TTL) {
                $this->writeActivationFields($tag, [self::BODY_KEY_KEY => '']);
            }
        }
    }

    /**
     * @throws UserNameDoesNotExistException
     * @throws BadActivationKeyException
     */
    protected function checkValidity(string $userName, string $key, bool $force): void
    {
        if (empty($userName) || empty($this->userManager->getOneByName($userName))) {
            throw new UserNameDoesNotExistException("Trying to activate/inactivate a non-existent user ($userName).");
        }
        if (!$force) {
            if (empty($key) || !preg_match('/[A-Za-z0-9+\/=]+/', $key)) {
                throw new BadActivationKeyException("The activation key for user $userName is in an invalid format.");
            }
        }
    }

    protected function isValidActivationKey(string $userName, string $key): bool
    {
        $raw = $this->readActivationField($userName, self::BODY_KEY_KEY);
        if (empty($raw)) {
            return false;
        }
        $parts = explode(UserManager::KEY_VALUE_SEPARATOR, $raw);
        if (count($parts) !== 2 || $parts[0] !== $key) {
            return false;
        }
        $issuedAt = (int)$parts[1];

        return (time() - $issuedAt) <= UserManager::KEY_TTL;
    }

    /**
     * @throws \Exception
     */
    protected function getActivationLink(string $userName): string
    {
        $length = $this->params->get('user_activation_key_length');
        $length = (is_scalar($length) && intval($length) > 6) ? intval($length) : 20;
        $key = base64_encode(random_bytes($length));

        $this->writeActivationFields($userName, [
            self::BODY_KEY_KEY => $key . UserManager::KEY_VALUE_SEPARATOR . time(),
        ]);

        return $this->urlFormatter->href('emailactivation', $this->params->get('root_page'), [
            'username' => $userName,
            'key' => $key,
        ], false);
    }

    /**
     * @return array|null the decoded body, or null if $tag isn't a real, existing page
     */
    private function readBody(string $tag): ?array
    {
        $page = $this->container->get(PageManager::class)->getOne($tag, null, true, true);
        if (!$page) {
            return null;
        }

        $body = $page['body'] ?? [];

        return is_array($body) ? $body : [];
    }

    protected function readActivationField(string $tag, string $field): ?string
    {
        $body = $this->readBody($tag);

        return $body !== null ? ($body[$field] ?? null) : null;
    }

    /**
     * @throws \Exception
     */
    protected function writeActivationFields(string $tag, array $fields): void
    {
        $pageManager = $this->container->get(PageManager::class);
        $body = $this->readBody($tag);
        if ($body === null) {
            throw new \Exception("Cannot set activation fields on '$tag': no such user");
        }
        $body = array_merge($body, $fields);

        $pageManager->save($tag, $body, '', true);
    }

    private function getBaseUrl(): string
    {
        return preg_replace('/(\\/wakka\\.php\\?wiki=|\\/\\?wiki=|\\/\\?|\\/)$/m', '', $this->params->get('base_url'));
    }
}
