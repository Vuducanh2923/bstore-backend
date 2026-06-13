<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'brands';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
