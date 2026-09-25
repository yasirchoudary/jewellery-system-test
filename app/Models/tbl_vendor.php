<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_vendor extends Model
{
    use HasFactory;

    protected $primaryKey = "vendor_id";
    protected $guarded = [];

    public function setVendorCompanyNameAttribute($value){
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['vendor_company_name'] = ucwords(strtolower($value));
    }

    /*public function setvendorCompanyContactAttribute($value){
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['vendor_contact_no'] = ucwords(strtolower($value));
    }*/

    public function setVendorEmailAttribute($value){
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['vendor_email'] = strtolower($value);
    }

    public function setVendorGstNoAttribute($value){
        if ($value === null || $value === '') {
            $this->attributes['vendor_gst_no'] = null;
            return;
        }
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['vendor_gst_no'] = strtoupper($value);
    }

    public function setVendorGstCodeAttribute($value){
        if ($value === null || $value === '') {
            $this->attributes['vendor_gst_code'] = null;
            return;
        }
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['vendor_gst_code'] = strtoupper($value);
    }

    public function setVendorAddressAttribute($value){
        if ($value === null || $value === '') {
            $this->attributes['vendor_address'] = null;
            return;
        }
        $value = preg_replace('/\s+/', ' ', $value);
        $this->attributes['vendor_address'] = ucwords(strtolower($value));
    }

    public static function isThereCompanyNameWithVendorId($vendorId){
        $isVendorAvailable = tbl_vendor::where("vendor_status", true)
            ->where("vendor_id", $vendorId)
            ->first();

        if(is_null($isVendorAvailable)){
           return false; 
        }
        
        return true;
        
    }
}
