<?php

namespace MCT\Controllers;

use MCT\Configuration\PluginConfiguration;
use MCT\Repositories\ExportDataRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Log\Loggable;

class TestController extends Controller
{
    use Loggable;

    public function testMethod()
    {
        return 'abc';
    }

    public function clearDataTable()
    {
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportList = $exportDataRepository->deleteAllRecords();
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return false;
        }
        return true;
    }

    public function clearOneOrderFromDataTable($plentyOrderId)
    {
        /** @var ExportDataRepository $exportDataRepository */
        $exportDataRepository = pluginApp(ExportDataRepository::class);
        try {
            $exportDataRepository->deleteOneRecord($plentyOrderId);
        } catch (\Throwable $e) {
            $this->getLogger(__METHOD__)->error(PluginConfiguration::PLUGIN_NAME . '::error.readExportError',
                [
                    'message'     => $e->getMessage(),
                ]);
            return false;
        }
        return true;
    }
}
