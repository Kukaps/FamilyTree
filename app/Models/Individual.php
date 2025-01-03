<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Individual extends Model
{
    protected $fillable = [
        'user_id',
        'gedcom_id',
        'name',
        'given_name',
        'surname',
        'sex',
        'birth_date',
        'death_date'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'data' => 'array'
    ];

    public function families()
    {
        return $this->belongsToMany(Family::class, 'family_individual')
                    ->withPivot('relationship_type')
                    ->withTimestamps();
    }
} 