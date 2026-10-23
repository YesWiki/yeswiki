<?php

namespace YesWiki\Test\Bazar\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * On a wiki private by default, a search filtered on read ACLs still lists the entries left public.
 */
class SearchManagerPrivateWikiTest extends YesWikiTestCase
{
    private const ENTRY_TAG = 'SearchManagerPrivateWikiFiche';

    private $wiki;
    private $params;
    private $defaultReadAcl;
    private string $formId;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->wiki->services->get(AuthController::class)->logout();
        $this->params = $this->wiki->services->get(ParameterBagInterface::class);
        $this->defaultReadAcl = $this->params->has('default_read_acl') ? $this->params->get('default_read_acl') : null;

        $this->formId = $this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Search private wiki test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
        ]);
        $this->wiki->services->get(EntryManager::class)->create($this->formId, [
            'antispam' => 1,
            'id_fiche' => self::ENTRY_TAG,
            'bf_titre' => 'Search private wiki entry',
        ]);
        $this->wiki->services->get(AclService::class)->save(self::ENTRY_TAG, 'read', '*');
        $this->params->set('default_read_acl', '+');
    }

    protected function tearDown(): void
    {
        $this->params->set('default_read_acl', $this->defaultReadAcl ?? '*');
        $this->wiki->services->get(EntryManager::class)->delete(self::ENTRY_TAG, true);
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::ENTRY_TAG);
        $this->wiki->services->get(AclService::class)->delete(self::ENTRY_TAG);
        $this->wiki->services->get(FormManager::class)->delete($this->formId);
    }

    public function testAPublicEntryIsFoundByAnAnonymousVisitor()
    {
        $entries = $this->wiki->services->get(SearchManager::class)->search(['formsIds' => [$this->formId]], true);

        $this->assertSame([self::ENTRY_TAG], array_values(array_column($entries, 'id_fiche')));
    }
}
