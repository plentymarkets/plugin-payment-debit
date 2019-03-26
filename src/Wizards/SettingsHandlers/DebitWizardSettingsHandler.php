<?php

namespace Debit\Wizards\SettingsHandlers;
use Debit\Services\SettingsService;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;

/**
 * Class TestWizardDataValidator
 * @package Plenty\Modules\Wizard\Validators
 */
class DebitWizardSettingsHandler implements WizardSettingsHandler
{
    /**
     * @var Webstore
     */
    private $webstore;

    /**
     * @param array $parameter
     * @return bool
     */
    public function handle(array $parameter)
    {
        $data = $parameter['data'];
        $webstoreId = $parameter['optionId'];

        $this->saveDebitSettings($webstoreId, $data);
        $this->saveDebitShippingCountrySettings($webstoreId, $data);

        //TODO Create container

        return true;
    }

    /**
     * @param int $webstoreId
     * @param array $data
     */
    private function saveDebitSettings($webstoreId, $data)
    {
        $webstore = $this->getWebstore($webstoreId);

        $settings = [
            'PID_' . $webstore->storeIdentifier => [
                'name'  => $data['name'],
                'logo_type' => $data['logo_type'],
                'logo_url'  => $data['logo_url'],
                'external_info_page'  => $data['external_info_page'],
                'internal_info_page' => $data['internal_info_page'],
                'info_page_type' => $data['info_page_type'],
                'webstore' => $webstoreId
            ]
        ];
        /** @var SettingsService $settingsService */
        $settingsService = pluginApp(SettingsService::class);
        $settingsService->saveSettings('debit', $settings);
    }

    /**
     * @param array $data
     */
    private function saveDebitShippingCountrySettings($webstoreId, $data)
    {
        $webstore = $this->getWebstore($webstoreId);

        $settings = [
            'plentyId' => $webstore->storeIdentifier,
            'countries' => $data['countries'],
        ];
        /** @var SettingsService $settingsService */
        $settingsService = pluginApp(SettingsService::class);
        $settingsService->saveShippingCountrySettings($settings);
    }

    /**
     * @param int $webstoreId
     * @return Webstore
     */
    private function getWebstore($webstoreId)
    {
        if ($this->webstore === null) {
            /** @var WebstoreRepositoryContract $webstoreRepository */
            $webstoreRepository = pluginApp(WebstoreRepositoryContract::class);
            $this->webstore = $webstoreRepository->findById($webstoreId);
        }

        return $this->webstore;
    }

}
