<?php

namespace App\Services;

use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\Auth;

class WarrantyService
{
    public function create(array $data): Warranty
    {
        return Warranty::create([
            'sale_id'       => $data['sale_id'] ?? null,
            'sale_item_id'  => $data['sale_item_id'] ?? null,
            'product_id'    => $data['product_id'],
            'customer_id'   => $data['customer_id'] ?? null,
            'folio'         => Warranty::nextFolio(),
            'serial_number' => $data['serial_number'] ?? null,
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'terms'         => $data['terms'] ?? null,
            'status'        => 'active',
        ]);
    }

    public function update(Warranty $warranty, array $data): Warranty
    {
        $warranty->update([
            'product_id'    => $data['product_id'],
            'customer_id'   => $data['customer_id'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'terms'         => $data['terms'] ?? null,
            'status'        => $data['status'] ?? $warranty->status,
        ]);
        return $warranty;
    }

    public function void(Warranty $warranty): Warranty
    {
        $warranty->update(['status' => 'void']);
        return $warranty;
    }

    public function registerClaim(Warranty $warranty, array $data): WarrantyClaim
    {
        return $warranty->claims()->create([
            'user_id'     => Auth::id(),
            'date'        => $data['date'] ?? now()->toDateString(),
            'description' => $data['description'],
            'status'      => 'open',
        ]);
    }

    public function updateClaim(WarrantyClaim $claim, array $data): WarrantyClaim
    {
        $claim->update([
            'status'     => $data['status'] ?? $claim->status,
            'resolution' => $data['resolution'] ?? $claim->resolution,
        ]);
        return $claim;
    }
}
