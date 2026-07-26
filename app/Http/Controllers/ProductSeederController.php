<?php

namespace App\Http\Controllers;

use App\Models\MasterData\Color;
use App\Models\MasterData\CMT;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductSeederController extends Controller
{
    public function index()
    {
        $models = ProductModel::orderBy('name')->get();
        $colors = Color::orderBy('name')->get();
        $sizes = Size::orderBy('name')->get();
        $cmts = CMT::orderBy('name')->get();
        $racks = Rack::orderBy('name')->get();

        $html = $this->viewSeederForm($models, $colors, $sizes, $cmts, $racks);
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'model_id' => 'required|exists:mdx_models,id',
            'color_id' => 'required|exists:mdx_colors,id',
            'size_id' => 'required|exists:mdx_sizes,id',
            'cmt_id' => 'required|exists:mdx_cmts,id',
            'rack_id' => 'nullable|exists:mdx_racks,id',
            'type' => 'required|in:D,P',
            'number' => 'required|integer|min:1',
            'qty' => 'required|integer|min:1|max:100',
        ]);

        $model = ProductModel::find($request->model_id);
        $color = Color::find($request->color_id);
        $size = Size::find($request->size_id);
        $cmt = CMT::find($request->cmt_id);

        $barcodes = [];
        $baseTime = now();

        for ($i = 0; $i < $request->qty; $i++) {
            $series = $baseTime->copy()->addSeconds($i)->format('YmdHis');
            $barcode = implode('|', [
                $cmt->code,
                $series,
                $model->sku,
                $color->code,
                $size->code,
                $request->type,
                $request->number + $i // Increment piece/dozen number for multiple items
            ]);

            Product::create([
                'id' => (string) Str::uuid(),
                'model_id' => $request->model_id,
                'rack_id' => $request->rack_id,
                'color_id' => $request->color_id,
                'size_id' => $request->size_id,
                'series' => $series,
                'barcode' => $barcode,
            ]);

            $barcodes[] = $barcode;
        }

        $models = ProductModel::orderBy('name')->get();
        $colors = Color::orderBy('name')->get();
        $sizes = Size::orderBy('name')->get();
        $cmts = CMT::orderBy('name')->get();
        $racks = Rack::orderBy('name')->get();

        $html = $this->viewSeederForm($models, $colors, $sizes, $cmts, $racks, $barcodes);
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function viewSeederForm($models, $colors, $sizes, $cmts, $racks, $generated = [])
    {
        $modelOpts = '';
        foreach ($models as $m) {
            $modelOpts .= "<option value='{$m->id}'>{$m->name} ({$m->sku})</option>";
        }

        $colorOpts = '';
        foreach ($colors as $c) {
            $colorOpts .= "<option value='{$c->id}'>{$c->name} ({$c->code})</option>";
        }

        $sizeOpts = '';
        foreach ($sizes as $s) {
            $sizeOpts .= "<option value='{$s->id}'>{$s->name} ({$s->code})</option>";
        }

        $cmtOpts = '';
        foreach ($cmts as $cmt) {
            $cmtOpts .= "<option value='{$cmt->id}'>{$cmt->name} ({$cmt->code})</option>";
        }

        $rackOpts = "<option value=''>-- Tanpa Rak --</option>";
        foreach ($racks as $r) {
            $rackOpts .= "<option value='{$r->id}'>{$r->name}</option>";
        }

        $generatedList = '';
        if (!empty($generated)) {
            $items = '';
            foreach ($generated as $bc) {
                $items .= "<li><code class='barcode-text'>{$bc}</code></li>";
            }
            $generatedList = "
            <div class='result-box'>
                <h3>🎉 Berhasil Seed! Barcode yang Dibuat:</h3>
                <ul>{$items}</ul>
            </div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Product Barcode Seeder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; margin: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); border: 1px solid #334155; }
        h1 { margin-top: 0; font-size: 24px; color: #fff; text-align: center; margin-bottom: 24px; border-bottom: 1px solid #334155; padding-bottom: 15px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: #cbd5e1; }
        select, input { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: #fff; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        select:focus, input:focus { outline: none; border-color: #3b82f6; }
        .btn { background: #2563eb; color: white; border: none; padding: 12px 20px; font-weight: bold; cursor: pointer; border-radius: 6px; width: 100%; font-size: 15px; margin-top: 10px; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .result-box { background: #0f172a; border-left: 4px solid #10b981; padding: 20px; border-radius: 6px; margin-bottom: 24px; }
        .result-box h3 { margin-top: 0; color: #10b981; font-size: 16px; }
        .result-box ul { margin: 0; padding-left: 20px; }
        .barcode-text { font-family: monospace; font-size: 14px; color: #38bdf8; background: #1e293b; padding: 2px 6px; border-radius: 4px; }
        .footer-links { text-align: center; margin-top: 20px; }
        .footer-links a { color: #38bdf8; text-decoration: none; font-size: 14px; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Product Barcode Seeder</h1>
        
        {$generatedList}

        <form method="POST" action="/seed-product">
            <!-- CSRF Token helper for Laravel -->
            <input type="hidden" name="_token" value="csrf_token_placeholder">
            
            <div class="form-group">
                <label>Pilih Model (Model SKU)</label>
                <select name="model_id" required>
                    {$modelOpts}
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Warna (Color)</label>
                <select name="color_id" required>
                    {$colorOpts}
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Ukuran (Size)</label>
                <select name="size_id" required>
                    {$sizeOpts}
                </select>
            </div>

            <div class="form-group">
                <label>Pilih CMT</label>
                <select name="cmt_id" required>
                    {$cmtOpts}
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Lokasi Rak (Optional)</label>
                <select name="rack_id">
                    {$rackOpts}
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Tipe Group</label>
                    <select name="type" required>
                        <option value="P">P (Piece / Satuan)</option>
                        <option value="D">D (Dozen / Lusinan)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Sequence / Number</label>
                    <input type="number" name="number" value="1" min="1" required>
                </div>
            </div>

            <div class="form-group">
                <label>Jumlah Product yang Dibuat (Qty)</label>
                <input type="number" name="qty" value="1" min="1" max="100" required>
            </div>

            <button type="submit" class="btn">Generate & Seed Product</button>
        </form>
        
        <div class="footer-links">
            <a href="/logs">← Buka Log Viewer</a>
        </div>
    </div>
    
    <script>
        // Replace CSRF token placeholder dynamically
        document.querySelector('input[name="_token"]').value = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] 
            || '';
            
        // Fallback CSRF token retrieval or let laravel request override it if needed.
        // Actually, in standard Laravel closure routes we can fetch the token using standard CSRF helper.
    </script>
</body>
</html>
HTML;
    }
}
