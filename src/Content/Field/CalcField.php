<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\FormManager;

/**
 * @phpstan-type CalcToken array{type: 'number', value: float}|array{type: 'op', value: string}|array{type: 'name', value: string}
 */
#[\Field(['calc'])]
class CalcField extends BazarField
{
    protected const FIELD_DISPLAY_TEXT = 4;
    protected const FIELD_CALCFORMULA = 5;

    private const ALLOWED_FUNCTIONS = [
        'sin' => 'sin', 'sinh' => 'sinh',
        'cos' => 'cos', 'cosh' => 'cosh',
        'tan' => 'tan', 'tanh' => 'tanh',
        'asin' => 'asin', 'asinh' => 'asinh',
        'acos' => 'acos', 'acosh' => 'acosh',
        'atan' => 'atan', 'atanh' => 'atanh',
        'abs' => 'abs', 'exp' => 'exp', 'log10' => 'log10',
        'deg2rad' => 'deg2rad', 'rad2deg' => 'rad2deg',
        'sqrt' => 'sqrt', 'ceil' => 'ceil', 'floor' => 'floor', 'round' => 'round',
    ];

    protected string $calcFormula;
    protected string $displayText;

    protected ?FormManager $formManager = null;

    /** @var list<CalcToken> */
    private array $formulaTokens = [];
    private int $formulaPos = 0;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->calcFormula = (string)($values[self::FIELD_CALCFORMULA] ?? '');
        $this->displayText = empty($values[self::FIELD_DISPLAY_TEXT]) ? '{value}' : (string)$values[self::FIELD_DISPLAY_TEXT];
        $this->default = '';
        $this->maxChars = '';
        $this->formManager = null;
    }

    protected function renderInput($entry)
    {
        return '';
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!in_array($value, [0, '0'], true) && empty($value)) {
            return '';
        }

        return $this->render('@core/fields/text.twig', [
            'value' => str_replace('{value}', strval($value), $this->displayText),
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $number = '(?:\d+(?:[,.]\d+)?|pi|π)';
        $operators = '[+\/*\^%-]';
        $parenthesis = '\)|\(';
        $fieldPropertyName = '[A-Za-z_0-9]+';
        $functions = '(?:sinh?|cosh?|tanh?|abs|acosh?|asinh?|atanh?|exp|log10|deg2rad|rad2deg|sqrt|ceil|floor|round)';
        $specialtest = '(?:test\(([A-Za-z_0-9]+),([A-Za-z_0-9,]*)\))';
        if (!preg_match_all("/($operators|$parenthesis)|($number)|($functions)|$specialtest|($fieldPropertyName)/", $this->calcFormula, $matches)) {
            $value = 0;
        } else {
            $formula = '';
            foreach ($matches[0] as $key => $value) {
                if (!empty($matches[1][$key])) {
                    $formula .= $matches[1][$key];
                } elseif (!empty($matches[2][$key]) || in_array($matches[2][$key], [0, '0'], true)) {
                    $formula .= floatval($matches[2][$key]);
                } elseif (!empty($matches[3][$key])) {
                    $formula .= $matches[3][$key];
                } elseif (!empty($matches[4][$key])) {
                    $formula .= $this->testEntryValue($entry, $matches[4][$key], $matches[5][$key] ?? null);
                } elseif (!empty($matches[6][$key])) {
                    $formula .= $this->getEntryValue($entry, $matches[6][$key]);
                }
            }
            $formula = preg_replace('/\s+/', '', $formula) ?? $formula;
            try {
                $value = $this->evaluateFormula($formula);
                if (!is_finite($value)) {
                    $value = 0;
                }
            } catch (\Throwable $th) {
                $value = 0;
            }
        }
        if (empty($value)) {
            $value = 0;
        }

        return [$this->getPropertyName() => strval($value)];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @param float|int                 $default
     *
     * @return float|int
     */
    private function getEntryValue($entry, string $name, $default = 0)
    {
        $propertyName = $this->getPropertyNameIfDefined($entry, $name);

        return empty($propertyName) ? $default : floatval($entry[$propertyName] ?? $default);
    }

    /**
     * @param array<string, mixed>|null $entry
     * @param string|null               $value the value to compare against, null when the formula gave none
     */
    private function testEntryValue($entry, string $name, $value): string
    {
        $result = false;
        $propertyName = $this->getPropertyNameIfDefined($entry, $name);
        if (!empty($propertyName)) {
            $fieldValue = $entry[$propertyName] ?? null;
            if (empty($value) && !in_array($value, [0, '0'], true)) {
                $result = empty($fieldValue);
            } else {
                $result = ($fieldValue == $value);
            }
        }

        return $result ? '1' : '0';
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function getPropertyNameIfDefined($entry, string $name): ?string
    {
        if (!empty($entry['form_id'])) {
            if (is_null($this->formManager)) {
                $formManager = $this->getService(FormManager::class);
                if (!$formManager instanceof FormManager) {
                    return null;
                }
                $this->formManager = $formManager;
            }
            $field = $this->formManager->findFieldFromNameOrPropertyName($name, (string)$entry['form_id']);
            if (!empty($field)) {
                $propertyName = $field->getPropertyName();
                if (!empty($propertyName) && isset($entry[$propertyName]) && is_scalar($entry[$propertyName])) {
                    return $propertyName;
                }
            }
        }

        return null;
    }

    private function evaluateFormula(string $formula): float
    {
        $this->formulaTokens = $this->tokenizeFormula($formula);
        $this->formulaPos = 0;
        $result = $this->parseAddSub();
        if ($this->formulaPos < count($this->formulaTokens)) {
            throw new \RuntimeException('Unexpected token at position ' . $this->formulaPos);
        }

        return $result;
    }

    /**
     * @return list<CalcToken>
     */
    private function tokenizeFormula(string $formula): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($formula);
        while ($i < $len) {
            $c = $formula[$i];
            if ($c === ' ' || $c === "\t") {
                $i++;
                continue;
            }
            if (ctype_digit($c) || $c === '.') {
                $j = $i;
                while ($j < $len && (ctype_digit($formula[$j]) || $formula[$j] === '.' || $formula[$j] === ',')) {
                    $j++;
                }

                if ($j < $len && in_array($formula[$j], ['e', 'E'], true)) {
                    $j++;
                    if ($j < $len && in_array($formula[$j], ['+', '-'], true)) {
                        $j++;
                    }
                    while ($j < $len && ctype_digit($formula[$j])) {
                        $j++;
                    }
                }
                $tokens[] = ['type' => 'number', 'value' => (float)str_replace(',', '.', substr($formula, $i, $j - $i))];
                $i = $j;
                continue;
            }
            if (in_array($c, ['+', '-', '*', '/', '^', '%', '(', ')'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $c];
                $i++;
                continue;
            }

            if (ord($c) === 0xCF && $i + 1 < $len && ord($formula[$i + 1]) === 0x80) {
                $tokens[] = ['type' => 'name', 'value' => 'pi'];
                $i += 2;
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $j = $i;
                while ($j < $len && (ctype_alnum($formula[$j]) || $formula[$j] === '_')) {
                    $j++;
                }
                $tokens[] = ['type' => 'name', 'value' => substr($formula, $i, $j - $i)];
                $i = $j;
                continue;
            }
            throw new \RuntimeException("Unexpected character '$c' in formula");
        }

        return $tokens;
    }

    /**
     * @return CalcToken|null
     */
    private function peekToken(): ?array
    {
        return $this->formulaTokens[$this->formulaPos] ?? null;
    }

    /**
     * @return CalcToken
     */
    private function consumeToken(): array
    {
        $t = $this->formulaTokens[$this->formulaPos] ?? null;
        if ($t === null) {
            throw new \RuntimeException('Unexpected end of formula');
        }
        $this->formulaPos++;

        return $t;
    }

    private function parseAddSub(): float
    {
        $left = $this->parseMulDivMod();
        while (($t = $this->peekToken()) !== null && $t['type'] === 'op' && in_array($t['value'], ['+', '-'], true)) {
            $this->consumeToken();
            $right = $this->parseMulDivMod();
            $left = $t['value'] === '+' ? $left + $right : $left - $right;
        }

        return $left;
    }

    private function parseMulDivMod(): float
    {
        $left = $this->parsePower();
        while (($t = $this->peekToken()) !== null && $t['type'] === 'op' && in_array($t['value'], ['*', '/', '%'], true)) {
            $this->consumeToken();
            $right = $this->parsePower();
            if ($t['value'] === '*') {
                $left = $left * $right;
            } elseif ($t['value'] === '/') {
                $left = $right != 0 ? $left / $right : 0.0;
            } else {
                $left = $right != 0 ? fmod($left, $right) : 0.0;
            }
        }

        return $left;
    }

    private function parsePower(): float
    {
        $base = $this->parseUnary();
        if (($t = $this->peekToken()) !== null && $t['type'] === 'op' && $t['value'] === '^') {
            $this->consumeToken();

            return pow($base, $this->parsePower());
        }

        return $base;
    }

    private function parseUnary(): float
    {
        $t = $this->peekToken();
        if ($t !== null && $t['type'] === 'op' && $t['value'] === '-') {
            $this->consumeToken();

            return -$this->parseUnary();
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): float
    {
        $t = $this->consumeToken();
        if ($t['type'] === 'number') {
            return (float)$t['value'];
        }
        if ($t['type'] === 'name') {
            if ($t['value'] === 'pi') {
                $next = $this->peekToken();
                if ($next !== null && $next['type'] === 'op' && $next['value'] === '(') {
                    $this->consumeToken();
                    $close = $this->consumeToken();
                    if ($close['type'] !== 'op' || $close['value'] !== ')') {
                        throw new \RuntimeException("Expected ')' after pi()");
                    }
                }

                return M_PI;
            }
            $fn = self::ALLOWED_FUNCTIONS[$t['value']] ?? null;
            if ($fn === null) {
                throw new \RuntimeException("Unknown function: {$t['value']}");
            }
            $open = $this->consumeToken();
            if ($open['type'] !== 'op' || $open['value'] !== '(') {
                throw new \RuntimeException("Expected '(' after {$t['value']}");
            }
            $arg = $this->parseAddSub();
            $close = $this->consumeToken();
            if ($close['type'] !== 'op' || $close['value'] !== ')') {
                throw new \RuntimeException("Expected ')' after function argument");
            }

            return (float)$fn($arg);
        }
        // only an 'op' token can reach here: 'number' and 'name' both returned above
        if ($t['value'] === '(') {
            $val = $this->parseAddSub();
            $close = $this->consumeToken();
            if ($close['type'] !== 'op' || $close['value'] !== ')') {
                throw new \RuntimeException("Expected ')'");
            }

            return $val;
        }
        throw new \RuntimeException("Unexpected token: {$t['type']} '{$t['value']}'");
    }

    public function getCalcFormula(): ?string
    {
        return $this->calcFormula;
    }

    public function getDisplayText(): ?string
    {
        return $this->displayText;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'calcFormula' => $this->getCalcFormula(),
                'displayText' => $this->getDisplayText(),
            ]
        );
    }
}
