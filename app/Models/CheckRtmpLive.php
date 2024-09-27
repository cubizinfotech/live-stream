<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Rtmp;
use Illuminate\Support\Facades\Auth;

class CheckRtmpLive extends Model
{
    use HasFactory;

    protected $table = "check_rtmp_lives";

    protected $fillable = [
        'rtmp_id',
        'created_by',
        'status',
    ];

    public function rtmp()
    {
        return $this->hasOne(Rtmp::class, 'id', 'rtmp_id');
        // $created_by = Auth::user()->id ?? 0;
        // return $this->hasOne(Rtmp::class, 'id', 'rtmp_id')->where('created_by', $created_by);
    }
}
