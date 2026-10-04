<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Handler\RenderHandler;

/** The render preview strips HTML from the content but never from inside an {{action}} call. */
class RenderHandlerTest extends TestCase
{
    public function testAQueryComparisonInsideAnActionSurvives(): void
    {
        $this->assertSame(
            '{{bazarliste id="1" query="bf_a<3 AND bf_b>1"}}',
            RenderHandler::stripTagsOutsideActions('{{bazarliste id="1" query="bf_a<3 AND bf_b>1"}}')
        );
    }

    public function testHtmlOutsideActionsIsStripped(): void
    {
        $this->assertSame(
            'before alert(1) {{button text="<b>"}} after',
            RenderHandler::stripTagsOutsideActions('<p>before <script>alert(1)</script> {{button text="<b>"}} after</p>')
        );
    }

    public function testPlaceholderBytesInTheInputCannotPullInAnAction(): void
    {
        $this->assertSame(
            '0 {{a}}',
            RenderHandler::stripTagsOutsideActions("\x020\x03 {{a}}")
        );
    }
}
