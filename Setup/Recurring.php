<?php

namespace Mage\ProductView\Setup;

use Mage\DB2\DB2 as DB;

class Recurring extends ViewTableCreate
{

    public $viewName = "catalog_product_view";
    const PRODUCT_TYPE_ID = 4;

    public function getSelect($join = false)
    {
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
        //->limit(100) // Limit to 100 results
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
                if($counter > 61) continue;
                $tableAlias = "attr_{$attribute->attribute_id}"; // Unique alias for each join

                $query->leftJoin(
                    DB::raw("catalog_product_entity_{$attribute->backend_type} AS $tableAlias"), function ($join) use ($attribute, $tableAlias) {
                        $join->on("p.entity_id", "=", DB::raw("$tableAlias.entity_id"))
                            ->whereRaw("$tableAlias.attribute_id = $attribute->attribute_id");
                    }
                );
                $query->addSelect(DB::raw("$tableAlias.value AS `$attribute->attribute_code`"));
            } else // Using Subqueries
            {
                $subquery = DB::table("catalog_product_entity_{$attribute->backend_type}")
                    ->select('value')
                    ->whereColumn("entity_id", "p.entity_id")
                    ->whereRaw("attribute_id = $attribute->attribute_id")
                    ->limit(1);

                $query->addSelect(DB::raw("({$subquery->toSql()}) AS `{$attribute->attribute_code}`"));
            }

        }
        //echo $query->toSql();
        return $query->toSql();
        die();
    }

}
