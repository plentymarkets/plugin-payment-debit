<?php

namespace Debit\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Class ShippingCountrySettings
 *
 * @property int $id
 * @property int $plentyId
 * @property int $shippingCountryId
 */
class ShippingCountrySettings extends Model
{
    const MODEL_NAMESPACE = 'Debit\Models\ShippingCountrySettings';

    public $id;
    public $plentyId;
    public $shippingCountryId;


    /**
     * @return string
     */
    public function getTableName():string
    {
        return 'Debit::shippingCountries';
    }
}