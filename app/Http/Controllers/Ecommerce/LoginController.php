<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Order;

class LoginController extends Controller
{
    public function loginForm()
    {
    	if(auth()->guard('customer')->check()) return redirect(route('customer.dashboard'));
    	return view('ecommerce.login');
    }

    public function login(Request $request)
    {
    	$this->validate($request, [
    		'email' => 'required|email|exists:customers,email',
    		'password' => 'required|string'
    	]);

    	$auth = $request->only('email', 'password');
    	$auth['status'] = 1; // yang bisa logi  harus 1

    	// cek proses otentikasi
    	if (auth()->guard('customer')->attempt($auth)) {
    		return redirect()->intended(route('customer.dashboard'));
    	}

    	return redirect()->back()->with(['error' => 'Email / Passwords Salah']);
    }

    public function dashboard()
    {
        $orders = Order::selectRaw('COALESCE(sum(CASE WHEN status = 0 THEN subtotal END), 0) as pending,COALESCE(count(CASE WHEN status = 3 THEN subtotal END), 0) as shiping,
            COALESCE(count(CASE WHEN status = 4 THEN subtotal END), 0) as complateOrder')
            ->where('customer_id', auth()->guard('customer')->user()->id)->get();

    	return view('ecommerce.dashboard', compact('orders'));
    }

    public function logout()
    {
    	auth()->guard('customer')->logout(); // logout session dari guard customer
    	return redirect(route('customer.login'));
    }
}