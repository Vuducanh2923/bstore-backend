<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $connection = 'bstore_catalog';

    protected $table = 'categories';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'status',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
