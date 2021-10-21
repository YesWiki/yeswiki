<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\UserManager;

class UserFieldException extends \Exception
{
}

/**
 * @Field({"yeswiki_user", "utilisateur_wikini"})
 */
class UserField extends BazarField
{
    protected $nameField;
    protected $emailField;
    protected $mailingList;
    protected $autoUpdateMail;

    protected const FIELD_NAME_FIELD = 1;
    protected const FIELD_EMAIL_FIELD = 2;
    protected const FIELD_MAILING_LIST = 5;
    protected const FIELD_AUTO_UPDATE_MAIL = 9;

    private const CONFIRM_NAME_SUFFIX = '_confirmNewName';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->nameField = $values[self::FIELD_NAME_FIELD];
        $this->emailField = $values[self::FIELD_EMAIL_FIELD];
        $this->mailingList = $values[self::FIELD_MAILING_LIST];
        $this->autoUpdateMail = in_array($values[self::FIELD_AUTO_UPDATE_MAIL], [true,"1",1], true);

        // We have no default value
        $this->default = null;
        // not searchable
        $this->searchable = null;

        $this->propertyName = 'nomwiki';
        $this->maxChars = 60;
    }

    protected function renderInput($entry)
    {
        return $this->render("@bazar/inputs/user.twig", [
            'value' => $this->getValue($entry)
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $userManager = $this->getService(UserManager::class);
        $mailer = $this->getService(Mailer::class);

        $value = $this->getValue($entry);
        $isImport = isset($GLOBALS['_BAZAR_']['provenance']) && $GLOBALS['_BAZAR_']['provenance'] === 'import';

        if ($value) {
            $wikiName = $value;
            $this->updateEmailIfNeeded($wikiName, $entry[$this->emailField] ?? null);
        } else {
            $wikiName = $entry[$this->nameField];

            $wiki = $this->getWiki();
            if (!$wiki->IsWikiName($wikiName)) {
                $wikiName = genere_nom_wiki($wikiName, 0);
            }
            if ($this->isUserByName($wikiName)) {
                $currentWikiName = $wikiName;
                // If user exist, add a number
                while ($this->isUserByName($wikiName)) {
                    $wikiName = genere_nom_wiki($wikiName);
                }
                if (!$isImport
                        && !in_array($_POST[$this->propertyName.self::CONFIRM_NAME_SUFFIX] ?? false, [true,1,"1"], true)
                        ) {
                    throw new UserFieldException(
                        $this->render("@bazar/inputs/user-confirm.twig", [
                            'confirmName' => $this->propertyName.self::CONFIRM_NAME_SUFFIX,
                            'wikiName' => $currentWikiName,
                            'newWikiName' => $wikiName,
                        ])
                    );
                }
            }
            if ($this->isUserByEmail($entry[$this->emailField])) {
                throw new UserFieldException(_t('BAZ_USER_FIELD_EXISTING_USER_BY_EMAIL'));
            }
            if (!filter_var($entry[$this->emailField], FILTER_VALIDATE_EMAIL)) {
                throw new UserFieldException(_t('USER_THIS_IS_NOT_A_VALID_EMAIL'));
            }
            if (!$isImport
                    && $entry['mot_de_passe_wikini'] !== $entry['mot_de_passe_repete_wikini']) {
                throw new UserFieldException(_t('USER_PASSWORDS_NOT_IDENTICAL'));
            }

            // check existence of user with same
            $userManager->create($wikiName, $entry[$this->emailField], $entry['mot_de_passe_wikini']);

            // Do not send mails if we are importing
            // TODO improve import detection
            if (!$isImport) {
                $mailer->notifyNewUser($wikiName, $entry[$this->emailField]);

                // Check if we need to subscribe the user to a mailing list
                if (isset($this->mailingList) && $this->mailingList != '') {
                    $mailer->subscribeToMailingList($entry[$this->emailField], $this->mailingList);
                }
            }
        }

        // indicateur pour la gestion des droits associee a la fiche.
        $GLOBALS['utilisateur_wikini'] = $wikiName;
        
        return [
            $this->propertyName => $wikiName,
            'fields-to-remove' => [
                'mot_de_passe_wikini',
                'mot_de_passe_repete_wikini',
                $this->propertyName.self::CONFIRM_NAME_SUFFIX
                ]
        ];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        $userManager = $this->getService(UserManager::class);

        if ($value) {
            return $this->render("@bazar/fields/user.twig", [
                'value' => $value,
                'isLoggedUser' => $userManager->getLoggedUser() && $userManager->getLoggedUserName() === $value,
                'editUrl' => $this->getWiki()->href('edit', $value),
                'settingsUrl' => $this->getWiki()->href('', 'ParametresUtilisateur')
            ]);
        }

        return null;
    }

    // GETTERS. Needed to use them in the Twig syntax

    public function getNameField()
    {
        return $this->nameField;
    }

    public function getEmailField()
    {
        return $this->emailField;
    }

    public function getMailingList()
    {
        return $this->mailingList;
    }

    public function getAutoUpdateMail()
    {
        return $this->autoUpdateMail;
    }

    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'nameField' => $this->getNameField(),
                'emailField' => $this->getEmailField(),
                'mailingList' => $this->getMailingList(),
                'autoUpdateMail' => $this->getAutoUpdateMail(),
            ]
        );
    }

    private function isUserByName(string $userName): bool
    {
        $userManager = $this->getService(UserManager::class);
        return !empty($userManager->getOneByName($userName));
    }

    private function isUserByEmail(string $email): bool
    {
        $userManager = $this->getService(UserManager::class);
        return !empty($userManager->getOneByEmail($email));
    }

    private function updateEmailIfNeeded(string $userName, string $email)
    {
        if ($this->getAutoUpdateMail() && !empty($userName) && !empty($email)) {
            $userManager = $this->getService(UserManager::class);
            $user = $userManager->getOneByName($userName);
            $loggedUser = $userManager->getLoggedUser();
            if (!empty($user)
                && (
                    $this->getWiki()->UserIsAdmin()
                        || (
                            !empty($loggedUser)
                            && $user['name'] === $loggedUser['name']
                        )
                )
                && $user['email'] !== $email
                ) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new UserFieldException(_t('USER_THIS_IS_NOT_A_VALID_EMAIL'));
                }
                $userManager->updateEmail($user['name'], $email);
            }
        }
    }
}
