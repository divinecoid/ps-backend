<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transactions\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get real computed stats for the authenticated preparist user with filters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $userId = $user->id;
        $period = $request->query('period', 'today');
        
        // Define default boundaries
        $startDate = Carbon::today()->startOfDay();
        $endDate = Carbon::today()->endOfDay();

        if ($period === 'this_week') {
            $startDate = Carbon::now()->startOfWeek()->startOfDay(); // Monday
            $endDate = Carbon::now()->endOfDay();
        } elseif ($period === 'this_month') {
            $startDate = Carbon::now()->startOfMonth()->startOfDay(); // 1st day of month
            $endDate = Carbon::now()->endOfDay();
        } elseif ($period === 'custom') {
            try {
                $startInput = $request->query('start_date');
                $endInput = $request->query('end_date');
                
                if ($startInput && $endInput) {
                    $startDate = Carbon::parse($startInput)->startOfDay();
                    $endDate = Carbon::parse($endInput)->endOfDay();
                }
            } catch (\Exception $e) {
                // Fallback to today if parsing fails
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
            }
        }

        // 1. Calculations for General Stats (filtered by selected date range)
        $generalStats = Order::where('preparist_user_id', $userId)
            ->where(function ($query) use ($startDate, $endDate) {
                // Prepared orders should fall within the range
                $query->whereBetween('prepared_at', [$startDate, $endDate])
                      // OR pending orders created/assigned within the range
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->whereNull('prepared_at')
                            ->whereBetween('created_at', [$startDate, $endDate]);
                      });
            })
            ->selectRaw('
                COUNT(CASE WHEN prepared_at IS NOT NULL THEN 1 END) as total_packages,
                SUM(CASE WHEN prepared_at IS NOT NULL THEN item_count ELSE 0 END) as total_items,
                AVG(CASE WHEN prepared_at IS NOT NULL AND prepare_duration IS NOT NULL THEN prepare_duration END) as avg_prepare_duration_sec,
                COUNT(CASE WHEN prepared_at IS NOT NULL THEN 1 END) as completed_orders,
                COUNT(CASE WHEN prepared_at IS NULL THEN 1 END) as pending_orders
            ')
            ->first();

        // 2. Calculations for Today\'s Summary (always static for the current date)
        $today = Carbon::today();
        $todayStats = Order::where('preparist_user_id', $userId)
            ->whereDate('prepared_at', $today)
            ->selectRaw('
                COUNT(*) as today_packages,
                SUM(item_count) as today_items
            ')
            ->first();

        // Convert avg preparation time from seconds to minutes (rounded to 1 decimal place)
        $avgPrepSec = $generalStats->avg_prepare_duration_sec ?? 0;
        $avgPrepMin = round($avgPrepSec / 60, 1);

        // 3. Calculations for Weekly Chart Data (Last 7 days, formatted for the Flutter chart)
        $weeklyData = [];
        $dayNamesIndonesian = [
            1 => 'Sen',
            2 => 'Sel',
            3 => 'Rab',
            4 => 'Kam',
            5 => 'Jum',
            6 => 'Sab',
            7 => 'Min'
        ];

        // Loop for the last 7 days (including today)
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayOfWeek = (int) $date->format('N'); // 1 (Mon) - 7 (Sun)
            $dayLabel = $dayNamesIndonesian[$dayOfWeek];

            $dayStats = Order::where('preparist_user_id', $userId)
                ->whereDate('prepared_at', $date)
                ->selectRaw('
                    COUNT(*) as packages,
                    SUM(item_count) as items
                ')
                ->first();

            $weeklyData[] = [
                'day' => $dayLabel,
                'packages' => (int) ($dayStats->packages ?? 0),
                'items' => (int) ($dayStats->items ?? 0)
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'avgPreparationTime' => (double) $avgPrepMin,
                'totalPackages' => (int) ($generalStats->total_packages ?? 0),
                'totalItems' => (int) ($generalStats->total_items ?? 0),
                'completedOrders' => (int) ($generalStats->completed_orders ?? 0),
                'pendingOrders' => (int) ($generalStats->pending_orders ?? 0),
                'todayPackages' => (int) ($todayStats->today_packages ?? 0),
                'todayItems' => (int) ($todayStats->today_items ?? 0),
                'weeklyData' => $weeklyData
            ]
        ], 200);
    }
}
