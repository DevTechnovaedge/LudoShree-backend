<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
                'id'                    => $this->id,
                'type'                  => $this->type,
                'account_name'          => $this->account_name,
                'account_number'        => $this->account_number,
                'ifsc_code'             => $this->ifsc_code,
                'upi_id'                => $this->upi_id,
                'is_default'            => $this->is_default,
                'status'                => $this->status,
                'status_label'          => $this->status_label,
                'created_at'            => $this->created_at,
        ];
    }
}
