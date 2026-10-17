<?php

namespace YesWiki\Test\Federation;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\FormManager;
use YesWiki\Federation\Service\ActivityPubProfiles;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A form's ActivityPub profile is checked before it is stored with the form, and a username names one form only. */
class ActivityPubProfilesTest extends YesWikiTestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $forms = [
        '1' => ['id' => '1', 'activitypub' => []],
        '2' => ['id' => '2', 'activitypub' => ['username' => 'blog']],
    ];
    private int $saves = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki();
    }

    private function profiles(): ActivityPubProfiles
    {
        $formManager = $this->createStub(FormManager::class);
        $formManager->method('getAll')->willReturnCallback(fn () => $this->forms);
        $formManager->method('saveActivitypubProfile')->willReturnCallback(function ($formId, array $profile) {
            $this->forms[(int)$formId]['activitypub'] = $profile;
            $this->saves++;
        });
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($formManager);

        return new ActivityPubProfiles($container);
    }

    public function testAProfileIsCleanedAndStoredWithTheForm(): void
    {
        $this->profiles()->save($this->forms[1], [
            'username' => ' Agenda ',
            'summary' => 'Les rencontres',
            'icon' => 'files/Logo.png',
            'image' => 'https://cdn.example/banner.jpg',
            'fields' => ['Site' => 'https://us.example/', '' => 'orphan', 'Empty' => ''],
        ]);

        $profiles = $this->profiles();
        $this->assertSame([
            'username' => 'agenda',
            'summary' => 'Les rencontres',
            'icon' => 'files/Logo.png',
            'image' => 'https://cdn.example/banner.jpg',
            'fields' => ['Site' => 'https://us.example/'],
            'approve_followers' => false,
        ], $profiles->get($this->forms[1]));
        $this->assertSame('1', $profiles->formIdForUsername('AGENDA'));
    }

    public function testAUsernameBelongsToOneFormOnly(): void
    {
        try {
            $this->profiles()->save($this->forms[1], ['username' => 'Blog']);
            $this->fail('A second form took the username');
        } catch (\Exception $e) {
            $this->assertSame('2', $this->profiles()->formIdForUsername('blog'));
            $this->assertSame(0, $this->saves);
        }
    }

    public function testAFormCanKeepItsOwnUsername(): void
    {
        $this->profiles()->save($this->forms[2], ['username' => 'blog', 'summary' => 'Les actus']);

        $this->assertSame('Les actus', $this->profiles()->get($this->forms[2])['summary']);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function refusedProfiles(): array
    {
        return [
            'empty username' => [['username' => '']],
            'username with a dot' => [['username' => 'a.b']],
            'username too long' => [['username' => str_repeat('a', 31)]],
            'script address' => [['username' => 'a', 'icon' => 'javascript:alert(1)']],
            'path leaving the wiki' => [['username' => 'a', 'image' => '../yeswiki.config.php']],
            'quote in an address' => [['username' => 'a', 'icon' => 'https://x.example/"onerror="alert(1)']],
        ];
    }

    /** @param array<string, mixed> $profile */
    #[DataProvider('refusedProfiles')]
    public function testInvalidProfilesAreRefusedAndNothingIsStored(array $profile): void
    {
        try {
            $this->profiles()->save($this->forms[1], $profile);
            $this->fail('The profile was accepted');
        } catch (\Exception $e) {
            $this->assertSame(0, $this->saves);
        }
    }

    public function testOnlySixFieldsAreKept(): void
    {
        $fields = [];
        for ($i = 1; $i <= 8; $i++) {
            $fields["Champ $i"] = "Valeur $i";
        }

        $this->profiles()->save($this->forms[1], ['username' => 'agenda', 'fields' => $fields]);

        $this->assertCount(ActivityPubProfiles::MAX_FIELDS, $this->profiles()->get($this->forms[1])['fields']);
    }
}
