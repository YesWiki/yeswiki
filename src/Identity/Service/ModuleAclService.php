<?php

namespace YesWiki\Identity\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/**
 * Per-module (action/handler) ACLs, stored under the `permissions` config key
 * (historic Wiki::GetModuleACL()/SetModuleACL()/CheckModuleACL()). "*" means
 * everybody; see EditActionsAclsAction/EditHandlersAclsAction for the admin UI.
 */
class ModuleAclService
{
    protected ParameterBagInterface $params;
    protected ConfigurationService $configurationService;
    protected AclService $aclService;

    /** @var array<string, string> memo cache, `type_module` => acl */
    protected $cache = [];

    public function __construct(
        ParameterBagInterface $params,
        ConfigurationService $configurationService,
        AclService $aclService
    ) {
        $this->params = $params;
        $this->configurationService = $configurationService;
        $this->aclService = $aclService;
    }

    /**
     * The ACL for a module, "*" (everybody) when none is configured.
     *
     * @param string $moduleType 'action' or 'handler'
     */
    public function getModuleAcl(string $module, string $moduleType): string
    {
        $module = strtolower($module);
        $moduleType = strtolower($moduleType);
        $moduleKey = $moduleType . '_' . $module;
        if (array_key_exists($moduleKey, $this->cache)) {
            return $this->cache[$moduleKey];
        }
        $permissions = $this->params->has('permissions') ? $this->params->get('permissions') : [];
        $permissions = is_array($permissions) ? $permissions : [];
        $acl = empty($permissions[$moduleType][$module]) ? '*' : $permissions[$moduleType][$module];

        return $this->cache[$moduleKey] = $acl;
    }

    /**
     * Set the ACL for a module and persist it to the config file.
     *
     * @param string $moduleType 'action' or 'handler'
     *
     * @return int 0 on success, > 0 on error
     */
    public function setModuleAcl(string $module, string $moduleType, string $acl): int
    {
        $module = strtolower($module);
        $moduleType = strtolower($moduleType);
        $moduleKey = $moduleType . '_' . $module;

        // Check if value has changed
        $old = $this->getModuleAcl($module, $moduleType);
        if ($old === $acl) {
            return 0; // nothing has changed
        }

        // Update the cache
        $this->cache[$moduleKey] = $acl;

        // Write to the config file
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $permissions = $config['permissions'] ?? [];
        if (!isset($permissions[$moduleType])) {
            $permissions[$moduleType] = [];
        }
        $permissions[$moduleType][$module] = $acl;
        $config['permissions'] = $permissions;

        return $config->write() ? 0 : 1;
    }

    /**
     * Whether $user (default: the current user) satisfies a module's ACL.
     *
     * @param string $moduleType 'action' or 'handler'
     */
    public function checkModuleAcl(string $module, string $moduleType, ?string $user = null): bool
    {
        $acl = $this->getModuleAcl($module, $moduleType);

        return $this->aclService->check($acl, $user);
    }
}
