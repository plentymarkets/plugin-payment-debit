<?php

namespace Debit\Methods;

use Debit\Helper\DebitHelper;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;
use Plenty\Modules\Account\Contact\Models\ContactAllowedMethodOfPayment;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Category\Contracts\CategoryRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Application;
use Debit\Services\SettingsService;
use Plenty\Plugin\Translation\Translator;

/**
 * Class DebitPaymentMethod
 * @package Debit\Methods
 */
class DebitPaymentMethod extends PaymentMethodService
{
    /** @var BasketRepositoryContract */
    private $basketRepo;

    /** @var  SettingsService */
    private $settings;

    /** @var  Checkout */
    private $checkout;

    /** @var AccountService */
    protected $accountService;

    /** @var DebitHelper */
    protected $debitHelper;

    /** @var Translator */
    protected $translator;

    /**
     * DebitPaymentMethod constructor.
     * @param BasketRepositoryContract   $basketRepo
     * @param SettingsService            $service
     * @param Checkout                   $checkout
     * @param AccountService             $accountService
     * @param DebitHelper                $debitHelper
     * @param Translator                 $translator
     */
    public function __construct(  BasketRepositoryContract    $basketRepo,
                                  SettingsService             $service,
                                  Checkout                    $checkout,
                                  AccountService              $accountService,
                                  DebitHelper                 $debitHelper,
                                  Translator                  $translator)
    {
        $this->basketRepo     = $basketRepo;
        $this->settings       = $service;
        $this->checkout       = $checkout;
        $this->accountService = $accountService;
        $this->debitHelper    = $debitHelper;
        $this->translator     = $translator;
    }

    /**
     * Check whether Debit is active or not
     *
     * @return bool
     */
    public function isActive()
    {
        /** @var Basket $basket */
        $basket = $this->basketRepo->load();
        if($this->accountService->getIsAccountLoggedIn() && $basket->customerId > 0) {
            /** @var ContactRepositoryContract $contactRepository */
            $contactRepository = pluginApp(ContactRepositoryContract::class);
            $contact = $contactRepository->findContactById($basket->customerId);
            if(!is_null($contact) && $contact instanceof Contact) {
                $allowed = $contact->allowedMethodsOfPayment->first(function($method) {
                    if($method instanceof ContactAllowedMethodOfPayment) {
                        if($method->methodOfPaymentId == $this->debitHelper->getDebitMopId() && $method->allowed
                            || $method->methodOfPaymentId == $this->debitHelper->getOldDebitMopId() && $method->allowed) {
                            return true;
                        }
                    }
                });
                if($allowed) {
                    return true;
                }
            }
        }

        /**
         * Check whether the user is logged in
         */
        if( !$this->settings->getSetting('allowDebitForGuest') && !$this->accountService->getIsAccountLoggedIn())
        {
            return false;
        }


        if(!in_array($this->checkout->getShippingCountryId(), $this->settings->getShippingCountries()))
        {
            return false;
        }

        return true;
    }

    /**
     * Get DebitSourceUrl
     *
     * @return string
     */
    public function getSourceUrl()
    {
        if ($this->settings->getSetting('info_page_toggle')) {
            $infoPageType = $this->settings->getSetting('info_page_type');

            switch ($infoPageType)
            {
                case 'internal':
                    $categoryId = (int) $this->settings->getSetting('internal_info_page');
                    if($categoryId  > 0)
                    {
                        /** @var CategoryRepositoryContract $categoryContract */
                        $categoryContract = pluginApp(CategoryRepositoryContract::class);
                        return $categoryContract->getUrl($categoryId, $this->getLanguage());
                    }
                    return '';
                case 'external':
                    return $this->settings->getSetting('external_info_page');
            }
        }

        return '';
    }

    /**
     * Get Debit Icon
     *
     * @return string
     */
    public function getIcon( )
    {
        if(!$this->settings->getSetting('logo_type_external'))
        {
            $lang = $this->getLanguage();

            $app = pluginApp(Application::class);
            if ($lang == 'de') {
                $icon = $app->getUrlPath('debit').'/images/icon.png';
            } else {
                $icon = $app->getUrlPath('debit').'/images/icon_en.png';
            }

            return $icon;
        }
        else
        {
            return $this->settings->getSetting('logo_url');
        }
    }

    /**
     * Get shown name
     *
     * @return string
     */
    public function getName()
    {
        return $this->translator->trans("Debit::PaymentMethod.paymentMethodName");
    }

    /**
     * Get the description of the payment method.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->translator->trans("Debit::PaymentMethod.paymentMethodDescription");
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @return bool
     */
    public function isSwitchableTo()
    {
        return false;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @return bool
     */
    public function isSwitchableFrom()
    {
        return false;
    }

    /**
     * Get the actual frontend language
     *
     * @return string
     */
    private function getLanguage()
    {
        /** @var FrontendSessionStorageFactoryContract $session */
        $session = pluginApp(FrontendSessionStorageFactoryContract::class);
        return $session->getLocaleSettings()->language;
    }

    /**
     * Check if this payment method should be searchable in the backend
     *
     * @return bool
     */
    public function isBackendSearchable():bool
    {
        return true;
    }

    /**
     * Check if this payment method should be active in the backend
     *
     * @return bool
     */
    public function isBackendActive():bool
    {
        return true;
    }

    /**
     * Get the name for the backend
     *
     * @param $lang
     * @return string
     */
    public function getBackendName($lang) : string
    {
        return $this->translator->trans('Debit::PaymentMethod.paymentMethodName',[],$lang);
    }
}
