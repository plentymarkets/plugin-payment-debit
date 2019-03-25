<?php

namespace Debit\Controllers;

use Debit\Services\SettingsService;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;

class SettingsController extends Controller
{
    /**
     * @var SettingsService
     */
    protected $settingsService;

    /**
     * SettingsController constructor.
     * @param SettingsService $settingsService
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function saveSettings(Request $request)
    {
        return $this->settingsService->saveSettings("", $request->get('settings'));
    }

    /**
     * @return bool|mixed
     */
    public function loadSettings($settingType)
    {
        return $this->settingsService->loadSettings($settingType);
    }

    /**
     * Load the settings for one webshop
     *
     * @param $webstore
     * @return bool
     */
    public function loadSetting($webstore, $mode)
    {
        return $this->settingsService->loadSetting($webstore, $mode);
    }

}