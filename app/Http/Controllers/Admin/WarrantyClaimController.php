<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WarrantyClaimRequest;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Services\WarrantyService;

class WarrantyClaimController extends Controller
{
    public function __construct(private readonly WarrantyService $service) {}

    public function store(WarrantyClaimRequest $request, Warranty $warranty)
    {
        $this->service->registerClaim($warranty, $request->validated());
        return redirect()->route('admin.warranties.show', $warranty)->with('success', 'Reclamo registrado.');
    }

    public function update(WarrantyClaimRequest $request, Warranty $warranty, WarrantyClaim $claim)
    {
        $this->service->updateClaim($claim, $request->validated());
        return redirect()->route('admin.warranties.show', $warranty)->with('success', 'Reclamo actualizado.');
    }
}
