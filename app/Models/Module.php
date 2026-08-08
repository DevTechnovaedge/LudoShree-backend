<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;
    protected $fillable = [
    	'module_name',
    	'module_code',
    	'perm_create',
    	'perm_edit',
    	'perm_delete',
    	'perm_view',
		'status'
    ];

	public function permissions(){
		return $this->hasMany(RoleRight::class);
	}
	
}
