<?php

namespace Debit\Migrations;

use Debit\Models\Settings;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

class CreateSettings_1_0_0
{
    /**
     * Create the settings table
    */
    public function run(Migrate $migrate)
    {
        try
        {
            $migrate->createTable(Settings::class);
        }
        catch(\Exception $e)
        {
            echo $e->getMessage();
        }
    }
}