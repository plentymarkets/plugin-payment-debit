<?php

namespace Debit\Providers;

use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Templates\Twig;

use Debit\Helper\DebitHelper;
use Debit\Services\SessionStorageService;
use Debit\Services\SettingsService;
/**
 * Class DebitOrderConfirmationDataProvider
 * @package Debit\Providers
 */
class DebitOrderConfirmationDataProvider
{
    /**
     * @param Twig $twig
     * @param SettingsService $settings
     * @param DebitHelper $debitHelper
     * @param SessionStorageService $service
     * @param array $args
     * @return string
     */
    public function call(   Twig $twig,
                            SettingsService $settings,
                            DebitHelper $debitHelper,
                            SessionStorageService $service,
                            $arg)
    {
        $mop = $service->getOrderMopId();

        $content = '';

        /*
         * Load the method of payment id from the order
         */
        $order = $arg[0];
        if($order instanceof Order) {
            foreach ($order->properties as $property) {
                if($property->typeId == 3) {
                    $mop = $property->value;
                    break;
                }
            }
        }elseif(is_array($order)) {
            foreach ($order['properties'] as $property) {
                if($property['typeId'] == 3) {
                    $mop = $property['value'];
                    break;
                }
            }
        }

        if($mop == $debitHelper->getDebitMopId())
        {
            $content .= $twig->render('Debit::DebitBankDetailsButton');
        }

        return $content;
    }
}