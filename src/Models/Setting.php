<?php

namespace MCT\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class Setting extends Model implements \JsonSerializable
{
    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $value;

    protected $primaryKeyFieldName     = 'key';
    protected $primaryKeyFieldType     = self::FIELD_TYPE_STRING;
    protected $autoIncrementPrimaryKey = false;

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'mct::settings';
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
            $this->key => $this->value
        ];
    }
}
