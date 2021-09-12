<?php

namespace YesWiki\Core\Service;

require_once 'includes/diff/side.class.php';
require_once 'includes/diff/diff.class.php';
require_once 'includes/diff/diffformatter.class.php';

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Wiki;

class DiffService
{
    public function __construct(Wiki $wiki, PageManager $pageManager, EntryManager $entryManager, 
                                EntryController $entryController)
    {
        $this->wiki = $wiki;
        $this->pageManager = $pageManager;
        $this->entryManager = $entryManager;
        $this->entryController = $entryController;
    }

    function getDiff($idA, $idB)
    {
        $pageA = $this->pageManager->getById($idA);
        $pageB = $this->pageManager->getById($idB);

        $tag = $pageA['tag'];
        $isEntry = !empty($tag) && $this->entryManager->isEntry($tag);
        if ($isEntry) {
            // extract text from bodies
            $textA = '""'.$this->entryController->view($tag, $pageA['time'], false).'""';
            $textB = '""'.$this->entryController->view($tag, $pageB['time'], false).'""';
        } else {
            // extract text from bodies
            $textA = _convert($pageA["body"], "ISO-8859-15");
            $textB = _convert($pageB["body"], "ISO-8859-15");
        }

        $sideA = new \Side($textA);
        $sideB = new \Side($textB);

        $bodyA = '';
        $sideA->split_file_into_words($bodyA);

        $bodyB = '';
        $sideB->split_file_into_words($bodyB);

        // diff on these two file
        $diff = new \Diff(explode("\n", $bodyA), explode("\n", $bodyB));

        // format output
        $fmt = new \DiffFormatter();

        $sideO = new \Side($fmt->format($diff));

        $resync_left = 0;
        $resync_right = 0;

        $count_total_right=$sideB->getposition() ;

        $sideA->init();
        $sideB->init();

        $output='';

        while (1) {
            $sideO->skip_line();
            if ($sideO->isend()) {
                break;
            }

            if ($sideO->decode_directive_line()) {
                $argument=$sideO->getargument();
                $letter=$sideO->getdirective();
                switch ($letter) {
                case 'a':
                  $resync_left = $argument[0];
                  $resync_right = $argument[2] - 1;
                  break;

                case 'd':
                  $resync_left = $argument[0] - 1;
                  $resync_right = $argument[2];
                  break;

                case 'c':
                  $resync_left = $argument[0] - 1;
                  $resync_right = $argument[2] - 1;
                  break;

                }

                $sideA->skip_until_ordinal($resync_left);
                $sideB->copy_until_ordinal($resync_right, $output);

                // deleted word

                if (($letter=='d') || ($letter=='c')) {
                    $sideA->copy_whitespace($output);
                    $output .= ($isEntry) ? '<span class="del">' : "@@";
                    $sideA->copy_word($output);
                    $sideA->copy_until_ordinal($argument[1], $output);
                    $output .=($isEntry) ? '</span>' :"@@";
                }

                // inserted word
                if ($letter == 'a' || $letter == 'c') {
                    $sideB->copy_whitespace($output);
                    $output .=($isEntry) ? '<span class="add">' :"££";
                    $sideB->copy_word($output);
                    $sideB->copy_until_ordinal($argument[3], $output);
                    $output .=($isEntry) ? '</span>' :"££";
                }
            }
        }

        $sideB->copy_until_ordinal($count_total_right, $output);
        $sideB->copy_whitespace($output);
        $out= $this->wiki->Format($output);
        return _convert($out, 'ISO-8859-15');
    }
}