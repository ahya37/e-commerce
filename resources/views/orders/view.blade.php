@extends('layouts.admin')
@section('title')
<title>Detail Pesanan</title>
@endsection

@section('content')
<main class="main">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">View Order</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                        	<h4 class="card-title">
                        		Detail Pesanan

                        		{{-- Tombol ini untuk menerima pembayaran --}}
                        		<div class="float-right">
                        			{{-- tombol ini hanya tampil jika statusnya 1 dari order dan 0 dari pembayaran --}}
                        			@if($order->status == 1 && $order->payment->status == 0)
                        			<a href="{{route('order.approve_payment', $order->invoice)}}" class="btn btn-primary btn-sm">Terima Pembayaran</a>
                                    @endif
                        		</div>
                        	</h4>
                        </div>

                        <div class="card-body">
                        	<div class="row">
                        		
                        		<div class="col-md-6">
                        			<h4>Detail Pelanggan</h4>
                        			<table class="table table-bordered">
                        				<tr>
                        					<td width="30%">Nama Pelanggan</td>
                        					<td>{{ $order->customer_name}}</td>
                        				</tr>
                        				<tr>
                        					<td>Telp</td>
                        					<td>{{ $order->customer_phone}}</td>
                        				</tr>
                        				<tr>
                        					<td>Alamat</td>
                        					<td>{{ $order->customer_address}} {{ $order->customer->district->name}} - {{ $order->customer->district->city->name }}, {{$order->customer->district->city->province->name}}</td>
                        				</tr>
                        				<tr>
                        					<td>Order Status</td>
                        					<td>{!! $order->status_label !!}</td>
                        				</tr>

                        				 <!-- FORM INPUT RESI HANYA AKAN TAMPIL JIKA STATUS LEBIH BESAR 1 -->
                        				@if($order->status > 1)
                        				<tr>
                        					<th>Nomor Resi</th>
                        					<td>
                        						@if($order->status == 2)
                        						<form action="{{route('orders.shipping')}}" method="post">
                        							@csrf
                        							<div class="input-group">
                        								<input type="hidden" name="order_id" value="{{$order->id}}">
                        								<input type="text" name="tracking_number" placeholder="Masukan Nomor Resi" class="form-control" required>
                        								<div class="input-group-append">
                        									<button class="btn btn-secondary" type="submit">Kirim</button>
                        								</div>
                        							</div>
                        						</form>
                        						@else
                        						{{$order->tracking_number}}
                        						@endif
                        					</td>
                        				</tr>
                        				@endif
                        			</table>
                        		</div>
                        		<div class="col-md-6">
                        			<h4>Detail Pembayaran</h4>
                        			@if($order->status != 0)
                        			<table class="table table-bordered">
                        				<tr width="30%">
                        					<th>Nama Pengirim</th>
                        					<td>{{$order->payment->name}}</td>
                        				</tr>
                        				<tr>
                        					<th>Bank Tujuan</th>
                        					<td>{{$order->payment->transfer_to}}</td>
                        				</tr>
                        				<tr>
                        					<th>Tanggal Transfer</th>
                        					<td>{{$order->payment->transfer_date}}</td>
                        				</tr>
                        				<tr>
                        					<th>Bukti Pembayaran</th>
                        					<td>
                        						<a target="_blank" href="{{asset('storage/payment/' . $order->payment->proof)}}">Lihat</a>
                        					</td>
                        				</tr>
                        				<tr>
                        					<th>Status</th>
                        					<td>{!! $order->payment->status_label !!}</td>
                        				</tr>
                        			</table>
                        			@else
                        			<h5 class="text-center">Belum Konfirmasi Pembayaran</h5>
                                    @endif
                        		</div>
                        		<div class="col-md-12">
                        			<h4>Detail Produk</h4>
                        			<table class="table table-bordered table-hover">
                        				<tr>
                                            <th>Produk</th>
                                            <th>Quantity</th>
                                            <th>Harga</th>
                                            <th>Berat</th>
                                            <th>Subtotal</th>
                                        </tr>
                                        @foreach ($order->details as $row)
                                        <tr>
                                            <td>{{ $row->product->name }}</td>
                                            <td>{{ $row->qty }}</td>
                                            <td>Rp {{ number_format($row->price) }}</td>
                                            <td>{{ $row->weight }} gr</td>
                                            <td>Rp {{ $row->qty * $row->price }}</td>
                                        </tr>
                                        @endforeach
                        			</table>
                        		</div>
                        	</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </main>
@endsection