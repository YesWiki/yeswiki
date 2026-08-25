<?php

namespace YesWiki\Core;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HealthService;

abstract class YesWikiMigration
{
    /**
     * Every migration implements its body here (run by MigrationService).
     *
     * @return void
     */
    abstract public function run();

    protected ContainerInterface $services;
    protected ParameterBagInterface $params;
    protected DbService $dbService;

    /** @var list<string> */
    private array $notes = [];

    /**
     * Something the operator running `yeswicli migrate` needs to read: what this migration did, and what it found that it could not do for them (ticket 53).
     *
     * A finding does NOT go here. A finding is a claim about the present -- "your themes still
     * call {{searchform}}" -- which stops being true the moment somebody acts on it, so it is a
     * Health check the migration runs rather than a line it writes. What belongs here is what
     * this run did, said once, to whoever was at the terminal.
     */
    protected function say(string $line): void
    {
        $this->notes[] = $line;
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * Run a Health check now and say what it found, which is what a finding is for (ticket 53).
     *
     * The migration is where the operator is standing, so this is where they hear it. The check
     * itself is declared by the module that owns the subject and stays runnable from
     * `/admin/health` afterwards, for the webmaster who was not at the terminal -- and stops
     * being said at all the moment somebody acts on it.
     */
    protected function reportCheck(string $checkId): void
    {
        $finding = $this->getService(HealthService::class)->run($checkId);
        if ($finding === null) {
            return;
        }

        $this->say(
            $finding->check->labelText() . ': ' . $finding->detail
            . ($finding->check->sentence() === '' ? '' : ' -- ' . $finding->check->sentence())
        );
    }

    public function setServices(ContainerInterface $services): void
    {
        $this->services = $services;
    }

    /** Setter for the parameters. */
    public function setParams(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    public function setDbService(DbService $dbService): void
    {
        $this->dbService = $dbService;
    }

    /**
     * give service from name.
     *
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    protected function getService(string $className)
    {
        return $this->services->get($className);
    }
}
