<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_inventory_tag extends Model
{
    use HasFactory;

    protected $table = 'tbl_inventory_tags';
    protected $primaryKey = 'tag_id';

    protected $fillable = [
        'tag_number',
        'barcode',
        'sell_quality_id',
        'metal_type',
        'gross_weight',
        'stone_weight',
        'net_weight',
        'purity_karat',
        'fine_weight',
        'pieces',
        'cost_rate',
        'cost_amount',
        'selling_price',
        'status',
        'source_type',
        'source_id',
        'current_karigar_id',
        'sold_invoice_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'gross_weight' => 'float',
        'stone_weight' => 'float',
        'net_weight' => 'float',
        'purity_karat' => 'float',
        'fine_weight' => 'float',
        'pieces' => 'integer',
        'cost_rate' => 'float',
        'cost_amount' => 'float',
        'selling_price' => 'float',
    ];

    public function quality()
    {
        return $this->belongsTo(tbl_sell_quality::class, 'sell_quality_id', 'sell_quality_id');
    }

    public function karigar()
    {
        return $this->belongsTo(tbl_karigar::class, 'current_karigar_id', 'karigar_id');
    }

    public function invoice()
    {
        return $this->belongsTo(tbl_invoice_mst::class, 'sold_invoice_id', 'invoice_mst_id');
    }
}
