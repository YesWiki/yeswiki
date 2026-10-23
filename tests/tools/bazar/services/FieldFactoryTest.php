<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use YesWiki\Bazar\Service\FieldFactory;
use YesWiki\Fieldfactoryfixture\Field\AnnotationDeclaredField;
use YesWiki\Fieldfactoryfixture\Field\AttributeDeclaredField;
use YesWiki\Wiki;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';
require_once 'includes/YesWiki.php';
require_once __DIR__ . '/fieldfactory-fixture/fields/AttributeDeclaredField.php';
require_once __DIR__ . '/fieldfactory-fixture/fields/AnnotationDeclaredField.php';

class FieldFactoryTest extends TestCase
{
    private function factory(): FieldFactory
    {
        $wiki = (new \ReflectionClass(Wiki::class))->newInstanceWithoutConstructor();
        $wiki->extensions = ['fieldfactoryfixture' => __DIR__ . '/fieldfactory-fixture'];

        return new FieldFactory($wiki);
    }

    public function testFieldDeclaredWithAPhpAttributeIsAvailable()
    {
        $field = $this->factory()->create(['attributekeyword', 'bf_x']);

        $this->assertInstanceOf(AttributeDeclaredField::class, $field);
    }

    public function testFieldDeclaredWithADocblockAnnotationIsAvailable()
    {
        $field = $this->factory()->create(['annotationkeyword', 'bf_x']);

        $this->assertInstanceOf(AnnotationDeclaredField::class, $field);
    }

    public function testFieldClassNameIsAlsoAKeyword()
    {
        $this->assertInstanceOf(AttributeDeclaredField::class, $this->factory()->create(['attributedeclared']));
    }
}
