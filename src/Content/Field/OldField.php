<?php

namespace YesWiki\Content\Field;

use Field;
use Psr\Container\ContainerInterface;
use YesWiki\Render\Service\TemplateEngine;

/** Ensure backwardompatibility with old format field. */
#[\Field(['old'])]
class OldField extends BazarField
{
    /** @var string|null the legacy `formulaire_*` function this field delegates to */
    protected $functionName;
    /** @var array<int|string, mixed> the field's form-definition line, as the legacy function expects it */
    protected $template;
    /** @var string|null the rendered message shown in place of the field when it cannot be delegated */
    protected $error;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        $this->functionName = $values['functionName'] ?? null;
        $twig = $services->get(TemplateEngine::class);
        if (empty($this->functionName)) {
            $this->error = $twig->render(
                '@core/alert-message.twig',
                [
                    'type' => 'danger',
                    'message' => "Error \$values['functionName'] is not defined while creating " . get_class($this) . ". \n<br>" .
                        "Do not use 'retrocomp' field in form builder.",
                ]
            );
        } elseif (!function_exists($this->functionName)) {
            $this->error = $twig->render(
                '@core/alert-message.twig',
                [
                    'type' => 'danger',
                    'message' => "Error function '" . $this->functionName . "' is not defined while creating " . get_class($this),
                ]
            );
        } else {
            $this->error = null;
        }
        unset($values['functionName']);
        $this->template = $values;
        $this->template[0] = $this->functionName;
        parent::__construct($values, $services);
        if (!empty($this->functionName)) {
            $this->type = $this->functionName;
        }
    }

    /** The legacy function this field delegates to, or null when the constructor found none to call. */
    private function delegate(): ?callable
    {
        if ($this->error !== null || $this->functionName === null || !is_callable($this->functionName)) {
            return null;
        }

        return $this->functionName;
    }

    protected function renderInput($entry)
    {
        $funcName = $this->delegate();
        if ($funcName === null) {
            return $this->error;
        }
        $templateForm = [];

        return $funcName($templateForm, $this->template, 'saisie', $entry);
    }

    public function formatValuesBeforeSave($entry)
    {
        $funcName = $this->delegate();
        if ($funcName === null) {
            return [$this->propertyName => null];
        }
        $templateForm = [];

        return $funcName($templateForm, $this->template, 'requete', $entry);
    }

    protected function renderStatic($entry)
    {
        $funcName = $this->delegate();
        if ($funcName === null) {
            return $this->error;
        }
        $templateForm = [];

        return $funcName($templateForm, $this->template, 'html', $entry);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            ['functionName' => $this->functionName]
        );
    }
}
