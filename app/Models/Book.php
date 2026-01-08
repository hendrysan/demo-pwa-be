<?php

namespace App\Models;

use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'stock',
    ];

    protected $casts = [
        'stock' => 'integer',
    ];

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
