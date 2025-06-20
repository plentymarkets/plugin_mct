<?php

namespace MCT\Configuration;

use Exception;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class PluginConfiguration
{
    use Loggable;

    const PLUGIN_NAME            = "mct";

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    public function __construct(
        ConfigRepository $configRepository
    ) {
        $this->configRepository  = $configRepository;
    }

    /**
     * @param $configKey
     *
     * @return mixed
     */
    protected function getConfigValue($configKey)
    {
        return $this->configRepository->get(self::PLUGIN_NAME . '.' . $configKey);
    }

    /**
     * @return int
     */
    public function getCronInterval()
    {
        $cronInterval = $this->configRepository->get(self::PLUGIN_NAME . '.cronInterval');
        if ($cronInterval == null) {
            return 20;
        }
        return (int)$cronInterval;
    }

    /**
     * @return array|true[]
     */
    public function getSFTPCredentials(): array
    {
        $ftpHost        = $this->getConfigValue('host');
        $ftpUser        = $this->getConfigValue('username');
        $ftpPassword    = $this->getConfigValue('password');
        $ftpPort        = $this->getConfigValue('port');
        $ftpFolderPath  = $this->getConfigValue('folderPath');

        if ($ftpHost === null || $ftpUser === null || $ftpPassword === null || $ftpPort === null) {
            $this->getLogger(__METHOD__)->error(self::PLUGIN_NAME . '::error.mandatoryCredentialsAreNotSet',
                [
                    'ftp_hostname'     => $ftpHost,
                    'ftp_username'     => $ftpUser,
                    'ftp_password'     => $ftpPassword,
                    'ftp_port'         => $ftpPort,
                    'ftp_folderPath'   => $ftpFolderPath,
                ]);
            
            return [
                'error' => true
            ];
        }

        return [
            'ftp_hostname'     => $ftpHost,
            'ftp_username'     => $ftpUser,
            'ftp_password'     => $ftpPassword,
            'ftp_port'         => $ftpPort,
            'ftp_folderPath'   => $ftpFolderPath,
        ];
    }

    public function getFaultyOrderStatus(): string
    {
        $orderStatus = $this->getConfigValue('faultyStatus');
        if (!is_null($orderStatus) && ((int)$orderStatus > 0)){
            return $orderStatus;
        }
        return '';
    }
}
