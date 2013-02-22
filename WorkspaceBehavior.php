<?php

class WorkspaceBehavior extends Behavior
{

    /**
     * The prefix for the additional table columns.
     *
     * @var string
     */
    private $prefix = 'workspace_';

    /**
     * @var
     */
    private $builder;

    /**
     * @var Table
     */
    private $versionTable;

    /**
     * @var string
     */
    private $versionTableName;

    /**
     * The callable where wo get our current workspace id
     *
     * @var callable
     */
    private $workspaceGetter = '\\Core\\WorkspaceManager::getCurrent';

    /**
     * @param callable $workspaceGetter
     */
    public function setWorkspaceGetter($workspaceGetter) {
        $this->workspaceGetter = $workspaceGetter;
    }

    /**
     * @return callable
     */
    public function getWorkspaceGetter() {
        return $this->workspaceGetter;
    }

    /**
     * Modifies all tables with our behaviour.
     */
    public function modifyDatabase()
    {
        foreach ($this->getDatabase()->getTables() as $table) {
            if ($table->hasBehavior($this->getName())) {
                // don't add the same behavior twice
                continue;
            }
            if (property_exists($table, 'isVersionTable')) {
                // don't add the behavior to archive tables
                continue;
            }
            $b = clone $this;
            $table->addBehavior($b);
        }
    }

    /**
     * Modifies a table
     */
    public function modifyTable()
    {
        $table = $this->getTable();

        // add the column if not present
        if(!$table->hasColumn($this->prefix.'id')) {
            $table->addColumn(array(
                                   'name'    => $this->prefix.'id',
                                   'type'    => 'INTEGER',
                                   'primaryKey' => 'true'
                              ));
        }

        if(!$table->hasColumn($this->prefix.'action')) {
            $table->addColumn(array(
                                   'name'    => $this->prefix.'action',
                                   'type'    => 'INTEGER'
                              ));
        }

        if(!$table->hasColumn($this->prefix.'action_date')) {
            $table->addColumn(array(
                                   'name'    => $this->prefix.'action_date',
                                   'type'    => 'INTEGER'
                              ));
        }

        if(!$table->hasColumn($this->prefix.'action_user')) {
            $table->addColumn(array(
                                   'name'    => $this->prefix.'action_user',
                                   'type'    => 'INTEGER'
                              ));
        }

        $this->addVersionTable();

        $table->setBasePeer('\WorkspaceBehaviorPeer');

        /*
        //pk index
        $index = new Index();
        foreach ($table->getColumns() as $column) {
            if ($column->isPrimaryKey()) {
                $this->pks[] = clone $column;
                if ($column->getName() != $this->prefix.'rev')
                    $index->addColumn(array('name' => $column->getName()));
            }
        }
        $index->addColumn(array('name' => $this->prefix.'id'));
        $table->addIndex($index);
        */


    }

    /**
     * Adds version table.
     */
    public function addVersionTable(){
        $table = $this->getTable();
        $database = $table->getDatabase();


        $this->versionTableName = $this->getParameter('version_table');
        if (!$this->versionTableName){
            if ($database->getTablePrefix() && $start = strlen($database->getTablePrefix())){
                $this->versionTableName = substr($table->getName() . '_version', $start);
            } else {
                $this->versionTableName = $table->getName() . '_version';
            }
        }

        if (!$database->hasTable($this->versionTableName)) {
            // create the version table
            $versionTable = $database->addTable(array(
                 'name'      => $this->versionTableName,
                 'phpName'   => $table->getPhpName().'Version',
                 'package'   => $table->getPackage(),
                 'schema'    => $table->getSchema(),
                 'namespace' => $table->getNamespace() ? '\\' . $table->getNamespace() : null,
            ));
            $versionTable->isVersionTable = true;


            // copy all the columns
            foreach ($table->getColumns() as $column) {
                $columnInVersionTable = clone $column;
                $columnInVersionTable->clearInheritanceList();

                if ($columnInVersionTable->hasReferrers()) {
                    $columnInVersionTable->clearReferrers();
                }

                if ($columnInVersionTable->isAutoincrement()) {
                    $columnInVersionTable->setAutoIncrement(false);
                }
                $versionTable->addColumn($columnInVersionTable);
            }

            $versionTable->addColumn(array(
                                          'name'    => $this->prefix.'rev',
                                          'type'    => 'INTEGER',
                                          'primaryKey' => 'true',
                                          'autoIncrement' => 'true'
                                     ));

            /*
            // create the foreign key
            $fk = new ForeignKey();
            $fk->setForeignTableCommonName($table->getCommonName());
            $fk->setForeignSchemaName($table->getSchema());
            $fk->setOnDelete('CASCADE');
            $fk->setOnUpdate(null);

            $tablePKs = $table->getPrimaryKey();
            foreach ($versionTable->getPrimaryKey() as $key => $column) {
                $fk->addReference($column, $tablePKs[$key]);
            }
            $versionTable->addForeignKey($fk);
            */

            $this->versionTable = $versionTable;


            // every behavior adding a table should re-execute database behaviors
            // see bug 2188 http://www.propelorm.org/changeset/2188
            foreach ($database->getBehaviors() as $behavior) {
                $behavior->modifyDatabase();
            }

        } else {
            $this->versionTable = $database->getTable($this->versionTableName);
        }
    }

    public function staticAttributes(){

        return "
        public static \$workspaceBehaviorVersionName = '{$this->versionTable->getName()}';
        public static \$workspaceBehaviorPrefix = '{$this->prefix}';
        ";
    }

    private function getColumnConstant($columnName, $builder = null){
        if (!$builder) $builder = $this->builder;
        return $builder->getColumnConstant($this->getTable()->getColumn($columnName));
    }


    protected function getColumnSetter($columnName){
        return 'set' . $this->getTable()->getColumn($columnName)->getPhpName();
    }

    protected function getColumnGetter($columnName){
        return 'get' . $this->getTable()->getColumn($columnName)->getPhpName();
    }

    /**
     * Adds the default workspaceId if not set.
     *
     * @param $builder
     * @return string The code to put at the hook
     */

    public function preInsert($builder)
    {

        return "

//set default values
if (\$this->isModified() && !\$this->isColumnModified(" . $this->getColumnConstant($this->prefix.'id', $builder) . "))
    \$this->" . $this->getColumnSetter($this->prefix.'id') . "(".$this->workspaceGetter.");

if (\$this->isModified() && !\$this->isColumnModified(" . $this->getColumnConstant($this->prefix.'action', $builder) . "))
    \$this->" . $this->getColumnSetter($this->prefix.'action') . "(1); //created

if (\$this->isModified() && !\$this->isColumnModified(" . $this->getColumnConstant($this->prefix.'action_date', $builder) . "))
    \$this->" . $this->getColumnSetter($this->prefix.'action_date') . "(time());

";
    }


    public function queryMethods($builder){

        $this->builder = $builder;
        $script = '';
        $this->addFilterByWorkspace($script);
        return $script;

    }

    protected function addFilterByWorkspace(&$script){

        $table = $this->getTable();
        $workspaceId = $table->getColumn($this->prefix.'id')->getPhpName();

        $script .= "
/**
 * Filters all items by given workspace
 *
 * @return    " . $this->builder->getStubQueryBuilder()->getClassname() . " The current query
 */
public function filterByWorkspace(\$workspaceId){
    return \$this->filterBy{$workspaceId}(\$workspaceId+0);
}
";

    }

    public function preSelectQuery($builder){
        $this->builder = $builder;

        //$current = $this->getColumnConstant($this->prefix.'current');
        $id = $this->getColumnConstant($this->prefix.'id');

        $table = $this->getTable();
        $id = $table->getColumn($this->prefix.'id')->getPhpName();

        return "//HI WAS GEHT

\$this->filterBy$id(".$this->workspaceGetter.");

";
    }

    public function staticMethods($builder){
        $this->builder = $builder;
        $script = '';
        $this->addGetWorkspaceId($script);
        return $script;
    }

    public function addGetWorkspaceId(&$script){

        $script .= "
public static function getWorkspaceId(){
    return call_user_func_array(".var_export($this->workspaceGetter, true).", array());
}
";

    }
}