<?php
namespace NetglueEncrypt;

/**
 * Autoloader
 */
use Zend\Loader\AutoloaderFactory;
use Zend\Loader\StandardAutoloader;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;

/**
 * Service Provider
 */
use Zend\ModuleManager\Feature\ServiceProviderInterface;

/**
 * Config Provider
 */
use Zend\ModuleManager\Feature\ConfigProviderInterface;

/**
 * Controller Plugin Provider
 */
use Zend\ModuleManager\Feature\ControllerPluginProviderInterface;

/**
 * Controller Provider
 */
use Zend\ModuleManager\Feature\ControllerProviderInterface;

/**
 * Bootstrap Listener
 */
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\EventManager\EventInterface as Event;

class Module implements
	AutoloaderProviderInterface,
	ServiceProviderInterface,
	ConfigProviderInterface,
	BootstrapListenerInterface,
	ControllerPluginProviderInterface,
	ControllerProviderInterface {
	
	
	/**
	 * Return autoloader configuration
	 * @link http://framework.zend.com/manual/2.0/en/user-guide/modules.html
	 * @return array
	 */
	public function getAutoloaderConfig() {
    return array(
			AutoloaderFactory::STANDARD_AUTOLOADER => array(
				StandardAutoloader::LOAD_NS => array(
					__NAMESPACE__ => __DIR__,
				),
			),
		);
	}
	
	/**
	 * Include/Return module configuration
	 * @return array
	 * @implements ConfigProviderInterface
	 */
	public function getConfig() {
		return include __DIR__ . '/../../config/module.config.php';
	}
	
	/**
	 * Return Service Config
	 * @return array
	 * @implements ServiceProviderInterface
	 */
	public function getServiceConfig() {
		return include __DIR__ . '/../../config/services.config.php';
	}
	
	/**
	 * Return controller plugin config
	 * @return array
	 */
	public function getControllerPluginConfig() {
		return array(
			'factories' => array(
				
			),
			'aliases' => array(
				
			),
		);
	}
	
	/**
	 * Return controller configuration
	 * @return array
	 */
	public function getControllerConfig() {
		return include __DIR__ . '/../../config/controllers.config.php';
	}
	
	/**
	 * MVC Bootstrap Event
	 *
	 * @param Event $e
	 * @return void
	 * @implements BootstrapListenerInterface
	 */
	public function onBootstrap(Event $e) {
		$app = $e->getApplication();
		$serviceMgr = $app->getServiceManager();
	}
}
