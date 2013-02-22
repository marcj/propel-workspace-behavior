<?php

/**
 * The propel basePeer class for tables with `workspace` behavior.
 *
 */
class WorkspaceBehaviorPeer extends \Core\PropelBasePeer {

    private static function appendWorkspaceInfo(&$criteria, $pMode = 'select'){

        $dbMap = Propel::getDatabaseMap($criteria->getDbName());

        $tableName = $criteria->getPrimaryTableName();

        if (!$tableName){
            $keys = $criteria->keys();
            if (!empty($keys))
                $tableName = $criteria->getTableName( $keys[0] );
            else
                throw new PropelException("Database workspace info appending attempted without anything specified. Were should I get the target table if you do not specify any criterion?");
        }

        $table = $dbMap->getTable($tableName);
        $peer = $table->getPeerClassname();

        $prefix = $peer::$workspaceBehaviorPrefix;

        $colId = $table->getColumn($prefix.'id')->getFullyQualifiedName();
        $colAction = $table->getColumn($prefix.'action')->getFullyQualifiedName();

        if (!$criteria->containsKey($colId))
            $criteria->add($colId, $peer::getWorkspaceId());

        if (!$criteria->containsKey($colAction)){
            if ($pMode == 'insert')
                $criteria->add($colAction, 1); //created
            else if ($pMode == 'delete')
                $criteria->add($colAction, 0); //deleted
            else if ($pMode == 'updated')
                $criteria->add($colAction, 2); //updated
        }

    }

    public static function doBackupRecord(\Criteria $criteria, PropelPDO $con){

        $db = Propel::getDB($criteria->getDbName());
        $dbMap = Propel::getDatabaseMap($criteria->getDbName());

        $keys = $criteria->keys();
        if (!empty($keys))
            $tableName = $criteria->getTableName( $keys[0] );
        else
            throw new PropelException("Database insert attempted without anything specified to insert");

        $tableMap = $dbMap->getTable($tableName);

        $whereClause = array();
        $peer = $tableMap->getPeerClassname();
        $versionTable = $peer::$workspaceBehaviorVersionName;
        $originTable = $tableMap->getName();

        $tables = $criteria->getTablesColumns();
        if (empty($tables)) {
            throw new \PropelException("Empty Criteria");
        }

        $fields = array_keys($tableMap->getColumns());
        $fields = implode(', ', $fields);
        foreach ($tables as $tableName => $columns) {

            $whereClause = array();
            $params = array();
            $stmt = null;
            try {

                foreach ($columns as $colName) {
                    $sb = "";
                    $criteria->getCriterion($colName)->appendPsTo($sb, $params);
                    $whereClause[] = $sb;
                }

                $sql = sprintf(
                    "INSERT INTO %s (%s) SELECT %s FROM %s WHERE %s",
                    $versionTable, $fields, $fields, $originTable, implode(" AND ", $whereClause));

                $stmt = $con->prepare($sql);
                $db->bindValues($stmt, $params, $dbMap);
                $stmt->execute();
                $stmt->closeCursor();

            } catch (Exception $e) {
                Propel::log($e->getMessage(), Propel::LOG_ERR);
                throw new PropelException(sprintf('Unable to execute INSERT INTO statement [%s]', $sql), $e);
            }

        } // for each table

    }

    public static function doDelete(\Criteria $criteria, PropelPDO $con)
    {

        //save current version
        static::doBackupRecord($criteria, $con);

        //save workspace_action=deleted
        $updateValues = clone $criteria;
        self::appendWorkspaceInfo($updateValues, 'delete');
        parent::doUpdate($criteria, $updateValues, $con);
        static::doBackupRecord($criteria, $con); //move the current record (marked as deleted) the version table


        //we can delete it now real, since we've backuped anything.
        return parent::doDelete($criteria, $con);
    }

    public static function doInsert(\Criteria $criteria, PropelPDO $con)
    {
        self::appendWorkspaceInfo($criteria, 'insert');
        return parent::doInsert($criteria, $con);
    }

    public static function doCount(\Criteria $criteria, PropelPDO $con = null)
    {
        self::appendWorkspaceInfo($criteria);
        return parent::doCount($criteria, $con);
    }


    public static function doSelect(\Criteria $criteria, PropelPDO $con = null)
    {
        static::appendWorkspaceInfo($criteria);
        return parent::doSelect($criteria, $con);
    }


    public static function doUpdate(\Criteria $selectCriteria, \Criteria $updateValues, PropelPDO $con)
    {

        static::doBackupRecord($selectCriteria, $con);
        self::appendWorkspaceInfo($updateValues, 'update');

        return parent::doUpdate($selectCriteria, $updateValues, $con);

    }

}