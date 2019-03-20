<?php

namespace Debit\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Debit\Models\ShippingCountrySettings;

/** This migration initializes all Settings in the Database */
class CreateShippingCountrySettings_1_0
{
    public function run(Migrate $migrate)
    {
        $migrate->createTable(ShippingCountrySettings::class);
    }
}