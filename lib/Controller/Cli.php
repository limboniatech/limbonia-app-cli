<?php
namespace Limbonia\Controller;

/**
 * Limbonia CLI Controller class
 *
 * This defines all the basic parts of an Limbonia controller
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Api extends Base implements \Limbonia\Interfaces\Controller\Cli
{
  use \Limbonia\Traits\Controller\Cli;
  use \Limbonia\Traits\DriverList;

  /**
   * Controller Factory
   *
   * @param string $sType - The type of controller to create
   * @param \Limbonia\App\Cli $oApp
   * @return \Limbonia\Interfaces\Controller\Cli
   */
  public static function factory($sType, \Limbonia\App\Cli $oApp)
  {
    return static::driverFactory($sType, $oApp);
  }

  /**
   * Instantiate an API controller
   *
   * @param \Limbonia\App\Cli $oApp
   * @param \Limbonia\Router $oRouter (optional)
   */
  protected function __construct(\Limbonia\App\Cli $oApp, \Limbonia\Router $oRouter = null)
  {
      parent::__construct($oApp, $oRouter);
  }
}
