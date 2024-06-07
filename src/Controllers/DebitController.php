<?php

namespace Debit\Controllers;

use Debit\Helper\DebitHelper;
use Debit\Helper\PluginConstants;
use Debit\Services\SessionStorageService;
use Plenty\Modules\Account\Contact\Contracts\ContactPaymentRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\ContactBank;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Log\Loggable;

class DebitController extends Controller
{
    use Loggable;
    /**
     * @var SessionStorageService $sessionStorageService
     */
    private $sessionStorageService;
    /**
     * @var AccountService $accountService
     */
    private $accountService;
    /**
     * @var DebitHelper $debitHelper
     */
    private $debitHelper;

    public function __construct(
        AccountService $accountService,
        SessionStorageService $sessionStorageService,
        DebitHelper $debitHelper
    ) {
        $this->sessionStorageService = $sessionStorageService;
        $this->accountService = $accountService;
        $this->debitHelper = $debitHelper;
    }

    public function getBankDetails( Twig $twig, $orderId)
    {
        $logs = [];
        $logs['step0-GetBankDetailsDesc'] = 'Get bank details for order';
        $logs['step0-GetBankDetailsData']['orderId'] = $orderId;

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $orderId) {
            return $paymentRepo->getBankByOrderId($orderId);
        });
        $logs['step01-FoundBankDetailsDesc'] = 'Contact bank for orderId ';
        $logs['step01-FoundBankDetailsData']['orderId'] = $orderId;
        $logs['step01-FoundBankDetailsData']['contactBank'] = $contactBank;

        if (is_null($contactBank)) {
            $logs['step02-NotFoundContactBankDesc'] = 'Contact bank for orderId not found. Search for contact bank without orderId';
            $logs['step02-NotFoundContactBankData']['orderId'] = $orderId;
            $accountContactId = $this->accountService->getAccountContactId();
            if($accountContactId>0) {
                $logs['step03-SearchByContactIdDesc'] = 'Search bank details by contactId';
                $logs['step03-SearchByContactIdData']['orderId'] = $orderId;
                $logs['step03-SearchByContactIdData']['accountContactId'] = $accountContactId;
                /** @var ContactRepositoryContract $contactRepository */
                $contactRepository = pluginApp(ContactRepositoryContract::class);
                $contact = $authHelper->processUnguarded(function () use ($contactRepository, $accountContactId) {
                    return $contactRepository->findContactById($accountContactId);
                });

                $bank = $contact->banks->last();
                if($bank instanceof ContactBank)
                {
                    $logs['step04-LastBankForContactDesc'] = 'Use last bank of found contact';
                    $logs['step04-LastBankForContactData']['orderId'] = $orderId;

                    $bankAccount['bankAccountOwner'] =  $bank->accountOwner;
                    $bankAccount['bankName']         =	$bank->bankName;
                    $bankAccount['bankIban']	     =	$bank->iban;
                    $bankAccount['bankBic']		     =	$bank->bic;
                    $bankAccount['bankId']		     =	0;
                    $bankAccount['debitMandate']	 =	'';
                    $logs['step04-LastBankForContactData']['contactBank'] = $bankAccount;
                }
            }
        } else {
            $logs['step04-LastBankForContactDesc'] = 'Use bank details from order constact';
            $logs['step04-LastBankForContactData']['orderId'] = $orderId;


            $bankAccount['bankAccountOwner'] =  $contactBank->accountOwner;
            $bankAccount['bankName']         =	$contactBank->bankName;
            $bankAccount['bankIban']	     =	$contactBank->iban;
            $bankAccount['bankBic']		     =	$contactBank->bic;
            $bankAccount['bankId']		     =	$contactBank->id;
            $bankAccount['debitMandate']	 =	($contactBank->directDebitMandateAvailable ? 'checked' : '');
            $logs['step04-LastBankForContactData']['contactBank'] = $bankAccount;

        }

        $logs['step05-RedenderBankDetailsDesc'] = 'Render successfully BankDetailsOverlay';
        $logs['step05-LastBankForContactData']['contactBank'] = $bankAccount;
        $logs['step05-LastBankForContactData']['orderId'] = $orderId;
        //$this->getLogger(PluginConstants::PLUGIN_NAME)->error('Render successfully BankDetailsOverlay', $bankAccount);

        $this->debitHelper->logQueueDebit($logs, $orderId);
        return $twig->render('Debit::BankDetailsOverlay', [
            "action"            => "/rest/payment/debit/updateBankDetails",
            "bankAccountOwner"  => $bankAccount['bankAccountOwner'],
            "bankName"          => $bankAccount['bankName'],
            "bankIban"          => $bankAccount['bankIban'],
            "bankBic"           => $bankAccount['bankBic'],
            "bankId"            => $bankAccount['bankId'],
            "debitMandate"      => ($bankAccount['debitMandate'] ? 'checked' : ''),
            "orderId"           => $orderId,
        ]);
    }


    /*
     *
     * @return BaseResponse
     */
    public function setBankDetails(Response $response, Request $request)
    {
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Set bank details', $request);
        /** @var BasketRepositoryContract $basketRepo */
        $basketRepo = pluginApp(BasketRepositoryContract::class);
        $basket = $basketRepo->load();

        $bankData = [
            'contactId'     => $basket->customerId,
            'accountOwner'  => $request->get('bankAccountOwner'),
            'bankName'      => $request->get('bankName'),
            'iban'          => $request->get('bankIban'),
            'bic'           => $request->get('bankBic'),
            'lastUpdateBy'  => 'customer'
        ];

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Current used bank data ', $bankData);
        try
        {
            //check if this contactBank already exist
            $contactBankExists = false;
            if ($bankData['contactId'] != NULL) {
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Check if this contactBank already exist on user ', $bankData['contactId']);
                /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
                $authHelper = pluginApp(AuthHelper::class);

                /** @var ContactPaymentRepositoryContract $paymentRepo */
                $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);
                $contactBankExists = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
                    $contactBanks = $paymentRepo->getBanksOfContact($bankData['contactId'], ['contactId', 'accountOwner', 'bankName', 'iban', 'bic']);
                    foreach ($contactBanks as $contactBank) {
                        if ($contactBank->contactId == $bankData['contactId']
                            && $contactBank->accountOwner == $bankData['accountOwner']
                            && $contactBank->bankName == $bankData['bankName']
                            && $contactBank->iban == $bankData['iban']
                            && $contactBank->bic == $bankData['bic']
                        ) {
                            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank was found on contact', $contactBank);
                            return true;
                        }
                    }
                    $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank was Not found', $bankData);
                    return false;
                });
            }

            $bankData['lastUpdateBy'] = 'customer';
            if (!$contactBankExists) {
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank doesn\'t exist. Contact bank will be created', $bankData);
                /** @var ContactBank $newContactBank */
                $newContactBank = $this->createContactBank($bankData);
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error('New contact bank was created ', $newContactBank);
            }
            $bankData['contactId'] = null;
            $bankData['directDebitMandateAvailable'] = 1;
            $bankData['directDebitMandateAt'] = date('Y-m-d H:i:s');
            $bankData['paymentMethod'] = 'onOff';
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create contact bank with empty contactId ', $bankData);
            $contactBank = $this->createContactBank($bankData);
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank with empty contactId was created', $bankData);

            $this->sessionStorageService->setSessionValue('contactBank', $contactBank);

            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('place-order');
            return $response->redirectTo($this->sessionStorageService->getLang() . '/place-order');
        }
        catch(\Exception $e)
        {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Exception in contact bank processing', $e->getMessage());
            return $response->redirectTo($this->sessionStorageService->getLang().'/checkout');
        }
    }

    /*
     *
     * @return BaseResponse
     */
    public function updateBankDetails(Response $response, Request $request)
    {
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('UpdateBankDetails', $request);
        $orderId = $request->get('orderId');
        $bankData = [
            'accountOwner'  => $request->get('bankAccountOwner'),
            'bankName'      => $request->get('bankName'),
            'iban'          => $request->get('bankIban'),
            'bic'           => $request->get('bankBic'),
            'lastUpdateBy'  => 'customer',
            'orderId'       => $orderId
        ];
        if ($request->has('bankId') && $request->get('bankId') > 0) {
            //update existing bank account
            $bankData['bankId'] = $request->get('bankId');
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Update existing bank account', $bankData);
            $contactBank = $this->updateContactBank($bankData);
        } else {
            //create new bank account
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create bank account', $bankData);
            $contactBank = $this->createContactBank($bankData);
        }

        /** @var DebitHelper $debitHelper */
        $debitHelper = pluginApp(DebitHelper::class);
        // Create a plentymarkets payment
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create Plenty payment', $contactBank);
        $plentyPayment = $debitHelper->createPlentyPayment($orderId, $contactBank);



        if($plentyPayment instanceof Payment) {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Created Plenty payment', $plentyPayment);
            // Assign the payment to an order in plentymarkets
            $debitHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Assigned Plenty payment to orderId', $orderId);
        }

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Success, go to confirmation', $orderId);
        return $response->redirectTo($this->sessionStorageService->getLang().'/confirmation/'.$orderId);
    }


    /**
     * @param $bankData
     * @return mixed
     */
    private function createContactBank($bankData) {

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Started createContactBank', $bankData);
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Create contact bank', $bankData);
            /** @var ContactBank $newContactBank */
            $newContactBank = $paymentRepo->createContactBank($bankData);
            $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Created contact bank', $newContactBank);
            return $newContactBank;
        });

        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Contact bank not created, return contact bank', $contactBank);
        return $contactBank;
    }

    private function updateContactBank($bankData) {
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Started updateContactBank', $bankData);
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['bankId']);
        });
        $this->getLogger(PluginConstants::PLUGIN_NAME)->error('Updated contact bank', $contactBank);
        return $contactBank;
    }
}
