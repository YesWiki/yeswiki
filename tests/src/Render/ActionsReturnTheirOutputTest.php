<?php

namespace YesWiki\Test\Render;

use PHPUnit\Framework\TestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * An action returns its content; it does not print it.
 *
 * A printing action lands wherever the output buffer happens to be rather than where it
 * was called from. `{{bazar}}` inside a page's `content` field printed its menu straight
 * out, so it appeared above the title of the page that called it -- the title being a
 * field the form declares first.
 *
 * Most actions print internally and capture themselves with ob_start(), which is fine:
 * ticket 06 converted them from procedural files and the buffer is how their bodies were
 * kept. What must not happen is printing with no buffer of one's own.
 */
class ActionsReturnTheirOutputTest extends TestCase
{
    /** @return list<string> */
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
            // `echo`/`print` at the start of a statement -- not `<?php echo` in a heredoc,
            // and not the word inside a longer identifier
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
