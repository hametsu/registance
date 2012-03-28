<?php
//Singletonクラスの実装
class Singleton {

	private static $instance = array();
	final private function __construct(){

		if (isset(self::$instance[get_called_class()])){
			throw new Exception ("This is Singleton class!!");
		}
		static::initialize();
	}

	protected function initialize() {
	}

	final public static function getInstance() {
		$class = get_called_class();
		if(!isset(self::$instance[$class])) {
			self::$instance[$class] = new static();
		}
			return self::$instance[$class];
		}

		final private function __clone(){

			throw new Exception("This is Singleton class!!");

		}
	}


