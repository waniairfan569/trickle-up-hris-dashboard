<?php

namespace App\Console\Commands;

use App\Models\LetterheadTemplate;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Installs the official Trickle Up letterhead (brand wordmark header + yellow
 * contact-strip footer, from public/images/letterhead/) as the default for a
 * workspace. Idempotent — re-running updates it in place.
 */
class InstallTrickleUpLetterhead extends Command
{
    protected $signature = 'letterhead:install-trickleup {--tenant= : Tenant slug (defaults to trickle-up / the sole tenant)}';

    protected $description = 'Install the Trickle Up letterhead (header + footer band images) as the workspace default.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $this->resolveTenant();

        if (! $tenant && Tenant::count() > 1) {
            $this->error('Multiple tenants exist — pass --tenant=<slug> to choose which workspace to install into.');
            return self::FAILURE;
        }

        if ($tenant) {
            $manager->set($tenant);
        }

        $letterhead = LetterheadTemplate::updateOrCreate(
            ['name' => 'Trickle Up'],
            [
                'header_image_path'    => 'images/letterhead/brand.png',
                'footer_image_path'    => 'images/letterhead/footer.png',
                'watermark_image_path' => 'images/letterhead/watermark.png',
                'watermark_opacity'    => 0.9,
                'company_name'         => 'TRICKLE UP',
                'address'           => '55 St. Pauls Street, Leeds, England, LS1 2TE',
                'email'             => 'hello@trickleup.co.uk',
                'website'           => 'www.trickleup.co.uk',
                'header_bg'         => '#ffffff',
                'header_accent'     => '#F5D400',
                'footer_bg'         => '#F5D400',
                'footer_text'       => '#1a1a24',
            ]
        );

        $letterhead->makeDefault();
        $manager->set(null);

        $this->info('Trickle Up letterhead installed and set as default' . ($tenant ? " for {$tenant->slug}" : '') . '.');

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        if ($slug = $this->option('tenant')) {
            return Tenant::where('slug', $slug)->first();
        }

        return Tenant::where('slug', 'trickle-up')->first()
            ?? (Tenant::count() === 1 ? Tenant::first() : null);
    }
}
