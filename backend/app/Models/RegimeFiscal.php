<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegimeFiscal extends Model
{
    protected $table = 'regimes_fiscaux';

    protected $fillable = ['code', 'designation', 'indice'];

    protected $casts = ['indice' => 'decimal:2'];
}
