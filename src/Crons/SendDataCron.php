<?php

namespace MCT\Crons;

use MCT\Configuration\PluginConfiguration;
use MCT\Repositories\SettingRepository;
use MCT\Services\OrderExportService;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\Log\Loggable;
use Throwable;

class SendDataCron extends CronHandler
{
    use Loggable;

    /**
     * @param OrderExportService $exportService
     * @return bool
     */
    public function handle(
        OrderExportService  $exportService,
        PluginConfiguration $configRepository,
        SettingRepository   $settingsRepository
    )
    {
        $cronInterval = $configRepository->getCronInterval();
        $latestExecutionTime = $settingsRepository->getLatestCronExecutionTime();

        if (($latestExecutionTime + $cronInterval * 60) <= microtime(true)) {
            $result = $exportService->sendDataToClient();
            $settingsRepository->setLatestCronExecutionTime();
            return $result;
        }
    }
}
