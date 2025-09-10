<?php

namespace MCT\Controllers;

use MCT\Configuration\PluginConfiguration;
use MCT\Helpers\MappingHelper;
use MCT\Repositories\ExportDataRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Log\Loggable;

class TestController extends Controller
{
    use Loggable;

    public function clearDataTable()
    {
        $exportDataRepository = pluginApp(ExportDataRepository::class);

        /** @var MappingHelper $helper */
        $helper = pluginApp(MappingHelper::class);
        try {
            $exportList = $exportDataRepository->deleteAllRecords();
            $helper->addHistoryData('Clearing export table...');
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

        /** @var MappingHelper $helper */
        $helper = pluginApp(MappingHelper::class);
        try {
            $exportDataRepository->deleteOneRecord($plentyOrderId);
            $helper->addHistoryData('Clearing ' . $plentyOrderId . ' from export table...');
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
