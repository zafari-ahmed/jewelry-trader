<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A metals feed, as a data row.
 *
 * Adding one is a record and a form, never a deploy. The endpoint, where the
 * rates sit in the response and the unit they are quoted in are the only
 * three things that differ between feeds in practice.
 */
class MetalRateProvider extends Model
{
    use Auditable;

    protected $fillable = ['name', 'slug', 'endpoint', 'rates_path', 'quoted_per_ounce', 'is_active'];

    protected $casts = ['quoted_per_ounce' => 'boolean', 'is_active' => 'boolean'];
}
