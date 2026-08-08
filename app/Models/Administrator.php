<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;

class Administrator extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends   = ['role_type', 'permissions', 'status_label', 'status_view'];

    protected $fillable =   [
            'name',
            'role_id',
            'username',
            'email',
            'mobile',
            'password',
            'status',
    ];

    public function getRoleTypeAttribute(){
        $role   =   Role::where('id', $this->role_id)->first();
        return $role->name ?? '';
    }

    public function getStatusLabelAttribute(){
            return $this->status == 1 ? 'Active' : 'Deactive';
    }

    public function getStatusViewAttribute(){
            return $this->status == 1 ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Deactive</span>';
    }

    public function getPermissionsAttribute(){
        return  Module::select('modules.module_name', 'modules.module_code',
                            'role_rights.rr_create', 'role_rights.rr_edit', 'role_rights.rr_delete', 'role_rights.rr_view',
                            'role_rights.role_id', 'role_rights.module_id')
                    ->join('role_rights', 'role_rights.module_id', '=', 'modules.id')  
                    ->where('role_rights.role_id', $this->role_id)  
                    ->whereStatus(1)            
                    ->distinct()
                ->get();
    }

    // protected $hidden = [
    //     'password'
    // ];
}
