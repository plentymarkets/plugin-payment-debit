<?php

namespace Debit\Methods;

use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;

/**
 * Class DebitPaymentMethod
 * @package Debit\Methods
 */
class DebitPaymentMethod extends PaymentMethodService
{
    /**
     * Check the configuration if the payment method is active
     * Return true if the payment method is active, else return false
     *
     * @param ConfigRepository $configRepository
     * @param BasketRepositoryContract $basketRepositoryContract
     * @return bool
     */
    public function isActive( ConfigRepository $configRepository,
                              BasketRepositoryContract $basketRepositoryContract):bool
    {
        /** @var bool $active */
        $active = true;

        /** @var Basket $basket */
        $basket = $basketRepositoryContract->load();

        /**
         * Check the shipping profile ID. The ID can be entered in the config.json.
         */
        if( $configRepository->get('Debit.shippingProfileId') != $basket->shippingProfileId)
        {
            $active = false;
        }

        return $active;
    }

    /**
     * Get the name of the payment method. The name can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getName( ConfigRepository $configRepository ):string
    {
        $name = $configRepository->get('Debit.name');

        if(!strlen($name))
        {
            $name = 'Debit';
        }

        return $name;

    }

    /**
     * Get the path of the icon. The URL can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getIcon( ConfigRepository $configRepository ):string
    {
        if($configRepository->get('Debit.logo') == 1)
        {
            return $configRepository->get('Debit.logo.url');
        }
        return '';
    }

    /**
     * Get the description of the payment method. The description can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getDescription( ConfigRepository $configRepository ):string
    {
        if($configRepository->get('Debit.infoPage.type') == 1)
        {
            return $configRepository->get('Debit.infoPage.intern');
        }
        elseif ($configRepository->get('Debit.infoPage.type') == 2)
        {
            return $configRepository->get('Debit.infoPage.extern');
        }
        else
        {
            return '';
        }
    }
}