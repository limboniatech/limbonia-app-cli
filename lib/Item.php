<?php
namespace Omniverse;

/**
 * Omniverse Item Class
 *
 * This is a wrapper around the around a row of item data that allows access to
 * the data
 *
 * @author Lonnie Blansett <lonnie@omniverserpg.com>
 * @version $Revision: 1.1 $
 * @package Omniverse
 */
class Item implements \ArrayAccess, \Countable, \SeekableIterator
{
  /**
   * The prepared statements that represent various item queries
   *
   * @var array
   */
  protected static $hStatement = [];

  /**
   * This object's data
   *
   * @var array
   */
  protected $hData = [];

  /**
   * List of columns that shouldn't be updated after the data has been created
   *
   * @var array
   */
  protected $aNoUpdate = [];

  /**
   * The stored database object
   *
   * @var Database;
   */
  protected $oDatabase = null;

  /**
   * The table that this object is referencing
   *
   * @var string
   */
  protected $sTable = '';

  /**
   * The name of the "ID" column associated with this object's table
   *
   * @var string
   */
  protected $sIdColumn = '';

  /**
   * List of names and their associated methods, used by __get to generate data
   *
   * @var array
   */
  protected $hAutoGetter =
  [
    'all' => 'getAll',
    'columns' => 'getColumns',
    'columnlist' => 'getColumnNames',
    'idcolumn' => 'getIDColumn',
    'table' => 'getTable'
  ];

  /**
   * List of names and their associated types, used by __get to generate item objects
   *
   * @var array
   */
  protected $hAutoExpand = [];

  /**
   * The list of objects associated with certain values of this object
   *
   * @var array
   */
  protected $hItemObjects = [];

  /**
   * Generate and return an empty item object based on the specified table.
   *
   * @param string $sTable
   * @param Database $oDatabase (optional)
   * @return Item
   */
  public static function factory($sTable, Database $oDatabase = null)
  {
    $sTypeClass = __NAMESPACE__ . '\\Item\\' . $sTable;

    if (\class_exists($sTypeClass, true))
    {
      return new $sTypeClass(null, $oDatabase);
    }

    $sTypeClass = __NAMESPACE__ . '\\Item\\' . ucfirst(strtolower($sTable));

    if (\class_exists($sTypeClass, true))
    {
      return new $sTypeClass(null, $oDatabase);
    }

    return new Item($sTable, $oDatabase);
  }

  /**
   * Generate and return an item object filled with data from the specified table id
   *
   * @param string $sTable
   * @param integer $iItem
   * @param Database $oDatabase (optional)
   * @return Item
   */
  public static function fromId($sTable, $iItem, Database $oDatabase = null)
  {
    $oItem = self::factory($sTable, $oDatabase);
    $oItem->load($iItem);
    return $oItem;
  }

  /**
   * Generate and return an item object filled with data from the specified array
   *
   * @param string $sTable
   * @param array $hItem
   * @param Database $oDatabase (optional)
   * @return Item
   */
  public static function fromArray($sTable, $hItem, Database $oDatabase = null)
  {
    $oItem = self::factory($sTable, $oDatabase);
    $oItem->setAll($hItem);
    return $oItem;
  }

  /**
   * Generate an item list based on the specified type and SQL query
   *
   * @param string $sType
   * @param string $sQuery
   * @param Database $oDatabase (optional)
   * @return \Omniverse\ItemList
   */
  public static function getList($sType, $sQuery, Database $oDatabase = null)
  {
    $oDatabase = $oDatabase instanceof \Omniverse\Database ? $oDatabase : \Omniverse\Controller::getDefault()->getDB();
    return new ItemList($sType, $oDatabase->query($sQuery));
  }

  /**
   * Generate an item list based on the specified type and search criteria
   *
   * @param string $sType - The type of Item to search for
   * @param array $hWhere - Where to search for the Items
   * @param string|array $xOrder - The order the Items should be returned
   * @param Database $oDatabase (optional) - The Database to perform the search in
   * @return \Omniverse\ItemList
   */
  public static function search($sType, $hWhere = [], $xOrder = null, Database $oDatabase = null)
  {
    return self::getList($sType, self::factory($sType, $oDatabase)->makeSearchQuery($hWhere, $xOrder), $oDatabase);
  }

  /**
   * The item constructor
   *
   * @param string $sType (optional)
   * @param \Omniverse\Database $oDatabase (optional)
   */
  public function __construct($sType = null, \Omniverse\Database $oDatabase = null)
  {
    if ($oDatabase instanceof \Omniverse\Database)
    {
      $this->oDatabase = $oDatabase;
    }

    if (empty($this->sTable))
    {
      $this->sTable = empty($sType) ? str_replace(__CLASS__ . '\\', '', get_class($this)) : $sType;
    }

    if (empty($this->sIdColumn))
    {
      $this->sIdColumn = $this->hasColumn('id');
    }

    $this->aNoUpdate[] = $this->sIdColumn;

    foreach ($this->getColumns() as $sColumn => $hColumnData)
    {
      $this->hData[$sColumn] = isset($hColumnData['Default']) ? $hColumnData['Default'] : null;
    }
  }

  /**
   * Return the database object that the data for this object was generated from
   *
   * @return \Omniverse\Database
   */
  public function getDB()
  {
    if (!\is_null($this->oDatabase))
    {
      return $this->oDatabase;
    }

    return \Omniverse\Controller::getDefault()->getDB();
  }

  /**
   * Return this object's list of column data
   *
   * @return array
   */
  public function getColumns()
  {
      return $this->getDB()->getColumns($this->sTable);
  }

  /**
   * Get list of column names
   *
   * @return array
   */
  public function getColumnNames()
  {
    return array_keys($this->getColumns());
  }

  /**
   * Return the column data for the specified column
   *
   * @param string $sColumn
   * @return array
   */
  public function getColumn($sColumn)
  {
    return $this->getDB()->getColumnData($this->sTable, $sColumn);
  }

  /**
   * Return the ID column, if there is one, for this object
   *
   * @return string
   */
  public function getIDColumn()
  {
    return $this->sIdColumn;
  }

  /**
   * Return the name of the table for this object
   *
   * @return string
   */
  public function getTable()
  {
    return $this->sTable;
  }

  /**
   * Loop through the specified array looking for keys that match column names.  For each match
   * set that column to the value for that key in the array then unset that value in the array.
   * After each matching key has been used return the remainder of the array.
   *
   * @param array $hItem
   * @return array
   */
  public function setAll(array $hItem = [])
  {
    $iID = null;
    $hLowerItem = \array_change_key_case($hItem, CASE_LOWER);

    foreach (array_keys($hLowerItem) as $sName)
    {
      //if the column exists the processit
      if ($sRealName = $this->hasColumn($sName))
      {
        //don't do the id column until last
        if ($sRealName == $this->sIdColumn)
        {
          $iID = $hLowerItem[$sName];
        }
        else
        {
          $this->__set($sRealName, $hLowerItem[$sName]);
        }

        unset($hLowerItem[$sName]);
      }
    }

    //finally do the id column
    if (!is_null($iID))
    {
      $this->__set($this->sIdColumn, $iID);
    }

    return $hLowerItem;
  }

  /**
   * Get a copy of all the data this object contains
   *
   * @param boolean $bFormatted Format the returned data?
   * @return array
   */
  public function getAll($bFormatted = false)
  {
    if (!$bFormatted)
    {
      return $this->hData;
    }

    $hData = [];
    $aDataName = array_keys($this->hData);

    foreach ($aDataName as $sName)
    {
      $hData[$sName] = $this->formatOutput($sName, $this->columns[$sName]['Type']);
    }

    return $hData;
  }

  /**
   * Get the type data for the specified column
   *
   * @param string $sColumn
   * @return string
   */
  protected function getColumnType($sColumn)
  {
    return $this->hasColumn($sColumn) ? strtolower($this->getColumn($sColumn)['Type']) : '';
  }

  /**
   * Format the specified value to valid input using type data from the specified column
   *
   * @param string $sColumn
   * @param mixed $xValue
   * @return mixed
   */
  protected function formatInput($sColumn, $xValue)
  {
    $sType = $this->getColumnType($sColumn);
    switch ($sType)
    {
      case 'boolean':
        //booleans are stored in the database as integer(1) type data (either 0 or 1)
        return (integer)(boolean)$xValue;

      case 'dollar':
        //replace dollar signs and commas first...
        $xValue = preg_replace('#$|,#', '', $xValue);

        //then try to cast it to a float...
        return (float)$xValue;

      case 'phone':
        //remove all non-numeric characters
        $xValue = preg_replace('#\D#', '', $xValue);

        //then try to cast it to a string...
        return (string)$xValue;

      //if the type isn't found then just return the original data
      default:
        return Database::filterValue($sType, $xValue);
    }
  }

  /**
   * Sets the specified values if possible
   *
   * @param string $sName
   * @param mixed $xValue
   */
  public function __set($sName, $xValue)
  {
    $sRealName = $this->hasColumn($sName);

    //this object is not allowed to change these after it's created...
    if (in_array($sRealName, $this->aNoUpdate) && $this->isCreated())
    {
      return;
    }

    if ($sRealName)
    {
      $this->hData[$sRealName] = $this->formatInput($sRealName, $xValue);
    }

    $sSetMethod = "set$sRealName";

    if (method_exists($this, $sSetMethod))
    {
      return call_user_func([$this, $sSetMethod], $xValue);
    }
  }

  /**
   * Format the specified value to valid output using type data from the specified column
   *
   * @param string $sName
   * @return mixed
   */
  protected function formatOutput($sName)
  {
    if (preg_match('/(.+?)List/', $sName, $aMatch))
    {
      $hColumn = $this->getColumn($aMatch[1]);
      $sType = strtolower(Database::columnType($hColumn['Type']));

      if (preg_match("#(.*?)\((.*?)\)#", $sType, $aMatch))
      {
        $sType = $aMatch[1];
        $sExtra = $aMatch[2];
      }

      if (in_array(strtolower($sType), ['set', 'enum']))
      {
        return explode(',', strtolower(preg_replace("/','/", ',', trim($sExtra, "'"))));
      }
    }

    $sExtra = null;
    $sType = $this->getColumnType($sName);
    $sRealName = $this->hasColumn($sName);
    $xValue = $sRealName ? $this->hData[$sRealName] : '';

    if (preg_match("#(.*?)\((.*?)\)#", $sType, $aMatch))
    {
      $sType = $aMatch[1];
      $sExtra = $aMatch[2];
    }

    switch ($sType)
    {
      case 'set':
        return explode(',', strtolower($xValue));

      case 'boolean':
        //booleans are stored in the database as integer(1) type data (either 0 or 1)
        return (boolean)$xValue;

      case 'dollar':
        //format the amount and put a $ in front of it
        return '$' . number_format((float)$xValue, 2);

      case 'phone':
        //format the number like this xxx-xxx-xxxx
        return preg_replace('#(\d\d\d)(\d\d\d)#', "$1-$2-", $xValue);

      default:
        //in most cases the data was formatted correctly when it was stored, so just spit it back out...
        return $xValue;
    }
  }

  /**
   * Get the specified data
   *
   * @param string $sName
   * @return mixed
   */
  public function __get($sName)
  {
    $sLowerName = strtolower($sName);

    if (isset($this->hAutoGetter[$sLowerName]))
    {
      return call_user_func([$this, $this->hAutoGetter[$sLowerName]]);
    }

    $sIDType = "{$sName}id";

    if ($this->hasColumn($sIDType))
    {
      if (!isset($this->hItemObjects[$sLowerName]))
      {
        $sType = isset($this->hAutoExpand[$sLowerName]) ? $this->hAutoExpand[$sLowerName] : $sName;

        try
        {
          $this->hItemObjects[$sLowerName] = self::fromId($sType, $this->__get($sIDType), $this->getDB());
        }
        catch (\Exception $e)
        {
          $this->hItemObjects[$sLowerName] = self::factory($sType, $this->getDB());
        }
      }

      if (!empty($this->hItemObjects[$sLowerName]))
      {
        return $this->hItemObjects[$sLowerName];
      }
    }

    if ($this->__isset($sName))
    {
      return $this->formatOutput($sName);
    }
  }

  /**
   * Does the specified column exist?
   *
   * @param string $sColumn
   * @return string -
   */
  public function hasColumn($sColumn)
  {
    return $this->getDB()->hasColumn($this->sTable, $sColumn);
  }

  /**
   * Determine if the specified value is set (exists) or not...
   *
   * @param string $sName
   * @return boolean
   */
  public function __isset($sName)
  {
    if ($this->hasColumn($sName))
    {
      return true;
    }

    if (isset($this->hAutoGetter[strtolower($sName)]))
    {
      return true;
    }

    if ($this->hasColumn("{$sName}id"))
    {
      return true;
    }

    if (preg_match('/(.+?)List/', $sName, $aMatch))
    {
      return $this->__isset($aMatch[1]);
    }

    return false;
  }

  /**
   * Unset the specified value
   *
   * @param string $sName
   */
  public function __unset($sName)
  {
    if ($this->__isset($sName))
    {
      $this->hData[$sName] = null;
    }
  }

  /**
   * Generate and return an SQL query for this object's table based on the specified crieria
   *
   * @param array $hWhere
   * @param string|array $xOrder
   * @return string
   */
  public function makeSearchQuery($hWhere = [], $xOrder = null)
  {
    return $this->getDB()->makeSearchQuery($this->sTable, $hWhere, $xOrder);
  }

  /**
   * Has this object been created in the database?
   *
   * @return boolean
   */
  protected function isCreated()
  {
    return isset($this->hData[$this->sIdColumn]) && is_numeric($this->hData[$this->sIdColumn]) && $this->hData[$this->sIdColumn] > 0;
  }

  /**
   * Created a row for this object's data in the database
   *
   * @return integer The ID of the row created on success or false on failure
   */
  protected function create()
  {
    $hData = $this->hData;
    unset($hData[$this->sIdColumn]);
    $iID = $this->getDB()->insert($this->sTable, $hData);

    if (empty($iID))
    {
      return false;
    }

    $this->hData[$this->sIdColumn] = $iID;
    return $iID;
  }

  /**
   * Update this object's data in the data base with current data
   *
   * @return integer The ID of this object on success or false on failure
   */
  protected function update()
  {
    return $this->getDB()->update($this->sTable, $this->id, $this->hData);
  }

  /**
   * Either create or update this object depending on if it's already been created or not
   *
   * @return integer The ID of this object on success or false on failure
   */
  public function save()
  {
    return $this->isCreated() ? $this->update() : $this->create();
  }

  /**
   * Set the data for this object to the row of data specified by the given item id.
   *
   * @param integer $iItemID
   * @throws Exception
   */
  public function load($iItemID)
  {
    if (!isset(self::$hStatement[$this->sTable]['load']))
    {
      self::$hStatement[$this->sTable]['load'] = $this->getDB()->prepare("SELECT * FROM $this->sTable WHERE $this->sIdColumn = :ItemId LIMIT 1");
    }

    settype($iItemID, 'integer');
    self::$hStatement[$this->sTable]['load']->bindParam(':ItemId', $iItemID, \PDO::PARAM_INT);
    $bSuccess = self::$hStatement[$this->sTable]['load']->execute();

    if (!$bSuccess)
    {
      $aError = self::$hStatement[$this->sTable]['load']->errorInfo();
      throw new \Exception("Failed to load data from $this->sTable: {$aError[2]}");
    }

    $hData = self::$hStatement[$this->sTable]['load']->fetch();

    if ($hData == false)
    {
      throw new \Exception("The table $this->sTable does not contain the $this->sIdColumn $iItemID!");
    }

    $this->setAll($hData);
  }

  /**
   * Delete the row representing this object from the database
   *
   * @return boolean
   */
  public function delete()
  {
    if (!$this->IsCreated())
    {
      return true;
    }

    return $this->getDB()->delete($this->sTable, $this->id);
  }

  /**
   * Set the specified array offset with the specified value
   *
   * @note This is an implementation detail of the ArrayAccess Interface
   *
   * @param mixed $xOffset
   * @param mixed $xValue
   */
  public function offsetset($xOffset, $xValue)
  {
    $this->__set($xOffset, $xValue);
  }

  /**
   * Unset the specified array offset
   *
   * @note This is an implementation detail of the ArrayAccess Interface
   *
   * @param mixed $xOffset
   */
  public function offsetUnset($xOffset)
  {
    $this->__unset($xOffset);
  }

  /**
   * Does the specified array offset exist?
   *
   * @note This is an implementation detail of the ArrayAccess Interface
   *
   * @param mixed $xOffset
   * @return boolean
   */
  public function offsetExists($xOffset)
  {
    return $this->__isset($xOffset);
  }

  /**
   * Return the value stored at the specified array offset
   *
   * @note This is an implementation detail of the ArrayAccess Interface
   *
   * @param mixed $xOffset
   * @return mixed
   */
  public function offsetget($xOffset)
  {
    return $this->__get($xOffset);
  }

  /**
   * Return the number of columns represented by this object
   *
   * @note This is an implementation detail of the Countable Interface
   *
   * @return integer
   */
  public function count()
  {
    return count($this->hData);
  }

  /**
   * Return the current value of this object's data array
   *
   * @note This is an implementation detail of the Iterator Interface
   *
   * @return mixed
   */
  public function current()
  {
    return $this->__get(key($this->hData));
  }

  /**
   * Return the key of the current value of this object's data array
   *
   * @note This is an implementation detail of the Iterator Interface
   *
   * @return mixed
   */
  public function key()
  {
    return key($this->hData);
  }

  /**
   * Move to the next value in this object's data
   *
   * @note This is an implementation detail of the Iterator Interface
   */
  public function next()
  {
    next($this->hData);
  }

  /**
   * Rewind to the first item of this object's data
   *
   * @note This is an implementation detail of the Iterator Interface
   */
  public function rewind()
  {
    reset($this->hData);
  }

  /**
   * Is the current value valid?
   *
   * @note This is an implementation detail of the Iterator Interface
   *
   * @return boolean
   */
  public function valid()
  {
    return $this->key() !== null;
  }

  /**
   * Move the value to the data represented by the specified key
   *
   * @note This is an implementation detail of the SeekableIterator Interface
   *
   * @param mixed $xKey
   * @throws \OutOfBoundsException
   */
  public function seek($xKey)
  {
    $this->rewind();

    while ($this->key() != $xKey)
    {
      $this->next();
    }

    if ($this->key() != $xKey)
    {
      throw new \OutOfBoundsException("Invalid seek position ($xKey)");
    }
  }
}