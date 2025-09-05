<?php

namespace App\Models\Master\Geo;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class City extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public $table = 'ref_geo_city';

    protected $fillable = [
        'province_id',
        'code',
        'name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'province_id' => 'string',
        'code' => 'string',
        'name' => 'string',

        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    /*******************************
     ** ACTIVITY LOG
     *******************************/
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['province_id', 'code', 'name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "City {$eventName}")
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
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'city_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*******************************
     ** SCOPE
     *******************************/

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
        if ($this->districts()->exists()) {
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
