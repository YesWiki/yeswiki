<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{ariane}}` -- converted from the procedural actions/ariane.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ArianeAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'ariane';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // WikiTrails code V2.1 based on copyrighted code by G. M. Bowen & Andrew Somerville, 2005.
        // Version 2 stores the crumbs in the $_SESSION variable. This makes this feature usable for all users.
        // Originally Prepared for a SSHRC funded research project extended by Roland Stens, 2006. Licensed under GPL.
        // Please keep attributions if distributing code.

        // Define the maximum breadcrumbs shown.

        // Todo : Trouver un moyen d'afficher un titre "propre" :
        /*
         - Table des matières ?
         - Parametre dans l'url ?
         - Jquery ?

        */

        if ($max = $this->wiki->GetParameter('nb')) {
            $max = (int)$max;
        } else {
            $max = 4;
        }

        $crumbs = [];

        // Just get the PageTage, no use in doing that more times than 1

        $wikireq = $_REQUEST['wiki'];
        // remove leading slash
        $wikireq = preg_replace("/^\//", '', $wikireq);
        // split into page/method, checking wiki name & method name (XSS proof)
        if (preg_match(
            '`^([A-Za-z0-9]+)/([A-Za-z0-9_-]*)$`',
            $wikireq,
            $matches
        )) {
            list($PageTag, $method) = $matches;
        } elseif (preg_match('`^[A-Za-z0-9]+$`', $wikireq)) {
            $PageTag = $wikireq;
        }

        // Find out if the breadcrumbs were already stored.
        if (!isset($_SESSION['breadcrumbs'])) {
            // Not stored yet, so set the current page name in the crumbs array.
            $crumbs[0] = $PageTag;
        } else {
            // The crumbs are already stored, so get them and put them in the crumbs array.
            $crumbs = $_SESSION['breadcrumbs'];

            if ($crumbs[count($crumbs) - 1] != $this->wiki->GetPageTag()) {
                // Test for the maximum amount of crumbs and if the last pagetag is not
                // the same as the last stored tag. If it is a duplicate we'll get rid of it later.
                if (count($crumbs) >= $max and $PageTag != $crumbs[$max - 1]) {
                    // Drop the first element in the crumbs array.
                    array_shift($crumbs);
                    // Add the new page name to the last position in the array.
                    $crumbs[$max - 1] = $PageTag;
                } else {
                    // Not at the maximum yet, then just add to page to the end of the array.
                    $crumbs[count($crumbs)] = $PageTag;
                }
            }
        }

        // Get rid of duplicates, but only if they are in subsequent array locations.
        $count = 1;
        $temp = [];
        $target = [];
        // Only do this, if you have 3 or more entries in the array
        if (count($crumbs) > 2) {
            while ($count <= (count($crumbs) - 1)) {
                $temp[$count - 1] = $crumbs[$count - 1];
                $temp[$count] = $crumbs[$count];
                $temp = array_unique($temp);
                $target = $target + $temp;
                $temp = '';
                $count++;
            }
            $crumbs = $target;
        } else {
            $crumbs = array_unique($crumbs);
        }

        // Save the breadcrumbs.
        $_SESSION['breadcrumbs'] = $crumbs;

        // Create the trail by walking through the array of page names.

        $page_trail = "<ol class=\"yw-breadcrumb\">\n"
            . '<li><a href="'
            . $this->wiki->href('', $this->wiki->config['root_page'])
            . '"><span class="fa fa-home"></span></a></li>'
            . "\n";

        foreach ($crumbs as $this_crumb) {
            if ($this->wiki->GetPageTag() == $this_crumb) {
                $page_trail .= '<li class="yw-breadcrumb__active">' . $this_crumb . '</li>' . "\n";
            } else {
                $page_trail .= '<li><a href="'
                    . $this->wiki->href('', $this_crumb)
                    . '">' . $this_crumb
                    . "</a></li>\n";
            }
        }
        $page_trail .= "</ol>\n";

        echo $page_trail;
    }
}
