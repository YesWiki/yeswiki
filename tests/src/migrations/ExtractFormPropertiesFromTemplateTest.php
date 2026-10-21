<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A Doryphore form's automatic title survives its template being converted to JSON and then losing its pseudo-fields. */
class ExtractFormPropertiesFromTemplateTest extends YesWikiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260727110000_ExtractFormPropertiesFromTemplate.php';
    }

    /** @return array<string, mixed> the form body as ConvertFormTemplatesToJson then RenameFormBodyKeys leave it */
    private function convertedBody(string $legacyTemplate): array
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        return [
            'label' => 'Annuaire',
            'template' => json_decode($formManager->normalizeTemplate($legacyTemplate), true),
        ];
    }

    public function testTheAutomaticTitleBecomesTheEntryTitleTemplate(): void
    {
        $body = (new \ExtractFormPropertiesFromTemplate())->extract($this->convertedBody(
            "titre***{{bf_nom}} {{bf_prenom}}***Titre Automatique***\r\n"
            . "texte***bf_nom***Nom***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\n"
            . 'texte***bf_prenom***Prénom***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***'
        ));

        $this->assertNotNull($body);
        $this->assertSame('{{bf_nom}} {{bf_prenom}}', $body['entry_title_template']);
        $this->assertSame(['bf_nom', 'bf_prenom'], array_column($body['template'], 'name'));
    }

    public function testTheOtherPseudoFieldsBecomeProperties(): void
    {
        $body = (new \ExtractFormPropertiesFromTemplate())->extract($this->convertedBody(
            "texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\n"
            . "acls***+***%***@admins***Commentaires*** ***bf_commentaires***1***\r\n"
            . "metadatas***margot***1col.tpl.html***margot.css***fond.jpg***clair***\r\n"
            . "utilisateur_wikini***bf_titre***bf_mail*** *** ***liste@example.org***@membres*** *** ***1***\r\n"
            . 'bookmarklet***bf_bookmarklet***Bookmarklet***bf_lien***bf_resume***Glissez-moi***'
        ));

        $this->assertNotNull($body);
        $this->assertSame('+', $body['entry_read_access']);
        $this->assertSame('%', $body['entry_write_access']);
        $this->assertSame('@admins', $body['entry_comment_access']);
        $this->assertTrue($body['entry_permit_activate_comments']);
        $this->assertSame(
            ['theme' => 'margot', 'skeleton' => '1col.tpl.html', 'style' => 'margot.css', 'background_image' => 'fond.jpg', 'css_preset' => 'clair'],
            $body['entry_metadatas']
        );
        $this->assertSame(
            ['name_field' => 'bf_titre', 'email_field' => 'bf_mail', 'add_to_group' => '@membres', 'update_email' => '1', 'mailing_list' => 'liste@example.org'],
            $body['entry_creates_user']
        );
        $this->assertSame(
            ['url_field' => 'bf_lien', 'description_field' => 'bf_resume', 'text_field' => 'Glissez-moi'],
            $body['entry_bookmarklet']
        );
        $this->assertSame(['bf_titre'], array_column($body['template'], 'name'));
    }

    public function testAFormWithoutAnAutomaticTitleUsesItsTitleField(): void
    {
        $body = (new \ExtractFormPropertiesFromTemplate())->extract($this->convertedBody(
            'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***'
        ));

        $this->assertNotNull($body);
        $this->assertSame('{{bf_titre}}', $body['entry_title_template']);
    }
}
