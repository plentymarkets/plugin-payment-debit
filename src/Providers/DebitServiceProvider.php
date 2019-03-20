<?php //strict

namespace Debit\Providers;

use Debit\Wizards\DebitWizard;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Plenty\Plugin\ServiceProvider;
use Debit\Helper\DebitHelper;
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
      * @param DebitHelper $paymentHelper
      * @param PaymentMethodContainer $payContainer
      * @param Dispatcher $eventDispatcher
      * @param WizardContainerContract $wizardContainerContract
      */
     public function boot(  DebitHelper $paymentHelper,
                            PaymentMethodContainer $payContainer,
                            Dispatcher $eventDispatcher,
                            WizardContainerContract $wizardContainerContract)
     {

         // Register the debit payment method in the payment method container
         $payContainer->register('plenty::DEBIT', DebitPaymentMethod::class,
                                [ AfterBasketChanged::class, AfterBasketItemAdd::class, AfterBasketCreate::class ]
         );

         $wizardContainerContract->register('payment-debit-wizard', DebitWizard::class);

         // Listen for the event that gets the payment method content
         $eventDispatcher->listen(GetPaymentMethodContent::class,
                 function(GetPaymentMethodContent $event) use( $paymentHelper)
                 {
                     if($event->getMop() == $paymentHelper->getDebitMopId())
                     {
                         //Kaufen Button --> Overlay zur Eingabe der Kontodaten
                         $event->setValue('');
                         $event->setType('continue');
                     }
                 });

         // Listen for the event that executes the payment
         $eventDispatcher->listen(ExecutePayment::class,
             function(ExecutePayment $event) use( $paymentHelper)
             {
                 if($event->getMop() == $paymentHelper->getDebitMopId())
                 {
                     $event->setValue('<h1>Lastschriftkauf<h1>');
                     $event->setType('htmlContent');
                 }
             });
     }
 }
