<?php

namespace MCT\Crons;

use MCT\Services\OrderExportService;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\Log\Loggable;
use Throwable;

class ClearExportTableCron extends CronHandler
{
    use Loggable;

    /**
     * @param OrderExportService $exportService
     * @return void
     */
    public function handle(OrderExportService $exportService)
    {
        $exportService->clearExportTable();
    }
}
