<?php //strict

namespace Debit\Helper;

use Debit\Services\SessionStorageService;
use Plenty\Modules\Account\Contact\Contracts\ContactPaymentRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\System\Models\WebstoreConfiguration;
use Plenty\Plugin\Log\Loggable;

/**
 * Class DebitHelper
 *
 * @package Debit\Helper
 */
class DebitHelper
{
    use Loggable;

    private $contactBank;

    /**
     * @var WebstoreConfiguration
     */
    private $webstoreConfig;

    /**
     * @var PaymentMethodRepositoryContract $paymentMethodRepository
     */
    private $paymentMethodRepository;

    /**
     * @var SessionStorageService $sessionStorageService
     */
    private $sessionStorageService;

    /**
     * DebitHelper constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository, SessionStorageService $sessionStorageService)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->sessionStorageService = $sessionStorageService;
    }

    /**
     * Create the ID of the payment method if it doesn't exist yet
     */
    public function createMopIfNotExists()
    {
        // Check whether the ID of the debit payment method has been created
        if($this->getDebitMopId() == 'no_paymentmethod_found')
        {
            $paymentMethodData = array( 'pluginKey' => 'plentyDebit',
                'paymentKey' => 'DEBIT',
                'name' => 'debit');

            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
        }
    }

    /**
     * Load the ID of the payment method for the given plugin key
     * Return the ID for the payment method
     *
     * @return string|int
     */
    public function getDebitMopId()
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plentyDebit');

        if( !is_null($paymentMethods) )
        {
            foreach($paymentMethods as $paymentMethod)
            {
                if($paymentMethod->paymentKey == 'DEBIT')
                {
                    return $paymentMethod->id;
                }
            }
        }

        return 'no_paymentmethod_found';
    }

    /**
     * Return the ID for the old debit payment method
     *
     * @return int
     */
    public function getOldDebitMopId()
    {
        return 3;
    }

    /**
     * Create a payment in plentymarkets
     *
     * @param int $orderId
     * @param ContactBank $contactBank
     * @return Payment|boolean
     */
    public function createPlentyPayment($orderId, $contactBank)
    {
        /** @var PaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(PaymentRepositoryContract::class);
        $orderPayment = $paymentRepo->getPaymentsByOrderId($orderId);
        if(isset($orderPayment) || count($orderPayment) > 0) {
            // There is already a payment assigned to the order so we don't need to create an other one.
            return false;
        }

        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(OrderRepositoryContract::class);
        try {
            $order = $orderRepository->findById($orderId);
        } catch (\Exception $exception) {
            //The order doesn't exists so we don't need to create an payment
            $this->getLogger('DEBIT')->error('Order not found', [
                'orderId' => $orderId
            ]);
            return false;
        }

        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);

        $payment->mopId             = $this->getDebitMopId();
        $payment->transactionType   = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status            = Payment::STATUS_APPROVED;
        $payment->unaccountable     = 1;
        $payment->regenerateHash    = true;
        $payment->amount            = $order->amount->invoiceTotal - $order->amount->giftCardAmount;
        $payment->currency          = $order->amount->currency;
        $payment->isSystemCurrency  = $order->amount->isSystemCurrency;

        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $orderId);
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_IBAN_OF_SENDER, $contactBank->iban);
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_BIC_OF_SENDER, $contactBank->bic);
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_NAME_OF_SENDER, $contactBank->accountOwner);
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_BANK_NAME_OF_SENDER, $contactBank->bankName);
        $paymentProperties[] = $this->createPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, 'ORDER '. $orderId. ' IBAN: '.$contactBank->iban.' SENDER: '.$contactBank->accountOwner);

        $payment->properties = $paymentProperties;

        /** @var PaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(PaymentRepositoryContract::class);
        $orderPayment = $paymentRepo->getPaymentsByOrderId($orderId);

        if ($orderPayment == null) {
            $orderPayment = $paymentRepo->createPayment($payment);
        }

        return $orderPayment;
    }

    /**
     * Assign the payment to an order in plentymarkets
     *
     * @param Payment   $payment
     * @param int       $orderId
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        /** @var OrderRepositoryContract $orderContract */
        $orderContract = pluginApp(OrderRepositoryContract::class);

        /** @var Order $order */
        // use processUnguarded to find orders for guests
        $order = $authHelper->processUnguarded(
            function () use ($orderContract, $orderId) {
                //unguarded
                return $orderContract->findOrderById($orderId, ['relation']);
            }
        );
        // Check whether the order truly exists in plentymarkets
        if(!is_null($order) && $order instanceof Order)
        {
            // Assign the given payment to the given order
            /** @var PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo */
            $paymentOrderRelationRepo = pluginApp(PaymentOrderRelationRepositoryContract::class);
            $paymentOrderRelationRepo->createOrderRelation($payment, $order);
        }
    }

    /**
     * Set orderId for ContactBank
     *
     * @param int $orderId
     * @return ContactBank $contactBank
     */
    public function updateContactBank($orderId)
    {
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        $contactBank = $this->sessionStorageService->getSessionValue('contactBank');
        $bankData = [
            'id'            => $contactBank->id,
            'orderId'       => $orderId,
            'accountOwner'  => $contactBank->accountOwner,
            'iban'          => $contactBank->iban,
            'bic'           => $contactBank->bic,
            'bankName'      => $contactBank->bankName,
            'lastUpdateBy'  => 'customer'
        ];


//We dont want to save the contactId to till the ui for the customer bankdata is updated

//        /** @var OrderRepositoryContract $orderContract */
//        $orderContract = pluginApp(OrderRepositoryContract::class);
//
//        /** @var Order $order */
//        // use processUnguarded to find orders for guests
//        $order = $authHelper->processUnguarded(
//            function () use ($orderContract, $orderId) {
//                //unguarded
//                return $orderContract->findOrderById($orderId, ['relation']);
//            }
//        );
//        // Check whether the order truly exists in plentymarkets
//        if(!is_null($order) && $order instanceof Order)
//        {
//            $contactId = $order->contactReceiver->id;
//            if($contactId>0){
//                $bankData['contactId'] = $contactId;
//            }
//        }

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);


        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['id']);
        });

        return $contactBank;
    }


    /*
     * Create a PaymentProperty
     *
     * @param int    $typeId
     * @param string $value
     *
     * @return PaymentProperty $paymentProperty
     */
    private function createPaymentProperty($typeId, $value)
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp( \Plenty\Modules\Payment\Models\PaymentProperty::class );
        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = $value;
        return $paymentProperty;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        $webstoreConfig = $this->getWebstoreConfig();

        $domain = $webstoreConfig->domainSsl;
        if (strpos($domain, 'master.plentymarkets') || $domain == 'http://dbmaster.plenty-showcase.de' || $domain == 'http://dbmaster-beta7.plentymarkets.eu' || $domain == 'http://dbmaster-stable7.plentymarkets.eu') {
            $domain = 'https://master.plentymarkets.com';
        }

        return $domain;
    }

    /**
     * @return WebstoreConfiguration
     */
    public function getWebstoreConfig()
    {
        if ($this->webstoreConfig === null) {
            /** @var WebstoreHelper $webstoreHelper */
            $webstoreHelper = pluginApp(WebstoreHelper::class);
            /** @var WebstoreConfiguration $webstoreConfig */
            $this->webstoreConfig = $webstoreHelper->getCurrentWebstoreConfiguration();
        }

        return $this->webstoreConfig;
    }
    
    public function logQueueDebit(array $logs, int $orderId)
    {
        foreach ($logs as $log) {
            if (isset($log['contactBank'])) {
                $replacement = '****';
                if (is_array($log['contactBank'])) {
                    foreach ($log['contactBank'] as $key => $value) {
                        if ($key == 'iban') {
                            $log['contactBank']['iban'] = $replacement;
                        } elseif ($key == 'bic') {
                            $log['contactBank']['bic'] = $replacement;
                        }
                    }
                } else {
                    if (isset($log['contactBank']->iban)) {
                        $log['contactBank']->iban = $replacement;
                    }
                    if (isset($log['contactBank']->bic)) {
                        $log['contactBank']->bic = $replacement;
                    }
                }
            }
        }
        $this->getLogger(PluginConstants::PLUGIN_NAME)
            ->addReference('orderId', $orderId)
            ->debug('Checked for Debit operations - Result', $logs);
    }
}
