<?php

namespace YesWiki\Bazar;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\HtmlPurifierService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

/** Edits the translations of the entry shown on the current page. */
class TranslateHandler extends YesWikiHandler
{
    private const TRANSLATABLE_TYPES = ['texte', 'text', 'textelong', 'textarea', 'champs_mail', 'email', 'lien_internet', 'link', 'titre', 'label'];

    public function run(): string
    {
        $output = $this->wiki->Header();
        $tag = $this->wiki->GetPageTag();
        $entryManager = $this->getService(EntryManager::class);

        if (!$this->wiki->page || !$this->wiki->HasAccess('write')) {
            $output .= $this->alert(_t('NOT_ALLOWED'));
        } elseif (empty($this->wiki->config['supported_langs'])) {
            $output .= $this->alert(_t('TRANSLATE_MISSING_EXTRA_LANG'));
        } elseif (!$entryManager->isEntry($tag)) {
            $output .= $this->alert(_t('TRANSLATE_ACTION_ONLY_ALLOWED_FOR_ENTRY'));
        } elseif ($this->getRequest()->getMethod() === 'POST') {
            $output .= $this->save($tag);
        } else {
            $output .= $this->displayForm($tag);
        }

        return $output . $this->wiki->Footer();
    }

    private function alert(string $message): string
    {
        return $this->render('@templates/alert-message.twig', ['type' => 'danger', 'message' => $message]);
    }

    /** The stored body of entry $tag, untranslated. */
    private function storedEntry(string $tag): array
    {
        $page = $this->getService(PageManager::class)->getOne($tag, null, false, true);
        $entry = json_decode($page['body'] ?? '', true);

        return is_array($entry) ? $entry : [];
    }

    /** The fields of the entry's form that can be translated, as name, label and input type. */
    private function translatableFields(array $entry): array
    {
        $form = $this->getService(FormManager::class)->getOne($entry['id_typeannonce'] ?? null, 'all');
        $fields = [];
        foreach ($form['prepared'] ?? [] as $field) {
            $name = $field->getPropertyName();
            if (empty($name) || !in_array($field->getType(), self::TRANSLATABLE_TYPES, true)) {
                continue;
            }
            $fields[$name] = [
                'name' => $name,
                'label' => strip_tags((string)$field->getLabel()),
                'type' => in_array($field->getType(), ['textelong', 'textarea'], true) ? 'textarea' : 'text',
            ];
        }

        return $fields;
    }

    private function save(string $tag): string
    {
        $entry = $this->storedEntry($tag);
        $fields = $this->translatableFields($entry);
        $posted = json_decode((string)$this->getRequest()->request->get('__translation'), true);
        $purifier = $this->getService(HtmlPurifierService::class);
        $translations = [];
        foreach (is_array($posted) ? $posted : [] as $lang => $values) {
            if (!in_array($lang, $this->wiki->config['supported_langs'], true) || !is_array($values)) {
                continue;
            }
            foreach (array_intersect_key($values, $fields) as $name => $value) {
                if (is_scalar($value) && trim((string)$value) !== '') {
                    $translations[$lang][$name] = $purifier->cleanHTML((string)$value);
                }
            }
        }
        $entry['__translation'] = $translations;
        $entry['date_maj_fiche'] = date('Y-m-d H:i:s');
        $message = $this->getService(PageManager::class)->save($tag, json_encode($entry)) === 0 ? 'translate_ok' : 'translate_error';
        $this->wiki->redirect($this->wiki->Href(testUrlInIframe(), '', [
            'vue' => 'consulter',
            'action' => 'voir_fiche',
            'id_fiche' => $tag,
            'message' => $message,
        ], false));

        return '';
    }

    private function displayForm(string $tag): string
    {
        $entry = $this->storedEntry($tag);
        $baseLang = $entry['lang'] ?? $this->wiki->config['default_language'] ?? 'fr';
        $langs = array_values($this->wiki->config['supported_langs']);
        if (!is_array($entry['__translation'] ?? null)) {
            $entry['__translation'] = [];
        }
        foreach ($langs as $lang) {
            if ($lang !== $baseLang && !isset($entry['__translation'][$lang])) {
                $entry['__translation'][$lang] = new \stdClass();
            }
        }

        return $this->render('@bazar/entries/translate.twig', [
            'entry' => $entry,
            'langs' => $langs,
            'baseLang' => $baseLang,
            'fieldsNames' => array_values($this->translatableFields($entry)),
        ]);
    }
}
