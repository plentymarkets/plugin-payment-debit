<?php

namespace Debit\Assistants\SettingsHandlers;
use Debit\Helper\DebitHelper;
use Debit\Services\SettingsService;
use Plenty\Modules\Order\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;
use Plenty\Modules\Plugin\Contracts\PluginLayoutContainerRepositoryContract;
use Plenty\Modules\Plugin\Models\Plugin;


class DebitAssistantSettingsHandler implements WizardSettingsHandler
{
    /**
     * @var Webstore
     */
    private $webstore;

    /**
     * @var Plugin
     */
    private $debitPlugin;
    /**
     * @var Plugin
     */
    private $ceresPlugin;

    /**
     * @param array $parameter
     * @return bool
     */
    public function handle(array $parameter)
    {
        $data = $parameter['data'];
        $webstoreId = $data['config_name'];
        if ((!is_numeric($webstoreId) || $webstoreId < 0) && !$this->isValidUUIDv4($parameter['optionId'])) {
            $webstoreId = $parameter['optionId'];
        }

        $this->saveDebitSettings($webstoreId, $data);
        $this->saveDebitShippingCountrySettings($webstoreId, $data);
        $this->createContainer($webstoreId, $data);
        $this->activateLegacyPaymentMethod();

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
                'logo_type_external' => $data['logo_type_external'],
                'logo_url'  => $data['logo_url'],
                'info_page_toggle' => $data['info_page_toggle'],
                'external_info_page'  => $data['external_info_page'],
                'internal_info_page' => $data['internal_info_page'],
                'info_page_type' => $data['info_page_type'],
                'allowDebitForGuest' => $data['allowDebitForGuest']
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

    /**
     * @param int $webstoreId
     * @param array $data
     */
    private function createContainer($webstoreId, $data)
    {
        $webstore = $this->getWebstore($webstoreId);
        $debitPlugin = $this->getDebitPlugin($webstoreId);
        $ceresPlugin = $this->getCeresPlugin($webstoreId);

        if( ($webstore && $webstore->pluginSetId) &&  $debitPlugin !== null && $ceresPlugin !== null) {
            /** @var PluginLayoutContainerRepositoryContract $pluginLayoutContainerRepo */
            $pluginLayoutContainerRepo = pluginApp(PluginLayoutContainerRepositoryContract::class);

            $containerListEntries = [];

            // Default entries
            $containerListEntries[] = $this->createContainerDataListEntry(
                $webstoreId,
                'Ceres::Script.AfterScriptsLoaded',
                'Debit\Providers\DataProvider\DebitReinitializePaymentScript'
            );

            $containerListEntries[] = $this->createContainerDataListEntry(
                $webstoreId,
                'Ceres::MyAccount.OrderHistoryPaymentInformation',
                'Debit\Providers\DataProvider\DebitReinitializePayment'
            );

            $containerListEntries[] = $this->createContainerDataListEntry(
                $webstoreId,
                'Ceres::OrderConfirmation.AdditionalPaymentInformation',
                'Debit\Providers\DataProvider\DebitReinitializePayment'
            );

            if (isset($data['debitPaymentMethodIcon']) && $data['debitPaymentMethodIcon']) {
                $containerListEntries[] = $this->createContainerDataListEntry(
                    $webstoreId,
                    'Ceres::Homepage.PaymentMethods',
                    'Debit\Providers\Icon\IconProvider'
                );
            } else {
                $pluginLayoutContainerRepo->removeOne(
                    $webstore->pluginSetId,
                    'Ceres::Homepage.PaymentMethods',
                    'Debit\Providers\Icon\IconProvider',
                    $ceresPlugin->id,
                    $debitPlugin->id
                );
            }

            $pluginLayoutContainerRepo->addNew($containerListEntries, $webstore->pluginSetId);
        }
    }

    /**
     * @param int $webstoreId
     * @param string $containerKey
     * @param string $dataProviderKey
     * @return array
     */
    private function createContainerDataListEntry($webstoreId, $containerKey, $dataProviderKey)
    {
        $webstore = $this->getWebstore($webstoreId);
        $debitPlugin = $this->getDebitPlugin($webstoreId);
        $ceresPlugin = $this->getCeresPlugin($webstoreId);

        $dataListEntry = [];

        $dataListEntry['containerKey'] = $containerKey;
        $dataListEntry['dataProviderKey'] = $dataProviderKey;
        $dataListEntry['dataProviderPluginId'] = $debitPlugin->id;
        $dataListEntry['containerPluginId'] = $ceresPlugin->id;
        $dataListEntry['pluginSetId'] = $webstore->pluginSetId;
        $dataListEntry['dataProviderPluginSetEntryId'] = $debitPlugin->pluginSetEntries->firstWhere('pluginSetId', $webstore->pluginSetId)->id;
        $dataListEntry['containerPluginSetEntryId'] = $ceresPlugin->pluginSetEntries->firstWhere('pluginSetId', $webstore->pluginSetId)->id;

        return $dataListEntry;
    }

    /**
     * @param int $webstoreId
     * @return Plugin
     */
    private function getCeresPlugin($webstoreId)
    {
        if ($this->ceresPlugin === null) {
            $webstore = $this->getWebstore($webstoreId);
            $pluginSet = $webstore->pluginSet;
            $plugins = $pluginSet->plugins();
            $this->ceresPlugin = $plugins->where('name', 'Ceres')->first();
        }

        return $this->ceresPlugin;
    }

    /**
     * @param int $webstoreId
     * @return Plugin
     */
    private function getDebitPlugin($webstoreId)
    {
        if ($this->debitPlugin === null) {
            $webstore = $this->getWebstore($webstoreId);
            $pluginSet = $webstore->pluginSet;
            $plugins = $pluginSet->plugins();
            $this->debitPlugin = $plugins->where('name', 'Debit')->first();
        }

        return $this->debitPlugin;
    }

    /**
     * Check if a string is a valid UUID.
     *
     * @param string $string
     * @return false|int
     */
    public static function isValidUUIDv4($string)
    {
        $regex = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
        return preg_match($regex, $string);
    }

    /**
     * Activate the legacy payment method. This is needed to use the debit payment method.
     */
    private function activateLegacyPaymentMethod()
    {
        /** @var DebitHelper $debitHelper */
        $debitHelper = pluginApp(DebitHelper::class);
        /** @var PaymentMethodRepositoryContract $paymentMethodRepository */
        $paymentMethodRepository = pluginApp(PaymentMethodRepositoryContract::class);

        $paymentMethodRepository->activatePaymentMethod($debitHelper->getDebitMopId());
    }
}
