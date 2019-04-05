<?php

namespace Debit\Assistents;

use Debit\Assistents\SettingsHandlers\DebitAssistentSettingsHandler;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\Wizard\Services\WizardProvider;
use Plenty\Plugin\Application;
use Plenty\Plugin\Translation\Translator;

/**
 * Class DebitAssistent
 * @package  Debit\Assistents
 */
class DebitAssistent extends WizardProvider
{
    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;

    /**
     * @var WebstoreRepositoryContract
     */
    private $webstoreRepository;

    /**
     * @var array
     */
    private $deliveryCountries;

    /**
     * @var string
     */
    private $language;

    /**
     * @var Translator
     */
    protected $translator;

    public function __construct(
        CountryRepositoryContract $countryRepository,
        WebstoreRepositoryContract $webstoreRepository,
        Translator $translator
    ) {
        $this->countryRepository = $countryRepository;
        $this->webstoreRepository = $webstoreRepository;
        $this->translator = $translator;
    }

    /**
     * The Assistent structure
     *
     * @return array
     */
    protected function structure()
    {
        $config = [
            "title" => 'debitAssistent.assistentTitle',
            "iconPath" => $this->getIcon(),
            "settingsHandlerClass" => DebitAssistentSettingsHandler::class,
            "translationNamespace" => "Debit",
            "key" => "payment-debit-assistent",
            "topics" => [
                "payment",
                "debit",
            ],
            "options" => [
                "config_name" => [
                    "type" => 'select',
                    "defaultValue" => 0,
                    "options" => [
                        "name" => 'debitAssistent.storeName',
                        'required' => true,
                        'listBoxValues' => $this->getWebstoreListForm(),
                    ],
                ],
            ],
            "steps" => [
                "stepOne" => [
                    "title" => 'debitAssistent.stepOneTitle',
                    "sections" => [
                        [
                            "title" => 'debitAssistent.shippingCountriesTitle',
                            "description" => 'debitAssistent.shippingCountriesDescription',
                            "form" => [
                                "countries" => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => [],
                                    'options' => [
                                        "required" => false,
                                        'name' => 'debitAssistent.shippingCountries',
                                        'checkboxValues' => $this->getCountriesListForm(),
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitAssistent.allowDebitForGuestTitle',
                            "description" => 'debitAssistent.allowDebitForGuestDescription',
                            "form" => [
                                "allowDebitForGuest" => [
                                    'type' => 'radioGroup',
                                    'defaultValue' => false,
                                    'options' => [
                                        'radioValues' => [
                                            [
                                                'caption'=>'debitAssistent.assistentNo',
                                                'value'=>false,
                                            ],
                                            [
                                                'caption'=>'debitAssistent.assistentYes',
                                                'value'=>true
                                            ]
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                "stepTwo" => [
                    "title" => 'debitAssistent.stepTwoTitle',
                    "sections" => [
                        [
                            "title" => 'debitAssistent.infoPageTitle',
                            "description" => 'debitAssistent.infoPageDescription',
                            "form" => [
                                "info_page_toggle" => [
                                    'type' => 'toggle',
                                    'defaultValue' => false,
                                    'options' => [
                                        'name' => '',
                                        'required' => true,
                                    ]
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitAssistent.infoPageTypeTitle',
                            "description" => 'debitAssistent.infoPageTypeDescription',
                            "condition" => 'info_page_toggle',
                            "form" => [
                                "info_page_type" => [
                                    'type' => 'select',
                                    'options' => [
                                        "required" => false,
                                        'name' => 'debitAssistent.infoPageTypeName',
                                        'listBoxValues' => [
                                            [
                                                "caption" => 'debitAssistent.infoPageInternal',
                                                "value" => 'internal',
                                            ],
                                            [
                                                "caption" => 'debitAssistent.infoPageExternal',
                                                "value" => 'external',
                                            ],
                                        ],
                                    ],
                                ],
                                "internal_info_page" => [
                                    'type' => 'number',
                                    'isVisible' => "info_page_toggle === true && info_page_type === 'internal'",
                                    'options' => [
                                        'required'=> false,
                                        'name' => 'debitAssistent.infoPageNameInternal',
                                    ],
                                ],
                                "external_info_page" => [
                                    'type' => 'text',
                                    'isVisible' => "info_page_toggle === true && info_page_type === 'external'",
                                    'options' => [
                                        'required'=> false,
                                        'pattern'=> "(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})",
                                        'name' => 'debitAssistent.infoPageNameExternal',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                "stepThree" => [
                    "title" => 'debitAssistent.stepTwoThree',
                    "sections" => [
                        [
                            "title" => 'debitAssistent.sectionLogoTitle',
                            "description" => 'debitAssistent.sectionLogoDescription',
                            "form" => [
                                "logo_type_external" => [
                                    'type' => 'toggle',
                                    'defaultValue' => false,
                                    'options' => [
                                        'name' => '',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => '',
                            "description" => 'debitAssistent.logoURLDescription',
                            "condition" => 'logo_type_external',
                            "form" => [
                                "logo_url" => [
                                    'type' => 'text',
                                    'options' => [
                                        'required' => true,
                                        'pattern'=> "(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})",
                                        'name' => 'debitAssistent.logoURLTypeName',
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitAssistent.sectionPaymentMethodIconTitle',
                            "description" => 'debitAssistent.sectionPaymentMethodIconDescription',
                            "form" => [
                                "debitPaymentMethodIcon" => [
                                    'type' => 'radioGroup',
                                    'defaultValue' => false,
                                    'options' => [
                                        'radioValues' => [
                                            [
                                                'caption'=>'debitAssistent.assistentNo',
                                                'value'=>false,
                                            ],
                                            [
                                                'caption'=>'debitAssistent.assistentYes',
                                                'value'=>true
                                            ]
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ]
            ]
        ];
        return $config;
    }

    /**
     * @return array
     */
    private function getCountriesListForm()
    {
        if ($this->deliveryCountries === null) {
            /** @var CountryRepositoryContract $countryRepository */
            $countryRepository = pluginApp(CountryRepositoryContract::class);
            $countries = $countryRepository->getCountriesList(true, ['names']);
            $this->deliveryCountries = [];
            $systemLanguage = $this->getLanguage();
            foreach($countries as $country) {
                $name = $country->names->where('lang', $systemLanguage)->first()->name;
                $this->deliveryCountries[] = [
                    'caption' => $name ?? $country->name,
                    'value' => $country->id
                ];
            }
            // Sort values alphabetically
            usort($this->deliveryCountries, function($a, $b) {
                return ($a['caption'] <=> $b['caption']);
            });
        }
        return $this->deliveryCountries;
    }

    /**
     * @return array
     */
    private function getWebstoreListForm()
    {
        $webstores = $this->webstoreRepository->loadAll();
        /** @var Webstore $webstore */
        foreach ($webstores as $webstore) {
            $values[] = [
                "caption" => $webstore->name,
                "value" => $webstore->id,
            ];
        }

        usort($values, function ($a, $b) {
            return ($a['value'] <=> $b['value']);
        });

        return $values;
    }

    /**
     * @return string
     */
    private function getLanguage()
    {
        if ($this->language === null) {
            $this->language =  \Locale::getDefault();
        }

        return $this->language;
    }

    private function getIcon()
    {
        $app = pluginApp(Application::class);

        if ($this->getLanguage() != 'de') {
            return $app->getUrlPath('debit').'/images/icon_en.png';
        }

        return $app->getUrlPath('debit').'/images/icon.png';
    }
}
