<?php

namespace YesWiki\Aceditor\Service;

use Symfony\Component\Yaml\Yaml;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;

class ActionsBuilderService
{
    protected $data = null;
    protected $renderer;
    protected $wiki;

    public function __construct(TemplateEngine $renderer, Wiki $wiki)
    {
        $this->renderer = $renderer;
        $this->wiki = $wiki;
    }

    // ---------------------
    // Data for the template
    // ---------------------
    public function getData()
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $data = baz_forms_and_lists_ids();
        // Loads various Yaml file
        $docFiles = glob('docs/actions/*.yaml');
        $extensionDocFiles = glob('tools/*/actions/documentation.yaml');
        $customDocFiles = glob('custom/actions/documentation.yaml');
        $docFiles = array_merge($docFiles, $extensionDocFiles);
        $docFiles = array_merge($docFiles, $customDocFiles);
        $data['action_groups'] = [];
        foreach ($docFiles as $filePath) {
            $filename = pathinfo($filePath)['filename'];
            if ($filename == 'documentation') {
                // find key from filePath between tools and actions
                $matches = [];
                if (preg_match('/tools(?:\\/|\\\)([^\/]*)(?:\\/|\\\)actions(?:\\/|\\\)documentation.yaml/', $filePath, $matches)
                    ||
                    preg_match('/(custom)(?:\\/|\\\)actions(?:\\/|\\\)documentation.yaml/', $filePath, $matches)
                ) {
                    $key = $matches[1];
                } else {
                    $key = $filename;
                }
            } else {
                $key = $filename;
            }
            $data['action_groups'][$key] = Yaml::parseFile($filePath);
            // remove file for no admins if 'onlyForAdmins'
            if (isset($data['action_groups'][$key]['onlyForAdmins'])
                && $data['action_groups'][$key]['onlyForAdmins']
                && !$GLOBALS['wiki']->UserIsAdmin()) {
                unset($data['action_groups'][$key]);
            } else {
                // When order is not defined, put at the end
                if (empty($data['action_groups'][$key]['position'])) {
                    $data['action_groups'][$key]['position'] = 1000;
                }
            }
        }
        // Sort by position
        uasort($data['action_groups'], function ($a, $b) {
            return $a['position'] - $b['position'];
        });

        // Add custom bazar templates to the list of bazarliste component
        $bazarlisteTwigFiles = glob('custom/templates/bazar/*.twig');
        $bazarlisteTplFiles = glob('custom/templates/bazar/*.tpl.html');
        $bazarlisteCustomTemplates = array_merge($bazarlisteTplFiles, $bazarlisteTwigFiles);
        foreach ($bazarlisteCustomTemplates as $k => $v) {
            $bazarlisteCustomTemplates[$k] = str_replace('custom/templates/bazar/', '', $v);
        }
        // bazar templates starting with "fiche" are not list of entries
        $filtered_files = preg_grep('/^(?!fiche)/', $bazarlisteCustomTemplates);
        foreach ($filtered_files as $file) {
            $name = str_replace(['.tpl.html', '.twig'], '', $file);
            $translation = _t('AB_' . $name . '_label');
            // if no translation found, write "Template custom"
            if ($translation == 'AB_' . $name . '_label') {
                $translation = _t('ACTION_BUILDER_TEMPLATE_CUSTOM') . ' ' . $name;
            } else {
                $translation = '_t(AB_' . $name . '_label)';
            }
            if (empty($data['action_groups']['bazarliste']['actions'][$name])) {
                $data['action_groups']['bazarliste']['actions'][$name] = [
                    'label' => $translation,
                    'properties' => [
                        'template' => ['value' => $file],
                    ],
                ];
            }
        }

        // Handle translations
        array_walk_recursive($data['action_groups'], function (&$item, $key) {
            if (is_string($item) && preg_match("/_t\((.+)\)/", $item, $trans_key)) {
                $item = str_replace($trans_key[0], _t($trans_key[1]), $item);
            }
        });

        // add extra components
        $extraComponents = [];
        $files = [];
        foreach ($this->wiki->extensions as $pluginName => $pluginPath) {
            $files = glob("tools/$pluginName/javascripts/components/actions-builder/*.js");
            foreach ($files as $filePath) {
                $filename = pathinfo($filePath)['filename'];
                $extraComponents[$filename] = "../../../$pluginName/javascripts/components/actions-builder/$filename.js";
            }
        }
        $files = glob('custom/javascripts/components/actions-builder/*.js');
        foreach ($files as $filePath) {
            $filename = pathinfo($filePath)['filename'];
            $extraComponents[$filename] = "../../../../custom/javascripts/components/actions-builder/$filename.js";
        }
        if (!empty($extraComponents)) {
            $data['extraComponents'] = $extraComponents;
        }
        $this->data = $data;

        return $this->data;
    }
}
