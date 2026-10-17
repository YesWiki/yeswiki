<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Bazar\Service\ActivityPubProfiles;
use YesWiki\Core\Service\ConfigurationService;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';
require_once 'includes/i18n.inc.php';

class ActivityPubProfilesTest extends TestCase
{
    private string $configFile;

    protected function setUp(): void
    {
        $this->configFile = sys_get_temp_dir() . '/yeswiki-activitypub-config-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($this->configFile, "<?php\n\n\$wakkaConfig = ['wakka_name' => 'Test', 'activitypub_profiles' => ['2' => ['username' => 'blog']]];\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->configFile);
    }

    private function profiles(): ActivityPubProfiles
    {
        $config = (new ConfigurationService())->getConfiguration($this->configFile);
        $config->load();

        return new ActivityPubProfiles(new ParameterBag($config->_parameters), new ConfigurationService(), $this->configFile);
    }

    public function testASavedProfileIsWrittenToTheConfigurationAndKeepsTheOtherSettings()
    {
        $this->profiles()->save('1', [
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
        ], $profiles->get('1'));
        $this->assertSame('blog', $profiles->username(2));
        $this->assertSame('1', $profiles->formIdForUsername('AGENDA'));
        $this->assertStringContainsString("'wakka_name' => 'Test'", file_get_contents($this->configFile));
    }

    public function testAUsernameBelongsToOneFormOnly()
    {
        try {
            $this->profiles()->save('1', ['username' => 'Blog']);
            $this->fail('A second form took the username');
        } catch (\Exception $e) {
            $this->assertSame('2', $this->profiles()->formIdForUsername('blog'));
            $this->assertSame('', $this->profiles()->username('1'));
        }
    }

    public function testAFormCanKeepItsOwnUsername()
    {
        $this->profiles()->save('2', ['username' => 'blog', 'summary' => 'Les actus']);

        $this->assertSame('Les actus', $this->profiles()->get('2')['summary']);
    }

    public static function refusedProfiles(): array
    {
        return [
            'empty username' => [['username' => '']],
            'username with a dot' => [['username' => 'a.b']],
            'username too long' => [['username' => str_repeat('a', 31)]],
            'script address' => [['username' => 'a', 'icon' => 'javascript:alert(1)']],
            'path leaving the wiki' => [['username' => 'a', 'image' => '../wakka.config.php']],
            'quote in an address' => [['username' => 'a', 'icon' => 'https://x.example/"onerror="alert(1)']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedProfiles')]
    public function testInvalidProfilesAreRefusedAndNothingIsWritten(array $profile)
    {
        $before = file_get_contents($this->configFile);
        try {
            $this->profiles()->save('1', $profile);
            $this->fail('The profile was accepted');
        } catch (\Exception $e) {
            $this->assertSame($before, file_get_contents($this->configFile));
        }
    }

    public function testOnlySixFieldsAreKept()
    {
        $fields = [];
        for ($i = 1; $i <= 8; $i++) {
            $fields["Champ $i"] = "Valeur $i";
        }

        $this->profiles()->save('1', ['username' => 'agenda', 'fields' => $fields]);

        $this->assertCount(ActivityPubProfiles::MAX_FIELDS, $this->profiles()->get('1')['fields']);
    }
}
