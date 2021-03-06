<?php //strict

namespace Debit\Providers;

use Debit\Assistants\DebitAssistant;
use Debit\Extensions\DebitTwigServiceProvider;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Plenty\Plugin\ServiceProvider;
use Debit\Helper\DebitHelper;
use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Plugin\Events\Dispatcher;

use Debit\Methods\DebitPaymentMethod;

use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;

/**
 * Class DebitServiceProvider
 * @package Debit\Providers
 */
class DebitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->getApplication()->register(DebitRouteServiceProvider::class);
    }

    /**
     * Boot additional services for the payment method
     *
     * @param Twig $twig
     * @param DebitHelper $paymentHelper
     * @param PaymentMethodContainer $payContainer
     * @param Dispatcher $eventDispatcher
     * @param WizardContainerContract $wizardContainerContract
     */
    public function boot(  Twig $twig,
                           DebitHelper $paymentHelper,
                           PaymentMethodContainer $payContainer,
                           Dispatcher $eventDispatcher,
                           WizardContainerContract $wizardContainerContract)
    {
        // Create the ID of the payment method if it doesn't exist yet
        $paymentHelper->createMopIfNotExists();

        $twig->addExtension(DebitTwigServiceProvider::class);

        // Register the debit payment method in the payment method container
        $payContainer->register('plentyDebit::DEBIT', DebitPaymentMethod::class,
            [ AfterBasketChanged::class, AfterBasketItemAdd::class, AfterBasketCreate::class ]
        );

        $wizardContainerContract->register('payment-debit-assistant', DebitAssistant::class);

        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(GetPaymentMethodContent::class,
            function(GetPaymentMethodContent $event) use( $paymentHelper, $twig)
            {
                if($event->getMop() == $paymentHelper->getDebitMopId())
                {
                    $bankAccount = array();

                    /** @var BasketRepositoryContract $basketRepo */
                    $basketRepo = pluginApp(BasketRepositoryContract::class);
                    $basket = $basketRepo->load();

                    if($basket->customerId > 0) {
                        /** @var ContactRepositoryContract $contactRepository */
                        $contactRepository = pluginApp(ContactRepositoryContract::class);
                        $contact = $contactRepository->findContactById($basket->customerId);

                        $bank = $contact->banks->last();
                        if($bank instanceof ContactBank) {
                            $bankAccount['bankAccountOwner'] =  $bank->accountOwner;
                            $bankAccount['bankName']         =	$bank->bankName;
                            $bankAccount['bankIban']	     =	$bank->iban;
                            $bankAccount['bankBic']		 =	$bank->bic;
                        }
                    }

                    $event->setValue($twig->render('Debit::BankDetailsOverlay', [
                        "checkout"          => "true",
                        "action"            => "/rest/payment/debit/bankdetails",
                        "bankAccountOwner"  => $bankAccount['bankAccountOwner'],
                        "bankName"          => $bankAccount['bankName'],
                        "bankIban"          => $bankAccount['bankIban'],
                        "bankBic"           => $bankAccount['bankBic']
                    ]));
                    $event->setType('htmlContent');
                }
            });

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function(ExecutePayment $event) use( $paymentHelper)
            {
                if($event->getMop() == $paymentHelper->getDebitMopId())
                {
                    // Save orderId to ContactBank
                   $contactBank = $paymentHelper->updateContactBank($event->getOrderId());

                    // Create a plentymarkets payment
                    $plentyPayment = $paymentHelper->createPlentyPayment($event->getOrderId(), $contactBank);

                    if($plentyPayment instanceof Payment) {
                        // Assign the payment to an order in plentymarkets
                        $paymentHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $event->getOrderId());
                        $event->setType('success');
                        $event->setValue('');
                    }
                }
            });
    }
}
