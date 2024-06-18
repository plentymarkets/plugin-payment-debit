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
        if ($contactBank instanceof ContactBank) {
            $logs['step01-FoundBankDetailsData']['contactBank']['id'] = $contactBank->id;
            $logs['step01-FoundBankDetailsData']['contactBank']['contactId'] = $contactBank->contactId;
            $logs['step01-FoundBankDetailsData']['contactBank']['accountOwner'] = $contactBank->accountOwner;
            $logs['step01-FoundBankDetailsData']['contactBank']['bankName'] = $contactBank->bankName;
            $logs['step01-FoundBankDetailsData']['contactBank']['lastUpdateBy'] = $contactBank->lastUpdateBy;
        }

        if (is_null($contactBank)) {
            $logs['step02-NotFoundContactBankDesc'] = 'Contact bank for orderId not found. Search for contact bank without orderId';
            $accountContactId = $this->accountService->getAccountContactId();
            if($accountContactId>0) {
                $logs['step03-SearchByContactIdDesc'] = 'Search bank details by contactId';
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
                    $logs['step04-LastBankForContactData']['contactBank']['bankAccountOwner'] = $bank->accountOwner;
                    $logs['step04-LastBankForContactData']['contactBank']['bankName'] = $bank->bankName;
                    $logs['step04-LastBankForContactData']['contactBank']['bankId'] = 0;
                    $logs['step04-LastBankForContactData']['contactBank']['debitMandate'] = '';
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
            $debitMandate = $contactBank->directDebitMandateAvailable ? 'checked' : '';
            $bankAccount['debitMandate']	 =	$debitMandate;
            $logs['step05-UseBankFromOrderData']['contactBank']['bankAccountOwner'] = $bankAccount['bankAccountOwner'];
            $logs['step05-UseBankFromOrderData']['contactBank']['bankName'] = $bankAccount['bankName'];
            $logs['step05-UseBankFromOrderData']['contactBank']['bankId'] = $bankAccount['bankId'];
            $logs['step05-UseBankFromOrderData']['contactBank']['debitMandate'] = $debitMandate;
        }

        $logs['step06-RedenderBankDetailsDesc'] = 'Render successfully BankDetailsOverlay';
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
        $logs['step01-UsedBankDetailsData']['bankData']['contactId'] = $bankData['contactId'];
        $logs['step01-UsedBankDetailsData']['bankData']['accountOwner'] = $bankData['accountOwner'];
        $logs['step01-UsedBankDetailsData']['bankData']['bankName'] = $bankData['bankName'];
        $logs['step01-UsedBankDetailsData']['bankData']['lastUpdateBy'] = $bankData['lastUpdateBy'];

        try
        {
            //check if this contactBank already exist
            $contactBankExists = false;
            if ($bankData['contactId'] != NULL) {
                $logs['step01-CheckIfBankDetailExistDesc'] = 'Check if this contactBank already exist on user';
                $logs['step01-CheckIfBankDetailExistData']['contactId'] = $bankData['contactId'];

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
                            $logs['step02-FoundOnContactData']['contactBank']['contactId'] = $bankData['contactId'];
                            $logs['step02-FoundOnContactData']['contactBank']['accountOwner'] = $bankData['accountOwner'];
                            $logs['step02-FoundOnContactData']['contactBank']['bankName'] = $bankData['bankName'];
                            return true;
                        }
                    }

                    $logs['step03-ContactBankNotFoundDesc'] = 'Contact bank was Not found on contact';
                    $logs['step03-ContactBankNotFoundData']['bankData']['contactId'] = $bankData['contactId'];
                    $logs['step03-ContactBankNotFoundData']['bankData']['accountOwner'] = $bankData['accountOwner'];
                    $logs['step03-ContactBankNotFoundData']['bankData']['bankName'] = $bankData['bankName'];
                    return false;
                });
            }

            $bankData['lastUpdateBy'] = 'customer';
            if (!$contactBankExists) {
                $logs['step04-NotFoundContactBankDesc'] = 'Contact bank was Not found';
                /** @var ContactBank $newContactBank */
                $newContactBank = $this->createContactBank($bankData);
                $logs['step05-CreatedContactBankDesc'] = 'New contact bank was created';
                if ($newContactBank instanceof ContactBank) {
                    $logs['step05-CreatedContactBankData']['bankData']['contactId'] = $newContactBank->contactId;
                    $logs['step05-CreatedContactBankData']['bankData']['accountOwner'] = $newContactBank->accountOwner;
                    $logs['step05-CreatedContactBankData']['bankData']['bankName'] = $newContactBank->bankName;
                }
            }
            $bankData['contactId'] = null;
            $bankData['directDebitMandateAvailable'] = 1;
            $bankData['directDebitMandateAt'] = date('Y-m-d H:i:s');
            $bankData['paymentMethod'] = 'onOff';

            $logs['step06-ToCreateContactBankEmptyContactIdDesc'] = 'Contact bank with empty contactId will be created';
            $logs['step06-ToCreateContactBankEmptyContactIdData']['bankData']['contactId'] = $bankData['contactId'];
            $logs['step06-ToCreateContactBankEmptyContactIdData']['bankData']['accountOwner'] = $bankData['accountOwner'];
            $logs['step06-ToCreateContactBankEmptyContactIdData']['bankData']['bankName'] = $bankData['bankName'];

            $contactBank = $this->createContactBank($bankData);
            if ($contactBank instanceof ContactBank) {
                $logs['step07-CreatedContactBankEmptyContactIdDesc'] = 'Contact bank with empty contactId was created';
                $logs['step07-CreatedContactBankEmptyContactIdData']['contactBank']['contactId'] = $contactBank->contactId;
                $logs['step07-CreatedContactBankEmptyContactIdData']['contactBank']['accountOwner'] = $contactBank->accountOwner;
                $logs['step07-CreatedContactBankEmptyContactIdData']['contactBank']['bankName'] = $contactBank->bankName;
                $logs['step07-CreatedContactBankEmptyContactIdData']['contactBank']['id'] = $contactBank->id;
            }

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

        $orderId = $request->get('orderId');
        $bankData = [
            'accountOwner'  => $request->get('bankAccountOwner'),
            'bankName'      => $request->get('bankName'),
            'iban'          => $request->get('bankIban'),
            'bic'           => $request->get('bankBic'),
            'lastUpdateBy'  => 'customer',
            'orderId'       => $orderId
        ];

        $logs['step0-UpdateBankDetailsData']['bankData']['accountOwner'] = $bankData['accountOwner'];
        $logs['step0-UpdateBankDetailsData']['bankData']['bankName'] = $bankData['bankName'];
        $logs['step0-UpdateBankDetailsData']['bankData']['lastUpdateBy'] = $bankData['lastUpdateBy'];
        $logs['step0-UpdateBankDetailsData']['bankData']['orderId'] = $bankData['orderId'];

        if ($request->has('bankId') && $request->get('bankId') > 0) {
            //update existing bank account
            $bankData['bankId'] = $request->get('bankId');
            $logs['step01-UpdateExistingBankAccountDesc'] = 'Update existing bank account';
            $logs['step01-UpdateExistingBankAccountData']['bankData']['bankId'] = $bankData['bankId'];
            $contactBank = $this->updateContactBank($bankData);

        } else {
            //create new bank account
            $logs['step02-CreateBankAccountDesc'] = 'Create new bank account';
            $contactBank = $this->createContactBank($bankData);
        }

        /** @var DebitHelper $debitHelper */
        $debitHelper = pluginApp(DebitHelper::class);
        // Create a plentymarkets payment
        $logs['step03-CreatePlentyPaymentDesc'] = 'Create Plenty payment with these contact bank details';
        if ($contactBank instanceof ContactBank) {
            $logs['step03-CreatePlentyPaymentData']['contactBank']['accountOwner'] = $contactBank->accountOwner;
            $logs['step03-CreatePlentyPaymentData']['contactBank']['contactId'] = $contactBank->contactId;
            $logs['step03-CreatePlentyPaymentData']['contactBank']['bankName'] = $contactBank->bankName;
            $logs['step03-CreatePlentyPaymentData']['contactBank']['lastUpdateBy'] = $contactBank->lastUpdateBy;
        }

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
        if (is_array($bankData)) {
            $logs['step0-CreateContactBankData']['bankData']['accountOwner'] = isset($bankData['accountOwner']) ? $bankData['accountOwner'] : '';
            $logs['step0-CreateContactBankData']['bankData']['bankName'] = isset($bankData['bankName']) ? $bankData['bankName'] : '';
            $logs['step0-CreateContactBankData']['bankData']['lastUpdateBy'] = isset($bankData['lastUpdateBy']) ? $bankData['lastUpdateBy'] : '';
            $logs['step0-CreateContactBankData']['bankData']['orderId'] = isset($bankData['orderId']) ? $bankData['orderId'] : '';
            $logs['step0-CreateContactBankData']['bankData']['bankId'] = isset($bankData['bankId']) ? $bankData['bankId'] : '';
        }

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            $logs['step01-CreateContactBankDesc'] = 'Create contact bank';
            /** @var ContactBank $newContactBank */
            $newContactBank = $paymentRepo->createContactBank($bankData);
            $logs['step02-CreatedContactBankDesc'] = 'Created contact bank';
            if ($newContactBank instanceof ContactBank) {
                $logs['step02-CreatedContactBanktData']['newContactBank']['id'] = $newContactBank->id;
            }
            return $newContactBank;
        });

        $this->debitHelper->logQueueDebit($logs);
        return $contactBank;
    }

    private function updateContactBank($bankData) {
        $logs = [];
        $logs['step0-CurrentContactBankDesc'] = 'Started updateContactBank';
        if (is_array($bankData)) {
            $logs['step0-CreateContactBankData']['bankData']['accountOwner'] = isset($bankData['accountOwner']) ? $bankData['accountOwner'] : '';
            $logs['step0-CreateContactBankData']['bankData']['bankName'] = isset($bankData['bankName']) ? $bankData['bankName'] : '';
            $logs['step0-CreateContactBankData']['bankData']['lastUpdateBy'] = isset($bankData['lastUpdateBy']) ? $bankData['lastUpdateBy'] : '';
            $logs['step0-CreateContactBankData']['bankData']['orderId'] = isset($bankData['orderId']) ? $bankData['orderId'] : '';
            $logs['step0-CreateContactBankData']['bankData']['bankId'] = isset($bankData['bankId']) ? $bankData['bankId'] : '';
        }

        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var ContactPaymentRepositoryContract $paymentRepo */
        $paymentRepo = pluginApp(ContactPaymentRepositoryContract::class);

        $contactBank = $authHelper->processUnguarded(function () use ($paymentRepo, $bankData) {
            return $paymentRepo->updateContactBank($bankData, $bankData['bankId']);
        });
        $logs['step01-CurrentContactBankDesc'] = 'Updated contact bank';
        if ($contactBank instanceof ContactBank) {
            $logs['step01-CurrentContactBankData']['contactBank']['id'] = $contactBank->id;
            $logs['step01-CurrentContactBankData']['contactBank']['accountOwner'] = $contactBank->accountOwner;
            $logs['step01-CurrentContactBankData']['contactBank']['bankName'] = $contactBank->bankName;
            $logs['step01-CurrentContactBankData']['contactBank']['orderId'] = $contactBank->orderId;
            $logs['step01-CurrentContactBankData']['contactBank']['lastUpdateBy'] = $contactBank->lastUpdateBy;
        }

        $this->debitHelper->logQueueDebit($logs);
        return $contactBank;
    }
}
