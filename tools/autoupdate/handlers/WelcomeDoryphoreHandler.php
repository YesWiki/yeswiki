<?php

use YesWiki\Core\YesWikiHandler;
use YesWiki\Core\Service\Performer;

/*
 * This handler is called from /tools/autoupdate/Viewupdate.php only when upgrading from cercopitheque to doryphore.
 * whereas it is not used.
 * This handlers will not be useful for ectoplasme.
 */

class WelcomeDoryphoreHandler extends YesWikiHandler
{
    public function run()
    {
        // search data in session message
        $message = $this->wiki->GetMessage();
        
        $error = empty($message);

        // check integrity of data
        if (!$error) {
            $data = json_decode($message, true);
            $error = !is_array($data) && !isset($data['messages']) && !isset($data['baseURL']);
        }

        // on error call handler 'show'
        if ($error) {
            if (!empty($message)) {
                // save received message as if it was not extracted
                $this->wiki->SetMessage($message) ;
            }
            return $this->getService(Performer::class)->run('show', 'handler', []);
        }

        // finished rendering of autoupdate
        $output = '<h1>Welcome on Doryphore</h1>'."\n";
        // $output .= $message;
        $output .= $this->wiki->render("@autoupdate/update.twig", [
            'messages' => $data['messages'],
            'baseUrl' => $data['baseURL'],
        ]);
        $output = $this->wiki->Header() . $output ;
        $output .= $this->wiki->Footer();
        return $output;
    }
}
