<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\ServiceProvider;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', Carbon::now()->toDateString());

        // Overall statistics
        $totalLeads = Lead::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $newLeads = Lead::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'new')->count();
        $contactedLeads = Lead::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'contacted')->count();
        $closedLeads = Lead::whereBetween('created_at', [$dateFrom, $dateTo])->where('status', 'closed')->count();
        
        $conversionRate = $totalLeads > 0 ? ($closedLeads / $totalLeads) * 100 : 0;

        // Leads by location
        $leadsByLocation = Location::withCount(['leads' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }])->get();

        // Leads by status (daily)
        $leadsByStatusDaily = Lead::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "new" THEN 1 ELSE 0 END) as new'),
            DB::raw('SUM(CASE WHEN status = "contacted" THEN 1 ELSE 0 END) as contacted'),
            DB::raw('SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as closed')
        )
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // Provider performance
        $providerPerformance = ServiceProvider::withCount(['leads' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }])
        ->withCount(['leads as closed_leads_count' => function ($query) use ($dateFrom, $dateTo) {
            $query->where('status', 'closed')->whereBetween('created_at', [$dateFrom, $dateTo]);
        }])
        ->get()
        ->map(function ($provider) {
            $conversionRate = $provider->leads_count > 0 
                ? ($provider->closed_leads_count / $provider->leads_count) * 100 
                : 0;
            
            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'total_leads' => $provider->leads_count,
                'closed_leads' => $provider->closed_leads_count,
                'conversion_rate' => round($conversionRate, 2),
            ];
        });

        return response()->json([
            'summary' => [
                'total_leads' => $totalLeads,
                'new_leads' => $newLeads,
                'contacted_leads' => $contactedLeads,
                'closed_leads' => $closedLeads,
                'conversion_rate' => round($conversionRate, 2),
            ],
            'leads_by_location' => $leadsByLocation,
            'leads_by_status_daily' => $leadsByStatusDaily,
            'provider_performance' => $providerPerformance,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }
}

