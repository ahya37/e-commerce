<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use App\Mail\OrderMail;
use Mail;

class OrderController extends Controller
{
    public function index()
    {
        //QUERY UNTUK MENGAMBIL SEMUA PESANAN DAN LOAD DATA YANG BERELASI MENGGUNAKAN EAGER LOADING
        // DAN URUTKAN BERDASARKAN CREated_at
        $orders = Order::with(['customer.district.city.province'])->withCount(['return'])->orderBy('created_at','DESC');

        // JIKA Q UNTUK PENCARIA  TIDAK KOSONG
        if (request()->q != '') {
            #MAKA DIBUAT QUERY UNTUK MENCARI DATA BERDASARKAN NAMA, INVOICE DAN ALAMAT
            $orders = $orders->where(function($q) {
                $q->where('customer_name','LIKE','%' . request()->q . '%')
                ->orWhere('invoice','LIKE', '%', request()->q . '%')
                ->orWhere('customer_address','LIKE', '%' . request()->q . '%');
            });
        }

        // JIKA STATUS TIDAK KOSONG
        if (request()->status != '') {
            #MAKA DIFILTER BERDASARKAN STATUS
            $orders = $orders->where('status', request()->status);
        }
        $orders = $orders->paginate(10);
        return view('orders.index', compact('orders'));
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        $order->details->delete();
        $order->payment->delete();
        $order->delete();
        return redirect(route('orders.index'));
    }

    public function view($invoice)
    {
        $order = Order::with(['customer.district.city.province','payment','details.product'])->where('invoice', $invoice)->first();
        return view('orders.view', compact('order'));
    }

    public function acceptPayment($invoice)
    {
        // MENGAMBIL DATA CUSTOMER BERDASARKAN INVOICE
        $order = Order::with(['payment'])->where('invoice', $invoice)->first();
        // UBAH STATUS DI TABLE PEYMENTS MELALUI ORDER YANG TERKAIT
        $order->payment()->update(['status' => 1]);
        // UBAH STATUS ORDER MENJADI PROSES
        $order->update(['status' => 2]);
        // REDIRECT KE HALAMAN YANG SAMA
        return redirect(route('orders.view', $order->invoice));
    }

    public function shippingOrder(Request $request)
    {
        // MENGAMBIL DATA BERDASARKAN ID
        $order = Order::with(['customer'])->find($request->order_id);
        // UPDATE DATA ORDER DENGAN MEMASUKAN NOMOR RESI DAN MENGUBAH STATUS MENJADI DIKIRIM
        $order->update(['tracking_number' => $request->tracking_number, 'status' => 3]);
        // KIRIM EMAIL KE PELANGGAN TERKAIT
        Mail::to($order->customer->email)->send(new OrderMail($order));
        return redirect()->back();
    }

    public function return($invoice)
    {
        $order = Order::with(['return','customer'])->where('invoice', $invoice)->first();
        return view('orders.return', compact('order'));
    }

    public function approveReturn(Request $request)
    {
        $this->validate($request, ['status' => 'required']);
        $order = Order::find($request->order_id);
        $order->return()->update(['status' => $request->status]);
        $order->update(['status' => 4]);
        return redirect()->back();
    }

    
}
