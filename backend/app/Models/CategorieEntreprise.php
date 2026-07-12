<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorieEntreprise extends Model
{
    protected $table = 'categories_entreprise';

    protected $fillable = ['code', 'designation', 'indice'];

    protected $casts = ['indice' => 'decimal:2'];
}
