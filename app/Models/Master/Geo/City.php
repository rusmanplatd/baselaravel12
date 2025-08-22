<?php

namespace App\Models\Master\Geo;

use App\Models\Auth\User;
use App\Models\Globals\Activity;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class City extends Model
{
    use HasUlids;

    public $table = 'ref_city';

    protected $fillable = [
        'province_id',
        'code',
        'name',
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

    public function district(): HasMany
    {
        return $this->hasMany(District::class, 'city_id');
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
                    $q->where('province_id', $province_id);
                }
            )
            ->filterBy([
                'code',
                'name',
            ]);
    }

    /*******************************
     ** SAVING
     *******************************/
    public function handleStoreOrUpdate(Request &$request)
    {
        $this->beginTransaction();
        try {
            [$guard, $user] = getAuthenticatedUser();
            $is_update = false;
            if ($this->id) {
                $is_update = true;
            }

            $this->fill($request->all());
            $this->created_by ??= $user->id;
            $this->updated_by = $user->id;
            $this->save();

            if ($is_update) {
                $this->addLog('Mengubah Data '.$this->getLogMessage(), Activity::UPDATE);

                return $this->commitSaved();
            } else {
                $this->addLog('Membuat Data '.$this->getLogMessage(), Activity::CREATE);

                return $this->commitStateStill();
            }
        } catch (\Exception $e) {
            return $this->rollbackSaved($e);
        }
    }

    public function handleDestroy()
    {
        $this->beginTransaction();
        try {
            $this->addLog('Menghapus Data '.$this->getLogMessage(), Activity::DELETE);
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
        if ($this->district()->exists()) {
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
