<?php

namespace App\Http\Controllers;

use App\Models\ApplicationError;
use Illuminate\Http\Request;

/** Operator view of captured application errors — see what clients hit. */
class OperatorErrorController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'open'); // open | resolved | all

        $query = ApplicationError::with(['user:id,first_name,last_name,email', 'tenant:id,name'])
            ->latest('updated_at');

        if ($filter === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($filter === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        return view('operator.errors', [
            'appErrors' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'openCount' => ApplicationError::whereNull('resolved_at')->count(),
        ]);
    }

    public function resolve(ApplicationError $error)
    {
        $error->update(['resolved_at' => now()]);

        return back()->with('success', 'Marked as resolved.');
    }

    public function reopen(ApplicationError $error)
    {
        $error->update(['resolved_at' => null]);

        return back()->with('success', 'Reopened.');
    }

    public function destroy(ApplicationError $error)
    {
        $error->delete();

        return back()->with('success', 'Error deleted.');
    }
}
