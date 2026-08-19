<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Brand extends Model
{
    private static array $timestampSupport = [];

    protected $connection = 'bstore_catalog';

    protected $table = 'brands';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'status',
    ];

    // Cung cấp trạng thái và thao tác cho timestamps.
    public function usesTimestamps(): bool
    {
        $cacheKey = spl_object_id($this->getConnection()).':'.$this->getTable();

        return self::$timestampSupport[$cacheKey] ??= (
            Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), static::CREATED_AT)
            && Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), static::UPDATED_AT)
        );
    }

    // Thực hiện sản phẩm.
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
