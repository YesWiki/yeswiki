<?php

use YesWiki\Core\YesWikiAction;

class PanelAction extends YesWikiAction
{
    public function run()
    {
        ob_start();
        // Titre du panel
        $title = $this->arguments['title'] ?? '';

        // classe css pour la couleur du panel ou autre
        $class = $this->arguments['class'] ?? 'panel-default';

        // collapsed: initial state is collapsed, and the panel is collapsible
        // collapsible: initial state is displayed, and the panel is collapsible
        // empty: initial state is displayed, and the panel is not collapsible
        $type = $this->arguments['type'] ?? '';
        $pagetag = $this->wiki->GetPageTag();

        if ($this->check_end_elem('panel')) {
            $headingID = uniqid('heading');
            $collapseID = uniqid('collapse');

            $collapsed = ($type == 'collapsed');
            $collapsible = ($type == 'collapsible') || $collapsed;

            if (isset($GLOBALS['check_' . $pagetag]['accordion_uniqueID'])) {
                $accordionID = $GLOBALS['check_' . $pagetag]['accordion_uniqueID'];
                if ($collapsible && !isset($GLOBALS['check_' . $pagetag]['accordion_collapsible'])) {
                    $collapsed = false;
                    $GLOBALS['check_' . $pagetag]['accordion_collapsible'] = true;
                }
                $collapsed = true;
                $collapsible = true;
            } else {
                $accordionID = '';
            }

            $headerTagName = $collapsible ? 'button' : 'div';
            $result = '<!-- start of panel -->'
                . "<div class=\"panel $class\">
              <$headerTagName class=\"panel-heading" . ($collapsed ? ' collapsed' : '') . '"';
            if ($collapsible) {
                $result .= " id=\"$headingID\"" . ' data-toggle="collapse"' . (!empty($accordionID) ? " data-parent=\"#$accordionID\"" : '')
                    . " href=\"#$collapseID\" aria-expanded=\"" . ($collapsed ? 'false' : 'true') . "\" aria-controls=\"$collapseID\"";
            }
            $result .= ">
                  <h4 class=\"panel-title\">
                   $title
                  </h4>
              </$headerTagName>

              <div id=\"$collapseID\"";
            if ($collapsible) {
                $result .= ' class="panel-collapse collapse ' . ($collapsed ? '' : 'in') . '" role="tabpanel"'
                    . " aria-labelledby=\"$headingID\"";
            }
            $result .= '>
                <div class="panel-body">';

            echo $result;
        } else {
            echo $this->generate_error_msg('panel');
        }
        $panel = ob_get_contents();
        ob_end_clean();

        return $panel;
    }

    public function end(): string
    {
        return "\t\t\n</div>\t\n</div>\n</div> <!-- end of panel -->\n";
    }
}
