<?php

namespace Debit\Methods;

use Debit\Helper\DebitHelper;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;
use Plenty\Modules\Account\Contact\Models\ContactAllowedMethodOfPayment;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Payment\Method\Services\PaymentMethodBaseService;
use Plenty\Plugin\Application;
use Debit\Services\SettingsService;
use Plenty\Plugin\Translation\Translator;
use Plenty\Modules\Webshop\Contracts\UrlBuilderRepositoryContract;

/**
 * Class DebitPaymentMethod
 * @package Debit\Methods
 */
class DebitPaymentMethod extends PaymentMethodBaseService
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
     * @throws \Plenty\Exceptions\ValidationException
     */
    public function isActive(): bool
    {
        /** @var Basket $basket */
        $basket = $this->basketRepo->load();

        if (!$this->isGuest($basket->customerId)) {

            /** @var ContactRepositoryContract $contactRepository */
            $contactRepository = pluginApp(ContactRepositoryContract::class);
            $contact = $contactRepository->findContactById($basket->customerId);

            if (!$this->hasActiveShippingCountry()) {
                if (!$this->isExplicitlyAllowedForThisCustomer($contact)) {
                    return false;
                }
            }

        } else {

            if (!$this->hasActiveShippingCountry()) {
                return false;
            }

            if(!$this->settings->getSetting('allowDebitForGuest') && $this->isGuest($basket->customerId)) {
                return false;
            }

        }

        return true;
    }

    /**
     * Get DebitSourceUrl
     *
     * @param string $lang
     * @return string
     */
    public function getSourceUrl(string $lang = 'de'): string
    {
        if ($this->settings->getSetting('info_page_toggle')) {

            $lang = $this->getLanguage();
            
            $infoPageType = $this->settings->getSetting('info_page_type');

            switch ($infoPageType)
            {
                case 'internal':
                    $categoryId = (int) $this->settings->getSetting('internal_info_page');
                    if($categoryId  > 0)
                    {
                        /** @var DebitHelper $debitHelper */
                        $debitHelper = pluginApp(DebitHelper::class);
                        $urlBuilderRepository = pluginApp(UrlBuilderRepositoryContract::class);

                        $urlQuery = $urlBuilderRepository->buildCategoryUrl($categoryId, $lang);

                        $defaultLanguage = $debitHelper->getWebstoreConfig()->defaultLanguage;
                        $includeLanguage = false;
                        if ($lang != $defaultLanguage) {
                            $includeLanguage = true;
                        }

                        return $debitHelper->getDomain() . $urlQuery->toRelativeUrl($includeLanguage);
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
     * @param string $lang
     * @return string
     */
    public function getIcon(string $lang = 'de'): string
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
     * @param string $lang
     * @return string
     */
    public function getName(string $lang = 'de'): string
    {
        return $this->translator->trans("Debit::PaymentMethod.paymentMethodName");
    }

    /**
     * Get the description of the payment method.
     *
     * @param string $lang
     * @return string
     */
    public function getDescription(string $lang = 'de'): string
    {
        return $this->translator->trans("Debit::PaymentMethod.paymentMethodDescription");
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @param int|null $orderId
     * @return bool
     * @throws \Plenty\Exceptions\ValidationException
     */
    public function isSwitchableTo(int $orderId = null):bool
    {
        if(!is_null($orderId) && $orderId > 0) {

            try {

                /** @var OrderRepositoryContract $orderRepo */
                $orderRepo = pluginApp(OrderRepositoryContract::class);
                $filters = $orderRepo->getFilters();
                $filters['addOrderItems'] = false;
                $orderRepo->setFilters($filters);

                $order = $orderRepo->findOrderById($orderId, ['amounts', 'contactReceiver']);

                $customerId = $order->contactReceiver !== null ? $order->contactReceiver->id : 0;

                if (!$this->isGuest($customerId)) {

                    /** @var ContactRepositoryContract $contactRepository */
                    $contactRepository = pluginApp(ContactRepositoryContract::class);
                    $contact = $contactRepository->findContactById($customerId);

                    if (!$this->hasActiveShippingCountry()) {
                        if (!$this->isExplicitlyAllowedForThisCustomer($contact)) {
                            return false;
                        }
                    }

                }

            } catch(\Exception $e) {}

        } else {

            try {

                $basketRepositoryContract = pluginApp(BasketRepositoryContract::class);

                /** @var Basket $basket */
                $basket = $basketRepositoryContract->load();

                if (!$this->isGuest($basket->customerId)) {

                    /** @var ContactRepositoryContract $contactRepository */
                    $contactRepository = pluginApp(ContactRepositoryContract::class);
                    $contact = $contactRepository->findContactById($basket->customerId);

                    if (!$this->hasActiveShippingCountry()) {
                        if (!$this->isExplicitlyAllowedForThisCustomer($contact)) {
                            return false;
                        }
                    }

                } else {

                    if (!$this->hasActiveShippingCountry()) {
                        return false;
                    }

                    if( !$this->settings->getSetting('allowDebitForGuest') && $this->isGuest($basket->customerId)) {
                        return false;
                    }

                }

            } catch(\Exception $e) {}

        }

        return true;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @return bool
     */
    public function isSwitchableFrom(): bool
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
     * @param string $lang
     * @return string
     */
    public function getBackendName(string $lang = 'de'):string
    {
        return $this->translator->trans('Debit::PaymentMethod.paymentMethodName',[],$lang);
    }

    /**
     * Check if this payment method can handle subscriptions
     *
     * @return bool
     */
    public function canHandleSubscriptions():bool
    {
        return true;
    }

    /**
     * Get the url for the backend icon
     *
     * @return string
     */
    public function getBackendIcon(): string
    {
        $app = pluginApp(Application::class);
        $icon = $app->getUrlPath('debit').'/images/logos/debit_backend_icon.svg';
        return $icon;
    }

    /**
     * @param int $customerId
     * @return bool
     */
    private function isGuest($customerId)
    {
        return !$this->accountService->getIsAccountLoggedIn() || $customerId <= 0;
    }

    /**
     * @param Contact $contact
     * @return bool
     */
    private function isExplicitlyAllowedForThisCustomer(Contact $contact = null)
    {
        if (is_null($contact)) {
            return false;
        }

        $allowed = $contact->allowedMethodsOfPayment->first(function ($method) {
            if ($method instanceof ContactAllowedMethodOfPayment) {
                if ($method->methodOfPaymentId == $this->debitHelper->getDebitMopId() && $method->allowed
                    || $method->methodOfPaymentId == $this->debitHelper->getOldDebitMopId() && $method->allowed) {
                    return true;
                }
            }
        });

        return $allowed ? true : false;

    }

    /**
     * @return bool
     * @throws \Plenty\Exceptions\ValidationException
     */
    private function hasActiveShippingCountry()
    {
        if (empty($this->settings->getShippingCountries()) || !in_array($this->checkout->getShippingCountryId(), $this->settings->getShippingCountries())) {
            return false;
        } else {
            return true;
        }

    }


}
