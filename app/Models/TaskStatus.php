<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStatus extends Model
{
    protected $fillable = ['key', 'name'];

    public static function byKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
