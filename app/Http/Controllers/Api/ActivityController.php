<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')
            ->where('company_id', $request->user()->company_id);

        // Rule 11: activity of non-admins should be to only user related 
        if (!in_array($request->user()->role, ['super_admin','admin','recruiting_admin','manager','hiring_manager'])) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('entity_type')) $query->where('entity_type', $request->entity_type);
        if ($request->has('user_id') && in_array($request->user()->role, ['super_admin','admin'])) $query->where('user_id', $request->user_id);
        
        // Basic date range filter
        if ($request->has('from_date')) $query->whereDate('created_at', '>=', $request->from_date);
        if ($request->has('to_date')) $query->whereDate('created_at', '<=', $request->to_date);

        return response()->json($query->orderBy('created_at', 'desc')->paginate(20));
    }
}
