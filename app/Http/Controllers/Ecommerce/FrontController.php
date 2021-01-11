<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Product;
use App\Category;
use App\Customer;
use App\Province;

class FrontController extends Controller
{
    public function index()
    {
    	$products = Product::orderBy('created_at','DESC')->paginate(10);
    	return view('ecommerce.index', compact('products'));
    }

    public function product()
    {
    	$products = Product::orderBy('created_at','DESC')->paginate(12);
    	return view('ecommerce.product', compact('products'));
    }

    public function categoryProduct($slug)
    {
        // jadi query nya adalah kita cari dulu kategori berdasarkan slug, setelah datanya ditemukan
        // maka slug akan mengambil data product yang berelasi menggunakan method product() zang telah didefinisikan pada file Category.php
        $products = Category::where('slug', $slug)->first()->product()->orderBy('created_at','DESC')->paginate(12);
        return view('ecommerce.product', compact('products'));
         
    }

    public function show($slug)
    {
        // ambil single data berdasarkan slugnya
        $product = Product::with(['category'])->where('slug', $slug)->first();
        return view('ecommerce.show', compact('product'));
    }

    public function verifyCustomerRegistration($token)
    {
        // membuat query untuk mengambil data user berdasarkan token yang diterima
        $customer = Customer::where('activate_token', $token)->first();
        if ($customer) {
            // jika ada maka datanya diupdate dengan mengosongkan tokennya dan statusnya jadi aktif
            $customer->update([
                'activate_token' => null,
                'status' => 1
            ]);

            // redirect ke halaman login dengan mengirimkan flash session success
            return redirect(route('customer.login'))->with(['success' => 'Verifikasi Berhasil, Silahkan Login!']);
        }

        // jika tidak ada, maka redirect ke halaman login
        // dengan mengirimkan flash session
        return redirect(route('customer.login'))->with(['error' => 'Invalid Verifikasi Token']);
    }

    public function customerSettingForm()
    {
        // MENGAMBIL DATA CUSTOMER YANG SEDANG LOGIN
        $customer = auth()->guard('customer')->user()->load('district');
        // GET DATA PROVINSI UNTUK DITAMPILKAN PADA SELECT BOX 
        $provinces = Province::orderBy('name','ASC')->get();
        // LOAD VIEW setting.blade.php DAN PASSING DATA CUSTOMER - PROCINCES
        return view('ecommerce.setting', compact('customer','provinces'));
    }

    public function customerUpdateProfile(Request $request)
    {
        // validasi data yang dikirim
        $this->validate($request, [
            'name' => 'required|string|max:100',
            'phone_number' => 'required|max:15',
            'address' => 'required|string',
            'district_id' => 'required|exists:districts,id',
            'password' => 'nullable|string|min:6'
        ]);

        // AMBIL DATA CUSTOMER YANG SEDANG LOGIN
        $user = auth()->guard('customer')->user();
        // AMBIL DATA YANG DI KIrim DARI FORM
        // TAPI HANYA 4 KOLOM SAJA SESUAI YANG ADA DIBAWAH
        $data = $request->only('name','phone_number','address','district_id');
        // ADAPUN PASSword NYA KITA CEK DULU, JIKA TIDAK KOSONG
        if ($request->password != '') {
            // MAKA TAMBAHKAN KE DALAM ARRAY
            $data['password'] = $request->password;
        }

        // UPDATE DATANYA
        $user->update($data);
        // DAN REDIRECT KEMBALI DENGAN MENGGUNAKAN PESAN BERHASIL
        return redirect()->back()->with(['success' => 'Profil berhasil diperbarui']);
    }

}
