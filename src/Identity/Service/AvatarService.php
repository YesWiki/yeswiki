<?php

namespace YesWiki\Identity\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\AttachedFilePaths;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ImageResizer;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\Avatar;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The face of an account: its profile picture, or initials on a colour of its own.
 *
 * Every wiki has accounts that never uploaded anything, so "no picture" is the normal
 * case rather than the error case, and a blank disc for most of the wiki is not an
 * answer. Initials on a colour derived from the name are: it is stable (the same account
 * is the same colour on every screen, forever), it needs no storage, and it tells two
 * accounts apart at navbar size, which is the whole job.
 *
 * **Which field holds the picture is the form's to say** (ticket 11): this asks the User
 * form for the field playing the `image` role rather than reading `profile_picture` by
 * name. Unconfigured, that resolves to the form's first image field -- the locked
 * `profile_picture` ContentTypeSchema declares -- so nothing needs configuring, and a
 * webmaster with two image fields on their User form can still say which one is the face.
 */
class AvatarService
{
    /**
     * Rendered pixels of the square thumbnail. Double the ~48px an avatar shows at, so a
     * dense screen has something to work with; small enough that the navbar does not pull
     * a phone photo down on every page.
     */
    private const SIZE = 128;

    private PageManager $pageManager;
    private UserManager $userManager;
    private FormManager $formManager;
    private FieldRoleResolver $fieldRoles;
    private AttachedFilePaths $paths;
    private ImageResizer $resizer;
    private PageContext $pageContext;
    private UrlFormatter $urlFormatter;

    public function __construct(
        PageManager $pageManager,
        UserManager $userManager,
        FormManager $formManager,
        FieldRoleResolver $fieldRoles,
        AttachedFilePaths $paths,
        ImageResizer $resizer,
        PageContext $pageContext,
        UrlFormatter $urlFormatter
    ) {
        $this->pageManager = $pageManager;
        $this->userManager = $userManager;
        $this->formManager = $formManager;
        $this->fieldRoles = $fieldRoles;
        $this->paths = $paths;
        $this->resizer = $resizer;
        $this->pageContext = $pageContext;
        $this->urlFormatter = $urlFormatter;
    }

    /**
     * The avatar of the account named $name.
     *
     * A name that is not an account's -- an anonymous editor's IP address, a deleted
     * author -- still gets initials and a colour rather than nothing: the caller is
     * drawing someone either way.
     */
    public function forName(string $name): Avatar
    {
        $name = trim($name);
        $background = $this->background($name);

        return new Avatar(
            $name,
            $this->pictureUrl($name),
            $this->initials($name),
            $background,
            $this->readableOn($background)
        );
    }

    // -- the picture ----------------------------------------------------------------

    private function pictureUrl(string $name): ?string
    {
        if ($name === '' || !$this->userManager->isUserTag($name)) {
            return null;
        }

        $form = $this->formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        $propertyName = $this->fieldRoles->propertyName($form, FieldRole::IMAGE);
        if ($propertyName === null) {
            return null;
        }

        // NOT bypassing the ACLs: a webmaster who put a read ACL on the picture field
        // meant it, and Guard blanks the value for a reader it excludes (ticket 10)
        $page = $this->pageManager->getOne($name);
        $value = trim((string)(($page['body'] ?? [])[$propertyName] ?? ''));
        if ($value === '') {
            return null;
        }

        // an image field may hold a URL instead of an upload; it is already an address
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        return $this->thumbnailUrl($name, $value);
    }

    /**
     * The square thumbnail of an uploaded picture, generated on first use.
     *
     * Where an upload lives and what its resized copy is called both depend on the page
     * being rendered -- outside safe mode each page owns a directory -- and the page being
     * rendered here is whatever the visitor asked for, not the account. So the account is
     * made the current page for the length of the lookup, the same swap `FileField` does
     * to name a file it is about to write.
     */
    private function thumbnailUrl(string $tag, string $fileName): ?string
    {
        $previousTag = $this->pageContext->getTag();
        $previousPage = $this->pageContext->getPage();
        $this->pageContext->setTag($tag);
        $this->pageContext->setPage(['tag' => $tag, 'time' => '', 'body' => [], 'owner' => '', 'user' => '']);

        try {
            $source = $this->paths->uploadPath() . '/' . $fileName;
            if (!file_exists($source)) {
                return null;
            }

            $base = $this->urlFormatter->getBaseUrl() . '/';
            $thumbnail = $this->resizer->resizedFilename($source, (string)self::SIZE, (string)self::SIZE, 'crop');
            if (file_exists($thumbnail)) {
                return $base . $thumbnail;
            }

            // a picture that cannot be resized (an exotic encoding, a read-only cache
            // directory) is still the account's picture: serve the original rather than
            // falling back to initials it does not need
            return $this->resizer->resize($source, $thumbnail, self::SIZE, self::SIZE, 'crop') === $thumbnail
                ? $base . $thumbnail
                : $base . $source;
        } finally {
            $this->pageContext->setTag($previousTag);
            $this->pageContext->setPage($previousPage);
        }
    }

    // -- and what stands in for it --------------------------------------------------

    /** The first two letters of the name, upper-cased. */
    private function initials(string $name): string
    {
        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /**
     * A colour derived from the name, as `#rrggbb`.
     *
     * Hue, saturation and lightness each come from a different part of one hash, so two
     * accounts that happen to share a hue still differ, and the lightness genuinely
     * spans the range -- which is what makes the black-or-white choice below a choice
     * rather than a constant.
     */
    private function background(string $name): string
    {
        $hash = crc32(mb_strtolower($name));

        return $this->hslToHex(
            $hash % 360,
            (55 + ($hash >> 9) % 30) / 100,
            (32 + ($hash >> 17) % 48) / 100
        );
    }

    /**
     * Black or white, whichever contrasts more with $hex -- WCAG relative luminance,
     * which is what "readable on" means to a standard rather than to an eyeball.
     */
    private function readableOn(string $hex): string
    {
        $luminance = $this->relativeLuminance($hex);

        // the two contrast ratios against pure black and pure white, (L1+.05)/(L2+.05)
        return ($luminance + 0.05) / 0.05 >= 1.05 / ($luminance + 0.05) ? '#000000' : '#ffffff';
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = [];
        foreach ([1, 3, 5] as $offset) {
            $channel = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function hslToHex(int $hue, float $saturation, float $lightness): string
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $second = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $offset = $lightness - $chroma / 2;

        $sextant = intdiv($hue, 60) % 6;
        $rgb = [
            [$chroma, $second, 0],
            [$second, $chroma, 0],
            [0, $chroma, $second],
            [0, $second, $chroma],
            [$second, 0, $chroma],
            [$chroma, 0, $second],
        ][$sextant];

        return '#' . implode('', array_map(
            fn (float $channel) => str_pad(dechex((int)round(($channel + $offset) * 255)), 2, '0', STR_PAD_LEFT),
            $rgb
        ));
    }
}
