<?php

/**
 * @author Chaim Leichman, MIPO Technologies Ltd
 */

namespace mipotech\metaActiveRecord;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\db\Schema;

abstract class MetaActiveRecord extends ActiveRecord
{
	/** @var boolean $autoLoadMetaData Whether meta data should be loaded  */
	protected $autoLoadMetaData = true;

	/** @var boolean $autoSaveMetaFields Whether meta data should be loaded  */
	protected $autoSaveMetaFields = false;

	/** @var mixed $metaData Array of the this record's meta data */
	protected $metaData = null;

	/** @var array $metaDataUpdateQueue Queue of meta data key-value pairs to update */
	protected $metaDataUpdateQueue = array();


	/**
	 * Override __get of yii\db\ActiveRecord
	 *
	 * @param string $name the property name
	 * @return mixed
	 */
	public function __get($name)
	{
		if($this->hasAttribute($name))
			return parent::__get($name);
		else
			return $this->getMetaAttribute($name);
	}

	/**
	 * Override __get of yii\db\ActiveRecord
	 *
	 * @param string $name the property name or the event name
     * @param mixed $value the property value
	 */
	public function __set($name, $value)
	{
		if ($this->hasAttribute($name))
			parent::__set($name, $value);
		else
		{
			if ($this->autoSaveMetaFields && !$this->isNewRecord)
				$this->setMetaAttribute($name, $value);
			else
				$this->enqueueMetaUpdate($name, $value);
		}
	}

	/**
	 * Catch the afterFind event to load the meta data if the
	 * $autoLoadMetaData flag is set to true
	 *
	 */
	public function afterFind()
	{
		if ($this->autoLoadMetaData)
			$this->loadMetaData();

		parent::afterFind();
	}

	/**
	 * Catch the afterSave event to save all of the queued meta data
	 *
	 */
	public function afterSave($insert, $changedAttributes)
	{
		$queue = $this->metaDataUpdateQueue;

		if (is_array($queue) && count($queue))
		{
			foreach ($queue as $name => $value)
				$this->setMetaAttribute($name, $value);

			$this->metaDataUpdateQueue = array();
		}

		parent::afterSave($insert, $changedAttributes);
	}

	/**
	 * Enqueue a meta key-value pair to be saved when the record is saved
	 *
	 * @param string $name the property name or the event name
     * @param mixed $value the property value
	 */
	protected function enqueueMetaUpdate($name, $value)
	{
		if(!is_array($this->metaDataUpdateQueue))
			$this->metaDataUpdateQueue = array();

		$this->metaDataUpdateQueue[$name] = $value;
	}

	/**
	 * Load the meta data for this record
	 *
	 * @return void
	 */
	protected function loadMetaData()
	{
		$rows = (new Query)
		    ->select('*')
		    ->from($this->metaTableName())
		    ->where([
		    	'record_id'	=> $this->{$this->getPkName()}
		    ])
		    ->all();

		$this->metaData = $rows;
	}

	/**
	 * Return the name of the meta table associated with this model
	 *
	 * @return string
	 */
	public function metaTableName()
	{
		$tblName = self::tableName();

		// Add _meta prefix to parent table name
		$tblName = str_replace('}}', '_meta}}', $tblName);

		// Resolve the actual name of the meta table
		$tblName = str_replace('{{%', Yii::$app->db->tablePrefix ?: '', $tblName);
		$tblName = str_replace('}}', '', $tblName);

		return $tblName;
	}

	/**
	 * @link https://github.com/yiisoft/yii2/issues/6533
	 * @return string
	 */
	public function getDbName()
	{
		$db = Yii::$app->db;
		$dsn = $db->dsn;
		$name = 'dbname';

		if (preg_match('/' . $name . '=([^;]*)/', $dsn, $match)) {
            return $match[1];
        } else {
            return null;
        }
	}

	/**
	 * @param boolean $autoCreate Create the table if it does not exist
	 * @return boolean If table exists
	 */
	protected function assertMetaTable($autoCreate = false)
	{
		$row = (new Query)
		    ->select('*')
		    ->from('information_schema.tables')
		    ->where([
		    	'table_schema'	=> $this->getDbName(),
		    	'table_name'	=> $this->metaTableName()
		    ])
		    ->limit(1)
		    ->all();

		if(null === $row)
		{
			if($autoCreate)
			{
				$this->createMetaTable();
				return true;
			}
			else
				return false;
		}
		else
			return true;
	}

	/**
	 *
	 */
	protected function createMetaTable()
	{
		$db = Yii::$app->db;
		$tbl = $this->metaTableName();

		$ret = $db
				->createCommand()
				->createTable($tbl, [
					'id'		=> Schema::TYPE_BIGPK,
					'record_id' => Schema::TYPE_BIGINT.' NOT NULL default \'0\'',
					'meta_key'	=> Schema::TYPE_STRING.' default NULL',
					'meta_value'=> 'longtext',
				], 'ENGINE=MyISAM  DEFAULT CHARSET=utf8')
				->execute();

		if($ret)
		{
			$db
				->createCommand()
				->createIndex('UNIQUE_META_RECORD', $tbl, ['record_id', 'meta_key'], true)
				->execute();
		}

		return $ret;
	}

	protected function getPkName()
	{
		$pk = $this->primaryKey();
		$pk = $pk[0];

		return $pk;
	}

	/**
	 * Return the value of the named meta attribute
	 *
	 * @param string $name Property name
	 * @return mixed Property value
	 */
	protected function getMetaAttribute($name)
	{
		if(!$this->assertMetaTable())
			return null;

		$row = (new Query)
		    ->select('meta_value')
		    ->from($this->metaTableName())
		    ->where([
		    	'record_id'	=> $this->{$this->getPkName()},
		    	'meta_key'	=> $name
		    ])
		    ->limit(1)
		    ->one();

		return is_array($row) ? $row['meta_value'] : null;
	}

	/**
	 * Set the value of the named meta attribute
	 *
	 * @param string $name the property name or the event name
     * @param mixed $value the property value
	 */
	protected function setMetaAttribute($name, $value)
	{
		// Assert that the meta table exists,
		// and create it if it does not
		$this->assertMetaTable(true);

		$db = Yii::$app->db;
		$tbl = $this->metaTableName();

		$pk = $this->getPkName();

		// Check if we need to create a new record or update an existing record
		$currentVal = $this->getMetaAttribute($name);
		if(is_null($currentVal))
		{
			$ret = $db
				->createCommand()
				->insert($tbl, [
					'record_id'	=> $this->{$pk},
					'meta_key'	=> $name,
					'meta_value'=> is_scalar($value) ? $value : serialize($value)
				])
				->execute();
		}
		else
		{
			$ret = $db
				->createCommand()
				->update($tbl, [
					'meta_value'=> is_scalar($value) ? $value : serialize($value)
				], "record_id = '{$this->$pk}' AND meta_key = '{$name}'")
				->execute();
		}

		// If update succeeded, save the new value right away
		if($ret)
			$this->metaData[$name] = $value;

		return $ret;
	}
}
