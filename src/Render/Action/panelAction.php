<?php

namespace YesWiki\Render\Action;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class PanelAction extends YesWikiAction implements RegisteredAction
{
    /** `{{panel}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'panel';
    }

    public function run()
    {
       ob_start();
       // Titre du panel
       $title = $this->arguments['title'] ?? '';

       // classe css pour la couleur du panel ou autre
       $class = $this->arguments['class'] ?? '';

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
                $collapsible = true;
                if (!isset($GLOBALS['check_' . $pagetag]['accordion_collapsible'])) {
                    // first panel in the accordion starts open, the rest start collapsed
                    $collapsed = false;
                    $GLOBALS['check_' . $pagetag]['accordion_collapsible'] = true;
                } else {
                    $collapsed = true;
                }
            } else {
                $accordionID = '';
            }

            $headerTagName = $collapsible ? 'button' : 'div';
            $result = '<!-- start of panel -->'
                . "<div class=\"yw-panel $class\">
              <$headerTagName class=\"yw-panel__heading" . ($collapsible ? ' yw-collapse-toggle' : '') . '"';
            if ($collapsible) {
                $result .= " id=\"$headingID\"" . " data-yw-collapse-toggle=\"#$collapseID\"" . (!empty($accordionID) ? " data-yw-accordion=\"#$accordionID\"" : '')
                    . " aria-expanded=\"" . ($collapsed ? 'false' : 'true') . "\" aria-controls=\"$collapseID\"";
            }
            $result .= ">
                  <h4 class=\"yw-panel__title\">
                   $title
                  </h4>
              </$headerTagName>

              <div id=\"$collapseID\"";
            if ($collapsible) {
                $result .= ' class="yw-collapse' . ($collapsed ? '' : ' yw-collapse--open') . '" role="region"'
                    . " aria-labelledby=\"$headingID\"";
            }
            $result .= '>
                <div class="yw-panel__body">';

            echo $result;
        } else {
            echo $this->generate_error_msg('panel');
        }
       $panel = ob_get_contents();
       ob_end_clean();
       return $panel;
    }


   public function end(): string {
       return "\t\t\n</div>\t\n</div>\n</div> <!-- end of panel -->\n";
   }
}
