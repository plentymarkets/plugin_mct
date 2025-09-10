<?php

namespace MCT\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class HistoryData extends Model implements \JsonSerializable
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $plentyOrderId;

    /**
     * @var string
     */
    public $message;

    /**
     * @var string
     */
    public $savedAt;

    protected $primaryKeyFieldName     = 'id';
    protected $primaryKeyFieldType     = self::FIELD_TYPE_INT;
    protected $autoIncrementPrimaryKey = true;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'mct::history_data';
    }

    /**
     * Specify data which should be serialized to JSON
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize(): mixed
    {
        return [
            'plentyOrderId'    => $this->plentyOrderId,
            'message'          => $this->message,
            'savedAt'          => $this->savedAt
        ];
    }
}
