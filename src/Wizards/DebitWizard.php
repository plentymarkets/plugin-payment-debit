<?php

namespace Debit\Wizards;

use Debit\Wizards\SettingsHandlers\DebitWizardSettingsHandler;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\System\Contracts\SystemInformationRepositoryContract;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\Wizard\Services\WizardProvider;
use Plenty\Plugin\Translation\Translator;

/**
 * Class DebitWizard
 * @package  Debit\Wizards
 * @author   Daniel Marx
 */
class DebitWizard extends WizardProvider
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
     * The wizard structure
     *
     * @return array
     */
    protected function structure()
    {
        $config = [
            "title" => 'debitWizard.wizardTitle',
            "iconPath" => "",
            "settingsHandlerClass" => DebitWizardSettingsHandler::class,
            "translationNamespace" => "Debit",
            "key" => "payment-debit-wizard",
            "topics" => [
                "payment",
                "debit",
            ],
            "options" => [
                "config_name" => [
                    "type" => 'select',
                    "options" => [
                        "name" => 'debitWizard.storeName',
                        'listBoxValues' => $this->getWebstoreListForm(),
                    ],
                ],
            ],
            "steps" => [
                "stepOne" => [
                    "title" => 'debitWizard.stepOneTitle',
                    "sections" => [
                        [
                            "title" => 'debitWizard.shippingCountriesTitle',
                            "form" => [
                                "countries" => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => [],
                                    'options' => [
                                        "required" => true,
                                        'name' => 'debitWizard.shippingCountries',
                                        'checkboxValues' => $this->getCountriesListForm(),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],

                "stepTwo" => [
                    "title" => 'debitWizard.stepTwoTitle',
                    "sections" => [
                        [
                            "title" => 'debitWizard.sectionInfoPageTitle',
                            "form" => [
                                "info_page_type" => [
                                    'type' => 'select',
                                    'options' => [
                                        "required" => false,
                                        'name' => 'debitWizard.inputInfoPageTypeName',
                                        'listBoxValues' => [
                                            [
                                                "caption" => 'debitWizard.infoPageInternal',
                                                "value" => 'internal',
                                            ],
                                            [
                                                "caption" => 'debitWizard.infoPageExternal',
                                                "value" => 'external',
                                            ],
                                        ],
                                    ],
                                ],
                                "internal_info_page" => [
                                    'type' => 'number',
                                    'isVisible' => "info_page_type === 'internal'",
                                    'options' => [
                                        'required'=> false,
                                        'name' => 'debitWizard.inputInfoPageNameInternal',
                                    ],
                                ],
                                "external_info_page" => [
                                    'type' => 'text',
                                    'isVisible' => "info_page_type === 'external'",
                                    'options' => [
                                        'required'=> false,
                                        'pattern'=> "(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})",
                                        'name' => 'debitWizard.inputInfoPageNameExternal',
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitWizard.sectionLogoTitle',
                            "form" => [
                                "logo_type" => [
                                    'type' => 'select',
                                    'options' => [
                                        "required" => false,
                                        'name' => 'debitWizard.inputLogoTypeName',
                                        'listBoxValues' => [
                                            [
                                                "caption" => 'debitWizard.logoDefault',
                                                "value" => 'default',
                                            ],
                                            [
                                                "caption" => 'debitWizard.logoURL',
                                                "value" => 'url',
                                            ],
                                        ],
                                    ],
                                ],
                                "logo_url" => [
                                    'type' => 'text',
                                    'isVisible' => "logo_type === 'url'",
                                    'options' => [
                                        'required' => "logo_type === 'url'",
                                        'pattern'=> "(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})",
                                        'name' => 'debitWizard.inputLogoTypeName',
                                    ],
                                ],
                            ],
                        ],
                        [
                            "title" => 'debitWizard.sectionPaymentMethodIconTitle',
                            "form" => [
                                "debitPaymentMethodIcon" => [
                                    'type' => 'toggle',
                                    'defaultValue' => false,
                                    'options' => [
                                        'name' => '',
                                        'required' => true,
                                    ]
                                ],
                            ],
                        ]
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
            return ($a['caption'] <=> $b['caption']);
        });

        return $values;
    }

    /**
     * @return string
     */
    private function getLanguage()
    {
        if ($this->language === null) {
            /** @var SystemInformationRepositoryContract $systemInformationRepository */
            $systemInformationRepository = pluginApp(SystemInformationRepositoryContract::class);
            $this->language = $systemInformationRepository->loadValue('systemLang');
        }

        return $this->language;
    }
}
