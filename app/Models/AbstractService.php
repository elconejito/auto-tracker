<?php

namespace App\Models;

use App\Contracts\ServiceContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

abstract class AbstractService extends Model implements ServiceContract
{
    use HasFactory, HasUlids;

    protected static string $serviceLabel;

    protected static string $serviceRecencyGroup;

    protected static bool $serviceSupportsMileageSince = true;

    /** @var list<string> */
    protected $fillable = [];

    public function serviceLine(): MorphOne
    {
        return $this->morphOne(ServiceLine::class, 'serviceable');
    }

    public function label(): string
    {
        return static::$serviceLabel;
    }

    public function recencyGroup(): string
    {
        return static::$serviceRecencyGroup;
    }

    public function supportsMileageSince(): bool
    {
        return static::$serviceSupportsMileageSince;
    }
}
