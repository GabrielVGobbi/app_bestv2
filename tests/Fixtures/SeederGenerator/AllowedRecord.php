<?php

declare(strict_types=1);

namespace Tests\Fixtures\SeederGenerator;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllowedRecord extends Model
{
    /** @use HasFactory<AllowedRecordFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'allowed_records';

    protected $fillable = ['label', 'unique_key'];

    protected static function newFactory(): AllowedRecordFactory
    {
        return AllowedRecordFactory::new();
    }
}
