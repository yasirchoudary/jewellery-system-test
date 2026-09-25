<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_broker extends Model
{
    use HasFactory;
    protected $table = "tbl_brokers";
    protected $primaryKey = "broker_id";
    protected $guarded = [];

    public function setBrokerNameAttribute($value){
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['broker_name'] = ucwords(strtolower($value));
    }

    public static function isThereBrokerWithBrokerId($brokerId){
        $isBrokerAvailable = tbl_broker::where("broker_status", true)
            ->where("broker_id", $brokerId)
            ->first();

        if(is_null($isBrokerAvailable)){
           return false; 
        }
        
        return true;
        
    }
}
