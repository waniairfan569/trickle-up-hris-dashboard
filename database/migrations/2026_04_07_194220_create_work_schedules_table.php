<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // day_of_week: 1=Monday ... 7=Sunday (ISO 8601)
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Mon ... 7=Sun
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'day_of_week']);
        });

        Schema::disableForeignKeyConstraints();
        // Seed default schedule (Mon–Fri 9–5, Sat–Sun off) for company 1
        $companyId = DB::table('companies')->first()?->id ?? 1;
        $days = [
            ['day' => 1, 'start' => '09:00:00', 'end' => '17:00:00', 'break' => 60, 'working' => true],
            ['day' => 2, 'start' => '09:00:00', 'end' => '17:00:00', 'break' => 60, 'working' => true],
            ['day' => 3, 'start' => '09:00:00', 'end' => '17:00:00', 'break' => 60, 'working' => true],
            ['day' => 4, 'start' => '09:00:00', 'end' => '17:00:00', 'break' => 60, 'working' => true],
            ['day' => 5, 'start' => '09:00:00', 'end' => '17:00:00', 'break' => 60, 'working' => true],
            ['day' => 6, 'start' => '09:00:00', 'end' => '13:00:00', 'break' =>  0, 'working' => false],
            ['day' => 7, 'start' => '09:00:00', 'end' => '13:00:00', 'break' =>  0, 'working' => false],
        ];
        foreach ($days as $d) {
            DB::table('work_schedules')->insert([
                'company_id'    => $companyId,
                'day_of_week'   => $d['day'],
                'start_time'    => $d['start'],
                'end_time'      => $d['end'],
                'break_minutes' => $d['break'],
                'is_working_day'=> $d['working'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
