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
            "title" => $this->translator->trans('Debit::debitWizard.wizardTitle'),
            //"iconPath" => "http://assistant.plentymarkets.com/fulfi-wizard-icon.png",
            "settingsHandlerClass" => DebitWizardSettingsHandler::class,
            "key" => "payment-debit-wizard",
            "topics" => [
                "payment",
                "debit",
            ],
            "options" => [
                "config_name" => [
                    "type" => 'select',
                    "options" => [
                        "name" => $this->translator->trans('Debit::debitWizard.storeName'),
                        'listBoxValues' => $this->getWebstoreListForm(),
                    ],
                ],
            ],
            "steps" => [
                "stepOne" => [
                    "title" => $this->translator->trans('Debit::debitWizard.stepOneTitle'),
                    "sections" => [
                        [
                            "title" => $this->translator->trans('Debit::debitWizard.sectionNameTitle'),
                            "form" => [
                                "name" => [
                                    'type' => 'text',
                                    'options' => [
                                        'name' => $this->translator->trans('Debit::debitWizard.inputName'),
                                    ],
                                ],
                            ],
                        ],
                        ["title" => $this->translator->trans('Debit::debitWizard.sectionInfoPageTitle'),
                        "form" => [
                            "info_page_type" => [
                                'type' => 'select',
                                'options' => [
                                    "required" => true,
                                    'name' => $this->translator->trans('Debit::debitWizard.inputInfoPageTypeName'),
                                    'listBoxValues' => [
                                        [
                                            "caption" => $this->translator->trans('Debit::debitWizard.infoPageInternal'),
                                            "value" => 1,
                                        ],
                                        [
                                            "caption" => $this->translator->trans('Debit::debitWizard.infoPageExternal'),
                                            "value" => 2,
                                        ],
                                    ],
                                ],
                            ],
                            "internal_info_page" => [
                                'type' => 'number',
                                'isVisible' => "info_page_type === 1",
                                'options' => [
                                    'name' => $this->translator->trans('Debit::debitWizard.inputInfoPageNameInternal'),
                                ],
                            ],
                            "external_info_page" => [
                                'type' => 'text',
                                'isVisible' => "info_page_type === 2",
                                'options' => [
                                    'name' => $this->translator->trans('Debit::debitWizard.inputInfoPageNameExternal'),
                                ],
                            ],
                        ],
                    ],
                    [
                        "title" => $this->translator->trans('Debit::debitWizard.sectionLogoTitle'),
                        "form" => [
                            "logo_type" => [
                                'type' => 'select',
                                'options' => [
                                    "required" => true,
                                    'name' => $this->translator->trans('Debit::debitWizard.inputLogoTypeName'),
                                    'listBoxValues' => [
                                        [
                                            "caption" => '',
                                            "value" => '',
                                        ],
                                        [
                                            "caption" => $this->translator->trans('Debit::debitWizard.logoURL'),
                                            "value" => 'url',
                                        ],
                                        [
                                            "caption" => $this->translator->trans('Debit::debitWizard.logoDefault'),
                                            "value" => 'default',
                                        ],
                                    ],
                                ],
                            ],
                            "external_info_page" => [
                                'type' => 'text',
                                'isVisible' => "logo_type === 'url'",
                                'options' => [
                                    'name' => $this->translator->trans('Debit::debitWizard.inputLogoURL'),
                                ],
                            ],
                        ],
                    ],
                    [
                        "title" => $this->translator->trans('Debit::debitWizard.shippingCountriesTitle'),
                        "form" => [
                            "countries" => [
                                'type' => 'checkboxGroup',
                                'defaultValue' => [],
                                'options' => [
                                    "required" => true,
                                    'name' => $this->translator->trans('Debit::debitWizard.shippingCountries'),
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
