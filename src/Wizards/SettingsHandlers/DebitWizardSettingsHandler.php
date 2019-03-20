<?php

namespace Debit\Wizards\SettingsHandlers;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;

/**
 * Class TestWizardDataValidator
 * @package Plenty\Modules\Wizard\Validators
 */
class DebitWizardSettingsHandler implements WizardSettingsHandler
{

    /**
     * @param array $data
     * @return bool
     */
    public function handle(array $data)
    {
        //TODO save config
        return true;
    }

}
