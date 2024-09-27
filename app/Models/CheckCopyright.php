<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Rtmp;
use Illuminate\Support\Facades\Auth;

class CheckCopyright extends Model
{
    use HasFactory;

    protected $table = "check_copyright";

    protected $fillable = [
        'rtmp_id',
        'created_by',
        'file_name',
        'date_time',
        'status',
        'api_output',
    ];

    public function rtmp()
    {
        $created_by = Auth::user()->id ?? 0;
        return $this->hasOne(Rtmp::class, 'id', 'rtmp_id')->where('created_by', $created_by);
    }
}
