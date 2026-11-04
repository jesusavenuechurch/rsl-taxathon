<?php

namespace App\Models\Cases;

use Illuminate\Database\Eloquent\Model;

/**
 * The taxpayer's filing cabinet (handoff rule 3): not a source record, no
 * Eloquent relation from any case model points here. Only the delivery
 * mechanism reads it — a case never sees a row until a matching request is
 * sent, and even then only through a released_documents row.
 */
class SimulatedTaxpayerDocument extends Model
{
    protected $guarded = [];
}
