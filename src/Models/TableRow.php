<?php

namespace MCT\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class TableRow extends Model implements \JsonSerializable
{
    /**
     * @var int
     */
    public $plentyOrderId;

    /**
     * @var string
     */
    public $exportedData;

    /**
     * @var string
     */
    public $savedAt;


    /**
     * @var string
     */
    public $sentAt;

    protected $primaryKeyFieldName     = 'plentyOrderId';
    protected $primaryKeyFieldType     = self::FIELD_TYPE_INT;
    protected $autoIncrementPrimaryKey = false;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'mct::export_stack';
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
            'exportedData'     => $this->exportedData,
            'savedAt'          => $this->savedAt,
            'sentdAt'          => $this->sentAt,
        ];
    }
}
