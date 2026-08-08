<?php

namespace App\Traits;

use App\Models\Administrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait  ActionBy
{

    protected static function  bootActionBy()
    {
        static::retrieved(function (Model $model) {
            if (($model->created_by ?? 0) || ($model->updated_by ?? 0) || ($model->deleted_by ?? 0)) :
                
                if ($model->created_by ?? 0) :
                    $created_by_member         =    Administrator::select('id', 'name', 'role_id')->find($model->created_by);
                    if ($created_by_member) :
                        $created_by_member->makeHidden(['role_id', 'permissions', 'status_label', 'status_view']);
                    endif;
                endif;

                if ($model->updated_by ?? 0) :
                    $updated_by_member         =    Administrator::select('id', 'name', 'role_id')->find($model->updated_by);
                    if ($updated_by_member) :
                        $updated_by_member->makeHidden(['role_id', 'permissions', 'status_label', 'status_view']);
                    endif;
                endif;

                if ($model->deleted_by ?? 0) :
                    $deleted_by_member         =    Administrator::select('id', 'name', 'role_id')->find($model->deleted_by);
                    if ($deleted_by_member) :
                        $deleted_by_member->makeHidden(['role_id', 'permissions', 'status_label', 'status_view']);
                    endif;
                endif;
            endif;

            // $model->created_by_member = $created_by_member ?? null;
            // $model->updated_by_member = $updated_by_member ?? null;
            // $model->deleted_by_member = $deleted_by_member ?? null;

        });

        static::creating(function (Model $model) {
            if (Schema::hasColumn($model->getTable(), 'created_by')) :
                $model->created_by = request()->user()->id ?? 0;
            endif;
        });

        static::updating(function (Model $model) {
            if (Schema::hasColumn($model->getTable(), 'updated_by')) :
                if (!$model->deleted_by) :
                    $model->updated_by = request()->user()->id ?? 0;
                endif;
            endif;
        });

        static::deleting(function (Model $model) {
            if (Schema::hasColumn($model->getTable(), 'deleted_by') && Schema::hasColumn($model->getTable(), 'deleted_at')) {
                $model->deleted_by = request()->user()->id ?? 0;
                $model->save();
            }
        });
    }
}
