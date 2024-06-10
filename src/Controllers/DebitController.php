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
            $logs['step05-UseBankFromOrderDesc'] = 'Use bank details from order contact';
            $logs['step05-UseBankFromOrderData']['orderId'] = $orderId;


            $bankAccount['bankAccountOwner'] =  $contactBank->accountOwner;
            $bankAccount['bankName']         =	$contactBank->bankName;
            $bankAccount['bankIban']	     =	$contactBank->iban;
            $bankAccount['bankBic']		     =	$contactBank->bic;
            $bankAccount['bankId']		     =	$contactBank->id;
            $bankAccount['debitMandate']	 =	($contactBank->directDebitMandateAvailable ? 'checked' : '');
            $logs['step05-UseBankFromOrderData']['contactBank'] = $bankAccount;

        }

        $logs['step06-RedenderBankDetailsDesc'] = 'Render successfully BankDetailsOverlay';
        $logs['step06-RedenderBankDetailsData']['contactBank'] = $bankAccount;
        $logs['step06-RedenderBankDetailsData']['orderId'] = $orderId;
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
        $logs = [];
        $logs['step0-SetBankDetailsDesc'] = 'Set bank details';
        $logs['step0-SetBankDetailsData']['request'] = $request;

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

        $logs['step01-UsedBankDetailsDesc'] = 'Current used bank data';
        $logs['step01-UsedBankDetailsData']['bankData'] = $bankData;
        try
        {
            //check if this contactBank already exist
            $contactBankExists = false;
            if ($bankData['contactId'] != NULL) {
                $logs['step01-CheckIfBankDetailExistDesc'] = 'Check if this contactBank already exist on user';
                $logs['step01-CheckIfBankDetailExistData']['contactId'] = $bankData['contactId'];
                $this->getLogger(PluginConstants::PLUGIN_NAME)->error(' ', );
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
                            $logs['step02-FoundOnContactDesc'] = 'Contact bank was found on contact';
                            $logs['step02-FoundOnContactData']['contactBank'] = $contactBank;
                            return true;
                        }
                    }

                    $logs['step03-ContactBankNotFoundDesc'] = 'Contact bank was Not found on contact';
                    $logs['step03-ContactBankNotFoundData']['bankData'] = $bankData;
                    return false;
                });
            }

            $bankData['lastUpdateBy'] = 'customer';
            if (!$contactBankExists) {
                $logs['step04-NotFoundContactBankDesc'] = 'Contact bank was Not found';
                $logs['step04-NotFoundContactBankData']['bankData'] = $bankData;
                /** @var ContactBank $newContactBank */
                $newContactBank = $this->createContactBank($bankData);
                $logs['step05-CreatedContactBankDesc'] = 'New contact bank was created';
                $logs['step05-CreatedContactBankData']['bankData'] = $newContactBank;
            }
            $bankData['contactId'] = null;
            $bankData['directDebitMandateAvailable'] = 1;
            $bankData['directDebitMandateAt'] = date('Y-m-d H:i:s');
            $bankData['paymentMethod'] = 'onOff';
            $logs['step06-ToCreateContactBankEmptyContactIdDesc'] = 'Contact bank with empty contactId will be created';
            $logs['step06-ToCreateContactBankEmptyContactIdData']['bankData'] = $bankData;
            $contactBank = $this->createContactBank($bankData);
            $logs['step07-CreatedContactBankEmptyContactIdDesc'] = 'Contact bank with empty contactId was created';
            $logs['step07-CreatedContactBankEmptyContactIdData']['contactBank'] = $contactBank;

            $this->sessionStorageService->setSessionValue('contactBank', $contactBank);
            $logs['step08-FinalPlaceOrder'] = 'Success, go to place-order';
            $this->debitHelper->logQueueDebit($logs);
            return $response->redirectTo($this->sessionStorageService->getLang() . '/place-order');
        }
        catch(\Exception $e)
        {
            $logs['step09-ContactCreationExceptionDesc'] = 'Exception in contact bank processing';
            $logs['step09-ContactCreationExceptionData']['message'] = $e->getMessage();
            $this->debitHelper->logQueueDebit($logs);
            return $response->redirectTo($this->sessionStorageService->getLang().'/checkout');
        }
    }

    /*
     *
     * @return BaseResponse
     */
    public function updateBankDetails(Response $response, Request $request)
    {
        $logs = [];
        $logs['step0-UpdateBankDetailsDesc'] = 'Update bank details';
        $logs['step0-UpdateBankDetailsData']['request'] = $request;

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
            $logs['step01-UpdateExistingBankAccountDesc'] = 'Update existing bank account';
            $logs['step01-UpdateExistingBankAccountData']['bankData'] = $bankData;
            $contactBank = $this->updateContactBank($bankData);
        } else {
            //create new bank account
            $logs['step02-CreateBankAccountDesc'] = 'Create bank account';
            $logs['step02-CreateBankAccountData']['bankData'] = $bankData;
            $contactBank = $this->createContactBank($bankData);
        }

        /** @var DebitHelper $debitHelper */
        $debitHelper = pluginApp(DebitHelper::class);
        // Create a plentymarkets payment
        $logs['step03-CreatePlentyPaymentDesc'] = 'Create Plenty payment';
        $logs['step03-CreatePlentyPaymentData']['contactBank'] = $contactBank;
        $plentyPayment = $debitHelper->createPlentyPayment($orderId, $contactBank);

        if($plentyPayment instanceof Payment) {
            $logs['step04-CreatedPlentyPaymentDesc'] = 'Created Plenty payment';
            $logs['step04-CreatedPlentyPaymentData']['plentyPayment'] = $plentyPayment;

            // Assign the payment to an order in plentymarkets
            $debitHelper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
            $logs['step05-AssignedPlentyPaymentDesc'] = 'Assigned Plenty payment to orderId';
            $logs['step05-AssignedPlentyPaymentData']['orderId'] = $orderId;
        }

        $logs['step06-GoToConfirmationDesc'] = 'Success, go to confirmation page ';
        $logs['step06-GoToConfirmationData']['orderId'] = $orderId;

        $this->debitHelper->logQueueDebit($logs, $orderId);
        return $response->redirectTo($this->sessionStorageService->getLang().'/confirmation/'.$orderId);
    }


    /**
     * @param $bankData
     * @return mixed
     */
    private function createContactBank($bankData) {

        $logs = [];
        $logs['step0-CreateContactBankDesc'] = 'Started createContactBank';
        $logs['step0-CreateContactBankData']['bankData'] = $bankData;

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            $logs['step01-CreateContactBankDesc'] = 'Create contact bank';
            $logs['step01-CreateContactBanktData']['bankData'] = $bankData;
            /** @var ContactBank $newContactBank */
            $newContactBank = $paymentRepo->createContactBank($bankData);
            $logs['step02-CreatedContactBankDesc'] = 'Created contact bank';
            $logs['step02-CreatedContactBanktData']['newContactBank'] = $newContactBank;
            return $newContactBank;
        });



        return $contactBank;
    }

    private function updateContactBank($bankData) {

        $logs = [];
        $logs['step0-CurrentContactBankDesc'] = 'Started updateContactBank';
        $logs['step0-CurrentContactBankData']['bankData'] = $bankData;

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['bankId']);
        });
        $logs['step01-CurrentContactBankDesc'] = 'Updated contact bank';
        $logs['step01-CurrentContactBankData']['contactBank'] = $contactBank;
        $this->debitHelper->logQueueDebit($logs);
        return $contactBank;
    }
}
