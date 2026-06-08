<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = User::with('departmentAssignments.department')
            ->where('company_id', $request->user()->company_id);

        if ($request->has('role')) $query->where('role', $request->role);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $profileRules = [
            'first_name'          => 'sometimes|required|string|max:100',
            'last_name'           => 'sometimes|required|string|max:100',
            'email'               => 'sometimes|required|email|unique:users,email',
            'role_id'             => 'sometimes|required|exists:roles,id',
            'status'              => 'sometimes|in:active,inactive,pending',
            'phone'               => 'nullable|string|max:30',
            'city'                => 'nullable|string|max:100',
            'country'             => 'nullable|string|max:100',
            'timezone'            => 'nullable|string|max:100',
            'date_of_birth'       => 'nullable|date',
            'nationality'         => 'nullable|string|max:100',
            'gender'              => 'nullable|string|max:30',
            'languages'           => 'nullable|string|max:255',
            'linkedin_url'        => 'nullable|url|max:500',
            'github_url'          => 'nullable|url|max:500',
            'portfolio_url'       => 'nullable|url|max:500',
            'job_title'           => 'nullable|string|max:255',
            'employee_id'         => 'nullable|string|max:100',
            'manager_id'          => 'nullable|exists:users,id',
            'hire_date'           => 'nullable|date',
            'contract_type'       => 'nullable|string|max:50',
            'salary'              => 'nullable|numeric|min:0',
            'salary_currency'     => 'nullable|string|max:10',
            'notice_period_days'  => 'nullable|integer|min:0',
            'years_of_experience' => 'nullable|integer|min:0',
            'education'           => 'nullable|string|max:255',
            'specialization'      => 'nullable|string|max:255',
            'skills'              => 'nullable|array',
            'skills.*'            => 'string|max:100',
            'admin_notes'         => 'nullable|string',
            'department_id'       => 'nullable|exists:departments,id',
            'location_id'         => 'nullable|exists:locations,id',
        ];

        $data = $request->validate($profileRules);

        $userData = $request->only([
            'first_name', 'last_name', 'email', 'role_id', 'phone', 'city', 'country', 
            'timezone', 'date_of_birth', 'nationality', 'gender', 'languages', 
            'linkedin_url', 'github_url', 'portfolio_url', 'job_title', 'employee_id', 
            'manager_id', 'hire_date', 'contract_type', 'salary', 'salary_currency', 
            'notice_period_days', 'years_of_experience', 'education', 'specialization', 
            'skills', 'admin_notes'
        ]);

        $user = User::create(array_merge($userData, [
            'company_id' => $request->user()->company_id,
            'password' => Hash::make(Str::random(12)),
            'status' => 'pending',
        ]));

        $user->departmentAssignments()->create([
            'department_id' => $data['department_id'],
            'location_id' => $data['location_id'] ?? null,
            'assigned_role' => 'Member'
        ]);

        $this->logActivity('created', 'User', $user->id, "Invited user {$user->email}");
        // Mail::fake(); // handled differently in production
        return response()->json($user, 201);
    }

    public function show(Request $request, $id)
    {
        $user = User::with([
            'manager:id,first_name,last_name,job_title,avatar_url',
            'role:id,name,permissions',
            'departmentAssignments.department:id,name',
            'departmentAssignments.location:id,name,city',
            'activityLogs' => fn($q) => $q->latest()->limit(50),
        ])->where('company_id', $request->user()->company_id)->findOrFail($id);

        // Jobs this user is assigned to
        $assignedJobs = \App\Models\Job::whereHas('interviews', fn($q) => $q->where('interviewer_id', $id))
            ->orWhereHas('candidates', fn($q) => $q->whereHas('evaluations', fn($eq) => $eq->where('evaluator_id', $id)))
            ->with(['department:id,name', 'location:id,name'])
            ->withCount('candidates')
            ->limit(20)
            ->get();

        // Stats
        $stats = [
            'jobs_assigned'        => $assignedJobs->count(),
            'candidates_reviewed'  => \App\Models\CandidateEvaluation::where('evaluator_id', $id)->count(),
            'interviews_conducted' => \App\Models\Interview::where('interviewer_id', $id)->count(),
            'avg_eval_score'       => round(
                \App\Models\CandidateEvaluation::where('evaluator_id', $id)->avg('overall_score') ?? 0, 1
            ),
        ];

        // Active sessions (from personal_access_tokens)
        $sessions = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $id)
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'last_used_at', 'created_at']);

        return response()->json([
            'user'          => $user,
            'assigned_jobs' => $assignedJobs,
            'stats'         => $stats,
            'sessions'      => $sessions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);

        $profileRules = [
            'first_name'          => 'sometimes|required|string|max:100',
            'last_name'           => 'sometimes|required|string|max:100',
            'email'               => 'sometimes|required|email|unique:users,email,' . ($id ?? 'NULL'),
            'role_id'             => 'sometimes|required|exists:roles,id',
            'status'              => 'sometimes|in:active,inactive,pending',
            'phone'               => 'nullable|string|max:30',
            'city'                => 'nullable|string|max:100',
            'country'             => 'nullable|string|max:100',
            'timezone'            => 'nullable|string|max:100',
            'date_of_birth'       => 'nullable|date',
            'nationality'         => 'nullable|string|max:100',
            'gender'              => 'nullable|string|max:30',
            'languages'           => 'nullable|string|max:255',
            'linkedin_url'        => 'nullable|url|max:500',
            'github_url'          => 'nullable|url|max:500',
            'portfolio_url'       => 'nullable|url|max:500',
            'job_title'           => 'nullable|string|max:255',
            'employee_id'         => 'nullable|string|max:100',
            'manager_id'          => 'nullable|exists:users,id',
            'hire_date'           => 'nullable|date',
            'contract_type'       => 'nullable|string|max:50',
            'salary'              => 'nullable|numeric|min:0',
            'salary_currency'     => 'nullable|string|max:10',
            'notice_period_days'  => 'nullable|integer|min:0',
            'years_of_experience' => 'nullable|integer|min:0',
            'education'           => 'nullable|string|max:255',
            'specialization'      => 'nullable|string|max:255',
            'skills'              => 'nullable|array',
            'skills.*'            => 'string|max:100',
            'admin_notes'         => 'nullable|string',
            'department_id'       => 'nullable|exists:departments,id',
            'location_id'         => 'nullable|exists:locations,id',
        ];

        $data = $request->validate($profileRules);

        // --- Super Admin Self-Role Protection ---
        // If the target user is a Super Admin and they're trying to change their own role,
        // block it unless another Super Admin exists in the company.
        if ($request->has('role_id') && (int)$id === $request->user()->id) {
            $superAdminRole = \App\Models\Role::where('name', 'Super Admin')
                ->where('company_id', $request->user()->company_id)
                ->first();

            if ($superAdminRole && $user->role_id === $superAdminRole->id) {
                // They are currently a Super Admin trying to change their own role
                $otherSuperAdmins = User::where('company_id', $request->user()->company_id)
                    ->where('role_id', $superAdminRole->id)
                    ->where('id', '!=', $id)
                    ->count();

                if ($otherSuperAdmins === 0) {
                    return response()->json([
                        'message' => 'You cannot change your own role while you are the only Super Admin. Please assign another Super Admin first.'
                    ], 403);
                }
            }
        }

        $user->update(\Illuminate\Support\Arr::except($data, ['department_id', 'location_id']));


        if ($request->has('department_id')) {
            $user->departmentAssignments()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department_id' => $data['department_id'],
                    'location_id' => $data['location_id'] ?? null,
                ]
            );
        }
        
        $this->logActivity('updated', 'User', $user->id, "Updated user {$user->email}");
        return response()->json($user);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        $user->update(['status' => 'inactive']);
        $this->logActivity('deactivated', 'User', $user->id, "Deactivated user {$user->email}");
        return response()->json(['message' => 'User deactivated']);
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        
        if ($request->has('password')) {
            $request->validate(['password' => 'required|string|min:6']);
            $user->password = Hash::make($request->password);
            $user->save();
            return response()->json(['message' => 'Password updated successfully']);
        }

        $token = Str::random(60);
        return response()->json(['reset_token' => $token]);
    }

    public function saveNotes(Request $request, $id)
    {
        $request->validate(['admin_notes' => 'required|string']);
        User::findOrFail($id)->update(['admin_notes' => $request->admin_notes]);
        return response()->json(['saved' => true]);
    }

    public function revokeSession($id, $tokenId)
    {
        \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $id)
            ->where('id', $tokenId)
            ->delete();
        return response()->json(['revoked' => true]);
    }

    public function revokeAllSessions($id)
    {
        \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $id)->delete();
        return response()->json(['revoked' => true]);
    }
}
