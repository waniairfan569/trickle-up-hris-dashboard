<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Personal
            if (!Schema::hasColumn('users', 'phone'))
                $table->string('phone', 30)->nullable()->after('email');
            if (!Schema::hasColumn('users', 'address'))
                $table->string('address', 255)->nullable()->after('phone');
            if (!Schema::hasColumn('users', 'city'))
                $table->string('city', 100)->nullable()->after('address');
            if (!Schema::hasColumn('users', 'country'))
                $table->string('country', 100)->nullable()->after('city');
            if (!Schema::hasColumn('users', 'timezone'))
                $table->string('timezone', 100)->default('UTC')->after('country');
            if (!Schema::hasColumn('users', 'date_of_birth'))
                $table->date('date_of_birth')->nullable()->after('timezone');
            if (!Schema::hasColumn('users', 'nationality'))
                $table->string('nationality', 100)->nullable()->after('date_of_birth');
            if (!Schema::hasColumn('users', 'gender'))
                $table->string('gender', 30)->nullable()->after('nationality');
            if (!Schema::hasColumn('users', 'languages'))
                $table->string('languages', 255)->nullable()->after('gender');

            // Professional links
            if (!Schema::hasColumn('users', 'linkedin_url'))
                $table->string('linkedin_url', 500)->nullable()->after('languages');
            if (!Schema::hasColumn('users', 'github_url'))
                $table->string('github_url', 500)->nullable()->after('linkedin_url');
            if (!Schema::hasColumn('users', 'portfolio_url'))
                $table->string('portfolio_url', 500)->nullable()->after('github_url');

            // Work details
            if (!Schema::hasColumn('users', 'job_title'))
                $table->string('job_title', 255)->nullable()->after('portfolio_url');
            if (!Schema::hasColumn('users', 'employee_id'))
                $table->string('employee_id', 100)->nullable()->after('job_title');
            if (!Schema::hasColumn('users', 'manager_id'))
                $table->unsignedBigInteger('manager_id')->nullable()->after('employee_id');
            if (!Schema::hasColumn('users', 'hire_date'))
                $table->date('hire_date')->nullable()->after('manager_id');
            if (!Schema::hasColumn('users', 'contract_type'))
                $table->string('contract_type', 50)->nullable()->after('hire_date');
            if (!Schema::hasColumn('users', 'salary'))
                $table->decimal('salary', 10, 2)->nullable()->after('contract_type');
            if (!Schema::hasColumn('users', 'salary_currency'))
                $table->string('salary_currency', 10)->default('USD')->after('salary');
            if (!Schema::hasColumn('users', 'notice_period_days'))
                $table->integer('notice_period_days')->nullable()->after('salary_currency');
            if (!Schema::hasColumn('users', 'years_of_experience'))
                $table->integer('years_of_experience')->nullable()->after('notice_period_days');
            if (!Schema::hasColumn('users', 'education'))
                $table->string('education', 255)->nullable()->after('years_of_experience');
            if (!Schema::hasColumn('users', 'specialization'))
                $table->string('specialization', 255)->nullable()->after('education');
            if (!Schema::hasColumn('users', 'skills'))
                $table->json('skills')->nullable()->after('specialization');

            // Account settings
            if (!Schema::hasColumn('users', 'two_factor_enabled'))
                $table->boolean('two_factor_enabled')->default(false)->after('skills');
            if (!Schema::hasColumn('users', 'sso_provider'))
                $table->string('sso_provider', 50)->nullable()->after('two_factor_enabled');
            if (!Schema::hasColumn('users', 'admin_notes'))
                $table->text('admin_notes')->nullable()->after('sso_provider');

            // Foreign key for manager
            if (!Schema::hasColumn('users', 'manager_id')) {
                $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone','address','city','country','timezone','date_of_birth',
                'nationality','gender','languages','linkedin_url','github_url',
                'portfolio_url','job_title','employee_id','manager_id','hire_date',
                'contract_type','salary','salary_currency','notice_period_days',
                'years_of_experience','education','specialization','skills',
                'two_factor_enabled','sso_provider','admin_notes',
            ]);
        });
    }
};
