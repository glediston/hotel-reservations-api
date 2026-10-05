<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['reserve_id', 'method', 'value'];

protected function casts(): array
{
    return ['value' => 'decimal:2'];
}

public function reserve(): BelongsTo
{
    return $this->belongsTo(Reserve::class);
}
}
