<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use Carbon\Carbon;
use PDF;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function orderReport()
    {
        // Inisiasi 30 hari range saat ini
        // startOfMonth untuk mengambil tgl 1
        $start = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        // dan endOfMonth untuk mengambil tgl terahir pada bulan saat ini
        $end = Carbon::now()->endOfMonth()->format('Y-m-d H:i:s');

        // jika user melakukan filter manual, maka parameter date akan terisi
        if (request()->date != '') {
            #maka formating tgl nya berdasarkan filter user
            $date = explode(' - ', request()->date);
            $start = Carbon::parse($date[0])->format('Y-m-d') . ' 00:00:01';
            $end = Carbon::parse($date[1])->format('Y-m-d') . ' 23:59:59';
        }

        // membuat query 
        $orders = Order::with(['customer.district'])->whereBetween('created_at', [$start, $end])->get();
        return view('report.order', compact('orders'));
    }

    public function orderReportPdf($daterange)
    {
        $date = explode('+', $daterange); // explode tgl untuk memisahkan start dan end
        $start =  Carbon::parse($date[0])->format('Y-m-d') . ' 00:00:01';
        $end =  Carbon::parse($date[1])->format('Y-m-d') . ' 23:59:59';

        $orders = Order::with(['customer.district'])->whereBetween('created_at', [$start, $end])->get();
        $pdf = PDF::LoadView('report.order_pdf', compact('orders','date'));
        return $pdf->stream();
    }

    public function returnReport()
    {
        $start  = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        $end  = Carbon::now()->endOfMonth()->format('Y-m-d H:i:s');

        if (request()->date != '') {
            $date = explode(' - ', request()->date);
            $start = Carbon::parse($date[0])->format('Y-m-d') . ' 00:00:01';
            $end = Carbon::parse($date[1])->format('Y-m-d') . ' 23:59:59';
        }

        $orders =  Order::with(['customer.district'])->has('return')->whereBetween('created_at', [$start, $end])->get();
        return view('report.return', compact('orders'));
    }

    public function returnReportPdf($daterange)
    {
        $date = explode('+', $daterange);
        $start = Carbon::parse($date[0])->format('Y-m-d') . ' 00:00:01';
        $end = Carbon::parse($date[1])->format('Y-m-d') . ' 23:59:59';

        $orders = Order::with(['customer.district'])->has('return')->whereBetween('created_at', [$start, $end])->get();
        $pdf = PDF::LoadView('report.return_pdf', compact('orders','date'));
        return $pdf->stream();
    }
}
