<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Exception\UserFieldException;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Saving an entry must never hand its author the session of another account.
 */
class EntryOwnershipTest extends YesWikiTestCase
{
    private const VICTIM = 'EntryOwnershipTestVictim';
    private const TEMPLATE = "texte***bf_titre***Nom***60***255*** *** *** ***1***\n"
        . "champs_mail***bf_mail***Email***60***255*** *** *** ***1***\n"
        . "utilisateur_wikini***bf_titre***bf_mail***\n";

    private $wiki;
    private $formIds = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $userManager = $this->wiki->services->get(UserManager::class);
        if (!$userManager->getOneByName(self::VICTIM)) {
            $userManager->create([
                'name' => self::VICTIM,
                'email' => 'entryownershipvictim@example.com',
                'password' => 'a-long-enough-password',
            ]);
        }
        $this->wiki->services->get(AuthController::class)->logout();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['utilisateur_wikini']);
        $this->wiki->services->get(AuthController::class)->logout();
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $formManager = $this->wiki->services->get(FormManager::class);
        foreach ($this->formIds as $formId) {
            foreach ($entryManager->search(['formsIds' => [$formId]]) as $entry) {
                $entryManager->delete($entry['id_fiche'], true);
            }
            $formManager->delete($formId);
        }
        $userManager = $this->wiki->services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::VICTIM)) {
            $userManager->delete($user);
        }
    }

    public function testAVisitorCannotTakeTheNameOfAnExistingAccount()
    {
        $formId = $this->createForm();
        $authController = $this->wiki->services->get(AuthController::class);

        try {
            $this->wiki->services->get(EntryManager::class)->create($formId, $this->entry(['nomwiki' => self::VICTIM]));
            $this->fail('An anonymous visitor linked an entry to an existing account.');
        } catch (UserFieldException $exception) {
            $this->assertEmpty($authController->getLoggedUser());
        }
    }

    public function testAnAccountCanLinkAnEntryToItself()
    {
        $formId = $this->createForm();
        $authController = $this->wiki->services->get(AuthController::class);
        $authController->login($this->wiki->services->get(UserManager::class)->getOneByName(self::VICTIM));

        $entry = $this->wiki->services->get(EntryManager::class)->create($formId, $this->entry(['nomwiki' => self::VICTIM]));

        $this->assertSame(self::VICTIM, $entry['nomwiki']);
        $this->assertSame(self::VICTIM, $authController->getLoggedUserName());
    }

    public function testAFormFetchedFromAnotherWikiCannotBeSavedInto()
    {
        $formManager = $this->wiki->services->get(FormManager::class);
        $formId = $formManager->findNewId();
        $form = ['bn_id_nature' => $formId, 'bn_label_nature' => 'Remote', 'bn_template' => self::TEMPLATE, 'bn_condition' => '', '_isExternal_' => true];
        $form['template'] = $formManager->parseTemplate(self::TEMPLATE);
        $form['prepared'] = $formManager->prepareData($form);
        $formManager->cacheForm($formId, $form);

        $this->expectExceptionMessage(_t('BAZ_EXTERNAL_FORM_READ_ONLY'));
        $this->wiki->services->get(EntryManager::class)->create($formId, $this->entry(['nomwiki' => self::VICTIM]));
    }

    private function createForm(): int
    {
        $formId = (int)$this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Entry ownership test form',
            'bn_template' => self::TEMPLATE,
            'bn_condition' => '',
        ]);
        $this->formIds[] = $formId;

        return $formId;
    }

    private function entry(array $overrides): array
    {
        return array_merge([
            'antispam' => 1,
            'bf_titre' => 'Entry ownership test',
            'bf_mail' => 'entryownershipvictim@example.com',
            'mot_de_passe_wikini' => 'another-long-password',
            'mot_de_passe_repete_wikini' => 'another-long-password',
        ], $overrides);
    }
}
