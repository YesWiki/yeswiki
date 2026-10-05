<?php

namespace YesWiki\Test\Render;

use YesWiki\Files\Service\Storage;
use YesWiki\Render\Service\DoryphoreLookRestorer;
use YesWiki\Render\Service\PresetService;
use YesWiki\Render\Service\PresetUpgrader;
use YesWiki\Render\Service\ThemeManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A Doryphore preset, larobustesse.org's R10, becomes a complete Preset that keeps its colours and its fonts. */
class PresetUpgraderTest extends YesWikiTestCase
{
    private const R10 = <<<'CSS'
:root {
  --primary-color: #54368d;
  --secondary-color-1: #1aab6d;
  --secondary-color-2: #000000;
  --neutral-color: #4e5056;
  --neutral-soft-color: #57575c;
  --neutral-light-color: #f2f2f2;
  --main-text-fontsize: 17px;
  --main-text-fontfamily: 'Roboto Condensed', sans-serif;
  --main-title-fontfamily: 'Asap', sans-serif;
}


@font-face {
  font-family: 'Roboto Condensed';
  font-style: normal;
  font-weight: 400;
  src: local(''),
        url('../../custom/fonts/roboto-condensed/roboto-condensed-normal-400-.woff2') format('woff2'),
        url('../../custom/fonts/roboto-condensed/roboto-condensed-normal-400-.woff') format('woff'),
        url('../../custom/fonts/roboto-condensed/roboto-condensed-normal-400-.ttf') format('truetype');
  unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
}
/* latin */
@font-face {
  font-family: 'Roboto Condensed';
  font-style: normal;
  font-weight: 400;
  src: local(''),
        url('../../custom/fonts/roboto-condensed/roboto-condensed-normal-400-latin.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* latin-ext */
@font-face {
  font-family: 'Roboto Condensed';
  font-style: normal;
  font-weight: 400;
  src: local(''),
        url('../../custom/fonts/roboto-condensed/roboto-condensed-normal-400-latin-ext.woff2') format('woff2');
  unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}

@font-face {
  font-family: 'Asap';
  font-style: normal;
  font-weight: 400;
  src: local(''),
        url('../../custom/fonts/asap/asap-normal-400-.woff2') format('woff2'),
        url('../../custom/fonts/asap/asap-normal-400-.woff') format('woff'),
        url('../../custom/fonts/asap/asap-normal-400-.ttf') format('truetype');
  unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
}
/* latin */
@font-face {
  font-family: 'Asap';
  font-style: normal;
  font-weight: 400;
  src: local(''),
        url('../../custom/fonts/asap/asap-normal-400-latin.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
/* latin-ext */
@font-face {
  font-family: 'Asap';
  font-style: normal;
  font-weight: 400;
  src: local(''),
        url('../../custom/fonts/asap/asap-normal-400-latin-ext.woff2') format('woff2');
  unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
CSS;

    private string $instance;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = sys_get_temp_dir() . '/preset-upgrader-' . bin2hex(random_bytes(4));
        mkdir($this->instance . '/custom', 0755, true);
        $this->fixture = $this->instance . '/doryphore-custom';
        $this->writeDoryphoreCustom($this->fixture);
    }

    /** A Doryphore custom/ as larobustesse.org had it: R10 and the font files it names, each a placeholder. */
    private function writeDoryphoreCustom(string $dir): void
    {
        mkdir($dir . '/css-presets', 0755, true);
        file_put_contents($dir . '/css-presets/R10.css', self::R10 . "\n");
        preg_match_all('~url\(\s*[\'"]?\.\./\.\./custom/([^\'")]+)~', self::R10, $fonts);
        foreach ($fonts[1] as $font) {
            if (!is_dir(dirname($dir . '/' . $font))) {
                mkdir(dirname($dir . '/' . $font), 0755, true);
            }
            file_put_contents($dir . '/' . $font, 'placeholder');
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->instance));
        parent::tearDown();
    }

    private function presets(): PresetService
    {
        return $this->getWiki()->services->get(PresetService::class);
    }

    /**
     * A ThemeManager whose font downloads are whatever the test says they are.
     *
     * @param array<string, string> $installed
     */
    private function fonts(bool $downloads, array $installed = []): RecordingFonts
    {
        return new RecordingFonts($downloads, $installed);
    }

    private function r10(): string
    {
        return self::R10 . "\n";
    }

    public function testR10IsAPresetToUpgrade(): void
    {
        $upgrader = new PresetUpgrader($this->presets(), $this->fonts(false));

        $this->assertTrue($upgrader->speaksDoryphore($this->r10()));
        $this->assertTrue($upgrader->needsUpgrade($this->r10()));
    }

    public function testR10BecomesACompletePresetWithItsColoursAndFonts(): void
    {
        $fonts = $this->fonts(false);
        $result = (new PresetUpgrader($this->presets(), $fonts))->upgrade($this->r10(), 'custom/css-presets/R10.css', false);
        $values = $this->presets()->valuesOf($result['css']);

        $this->assertSame([], $result['missing']);
        $this->assertSame([], $this->presets()->missingIn($values));
        $this->assertSame('#54368d', $values['light']['yw-primary']);
        $this->assertSame('#1aab6d', $values['light']['yw-secondary']);
        $this->assertSame('#000000', $values['light']['yw-tertiary']);
        $this->assertSame('#4e5056', $values['light']['yw-ink-on-light']);
        $this->assertSame('#f2f2f2', $values['light']['yw-surface-sunken']);
        $this->assertSame("'Roboto Condensed', sans-serif", $values['light']['yw-font-body']);
        $this->assertSame("'Asap', sans-serif", $values['light']['yw-font-heading']);
        $this->assertSame('17px', $values['light']['yw-font-size-base']);
        $this->assertSame('var(--yw-primary)', $values['light']['yw-heading-1']);
        $this->assertNotSame('var(--yw-primary)', $values['dark']['yw-heading-2'], 'a dark purple heading is unreadable on a dark surface');
        $this->assertNotSame('var(--yw-tertiary)', $values['dark']['yw-heading-4'], 'nor is a black one');
        $this->assertSame('var(--yw-secondary)', $values['dark']['yw-heading-3'], 'the green reads on dark and stays');
        $this->assertSame('var(--yw-secondary)', $values['light']['yw-heading-3']);
        $this->assertSame('#54368d', $values['dark']['yw-primary'], 'a brand colour carries into the dark scheme');
        $this->assertNotSame('#f2f2f2', $values['dark']['yw-surface-sunken'], 'a light grey is not a dark surface');
        $this->assertStringNotContainsString('--primary-color', $result['css']);
        $this->assertSame([], $fonts->asked, 'fonts the preset already serves are not fetched again');
        $this->assertFalse((new PresetUpgrader($this->presets(), $fonts))->needsUpgrade($result['css']));
    }

    public function testR10sFontsStillPointAtTheFilesDoryphoreDownloaded(): void
    {
        $result = (new PresetUpgrader($this->presets(), $this->fonts(false)))->upgrade($this->r10(), 'custom/css-presets/R10.css', false);

        $this->assertSame(6, substr_count($result['css'], '@font-face'));
        $this->assertStringContainsString("font-family: 'Roboto Condensed'", $result['css']);
        $this->assertStringContainsString("font-family: 'Asap'", $result['css']);
        preg_match_all('~url\(\s*[\'"]?([^\'")]+)~', $result['css'], $urls);
        $this->assertNotEmpty($urls[1]);
        foreach ($urls[1] as $url) {
            $this->assertStringStartsWith('../../custom/fonts/', $url);
            $this->assertFileExists($this->fixture . '/' . substr($url, strlen('../../custom/')), "$url resolves from custom/css-presets/");
        }
    }

    public function testAColouredNavbarIsPaintedInThePrimary(): void
    {
        $result = (new PresetUpgrader($this->presets(), $this->fonts(false)))->upgrade($this->r10(), 'custom/css-presets/R10.css', true);
        $values = $this->presets()->valuesOf($result['css']);

        $this->assertSame('#54368d', $values['light']['yw-navbar-bg']);
        $this->assertSame('#ffffff', $values['light']['yw-navbar-text']);
        $this->assertTrue(PresetUpgrader::hadColouredNavbar('margot.css'));
        $this->assertFalse(PresetUpgrader::hadColouredNavbar('light.css'));
    }

    public function testAGoogleFontsImportBecomesFontsTheWikiServes(): void
    {
        $css = "@import url('https://fonts.googleapis.com/css?family=Lato:400,700|Open+Sans');\n"
            . ":root {\n  --primary-color: #aa0000;\n  --main-text-fontfamily: 'Lato', sans-serif;\n  --main-title-fontfamily: 'Open Sans', sans-serif;\n}\n";
        $fonts = $this->fonts(true);
        $result = (new PresetUpgrader($this->presets(), $fonts))->upgrade($css, 'custom/themes/margot/presets/house.css', false);

        $this->assertSame(['Lato', 'Open Sans'], $fonts->asked);
        $this->assertSame(['Lato', 'Open Sans'], $result['localised']);
        $this->assertSame([], $result['imports']);
        $this->assertStringNotContainsString('@import', $result['css']);
        $this->assertStringContainsString("font-family: 'Lato'", $result['css']);
        $this->assertStringContainsString('url(../../../../custom/fonts/lato/lato-normal-400-latin.woff2)', $result['css'], 'four folders down, four levels up');
        $this->assertSame([], $result['missing']);
    }

    public function testAGoogleFontsImportThatCannotBeDownloadedIsKept(): void
    {
        $import = "@import url('https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap');";
        $css = $import . "\n:root {\n  --primary-color: #aa0000;\n  --main-text-fontfamily: 'Lato', sans-serif;\n}\n";
        $result = (new PresetUpgrader($this->presets(), $this->fonts(false)))->upgrade($css, 'custom/css-presets/house.css', false);

        $this->assertSame([$import], $result['imports']);
        $this->assertStringStartsWith($import, $result['css'], 'an @import only works before every other rule');
        $this->assertSame("'Lato', sans-serif", $this->presets()->valuesOf($result['css'])['light']['yw-font-body']);
        $this->assertSame([], $result['missing']);
    }

    public function testANamedWebfontWithNoRulesIsFetchedLikeSavingThePresetWould(): void
    {
        $css = ":root {\n  --primary-color: #aa0000;\n  --main-title-fontfamily: 'Lato', sans-serif;\n  --main-text-fontfamily: system-ui, sans-serif;\n}\n";
        $fonts = $this->fonts(true);
        $result = (new PresetUpgrader($this->presets(), $fonts))->upgrade($css, 'custom/css-presets/house.css', false);

        $this->assertSame(['Lato'], $fonts->asked);
        $this->assertStringContainsString('url(../../custom/fonts/lato/lato-normal-700-latin.woff2)', $result['css']);
    }

    public function testAnAdr0020PresetIsCompletedAndACompletePresetIsLeftAlone(): void
    {
        $upgrader = new PresetUpgrader($this->presets(), $this->fonts(false));
        $adr0020 = ":root {\n  --yw-primary: #123456;\n  --yw-text: #222222;\n  --yw-space-2: 0.5rem;\n  --yw-radius-md: 1rem;\n}\n";

        $this->assertTrue($upgrader->needsUpgrade($adr0020));
        $result = $upgrader->upgrade($adr0020, 'custom/css-presets/old.css', false);
        $values = $this->presets()->valuesOf($result['css']);
        $this->assertSame([], $result['missing']);
        $this->assertSame('#123456', $values['light']['yw-primary']);
        $this->assertSame('0.5rem', $values['light']['yw-space-sm-y']);
        $this->assertSame('2', $values['light']['yw-radius-scale']);

        $this->assertFalse($upgrader->needsUpgrade($result['css']));
        $this->assertFalse($upgrader->needsUpgrade((string)file_get_contents('themes/yeswiki/presets/default.css')));
    }

    public function testASetAsidePresetComesBackCompleteAndOnlyOnce(): void
    {
        $aside = $this->instance . '/private/doryphore/custom';
        mkdir(dirname($aside), 0755, true);
        exec('cp -r ' . escapeshellarg($this->fixture) . ' ' . escapeshellarg($aside));
        mkdir($aside . '/fields', 0755, true);
        file_put_contents($aside . '/fields/VideoField.php', '<?php');
        $storage = Storage::rootedAt($this->instance);
        $restorer = new DoryphoreLookRestorer($storage, new PresetUpgrader($this->presets(), $this->fonts(false)));

        $this->assertFalse($restorer->resolves('custom/R10.css'));
        $first = $restorer->restore($this->instance, false);

        $this->assertContains('custom/css-presets/R10.css', $first['restored']);
        $this->assertContains('custom/fonts/asap/asap-normal-400-latin.woff2', $first['restored']);
        $this->assertFileDoesNotExist($this->instance . '/custom/fields/VideoField.php');
        $this->assertSame(['custom/css-presets/R10.css'], array_column($first['upgraded'], 'path'));
        $this->assertTrue($restorer->resolves('custom/R10.css'));
        $restored = $storage->read('custom/css-presets/R10.css');
        $this->assertSame([], $this->presets()->missingIn($this->presets()->valuesOf($restored)));
        $this->assertStringContainsString('--yw-primary: #54368d;', $restored);
        $this->assertStringContainsString('R10.css', DoryphoreLookRestorer::summary($first['upgraded']));

        $this->assertSame(['restored' => [], 'upgraded' => []], $restorer->restore($this->instance, false));
        $this->assertSame($restored, $storage->read('custom/css-presets/R10.css'));
    }
}

/** Font downloads that succeed or fail on demand, remembering which families were asked for. */
class RecordingFonts extends ThemeManager
{
    /** @var list<string> */
    public array $asked = [];

    /** @param array<string, string> $installed */
    public function __construct(private bool $downloads, private array $installed)
    {
    }

    public function fontFaces(string $family): string
    {
        return $this->installed[$family] ?? '';
    }

    public function installFont(string $family): bool
    {
        $this->asked[] = $family;
        if (!$this->downloads) {
            return false;
        }
        $folder = strtolower(str_replace(' ', '-', $family));
        $this->installed[$family] = ThemeManager::fontFaceRule($family, 'normal', '400', '', '../../custom/fonts/' . $folder . '/' . $folder . '-normal-400-latin.woff2')
            . ThemeManager::fontFaceRule($family, 'normal', '700', '', '../../custom/fonts/' . $folder . '/' . $folder . '-normal-700-latin.woff2');

        return true;
    }
}
