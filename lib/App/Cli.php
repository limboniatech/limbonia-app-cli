<?php
namespace Limbonia\App;

/**
 * Limbonia CLI App Class
 *
 * This allows the basic app to run in the command line environment
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Cli extends \Limbonia\App
{
  /**
   * This constant tells processOptions that this option may *not* have a value associated with it
   */
  const OPTION_VALUE_NONE = 0;

  /**
   * This constant tells processOptions that this option may have a value associated with it but is not required
   */
  const OPTION_VALUE_ALLOW = 1;

  /**
   * This constant tells processOptions that this option must have a value associated with it
   */
  const OPTION_VALUE_REQUIRE = 2;

  /**
   * The name of the view to display
   *
   * @var string
   */
  protected $sViewName = '';

  /**
   * The description of the view to display in the help
   *
   * @var string
   */
  protected $sTempalteDesc = 'This utility does nothing but display this help including available modes';

  /**
   * List of command line options that should be processed and what they do
   *
   * @var array
   */
  protected $hOptionList =
  [
    [
      'short' => 'h',
      'long' => 'help',
      'desc' => 'Print this help screen',
      'value' => self::OPTION_VALUE_NONE
    ],
    [
      'long' => 'debug',
      'desc' => 'Set the debug level of this utility, if no value is specified then it defaults to highest debug level available.',
      'value' => self::OPTION_VALUE_ALLOW
    ]
  ];

  protected $sMode = '';

  protected $sCliName = '';

  /**
   * The CLI app constructor
   *
   * NOTE: This constructor should only be used by the factory and *never* directly
   *
   * @param array $hConfig - A hash of configuration data
   */
  protected function __construct(array $hConfig = [])
  {
    $oServer = \Limbonia\Input::singleton('server');
    $this->sCliName = preg_replace("/^limbonia_/", '', basename($oServer['argv'][0]));

    $hOptions = getopt('', ['mode::']);
    $this->sMode = empty($hOptions) ? $this->sCliName : $hOptions['mode'];

    $this->oRouter = \Limbonia\Router::fromUri(strtolower(preg_replace("#_#", '/', $this->sMode)));
    parent::__construct($hConfig);
  }

  /**
   * Display the help information
   */
  public function displayHelp()
  {
    $sHelp = "
Usage:
$this->sViewName [options]

$this->sTempalteDesc

Options:\n";

    $iMaxLength = 2;

    foreach ($this->hOptionList as $hOption)
    {
      if (isset($hOption['long']))
      {
        $iTemp = strlen($hOption['long']);

        if ($iTemp > $iMaxLength)
        {
          $iMaxLength = $iTemp;
        }
      }
    }

    foreach ($this->hOptionList as $hOption)
    {
      $bShort = isset($hOption['short']);
      $bLong = isset($hOption['long']);

      if (!$bShort && !$bLong)
      {
        continue;
      }

      if ($bShort && $bLong)
      {
        $sOpt = '-' . $hOption['short'] . ', --' . $hOption['long'];
      }
      elseif ($bShort && !$bLong)
      {
        $sOpt = '-' . $hOption['short'] . "\t";
      }
      elseif (!$bShort && $bLong)
      {
        $sOpt = '    --' . $hOption['long'];
      }

      $sOpt = str_pad($sOpt, $iMaxLength + 7);
      $sHelp .= "\t$sOpt\t\t{$hOption['desc']}\n\n";
    }

    die($sHelp . "\n");
  }

  /**
   * Update the view description to the specified value
   *
   * @param string $sDesc
   */
  public function setDescription($sDesc)
  {
    $this->sTempalteDesc = $sDesc;
  }

  /**
   * Process the specified command line options against the internal option list and return the list of active options
   *
   * @return array
   */
  public function processOptions()
  {
    $sShortOptions = '';
    $aLongOptions = [];

    foreach ($this->hOptionList as $hOption)
    {
      $sOptionValueMod = '';

      if (isset($hOption['value']))
      {
        if ($hOption['value'] == self::OPTION_VALUE_ALLOW)
        {
          $sOptionValueMod = '::';
        }
        elseif ($hOption['value'] == self::OPTION_VALUE_REQUIRE)
        {
          $sOptionValueMod = ':';
        }
      }

      if (isset($hOption['short']))
      {
        $sShortOptions .= $hOption['short'] . $sOptionValueMod;
      }

      if (isset($hOption['long']))
      {
        $aLongOptions[] = $hOption['long'] . $sOptionValueMod;
      }
    }

    $hActiveOptions = getopt($sShortOptions, $aLongOptions);

    if (isset($hActiveOptions['h']) || isset($hActiveOptions['help']))
    {
      $this->displayHelp();
    }

    return $hActiveOptions;
  }

  /**
   * Add a new option to the internal option list
   *
   * @param array $hOption
   */
  public function addOption($hOption)
  {
    $this->hOptionList[] = $hOption;
  }

  /**
   * Determine the view that should be used at this time and return it
   *
   * @return string
   * @throws \Exception
   */
  protected function generateViewFile()
  {
    $sViewFile = $this->viewFile($this->sCliName);

    //if the view file is not empty and not the current running file
    if (!empty($sViewFile) && $sViewFile !== $this->sCliName)
    {
      //then return it as is...
      $this->sViewName = $this->sCliName;
      return $sViewFile;
    }

    //attempt to use the mode for the view
    $sViewFile = $this->viewFile($this->sMode);

    if (!empty($sViewFile))
    {
      $this->sViewName = $this->sMode;
      return $sViewFile;
    }

    return $this->viewFile('default');
  }

  /**
   * Render this app instance for output and return that data
   *
   * @return string
   */
  protected function render()
  {
    $sControllerDriver = \Limbonia\Controller\Cli::driver($this->oRouter->controller);

    if (empty($sControllerDriver))
    {
      try
      {
        $aAvailableModes = [];

        foreach (\Limbonia\App::viewDirs() as $sDir)
        {
          foreach (glob($sDir . '/' . $this->type . '/*.php') as $sFileName)
          {
            $aAvailableModes[] = basename($sFileName, '.php');
          }

          foreach (glob($sDir . '/' . $this->type . '/*/*.php') as $sFileName)
          {
            $aAvailableModes[] = basename(dirname($sFileName)) . '_' . basename($sFileName, '.php');
          }
        }

        $aAvailableModes = array_unique($aAvailableModes);
        sort($aAvailableModes);
        $iPos = array_search('default', $aAvailableModes);

        if (false !== $iPos)
        {
          unset($aAvailableModes[$iPos]);
        }

        $iPos = array_search('error', $aAvailableModes);

        if (false !== $iPos)
        {
          unset($aAvailableModes[$iPos]);
        }

        if (count($aAvailableModes) > 0)
        {
          $this->addOption
          ([
            'long' => 'mode',
            'value' => \Limbonia\App\Cli::OPTION_VALUE_REQUIRE,
            'desc' => "This utility has the following built-in modes:\n\t\t\t\t" . implode("\n\t\t\t\t", $aAvailableModes)
          ]);
        }

        $this->processOptions();
        return $this->viewRender($this->generateViewFile());
      }
      catch (Exception $e)
      {
        $this->viewData('failure', 'Failed to generate the requested data: ' . $e->getMessage());
        return $this->viewRender('error');
      }
    }

    try
    {
      $oCurrentController = $this->controllerFactory($sControllerDriver);
      $this->sViewName = strtolower($sControllerDriver) . '_' . $this->oRouter->action;
      $oCurrentController->prepareView();
      $this->viewData('options', $this->processOptions());
      $sControllerView = $oCurrentController->getView();
      return $this->viewRender($sControllerView);
    }
    catch (\Exception $e)
    {
      $this->viewData('failure', "The controller {$this->oRouter->controller} could not be instaniated: " . $e->getMessage());
      return $this->viewRender('error');
    }
  }

  /**
   * Run everything needed to react to input and display data in the way this app is intended
   */
  public function run()
  {
    try
    {
      $this->viewData('app', $this);
      $this->oUser = $this->generateUser();
    }
    catch (\Exception $e)
    {
      echo $e->getMessage() . "\n";
    }

    $this->viewData('currentUser', $this->oUser);
    die($this->render());
  }
}