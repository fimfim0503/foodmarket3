<?php

namespace App\Http\Controllers\API;

use Midtrans\Config;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Midtrans\Notification;

class MidtransController extends Controller
{
    //

    public function callback(Request $request)
    {
        //set konfigurasi midtrans
            Config::$serverKey = config('service.midtrans.serverKey');
            Config::$isProduction = config('service.midtrans.isProduction');
            Config::$isSanitized = config('service.midtrans.isSan$isSanitized');
            Config::$is3ds = config('service.midtrans.is3ds');

        //buat instance midtrans notification
            $notification=new Notification();

        //assign ke variable untuk memudahkan coding

            $status=$notification->transaction_status;
            $type = $notification->payment_type;
            $fraud = $notification->fraud_status;
            $order_id = $notification->order_id;

        //cari transaksi berdasarkan ID
            $transaction = Transaction::findorFail($order_id);


        //Handle notifikasi status midtrans
            if($status == 'capture')
            {
                if($type == 'credit_card' )
                {
                    if($fraud == 'chalenge')
                    {
                        $transaction->status = 'PENDING';
                    }
                    else
                    {
                        $transaction->status = 'SUCCESS';
                    }
                }
            }
            else if ($status == 'settlement')
            {
                $transaction->status = 'SUCCESS';
            }
            else if ($status == 'pending')
            {
                $transaction->status = 'PENDING';
            }
            else if ($status == 'deny')
            {
                $transaction->status = 'CANCELLED';
            }
            else if ($status == 'expire')
            {
                $transaction->status = 'CANCELLED';
            }
            else if ($status == 'cancel')
            {
                $transaction->status = 'CANCELLED';
            }

        //simpan transaksi 
        $transaction->save();
    }

    public function success()
    {
        return view('midtrans.success');
    }

    public function unfinish()
    {
        return view('midtrans.unfinish');
    }

    public function error()
    {
        return view('midtrans.error');
    }
}
