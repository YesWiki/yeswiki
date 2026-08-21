<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * this class defines methods to generate captcha with a service.
 *
 * @author : Jeremy Dufraisse 2024
 * @author : Julien Ballestracci 2007 <julien@ecole-et-nature.org> (this file is inspired from his works)
 */
class CaptchaController extends YesWikiController
{
    /**
     * @var int[][] COLOURS array or 3 int to be associated to a color name
     */
    public const COLOURS = [
        'pink' => [216, 68, 204],
        'red' => [214, 34, 34],
        'orange' => [216, 155, 68],
        'gold' => [255, 208, 0],
        'yellow' => [234, 231, 19],
        'green' => [68, 214, 34],
        'turkoise' => [68, 211, 216],
        'blue' => [34, 132, 214],
        'royalblue' => [65, 105, 225],
        'purple' => [144, 0, 144],
    ];
    /**
     * @var int[][] TONES array or 3 int to be associated to a tone name
     */
    public const TONES = [
        'grey' => [200, 196, 196],
        'white' => [255, 255, 255],
        'black' => [0, 0, 0],
    ];
    /**
     * @var string[] DEFAULT_TEXTS for captcha
     */
    public const DEFAULT_TEXTS = [
        'arbre',
        'cactus',
        'eau',
        'ecorse',
        'feuille',
        'foret',
        'jungle',
        'kiwi',
        'laitue',
        'mimosa',
        'narcisse',
        'ocean',
        'palmier',
        'plante',
        'pommier',
        'radis',
        'rose',
        'salade',
        'sapin',
        'terre',
        'trefle',
        'vigne',
    ];
    /**
     * @var int IMAGE_HEIGHT
     */
    public const IMAGE_HEIGHT = 50;
    /**
     * @var int TEXT_BASELINE
     */
    public const TEXT_BASELINE = 40;
    /**
     * @var int TEXT_HEIGHT
     */
    public const TEXT_HEIGHT = 32;
    /**
     * @var int TEXT_MARGIN
     */
    public const TEXT_MARGIN = 15;
    /**
     * @var int CHAR_SLOPE_MAX_ANGLE
     */
    public const CHAR_SLOPE_MAX_ANGLE = 20;
    /**
     * @var int CHAR_WIDTH
     */
    public const CHAR_WIDTH = 27;

    /**
     * @var string CRYPT_ALGO used to calculate hash
     */
    public const CRYPT_ALGO = PASSWORD_BCRYPT;

    /**
     * @var int CRYPT_COST used to calculate hash ; fast and not too strong
     */
    public const CRYPT_COST = 4;

    /**
     * @var string path
     */
    protected $fontFile;
    /**
     * @var int according to longest word
     */
    protected $imageWidth;
    /**
     * @var ParameterBagInterface parameters
     */
    protected $params;
    /**
     * @var string[] availables words
     */
    protected $words;

    /**
     * constructor.
     */
    public function __construct(
        ParameterBagInterface $params
    ) {
        $this->params = $params;
        $this->words = self::DEFAULT_TEXTS;
        $this->updateWordsFromConfig();
        $this->imageWidth = $this->getImageWidth();
        $this->fontFile = YESWIKI_PROGRAM_DIR . '/src/assets/fonts/agenda__.ttf';
    }

    /**
     * generate and output an image for captcha.
     *
     * @throws \Exception on errors
     */
    public function printImage(string $hash): void
    {
        /**
         * @var \GdImage $image manipulated image
         */
        $image = $this->createImage($this->imageWidth);

        // background
        imagefilledrectangle($image, 0, 0, $this->imageWidth, self::IMAGE_HEIGHT, $this->getColorFromName($image, 'white'));

        $this->drawSomeElipses($image, $this->imageWidth);

        /**
         * @var string $text
         */
        $text = $this->getTextFromHash($hash);

        $this->drawtext($image, $this->imageWidth, $text);

        /* output */
        imagepng($image);
    }

    /**
     * choose a random word and gives hash.
     *
     * @return string $hash
     */
    public function generateHash(): string
    {
        return $this->cryptWord($this->selectText());
    }

    /**
     * check captcha before save edit.
     *
     * @param string $mode 'page' or 'entry'
     *
     * @return array{bool, string|null} the verdict, and the message to show when it is false -- null when no captcha was due
     */
    public function checkCaptchaBeforeSave(string $mode = 'page'): array
    {
        if (!$this->getService(AclService::class)->isAdmin() && $this->params->get('use_captcha')) {
            $post = $this->getRequest()->request;
            if (($mode != 'entry' && $post->get('submit') == InputFilter::EDIT_PAGE_SUBMIT_VALUE)
                || ($mode == 'entry' && !empty($post->get('bf_titre')))) {
                $error = '';
                if (empty($post->get('captcha'))) {
                    $error = _t('CAPTCHA_ERROR_PAGE_UNSAVED');
                } elseif (!$this->check(
                    (string)$post->get('captcha', ''),
                    (string)$post->get('captcha_hash', '')
                )) {
                    $error = _t('CAPTCHA_ERROR_WRONG_WORD');
                }
                // clean if error
                if (!empty($error)) {
                    $_POST['submit'] = '';
                    if ($mode == 'entry') {
                        unset($_POST['bf_titre']);
                    }
                }
                unset($_POST['captcha']);
                unset($_POST['captcha_hash']);
            }
        }

        return [empty($error), $error ?? null];
    }

    /**
     * render captcha if needed.
     */
    public function renderCaptcha(string &$output): void
    {
        if (!$this->getService(AclService::class)->isAdmin() && $this->params->get('use_captcha')) {
            $captchaField = $this->renderCaptchaField();
            $matches = [];
            if (preg_match_all('/(\<div class="form-actions">.*<button type=\"submit\" name=\"submit\")/Uis', $output, $matches)) {
                foreach ($matches[0] as $key => $match) {
                    $output = str_replace(
                        $match,
                        $captchaField . $matches[1][$key],
                        $output
                    );
                }
            }
        }
    }

    /**
     * render captcha field if needed.
     */
    public function renderCaptchaField(): string
    {
        $captchaField = '';
        if (!$this->getService(AclService::class)->isAdmin() && $this->params->get('use_captcha')) {
            // afficher les champs de formulaire et de l'image
            $hash = $this->generateHash();
            $captchaField = $this->render(
                '@core/captcha-field.twig',
                [
                    'baseUrl' => $this->getService(UrlFormatter::class)->getBaseUrl(),
                    'crypt' => $hash,
                    'cryptBase64' => base64_encode($hash),
                ]
            );
        }

        return $captchaField;
    }

    /**
     * check if word is the rigth one.
     *
     * @return bool $isValidated
     */
    public function check(string $word, string $hash): bool
    {
        if (!empty($word)
            && !empty($hash)
            && in_array($word, $this->words, true)) {
            $options = password_get_info($hash);

            return isset($options['algo'])
                && $options['algo'] === self::CRYPT_ALGO
                && password_verify($word, $hash);
        }

        return false;
    }

    /**
     * retrieve text from hash.
     *
     * @return string $text (randomly selected in case of error)
     */
    protected function getTextFromHash(string $hash): string
    {
        if (!empty($hash)) {
            for ($idx = 0; $idx < count($this->words); $idx++) {
                /**
                 * @var string $word
                 */
                $word = $this->words[$idx];
                if ($this->check($word, $hash)) {
                    return $word;
                }
            }
        }

        // back-up
        return $this->selectText();
    }

    /**
     * crypt a word.
     *
     * @return string $hash
     */
    protected function cryptWord(string $word): string
    {
        return password_hash($word, self::CRYPT_ALGO, [
            'cost' => self::CRYPT_COST,
        ]);
    }

    /**
     * generate a color from a name.
     *
     * @param \GdImage $image
     *
     * @return int representation of colour
     *
     * @throws \Exception on errors
     */
    protected function getColorFromName($image, string $name): int
    {
        $colorSet = self::COLOURS[$name] ?? self::TONES[$name] ?? null;
        if ($colorSet === null) {
            throw new \Exception('Not existing color\'s name !');
        }
        // clamped for the analyser, which sees the two palettes' union as plain `int`. Every
        // declared value is already in range, so this is a no-op on the palettes as they stand
        // and a correction rather than a TypeError if one ever is not (ticket 40).
        $channel = static fn (int $value): int => max(0, min(255, $value));
        $color = imagecolorallocate($image, $channel($colorSet[0]), $channel($colorSet[1]), $channel($colorSet[2]));
        if ($color === false) {
            throw new \Exception('Not possible to generate color');
        }

        return $color;
    }

    /**
     * get random color.
     *
     * @param \GdImage $image
     *
     * @return int representation of colour
     *
     * @throws \Exception on errors
     */
    protected function getRandomColor($image): int
    {
        /**
         * @var string[] $colorsKeys
         */
        $colorsKeys = array_keys(self::COLOURS);

        return $this->getColorFromName($image, $colorsKeys[random_int(0, count($colorsKeys) - 1)]);
    }

    /**
     * create an image.
     *
     * @return \GdImage new image
     *
     * @throws \Exception on errors
     */
    protected function createImage(int $imageWidth)
    {
        // The `is_resource()` half of this was for PHP 7, where GD returned a resource rather
        // than a GdImage. composer.json has required ^8.3 since the rewrite, so that arm could
        // never run -- and the `@var string|bool` on phpversion() was making the analyser
        // uncertain about a function that returns a string (ticket 40).
        $image = imagecreatetruecolor(max(1, $imageWidth), self::IMAGE_HEIGHT);
        if ($image === false) {
            throw new \Exception('Not possible to generate image');
        }

        return $image;
    }

    /**
     * calculated image width according to longest word.
     *
     * @return int image width
     */
    protected function getImageWidth(): int
    {
        /**
         * @var int $maxSize size of the longest word
         */
        $maxSize = array_reduce(
            $this->words,
            function ($currentSize, $word) {
                $chars = str_split($word);

                return count($chars) > $currentSize ? count($chars) : $currentSize;
            },
            0
        );

        return $maxSize * self::CHAR_WIDTH + self::TEXT_MARGIN * 2;
    }

    /**
     * update $words from params.
     */
    protected function updateWordsFromConfig(): void
    {
        if ($this->params->has('captcha_words')) {
            $wantedWords = $this->params->get('captcha_words');
            if (is_array($wantedWords)) {
                $wantedWords = array_values(array_filter(
                    $wantedWords,
                    function ($element) {
                        return is_string($element)
                            && strlen(trim($element)) > 0
                            && preg_match('/^[A-Za-z0-9]*$/', $element);
                    }
                ));
                if (count($wantedWords) > 0) {
                    $this->words = $wantedWords;
                }
            }
        }
    }

    /**
     * draw some elipses.
     *
     * @param \GdImage $image
     *
     * @throws \Exception on errors
     */
    protected function drawSomeElipses($image, int $imageWidth): void
    {
        /**
         * @var int $grey
         */
        $grey = $this->getColorFromName($image, 'grey');
        for ($idx = 0; $idx < 22; $idx++) {
            imageellipse(
                $image,
                random_int(0, $imageWidth),
                random_int(0, self::IMAGE_HEIGHT),
                random_int(0, 200),
                random_int(0, 200),
                $grey
            );
        }
    }

    /**
     * choose randomly a text in list.
     *
     * @return string text
     */
    protected function selectText(): string
    {
        return $this->words[random_int(0, count($this->words) - 1)];
    }

    /**
     * draw text.
     *
     * @param \GdImage $image
     *
     * @throws \Exception on errors
     */
    protected function drawtext($image, int $imageWidth, string $text): void
    {
        /**
         * @var int $black color
         */
        $black = $this->getColorFromName($image, 'black');
        /**
         * @var string[] $chars from $text
         */
        $chars = str_split($text);
        /**
         * @var int $pos where text is written init, to center text
         */
        $pos = intval(floor(($imageWidth - count($chars) * self::CHAR_WIDTH) / 2));
        for ($idx = 0; $idx < count($chars); $idx++) {
            /**
             * @var int $randomSlope
             */
            $randomSlope = random_int(-self::CHAR_SLOPE_MAX_ANGLE, self::CHAR_SLOPE_MAX_ANGLE);
            // shadow
            imagettftext(
                $image,
                self::TEXT_HEIGHT,
                $randomSlope,
                $pos + 1,
                self::TEXT_BASELINE + 1,
                $black,
                $this->fontFile,
                $chars[$idx]
            );
            // texte
            imagettftext(
                $image,
                self::TEXT_HEIGHT,
                $randomSlope,
                $pos,
                self::TEXT_BASELINE,
                $this->getRandomColor($image),
                $this->fontFile,
                $chars[$idx]
            );
            $pos += self::CHAR_WIDTH;
        }
    }
}
