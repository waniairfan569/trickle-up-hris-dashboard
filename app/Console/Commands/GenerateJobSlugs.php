<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Job;

class GenerateJobSlugs extends Command
{
    protected $signature = 'jobs:generate-slugs';
    protected $description = 'Generate slugs for any jobs that are missing them';

    public function handle(): int
    {
        $jobs = Job::whereNull('slug')->get();
        $count = 0;
        foreach ($jobs as $job) {
            $slug = Str::slug($job->title) . '-' . Str::random(6);
            Job::where('id', $job->id)->update(['slug' => $slug]);
            $count++;
        }
        $this->info("Generated slugs for {$count} jobs.");
        return 0;
    }
}
