<?php
/**
 * Service Configuration for the NetglueEncrypt Module
 */

return array(
	
	'invokables' => array(
		'NetglueEncrypt\Form\Manual' => 'NetglueEncrypt\Form\Manual',
		'NetglueEncrypt\Form\SetPass' => 'NetglueEncrypt\Form\SetPass',
	),
	
	'initializers' => array(
		'NetglueEncrypt\Form\KeyStorageAware' => function($instance, $sl) {
			if(method_exists($instance, 'setKeyStorage')) {
				$storage = $sl->get('NetglueEncrypt\KeyStorage');
				$instance->setKeyStorage($storage);
			}
		},
	),
	
	'factories' => array(
		'NetglueEncrypt\KeyStorage' => 'NetglueEncrypt\Service\KeyStorageFactory',
		
		'NetglueEncrypt\Form\GenerateKeys' => function($sm) {
			$storage = $sm->get('NetglueEncrypt\KeyStorage');
			$form = new \NetglueEncrypt\Form\Generate($storage);
			return $form;
		},
		
		'NetglueEncrypt\Session' => function($sm) {
			$container = new \NetglueEncrypt\Session\Container('netglue_encrypt');
			return $container;
		},
	),
	
	'aliases' => array(
		
	),
	
);