<?php

namespace YesWiki\Identity\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Identity\Exception\DeleteUserException;
use YesWiki\Identity\Exception\UserEmailAlreadyUsedException;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\TripleStore;

class UserOperationsService extends YesWikiController
{
    use LimitationsTrait;

    public const DEFAULT_NAME_MAX_LENGTH = 80;
    public const DEFAULT_EMAIL_MAX_LENGTH = 254;

    /**
     * Rules for user name : - do not start by "!", "#" and "@" ; - do not contains "<", ">", "\", "/" anywhere ; - strictly more than 2 chars.
     *
     * @var string
     */
    public const PATTERN_USER_NAME = '^[^!#@<>\\\\\/][^<>\\\\\/]{2,}$';

    /** @var array<string, mixed> */
    private array $limitations = [];

    protected AuthenticationService $authenticationService;
    protected DbService $dbService;
    protected GroupOperationsService $groupOperationsService;
    protected PageManager $pageManager;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;
    protected TripleStore $tripleStore;
    protected UserManager $userManager;

    public function __construct(
        AuthenticationService $authenticationService,
        DbService $dbService,
        GroupOperationsService $groupOperationsService,
        PageManager $pageManager,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        TripleStore $tripleStore,
        UserManager $userManager
    ) {
        $this->authenticationService = $authenticationService;
        $this->dbService = $dbService;
        $this->groupOperationsService = $groupOperationsService;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->initLimitations();
    }

    /**
     * Initializes object limitation properties using values from the config file.
     *
     * @return void
     */
    private function initLimitations()
    {
        $this->limitations = [];
        $this->initLimitationHelper(
            'user_name_max_length',
            'nameMaxLength',
            self::DEFAULT_NAME_MAX_LENGTH,
            'USER_NAME_MAX_LENGTH_NOT_INT'
        );
        $this->initLimitationHelper(
            'user_email_max_length',
            'emailMaxLength',
            self::DEFAULT_EMAIL_MAX_LENGTH,
            'USER_EMAIL_MAX_LENGTH_NOT_INT'
        );
    }

    /**
     * create a user for e-mail check is existing e-mail.
     *
     * @param array<string, mixed> $newValues (associative array)
     *
     * @return User|null $user
     *
     * @throws \Exception
     */
    public function create(array $newValues): ?User
    {
        $newValues = $this->sanitizeValues($newValues);
        if (!empty($this->userManager->getOneByName($newValues['name']))) {
            throw new \Exception(str_replace('{currentName}', $newValues['name'], _t('USERSETTINGS_NAME_ALREADY_USED')));
        }
        if (!empty($this->userManager->getOneByEmail($newValues['email']))) {
            throw new \Exception(str_replace('{email}', $newValues['email'], _t('USERSETTINGS_EMAIL_ALREADY_USED')));
        }

        $user = $this->userManager->create($newValues);
        if (!empty($user)) {
            return $user;
        }
        throw new \Exception(_t('USER_CREATION_FAILED') . '.');
    }

    /**
     * update user params for e-mail check is existing e-mail.
     *
     * @param array<string, mixed> $newValues (associative array)
     *
     * @throws BadFormatPasswordException
     * @throws \Exception
     * @throws UserEmailAlreadyUsedException
     */
    public function update(User $user, array $newValues): bool
    {
        $newValues = $this->sanitizeValues($newValues);
        $this->userManager->update($user, $newValues);
        if (isset($newValues['password'])) {
            $updatedUser = $this->userManager->getOneByName($user['name']);
            if ($updatedUser === null) {
                throw new \Exception(_t('USERSTABLE_NOT_EXISTING_USER', ['username' => $user['name']]));
            }
            $this->authenticationService->setPassword($updatedUser, $newValues['password']);
        }

        return true;
    }

    /**
     * sanitize values.
     *
     * @param array<string, mixed> $newValues (associative array)
     *
     * @return array<string, mixed> $sanitizedValues
     *
     * @throws \Exception
     */
    private function sanitizeValues(array $newValues): array
    {
        if (isset($newValues['name'])) {
            $newValues['name'] = $this->sanitizeName($newValues['name']);
        }
        if (isset($newValues['email'])) {
            $newValues['email'] = $this->sanitizeEmail($newValues['email']);
        }
        if (isset($newValues['revisioncount'])) {
            $newValues['revisioncount'] = $this->sanitizeCount($newValues['revisioncount'], 'revisioncount');
        }
        if (isset($newValues['changescount'])) {
            $newValues['changescount'] = $this->sanitizeCount($newValues['changescount'], 'changescount');
        }
        if (isset($newValues['show_comments'])) {
            $newValues['show_comments'] = $this->sanitizeBoolean($newValues['show_comments'], 'show_comments');
        }
        if (isset($newValues['doubleclickedit'])) {
            $newValues['doubleclickedit'] = $this->sanitizeBoolean($newValues['doubleclickedit'], 'doubleclickedit');
        }
        if (isset($newValues['motto'])) {
            $newValues['motto'] = $this->sanitizeString($newValues['motto'], 'motto');
        }

        return $newValues;
    }

    /**
     * delete a user but check if possible before.
     *
     * @throws DeleteUserException
     * @throws \Exception
     */
    public function delete(User $user): void
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        if (!$this->getService(AclService::class)->isAdmin()) {
            throw new DeleteUserException(_t('USER_MUST_BE_ADMIN_TO_DELETE') . '.');
        }
        if ($this->isRunner($user)) {
            throw new DeleteUserException(_t('USER_CANT_DELETE_ONESELF') . '.');
        }
        $this->deleteGroupsWhereUserIsAlone($user);
        $this->deleteUserFromEveryGroup($user);
        $this->removeOwnership($user);
        $this->userManager->delete($user);
    }

    /**
     * get first admin name.
     *
     * @return string $adminName
     *
     * @throws \Exception
     */
    public function getFirstAdmin(): string
    {
        $admins = $this->groupOperationsService->getMembersText(ADMIN_GROUP);
        $admins = str_replace(["\r\n", "\r"], "\n", $admins);
        $admins = explode("\n", $admins);
        foreach ($admins as $line) {
            $line = trim($line);
            if (
                !empty($line)
                && !in_array(substr($line, 0, 1), ['@', '!', '#'])
            ) {
                $adminUser = $this->userManager->getOneByName($line);
                if (!empty($adminUser['name'])) {
                    $admin = $adminUser['name'];
                    break;
                }
            }
        }
        if (empty($admin)) {
            throw new \Exception('No admin found');
        }

        return $admin;
    }

    /** check if current user is the user to delete. */
    private function isRunner(User $user): bool
    {
        $loggedUser = $this->authenticationService->getLoggedUser();

        return !empty($loggedUser) && ($loggedUser['name'] == $user['name']);
    }

    /**
     * Delete groups where user is the sole member (unless it's the admins group).
     *
     * @throws DeleteUserException
     */
    private function deleteGroupsWhereUserIsAlone(User $user): void
    {
        $grouptab = $this->userManager->groupsWhereIsMember($user, false);
        foreach ($grouptab as $group) {
            $groupmembers = $this->groupOperationsService->getMembersText($group);
            $groupmembers = str_replace(["\r\n", "\r"], "\n", $groupmembers);
            $groupmembers = explode("\n", $groupmembers);
            $groupmembers = array_unique(array_filter(array_map('trim', $groupmembers)));
            if (count($groupmembers) == 1) {
                if (strtolower($group) === ADMIN_GROUP) {
                    throw new DeleteUserException(_t('USER_DELETE_LONE_MEMBER_OF_GROUP') . " ($group).");
                }
                $this->groupOperationsService->delete($group);
            }
        }
    }

    /**
     * remove user from every group.
     *
     * @throws DeleteUserException
     */
    private function deleteUserFromEveryGroup(User $user): void
    {
        $groups = $this->tripleStore->getMatching(
            GROUP_PREFIX . '%',
            'http://www.wikini.net/_vocabulary/acls',
            '%' . $user['name'] . '%',
            'LIKE',
            '=',
            'LIKE'
        );
        $error = false;
        $pregQuoteSearchValue = preg_quote($user['name'], '/');
        $prefixLen = strlen(GROUP_PREFIX);
        foreach ($groups as $group) {
            $newValue = $group['value'];
            $newValue = preg_replace("/(?<=^|\\n|\\r)$pregQuoteSearchValue(?:\\r\\n|\\n|\\r|$)/", '', $newValue);
            if ($newValue != $group['value']) {
                $groupName = substr($group['resource'], $prefixLen);
                $remainingMembers = array_filter(array_map('trim', preg_split('/[\\r\\n]+/', $newValue) ?: []));
                if (empty($remainingMembers) && strtolower($groupName) !== ADMIN_GROUP) {
                    $this->groupOperationsService->delete($groupName);
                } elseif (!in_array($this->tripleStore->update(
                    $group['resource'],
                    $group['property'],
                    $group['value'],
                    $newValue,
                    '',
                    ''
                ), [0, 3])) {
                    $error = true;
                }
            }
        }
        if ($error) {
            throw new DeleteUserException(_t('USER_DELETE_QUERY_FAILED') . '.');
        }
    }

    /**
     * remove user from every group.
     *
     * @throws \Exception
     */
    private function removeOwnership(User $user): void
    {
        $pagesWhereOwner = $this->dbService->loadAll("
            SELECT tag FROM {$this->dbService->prefixTable('pages')}
            WHERE owner = ?
            AND latest = 'Y' ;
        ", [$user['name']]);
        $pagesWhereOwner = array_map(function ($page) {
            return $page['tag'];
        }, $pagesWhereOwner);

        $firstAdmin = $this->getFirstAdmin();
        foreach ($pagesWhereOwner as $tag) {
            $this->pageManager->setOwner($tag, $firstAdmin);
        }
    }

    /**
     * check if value is int and return new value.
     *
     * @param mixed $value as it came out of the submitted values
     *
     * @throws \Exception
     */
    private function sanitizeCount($value, string $propertyName): int
    {
        if (!filter_var($value, FILTER_VALIDATE_INT) || $value < 0) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_A_POSITIVE_INTEGER_FOR', ['name' => $propertyName]));
        }

        return intval($value);
    }

    /**
     * check if value is Y or N and return new value.
     *
     * @param mixed $value as it came out of the submitted values
     *
     * @throws \Exception
     */
    private function sanitizeBoolean($value, string $propertyName): string
    {
        $value = strtolower($value);
        if (!in_array($value, ['o', 'oui', 'y', 'yes', 'n', 'non', 'no', '0', '1', 'true', 'false'])) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_YES_OR_NO', ['name' => $propertyName]));
        }

        return in_array($value, ['o', 'oui', 'y', 'yes', '1', 'true']) ? 'Y' : 'N';
    }

    /**
     * check if value is String and return new value.
     *
     * @param mixed $value as it came out of the submitted values
     *
     * @throws \Exception
     */
    private function sanitizeString($value, string $propertyName): string
    {
        if (!is_scalar($value)) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_A_STRING', ['name' => $propertyName]));
        }

        return strval($value);
    }

    /**
     * check if value is a nameand return new value.
     *
     * @param mixed $value as it came out of the submitted values
     *
     * @throws \Exception
     */
    private function sanitizeName($value): string
    {
        if (!is_scalar($value)) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_A_STRING', ['name' => 'name']));
        }
        $value = trim(strval($value));
        if (empty($value)) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_A_NAME') . '.');
        }
        if (strlen($value) > $this->limitations['nameMaxLength']) {
            throw new \Exception(_t('USER_NAME_S_MAXIMUM_LENGTH_IS') . " {$this->limitations['nameMaxLength']}.");
        }
        if (!preg_match('/' . self::PATTERN_USER_NAME . '/', $value)) {
            throw new \Exception(_t('USER_THIS_IS_NOT_A_VALID_NAME') . '.');
        }

        return $value;
    }

    /**
     * check if value is an email and return new value.
     *
     * @param mixed $value as it came out of the submitted values
     *
     * @throws \Exception
     */
    private function sanitizeEmail($value): string
    {
        if (empty($value)) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_AN_EMAIL') . '.');
        }

        if (!is_scalar($value)) {
            throw new \Exception(_t('USER_YOU_MUST_SPECIFY_A_STRING', ['name' => 'email']));
        }
        $value = strval($value);
        if (strlen($value) > $this->limitations['emailMaxLength']) {
            throw new \Exception(_t('USER_EMAIL_S_MAXIMUM_LENGTH_IS') . " {$this->limitations['emailMaxLength']}.");
        } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception(_t('USER_THIS_IS_NOT_A_VALID_EMAIL') . '.');
        }

        return $value;
    }
}
