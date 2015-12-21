<?php
/*
  lostpassword.php
  2013 David Delon

  License GPL.

 */



if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (!defined('PW_SALT')) {
    define('PW_SALT', 'FBcA');
}

if (!function_exists('checkUNEmail')) {
    function checkUNEmail($uname, $email)
    {
        global $wiki;
        $error = array('status' => false, 'userID' => 0);
        if (isset($email) && trim($email) != '') {
            //email was entered
            $existingEmail = $wiki->LoadSingle("select * from " . $wiki->config["table_prefix"]
                . "users where email = '" . mysqli_real_escape_string($wiki->dblink, $email) . "' limit 1");
            if ($existingEmail) {
                return array('status' => true, 'userID' => $existingEmail['name']);
            } else {
                return $error;
            }
        } elseif (isset($uname) && trim($uname) != '') {
            //username was entered
            if ($existingUser = $wiki->LoadUser($uname)) {
                return array('status' => true, 'userID' => $existingUser['name']);
            } else {
                return $error;
            }
        } else {
            //nothing was entered;
            return $error;
        }
    }
}

if (!function_exists('sendPasswordEmail')) {
function sendPasswordEmail($userID) {
    global $wiki;
    if ($existingUser = $wiki->LoadUser($userID)) {
        $expFormat = mktime(date("H"), date("i"), date("s"), date("m"), date("d") + 3, date("Y"));
        $expDate = date("Y-m-d H:i:s", $expFormat);
        $key = md5($userID . '_' . $existingUser['email'] . rand(0, 10000) . $expDate . PW_SALT);
        $res = $wiki->InsertTriple($userID, 'http://outils-reseaux.org/_vocabulary/key', $key);
            $passwordLink = $wiki->Href()."&a=recover&email=" . $key . "&u=" . urlencode(base64_encode($userID));
            $message = "Cher $userID,\r\n";
            $message .= "Cliquez sur le lien suivant pour reinitialiser votre mot de passe:\r\n";
            $message .= "-----------------------\r\n";
            $message .= "$passwordLink\r\n";
            $message .= "-----------------------\r\n";
            $message .= "Merci\r\n";
            $domain = $wiki->Href();
            $domain = parse_url($domain);
            $domain = $domain["host"];
            $subject = "Mot de passe perdu pour ".$domain;
            send_mail("noreply@".$domain, "WikiAdmin", $existingUser['email'], $subject, $message); 
    }
}
}

if (!function_exists('checkEmailKey')) {
function checkEmailKey($key, $userID) {
    global $wiki;
    // Pas de detournement possible car utilisation de _vocabulary/key ....
    $res=$wiki->TripleExists($userID, 'http://outils-reseaux.org/_vocabulary/key',$key);
    if ($res > 0) {
        return array('status' => true, 'userID' => $userID);
    }
    else {
        return false;
    }
}
}

if (!function_exists('updateUserPassword')) {
function updateUserPassword($userID, $password, $key) {
    global $wiki;
    if (checkEmailKey($key, $userID) === false)
        return false;

         $wiki->Query("update ".$wiki->config["table_prefix"]."users ".  "set ".  "password = '".MD5($password)."' ".  "where name = '".$userID."' limit 1");

     $res=$wiki->DeleteTriple($userID, 'http://outils-reseaux.org/_vocabulary/key',$key);
     return true;


}
}

if (!function_exists('send_mail')) {
function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '') {
    require_once('tools/login/libs/Mail.php');
    require_once('tools/login/libs/Mail/mime.php');
    $headers['From']    = $mail_sender;
    $headers['To']      = $mail_sender;
    $headers['Subject'] = $subject;
    $headers["Return-path"] = $mail_sender; 
    
    if ($message_html == '') {
        $message_html == $message_txt;
    }
    $mime = new Mail_mime("\n");

    $mimeparams = array();
    $mimeparams['text_encoding']="7bit";
    $mimeparams['text_charset']="UTF-8";
    $mimeparams['html_charset']="UTF-8"; 
    $mimeparams['head_charset']="UTF-8";  

    $mime->setTXTBody(utf8_encode($message_txt));
    $mime->setHTMLBody(utf8_encode($message_html));
    $message = $mime->get($mimeparams);
    $headers = $mime->headers($headers);
    
    // Creer un objet mail en utilisant la methode Mail::factory.
    $object_mail = & Mail::factory(CONTACT_MAIL_FACTORY);

    return $object_mail->send($mail_receiver, $headers, $message);
}
}


$error = false;
$step = 'emailForm'; // Formulaire par defaut

if (isset($_POST['subStep']) && !isset($_GET['a']) ) { // Sous-etape
    switch ($_POST['subStep']) {
        case 1:
            //we just submitted an email or username for verification
            $result = checkUNEmail($_POST['uname'], $_POST['email']);
            if ($result['status'] == false) {
                $error = true;
                $step = 'userNotFound';
            } else {
                $error = false;
                $step = 'successPage';
                $securityUser = $result['userID'];
                    sendPasswordEmail($securityUser);
            }
            break;
        case 2:
            //we are submitting a new password (only for encrypted)
            if ($_POST['userID'] == '' || $_POST['key'] == '')
                header("location: login.php");
            if (strcmp($_POST['pw0'], $_POST['pw1']) != 0 || trim($_POST['pw0']) == '') {
                $error = true;
                $step = 'recoverForm';
            } else {
                $error = false;
                $step = 'recoverSuccess';
                if (updateUserPassword($_POST['userID'], $_POST['pw0'], $_POST['key'])) { // il y encore un controle ici
                    $this->SetUser($this->LoadUser($_POST['userID'])); // on s'identitifie
                }
            }
            break;
    }
} elseif (isset($_GET['a']) && $_GET['a'] == 'recover' && $_GET['email'] != "") {
    $step = 'invalidKey';
    $result = checkEmailKey($_GET['email'], urldecode(base64_decode($_GET['u'])));
    if ($result == false) {
        $error = true;
        $step = 'invalidKey';
    } elseif ($result['status'] == true) {
        $error = false;
        $step = 'recoverForm';
        $securityUser = $result['userID'];
    }
}

switch ($step) {
    case 'userNotFound':
        echo $this->Format("Utilisateur ou email inconnus ");
        break;
    case 'emailForm':
        echo $this->FormOpen();
        echo $this->Format("==Recuperation du mot de passe==");
        if ($error == true) {
            echo $this->Format("Veuillez saisir un utilisateur ou un email pour continuer");
        }
        ?>
        <div style="display:none">
        <label for="uname">Utilisateur Wiki</label>
        <div class="field"><input type="text" name="uname" id="uname" value="" maxlength="20"></div>
        </div>
        <div>
        <label for="email">Email</label>
        <div class="field"><input type="text" name="email" id="email" value="" maxlength="255"></div>
        </div>
        <input type="hidden" name="subStep" value="1" />
        <div class="fieldGroup"><input type="submit" value="Submit" style="margin-left: 150px;" /></div>
        <div class="clearfix"></div>
        <?php
        echo $this->FormClose();
        break;
    case 'successPage':
         echo $this->Format("Un message vous a été envoyé avec les instructions pour re-initialiser votre mot de passe");
        break;
    case 'recoverForm':
        echo $this->Format("Bienvenue ".$securityUser."---");
        echo $this->Format("Saisir votre nouveau mot de passe dans les champs ci-dessous");
        if ($error == true) {
            echo $this->Format("Les nouveaux mots de passe doivent être identiques et non vides");
        }
        echo $this->FormOpen();
        ?>
        <div>
        <label for="pw0">New Password</label>
        <div class="field"><input type="password" class="input" name="pw0" id="pw0" value="" maxlength="20"></div>
        </div>
        <div>
        <label for="pw1">Confirm Password</label>
        <div class="field"><input type="password" class="input" name="pw1" id="pw1" value="" maxlength="20"></div>
        </div>
        <input type="hidden" name="subStep" value="2" />
        <input type="hidden" name="userID" value="<?php echo  $securityUser == '' ? $_POST['userID'] : $securityUser; ?>" />
        <input type="hidden" name="key" value="<?php echo  $_GET['email'] == '' ? $_POST['key'] : $_GET['email']; ?>" />
        <div> <input type="submit" value="Submit" style="margin-left: 150px;" /></div>
        <div class="clear"></div>
        <?php
        echo $this->FormClose();
        break;
    case 'invalidKey':
        echo $this->Format("Clef de validation incorrecte");
        break;
    case 'recoverSuccess':
        echo $this->Format("Mot de passe mis à jour !");
        break;
}
?>