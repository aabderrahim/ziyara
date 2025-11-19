<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:card,cash,bank_transfer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::where('user_id', auth()->id())->findOrFail($request->booking_id);

        // Check if booking can be paid
        if ($booking->payment_status === 'paid') {
            return response()->json(['message' => 'Booking already paid'], 400);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Cannot pay for cancelled booking'], 400);
        }

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'amount' => $booking->total_price,
                'payment_method' => $request->payment_method,
                'status' => 'pending',
            ]);

            // Update booking
            $booking->update(['payment_method' => $request->payment_method]);

            DB::commit();

            // Here you would integrate with payment gateway
            // For now, we'll return a pending payment

            return response()->json([
                'message' => 'Payment initiated successfully',
                'payment' => $payment,
                'booking' => $booking
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirm(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment = Payment::findOrFail($id);
        $booking = $payment->booking;

        // Verify payment belongs to user or admin
        if ($booking->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Update payment status
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $request->transaction_id,
                'payment_gateway_response' => $request->gateway_response ?? null,
            ]);

            // Update booking payment status
            $booking->update([
                'payment_status' => 'paid',
                'status' => 'confirmed'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment confirmed successfully',
                'payment' => $payment,
                'booking' => $booking->load('tour')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Payment confirmation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function failed(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $booking = $payment->booking;

        // Verify payment belongs to user
        if ($booking->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment->update([
            'status' => 'failed',
            'payment_gateway_response' => $request->gateway_response ?? null,
        ]);

        return response()->json([
            'message' => 'Payment marked as failed',
            'payment' => $payment
        ]);
    }

    public function refund($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment = Payment::findOrFail($id);
        $booking = $payment->booking;

        if ($payment->status !== 'completed') {
            return response()->json(['message' => 'Can only refund completed payments'], 400);
        }

        DB::beginTransaction();
        try {
            // Update payment status
            $payment->update(['status' => 'refunded']);

            // Update booking
            $booking->update([
                'payment_status' => 'refunded',
                'status' => 'cancelled'
            ]);

            DB::commit();

            // Here you would integrate with payment gateway for actual refund

            return response()->json([
                'message' => 'Payment refunded successfully',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Refund failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $payment = Payment::with(['booking', 'booking.tour'])->findOrFail($id);

        // Verify payment belongs to user or admin
        if ($payment->booking->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($payment);
    }

    public function myPayments()
    {
        $payments = Payment::with(['booking', 'booking.tour'])
            ->whereHas('booking', function($q) {
                $q->where('user_id', auth()->id());
            })
            ->latest()
            ->paginate(15);

        return response()->json($payments);
    }

    // Webhook handler (example for payment gateway callbacks)
    public function webhook(Request $request)
    {
        // Verify webhook signature here
        // Parse webhook payload
        // Update payment and booking status accordingly

        $paymentId = $request->payment_id;
        $status = $request->status;

        if ($paymentId && $status) {
            $payment = Payment::find($paymentId);

            if ($payment) {
                DB::beginTransaction();
                try {
                    $payment->update([
                        'status' => $status,
                        'transaction_id' => $request->transaction_id,
                        'payment_gateway_response' => $request->all(),
                    ]);

                    if ($status === 'completed') {
                        $payment->booking->update([
                            'payment_status' => 'paid',
                            'status' => 'confirmed'
                        ]);
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                }
            }
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }
}
