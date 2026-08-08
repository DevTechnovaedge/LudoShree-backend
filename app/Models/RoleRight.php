<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Module;
use App\Models\Administrator;

class RoleRight extends Model
{
    use HasFactory;

    protected $fillable = [
    	'rr_create',
    	'rr_edit',
    	'rr_delete',
    	'rr_view',
    	'module_id',
    	'role_id',
    ];

	
	public function user(){
		return $this->belongsTo(Administrator::class, 'role_id');
	}
	
	public function modules(){
		return $this->belongsTo(Module::class, 'module_id');
	}

}
