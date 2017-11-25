<?php

function FindMailFromWikiPage($wikipage, $nbactionmail)
{
    preg_match_all('/{{(contact|abonnement|desabonnement).*mail=\"(.*)\".*}}/U', $wikipage, $matches);
    return $matches[2][$nbactionmail - 1];
}

function ValidateEmail($email)
{
    $regex = "/([a-z0-9_\.\-]+)" . # name
    "@" . # at
    "([a-z0-9\.\-]+){2,255}" . # domain & possibly subdomains
    "\." . # period
    "([a-z]+){2,10}/i"; # domain extension
    $eregi = preg_replace($regex, '', $email);
    return empty($eregi) ? true : false;
}

function check_parameters_mail($type, $mail_sender, $name_sender, $mail_receiver, $subject, $messagebody)
{
    $message['message'] = '';
    $message['class'] = 'danger';

    // Check sender's name
    if ($type == 'contact' && !$name_sender) {
        $message['message'] .= _t('CONTACT_ENTER_NAME') . '<br />';
    }

    // Check sender's email
    if (!$mail_sender) {
        $message['message'] .= _t('CONTACT_ENTER_SENDER_MAIL') . '<br />';
    }
    if ($mail_sender && !ValidateEmail($mail_sender)) {
        $message['message'] .= _t('CONTACT_SENDER_MAIL_INVALID') . '<br />';
    }

    // Check the receiver's email
    if (!$mail_receiver) {
        $message['message'] .= _t('CONTACT_ENTER_RECEIVER_MAIL') . '<br />';
    }
    if ($mail_receiver && !ValidateEmail($mail_receiver)) {
        $message['message'] .= _t('CONTACT_RECEIVER_MAIL_INVALID') . '<br />';
    }

    // Check message (length)
    if ($type == 'contact' && (!$messagebody || strlen($messagebody) < 10)) {
        $message['message'] .= _t('CONTACT_ENTER_MESSAGE') . '<br />';
    }

    // If no errors, we inform of success!
    if ($message['message'] == '') {
        $message['class'] = 'success';
    }

    return $message;
}

function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '')
{
    require_once('tools/contact/libs/vendor/PHPMailer/PHPMailerAutoload.php');
    //Create a new PHPMailer instance
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    if ($GLOBALS['wiki']->config['contact_mail_func'] == 'smtp') {
        //Tell PHPMailer to use SMTP
        $mail->isSMTP();

        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = isset($GLOBALS['wiki']->config['contact_debug']) ?
            $GLOBALS['wiki']->config['contact_debug'] : 0;
        //Ask for HTML-friendly debug output
        $mail->Debugoutput = 'html';

        //Set the hostname of the mail server
        $mail->Host = $GLOBALS['wiki']->config['contact_smtp_host'];

        //Set the SMTP secure protocol - likely to be ssl or tsl
        if (!empty($GLOBALS['wiki']->config['contact_smtp_secure'])) {
            $mail->SMTPSecure = $GLOBALS['wiki']->config['contact_smtp_secure'];
        }

        // allow self signed ssl
        if (!empty($GLOBALS['wiki']->config['contact_allow_self_signed'])) {
            $mail->SMTPOptions = array(
               'ssl' => array(
                   'verify_peer' => false,
                   'verify_peer_name' => false,
                   'allow_self_signed' => true
               )
            );
        }

        //Set the SMTP port number - likely to be 25, 465 or 587
        if (!empty($GLOBALS['wiki']->config['contact_smtp_port'])) {
            $mail->Port = $GLOBALS['wiki']->config['contact_smtp_port'];
        }

        if (!empty($GLOBALS['wiki']->config['contact_smtp_user']) and !empty($GLOBALS['wiki']->config['contact_smtp_pass'])) {
            //Whether to use SMTP authentication
            $mail->SMTPAuth = true;

            //Username to use for SMTP authentication
            $mail->Username = $GLOBALS['wiki']->config['contact_smtp_user'];

            //Password to use for SMTP authentication
            $mail->Password = $GLOBALS['wiki']->config['contact_smtp_pass'];
        }
    } elseif ($GLOBALS['wiki']->config['contact_mail_func'] == 'sendmail') {
        // Set PHPMailer to use the sendmail transport
        $mail->isSendmail();
    }

    //Set who the message is to be sent from
    if (empty($name_sender)) {
        $name_sender = $mail_sender;
    }
    $mail->setFrom($mail_sender, $name_sender);
    //Set an alternative reply-to address
    $mail->addReplyTo($mail_sender, $name_sender);
    //Set who the message is to be sent to
    $mail->addAddress($mail_receiver, $mail_receiver);

    //Set the subject line
    $mail->Subject = $subject;
    //convert HTML into a basic plain-text alternative body
    $mail->isHTML(true);
    if (empty($message_txt)) {
        $message_txt = 'empty email body';
    }
    if (empty($message_html)) {
        $message_html = nl2br($message_txt);
    }
    if (!empty($GLOBALS['wiki']->config['mail_template'])) {
        // affichage des resultats
        include_once 'includes/squelettephp.class.php';
        // On cherche un template personnalise dans le repertoire themes/tools
        $templatetoload = 'themes/tools/contact/templates/'.$GLOBALS['wiki']->config['mail_template'];
        if (!is_file($templatetoload)) {
            if (is_file('tools/contact/presentation/templates/'.$GLOBALS['wiki']->config['mail_template'])) {
                $templatetoload = 'tools/contact/presentation/templates/'.$GLOBALS['wiki']->config['mail_template'];
            } else {
                exit('<div class="alert alert-danger">'._t('CONTACT_TEMPLATE_NOT_FOUND').' : '.$GLOBALS['wiki']->config['mail_template'].'</div>');
            }
        }
        $squel = new SquelettePhp($templatetoload);
        $squel->set(array('message' => $message_html));
        $message_html = $squel->analyser();
    }
    $mail->Body = $message_html;
    //Replace the plain text body with one created manually
    $mail->AltBody = $message_txt;
    //Attach an image file
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
        return false;
    } else {
        return true;
    }
}
function getPageTitle($page)
{
    // on recupere les bf_titre ou les titres de niveau 1 et de niveau 2, on met la PageWiki sinon
    preg_match_all('/"bf_titre":"(.*)"/U', $page['body'], $titles);
    if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
        $title = _convert(preg_replace_callback('/\\\\u([a-f0-9]{4})/', 'utf8_special_decode', $titles[1][0]), 'UTF-8');
            //preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $titles[1][0]));
    } else {
        preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
        if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
            $title = $GLOBALS['wiki']->Format(_convert(trim($titles[1][0]), 'ISO-8859-15'));
        } else {
            preg_match_all('/={5}(.*)={5}/U', $page['body'], $titles);
            if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0] != '') {
                $title = $GLOBALS['wiki']->Format(_convert(trim($titles[1][0]), 'ISO-8859-15'));
            } else {
                $title = $page['tag'];
            }
        }
    }

    return strip_tags($title);
}

function filterMailGroups($var)
{
    // returns all string starting with "Mail"
    return(preg_match('/^Mail/', $var));
}

function filterDailyMailGroups($var)
{
    // returns all string ending with "Day"
    return(preg_match('/Day$/', $var));
}

function filterWeeklyMailGroups($var)
{
    // returns all string ending with "Week"
    return(preg_match('/Week$/', $var));
}

function filterMonthlyMailGroups($var)
{
    // returns all string ending with "Month"
    return(preg_match('/Month$/', $var));
}

function sendPeriodicalMailToGroup($period, $groups, $oldjson)
{
    $newjson = array();
    $nextday = strtotime("tomorrow");
    $nextmonday = strtotime('next monday');
    $nextmonth = strtotime('first day of next month midnight');
    $sub = '';
    if ($period == 'day') {
        $d = $nextday;
        $sub = ' rapport journalier';
    } elseif ($period == 'week') {
        $d = $nextmonday;
        $sub = ' rapport hebdomadaire';
    } elseif ($period == 'month') {
        $d = $nextmonth;
        $sub = ' rapport mensuel';
    }
    $today = time();
    foreach ($groups as $group) {
        if (!isset($oldjson[$group]) or $today > $d) {
            // get page name
            $page = preg_replace(array('/^Mail/', '/'.ucfirst($period).'$/'), '', $group);
            $page = $GLOBALS['wiki']->LoadPage($page);

            // send emails to all group members
            $groupmembers = $GLOBALS['wiki']->GetGroupACL($group);
            $groupmembers = explode("\n", $groupmembers);
            $groupmembers = array_map('trim', $groupmembers);
            $groupmembers = array_filter($groupmembers);
            $mailheader =   '['.str_replace(array('/wakka.php?wiki=', 'http://', 'https://'), '', $GLOBALS['wiki']->config['base_url']).']';
            $subject = $mailheader.' '.getPageTitle($page).' '.date("d-m-Y").$sub;
            $message_html = $GLOBALS['wiki']->Format('{{include page="'.$page['tag'].'"}}');
            $message_html = preg_replace(
                '/(\<\!\-\- mailperiod start \-\-\>.*\<\!\-\- mailperiod end \-\-\>)/Uims',
                '',
                $message_html
            );
            $message_txt = nl2br(strip_tags($message_html));
            foreach ($groupmembers as $member) {
                $user = $GLOBALS['wiki']->LoadUser($member);
                if (!empty($user['email'])) {
                    send_mail('no-reply@yeswiki.net', 'YesWiki', $user['email'], $subject, $message_txt, $message_html);
                }
            }
            $newjson[$group] = $today;
        } else {
            $newjson[$group] = $oldjson[$group];
        }
    }
    return $newjson;
}


function sendEmailsToSubscribers()
{
    $cache_file = 'files/mailcron.json';
    $cache_life = '1'; //caching time, in seconds, 10 minutes

    $filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
    $today = time();

    if (!$filemtime or ($today - $filemtime >= $cache_life)) {
        // on recupere les dates des derniers envois
        $cronfile = @file_get_contents($cache_file);
        $oldjson = json_decode($cronfile, true);

        // on cree un fichier vide pour eviter que les envois soient multiples
        $newjson = array();
        file_put_contents($cache_file, json_encode($newjson));

        // on recupere tous les groupes et on les trie par periode
        $groups = $GLOBALS['wiki']->GetGroupsList();
        $groups = array_filter($groups, "filterMailGroups");

        // envois journaliers
        $dayGroups = array_filter($groups, "filterDailyMailGroups");
        $newjson = $newjson + sendPeriodicalMailToGroup('day', $dayGroups, $oldjson);

        // envois hebdomadaires
        $weekGroups = array_filter($groups, "filterWeeklyMailGroups");
        $newjson = $newjson + sendPeriodicalMailToGroup('week', $weekGroups, $oldjson);

        // envois mensuels
        $monthGroups = array_filter($groups, "filterMonthlyMailGroups");
        $newjson = $newjson + sendPeriodicalMailToGroup('month', $monthGroups, $oldjson);
        file_put_contents($cache_file, json_encode($newjson));
    } else {
        readfile($cache_file);
        $cronfile = file_get_contents($cache_file);
        echo $cache_file;
    }
}
