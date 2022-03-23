<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Throwable;
use YesWiki\Bazar\Field\BazarField;

/**
 * @Field({"calc"})
 */

class CalcField extends BazarField
{
    protected const FIELD_DISPLAY_TEXT = 4;
    protected const FIELD_CALCFORMULA = 5;
    protected $calcFormula;
    protected $displayText;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->calcFormula = $values[self::FIELD_CALCFORMULA];
        $this->displayText = empty($values[self::FIELD_DISPLAY_TEXT]) ? "{value}" : $values[self::FIELD_DISPLAY_TEXT];
        $this->default = ""; // to prevent field 5 to change default value
        $this->maxChars = ""; // to prevent field 4 to change maxChars
    }
    protected function renderInput($entry)
    {
        // display nothing
        return null;
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        if (!in_array($value, [0,"0"], true) && empty($value)) {
            // 0 should be displayed but not false or null or ""
            return null;
        }
        return $this->render("@bazar/fields/text.twig", [
            'value' => str_replace('{value}', $value, $this->displayText)
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
            $formula = "";
            foreach ($matches[0] as $key => $value) {
                if (!empty($matches[1][$key])) {
                    // operators or parenthesis
                    $formula .= $matches[1][$key];
                } elseif (!empty($matches[2][$key])) {
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
            $regexpToCheckIfMathFormula = '/^(('.$number.'|'.$functions.'\s*\((?1)+\)|\((?1)+\))(?:'.$operators.'(?2))?)+$/';
            // Final regexp, heavily using recursive patterns
            if (preg_match($regexpToCheckIfMathFormula, $formula)) {
                $formula = preg_replace('!pi|π!', 'pi()', $formula);
                try {
                    eval("\$value = $formula;");
                    $value = $value ?? 0;
                } catch (Throwable $th) {
                    $value = 0;
                }
            } else {
                $value = "formula not correct !";
            }
        }
        return [$this->getPropertyName() => $value];
    }
    private function getEntryValue($entry, $name, $default = 0)
    {
        return (!isset($entry[$name]) || !is_scalar($entry[$name])) ? $default : floatval($entry[$name]);
    }

    private function testEntryValue($entry, $name, $value)
    {
        $result = false ;
        if (isset($entry[$name]) && is_scalar($entry[$name])) {
            $fieldValue = $entry[$name];
            if (empty($value) && !in_array($value, [0,"0"], true)) {
                $result = empty($fieldValue);
            } else {
                $result = ($fieldValue == $value);
            }
        }
        return $result ?  1 : 0;
    }

    public function getCalcFormula(): ?string
    {
        return $this->calcFormula;
    }

    public function getDisplayText(): ?string
    {
        return $this->displayText;
    }
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
