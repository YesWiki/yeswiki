<?php

namespace YesWiki\Core\Controller;

use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiController;

class SearchAnchorCommons extends YesWikiController
{
    public const GET_PARAMETERS_NAME = "searchAnchor";
    public const ANCHOR_ID = "searchAnchor";

    private const ALL_CHARS = "[\w\W\p{Z}\\h\\v]";
    private const ALL_WORD_CHARS_AND_RETURN_EXCEPT_LT = "[\w\p{Z}\\h\\v^>]";

    protected $searchManager;

    public function __construct(
        SearchManager $searchManager
    ) {
        $this->searchManager = $searchManager;
    }

    public function run(string &$output)
    {
        if (!empty($_GET[self::GET_PARAMETERS_NAME])) {
            $searchText = filter_input(INPUT_GET, self::GET_PARAMETERS_NAME, FILTER_UNSAFE_RAW);
            $searchText = ($searchText === false) ? "" : htmlspecialchars($searchText);
            if (!empty($searchText)) {
                $formattedSearchText = $this->searchManager->prepareNeedleForRegexp(preg_quote($searchText));
                $allChars = self::ALL_CHARS;
                $ltNotFollowedByLt = ">".self::ALL_WORD_CHARS_AND_RETURN_EXCEPT_LT;
                if (preg_match("/(?:<div class=\"(?:yeswiki-page-widget page-widget )?page\"$allChars*)($ltNotFollowedByLt*$formattedSearchText)/iu", $output, $matches)) {
                    $textToReplace = $matches[1];
                    if (preg_match("/^($ltNotFollowedByLt*)($formattedSearchText)/iu", $textToReplace, $matches2)) {
                        $newOutput = str_replace(
                            $matches2[0],
                            "{$matches2[1]}<span id=\"".self::ANCHOR_ID."\"></span><b>{$matches2[2]}</b>",
                            $output
                        );
                        if (!empty($newOutput)) {
                            $output = $newOutput;
                        }
                    }
                }
            }
        }
    }
}
