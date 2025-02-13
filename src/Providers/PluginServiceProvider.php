<?php
namespace MCT\Providers;

use ErrorException;
use Exception;
use MCT\Crons\ClearExportTableCron;
use MCT\Crons\SendDataCron;
use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Plenty\Plugin\Application;
use Plenty\Plugin\ServiceProvider;


/**
 * Class PluginServiceProvider
 * @package MCT\Providers
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * @param CronContainer $container
     * @param EventProceduresService $eventProceduresService
     * @param WizardContainerContract $wizardContainerContract
     * @param Application $app
     * @return void
     * @throws ErrorException
     */
    public function boot(
        CronContainer $container,
        EventProceduresService $eventProceduresService,
        WizardContainerContract $wizardContainerContract,
        Application $app
    ) {
        $container->add(CronContainer::EVERY_FIVE_MINUTES, SendDataCron::class);
        $container->add(CronContainer::DAILY, ClearExportTableCron::class);
        $this->bootProcedures($eventProceduresService);
        $this->getApplication()->register(MCTRouteServiceProvider::class);
    }

    /**
     * @param  EventProceduresService  $eventProceduresService
     */
    private function bootProcedures(EventProceduresService $eventProceduresService)
    {
        $eventProceduresService->registerProcedure(
            'MCT',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => ' MCT FTPOrderExport',
                'en' => ' MCT FTPOrderExport'
            ],
            '\MCT\Procedures\ExportProcedure@run'
        );
    }
}
