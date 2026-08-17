<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Ticket 23 renamed the French action names. */
class RenameActionAclsToEnglishNames extends YesWikiMigration
{
    private const RENAMES = [
        'bazarliste' => 'entrylist',
        'bazarcarto' => 'entrymap',
        'bazartable' => 'entrytable',
        'bazarexport' => 'entryexport',
        'bazarimport' => 'entryimport',
        'bazarfollow' => 'entryfollow',
        'bazaruserpage' => 'entryuserpage',
        'bazarlistecategorie' => 'entrylistcategory',
        'calendrier' => 'calendar',
        'abonnement' => 'subscribe',
        'desabonnement' => 'unsubscribe',
        'nuagetag' => 'tagcloud',
        'valeur' => 'value',
        'gererdroits' => 'adminacls',
        'gererthemes' => 'adminthemes',
        'ariane' => 'breadcrumb',
        'doubleclic' => 'doubleclick',
        'barreredaction' => 'editbar',
        'titrepage' => 'pagetitle',
        'moteurrecherche' => 'searchform',
    ];

    public function run()
    {
        $params = $this->services->get(ParameterBagInterface::class);
        $permissions = $params->has('permissions') ? $params->get('permissions') : [];
        if (!is_array($permissions) || empty($permissions['action']) || !is_array($permissions['action'])) {
            return;
        }

        $actions = $permissions['action'];
        $moved = [];
        foreach (self::RENAMES as $old => $new) {
            if (!array_key_exists($old, $actions)) {
                continue;
            }

            if (!array_key_exists($new, $actions)) {
                $actions[$new] = $actions[$old];
                $moved[] = "$old -> $new";
            }
            unset($actions[$old]);
        }

        if (empty($moved)) {
            return;
        }

        $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $permissions = $config['permissions'] ?? [];
        $permissions['action'] = $actions;
        $config['permissions'] = $permissions;
        $config->write();

        echo 'Renamed action ACLs: ' . implode(', ', $moved) . "\n";
    }
}
