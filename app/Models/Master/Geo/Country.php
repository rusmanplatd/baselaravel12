<?php

namespace App\Models\Master\Geo;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Country extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'ref_geo_country';

    protected $fillable = [
        'code',
        'name',
        'iso_code',
        'phone_code',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'code' => 'string',
        'name' => 'string',
        'iso_code' => 'string',
        'phone_code' => 'string',

        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    /*******************************
     ** ACTIVITY LOG
     *******************************/
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'iso_code', 'phone_code'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Country {$eventName}")
            ->useLogName('geography');
    }

    /*******************************
     ** MUTATOR
     *******************************/

    /*******************************
     ** ACCESSOR
     *******************************/
    /*******************************
     ** RELATION
     *******************************/
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'country_id');
    }

    /*******************************
     ** SCOPE
     *******************************/
    public function scopeGrid($query)
    {
        return $query
            ->latest();
    }

    public function scopeFilters($query)
    {
        $request = request();

        return $query
            ->when(
                $code = $request->code,
                function ($q) use (&$code) {
                    $q->where('code', 'like', '%'.$code.'%');
                }
            )
            ->when(
                $name = $request->name,
                function ($q) use (&$name) {
                    $q->where('name', 'like', '%'.$name.'%');
                }
            )
            ->when(
                $iso_code = $request->iso_code,
                function ($q) use (&$iso_code) {
                    $q->where('iso_code', 'like', '%'.$iso_code.'%');
                }
            );
    }

    /*******************************
     ** SAVING
     *******************************/
    public function handleStoreOrUpdate(Request &$request)
    {
        $this->beginTransaction();
        try {
            $this->fill($request->all());
            $this->save();
        } catch (\Exception $e) {
            return $this->rollbackSaved($e);
        }
    }

    public function handleDestroy()
    {
        $this->beginTransaction();
        try {
            $this->delete();

            return $this->commitDeleted();
        } catch (\Exception $e) {
            return $this->rollbackDeleted($e);
        }
    }

    public function getLogMessage(): string
    {
        return $this->name;
    }

    /*******************************
     ** OTHER FUNCTIONS
     *******************************/
    public function _can(?User &$user, string $permission): array
    {
        return [
            'update' => $this->checkAction($user, 'update', $permission),
            'delete' => $this->checkAction($user, 'delete', $permission),
        ];
    }

    public function canDeleted(): bool
    {
        if ($this->provinces()->exists()) {
            return false;
        }

        return true;
    }

    public function checkAction(?User &$user, string $action, string $permission): bool
    {
        if (! $user) {
            return false;
        }
        switch ($action) {
            case 'update':
                return $user && $user->hasPermissionTo($permission.':update');

            case 'delete':
                return $user && $user->hasPermissionTo($permission.':delete') && $this->canDeleted();
        }

        return false;
    }
}
