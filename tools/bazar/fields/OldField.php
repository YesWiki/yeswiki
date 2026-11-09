<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\TemplateEngine;

/**
 * Ensure backwardompatibility with old format field.
 *
 * @Field({"old"})
 */
class OldField extends BazarField
{
    protected $functionName;
    protected $template;
    protected $error;

    public function __construct(array $values, ContainerInterface $services)
    {
        $this->functionName = $values['functionName'] ?? null;
        $twig = $services->get(TemplateEngine::class);
        if (empty($this->functionName)) {
            $this->error = $twig->render(
                '@templates/alert-message.twig',
                [
                    'type' => 'danger',
                    'message' => "Error \$values['functionName'] is not defined while creating " . get_class($this) . ". \n<br>" .
                        "Do not use 'retrocomp' field in form builder.",
                ]
            );
        } elseif (!function_exists($this->functionName)) {
            $this->error = $twig->render(
                '@templates/alert-message.twig',
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
        $this->type = $this->functionName;
    }

    protected function renderInput($entry)
    {
        $funcName = $this->functionName;
        $templateForm = [];

        return $this->error ?? $funcName($templateForm, $this->template, 'saisie', $entry);
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
        $funcName = $this->functionName;
        $templateForm = [];

        return ($this->error) ? [$this->propertyName => null]
            : $funcName($templateForm, $this->template, 'requete', $entry);
    }

    protected function renderStatic($entry)
    {
        $funcName = $this->functionName;
        $templateForm = [];

        return $this->error ?? $funcName($templateForm, $this->template, 'html', $entry);
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            ['functionName' => $this->functionName]
        );
    }
}
