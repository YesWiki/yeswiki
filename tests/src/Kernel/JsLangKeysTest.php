<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'src/lang/javascript-keys-builder.php';

/**
 * `_t()` in the browser reads the javascript catalog and nothing else, so a key the scripts ask for but that catalog does not carry renders as its own name.
 */
class JsLangKeysTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scannedKeys(): array
    {
        return collectJavascriptTranslationKeys(dirname(__DIR__, 3) . '/javascripts');
    }

    /**
     * @return list<string>
     */
    private function checkedInKeys(): array
    {
        return array_values(array_map('strval', (array)require dirname(__DIR__, 3) . '/src/lang/javascript-keys.php'));
    }

    public function testTheCheckedInListMatchesWhatTheScriptsAskFor(): void
    {
        $scanned = $this->scannedKeys();
        $checkedIn = $this->checkedInKeys();

        $this->assertSame(
            $scanned,
            $checkedIn,
            'src/lang/javascript-keys.php is stale -- run `php src/build-js-lang-keys.php`'
        );
    }

    /**
     * A key no catalog defines still renders as its own name; the generated list is only useful if the keys on it resolve.
     */
    public function testEveryKeyResolvesInFrenchOrIsTheJavascriptCatalogsOwn(): void
    {
        $php = (array)require dirname(__DIR__, 3) . '/src/lang/yeswiki_fr.php';
        $js = (array)require dirname(__DIR__, 3) . '/src/lang/yeswikijs_fr.php';

        $unresolved = array_values(array_filter(
            $this->checkedInKeys(),
            fn (string $key) => !isset($php[$key]) && !isset($js[$key])
        ));

        $this->assertSame([], $unresolved, 'these keys would render as their own name in the browser');
    }
}
