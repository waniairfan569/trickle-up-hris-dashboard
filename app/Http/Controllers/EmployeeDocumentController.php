<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentController extends Controller
{
    private const ACCEPTED = 'pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,gif';

    /** Upload a document to an employee's profile (self or admin). */
    public function store(Request $request, User $employee)
    {
        $this->authorizeManage($employee);

        $validated = $request->validate([
            'name' => 'required|string|max:70',
            'file' => 'required|file|max:20480|mimes:' . self::ACCEPTED, // 20 MB
        ]);

        $file = $request->file('file');
        $path = $file->store(\App\Tenancy\TenantStorage::path('employee-documents')); // private 'local' disk

        EmployeeDocument::create([
            'user_id' => $employee->id,
            'name' => $validated['name'],
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('employees.profile', $employee->id)
            ->with('success', 'File uploaded successfully.');
    }

    /** Download a document (self or admin). */
    public function download(User $employee, EmployeeDocument $document)
    {
        $this->authorizeManage($employee);
        abort_unless($document->user_id === $employee->id, 404);

        if (!Storage::exists($document->path)) {
            abort(404, 'File not found.');
        }

        return Storage::download($document->path, $document->original_name);
    }

    /** Delete a document (self or admin). */
    public function destroy(User $employee, EmployeeDocument $document)
    {
        $this->authorizeManage($employee);
        abort_unless($document->user_id === $employee->id, 404);

        Storage::delete($document->path);
        $document->delete();

        return redirect()->route('employees.profile', $employee->id)
            ->with('success', 'File deleted.');
    }

    /** Only the employee themselves or an admin may manage these documents. */
    private function authorizeManage(User $employee): void
    {
        $auth = auth()->user();
        abort_unless($auth && ($auth->isAdmin() || $auth->id === $employee->id), 403, 'You cannot manage these files.');
    }
}
