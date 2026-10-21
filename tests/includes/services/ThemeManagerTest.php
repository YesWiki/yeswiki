<?php

namespace YesWiki\Test\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\ThemeManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Templates\Service\Utils;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Theme settings coming from a request must neither break the page nor write anything but CSS values.
 */
class ThemeManagerTest extends YesWikiTestCase
{
    private const PRESET_USER = 'ThemeManagerTestUser';
    private const PRESET_FILE = 'ThemeManagerTestPreset.css';

    private $wiki;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
    }

    protected function tearDown(): void
    {
        $this->wiki->request->request->replace([]);
        @unlink(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . self::PRESET_FILE);
        $this->wiki->services->get(AuthController::class)->logout();
        $userManager = $this->wiki->services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::PRESET_USER)) {
            $userManager->delete($user);
        }
    }

    private function presetPost(array $overrides = []): array
    {
        return array_merge([
            'primary-color' => '#1a89a0',
            'secondary-color-1' => 'rgb(214, 54, 0)',
            'secondary-color-2' => '#4e9a06',
            'neutral-color' => '#4e5056',
            'neutral-soft-color' => '#b3b3b3',
            'neutral-light-color' => '#ffffff',
            'main-text-fontsize' => '17px',
            'main-text-fontfamily' => "'Nunito', sans-serif",
            'main-title-fontfamily' => "'Nunito', sans-serif",
        ], $overrides);
    }

    private function logInAUser(): void
    {
        $userManager = $this->wiki->services->get(UserManager::class);
        if (!$userManager->getOneByName(self::PRESET_USER)) {
            $userManager->create([
                'name' => self::PRESET_USER,
                'email' => 'thememanagertest@example.com',
                'password' => 'a-long-enough-password',
            ]);
        }
        $this->wiki->services->get(AuthController::class)->login($userManager->getOneByName(self::PRESET_USER));
    }

    private function offlineThemeManager(): ThemeManager
    {
        $services = $this->wiki->services;

        return new class($services->get(Wiki::class), $services->get(TemplateEngine::class), $services->get(PageManager::class), $services->get(ParameterBagInterface::class), $services->get(Performer::class), $services->get(SecurityController::class), $services->get(Utils::class)) extends ThemeManager {
            protected function getFontFiles(string $fontFamily): string
            {
                return '';
            }
        };
    }

    public function testAPresetWithOrdinaryValuesIsSaved()
    {
        $this->logInAUser();

        $result = $this->offlineThemeManager()->addCustomCSSPreset(self::PRESET_FILE, $this->presetPost());

        $this->assertTrue($result['status'], $result['message']);
    }

    public function testAPresetValueCannotCarryAnythingButACssValue()
    {
        $this->logInAUser();
        $themeManager = $this->offlineThemeManager();

        foreach (['red; } body { display: none', '<?php echo 1; ?>', "red\nx", ['red']] as $value) {
            $result = $themeManager->addCustomCSSPreset(self::PRESET_FILE, $this->presetPost(['primary-color' => $value]));

            $this->assertFalse($result['status'], var_export($value, true));
            $this->assertFileDoesNotExist(ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . self::PRESET_FILE);
        }
    }

    public function testArrayValuesForThemeKeysAreIgnored()
    {
        $themeManager = $this->wiki->services->get(ThemeManager::class);
        $themeManager->loadTemplates([]);
        $expected = [
            $themeManager->getFavoriteTheme(),
            $themeManager->getFavoriteSquelette(),
            $themeManager->getFavoriteStyle(),
            $themeManager->getFavoritePreset(),
        ];

        $this->wiki->request->request->replace([
            'theme' => ['margot', 'bootstrap'],
            'squelette' => ['1col.tpl.html'],
            'style' => ['margot.css'],
            'preset' => ['blue.css'],
        ]);
        $themeManager->loadTemplates([]);

        $this->assertSame($expected, [
            $themeManager->getFavoriteTheme(),
            $themeManager->getFavoriteSquelette(),
            $themeManager->getFavoriteStyle(),
            $themeManager->getFavoritePreset(),
        ]);
    }

    public function testPageMetadataCannotPointTheThemeOutsideItsFolder()
    {
        $themeManager = $this->offlineThemeManager();
        $themeManager->loadTemplates([
            'theme' => 'margot',
            'style' => 'light.css',
            'squelette' => '../../../wakka.config.php',
            'bgimg' => '../../wakka.config.php',
        ]);

        $this->assertStringNotContainsString('..', $themeManager->getFavoriteSquelette());
        $this->assertStringNotContainsString('..', $themeManager->getFavoriteBackgroundImage());
    }

    public function testARequestedBackgroundImageStaysInItsFolder()
    {
        $this->wiki->request->query->replace(['theme' => 'margot', 'style' => 'light.css', 'squelette' => '1col.tpl.html', 'bgimg' => '../../wakka.config.php']);
        $themeManager = $this->offlineThemeManager();
        $themeManager->loadTemplates([]);
        $this->wiki->request->query->replace([]);

        $this->assertStringNotContainsString('..', $themeManager->getFavoriteBackgroundImage());
    }
}
