<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\ConditionsCheckingField;
use YesWiki\Bazar\Field\LabelField;
use YesWiki\Bazar\Field\TextField;
use YesWiki\Bazar\Service\ConditionsChecker;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\TemplateEngine;

require_once 'includes/autoload.inc.php';
require_once 'includes/constants.php';
require_once 'includes/i18n.inc.php';

class ConditionsCheckerTest extends TestCase
{
    private $services;

    protected function setUp(): void
    {
        $templateEngine = $this->createStub(TemplateEngine::class);
        $templateEngine->method('render')->willReturn('<div data-conditionschecking="">');
        $purifier = $this->createStub(HtmlPurifierService::class);
        $purifier->method('cleanHTML')->willReturnArgument(0);

        $stubs = [
            TemplateEngine::class => $templateEngine,
            HtmlPurifierService::class => $purifier,
        ];
        $this->services = new class($stubs) implements ContainerInterface {
            private $stubs;

            public function __construct(array $stubs)
            {
                $this->stubs = $stubs;
            }

            public function get(string $id)
            {
                return $this->stubs[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return isset($this->stubs[$id]);
            }
        };
    }

    private function values(array $overrides): array
    {
        return array_replace(array_fill(0, 20, ''), $overrides);
    }

    private function text(string $name, bool $required = false): TextField
    {
        return new TextField($this->values([0 => 'texte', 1 => $name, 8 => $required ? 1 : 0]), $this->services);
    }

    private function condition(string $condition, string $option = ''): ConditionsCheckingField
    {
        return new ConditionsCheckingField(
            $this->values([0 => 'conditionschecking', 1 => $condition, 2 => $option, 4 => 'false']),
            $this->services
        );
    }

    private function label(string $formText): LabelField
    {
        return new LabelField($this->values([0 => 'labelhtml', 1 => $formText, 4 => 'false']), $this->services);
    }

    private function form(array $fields): array
    {
        return ['bn_id_nature' => '1', 'prepared' => $fields];
    }

    public function testAFieldOfAConditionThatDoesNotHoldIsHidden()
    {
        $form = $this->form([
            $this->text('bf_choix'),
            $this->condition('bf_choix==oui'),
            $this->text('bf_detail', true),
            $this->label('</div>'),
            $this->text('bf_apres', true),
        ]);

        $hidden = (new ConditionsChecker())->hiddenPropertyNames($form, ['bf_choix' => 'non']);

        $this->assertSame(['bf_detail'], $hidden);
    }

    public function testAFieldOfAConditionThatHoldsIsVisible()
    {
        $form = $this->form([
            $this->text('bf_choix'),
            $this->condition('bf_choix==oui'),
            $this->text('bf_detail', true),
            $this->label('</div>'),
        ]);

        $hidden = (new ConditionsChecker())->hiddenPropertyNames($form, ['bf_choix' => 'oui']);

        $this->assertSame([], $hidden);
    }

    public function testAClosingTagOfAnUnrelatedDivDoesNotEndTheCondition()
    {
        $form = $this->form([
            $this->text('bf_choix'),
            $this->condition('bf_choix==oui'),
            $this->label('<div class="panel">'),
            $this->text('bf_dedans', true),
            $this->label('</div><!-- fin du panel -->'),
            $this->text('bf_encore_dedans', true),
            $this->label('</div><!-- Fin de condition-->'),
            $this->text('bf_dehors', true),
        ]);

        $hidden = (new ConditionsChecker())->hiddenPropertyNames($form, ['bf_choix' => 'non']);

        $this->assertSame(['bf_dedans', 'bf_encore_dedans'], $hidden);
    }

    public function testHiddenValuesAreDroppedAndTheCascadeFollowed()
    {
        $form = $this->form([
            $this->text('bf_choix'),
            $this->condition('bf_choix==oui'),
            $this->text('bf_detail'),
            $this->label('</div>'),
            $this->condition('bf_detail==complet'),
            $this->text('bf_precision'),
            $this->label('</div>'),
        ]);

        $cleared = (new ConditionsChecker())->clearHiddenValues($form, [
            'bf_choix' => 'non',
            'bf_detail' => 'complet',
            'bf_precision' => 'reste de la saisie',
        ]);

        $this->assertSame(['bf_choix' => 'non'], $cleared);
    }

    public function testNocleanKeepsTheValueOfAHiddenField()
    {
        $form = $this->form([
            $this->text('bf_choix'),
            $this->condition('bf_choix==oui', 'noclean'),
            $this->text('bf_detail'),
            $this->label('</div>'),
        ]);

        $checker = new ConditionsChecker();
        $entry = ['bf_choix' => 'non', 'bf_detail' => 'a garder'];

        $this->assertSame($entry, $checker->clearHiddenValues($form, $entry));
        $this->assertSame(['bf_detail'], $checker->hiddenPropertyNames($form, $entry));
    }
}
