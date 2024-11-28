<?php

namespace Mage\ProductView\Setup;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Mage\DB2\DB2 as DB;

abstract class ViewTableCreate implements InstallSchemaInterface
{
    public $viewName;
    public $viewSQL;
    public $newTableName;

    abstract public function getSelect();

    protected function setViewName($name)
    {
        $this->viewName = $name;
    }

    public function createViewTableFromSelect()
    {
        $createViewSql = "CREATE VIEW " . $this->viewName . " AS " . $this->getSelect(false);
        return $createViewSql;
    }

    public function dropViewSQL()
    {
        return "DROP VIEW IF EXISTS " . $this->viewName;
    }

    protected function createTableFromView()
    {
        try {
            // Fetch columns from the view
            $columns = DB::select("DESCRIBE {$this->viewName}");
            $newTableName = $this->viewName . "_MVIEW";
            $this->newTableName = $newTableName;
            // Start creating the new table
            DB::schema()->create($newTableName, function (Blueprint $table) use ($columns) {
                $table->id(); // Add an ID column as the primary key
                foreach ($columns as $column) {
                    $type = $this->mapColumnType($column->Type); // Map MySQL types to Laravel Schema Builder types
                    $nullable = strpos($column->Null, 'YES') !== false;

                    // Add column dynamically
                    $col = $table->$type($column->Field);
                    if ($nullable) {
                        $col->nullable();
                    }
                }
                $table->timestamps(); // Add created_at and updated_at columns
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '42S01') { // Error code for "Table already exists"
                echo $e->getMessage();
            }
        } catch (\Exception $e) {
            // Ignoring Table exist message
            echo $e->getMessage();
        }
    }

    public function populateTableFromView($newTableName)
    {
        DB::table($this->viewName)->orderBy('entity_id') // Ensure rows are processed in a consistent order
            ->chunk(10, function ($rows) use ($newTableName) {
                $insertData = $rows->map(function ($row) {
                    return (array) $row; // Convert object to associative array
                })->toArray();

                DB::table($newTableName)->insert($insertData);
            });
    }

    protected function mapColumnType($mysqlType)
    {
        if (strpos($mysqlType, 'int') !== false) {
            return 'integer';
        } elseif (strpos($mysqlType, 'varchar') !== false || strpos($mysqlType, 'text') !== false) {
            //  Row size too large. The maximum row size for the used table type, not counting BLOBs, is 65535.
            return 'text'; //'string';
        } elseif (strpos($mysqlType, 'decimal') !== false || strpos($mysqlType, 'float') !== false) {
            return 'decimal';
        } elseif (strpos($mysqlType, 'datetime') !== false) {
            return 'dateTime';
        } elseif (strpos($mysqlType, 'json') !== false) {
            return 'json';
        }
        return 'string'; // Default type
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $installer->getConnection()->query($this->dropViewSQL()); // Drop the view if it exists
        $installer->getConnection()->query($this->createViewTableFromSelect()); // Create the view
        $this->createTableFromView();
        $this->populateTableFromView($this->newTableName);

        $installer->endSetup();
    }
}