<?php

use YesWiki\Core\YesWikiAction;

class whiteBoardAction extends YesWikiAction
{

    public function formatArguments($args)
    {
        return [
            'src' => $args['src'] ?? 'https://wbo.ophir.dev/boards/test1',
            'width' => $args['width'] ?? '100%',
            'height' => $args['height'] ?? '300px',
        ];
    }


    public function run()
    {
        $args = $this->formatArguments($this->arguments);

        // Construct the iframe
        $iframe = '<iframe src="' . htmlspecialchars($args['src']) . '" width="' . htmlspecialchars($args['width']) . '" height="' . htmlspecialchars($args['height']) . '"';

        // Close the tag
        $iframe .= '></iframe>';



        return $iframe;
    }
}
