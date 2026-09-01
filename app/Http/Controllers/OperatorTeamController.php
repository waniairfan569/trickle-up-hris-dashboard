<?php

namespace App\Http\Controllers;

use App\Models\OperatorAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Owner-only management of the operator team (Owner vs Support), plus the
 * operator action audit. Gated by the `operator.owner` middleware.
 */
class OperatorTeamController extends Controller
{
    public function index()
    {
        $operators = User::where('is_operator', true)
            ->orderByRaw("operator_role = 'owner' desc")
            ->orderBy('first_name')->get();

        $audit = OperatorAudit::with('operator')->latest()->limit(25)->get();

        return view('operator.operators', compact('operators', 'audit'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'required|email|max:255|unique:users,email',
            'operator_role' => ['required', Rule::in(['owner', 'support'])],
            'password'   => 'required|string|min:8',
        ]);

        $op = new User();
        $op->first_name = $data['first_name'];
        $op->last_name = $data['last_name'] ?? '';
        $op->email = $data['email'];
        $op->password = Hash::make($data['password']);
        $op->is_operator = true;
        $op->operator_role = $data['operator_role'];
        $op->account_status = 'active';
        $op->must_change_password = false;
        $op->email_verified_at = now();
        $op->save();

        // A platform operator belongs to no company.
        DB::table('users')->where('id', $op->id)->update(['company_id' => null, 'tenant_id' => null]);

        OperatorAudit::record('operator_added', "Added {$op->full_name} as {$data['operator_role']} operator.");

        return back()->with('success', "{$op->full_name} added as a {$data['operator_role']} operator.");
    }

    public function updateRole(Request $request, User $operator)
    {
        abort_unless($operator->is_operator, 404);
        $data = $request->validate(['operator_role' => ['required', Rule::in(['owner', 'support'])]]);

        // Never leave the platform without an owner.
        if ($data['operator_role'] === 'support' && $this->isLastOwner($operator)) {
            return back()->with('error', 'You can’t demote the last owner — promote another operator to owner first.');
        }

        $operator->update(['operator_role' => $data['operator_role']]);
        OperatorAudit::record('operator_role_changed', "Set {$operator->full_name} to {$data['operator_role']}.");

        return back()->with('success', "{$operator->full_name} is now a {$data['operator_role']} operator.");
    }

    public function revoke(User $operator)
    {
        abort_unless($operator->is_operator, 404);

        if ($operator->id === auth()->id()) {
            return back()->with('error', 'You can’t revoke your own operator access.');
        }
        if ($this->isLastOwner($operator)) {
            return back()->with('error', 'You can’t revoke the last owner.');
        }

        $name = $operator->full_name;
        $operator->update(['is_operator' => false, 'operator_role' => null]);
        OperatorAudit::record('operator_revoked', "Revoked operator access for {$name}.");

        return back()->with('success', "Operator access revoked for {$name}.");
    }

    private function isLastOwner(User $operator): bool
    {
        return $operator->operator_role === 'owner'
            && User::where('is_operator', true)->where('operator_role', 'owner')->count() <= 1;
    }
}
