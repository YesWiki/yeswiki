<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;

require_once 'tests/YesWikiTestCase.php';

/** An action returns its content; it does not print it. */
class ActionsReturnTheirOutputTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function actionFiles(): array
    {
        $files = glob(dirname(__DIR__, 3) . '/src/*/Action/*.php') ?: [];
        sort($files);

        return $files;
    }

    public function testNoActionPrintsWithoutCapturingItsOwnOutput(): void
    {
        $leaking = [];
        foreach ($this->actionFiles() as $file) {
            $source = (string)file_get_contents($file);

            $prints = preg_match('/^\s*(echo|print)[\s(]/m', $source) === 1;
            if ($prints && !str_contains($source, 'ob_start')) {
                $leaking[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $leaking,
            'these actions print without capturing it, so their output escapes to wherever the buffer is'
        );
    }
}
