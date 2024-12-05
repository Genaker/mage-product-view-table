<?php
namespace Mage\ProductView\Model;

use Illuminate\Database\Eloquent\Model;

class ProductJSON extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_json';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'entity_id';

    /**
     * Indicates if the primary key is an incrementing value.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    protected $casts = [
        'attributes' => 'array', //or 'json' ->object, 
    ];

    /**
     * Get a product by SKU.
     *
     * @param string $sku
     * @return CatalogProductView|null
     */
    public function getBySku(string $sku)
    {
        return self::connection()->where('sku', $sku)->first();
    }
}
