<?php

namespace App\Models;

use Database\Factories\BillAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['water_provider_id', 'bill_id', 'authorized_by', 'amount', 'reason'])]
class BillAdjustment extends Model
{
    /** @use HasFactory<BillAdjustmentFactory> */
    use HasFactory;
}
