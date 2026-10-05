<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = ['external_id', 'hotel_id', 'name'];

public function hotel(): BelongsTo
{
    return $this->belongsTo(Hotel::class);
}

public function reserves(): HasMany
{
    return $this->hasMany(Reserve::class);
}
    
}
