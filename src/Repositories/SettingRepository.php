<?php

namespace MCT\Repositories;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\DataBase\Contracts\Model;
use MCT\Contracts\SettingRepositoryContract;
use MCT\Models\Setting;

class SettingRepository implements SettingRepositoryContract
{
    /**
     * @var DataBase
     */
    private $database;

    /**
     * SettingsRepository constructor.
     *
     * @param  DataBase  $database
     */
    public function __construct(DataBase $database)
    {
        $this->database = $database;
    }

    /**
     * @param $key
     * @param $value
     * @return Model
     * @throws ValidationException
     */
    public function save($key, $value): Model
    {
        $settings        = pluginApp(Setting::class);
        $settings->key   = (string)$key;
        $settings->value = (string)$value;

        return $this->database->save($settings);
    }

    /**
     * @param $key
     * @return mixed|string|null
     */
    public function get($key)
    {
        $settings = $this->database->query(Setting::class)
            ->where('key', '=', $key)
            ->get();

        return is_array($settings) ? $settings[0]->value : null;
    }

    /**
     * @return Setting[]
     */
    public function list()
    {
        return $this->database->query(Setting::class)->get();
    }

    /**
     * @return string
     * @throws ValidationException
     */
    public function getBatchNumber(): string
    {
        $batch = $this->get('batch_number');

        if ($batch === null){
            $this->save('batch_number', 1);
            return '01';
        }

        if ((int)$batch < 10){
            return '0' . $batch;
        }

        return (string)$batch;
    }


    /**
     * @return int
     * @throws ValidationException
     */
    public function getLatestCronExecutionTime(): int
    {
        $latestTime = $this->get('latest_cron_execution_time');

        if ($latestTime === null){
            $this->save('latest_cron_execution_time', microtime(true));
            return 0;
        }

        return (int)$latestTime;
    }

    /**
     * @return void
     * @throws ValidationException
     */
    public function setLatestCronExecutionTime()
    {
        $this->save('latest_cron_execution_time', microtime(true));
    }

    /**
     * @return void
     * @throws ValidationException
     */
    public function incrementBatchNumber(): void
    {
        $batch = $this->getBatchNumber();
        $nextBatch = (int)$batch + 1;
        $this->save('batch_number', $nextBatch);
    }
}
