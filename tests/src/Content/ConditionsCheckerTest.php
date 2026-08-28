<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use YesWiki\Content\Field\ConditionsCheckingField;
use YesWiki\Content\Field\LabelField;
use YesWiki\Content\Field\TextField;
use YesWiki\Content\Service\ConditionsChecker;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Render\Service\TemplateEngine;

require_once 'tests/YesWikiTestCase.php';

class ConditionsCheckerTest extends TestCase
{
    private ContainerInterface $services;

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
            /** @var array<string, mixed> */
            private array $stubs;

            /** @param array<string, mixed> $stubs */
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

    /**
     * @param array<int, mixed> $overrides
     *
     * @return array<int, mixed>
     */
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

    /**
     * @param array<int, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function form(array $fields): array
    {
        return ['id' => '1', 'prepared' => $fields];
    }

    public function testAFieldOfAConditionThatDoesNotHoldIsHidden(): void
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

    public function testAFieldOfAConditionThatHoldsIsVisible(): void
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

    public function testAClosingTagOfAnUnrelatedDivDoesNotEndTheCondition(): void
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

    public function testHiddenValuesAreDroppedAndTheCascadeFollowed(): void
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

    public function testNocleanKeepsTheValueOfAHiddenField(): void
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
