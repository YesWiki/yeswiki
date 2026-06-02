<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Throwable;
use YesWiki\Bazar\Service\FormManager;

/**
 * @Field({"calc"})
 */
class CalcField extends BazarField
{
    protected const FIELD_DISPLAY_TEXT = 4;
    protected const FIELD_CALCFORMULA = 5;
    private const FIELD_CLASS_TYPE = 'CalcField';

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

    protected $calcFormula;
    protected $displayText;

    protected $formManager;

    private array $formulaTokens = [];
    private int $formulaPos = 0;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->calcFormula = $values[self::FIELD_CALCFORMULA];
        $this->displayText = empty($values[self::FIELD_DISPLAY_TEXT]) ? '{value}' : $values[self::FIELD_DISPLAY_TEXT];
        $this->default = ''; // to prevent field 5 to change default value
        $this->maxChars = ''; // to prevent field 4 to change maxChars
        $this->formManager = null;
    }

    protected function renderInput($entry)
    {
        // display nothing
        return '';
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!in_array($value, [0, '0'], true) && empty($value)) {
            // 0 should be displayed but not false or null or ""
            return '';
        }

        return $this->render('@bazar/fields/text.twig', [
            'value' => str_replace('{value}', strval($value), $this->displayText),
        ]);
    }

    // cette méthode est la plus importante car c'est celle où on définit le calcul à faire
    public function formatValuesBeforeSave($entry)
    {
        $number = '(?:\d+(?:[,.]\d+)?|pi|π)'; // What is a number
        $operators = '[+\/*\^%-]'; // Allowed math operators
        $parenthesis = '\)|\('; // Allowed math operators
        $fieldPropertyName = '[A-Za-z_0-9]+'; // Allowed math operators
        $functions = '(?:sinh?|cosh?|tanh?|abs|acosh?|asinh?|atanh?|exp|log10|deg2rad|rad2deg|sqrt|ceil|floor|round)'; // Allowed PHP functions
        $specialtest = '(?:test\(([A-Za-z_0-9]+),([A-Za-z_0-9,]*)\))';
        if (!preg_match_all("/($operators|$parenthesis)|($number)|($functions)|$specialtest|($fieldPropertyName)/", $this->calcFormula, $matches)) {
            $value = 0;
        } else {
            $formula = '';
            foreach ($matches[0] as $key => $value) {
                if (!empty($matches[1][$key])) {
                    // operators or parenthesis
                    $formula .= $matches[1][$key];
                } elseif (!empty($matches[2][$key]) || in_array($matches[2][$key], [0, '0'], true)) {
                    // number
                    $formula .= floatval($matches[2][$key]);
                } elseif (!empty($matches[3][$key])) {
                    // functions
                    $formula .= $matches[3][$key];
                } elseif (!empty($matches[4][$key])) {
                    // test
                    $formula .= $this->testEntryValue($entry, $matches[4][$key], $matches[5][$key] ?? null);
                } elseif (!empty($matches[6][$key])) {
                    // field property name
                    $formula .= $this->getEntryValue($entry, $matches[6][$key]);
                }
            }
            $formula = preg_replace('/\s+/', '', $formula);
            try {
                $value = $this->evaluateFormula($formula);
                if (!is_finite($value)) {
                    $value = 0;
                }
            } catch (Throwable $th) {
                $value = 0;
            }
        }
        if (empty($value)) {
            $value = 0;
        }

        return [$this->getPropertyName() => strval($value)];
    }

    private function getEntryValue($entry, $name, $default = 0)
    {
        $propertyName = $this->getPropertyNameIfDefined($entry, $name);

        return empty($propertyName) ? $default : floatval($entry[$propertyName]);
    }

    private function testEntryValue($entry, $name, $value)
    {
        $result = false;
        $propertyName = $this->getPropertyNameIfDefined($entry, $name);
        if (!empty($propertyName)) {
            $fieldValue = $entry[$propertyName];
            if (empty($value) && !in_array($value, [0, '0'], true)) {
                $result = empty($fieldValue);
            } else {
                $result = ($fieldValue == $value);
            }
        }

        return $result ? '1' : '0';
    }

    private function getPropertyNameIfDefined($entry, $name): ?string
    {
        if (!empty($entry['id_typeannonce'])) {
            if (is_null($this->formManager)) {
                // lazy loading because not possible at construct
                $this->formManager = $this->getService(FormManager::class);
            }
            $field = $this->formManager->findFieldFromNameOrPropertyName($name, $entry['id_typeannonce']);
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
                // scientific notation (e.g. 1.5E+20)
                if ($j < $len && in_array($formula[$j], ['e', 'E'], true)) {
                    $j++;
                    if ($j < $len && in_array($formula[$j], ['+', '-'], true)) {
                        $j++;
                    }
                    while ($j < $len && ctype_digit($formula[$j])) {
                        $j++;
                    }
                }
                $tokens[] = ['type' => 'number', 'value' => (float) str_replace(',', '.', substr($formula, $i, $j - $i))];
                $i = $j;
                continue;
            }
            if (in_array($c, ['+', '-', '*', '/', '^', '%', '(', ')'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $c];
                $i++;
                continue;
            }
            // UTF-8 π (U+03C0 = bytes 0xCF 0x80)
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

    private function peekToken(): ?array
    {
        return $this->formulaTokens[$this->formulaPos] ?? null;
    }

    private function consumeToken(): array
    {
        $t = $this->formulaTokens[$this->formulaPos] ?? null;
        if ($t === null) {
            throw new \RuntimeException('Unexpected end of formula');
        }
        $this->formulaPos++;
        return $t;
    }

    // expr = term (('+' | '-') term)*
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

    // term = power (('*' | '/' | '%') power)*
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

    // power = unary ('^' unary)* — right-associative
    private function parsePower(): float
    {
        $base = $this->parseUnary();
        if (($t = $this->peekToken()) !== null && $t['type'] === 'op' && $t['value'] === '^') {
            $this->consumeToken();
            return pow($base, $this->parsePower());
        }
        return $base;
    }

    // unary = '-' unary | primary
    private function parseUnary(): float
    {
        $t = $this->peekToken();
        if ($t !== null && $t['type'] === 'op' && $t['value'] === '-') {
            $this->consumeToken();
            return -$this->parseUnary();
        }
        return $this->parsePrimary();
    }

    // primary = number | 'pi' ['()'] | function '(' expr ')' | '(' expr ')'
    private function parsePrimary(): float
    {
        $t = $this->consumeToken();
        if ($t['type'] === 'number') {
            return (float) $t['value'];
        }
        if ($t['type'] === 'name') {
            if ($t['value'] === 'pi') {
                // accept both bare "pi" and legacy "pi()"
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
            return (float) $fn($arg);
        }
        if ($t['type'] === 'op' && $t['value'] === '(') {
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

    public static function mapToFieldArray($fieldProps): array
    {
        $new = parent::mapToFieldArray($fieldProps);
        $new[self::FIELD_CALCFORMULA] = $fieldProps['calcFormula'];
        $new[self::FIELD_DISPLAY_TEXT] = $fieldProps['displayText'];
        ksort($new);
        return $new;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
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
