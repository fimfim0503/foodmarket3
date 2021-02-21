<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionController extends Controller
{
    //
    public function all(Request $request)
    {
        $id=$request->input('id');
        $limit=$request->input('limit', 6);
        $food_id=$request->input('food_id');
        $status = $request->input('status');

       

        if($id)
        {
            $transaction = Transaction::with(['food', 'user'])->find($id);
            
            if($transaction)
            {
                return ResponseFormatter::success(
                    $transaction,
                    'Data transaksi berhasil diambil'
                );
            }
            else
            {
                return ResponseFormatter::error(
                    null,
                    'data transaksi tidak ada',
                    404
                );
            }
        }

        $transaction = Transaction::with(['food', 'user'])
                        ->where('user_id', Auth::user()->id);

        if($food_id)
        {
            $transaction->where('food_id', $food_id);
        }   
       
        if($status)
        {
            $transaction->where('status', $status);
        }   
        

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Data list transaksi berhasil diambil'
        );

    }

    public function update(Request $request, $id)
    {
        $transaction=Transaction::findorFail($id);

        $transaction->update($request->all());

        return ResponseFormatter::success($transaction, 'Transaksi berhasil diperbaharui');
    }

    public function checkout (Request $request)
    {
        $request->validate([
            'food_id' => 'required|exists:food,id',
            'user_id' => 'required|exists:users, id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required',
        ]);

        $transaction = Transaction::create([
            'food_id' => $request->food_id,
            'user_id' => $request->user_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'payment_url' => '',
        ]);

        //konfigurasi Midtrans
            Config::$serverKey = config('service.midtrans.serverKey');
            Config::$isProduction = config('service.midtrans.isProduction');
            Config::$isSanitized = config('service.midtrans.isSan$isSanitized');
            Config::$is3ds = config('service.midtrans.is3ds');

        //panggil transaksi yang dibuat 
            $transaction=Transaction::with(['food', 'user'])->find($transaction->id);

        //membuat transaksi Midtrans

        $midtrans=[
            'transaction_detail' => [
                'oreder_id' => $transaction->id,
                'gross_amount' => (int) $transaction->total,

            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email'=> $transaction->user->email,
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb'=> []
        ];

        //memanggil midtrans

        try {
            //ambil halaman payment midtrans
            $paymentUrl=Snap::createTransaction($midtrans)->redirect_url;
            $transaction->payment_url = $paymentUrl;
            $transaction->save();
            
            //Mengembalikan data ke API
            return ResponseFormatter::success($transaction, 'Transaksi berhail');
        } 
        catch(Exception $e ){
            return ResponseFormatter::error($e->getMessage(),'Transaksi Gagal');
        }

    }
}
