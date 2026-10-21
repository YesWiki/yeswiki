<?php

namespace YesWiki\Test\Core;

use YesWiki\Core\Service\TemplateEngine;

require_once 'tests/YesWikiTestCase.php';

/**
 * Data handed to a performable or a PHP template must never replace the file being included.
 */
class RunFileInBufferTest extends YesWikiTestCase
{
    private const MARKER = 'RUNFILEINBUFFER_PLANTED_MARKER';

    public function testActionParameterCannotReplaceTheIncludedFile()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $planted = 'files/RunFileInBufferTest_planted.txt';
        file_put_contents($planted, '<?php echo "' . self::MARKER . '"; ?>');

        try {
            foreach (['___file', '__file', 'vars', 'this'] as $name) {
                $output = $wiki->Format('{{moteurrecherche ' . $name . '="' . $planted . '"}}');
                $this->assertStringNotContainsString(self::MARKER, $output, $name);
            }
        } finally {
            @unlink($planted);
        }
    }

    public function testTemplateDataCannotReplaceTheIncludedTemplate()
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $planted = 'files/RunFileInBufferTest_planted_template.txt';
        file_put_contents($planted, '<?php echo "' . self::MARKER . '"; ?>');

        try {
            $output = $wiki->services->get(TemplateEngine::class)->render(
                '@templates/moteurrecherche_basic.tpl.html',
                ['realTemplatePath' => $planted, 'templatePath' => $planted, 'class' => '', 'btnclass' => '', 'iconclass' => '', 'url' => '', 'phrase' => '']
            );
            $this->assertStringNotContainsString(self::MARKER, $output);
        } finally {
            @unlink($planted);
        }
    }
}
