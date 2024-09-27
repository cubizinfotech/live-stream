<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RtmpLive;
use App\Models\CheckRtmpLive;
use App\Models\RtmpRecording;

class Rtmp extends Model
{
    use HasFactory;

    protected $table = "rtmps";

    protected $fillable = [
        'created_by',
        'name',
        'rtmp_url',
        'stream_key',
        'live_url',
        'status',
    ];

    public function check_rtmp_live()
    {
    	return $this->hasOne(CheckRtmpLive::class, 'rtmp_id', 'id');
    }

    public function rtmp_recording()
    {
    	return $this->hasMany(RtmpRecording::class, 'rtmp_id', 'id');
    }

    public function rtmp_live()
    {
    	return $this->hasMany(RtmpLive::class, 'rtmp_id', 'id');
    }
}
