<?php

namespace App\Http\Controllers;
use App\Category;

use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $category = Category::with(['parent'])->orderBy('created_at', 'DESC')->paginate(10);

        $parent = Category::getParent()->orderBy('name','ASC')->get();
        return view('categories.index', compact('category','parent'));

    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:50|unique:categories'
        ]);

        $request->request->add(['slug' => $request->name]);

        Category::create($request->except('_token'));

        return redirect(route('category.index'))->with(['success' => 'Kategori Baru Ditambahkan']);
    }

    public function edit($id)
    {
        $category = Category::find($id);
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|string|max:50|unique:categories,name,' .$id
        ]);

        $category = Category::find($id);
        $category->update([
            'name' => $request->name
        ]);

        return redirect(route('category.index'))->with(['success' => 'Kategori Diperbarui!']);
    }

    public function destroy($id)
    {
        // tambahkan produk kedalam array withcount()
        // fungsi ini akan membentuk field baru yang bernanama product_count
        $category = Category::withCount(['child','product'])->find($id);
        if ($category->child_count == 0 && $category->product_count == 0) {
            $category->delete();
            return redirect(route('category.index'))->with(['success' => 'Kategori Dihapus!']);
        }
        return redirect(route('category.index'))->with(['error' => 'Kategori ini memiliki anak kategori']);

    }
}
