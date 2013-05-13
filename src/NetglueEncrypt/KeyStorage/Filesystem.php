<?php

namespace NetglueEncrypt\KeyStorage;

use Traversable;
use Zend\Stdlib\ArrayUtils;

use Zend\Crypt\PublicKey\Rsa;
use Zend\Crypt\PublicKey\RsaOptions;

use Zend\Crypt\PublicKey\Exception\ExceptionInterface as ZendException;

class Filesystem implements KeyStorageInterface {
	
	/**
	 * File Name for the Key List
	 */
	const KEY_LIST_FILE_NAME = 'key-list.json';
	
	/**
	 * File Format Version
	 */
	const VERSION = '0.1';
	
	/**
	 * Options
	 * @var FilesystemOptions
	 */
	protected $options;
	
	/**
	 * Key List
	 * @var array
	 */
	protected $keyList;
	
	/**
	 * RSA Key Pairs
	 * @var array
	 */
	protected $keys = array();
	
	
	/**
	 * Constructor
	 * @param array|Traversable|FilesystemOptions $options
	 * @return void
	 */
	public function __construct($options) {
		if($options instanceof FilesystemOptions) {
			$this->setOptions($options);
			return;
		}
		if($options instanceof Traversable) {
			$options = ArrayUtils::iteratorToArray($options);
		}
		$type = is_object($options) ? get_class($options) : gettype($options);
		if(!is_array($options)) {
			throw new Exception\InvalidArgumentException("Options must be an instance of FilesystemOptions, or an array|Traversable. {$type} received");
		}
		$options = new FilesystemOptions($options);
		$this->setOptions($options);
	}
	
	/**
	 * Set Options
	 * @param FilesystemOptions $options
	 * @return Filesystem $this
	 */
	public function setOptions(FilesystemOptions $options) {
		$this->options = $options;
		return $this;
	}
	
	/**
	 * Get Options
	 * @return FilesystemOptions
	 */
	public function getOptions() {
		return $this->options;
	}
	
	/**
	 * Return Rsa encryption instance using the options for the given named key pair
	 * @param string $name
	 * @param string $passPhrase Key password must be provided to load private keys that are encrypted with a pass phrase
	 * @return Rsa
	 */
	public function get($name = self::DEFAULT_KEY_NAME, $passPhrase = NULL) {
		if(empty($name) || !is_string($name)) {
			throw new Exception\InvalidArgumentException("A name for the key pair must be provided");
		}
		if(!isset($this->keys[$name])) {
			$this->loadKey($name, $passPhrase);
		}
		return $this->keys[$name];
	}
	
	/**
	 * Persist a key pair using the provided name for identification
	 * @param Rsa $rsa
	 * @param string $name
	 * @return KeyStorageInterface $this
	 * @throws Exception\InvalidArgumentException if no key pair name is provided
	 * @throws Exception\RuntimeException if we fail to write 
	 * 
	 */
	public function set(Rsa $rsa, $name = self::DEFAULT_KEY_NAME) {
		if(empty($name) || !is_string($name)) {
			throw new Exception\InvalidArgumentException("A name for the key pair must be provided");
		}
		$this->checkBasePath('write');
		$base = $this->options->getBasePath() . DIRECTORY_SEPARATOR;
		$keyList = $this->getKeyList();
		$oldKey = false;
		if(isset($keyList[$name])) {
			$oldKey = $keyList[$name]['file'];
		}
		$private = $rsa->getOptions()->getPrivateKey()->toString();
		$privateHash = md5($private);
		$file = $base.$privateHash;
		$bytes = file_put_contents($file, $private, LOCK_EX);
		if(false === $bytes) {
			throw new Exception\RuntimeException('Failed to write the private key to disk');
		}
		chmod($file, $this->options->getPrivateKeyFileMode());
		$public = $rsa->getOptions()->getPublicKey()->toString();
		$file .= '.pub';
		$bytes = file_put_contents($file, $public, LOCK_EX);
		if(false === $bytes) {
			throw new Exception\RuntimeException('Failed to write the public key to disk');
		}
		chmod($file, $this->options->getPublicKeyFileMode());
		$pass = $rsa->getOptions()->getPassPhrase();
		$requiresPass = !empty($pass);
		$this->keyList[$name] = array(
			'file' => $privateHash,
			'requiresPassword' => $requiresPass,
			'binaryOutput' => $rsa->getOptions()->getBinaryOutput(),
			'hashAlgorithm' => $rsa->getOptions()->getHashAlgorithm(),
		);
		$this->keys[$name] = $rsa;
		$this->saveKeyList();
		if($this->options->getDeleteOldKeys() && false !== $oldKey) {
			if(file_exists($base . $oldKey)) {
				unlink($base . $oldKey);
			}
			if(file_exists($base . $oldKey . '.pub')) {
				unlink($base . $oldKey . '.pub');
			}
		}
		return $this;
	}
	
	/**
	 * Whether the named key pair exists
	 * @param string $name
	 * @return bool
	 */
	public function has($name = self::DEFAULT_KEY_NAME) {
		return array_key_exists($name, $this->getKeyList());
	}
	
	/**
	 * Load a key pair from disk
	 * @param string $name
	 * @param string $passPhrase
	 * @return void
	 */
	protected function loadKey($name, $passPhrase = NULL) {
		$list = $this->getKeyList();
		$hash = $list[$name]['file'];
		$base = $this->options->getBasePath() . DIRECTORY_SEPARATOR;
		
		$options = array(
			'private_key' => $base . $hash,
			'public_key' => $base . $hash . '.pub',
			'pass_phrase' => $passPhrase,
			'binary_output' => $list[$name]['binaryOutput'],
			'hashAlgorithm' => $list[$name]['hashAlgorithm'],
		);
		try {
			$rsa = Rsa::factory($options);
		} catch(ZendException $e) {
			throw new Exception\RuntimeException('Failed to load key pair', $e);
		}
		$this->keys[$name] = $rsa;
	}
	
	/**
	 * Return the key list array
	 * @return array
	 */
	public function getKeyList() {
		if(!is_array($this->keyList)) {
			$this->loadKeyList();
		}
		return $this->keyList;
	}
	
	/**
	 * Return an array of key pair names/identifiers
	 * @return array
	 */
	public function getKeyPairNames() {
		$list = $this->getKeyList();
		unset($list['__version']);
		return array_keys($list);
	}
	
	/**
	 * Load JSON encoded key list from disk
	 * @return void
	 * @throws Exception\RuntimeException if an existing file cannot be read/loaded
	 */
	protected function loadKeyList() {
		$this->checkBasePath('read');
		$file = $this->options->getBasePath() . DIRECTORY_SEPARATOR . static::KEY_LIST_FILE_NAME;
		if(file_exists($file)) {
			if( false === ($json = file_get_contents($file)) ) {
				throw new Exception\RuntimeException('Failed to load the key list json file from disk');
			}
			$this->keyList = json_decode($json, true);
			if($this->keyList['__version'] !== self::VERSION) {
				throw new Exception\RuntimeException("Key list file format version mismatch. Library version is ".self::VERSION." and file format version is ".$this->_keyList['__version']);
			}
			return;
		}
		$this->keyList = array(
			'__version' => self::VERSION,
		);
	}
	
	/**
	 * Save the key list to disk
	 * @return bool
	 * @throws Exception\RuntimeException if we fail to write the file
	 */
	protected function saveKeyList() {
		$this->checkBasePath('write');
		$file = $this->options->getBasePath() . DIRECTORY_SEPARATOR . static::KEY_LIST_FILE_NAME;
		$data = json_encode($this->getKeyList());
		$bytes = file_put_contents($file, $data, LOCK_EX);
		if(false === $bytes) {
			throw new Exception\RuntimeException('Failed to save the key list to disk');
		}
		return true;
	}
	
	/**
	 * Check access to the current configured base path
	 * If $access is set to 'write' The directory is checked for write access
	 * @param string $access
	 * @return void
	 * @throws Exception\InvalidArgumentException if the directory doesn't exist, or is not readable
	 */
	protected function checkBasePath($access = 'read') {
		$path = $this->options->getBasePath();
		if(!is_dir($path)) {
			throw new \InvalidArgumentException("Base path for key storage must be an existing directory on the filesystem. I couldn\'t find {$path}");
		}
		if(!is_readable($path)) {
			throw new \InvalidArgumentException("The provided key storage directory cannot be read: {$path}");
		}
		if(strtolower($access) === 'write') {
			if(!is_writable($path)) {
				throw new \InvalidArgumentException("The provided key storage directory cannot be written to: {$path}");
			}
		}
	}
}