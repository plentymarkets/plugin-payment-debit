<?php

namespace Debit\Wizards;

use Debit\Wizards\SettingsHandlers\DebitWizardSettingsHandler;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
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
            "iconPath" => "https://avoro.eu/templates/avoro/img/banktransfer.png",
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
                            "title" => 'debitWizard.sectionNameTitle',
                            "form" => [
                                "name" => [
                                    'type' => 'text',
                                    'options' => [
                                        'name' => 'debitWizard.inputName',
                                    ],
                                ],
                            ],
                        ],
                        ["title" => 'debitWizard.sectionInfoPageTitle',
                            "form" => [
                                "info_page_type" => [
                                    'type' => 'select',
                                    'options' => [
                                        "required" => true,
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
                                        'required'=> "info_page_type === 'internal'",
                                        'name' => 'debitWizard.inputInfoPageNameInternal',
                                    ],
                                ],
                                "external_info_page" => [
                                    'type' => 'text',
                                    'isVisible' => "info_page_type === 'external'",
                                    'options' => [
                                        'required'=> "info_page_type === 'external'",
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
                                        "required" => true,
                                        'name' => 'debitWizard.inputLogoTypeName',
                                        'listBoxValues' => [
                                            [
                                                "caption" => '',
                                                "value" => '',
                                            ],
                                            [
                                                "caption" => 'debitWizard.logoURL',
                                                "value" => 'url',
                                            ],
                                            [
                                                "caption" => 'debitWizard.logoDefault',
                                                "value" => 'default',
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
            ],
        ];
        return $config;
    }

    /**
     * @return array
     */
    private function getCountriesListForm()
    {
        $systemLanguage = 'de';//config('app.locale');

        $countries = $this->countryRepository->getCountriesList(true, ['names']);
        foreach ($countries as $country) {
            $name = $country->names->where('lang', '=', $systemLanguage)->first()->name;
            $values[] = [
                "caption" => $name ?? $country->name,
                "value" => $country->id,
            ];
        }

        usort($values, function ($a, $b) {
            return ($a['caption'] <=> $b['caption']);
        });

        return $values;
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
}
