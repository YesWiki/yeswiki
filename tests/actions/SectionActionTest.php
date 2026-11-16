<?php

namespace YesWiki\Test\Core\Action;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The opening tag written by the section action. */
class SectionActionTest extends YesWikiTestCase
{
    public function testHeightAppliesWithoutABackgroundImage()
    {
        $this->assertStringContainsString('height:300px;', $this->openingTag(['height' => '300', 'bgcolor' => '#5c2c45']));
        $this->assertStringContainsString('height:300px;', $this->openingTag(['height' => '300']));
    }

    public function testNoHeightWithoutTheParameter()
    {
        $this->assertStringNotContainsString('height:', $this->openingTag(['bgcolor' => '#5c2c45']));
    }

    public function testABorderedSectionDeclaresOneBackgroundColor()
    {
        $tag = $this->openingTag(['bgcolor' => '#5c2c45', 'pattern' => 'border-solid']);

        $this->assertSame(1, substr_count($tag, 'background-color'));
        $this->assertStringContainsString('background-color: var(--main-bg-color)', $tag);
        $this->assertStringContainsString('border-color: #5c2c45', $tag);
    }

    public function testAPlainSectionKeepsItsBackgroundColor()
    {
        $this->assertStringContainsString('background-color:#5c2c45;', $this->openingTag(['bgcolor' => '#5c2c45']));
    }

    public function testARestrictedSectionSendsNothingToAnUnauthorisedVisitor()
    {
        $html = $this->getWiki()->Format('avant{{section visibility="@admins"}}secret{{section}}imbriqué{{end elem="section"}}{{end elem="section"}}après');

        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringNotContainsString('imbriqué', $html);
        $this->assertStringNotContainsString('<section', $html);
        $this->assertStringNotContainsString('</section>', $html);
        $this->assertStringContainsString('avant', $html);
        $this->assertStringContainsString('après', $html);
    }

    public function testARestrictedSectionInsideAVisibleOneLeavesTheOuterIntact()
    {
        $html = $this->getWiki()->Format('{{section}}public{{section visibility="@admins"}}secret{{end elem="section"}}suite{{end elem="section"}}');

        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringContainsString('public', $html);
        $this->assertStringContainsString('suite', $html);
        $this->assertSame(1, substr_count($html, '<section'));
        $this->assertSame(1, substr_count($html, '</section>'));
    }

    public function testAnAdminReadsARestrictedSection()
    {
        $wiki = $this->getWiki();
        $auth = $wiki->services->get(AuthController::class);
        if (empty($auth->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }

        try {
            $html = $wiki->Format('{{section visibility="@admins"}}secret{{end elem="section"}}');
        } finally {
            $auth->logout();
        }

        $this->assertStringContainsString('secret', $html);
        $this->assertSame(1, substr_count($html, '</section>'));
    }

    public function testAnOpenSectionIsShownToEveryone()
    {
        $this->assertStringContainsString('visible', $this->getWiki()->Format('{{section visibility="*"}}visible{{end elem="section"}}'));
    }

    private function openingTag(array $params): string
    {
        $html = $this->getWiki()->Format('{{section' . implode('', array_map(
            fn ($name, $value) => " {$name}=\"{$value}\"",
            array_keys($params),
            $params,
        )) . '}}contenu{{end elem="section"}}');

        $this->assertSame(1, preg_match('/<section\b[^>]*>/s', $html, $match), $html);

        return $match[0];
    }
}
