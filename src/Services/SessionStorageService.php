<?php //strict

namespace Debit\Services;

use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Order\Models\Order;

/**
 * Class SessionStorageService
 * @package IO\Services
 */
class SessionStorageService
{
	/**
	 * @var FrontendSessionStorageFactoryContract
	 */
	private $sessionStorage;

    /**
     * SessionStorageService constructor.
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     */
	public function __construct(FrontendSessionStorageFactoryContract $sessionStorage)
	{
		$this->sessionStorage = $sessionStorage;
	}

    /**
     * Set the value in the session
     * @param string $name
     * @param $value
     */
	public function setSessionValue(string $name, $value)
	{
		$this->sessionStorage->getPlugin()->setValue($name, $value);
	}

    /**
     * Get a value from the session
     * @param string $name
     * @return mixed
     */
	public function getSessionValue(string $name)
	{
		return $this->sessionStorage->getPlugin()->getValue($name);
	}

    /**
     * Get Order Payment Method Id
     *
     * @return int
     */
	public function getOrderMopId()
    {
        /** @var array  $order*/
        $order = $this->sessionStorage->getOrder()->toArray();
        $mop = $order['methodOfPayment'];

        if(!empty($mop))
        {
            return $mop;
        }

        return 0;
    }

    /**
     * Get the language from session
     * @return string|null
     */
	public function getLang()
	{
        $lang = $this->sessionStorage->getLocaleSettings()->language;

        if(empty($lang))
        {
            $lang = 'de';
        }

		return $lang;
	}
}
