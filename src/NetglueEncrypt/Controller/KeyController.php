<?php

namespace NetglueEncrypt\Controller;

use Zend\View\Model\ViewModel;
use Zend\Http\PhpEnvironment\Response as Response;

use Zend\Crypt\PublicKey\RsaOptions;
use Zend\Crypt\PublicKey\Rsa;


class KeyController extends AbstractController {
	
	const ROUTE_HOME = 'netglue_encrypt';
	const ROUTE_GEN_KEYS = 'netglue_encrypt/generate';
	
	
	public function indexAction() {
		
	}
	
	public function manualAction() {
		$view = new ViewModel;
		$view->form = $this->getManualForm();
		$view->error = false;
		return $view;
	}
	
	/**
	 * Controller Action for generation of a new key pair
	 * @return ViewModel
	 */
	public function generateAction() {
		$view = new ViewModel;
		$view->form = $this->getGenerateForm();
		$view->error = false;
		
		$request = $this->getRequest();
		$redirectUrl = $this->url()->fromRoute(static::ROUTE_GEN_KEYS);
		$prg = $this->prg($redirectUrl, true);
		
		if($prg instanceof Response) {
			return $prg;
		}
		
		if($prg === false) {
			return $view;
		}
		
		$view->form->setData($prg);
		if(!$view->form->isValid()) {
			$view->error = true;
			return $view;
		}
		
		$post = $view->form->getData();
		$keys = $this->getKeyStorage();
		
		try {
			$rsaOptions = array();
			if(!empty($post['keyPassPhrase'])) {
				$rsaOptions['passPhrase'] = $post['keyPassPhrase'];
			}
			$rsaOptions['binaryOutput'] = $post['outputType'] === 'binary';
			$rsaOptions['hashAlgorithm'] = $post['digestAlgo'];
			$rsaOptions = new RsaOptions($rsaOptions);
			$rsaOptions->generateKeys(array(
				'private_key_bits' => $post['keySize'],
			));
			$rsa = new Rsa($rsaOptions);
			$keys->set($rsa, $post['keyName']);
		} catch(Exception $e) {
			$view->error = true;
			$message = 'Failed to Create Key Pair: '.$e->getMessage();
			$form->setMessages(array('keyName' => $message));
			return $view;
		}
		$this->flashMessenger()->addSuccessMessage('New RSA Key Pair Created Successfully');
		return $this->redirect()->toRoute(static::ROUTE_HOME);
	}
	
	
	public function getGenerateForm() {
		$sl = $this->getServiceLocator();
		return $sl->get('NetglueEncrypt\Form\GenerateKeys');
	}
	
	public function getManualForm() {
		$sl = $this->getServiceLocator();
		return $sl->get('NetglueEncrypt\Form\Manual');
	}
}