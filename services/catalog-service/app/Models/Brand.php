<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Brand extends Model
{
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

    public function usesTimestamps(): bool
    {
        return Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), static::CREATED_AT)
            && Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), static::UPDATED_AT);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
