<?php

use YesWiki\Core\YesWikiAction;

class IframeAction extends YesWikiAction
{

    public function formatArguments($args)
    {
        return [
            'src' => $args['src'] ?? 'https://yeswiki.net/?Accueil',
            'width' => $args['width'] ?? '100%',
            'height' => $args['height'] ?? '300px',
            'additionalAttributes' => $args['additionalAttributes'] ?? []
        ];
    }


    public function run()
    {
        $args = $this->formatArguments($this->arguments);

        // Construct the iframe
        $iframe = '<iframe src="' . htmlspecialchars($args['src']) . '" width="' . htmlspecialchars($args['width']) . '" height="' . htmlspecialchars($args['height']) . '"';

        // Add any additional attributes
        foreach ($args['additionalAttributes'] as $attr => $value) {
            $iframe .= ' ' . $attr . '="' . htmlspecialchars($value) . '"';
        }

        // Close the tag
        $iframe .= '></iframe>';



        return $iframe;
    }
}
