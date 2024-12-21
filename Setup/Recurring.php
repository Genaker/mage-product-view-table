<?php

namespace Mage\ProductView\Setup;

use Illuminate\Database\Schema\Blueprint;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Mage\DB2\DB2 as DB;
use Mage\ViewTable\Setup\ViewTableCreate;

class Recurring extends ViewTableCreate
{

    public $viewName = "catalog_product_view";
    public $changeLogTableName = "catalog_product_attribute_cl";
    const PRODUCT_TYPE_ID = 4;

    public function getSelect($storeId = 0, $globalFalback = true, $join = false)
    {
        // Ensure $storeId is correctly typed
        $storeId = is_numeric($storeId) ? (int) $storeId : 0;

        $attributes = DB::table('eav_attribute')
            ->join('catalog_eav_attribute', 'eav_attribute.attribute_id', '=', 'catalog_eav_attribute.attribute_id')
            ->where('eav_attribute.entity_type_id', self::PRODUCT_TYPE_ID) // For product attributes only
            ->where('eav_attribute.backend_type', '!=', 'static') // Exclude static attributes
            ->where(function ($query) {
                $query->where('catalog_eav_attribute.is_visible_on_front', 1)
                    ->orWhere('catalog_eav_attribute.is_searchable', 1)
                    ->orWhere('catalog_eav_attribute.is_filterable', 1)
                    ->orWhere('catalog_eav_attribute.is_comparable', 1)
                    ->orWhere('catalog_eav_attribute.is_html_allowed_on_front', 1);
            })
            ->select('*')
        //->limit(100)
            ->get();

        $query = DB::table('catalog_product_entity AS p')
            ->select('p.entity_id as product_id', 'p.entity_id', 'p.sku'); // Select basic product data

        //General error: 1116 Too many tables; MariaDB can only use 61 tables in a join
        $hardLimit = 61;
        $counter = 1;
        foreach ($attributes as $attribute) {
            if ($attribute->attribute_code === "static") {
                continue;
            }
            // using join
            if ($join) {
                $counter++;
                if ($counter > 61) {
                    continue;
                }

                $tableAlias = "attr_{$attribute->attribute_id}"; // Unique alias for each join

                $query->leftJoin(
                    DB::raw("catalog_product_entity_{$attribute->backend_type} AS $tableAlias"), function ($join) use ($attribute, $tableAlias) {
                        $join->on("p.entity_id", "=", DB::raw("$tableAlias.entity_id"))
                            ->whereRaw("$tableAlias.attribute_id = $attribute->attribute_id");
                        $join->whereRaw("$tableAlias.store_id = $storeId");
                    }
                );
                $query->addSelect(DB::raw("$tableAlias.value AS `$attribute->attribute_code`"));
            } else // Using Subqueries
            {
                if ($counter === 1) {
                    $query->addSelect(DB::raw("$storeId AS `store_id`"));
                }
                if (!$globalFalback) {
                    $subquery = DB::table("catalog_product_entity_{$attribute->backend_type}")
                        ->select('value')
                        ->whereColumn("entity_id", "p.entity_id")
                        ->whereRaw("attribute_id = $attribute->attribute_id")
                        ->whereRaw("store_id = " . e($storeId))
                        ->limit(1);
                } else {
                    /*if ($counter > 2) {
                    continue;
                    }*/
                    $fallbackSQL = '
                    (SELECT value FROM catalog_product_entity_' . $attribute->backend_type . '
                    WHERE entity_id = p.entity_id AND attribute_id = ' . $attribute->attribute_id . ' AND store_id = 0),
                    ';
                    if ($storeId === 0) {
                        $fallbackSQL = '';
                    }
                    $subquery = DB::table("catalog_product_entity_{$attribute->backend_type} as atr")
                        ->selectRaw('COALESCE( atr.value,' . $fallbackSQL . 'NULL) AS value ')
                        ->whereRaw("store_id = " . e($storeId))->limit(1);
                }
                $counter++;
                $query->addSelect(DB::raw("({$subquery->toSql()}) AS `{$attribute->attribute_code}`"));
            }
        }
        //echo $query->toSql();
        return $query->toSql();
    }

    /**
     * crc32 fastest, lightweight checksum.
     *
     * @param (string) $string
     * @return string
     */
    public function hash($string)
    {
        return crc32($string);
    }

    /**
     * Populate Json table with the product attribute data from the view table
     *
     * @param bool $changeLog - generate data from change log only. By default false - entire table regenerte
     */
    public function populateProductJsonTableFromView($storeIds = [0], $changeLog = false)
    {
        //DB::connection()->enableQueryLog();
        $timeStart = microtime(true);
        foreach ($storeIds as $storeId) {
            // Fetch data from the product view
            $query = DB::table('catalog_product_view_' . $storeId . ' AS cpv')
                ->orderBy('product_id'); // Ensure consistent order (adjust based on your view structure)
            if ($changeLog) {
                // When new product created we can use: catalog_product_price_cl or created at
                $query->rightJoin('catalog_product_attribute_cl AS cpac', 'cpv.entity_id', '=', 'cpac.entity_id');
            }
            $query->chunk(100, function ($rows) {
                $insertData = $rows->map(function ($row) {
                    return [
                        //'id' => $row->product_id, #this field is auto increment and not needed
                        'entity_id' => $row->product_id,
                        'sku' => $row->sku,
                        'store_id' => $row->store_id,
                        'attributes' => json_encode($row), // Convert entire row to JSON
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                })->toArray();
                $startTime = microtime(true);
                // Insert chunked data into the product_json table
                // MariaDB and MySQL database drivers ignore the second argument of the upsert method 
                // and always use the "primary" and "unique" indexes of the table to detect existing records. 
                DB::table('product_json')->upsert($insertData, uniqueBy: ['sku', 'store_id'], update: ['attributes', 'updated_at']);
                $endTime = microtime(true);
            });
            // Retrieve and print the query log
            //$queryLog = DB::connection()->getQueryLog();
            //print_r($queryLog);
            $timeEnd = microtime(true);
            // DB::flushQueryLog()
        }
    }

    public function jsonProductTableCreate($drop = true)
    {
        if($drop) {
            DB::schema()->dropIfExists('product_json');
        }
        try {
            DB::schema()->create('product_json', function (Blueprint $table) {
                $table->id(); // Primary key
                $table->unsignedBigInteger('entity_id'); // Reference to product ID
                $table->string('sku');
                $table->unsignedTinyInteger('store_id');
                $table->index('store_id');
                // Create a unique composite index on both sku and store_id
                $table->unique(['sku', 'store_id'], 'unique_sku_store_id');
                $table->unique(['entity_id', 'store_id'], 'unique_entity_id_store_id');
                $table->jsonb('attributes'); // JSONB column for searchable product data
                $table->timestamps(); // Created and updated timestamps
            });
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        
        $installer->getConnection()->query($this->dropViewSQL(0)); // Drop the view if it exists
        $installer->getConnection()->query($this->createViewTableFromSelect(0)); // Create the view
        $this->createTableFromView(0);
        $this->jsonProductTableCreate();
        //$this->populateProductJsonTableFromView();

        $installer->endSetup();
    }
}
