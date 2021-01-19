<?

use YesWiki\Core\YesWikiAction;

class CalendrierAction extends YesWikiAction
{
    function formatArguments($arg)
    {
        return([
            'minical' => $arg['minical']
        ]);
    }

    function run()
    {
        $this->arguments['template'] = 'calendar.tpl.html';
        $this->arguments['barregestion'] = false;

        return $this->callAction('bazarliste', $this->arguments);
    }
}
