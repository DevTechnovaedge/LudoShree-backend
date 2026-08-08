<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\RoleRight;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
    	'name',
    ];

    public function administrators(){
        return $this->hasMany(Administrator::class);
    }
    
	public function scopeNotSuperAdmin($query){
		return $query->where('id', '!=', 1);
	}
}
