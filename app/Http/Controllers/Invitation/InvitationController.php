<?php

namespace App\Http\Controllers\Invitation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Product;
use App\Category;
use App\Customer;

class InvitationController extends Controller
{
    public function index()
    {
    	$products = Product::orderBy('created_at','DESC')->paginate(10);
    	return view('invitation.index', compact('products'));
    }
}
