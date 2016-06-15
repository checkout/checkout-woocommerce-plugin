<?php

namespace Api\PCI;

class Api_Autoloader {
  
  private static $_instance;
  
  public static function instance()
  {

      if (!self::$_instance) {
          $class = __CLASS__;
          self::$_instance = new $class();
      }
      return self::$_instance;
  }

  function autoload($class)
  {
      $classNameArray = explode('_',$class);
      $includePath = get_include_path();
      set_include_path($includePath);
      $path = '';

      if(!empty($classNameArray)) {

          $path = __DIR__.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $classNameArray). '.php';

          if(file_exists($path)) {

              require_once $path;

          }

      } else {
         
         throw new Exception("Unable to load $class.");
      }

  }

  public static function register() 
  { 
    spl_autoload_extensions('.php');
    spl_autoload_register(array(self::instance(), 'autoload'));
  }

}

$autoload = new Api_Autoloader();
Api_Autoloader::register();
