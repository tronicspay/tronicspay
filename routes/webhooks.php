<?php

use App\Models\Admin\Order;
use App\Models\Admin\OrderNote;
use App\Models\Customer\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/twilio', function (Request $request) {

    if ($request->AccountSid != config('services.twilio.sid'))
        abort(404);

    if (empty(CustomerAddress::where('phone', $request->From)->first()))
        abort(404);

    $segments = explode(' ', trim($request->Body));
    $command = strtolower($segments[0]);

    switch ($command) {
        case '#support':
            if (count($segments) >= 3) {

                $order_no = strtoupper($segments[1]);
                $order = Order::with('customer.bill')->where('order_no', $order_no)->first();

                if ($order && $order->customer->bill->phone == $request->From) {

                    OrderNote::create([
                        'order_id' => $order->id,
                        'customer_id' => $order->customer->id,
                        'notes' => trim(str_replace([$segments[0], $segments[1]], '', $request->Body))
                    ]);
                }
            }
            break;
        case '#tracking':
            if (count($segments) == 2) {

                $order_no = strtoupper($segments[1]);
                $order = Order::with('customer.bill')->where('order_no', $order_no)->first();

                if ($order && $order->customer->bill->phone == $request->From) {

                    app('App\Http\Controllers\GlobalFunctionController')->doSmsSending($request->From, 'Tracking information for order ' . $order->order_no . ':
                    
Tracking number: ' . $order->tracking_code . '
Tracking link: ' . $order->shipping_tracker . '
                        
Thank you for choosing TronicPay.');
                }
            }
            break;

        default:
            # code...
            break;
    }
});
