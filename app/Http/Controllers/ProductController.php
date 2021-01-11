<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Product;
use App\Category;
use File;
use App\Job\productJob;

class ProductController extends Controller
{
    public function index()
    {
        $product = Product::with(['category'])->orderBy('created_at','DESC');

        // jika terdapat parameter pencarian di URL atau Q tidak sama dengan kosong
        if (request()->q != '') {
            // maka lakukan filter berdasarkan name yang dicari
            $product = $product->where('name','LIKE','%' . request()->q . '%');
        }

        // load 10 data perhalaman
        $product = $product->paginate(10);
        return view('products.index', compact('product'));
    }

    public function create()
    {
        $category = Category::orderBy('name','DESC')->get();
        return view('products.create', compact('category'));
    }

    public function store(Request $request)
    {
        // validasi request
        $this->validate($request, [
            'name'  => 'required|string|max:100',
            'description' => 'required',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|integer',
            'weight' => 'required|integer',
            'image' => 'required|image|mimes:png,jpg,jpeg'
        ]);
        // jika file nya ada
        if ($request->hasFile('image')) {
            // simpan sementara file tersebut kedalam variabel file
            $file = $request->file('image');
            $filename = time(). Str::slug($request->name) . '.' . $file->getClientOriginalExtension();
            // simpan file nya kedalam folder public/product
            $file->storeAs('public/products', $filename);
            
            // setelah file tersebut tersimpan, maka simpan informasinya kedalam database
            $product = Product::create([
                'name' => $request->name,
                'slug' => $request->name,
                'category_id' => $request->category_id,
                'description' => $request->description,
                'image' => $filename,
                'price' => $request->price,
                'weight' => $request->weight,
                'status' => $request->status
            ]);

            return redirect(route('product.index'))->with(['success' => 'Produk Baru Ditambahkan']);


        }
    }

    public function destroy($id)
    {
        $product = Product::find($id);
        // hapus file dalam direktori
        File::delete(storage_path('app/public/products/' . $product->image));
        // hapus data dari databse
        $product->delete();
        return redirect(route('product.index'))->with(['success','Produk Sudah Dihapus']);
    }

    public function massUploadForm()
    {
        $category = Category::orderBy('name','DESC')->get();
        return view('products.bulk', compact('category'));
    }

    public function massUpload(Request $request)
    {
        $this->validate($request, [
            'category_id' => 'required|exists:categories,id',
            'file' => 'required|mimes:xlsx'
        ]);

        // jika file nya ada
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time(). '-product.' . $file->getClientOriginalExtension();
            $file->storeAs('public/uploads', $filename);

            ProductJob::dispatch($request->category_id, $filename);
            return redirect()->back()->with(['success' => 'Upload Produk Dijadwalkan']);
        }
    }

    public function edit($id)
    {
        $product = Product::find($id);
        $category = Category::orderBy('name','DESC')->get();
        return view('products.edit', compact('product','category'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|string|max:100',
            'description' => 'required',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|integer',
            'weight' => 'required|integer',
            'image' => 'nullable|image|mimes:png,jpeg,jpg'
        ]);

        $product = Product::find($id); // ambil data produk berdasarkan id
        $filename = $product->image;

        // jika file gambar ada yang dikirim
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . Str::slug($request->name) . '.' . $file->getClientOriginalExtension();
            // upload file tersebut
            $file->storeAs('public/products', $filename);
            // dan hapus file gambar lama
            File::delete(storage_path('app/public/products/'. $product->image));
        }

        // kemudian update produk pada databse
        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'price' => $request->price,
            'weight' => $request->weight,
            'image' => $filename
        ]);
        return redirect(route('product.index'))->with(['success' => 'Data Produk Diperbarui']);
    }
}
