<?php

namespace App\Models;

use Database\Factories\ServiceActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['water_provider_id', 'water_connection_id', 'performed_by', 'action', 'balance_at_action', 'notes'])]
class ServiceAction extends Model
{
    /** @use HasFactory<ServiceActionFactory> */
    use HasFactory;
}
