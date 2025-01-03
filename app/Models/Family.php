<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'data'
    ];

    protected $casts = [
        'data' => 'string'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function individuals()
    {
        return $this->belongsToMany(Individual::class, 'family_individual')
                    ->withPivot('relationship_type')
                    ->withTimestamps();
    }
} 