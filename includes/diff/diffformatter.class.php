<?php

    /**
 * A class to format Diffs
 *
 * This class formats the diff in classic diff format.
 * It is intended that this class be customized via inheritance,
 * to obtain fancier outputs.
 */
class DiffFormatter
{

    /**
     * Format a diff.
     *
     * @param $diff object A Diff object.
     * @return string The formatted output.
     */
    function format($diff) {

    $xi = $yi = 1;
    $block = false;
    $context = array();

    $this->_start_diff();

    foreach ($diff->edits as $edit) {
        if ($edit->type == 'copy') {
        if (is_array($block)) {
            if (sizeof($edit->orig) <= 0) {
            $block[] = $edit;
            }
            else{
            $this->_block($x0, + $xi - $x0,
                      $y0, + $yi - $y0,
                      $block);
            $block = false;
            }
        }
        }
        else {
        if (! is_array($block)) {
            $x0 = $xi;
            $y0 = $yi;
            $block = array();
        }
        $block[] = $edit;
        }

        if ($edit->orig)
        $xi += sizeof($edit->orig);
        if ($edit->final)
        $yi += sizeof($edit->final);
    }

    if (is_array($block))
        $this->_block($x0, $xi - $x0,
              $y0, $yi - $y0,
              $block);

    return $this->_end_diff();
    }

    function _block($xbeg, $xlen, $ybeg, $ylen, &$edits) {
    $this->_start_block($this->_block_header($xbeg, $xlen, $ybeg, $ylen));
    }

    function _start_diff() {
    ob_start();
    }

    function _end_diff() {
    $val = ob_get_contents();
    ob_end_clean();
    return $val;
    }

    function _block_header($xbeg, $xlen, $ybeg, $ylen) {
    if ($xlen > 1)
        $xbeg .= "," . ($xbeg + $xlen - 1);
    if ($ylen > 1)
        $ybeg .= "," . ($ybeg + $ylen - 1);

    return $xbeg . ($xlen ? ($ylen ? 'c' : 'd') : 'a') . $ybeg;
    }
    
    function _start_block($header) {
    echo $header."\n";
    }

}

?>