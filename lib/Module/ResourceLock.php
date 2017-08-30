<?php
namespace Omniverse\Module;

/**
 * Omniverse Resource Lock Module class
 *
 * Admin module for handling site resource locks
 *
 * @author Lonnie Blansett <lonnie@omniverserpg.com>
 * @version $Revision: 1.1 $
 * @package Omniverse
 */
class ResourceLock extends \Omniverse\Module
{
  /**
   * List of column names that should remain static
   *
   * @var array
   */
  protected $aStaticColumn = [];

  /**
   * Generate and return the HTML for the specified form field based on the specified information
   *
   * @param string $sName
   * @param string $sValue
   * @param array $hData
   * @param boolean $bInTable - Should the returned HTML use a table to contain the data
   * @return string
   */
  public function getFormField($sName, $sValue = null, $hData = [], $bInTable = false)
  {
    if ($sName == 'Resource')
    {
      $oSelect = $this->getController()->widgetFactory('Select', "$this->sModuleName[Resource]");
      $oSelect->addOption('Select a resource', '');

      foreach ($_SESSION['ResourceList'] as $sResource => $hComponent)
      {
        if ($sValue == $sResource)
        {
          $oSelect->setSelected($sResource);
        }

        $oSelect->addOption($sResource);
      }

      if ($bInTable)
      {
        return "<tr class=\"OmnisysField\"><th class=\"OmnisysFieldName\">Resource:</th><td class=\"OmnisysFieldValue\">" . $oSelect . "</td></tr>";
      }

      return "<div class=\"OmnisysField\"><span class=\"OmnisysFieldName\">Resource:</span><span class=\"OmnisysFieldValue\">" . $oSelect . "</span></div>";
    }

    if ($sName == 'Component')
    {
      $oSelect = $this->getController()->widgetFactory('Select', "$this->sModuleName[Component]");
      $oSelect->addOption('Select a component', '');

      //since I'm setting the name for the Resource and Component objects above, I can depend on their ids below
      $sScript = "var resource = document.getElementById('{$this->sModuleName}Resource');\n";
      $sScript .= "var component = document.getElementById('{$this->sModuleName}Component');\n";
      $sScript .= "\nfunction updateComponent()\n";
      $sScript .= "{\n";
      $sScript .= "  currentResource = resource.options[resource.selectedIndex].value\n";
      $sScript .= "\n";
      $sScript .= "  with (component)\n";
      $sScript .= "  {\n";
      $sScript .= "    for (i = options.length - 1; i > 0; i--) { options[i] = null; }\n";
      $sScript .= "    switch (currentResource)\n";
      $sScript .= "    {\n";

      foreach ($_SESSION['ResourceList'] as $sResource => $hComponent)
      {
        $sScript .= "      case '".str_replace("'", "\'", $sResource)."':\n";
        $i = 1;

        foreach ($hComponent as $sName => $sDescription)
        {
          $sScript .= "        options[$i] = new Option('" . str_replace("'", "\'", $sName) . ":  " . str_replace("'", "\'", $sDescription) . "', '" . str_replace("'", "\'", $sName) . "');\n";
          $i++;
        }

        $sScript .= "        break;\n";
      }

      $sScript .= "    }\n";
      $sScript .= "  }\n";
      $sScript .= "}\n";
      $sScript .= "\n";
      $sScript .= "resource.onchange = updateComponent\n";
      $sScript .= "updateComponent();\n";
      $sScript .= "\n";
      $sScript .= "for (i = component.options.length - 1; i > 0; i--)\n";
      $sScript .= "{\n";
      $sScript .= "  if (component.options[i].value == '" . str_replace("'", "\'", $sValue) . "')\n";
      $sScript .= "  {\n";
      $sScript .= "    component.selectedIndex = i;\n";
      $sScript .= "  }\n";
      $sScript .= "}\n";

      $oSelect->writeJavascript($sScript);

      if ($bInTable)
      {
        return "<tr class=\"OmnisysField\"><th class=\"OmnisysFieldName\">Component:</th><td class=\"OmnisysFieldValue\">" . $oSelect . "</td></tr>";
      }

      return "<div class=\"OmnisysField\"><span class=\"OmnisysFieldName\">Component:</span><span class=\"OmnisysFieldValue\">" . $oSelect . "</span></div>";
    }

    return parent::getFormField($sName, $sValue, $hData, $bInTable);
  }

  /**
   * Generate the search results table headers in the specified grid object
   *
   * @param \Omniverse\Widget\Table $oSortGrid
   * @param string $sColumn
   */
  public function processSearchGridHeader(\Omniverse\Widget\Table $oSortGrid, $sColumn)
  {
    return parent::processSearchGridHeader($oSortGrid, ($sColumn == 'KeyID' ? 'Name' : $sColumn));
  }

  /**
   * Generate and return the value of the specified column
   *
   * @param \Omniverse\Item $oItem
   * @param string $sColumn
   * @return mixed
   */
  public function getColumnValue(\Omniverse\Item $oItem, $sColumn)
  {
    return $sColumn == 'KeyID' ? $this->getController()->itemFromId('ResourceKey', $oItem->keyId)->name : parent::getColumnValue($oItem, $sColumn);
  }
}