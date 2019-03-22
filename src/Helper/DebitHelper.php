<?php //strict

namespace Debit\Helper;

use Debit\Services\SessionStorageService;
use Plenty\Modules\Account\Contact\Contracts\ContactPaymentRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Method\Models\PaymentMethod;
use Plenty\Modules\Payment\Models\Payment;

/**
 * Class DebitHelper
 *
 * @package Debit\Helper
 */
class DebitHelper
{
    private $contactBank;

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
            $paymentMethodData = array( 'pluginKey' => 'plenty',
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
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plenty');

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
     * Create a payment in plentymarkets
     *
     * @param array $paypalPaymentData
     * @param array $paymentData
     * @return Payment
     */
    public function createPlentyPayment()
    {
        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);

        $payment->mopId             = $this->getDebitMopId();
        $payment->transactionType   = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status            = Payment::STATUS_APPROVED;
        $payment->unaccountable     = 1;
        $payment->regenerateHash    = true;

        /** @var PaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(PaymentRepositoryContract::class);
        $payment = $paymentRepo->createPayment($payment);

        return $payment;
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
     * @param int $contactBankId
     */
    public function setContactBank($contactBankId)
    {
        $this->sessionStorageService->setSessionValue('contactBankId', $contactBankId);
    }

    /**
     * @return ContactBank $contactBank
     */
    public function getContactBank()
    {
        return $this->contactBank;
    }

    /**
     * Set orderId for ContactBank
     *
     * @param int $orderId
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
            'lastUpdateBy'  => 'customer'
        ];

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['id']);
        });
    }
}
