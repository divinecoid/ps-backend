<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Notification;
use App\Models\MasterData\NotificationRecipient;
use App\Models\MasterData\Product;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Warehouse;
use App\Models\MasterData\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 50;
    private const LOW_STOCK_NOTIFICATION_TYPE = 'low_stock';

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated', 'data' => null], 401);
        }

        $query = NotificationRecipient::with('notification')
            ->where('user_id', $user->id);

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        $query->orderBy('is_read')->orderBy('created_at', 'desc');

        $pagination = $query->paginate((int) $request->input('per_page', 100));

        $items = $pagination->getCollection()->map(function (NotificationRecipient $recipient) {
            return [
                'id' => $recipient->notification->id,
                'title' => $recipient->notification->title,
                'message' => $recipient->notification->message,
                'type' => $recipient->notification->type,
                'data' => $recipient->notification->data,
                'is_read' => $recipient->is_read,
                'read_at' => $recipient->read_at,
                'created_at' => $recipient->notification->created_at,
                'updated_at' => $recipient->notification->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Success retrieved notifications',
            'data' => $items,
            'pagination' => [
                'current_page' => $pagination->currentPage(),
                'per_page' => $pagination->perPage(),
                'last_page' => $pagination->lastPage(),
                'total' => $pagination->total(),
            ],
        ]);
    }

    public function lowStock(Request $request)
    {
        $lowStockItems = $this->getLowStockItems();

        if ($lowStockItems->isNotEmpty()) {
            $this->storeLowStockNotifications($lowStockItems);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success retrieved low stock data',
            'data' => [
                'threshold' => self::LOW_STOCK_THRESHOLD,
                'low_stock_items' => $lowStockItems,
                'total' => $lowStockItems->count(),
            ],
        ]);
    }

    public function markAsRead($id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated', 'data' => null], 401);
        }

        $recipient = NotificationRecipient::where('notification_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$recipient) {
            return response()->json(['success' => false, 'message' => 'Notification not found', 'data' => null], 404);
        }

        if (!$recipient->is_read) {
            $recipient->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => [
                'id' => $id,
                'is_read' => true,
                'read_at' => $recipient->read_at,
            ],
        ]);
    }

    private function getLowStockItems()
    {
        $threshold = self::LOW_STOCK_THRESHOLD;

        // Use same selection logic as the SmallInventory index: any rack that has products
        // (the UI 'Gudang Kecil' tab is a view name, not a strict warehouse type filter)
        $lowStockRacks = Rack::query()
            ->with(['model', 'color', 'warehouse'])
            ->withCount('product')
            ->has('product')
            ->having('product_count', '<=', $threshold)
            ->get();

        if ($lowStockRacks->isEmpty()) {
            return collect();
        }

        return $lowStockRacks->map(function ($rack) use ($threshold) {
            return [
                'unique_key' => $this->buildUniqueKey($rack->id),
                'rack_id' => $rack->id,
                'rack_code' => $rack->code,
                'rack_name' => $rack->name,
                'model_id' => $rack->model_id,
                'model_name' => $rack->model?->name,
                'color_id' => $rack->color_id,
                'color_name' => $rack->color?->name,
                'size_id' => null,
                'size_name' => null,
                'quantity' => (int) $rack->product_count,
                'threshold' => $threshold,
                'warehouse_scope' => 'Gudang Kecil',
            ];
        })->values();
    }

    private function storeLowStockNotifications($lowStockItems)
    {
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->whereRaw('LOWER(name) = ?', ['admin']);
        })->get();

        if ($adminUsers->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($lowStockItems, $adminUsers) {
            foreach ($lowStockItems as $item) {
                $notification = Notification::updateOrCreate(
                    [
                        'unique_key' => $item['unique_key'],
                        'type' => self::LOW_STOCK_NOTIFICATION_TYPE,
                    ],
                    [
                        'title' => "Stok rendah: {$item['rack_name']}",
                        'message' => "{$item['quantity']} item tersisa di rak {$item['rack_name']}.",
                        'data' => [
                            'rack_id' => $item['rack_id'],
                            'rack_code' => $item['rack_code'],
                            'rack_name' => $item['rack_name'],
                            'model_id' => $item['model_id'],
                            'color_id' => $item['color_id'],
                            'size_id' => $item['size_id'],
                            'quantity' => $item['quantity'],
                            'threshold' => $item['threshold'],
                            'warehouse_scope' => $item['warehouse_scope'],
                        ],
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]
                );

                foreach ($adminUsers as $adminUser) {
                    NotificationRecipient::firstOrCreate(
                        [
                            'notification_id' => $notification->id,
                            'user_id' => $adminUser->id,
                        ],
                        [
                            'is_read' => false,
                        ]
                    );
                }
            }
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $adminUsers = User::whereHas('roles', function ($query) {
            $query->whereRaw('LOWER(name) = ?', ['admin']);
        })->get();

        if ($adminUsers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No admin user found'], 400);
        }

        $notification = DB::transaction(function () use ($request, $adminUsers) {
            $notification = Notification::create([
                'unique_key' => 'outbound:delete:' . uniqid(),
                'type' => $request->type,
                'title' => $request->title,
                'message' => $request->message,
                'data' => $request->data ?? [],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($adminUsers as $adminUser) {
                NotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'user_id' => $adminUser->id,
                    'is_read' => false,
                ]);
            }

            return $notification;
        });

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => $notification
        ]);
    }

    private function buildUniqueKey($rackId)
    {
        return 'low_stock:rack:' . ($rackId ?? 'none');
    }
}
