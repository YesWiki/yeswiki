<?

use YesWiki\Core\YesWikiAction;

class BazarCartoAction extends YesWikiAction
{
    function formatArguments($arg)
    {
        // TODO refactor getAllParametersCarto
        return([]);
    }

    function run()
    {
        $this->arguments['template'] = 'map.tpl.html';
        $this->arguments['barregestion'] = false;

        return $this->callAction('bazarliste', $this->arguments);
    }
}
