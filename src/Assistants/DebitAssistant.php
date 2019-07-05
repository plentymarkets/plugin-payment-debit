<?php

namespace Debit\Assistants;

use Debit\Assistants\SettingsHandlers\DebitAssistantSettingsHandler;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\Wizard\Services\WizardProvider;
use Plenty\Plugin\Application;
use Plenty\Plugin\Translation\Translator;

/**
 * Class DebitAssistant
 * @package  Debit\Assistants
 */
class DebitAssistant extends WizardProvider
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
     * The Assistant structure
     *
     * @return array
     */
    protected function structure()
    {
        $config = [
            "title" => 'debitAssistant.assistantTitle',
            "shortDescription" => 'debitAssistant.assistantShortDescription',
            "iconPath" => $this->getIcon(),
            "settingsHandlerClass" => DebitAssistantSettingsHandler::class,
            "translationNamespace" => "Debit",
            "key" => "payment-debit-assistant",
            "topics" => ["payment"],
            'priority' => 990,
            "options" => [
                "config_name" => [
                    "type" => 'select',
                    "defaultValue" => 0,
                    "options" => [
                        "name" => 'debitAssistant.storeName',
                        'required' => true,
                        'listBoxValues' => $this->getWebstoreListForm(),
                    ],
                ],
            ],
            "steps" => [
                "stepOne" => [
                    "title" => 'debitAssistant.stepOneTitle',
                    "sections" => [
                        [
                            "title" => 'debitAssistant.shippingCountriesTitle',
                            "description" => 'debitAssistant.shippingCountriesDescription',
                            "form" => [
                                "countries" => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => [],
                                    'options' => [
                                        "required" => false,
                                        'name' => 'debitAssistant.shippingCountries',
                                        'checkboxValues' => $this->getCountriesListForm(),
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitAssistant.allowDebitForGuestTitle',
                            "description" => 'debitAssistant.allowDebitForGuestDescription',
                            "form" => [
                                "allowDebitForGuest" => [
                                    'type' => 'checkbox',
                                    'defaultValue' => false,
                                    'options' => [
                                        'name' => 'debitAssistant.assistantDebitForGuestCheckbox'
                                    ]
                                ],
                            ],
                        ],
                    ],
                ],

                "stepTwo" => [
                    "title" => 'debitAssistant.stepTwoTitle',
                    "sections" => [
                        [
                            "title" => 'debitAssistant.infoPageTitle',
                            "description" => 'debitAssistant.infoPageDescription',
                            "form" => [
                                "info_page_toggle" => [
                                    'type' => 'toggle',
                                    'options' => [
                                        'name' => 'debitAssistant.infoPageToggle',
                                    ]
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitAssistant.infoPageTypeTitle',
                            "description" => 'debitAssistant.infoPageTypeDescription',
                            "condition" => 'info_page_toggle',
                            "form" => [
                                "info_page_type" => [
                                    'type' => 'select',
                                    'defaultValue' => 'internal',
                                    'options' => [
                                        "required" => false,
                                        'name' => 'debitAssistant.infoPageTypeName',
                                        'listBoxValues' => [
                                            [
                                                "caption" => 'debitAssistant.infoPageInternal',
                                                "value" => 'internal',
                                            ],
                                            [
                                                "caption" => 'debitAssistant.infoPageExternal',
                                                "value" => 'external',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => '',
                            "description" => 'debitAssistant.infoPageNameInternal',
                            "condition" => 'info_page_toggle && info_page_type == "internal"',
                            "form" => [
                                "internal_info_page" => [
                                    "type" => 'category',
                                    'defaultValue' => '',
                                    'isVisible' => "info_page_toggle == true && info_page_type == 'internal'",
                                    "displaySearch" => true
                                ],
                            ],
                        ],
                        [
                            "title" => '',
                            "description" => '',
                            "condition" => 'info_page_toggle && info_page_type == "external"',
                            "form" => [
                                "external_info_page" => [
                                    'type' => 'text',
                                    'defaultValue' => '',
                                    'options' => [
                                        'required'=> false,
                                        'pattern'=> "(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})",
                                        'name' => 'debitAssistant.infoPageNameExternal',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                "stepThree" => [
                    "title" => 'debitAssistant.stepTwoThree',
                    "sections" => [
                        [
                            "title" => 'debitAssistant.sectionLogoTitle',
                            "description" => 'debitAssistant.sectionLogoDescription',
                            "form" => [
                                "logo_type_external" => [
                                    'type' => 'toggle',
                                    'defaultValue' => false,
                                    'options' => [
                                        'name' => 'debitAssistant.logoTypeToggle',
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => '',
                            "description" => 'debitAssistant.logoURLDescription',
                            "condition" => 'logo_type_external',
                            "form" => [
                                "logo_url" => [
                                    'type' => 'file',
                                    'defaultValue' => '',
                                    'showPreview' => true
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitAssistant.sectionPaymentMethodIconTitle',
                            "description" => 'debitAssistant.sectionPaymentMethodIconDescription',
                            "form" => [
                                "debitPaymentMethodIcon" => [
                                    'type' => 'checkbox',
                                    'defaultValue' => 'false',
                                    'options' => [
                                        'name' => 'debitAssistant.assistantPaymentMethodIconCheckbox'
                                    ]
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
