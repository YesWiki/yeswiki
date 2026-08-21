<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Field\DateField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\MapField;
use YesWiki\Render\Service\TemplateEngine;

/**
 * The three questions a list template asks about an entry it is drawing.
 *
 * What data-attributes does its row carry, what does a `color=` or `icon=` parameter resolve to
 * for *this* entry, and what does the entry itself look like when rendered whole. Plus the
 * form-and-list names the actions builder offers, which is the same question asked of the wiki
 * rather than of an entry.
 *
 * Was `getHtmlDataAttributes()`, `getCustomValueForEntry()`, `renderEntryView()` and
 * `formAndListIds()` in `Content/bazar.functions.php` (ticket 50).
 */
class EntryDisplay
{
    public function __construct(private readonly ContainerInterface $services)
    {
    }

    /**
     * The `data-*` attributes an entry's row carries, so a filter can act on it client-side.
     *
     * @param array<string, mixed>|mixed $entry
     * @param array<mixed>|string        $formtab forms already loaded, to save a lookup per row
     */
    public function dataAttributes($entry, $formtab = ''): string
    {
        $htmldata = '';
        if (is_array($entry) && isset($entry['form_id'])) {
            $form = isset($formtab[$entry['form_id']]) ? $formtab[$entry['form_id']] : $this->services->get(FormManager::class)->getOne($entry['form_id']);
            foreach ($entry as $key => $value) {
                if (!empty($value)) {
                    if (
                        in_array(
                            $key,
                            [
                                // bf_latitude/bf_longitude used to be emitted here as
                                // data-attributes: they are not entry metadata, they were two
                                // fields of one particular French form, nothing reads the
                                // attributes, and geolocation has lived in a map field since
                                // 20260203091701_BazarChangeModelForGeolocation (ticket 11)
                                'form_id',
                                'owner',
                                'created_at',
                                'date_debut_validite_fiche',
                                'date_fin_validite_fiche',
                                'tag',
                                'status',
                                'updated_at',
                            ]
                        )
                    ) {
                        $htmldata .=
                            'data-' . htmlspecialchars($key) . '="' .
                            htmlspecialchars($value) . '" ';
                    } else {
                        if (isset($form['prepared'])) {
                            foreach ($form['prepared'] as $field) {
                                $propertyName = $field->getPropertyName();
                                if ($propertyName === $key) {
                                    if (
                                        $field instanceof MapField
                                        || $field instanceof EnumField
                                        || $field instanceof DateField
                                        || $field->getName() == 'scope'
                                    ) {
                                        $htmldata .=
                                            'data-' . htmlspecialchars($key) . '="' .
                                            htmlspecialchars(is_array($value) ? '[' . implode(',', $value) . ']' : $value) . '" ';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $htmldata;
    }

    /**
     * A display parameter (`color=`, `icon=`) resolved for one entry.
     *
     * When the parameter is a value map and a field is named, the entry's own value picks the
     * entry in that map; a checkbox value holding several picks the first that matches. Anything
     * else is the default.
     *
     * @param array<mixed>|string|null $parameter
     * @param array<string, mixed>     $entry
     */
    public function customValueFor($parameter, ?string $field, array $entry, mixed $default): mixed
    {
        if (is_array($parameter) && !empty($field)) {
            if (isset($entry[$field])) {
                // pour les checkbox, on teste les differentes valeurs et on renvoie la premiere qui va bien
                if (!isset($parameter[$entry[$field]]) && strpos($entry[$field], ',') !== false) {
                    $tab = explode(',', $entry[$field]);
                    foreach ($tab as $value) {
                        if (isset($parameter[$value])) {
                            // on retourne la premiere valeur trouvee
                            return $parameter[$value];
                        }
                    }

                    // on n a pas trouve de valeur, on renvoie la valeur par defaut
                    return $default;
                }

                return isset($parameter[$entry[$field]]) ?
                    $parameter[$entry[$field]] : $default;
            }

            // si la valeur n existe pas, on met l icone par defaut
            return $default;
        }

        // si le parametre n'est pas un tableau, il contient la valeur par defaut
        return $default;
    }

    /**
     * One entry rendered whole, or the error that stopped it.
     *
     * @param mixed $inApp   truthy when the management bar should be drawn
     * @param mixed $entryId the entry, by tag or already loaded
     */
    public function renderEntry($inApp, $entryId, mixed $form = ''): string
    {
        try {
            $output = $this->services->get(EntryController::class)->view($entryId, '', $inApp, null, $form);
        } catch (\Throwable $t) {
            return $this->services->get(TemplateEngine::class)
                ->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('PERFORMABLE_ERROR') . '<br/>' . $this->services->get(\YesWiki\Kernel\Service\ThrowableFormatter::class)->dump($t),
                ]);
        }

        return $output;
    }

    /**
     * Every form and every list this wiki has, by name, for a picker to offer.
     *
     * @return array{lists: array<mixed>, forms: array<mixed>}
     */
    public function formAndListNames(): array
    {
        $forms = [];
        $lists = $this->services->get(ListManager::class)->getAll();
        $lists = array_map(function ($list) {
            return $list['title'];
        }, $lists);
        $forms = $this->services->get(FormManager::class)->getAllLabels();

        return ['lists' => $lists, 'forms' => $forms];
    }
}
