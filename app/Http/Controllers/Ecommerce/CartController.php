<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Product;
use App\Province;
use App\City;
use App\District;
use App\Customer;
use App\Order;
use App\OrderDetail;
use Illuminate\Support\Str;
use DB;
use Cookie;
use App\Mail\CustomerRegisterMail;
use Mail;

class CartController extends Controller
{
    public function getCarts()
    {
        $carts = json_decode(request()->cookie('dw-carts'), true);
        $carts = $carts != '' ? $carts:[];
        return $carts;
    }

    public function addToCart(Request $request)
    {
    	// validasi data yang dikirim
    	$this->validate($request, [
    		'product_id' => 'required|exists:products,id',
    		'qty' => 'required|integer'
    	]);

    	// ambil data dari cookie, bentuknya JSON, dan rubah ke dalam Array
    	$carts = $this->getCarts();
    	// cek jika cart tidak null dan product_id ada di dalam array
    	if ($carts && array_key_exists($request->product_id, $carts)) {
    		// maka update QTY nya berdasarkan product_id yang dijadikan key
    		$carts[$request->product_id]['qty'] += $request->qty;
    	}else{
    		// selain itu, buat query untuk mengambil produk berdasarkan product_id
    		$product = Product::find($request->product_id);
    		// tambahkan data baru dengan menjadikan product_id sebagai key dari Array carts
    		$carts[$request->product_id] = [
    			'qty' => $request->qty,
    			'product_id' => $product->id,
    			'product_name' => $product->name,
    			'product_price' => $product->price,
    			'product_image' => $product->image,
                'weight' => $product->weight
    		];

    	   }
    		// buat cookie nya dengan name dw-carts
    		// encode kembali, dan limit nya 2800 menit atau 48 jam
    		$cookie = cookie('dw-carts', json_encode($carts), 2800);
    		// store ke browser untuk disimpan
    		return redirect()->back()->with(['success' => 'Produk Ditambahkan ke Keranjang'])->cookie($cookie);
    }

    public function listCart()
    {
    	// mengambil data dari cookie
    	$carts = $this->getCarts();
    	// ubah array menjadi collection, kemudian gunakan method sum untuk menghitung subtotal
    	$subtotal = collect($carts)->sum(function($q) {
    		return $q['qty'] * $q['product_price']; // subtotal terdiri dari qty * price
    	});

    	return view('ecommerce.cart', compact('carts','subtotal'));
    }

    public function updateCart(Request $request)
    {
    	// ambil data dari cookie
    	$carts = $this->getCarts();
    	// kemudian looping data product_id, karena name nya array pada view sebelumnya
    	// maka data yang diterima adalah array sehingga bisa di looping
    	foreach ($request->product_id as $key => $row) {
    		// di cek, jika qty  dengan key yang sama dengan product_id = 0
    		if ($request->qty[$key] == 0) {
    			// maka data teresbut dihapus dari array
    			unset($carts[$row]);
    		}else{
    			// selain itu maka akan diperbarui
    			$carts[$row]['qty'] = $request->qty[$key];
    		}
    	}

    	// set kembali cookie nya seperti sebelumnya
    	$cookie = cookie('dw-carts', json_encode($carts), 2800);
    	// dan store ke browser
    	return redirect()->back()->cookie($cookie);
    }

    

    public function checkout()
    {
    	// ambil semua data provinsi
    	$provinces = Province::orderBy('created_at','DESC')->get();
    	$carts = $this->getCarts(); // mengambil data cart
    	// menghitung subtotal dari keranjann belanja (cart)
    	$subtotal = collect($carts)->sum(function($q) {
    		return $q['qty'] * $q['product_price'];
    	});

    	return view('ecommerce.checkout', compact('provinces','carts','subtotal'));
    }

    public function getCity()
    {
    	// query ambil data kota /kabupaten berdasarkan province_id
    	$cities = City::where('province_id', request()->province_id)->get();
    	// kembalikan datanya dalam bentuk json
    	return response()->json(['status' => 'success', 'data' => $cities]);
    }

    public function getDistrict()
    {
    	// query untuk mengambil data kecamatan berdasarkan id kota / kabupaten (city_id)
    	$districts = District::where('city_id', request()->city_id)->get();
    	return response()->json(['status' => 'success','data' => $districts]);
    }

    public function processCheckout(Request $request)
    {
    	// validasi data
    	$this->validate($request, [
    		'customer_name' => 'required|string|max:100',
    		'customer_phone' => 'required',
    		'email' => 'required|email',
    		'customer_address' => 'required|string',
    		'province_id' => 'required|exists:provinces,id',
    		'city_id' => 'required|exists:cities,id',
    		'district_id' => 'required|exists:districts,id'
    	]);

    	// inisiasi data customer berdasarkan email
    	DB::beginTransaction();
    	try {
    		// cek data customer berdasarkan email
    		$customer = Customer::where('email', $request->email)->first();
    		// jika dia tidak login dan data customernya ada
    		if (!auth()->guard('customer')->check() && $customer) {
    			// maka redirect dan tampilkan instruksi untuk login
    			return redirect()->back()->with(['error' => 'Silahkan Login Terlebih Dahulu']);
    		}

    		// ambil data keranjang
    		$carts = $this->getCarts();
    		// hitung subtotal belanja
    		$subtotal = collect($carts)->sum(function($q) {
    			return $q['qty'] * $q['product_price'];
    		});

			if (!auth()->guard('customer')->check()) {
				// simpan data customer baru
				$password = Str::random(8);
				$customer = Customer::create([
					'name' => $request->customer_name,
					'email' => $request->email,
					'password' => $password,
					'phone_number' => $request->customer_phone,
					'address' => $request->customer_address,
					'district_id' => $request->district_id,
					'activate_token' => Str::random(30),
					'status' => false
				]);
			}

    		// simpan data order
    		$order = Order::create([
    			'invoice' => Str::random(4) . '-' . time(), // invoice dibuat dari string random dan waktu
    			'customer_id' => $customer->id,
    			'customer_name' => $customer->name,
    			'customer_phone' => $request->customer_phone,
    			'customer_address' => $request->customer_address,
    			'district_id' => $request->district_id,
    			'subtotal' => $subtotal
    		]);

    		// looping data di cart
    		foreach ($carts as $row) {
    			// ambil data produk berdasarkan product_id
    			$product = Product::find($row['product_id']);
    			// simpan detail order
    			OrderDetail::create([
    				'order_id' => $order->id,
    				'product_id' => $row['product_id'],
    				'price' => $row['product_price'],
    				'qty' => $row['qty'],
    				'weight' => $product->weight 
    			]);
    		}

    		// tidak terjadi error, maka commit datanya untuk menginformasikan bahwa data sudah fix untuk disimpan
    		DB::commit();

    		$carts = [];
    		// kosongkan data keranjang di cookie
			$cookie = cookie('dw-carts', json_encode($carts), 2800);
			
			if (!auth()->guard('customer')->check()) {
				Mail::to($request->email)->send(new CustomerRegisterMail($customer, $password));
			}
    		// redirect ke halaman finish transaksi
    		return redirect(route('front.finish_checkout', $order->invoice))->cookie($cookie);
    	}catch(\Exception $e) {
    		// jika terjadi error maka rollback datanya
    		DB::rollback();
    		// dan kembali ke form transaksi serta menampilkan error
    		return redirect()->back()->with(['error' => $e->getMessage()]);
    	}
    }

    public function checkoutFinish($invoice)
    {
    	// ambil data pesanan bedasarkan invoice
    	$order = Order::with(['district.city'])->where('invoice', $invoice)->first();
    	return view('ecommerce.checkout_finish', compact('order'));
    }
}
