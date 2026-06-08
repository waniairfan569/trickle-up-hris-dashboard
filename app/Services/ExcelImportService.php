<?php

namespace App\Services;

use App\Imports\ZktecoExcelImport;
use App\Models\AttendanceImportLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImportService
{
    public function import(UploadedFile $file, User $by): array
    {
        $import = new ZktecoExcelImport();
        
        Excel::import($import, $file);
        
        $result = $import->result;

        AttendanceImportLog::create([
            'filename' => $file->getClientOriginalName(),
            'imported_by' => $by->id,
            'source' => 'excel',
            'total_rows' => $result['total'],
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'unmapped' => $result['unmapped'],
        ]);

        return $result;
    }
}
