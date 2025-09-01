<?php

namespace App\Models\Master\Geo;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Village extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public $table = 'ref_geo_village';

    protected $fillable = [
        'district_id',
        'code',
        'name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'district_id' => 'string',
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
            ->logOnly(['district_id', 'code', 'name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Village {$eventName}")
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
    public function district()
    {
        return $this->belongsTo(District::class, 'district_id');
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
                $province_id = $request->province_id,
                function ($q) use (&$province_id) {
                    $q->whereRelation('district.city', 'province_id', $province_id);
                }
            )
            ->when(
                $city_id = $request->city_id,
                function ($q) use (&$city_id) {
                    $q->whereRelation('districts', 'city_id', $city_id);
                }
            )
            ->when(
                $district_id = $request->district_id,
                function ($q) use (&$district_id) {
                    $q->where('district_id', $district_id);
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
