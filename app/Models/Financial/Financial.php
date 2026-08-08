<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Financial extends Model
{
    use HasFactory;
    protected  $table = 'financial_details';
    
    protected $appends          =   [
                                        'status_label',
                                        'status_view',
                                    ];
    
    protected  $fillable           =   [
                                            'user_id',
                                            'type',
                                            'account_name',
                                            'account_number',
                                            'ifsc_code',
                                            'upi_id',
                                            'is_default',
                                            'status',
                                        ];
                                        
                                        
    public function getCreatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

    public function getUpdatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

                                        
    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Deactive';
    }

    public function getStatusViewAttribute()
    {
        return $this->status ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Deactive</sapn>';
    }

}