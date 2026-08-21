<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use YesWiki\Content\Field\CheckboxField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Field\TagsField;
use YesWiki\Identity\Exception\UserFieldException;
use YesWiki\Identity\Exception\UserNameAlreadyUsedException;
use YesWiki\Identity\Service\AccountJustCreated;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * Applies a form's `entry_*` properties (ADR-0010) to entries: title computation + tag generation, entry ACL stamping, the optional comments toggle, presentation metadata, and account creation.
 */
class FormPropertiesService
{
    public const COMMENTS_TOGGLE_POST_KEY = 'activate_comments';
    public const COMMENT_YES = 'oui';
    public const COMMENT_NO = 'non';

    /** The historical implicit title convention: a visitor-typed bf_titre field. */
    public const DEFAULT_TITLE_TEMPLATE = '{{bf_titre}}';

    protected UrlFormatter $urlFormatter;

    /** Every entry_* form property except entry_title_template (which is required). */
    public const OPTIONAL_PROPERTIES = [
        'entry_read_access', 'entry_write_access', 'entry_comment_access',
        'entry_permit_activate_comments', 'entry_metadatas', 'entry_creates_user',
        'entry_bookmarklet',
    ];

    protected ContainerInterface $container;
    protected ParameterBagInterface $params;
    protected AclService $aclService;
    protected PageManager $pageManager;
    protected GroupOperationsService $groupOperationsService;

    public function __construct(
        ContainerInterface $container,
        ParameterBagInterface $params,
        AclService $aclService,
        PageManager $pageManager,
        GroupOperationsService $groupOperationsService,
        UrlFormatter $urlFormatter,
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
        $this->params = $params;
        $this->aclService = $aclService;
        $this->pageManager = $pageManager;
        $this->groupOperationsService = $groupOperationsService;
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    private function getService(string $className)
    {
        return $this->container->get($className);
    }

    /**
     * Computes the entry's `title` from the form's `entry_title_template` -- a `{{field}}` substitution template ("{{bf_nom}} {{bf_prenom}}"), with per-type value resolution for enum/file/image fields (option label rather than key, filename rather than raw value).
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    public function computeTitle(array $form, array $entry): string
    {
        $template = trim((string)($form['entry_title_template'] ?? ''));
        if ($template === '') {
            $template = self::DEFAULT_TITLE_TEMPLATE;
        }

        $value = $this->getService(HtmlPurifierService::class)->cleanHTML($template);
        $formManager = $this->getService(FormManager::class);

        if (!$this->getService(ImportContext::class)->isImporting()) {
            $formId = $entry['form_id'] ?? null;
            foreach ($this->referencedFieldNames($value) as $fieldName) {
                $field = $formManager->findFieldFromNameOrPropertyName($fieldName, $formId);
                if ($field instanceof EnumField || $field instanceof FileField) {
                    // BazarField::getValue() is protected -- calling it from here was a fatal
                    // error for every title template naming an enum or file field. Read the
                    // stored value the way FieldRoleResolver::value() does, flattening the
                    // list shape a tags field stores.
                    $fieldValue = $entry[(string)$field->getPropertyName()] ?? null;
                    if (is_array($fieldValue)) {
                        $fieldValue = implode(',', array_filter($fieldValue, 'is_string'));
                    }
                    if ($field instanceof CheckboxField) {
                        $formattedValue = $field->formatValuesBeforeSave($entry)[$field->getPropertyName()];
                        $fieldValues = $field->getValues([$field->getPropertyName() => $formattedValue]);
                        $replacement = $field->getOptions()[$fieldValues[0] ?? null] ?? '';
                    } elseif ($field instanceof TagsField) {
                        $fieldValues = explode(',', (string)$fieldValue);
                        $replacement = trim($fieldValues[0]);
                    } elseif ($field instanceof EnumField) {
                        $replacement = $field->getOptions()[$fieldValue] ?? '';
                    } elseif ($field instanceof ImageField) {
                        $filenameKey = 'filename-' . $field->getPropertyName();
                        $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
                        if (!empty($request->request->get($filenameKey))) {
                            $replacement = StringUtilService::asFilename($request->request->get($filenameKey));
                            if (empty($replacement)) {
                                $replacement = 'image';
                            }
                        } elseif (!empty($fieldValue)) {
                            $replacement = $fieldValue;
                        } else {
                            $replacement = 'image';
                        }
                    } else {
                        if (!empty($_FILES[$field->getPropertyName()]['name'])) {
                            $replacement = StringUtilService::asFilename($_FILES[$field->getPropertyName()]['name']);
                            if (empty($replacement)) {
                                $replacement = 'file';
                            }
                        } elseif (!empty($fieldValue)) {
                            $replacement = $fieldValue;
                        } else {
                            $replacement = 'file';
                        }
                    }
                    $value = str_replace('{{' . $fieldName . '}}', (string)$replacement, $value);
                } elseif (isset($entry[$fieldName])) {
                    $value = str_replace('{{' . $fieldName . '}}', $entry[$fieldName], $value);
                }
            }
        }

        $value = trim((string)preg_replace('#{{(.*)}}#U', '', $value));

        return $value !== '' ? $value : trim((string)($entry['tag'] ?? ''));
    }

    /**
     * The title of an already-stored Content: what it carries, or the computed title, or its tag.
     *
     * @param array<string, mixed>|null $form
     * @param array<string, mixed>      $entry
     */
    public function titleOf(?array $form, array $entry): string
    {
        $stored = trim((string)($entry['title'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return $form === null ? trim((string)($entry['tag'] ?? '')) : $this->computeTitle($form, $entry);
    }

    /**
     * Which field of this form *carries* the title -- the title role, in the shape ticket 11 gives every other role: core asks the form instead of assuming `bf_titre`.
     *
     * @param array<string, mixed>|null $form
     */
    public function titleFieldName(?array $form): ?string
    {
        if ($form === null) {
            return null;
        }

        $template = trim((string)($form['entry_title_template'] ?? '')) ?: self::DEFAULT_TITLE_TEMPLATE;
        foreach ($this->referencedFieldNames($template) as $fieldName) {
            $fieldName = trim($fieldName);
            if ($fieldName !== '' && $this->getService(FormManager::class)->findFieldFromNameOrPropertyName($fieldName, $form['id'] ?? null) !== null) {
                return $fieldName;
            }
        }

        return null;
    }

    /**
     * The {{field}} names a title template references.
     *
     * @return list<string>
     */
    public function referencedFieldNames(string $titleTemplate): array
    {
        preg_match_all('#{{(.*)}}#U', $titleTemplate, $matches);

        return $matches[1];
    }

    /**
     * Generates a new entry's tag from its title: a lowercase slug (ADR-0010), collisions suffixed -2 / -3 by PageManager::suggestFreeTag().
     */
    public function generateTag(string $title): string
    {
        $slug = (new AsciiSlugger())->slug($title)->lower()->toString();
        if ($slug === '') {
            $slug = 'entry';
        }

        return $this->pageManager->suggestFreeTag($slug);
    }

    /**
     * Stamps the form's entry_read_access / entry_write_access / entry_comment_access onto a saved entry.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    public function applyEntryAcls(array $form, array $entry, bool $force = false): void
    {
        $tag = $entry['tag'] ?? '';
        if (!is_string($tag) || trim($tag) === '') {
            return;
        }

        $toStamp = [];
        foreach (['read' => 'entry_read_access', 'write' => 'entry_write_access'] as $mode => $property) {
            $right = trim((string)($form[$property] ?? ''));
            if ($right === '') {
                continue;
            }
            if ($force || empty($this->aclService->load($tag, $mode, false)['list'])) {
                $toStamp[$mode] = $this->replaceWithCreator($right, $entry);
            }
        }

        if ($this->permitsActivateComments($form)) {
            $toStamp['comment'] = $this->resolveCommentsChoice($form, $entry);
        } else {
            $commentRight = trim((string)($form['entry_comment_access'] ?? ''));
            if ($commentRight !== '' && ($force || empty($this->aclService->load($tag, 'comment', false)['list']))) {
                $toStamp['comment'] = $this->replaceWithCreator($commentRight, $entry);
            }
        }

        if (!empty($toStamp)) {
            $this->aclService->saveMany($tag, $toStamp);
        }
    }

    /**
     * @param array<string, mixed> $form
     */
    public function permitsActivateComments(array $form): bool
    {
        return in_array($form['entry_permit_activate_comments'] ?? false, [true, 1, '1', 'true'], true);
    }

    public function getCommentsType(): string
    {
        $commentsType = $this->params->get('comments_handler');

        return (empty($commentsType) || !is_string($commentsType)) ? '' : $commentsType;
    }

    /**
     * The author's posted comments-toggle choice opens or closes the entry's comments.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    private function resolveCommentsChoice(array $form, array $entry): string
    {
        $choice = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->get(self::COMMENTS_TOGGLE_POST_KEY, '');

        $commentsType = $this->getCommentsType();
        if (in_array($commentsType, ['', 'yeswiki'], true) && $choice === self::COMMENT_YES) {
            return $this->replaceWithCreator(
                trim((string)($form['entry_comment_access'] ?? '')) ?: '+',
                $entry
            );
        }

        return 'comments-closed';
    }

    /**
     * Renders the comments toggle appended at the end of the entry form when entry_permit_activate_comments is on.
     *
     * @param array<string, mixed>      $form
     * @param array<string, mixed>|null $entry
     */
    public function renderCommentsToggle(array $form, ?array $entry): string
    {
        if (!$this->permitsActivateComments($form)) {
            return '';
        }

        $commentsAlreadyClosed = false;
        $isYesWikiType = in_array($this->getCommentsType(), ['', 'yeswiki'], true);
        if ($isYesWikiType && !empty($entry['tag'])) {
            $currentCommentAcl = $this->aclService->load($entry['tag'], 'comment', false);
            $commentsAlreadyClosed = (!empty($currentCommentAcl['list']) && $currentCommentAcl['list'] == 'comments-closed');
        }

        return $this->getService(TemplateEngine::class)->render('@core/inputs/comments.twig', [
            'name' => self::COMMENTS_TOGGLE_POST_KEY,
            'value' => $commentsAlreadyClosed ? self::COMMENT_NO : '',
            'options' => [self::COMMENT_NO => _t('NO'), self::COMMENT_YES => _t('YES')],
            'label' => _t('BAZ_ACTIVATE_COMMENTS'),
            'hint' => _t('BAZ_ACTIVATE_COMMENTS_HINT'),
            'commentsType' => $this->getCommentsType(),
            'showAlertForCommentsNotActivated' => $isYesWikiType && $this->params->get('comments_activated') !== true,
        ]);
    }

    /**
     * @param string               $right an ACL line, in which 'user' and '#' stand for the entry's creator
     * @param array<string, mixed> $entry
     *
     * @return string
     */
    private function replaceWithCreator($right, array $entry)
    {
        if ($right === 'user' || $right === '#') {
            return $entry['nomwiki'] ?? $this->container->get(AuthenticationService::class)->getLoggedUserName();
        }

        return $right;
    }

    /**
     * Applies the form's entry_metadatas {theme, skeleton, style, background_image, css_preset} to the saved entry's page metadata (retired metadatas field).
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     */
    public function applyEntryMetadatas(array $form, array $entry): void
    {
        $config = $form['entry_metadatas'] ?? null;
        if (!is_array($config) || empty($entry['tag'])) {
            return;
        }

        $this->pageManager->setMetadata($entry['tag'], [
            'theme' => empty($config['theme']) ? THEME_PAR_DEFAUT : $config['theme'],
            'style' => empty($config['style']) ? CSS_PAR_DEFAUT : $config['style'],
            'squelette' => empty($config['skeleton']) ? SQUELETTE_PAR_DEFAUT : $config['skeleton'],
            'bgimg' => $config['background_image'] ?? '',
        ] + (
            empty($config['css_preset']) ? [] : ['favorite_preset' => $config['css_preset']]
        ));
    }

    private const CONFIRM_NAME_SUFFIX = '_confirmNewName';
    private const FORCE_LABEL = '_force_label';
    private const USER_PROPERTY_NAME = 'nomwiki';

    /**
     * @param array<string, mixed> $form
     */
    public function createsUser(array $form): bool
    {
        return is_array($form['entry_creates_user'] ?? null);
    }

    /**
     * Renders the account-creation block (username + passwords) appended to the entry form when entry_creates_user is configured.
     *
     * @param array<string, mixed>      $form
     * @param array<string, mixed>|null $entry
     */
    public function renderUserCreationInputs(array $form, ?array $entry): string
    {
        if (!$this->createsUser($form)) {
            return '';
        }
        $entry = $entry ?? [];
        $config = $form['entry_creates_user'];

        $value = $entry[self::USER_PROPERTY_NAME] ?? '';
        $authenticationService = $this->getService(AuthenticationService::class);
        $userManager = $this->getService(UserManager::class);
        $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
        $loggedUser = $authenticationService->getLoggedUser();
        $message = null;
        if (!empty($loggedUser)) {
            $associatedUser = $userManager->getOneByName($loggedUser['name']);
            if (!empty($associatedUser['name'])) {
                if (empty($value) || !$this->isUserByName($value)) {
                    $value = $associatedUser['name'];
                    $message = str_replace(
                        ['{wikiname}', '{email}'],
                        [$value, $associatedUser['email']],
                        _t('BAZ_USER_FIELD_ALREADY_CONNECTED')
                    );
                }
                if ($value !== $loggedUser['name'] && $this->aclService->isAdmin()) {
                    // the name an admin typed may belong to nobody
                    $associatedUser = $userManager->getOneByName($value);
                }
                $associatedEmail = $associatedUser['email'] ?? '';
                if ($value === $loggedUser['name'] || ($this->aclService->isAdmin() && !empty($associatedEmail))) {
                    $autoUpdate = in_array($config['update_email'] ?? '', [true, '1', 1], true);
                    $message = (!empty($message) ? $message . "\n" : '') . ($autoUpdate ? str_replace(
                        '{email}',
                        $associatedEmail,
                        _t('BAZ_USER_FIELD_ALREADY_CONNECTED_AUTOUPDATE')
                    ) : '');
                }
            }
        }

        return $this->getService(TemplateEngine::class)->render('@core/inputs/user.twig', [
            'value' => $value,
            'creationMode' => empty($entry[self::USER_PROPERTY_NAME]),
            'message' => $message,
            'userIsAdmin' => $this->aclService->isAdmin(),
            'userName' => $loggedUser['name'] ?? null,
            'userEmail' => $loggedUser['email'] ?? null,
            'emailField' => $config['email_field'] ?? '',
            'forceLabel' => self::USER_PROPERTY_NAME . self::FORCE_LABEL,
            'forceLabelChecked' => $request->request->get(self::USER_PROPERTY_NAME . self::FORCE_LABEL, false),
        ]);
    }

    /**
     * Creates (or links) the wiki account configured by entry_creates_user during entry save.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed> the account's `nomwiki` plus the `fields-to-remove` the entry must not keep, or [] when the form creates no account
     *
     * @throws UserFieldException
     */
    public function applyUserCreation(array $form, array $entry): array
    {
        if (!$this->createsUser($form)) {
            return [];
        }
        $config = $form['entry_creates_user'];
        $nameField = $config['name_field'] ?? 'bf_titre';
        $emailField = $config['email_field'] ?? 'bf_mail';
        $autoUpdateMail = in_array($config['update_email'] ?? '', [true, '1', 1], true);
        $addToGroup = trim((string)($config['add_to_group'] ?? ''));
        $mailingList = trim((string)($config['mailing_list'] ?? ''));

        $userOperationsService = $this->getService(UserOperationsService::class);
        $userManager = $this->getService(UserManager::class);
        $mailer = $this->getService(Mailer::class);
        $request = $this->container->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();

        $value = $entry[self::USER_PROPERTY_NAME] ?? '';
        $isImport = $this->getService(ImportContext::class)->isImporting();

        if (
            $this->aclService->isAdmin()
            && in_array($request->request->get(self::USER_PROPERTY_NAME . self::FORCE_LABEL, false), [true, 'true', 1, '1'], true)
        ) {
            $existingUser = $userManager->getOneByEmail($entry[$emailField]);
            $value = !empty($existingUser) ? $existingUser['name'] : null;
        }

        if ($value && $this->isUserByName($value)) {
            $wikiName = $value;
            $this->addUserToGroups($wikiName, $entry, $addToGroup);
            $this->updateEmailIfNeeded($wikiName, $entry[$emailField] ?? null, $autoUpdateMail);
        } else {
            $wikiName = $entry[$nameField] ?? '';
            if (!$this->urlFormatter->isWikiName($wikiName)) {
                $wikiName = $this->getService(WikiNameGenerator::class)->generate($wikiName, 0);
            }
            if ($this->isUserByName($wikiName)) {
                $currentWikiName = $wikiName;
                $wikiName = $this->findANewNotExistingUserName($currentWikiName);
                if (
                    !$isImport
                    && (
                        !$request->request->has(self::USER_PROPERTY_NAME . self::CONFIRM_NAME_SUFFIX)
                        || !in_array($request->request->get(self::USER_PROPERTY_NAME . self::CONFIRM_NAME_SUFFIX), [true, 1, '1'], true)
                    )
                ) {
                    throw new UserFieldException($this->getService(TemplateEngine::class)->render('@core/inputs/user-confirm.twig', ['confirmName' => self::USER_PROPERTY_NAME . self::CONFIRM_NAME_SUFFIX, 'wikiName' => $currentWikiName, 'newWikiName' => $wikiName]));
                }
            }
            if (!isset($entry[$emailField])) {
                throw new \Exception("\$entry[{$emailField}] should be set for entry_creates_user");
            }
            if (!$isImport) {
                if (!isset($entry['mot_de_passe_repete_wikini'])) {
                    throw new \Exception("\$entry['mot_de_passe_repete_wikini'] should be set for entry_creates_user");
                }
                if ($entry['mot_de_passe_wikini'] !== $entry['mot_de_passe_repete_wikini']) {
                    throw new UserFieldException(_t('USER_PASSWORDS_NOT_IDENTICAL'));
                }
            }

            try {
                $userOperationsService->create([
                    'name' => $wikiName,
                    'email' => $entry[$emailField],
                    'password' => $entry['mot_de_passe_wikini'],
                ]);
            } catch (UserNameAlreadyUsedException $ex) {
                throw new UserFieldException(_t('BAZ_USER_FIELD_EXISTING_USER_BY_EMAIL'));
            } catch (\Exception $ex) {
                throw new UserFieldException($ex->getMessage() . ' User: ' . $wikiName . ' - Email: ' . $entry[$emailField], $ex->getCode(), $ex);
            }

            $this->addUserToGroups($wikiName, $entry, $addToGroup);

            if (!$isImport) {
                $mailer->notifyNewUser($wikiName, $entry[$emailField]);
                if ($mailingList !== '') {
                    $mailer->subscribeToMailingList($entry[$emailField], $mailingList);
                }
            }
        }

        $this->getService(AccountJustCreated::class)->record($wikiName);

        return [
            self::USER_PROPERTY_NAME => $wikiName,
            'fields-to-remove' => [
                'mot_de_passe_wikini',
                'mot_de_passe_repete_wikini',
                self::USER_PROPERTY_NAME . self::CONFIRM_NAME_SUFFIX,
                self::USER_PROPERTY_NAME . self::FORCE_LABEL,
            ],
        ];
    }

    private function isUserByName(string $userName): bool
    {
        return !empty($this->getService(UserManager::class)->getOneByName($userName));
    }

    private function updateEmailIfNeeded(string $userName, ?string $email, bool $autoUpdateMail): void
    {
        if ($autoUpdateMail && !empty($userName) && !empty($email)) {
            $authenticationService = $this->getService(AuthenticationService::class);
            $userOperationsService = $this->getService(UserOperationsService::class);
            $userManager = $this->getService(UserManager::class);
            $user = $userManager->getOneByName($userName);
            $loggedUser = $authenticationService->getLoggedUser();
            if (
                !empty($user)
                && (
                    $this->aclService->isAdmin()
                    || (!empty($loggedUser) && $user['name'] === $loggedUser['name'])
                )
                && $user['email'] !== $email
            ) {
                try {
                    $userOperationsService->update($user, ['email' => $email]);
                } catch (UserNameAlreadyUsedException $ex) {
                    throw new UserFieldException(_t('BAZ_USER_FIELD_EXISTING_USER_BY_EMAIL'));
                } catch (\Exception $ex) {
                    throw new UserFieldException($ex->getMessage(), $ex->getCode(), $ex);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function addUserToGroups(string $wikiName, ?array $entry, string $addToGroup): void
    {
        if ($addToGroup === '') {
            return;
        }
        $groups = explode(',', $addToGroup);
        $groupsNames = [];
        $existingsGroups = $this->groupOperationsService->getAll();
        $formManager = $this->getService(FormManager::class);
        $userManager = $this->getService(UserManager::class);
        foreach ($groups as $group) {
            $group = trim($group);
            $forceGroupCreation = (substr($group, 0, 1) === '+');
            $groupName = substr($group, $forceGroupCreation ? 1 : 0);
            if (substr($groupName, 0, 1) !== '@') {
                $field = $formManager->findFieldFromNameOrPropertyName($groupName, $entry['form_id'] ?? null);
                if (!empty($field) && !empty($entry[$field->getPropertyName()])) {
                    $groupsNamesFromField = explode(',', $entry[$field->getPropertyName()]);
                    foreach ($groupsNamesFromField as $groupNameFromField) {
                        if ($this->userMustBeAddedToGroup($wikiName, $groupNameFromField, $forceGroupCreation, $userManager, $existingsGroups)) {
                            $groupsNames[] = $groupNameFromField;
                        }
                    }
                }
            } else {
                $groupName = substr($groupName, 1);
                if ($this->userMustBeAddedToGroup($wikiName, $groupName, $forceGroupCreation, $userManager, $existingsGroups)) {
                    $groupsNames[] = $groupName;
                }
            }
        }

        $groupsNames = array_unique($groupsNames);

        foreach ($groupsNames as $groupName) {
            $previousACL = !in_array($groupName, $existingsGroups, true)
                ? ''
                : $this->groupOperationsService->getMembersText($groupName) . "\n";
            $this->groupOperationsService->setMembersFromAclText($groupName, $previousACL . $wikiName);
        }
    }

    /**
     * @param array<string> $existingsGroups the names of every group that already exists
     */
    private function userMustBeAddedToGroup(
        string $wikiName,
        string $groupName,
        bool $forceGroupCreation,
        UserManager $userManager,
        array $existingsGroups
    ): bool {
        if (!preg_match('/^[A-Za-z0-9]+$/m', $groupName)) {
            return false;
        }

        if (in_array($groupName, $existingsGroups, true)) {
            if (!$userManager->isInGroup($groupName, $wikiName, false)) {
                return true;
            }
        } elseif ($forceGroupCreation) {
            return true;
        }

        return false;
    }

    private function findANewNotExistingUserName(string $firstWikiName): string
    {
        $baseWikiName = preg_replace('/[0-9]*$/', '', $firstWikiName);

        for ($i = 1; $i < 1000; $i++) {
            $newName = "$baseWikiName$i";
            if (!$this->isUserByName($newName)) {
                return $newName;
            }
        }

        throw new UserFieldException('Impossible to find a new user name !');
    }
}
