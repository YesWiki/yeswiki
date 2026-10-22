<?php

namespace YesWiki\Test\Identity;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Action\UserSettingsAction;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Fields added to the User form, saved from the account screen like any entry field. */
class AccountProfileFieldsTest extends YesWikiTestCase
{
    private const NAME = 'AccountProfileFieldsTestUser';

    /** @var array<string, mixed>|null */
    private ?array $userForm = null;

    protected function setUp(): void
    {
        parent::setUp();
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $this->userForm = $formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        $this->assertNotNull($this->userForm, 'the User form should exist -- run ./yeswicli migrate');
        $formManager->update([
            'id' => $this->userForm['id'],
            'label' => $this->userForm['label'],
            'template' => array_merge($this->userForm['template'], [
                ['type' => 'texte', 'name' => 'bf_ville', 'label' => 'Ville'],
                ['type' => 'map', 'name' => 'bf_geolocation', 'label' => 'Carte'],
            ]),
        ]);
        $this->getWiki()->services->get(UserManager::class)->create(self::NAME, 'apft@example.tld', 'Aa1!aaaaRegression');
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $userManager = $services->get(UserManager::class);
        $user = $userManager->getOneByName(self::NAME);
        if ($user) {
            $userManager->delete($user);
        }
        $services->get(PageManager::class)->deleteOrphaned(self::NAME);
        if ($this->userForm !== null) {
            $services->get(FormManager::class)->update([
                'id' => $this->userForm['id'],
                'label' => $this->userForm['label'],
                'template' => $this->userForm['template'],
            ]);
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $post */
    private function saveFromAccountScreen(array $post): User
    {
        $services = $this->getWiki()->services;
        $userManager = $services->get(UserManager::class);
        $user = $userManager->getOneByName(self::NAME);
        $this->assertNotNull($user);

        $action = new UserSettingsAction();
        $action->setServices($services);
        $values = (new \ReflectionClass($action))->getMethod('postedProfileValues')->invoke($action, $post, $user);
        $userManager->update($user, $values);

        $saved = $userManager->getOneByName(self::NAME);
        $this->assertNotNull($saved);

        return $saved;
    }

    /** @return array<string, mixed> */
    private function storedBody(): array
    {
        $page = $this->getWiki()->services->get(PageManager::class)->getOne(self::NAME, null, true, true);

        return is_array($page['body'] ?? null) ? $page['body'] : [];
    }

    public function testTheGeolocationIsSaved(): void
    {
        $this->saveFromAccountScreen([
            'bf_ville' => 'Lyon',
            'bf_geolocation' => ['latitude' => '45.76', 'longitude' => '4.83'],
        ]);

        $body = $this->storedBody();
        $this->assertSame('Lyon', $body['bf_ville'] ?? null);
        $this->assertSame('45.76', $body['bf_geolocation']['latitude'] ?? null);
        $this->assertSame('4.83', $body['bf_geolocation']['longitude'] ?? null);
    }

    public function testEmptyingTheMapClearsTheGeolocation(): void
    {
        $this->saveFromAccountScreen(['bf_geolocation' => ['latitude' => '45.76', 'longitude' => '4.83']]);
        $this->saveFromAccountScreen(['bf_geolocation' => ['latitude' => '', 'longitude' => '']]);

        $this->assertEmpty($this->storedBody()['bf_geolocation'] ?? null);
    }
}
