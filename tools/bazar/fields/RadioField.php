<?php

namespace YesWiki\Bazar\Field;

class RadioField extends ListField
{
    public function __construct(array $values)
    {
        parent::__construct($values);

        $this->type = 'radio';
    }

    public function showInput($record)
    {
        $bulledaide = '';
        if ($this->helper != '') {
            $bulledaide.= ' <img class="tooltip_aide" title="' . htmlentities($this->helper[10], ENT_QUOTES, YW_CHARSET) . '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
        }
        $ob = '';
        $optionrequired = '';
        if ($this->required) {
            $ob .= ' <span class="symbole_obligatoire"></span>' . "\n";
            $optionrequired .= ' radio_required';
        }
        if (is_array($record) && $record[$this->recordId] != '') {
            $def = $record[$this->recordId];
        } else {
            $def = $this->default;
        }
        $radio_html = '<div class="control-group form-group">
                      <label class="control-label col-sm-3">
                      '. $ob . $this->label . (empty($bulledaide) ? '' : $bulledaide) . '</label>
                      <div class="controls col-sm-9">';
        $valliste = baz_valeurs_liste($this->id);
        if (is_array($valliste['label'])) {
            $radio_html.= '<div class="bazar-radio">';
            foreach ($valliste['label'] as $key => $label) {
                $radio_html.= '<div class="radio"><label for="' . $this->recordId.$key . '"><input type="radio" id="' . $this->recordId.$key . '" value="' . $key . '" name="' . $this->recordId.'"';
                if ($def != '' && strstr($key, $def)) {
                    $radio_html.= ' checked';
                }
                $radio_html.= ' /><span>' . $label . '</span></label></div>';
            }
            $radio_html.= '</div>';
        }
        $radio_html.= '
          </div>
        </div>';

        return $radio_html;
    }

    public function getHtml($record)
    {
        $html = '';
        if ($record && $record[$this->recordId] != '') {
            $valliste = baz_valeurs_liste($this->id);

            $tabresult = explode(',', $record[$this->recordId]);
            if (is_array($tabresult)) {
                $labels_result = '';
                foreach ($tabresult as $id) {
                    if (isset($valliste["label"][$id])) {
                        if ($labels_result == '') {
                            $labels_result = $valliste["label"][$id];
                        } else {
                            $labels_result.= ', ' . $valliste["label"][$id];
                        }
                    }
                }
            }
            {
                $html = '<div class="BAZ_rubrique" data-id="' . $this->recordId.'">' . "\n" . '<span class="BAZ_label">' . $this->label . '</span>' . "\n" . '<span class="BAZ_texte">' . "\n" . $labels_result . "\n" . '</span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
            }
        }

        return $html;
    }
}
