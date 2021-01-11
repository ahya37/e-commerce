<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Order;
use App\OrderReturn;
use DB;
use Carbon\Carbon;
use App\Payment;
use Validator;
use PDF;

class OrderController extends Controller
{
    public function index()
    {
    	$orders = Order::withCount(['return'])->where('customer_id', auth()->guard('customer')->user()->id)->orderBy('created_at','DESC')->paginate(10);
    	return view('ecommerce.orders.index', compact('orders'));
    }

    public function view($invoice)
    {
    	$order = Order::with(['district.city.province','details', 'details.product','payment'])
                ->where('invoice', $invoice)->first();
        
        // JADI KITA CEK, VALUE forUser() NYA ADALAH CUSTOMER YANG SEDANG LOGIN
        // DAN ALLOW NYA MEMINTA DUA PARAMETER
        // PERTAMA ADALAH NAMA GATE YANG DIBUAT SEBELUMNYA DAN YANG KEDUA ADALAH DATA ORDER DARU QUERY DI ATAS
        if (\Gate::forUser(auth()->guard('customer')->user())->allows('order-view', $order)) {
            #JIKA HASILNYA TRUE, MAKA KITA TAMPILKAN DATANYA
    	    return view('ecommerce.orders.view', compact('order'));
        } 
        // JIKA FALSE, MAKA REDIRECT KE HALAMAN YANG DIINGINKAN
    	return redirect(route('customer.orders'))->with(['error' => 'Anda tidak diizinkan untuk mengakses order orang lain']);
    }

    public function paymentForm()
    {
    	return view('ecommerce.payment');
    }

    public function storePayment(Request $request)
    {
        $request->validate([
            'invoice' => 'required|exists:orders,invoice',
            'name' => 'required|string|max:255',
            'transfer_to' => 'required|string|max:255',
            'transfer_date' => 'required',
            'amount' => 'required|integer',
            'proof' => 'required|image|mimes:jpg,png,jpeg'
        ]);

        // DEFINE DATASAE UNTUK MENGHINDARI KESALAHAN SINKRONISASI DATA JIKA TERJADI ERROR DITENGAH QUERY
        DB::beginTransaction();
        try{
            // AMBIL DATA ORDER BERDASARKAN INVOICE
            $order = Order::where('invoice', $request->invoice)->first();
            if ($order->subtotal != $request->amount) return redirect()->back()->with(['error' => 'Pembayaran harus sama dengan tagihan']);

            // JIKA STATUSNYA MASIH 0 DAN ADA FILE BUKTI YANG DIKIRIM
            if ($order->status == 0 && $request->hasFile('proof')) {
                //MAKA UPLOAD FILE GAMBAR TERSEBUT
                $file = $request->file('proof');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/payment', $filename);

                //KEMUDIAN SIMPAN INFORMASI PEMBAYARAN
                Payment::create([
                    'order_id' => $order->id,
                    'name' => $request->name,
                    'transfer_to' => $request->transfer_to,
                    'transfer_date' => Carbon::parse($request->transfer_date)->format('Y-m-d'),
                    'amount' => $request->amount,
                    'proof' => $filename,
                    'status' => false
                ]);

                // DAN GANTI STATUS ORDER MANJADI SATU
                $order->update(['status' => 1]);
                // JIKA TIDAK ADA ERROR, MAKA COMMIT UNTUK MENANDAKAN BAHWA TRANSAKSI BERHASIL
                DB::commit();
                // REDIRECT DAN KIRIMKAN PESAN
                return redirect()->back()->with(['success' => 'Pesanan Dikonfirmasi']);
            }

            // REDIRECT DENGAN ERROR MESSAGE
            return redirect()->back()->with(['error' => 'Error, Upload bukti transfer']);
        }catch(\Exception $e){
            // JIKA TERJADI ERROR, MAKA ROLLBACK SELURUH PROSES QUERY
            DB::rollback();
            // DAN KEMUDIAN KIRIM PESAN ERROR
            return redirect()->back()->with(['error' => $e->getMessage()]);
        }

    }

    public function pdf($invoice)
    {
        // GET DATA BERHDASARKAN INVOICE
        $order = Order::with(['district.city.province','details','details.product','payment'])->where('invoice', $invoice)->first();
        // MENCEGAH DIRECT AKSES OLEH USER, SEHINGGA HANYA PEMILIKNYA YANG BISA MELIHAT
        if (!\Gate::forUser(auth()->guard('customer')->user())->allows('order-view', $order)) {
            return redirect(route('customer.view_order', $order->invoice));
        }

        // JIKA DIA ADALAH PEMILIKNYA, MAKA LOAD VIEW BERIKUT DAN PASSING DATA ORDERS
        $pdf = PDF::loadView('ecommerce.orders.pdf', compact('order'));
        return $pdf->stream();
    }

    public function acceptOrder(Request $request)
    {
        // CARI DATA BERDASARKAN ID
        $order = Order::find($request->order_id);
        // VALIDASI KEPEMILIKAN
        if (!\Gate::forUser(auth()->guard('customer')->user())->allows('order-view', $order)) {
            return redirect()->back()->with(['error' => 'Bukan pesanan Anda']);
        }

        // UBAH STATUSNYA MENJADI 4
        $order->update(['status' => 4]);
        return redirect()->back()->with(['success' => 'Pesanan dikonfirmasi']);
    }

    public function returnForm($invoice)
    {
        $order = Order::where('invoice', $invoice)->first();
        return view('ecommerce.orders.return', compact('order'));
    }

    public function processReturn(Request $request, $id)
    {
        $this->validate($request, [
            'reason' => 'required|string',
            'refund_transfer' => 'required|string',
            'photo' => 'required|image|mimes:jpg,png,jpeg'
        ]);

        // Cari data return berdasarkan order_id yang ada di table order_return 
        $return = OrderReturn::where('order_id', $id)->first();
        // jika ditemukan, maka tampilkan norifikasi error
        if ($return) return redirect()->back()->with(['error' => 'Permintaan refund dalam proses']);

        // Jika tidak, lakukan pengecekan untuk memastikan file foto dikirimkan
        if ($request->hasFile('photo')) {
            # get file
            $file = $request->file('photo');
            $filename = time() . Str::random(5) . '.' . $file->getClientOriginalExtension();
            // upload ke dalam directory storage/app/public/return
            $file->storeAs('public/return', $filename);

            // save ke order_return
            OrderReturn::create([
                'order_id' => $id,
                'photo' => $filename,
                'reason' => $request->reason,
                'refund_transfer' => $request->refund_transfer,
                'status' => 0
            ]);

            $order = Order::find($id);
            // Kirim pesan melalui bot
            $this->sendMessage($order->invoice, $request->reason);

            return redirect()->back()->with(['success' => 'Permintaan refund dikirim']);
        }
    }

    private function getTelegram($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . $params);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $content = curl_exec($ch);
        curl_close($ch);
        return json_decode($content, true);
    }

    private function sendMessage($order_id, $reason)
    {
        // $key = env('TELEGRAM_KEY');

        $key = 'bot1546146961:AAGDTkeG1t2pTvUCFsgSgOm4Evz12dIZktY';
        // kemudian kirim request ke telegram untuk mengambil data user yang me-listen bot kita
        $chat  = $this->getTelegram('https://api.telegram.org/'. $key .'/getUpdates', '');
        // Jika ada
        if ($chat['ok']) {
            #pesan ini hanya dikirim ke admin
            // unutk mendapatkan chat_id           
            $chat_id = $chat['result'][0]['message']['chat']['id'];
            // teks pesan 
            $text = 'Hai admin, Order ID ' . $order_id . ' Melakukan permintaan refund dengan alasan "'.$reason.'", Segera cek ya !';

            // dan kirim  request ke telegram untuk mengirimkan pesan
            return $this->getTelegram('https://api.telegram.org/'. $key .'/sendMessage', '?chat_id=' . $chat_id . '&text=' . $text);
        }
    }
}
