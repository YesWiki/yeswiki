<?php
/*
Code original de ce fichier : Julien BALLESTRACCI
Patch: Captcha (c) 2007 Julien Ballestracci <julien@ecole-et-nature.org>
--
THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
--
*/
var_dump($GLOBALS['wiki']->config['captcha_words']);

if (isset($GLOBALS['wiki']->config['captcha_words']) and is_array($GLOBALS['wiki']->config['captcha_words'])) {
    $textes = $GLOBALS['wiki']->config['captcha_words'];
} else {
    $textes = array(
        "cactus",
        "arbre",
        "palmier",
        "plante",
        "jungle",
        "foret",
        "feuille",
        "eau",
        "ocean",
        "terre",
        "kiwi",
        "laitue",
        "salade",
        "narcisse",
        "pommier",
        "rose",
        "sapin",
        "vigne",
        "ecorse",
        "trefle",
        "radis",
        "mimosa"
    );
}


class TheCaptcha
{

    public $image;
    public $size;
    public $text;
    public $colours;

    // constructeur
    public function theCaptcha()
    {
        global $textes;
        srand((double) microtime() * 10000000);
        $this->size = $this->getImageSize();

        // Création de l'image
        $this->image = imagecreatetruecolor($this->size, 50);
        $this->loadColours();

        $uri = $_SERVER['REQUEST_URI'];
        $tmp = explode("?", $uri);
        $hash = (!empty($tmp[1]) ? $tmp[1] : null);

        $this->text = $this->findWord($hash);
    }

    // calcul de la taille de l'image en fonction du mot le plus long
    public function getImageSize()
    {
        global $textes;
        $theLongWord = null;
        foreach ($textes as $text) {
            if (strlen($text) > strlen($theLongWord)) {
                $theLongWord = $text;
            }
        }

        $size = ( (strlen($theLongWord) * 27) + (15 * 2) );
        unset($theLongWord);
        return $size;
    }

    // chargement des couleurs
    public function loadColours()
    {
        // red
        $this->colours = array(
            'red'  =>  imagecolorallocate($this->image, 214, 34, 34),
            'blue'  =>  imagecolorallocate($this->image, 34, 132, 214),
            'green'  =>  imagecolorallocate($this->image, 68, 214, 34),
            'yellow'  => imagecolorallocate($this->image, 234, 231, 19),
            'turkoise'  => imagecolorallocate($this->image, 68, 211, 216),
            'pink'  => imagecolorallocate($this->image, 216, 68, 204),
            'orange'  => imagecolorallocate($this->image, 216, 155, 68),
        );
    }

    // affiche le captcha
    public function printImage()
    {
        // Définition du content-type
        header("Content-type: image/png");

        $grey = imagecolorallocate($this->image, 200, 196, 196);
        $white = imagecolorallocate($this->image, 255, 255, 255);
        $black = imagecolorallocate($this->image, 0, 0, 0);

        imagefilledrectangle($this->image, 0, 0, $this->size, 50, $white);
        $font = __DIR__.'/agenda__.ttf';

        // dessinons quelques eclipses ;)
        for ($t = 0; $t <= 20; $t++) {
            imageellipse($this->image, rand(0, $this->size), rand(0, 50), rand(0, 200), rand(0, 200), $grey);
        }

        // centrage du texte
        $maxWord = (($this->size - 30) / 27);
        $cur_left = (strlen($this->text) == $maxWord ? 15 : (15 + ((($maxWord - strlen($this->text)) * 27)) /2));

        for ($t = 0; isset($this->text{$t}); $t++) {
            $cur_incli = rand(-20, 20);
            // ombre
            imagettftext($this->image, 32, $cur_incli, $cur_left, 40, $black, $font, $this->text{$t});
            // texte
            imagettftext($this->image, 32, $cur_incli, ($cur_left -1), 39, $this->colours[array_rand($this->colours)], $font, $this->text{$t});
            $cur_left += 27;
        }

        imagepng($this->image);
        imagedestroy($this->image);
    }

    // trouve le bon mot par rapport au hash envoyé
    public function findWord($hash = null)
    {
        global $textes;
        if ($hash) {
            foreach ($textes as $text) {
                if ($hash == cryptWord($text)) {
                    return $text;
                }
            }
        }

        return $textes[array_rand($textes)];
    }
}

// retourne le cryptage pour la vérification
function cryptWord($word)
{
    return md5(crypt(strtolower($word), 'ca'));
}

if (!defined('CAPTCHA_INCLUDE')) {
    $captcha = new TheCaptcha();
    $captcha->printImage();
}
