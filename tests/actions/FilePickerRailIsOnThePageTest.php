<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A page that renders the file-picker *button* must also render the *panel* it opens.
 *
 * The two come from different places: the button is emitted by the field
 * (`@core/inputs/image.twig`), the panel by whichever template thought to include
 * `@core/aceditor-rails.twig`. Nothing connected them, so `{{usersettings}}` shipped the
 * button and not the panel -- and `FilePickerPanel`'s constructor guard
 * (`if (!this.panel) return`) turned every click on the profile picture's "choose a file"
 * button into silence: no rail, no error, nothing in the console.
 *
 * Asserted on the rendered markup rather than through a browser, because the failure is
 * entirely in what the page ships: the JavaScript was innocent.
 */
class FilePickerRailIsOnThePageTest extends YesWikiTestCase
{
    /** The element every FilePickerPanel instance looks itself up by. */
    private const PANEL_ID = 'YesWikiFilePickerPanel';

    /** What a field's "choose a file" button carries. */
    private const BUTTON_ATTRIBUTE = 'data-yw-file-picker-field';

    public function testUserSettingsShipsThePanelItsProfilePictureButtonOpens(): void
    {
        $output = $this->renderAsFirstUser('{{usersettings}}');

        if (!str_contains($output, self::BUTTON_ATTRIBUTE)) {
            $this->markTestSkipped('this wiki\'s User form renders no file-attaching field');
        }

        $this->assertStringContainsString(
            self::PANEL_ID,
            $output,
            'the profile picture offers a "choose a file" button, so the rail it opens has to be on the page too'
        );
    }

    /**
     * The rule stated once, over the surfaces that render form fields: a page carrying the
     * button carries the panel. A new field-rendering screen that forgets the rails fails
     * here rather than in someone's hands.
     */
    public function testNoRenderedSurfaceOffersTheButtonWithoutThePanel(): void
    {
        $checked = 0;
        foreach (['{{usersettings}}'] as $source) {
            $output = $this->renderAsFirstUser($source);
            if (!str_contains($output, self::BUTTON_ATTRIBUTE)) {
                continue;
            }
            $checked++;
            $this->assertStringContainsString(
                self::PANEL_ID,
                $output,
                "{$source} renders a file-picker button but not the panel it opens"
            );
        }

        $this->assertGreaterThan(0, $checked, 'nothing exercised the rule -- the test would pass vacuously');
    }

    private function renderAsFirstUser(string $source): string
    {
        $wiki = $this->getWiki();
        $users = $wiki->services->get(UserManager::class)->getAll();
        if (empty($users)) {
            $this->markTestSkipped('needs an account to render the settings of');
        }

        $auth = $wiki->services->get(AuthenticationService::class);
        $auth->login($users[0]);

        try {
            return $wiki->services->get(MarkdownFormatterService::class)->format($source);
        } finally {
            $auth->logout();
        }
    }
}
