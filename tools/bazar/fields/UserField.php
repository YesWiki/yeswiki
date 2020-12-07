<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\Mailer;
use YesWiki\Core\Service\UserManager;

/**
 * @Field({"yeswiki_user", "utilisateur_wikini"})
 */
class UserField extends BazarField
{
    protected $nameField;
    protected $emailField;
    protected $mailingList;

    protected const FIELD_NAME_FIELD = 1;
    protected const FIELD_EMAIL_FIELD = 2;
    protected const FIELD_MAILING_LIST = 5;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->nameField = $values[self::FIELD_NAME_FIELD];
        $this->emailField = $values[self::FIELD_EMAIL_FIELD];
        $this->mailingList = $values[self::FIELD_MAILING_LIST];

        // We have no default value
        $this->default = null;

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

        if( $value ) {
            $wikiName = $value;
        } else {
            $wikiName = $entry[$this->nameField];

            if (!$GLOBALS['wiki']->IsWikiName($wikiName)) {
                $wikiName = genere_nom_wiki($wikiName, 0);
                // If user exist, add a number
                while ($GLOBALS['wiki']->LoadUser($wikiName)) {
                    $wikiName = genere_nom_wiki($wikiName);
                }
            }

            $userManager->create($wikiName, $entry[$this->emailField], $entry['mot_de_passe_wikini']);

            // Do not send mails if we are importing
            // TODO improve import detection
            if (!isset($GLOBALS['_BAZAR_']['provenance']) || $GLOBALS['_BAZAR_']['provenance'] !== 'import') {
                $mailer->notifyNewUser($wikiName, $entry[$this->emailField]);

                // Check if we need to subscribe the user to a mailing list
                if (isset($this->mailingList) && $this->mailingList != '') {
                    $mailer->subscribeToMailingList($entry[$this->emailField], $this->mailingList);
                }
            }
        }

        // indicateur pour la gestion des droits associee a la fiche.
        $GLOBALS['utilisateur_wikini'] = $wikiName;
        
        return [$this->propertyName => $wikiName];
    }

    public function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        $userManager = $this->getService(UserManager::class);

        if( $value ) {
            return $this->render("@bazar/fields/user.twig", [
                'value' => $value,
                'isLoggedUser' => $userManager->getLoggedUser() && $userManager->getLoggedUserName() === $value,
                'editUrl' => $GLOBALS['wiki']->href('edit', $value),
                'settingsUrl' => $GLOBALS['wiki']->href('', 'ParametresUtilisateur')
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
}
