<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewExitRequest;
use App\Http\Requests\ResolvePaymentRequest;
use App\Http\Requests\SettleExitRequest;
use App\Http\Resources\ParkingSessionResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Payment;
use App\Services\ExitService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class ExitController extends Controller
{
    public function preview(PreviewExitRequest $request, ExitService $exits): JsonResponse
    {
        return response()->json(['quote' => $exits->preview($request->validated('qr_payload'))]);
    }

    public function settle(SettleExitRequest $request, ExitService $exits): JsonResponse
    {
        return $this->respond($exits->settle($request->user(), $request->validated()));
    }

    public function confirmPayment(ResolvePaymentRequest $request, Payment $payment, PaymentService $payments, ExitService $exits): JsonResponse
    {
        $payments->resolvePending($payment, true, $request->user(), $request->validated('external_reference'));

        return $this->respond($exits->completeAfterPayment($payment->fresh(), $request->user()));
    }

    public function failPayment(ResolvePaymentRequest $request, Payment $payment, PaymentService $payments): PaymentResource
    {
        return new PaymentResource($payments->resolvePending($payment, false, $request->user(), null, $request->validated('reason'))->load('method'));
    }

    private function respond(array $result): JsonResponse
    {
        $completed = $result['status'] === 'completed';

        return response()->json([
            'status' => $result['status'],
            'exit_authorized' => $completed,
            'replayed' => $result['replayed'] ?? false,
            'session' => new ParkingSessionResource($result['session']->load(['vehicle', 'zone', 'slot', 'package'])),
            'quote' => $result['quote'] ?? null,
            'payment' => isset($result['payment']) ? new PaymentResource($result['payment']->load('method')) : null,
            'receipt' => ! empty($result['receipt']) ? new ReceiptResource($result['receipt']) : null,
            'gate' => $result['gate'] ?? null,
        ], $completed ? 200 : 202);
    }
}
