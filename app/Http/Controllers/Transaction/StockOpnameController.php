<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Color;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;
use App\Models\Transactions\StockOpname;
use App\Services\StockOpnameService;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Stock opname: count a rack by scanning or typing quantities, then save the differences. */
class StockOpnameController extends Controller
{
    use CrudTrait;

    private function summary(): \Closure
    {
        return fn(StockOpname $o) => [
            'id' => $o->id,
            'rack' => [
                'code' => $o->rack?->code,
                'name' => $o->rack?->name,
                'warehouse' => $o->rack?->warehouse?->name,
            ],
            'user' => $o->user?->name,
            'notes' => $o->notes,
            'system_qty' => $o->system_qty,
            'counted_qty' => $o->counted_qty,
            'difference' => $o->counted_qty - $o->system_qty,
            // Barcodes that need attention: a count can match in total and still be wrong.
            'problem_count' => (int) ($o->problem_count
                ?? ($o->relationLoaded('barcodes') ? $o->barcodes->where('result', '!=', StockOpnameService::FOUND)->count() : 0)),
            'created_at' => $o->created_at?->utc()->toISOString(),
        ];
    }

    /** History, newest first. Used by the mobile app and the web report. */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:100',
        ]);

        return $this->baseIndex(
            $request,
            StockOpname::class,
            ['rack' => fn($q) => $q->withTrashed()->with('warehouse'), 'user'],
            [],
            $this->summary(),
            function ($query) use ($filters) {
                // The user picks WIB dates; the database holds UTC.
                if (!empty($filters['date_from'])) {
                    $query->where('created_at', '>=', Carbon::parse($filters['date_from'], 'Asia/Jakarta')->startOfDay()->utc());
                }
                if (!empty($filters['date_to'])) {
                    $query->where('created_at', '<=', Carbon::parse($filters['date_to'], 'Asia/Jakarta')->endOfDay()->utc());
                }
                if ($search = trim((string) ($filters['search'] ?? ''))) {
                    $query->where(fn($q) => $q
                        ->whereHas('rack', fn($r) => $r->withTrashed()
                            ->where(fn($w) => $w->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
                        ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")));
                }
                $query->withCount(['barcodes as problem_count' => fn($q) => $q->where('result', '!=', StockOpnameService::FOUND)])
                    ->latest();
            }
        );
    }

    public function show($id)
    {
        $opname = StockOpname::with(['rack' => fn($q) => $q->withTrashed()->with('warehouse'), 'user', 'items', 'barcodes'])->find($id);
        if (!$opname) {
            return $this->errorResponse(404, 'Stock opname tidak ditemukan');
        }

        return $this->successResponse($this->detail($opname));
    }

    /** What the system has on a rack, looked up by the code in the rack's QR. */
    public function rack(Request $request, StockOpnameService $service)
    {
        $request->validate(['code' => 'required|string']);

        $rack = Rack::with('warehouse')->where('code', $request->input('code'))->first();
        if (!$rack) {
            return $this->errorResponse(404, 'Rak tidak ditemukan');
        }
        $items = collect($service->expected($rack))->sortBy([['model', 'asc'], ['color', 'asc'], ['size', 'asc']])->values();

        return $this->successResponse([
            'rack' => ['id' => $rack->id, 'code' => $rack->code, 'name' => $rack->name, 'warehouse' => $rack->warehouse?->name],
            'items' => $items,
            'total' => $items->sum('quantity'),
        ]);
    }

    /** Lists for typing the quantity of an item the system does not have on the rack. */
    public function options()
    {
        $list = fn($query, string $label, string $fallback) => $query->orderBy($label)->get(['id', $label, $fallback])
            ->map(fn($row) => ['id' => $row->id, 'name' => $row->{$label} ?: $row->{$fallback}])
            ->values();

        return $this->successResponse([
            'models' => $list(ProductModel::query(), 'name', 'sku'),
            'colors' => $list(Color::query(), 'name', 'code'),
            // Sizes read best by their code (S, M, XL) in the order they were made.
            'sizes' => Size::orderBy('created_at')->get(['id', 'code', 'name'])
                ->map(fn($s) => ['id' => $s->id, 'name' => $s->code ?: $s->name])
                ->values(),
        ]);
    }

    /** Checks one scan so the counter sees at once what it is. */
    public function check(Request $request, StockOpnameService $service)
    {
        $data = $request->validate([
            'rack_id' => ['required', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
            'barcode' => 'required|string|max:255',
        ]);

        $row = $service->identify(Rack::with('warehouse')->findOrFail($data['rack_id']), trim($data['barcode']));
        if ($row['result'] === StockOpnameService::UNKNOWN) {
            return $this->errorResponse(422, $row['note']);
        }

        return $this->successResponse($row);
    }

    public function store(Request $request, StockOpnameService $service)
    {
        $data = $request->validate([
            'rack_id' => ['required', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
            'barcodes' => 'present|array|max:20000',
            'barcodes.*' => 'string|max:255',
            'manual' => 'nullable|array|max:2000',
            'manual.*.model_id' => ['required', Rule::exists('mdx_models', 'id')],
            'manual.*.color_id' => ['required', Rule::exists('mdx_colors', 'id')],
            'manual.*.size_id' => ['required', Rule::exists('mdx_sizes', 'id')],
            'manual.*.quantity' => 'required|integer|min:0|max:1000000',
            'notes' => 'nullable|string|max:1000',
        ]);

        @set_time_limit(300);

        $opname = DB::transaction(function () use ($data, $service) {
            $result = $service->compare(Rack::with('warehouse')->findOrFail($data['rack_id']), $data['barcodes'], $data['manual'] ?? []);

            $opname = StockOpname::create([
                'rack_id' => $data['rack_id'],
                'user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
                'system_qty' => $result['system_qty'],
                'counted_qty' => $result['counted_qty'],
            ]);
            $opname->items()->createMany($result['items']);
            $opname->barcodes()->createMany($result['barcodes']);

            return $opname;
        });

        AuditLogger::log('stock_opname', 'CREATE', "Stock opname {$opname->id}: sistem {$opname->system_qty}, fisik {$opname->counted_qty}");

        return $this->show($opname->id);
    }

    private function detail(StockOpname $opname): array
    {
        return ($this->summary())($opname) + [
            'items' => $opname->items->map(fn($i) => [
                'model' => $i->model,
                'color' => $i->color,
                'size' => $i->size,
                'system_qty' => $i->system_qty,
                'counted_qty' => $i->counted_qty,
                'difference' => $i->counted_qty - $i->system_qty,
                'manual' => $i->is_manual,
            ])->sortBy([['model', 'asc'], ['color', 'asc'], ['size', 'asc']])->values(),
            // Only what needs attention; matching barcodes are just counted.
            'problems' => $opname->barcodes->where('result', '!=', StockOpnameService::FOUND)
                ->map(fn($b) => ['barcode' => $b->barcode, 'result' => $b->result, 'note' => $b->note])
                ->values(),
        ];
    }
}
