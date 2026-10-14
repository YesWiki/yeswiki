<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Asking to create an entry in a form that does not exist leaves nothing behind. */
class EntryControllerCreateTest extends YesWikiTestCase
{
    private const NOT_A_FORM = '<?php echo 1; ?>';

    protected function tearDown(): void
    {
        unset($_SESSION['current_form_id']);
        parent::tearDown();
    }

    public function testAnUnknownFormIdIsNeitherStoredNorEchoed(): void
    {
        unset($_SESSION['current_form_id']);

        $output = $this->getWiki()->services->get(EntryController::class)->create(self::NOT_A_FORM);

        $this->assertArrayNotHasKey('current_form_id', $_SESSION ?? []);
        $this->assertStringNotContainsString(self::NOT_A_FORM, $output);
    }
}
