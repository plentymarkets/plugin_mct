<?php

namespace MCT\Procedures;

use MCT\Services\OrderExportService;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;

use Throwable;
use MCT\Configuration\PluginConfiguration;

class ExportProcedure
{
    use Loggable;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param OrderExportService $exportService
     * @return mixed
     * @throws Throwable
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        OrderExportService       $exportService
    ) {
        /** @var Order $order */
        $order = $eventTriggered->getOrder();

        $this->getLogger(__METHOD__)
            ->addReference('orderId', $order->id)
            ->report(PluginConfiguration::PLUGIN_NAME . '::general.logMessage', [
                'message'       => 'Start processing',
                'orderId'       => $order->id
            ]);

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        return $authHelper->processUnguarded(function () use ($order, $exportService) {
            try {
                $exportService->processOrder($order);
            } catch (Throwable $e) {
                $this->getLogger(__METHOD__)
                    ->addReference('orderId', $order->id)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.exceptionMessage', $e->getMessage());
            }

            $this->getLogger(__METHOD__)
                ->addReference('orderId', $order->id)
                ->report(PluginConfiguration::PLUGIN_NAME . '::general.logMessage', [
                    'message'       => 'End processing',
                    'orderId'       => $order->id
                ]);

            return 0;
        });
    }
}
