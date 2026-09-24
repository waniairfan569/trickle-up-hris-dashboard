<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A reusable branded letterhead (header + footer) applied to generated HR
 * documents. One per workspace is the default; documents/templates can override.
 */
class LetterheadTemplate extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'name', 'company_entity_id', 'logo_path', 'header_image_path', 'footer_image_path',
        'watermark_image_path', 'watermark_opacity',
        'company_name', 'company_suffix',
        'address', 'email', 'website', 'phone',
        'header_bg', 'header_accent', 'footer_bg', 'footer_text',
        'is_default', 'created_by',
    ];

    protected $casts = ['is_default' => 'boolean', 'watermark_opacity' => 'float'];

    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    /** The workspace's default letterhead (marked default, else the first one). */
    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::orderBy('id')->first();
    }

    /** Make this the sole default for the workspace. */
    public function makeDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    /** A stored path (public disk or a public/ asset) as a base64 data URI DomPDF can embed. */
    private function fileToDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $candidates = [];
        if (Storage::disk('public')->exists($path)) {
            $candidates[] = Storage::disk('public')->path($path);
        }
        $candidates[] = public_path($path);

        foreach ($candidates as $file) {
            if ($file && is_file($file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg']) ? 'image/jpeg' : ($ext === 'svg' ? 'image/svg+xml' : 'image/png');
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file));
            }
        }
        return null;
    }

    /** Logo as a data URI — uploaded logo, else the app mark. */
    public function logoData(): ?string
    {
        return $this->fileToDataUri($this->logo_path) ?? $this->fileToDataUri('images/logo.png');
    }

    /** Pre-designed header band image (e.g. a brand wordmark), if set. */
    public function headerImageData(): ?string
    {
        return $this->fileToDataUri($this->header_image_path);
    }

    /** Pre-designed full-width footer band image (contacts strip), if set. */
    public function footerImageData(): ?string
    {
        return $this->fileToDataUri($this->footer_image_path);
    }

    /** Faint page watermark image (large brand mark behind the body), if set. */
    public function watermarkImageData(): ?string
    {
        return $this->fileToDataUri($this->watermark_image_path);
    }

    /**
     * A footer icon (mail|building|globe) tinted to $hex, as a base64 PNG data URI.
     * PNG (not inline SVG) so it renders reliably in DomPDF.
     */
    public function iconDataUri(string $name, string $hex): ?string
    {
        $path = public_path("images/letterhead/icons/{$name}.png");
        if (! is_file($path) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $rgb = sscanf($hex, '#%02x%02x%02x');
        if (count(array_filter($rgb, fn ($v) => $v !== null)) < 3) {
            $rgb = [26, 26, 36];
        }
        [$r, $g, $b] = $rgb;

        $src = @imagecreatefrompng($path);
        if (! $src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $out = imagecreatetruecolor($w, $h);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $alpha = (imagecolorat($src, $x, $y) >> 24) & 0x7F;
                if ($alpha < 127) {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
                }
            }
        }

        ob_start();
        imagepng($out);
        $data = ob_get_clean();
        imagedestroy($src);
        imagedestroy($out);

        return 'data:image/png;base64,' . base64_encode($data);
    }

    /** Default field values for a fresh "create" form (colours + workspace name). */
    public static function starterDefaults(): array
    {
        $tenant = \App\Tenancy\Brand::tenant();
        $entity = CompanyEntity::where('is_primary', true)->first() ?? CompanyEntity::first();

        return [
            'company_name'  => mb_strtoupper($entity->legal_name ?? $entity->name ?? ($tenant?->displayName() ?? config('app.name', 'Company'))),
            'header_bg'     => '#FFFDF5',
            'header_accent' => '#fcd82f',
            'footer_bg'     => '#1F5FD6',
            'footer_text'   => '#ffffff',
        ];
    }

    /** Create a sensible starter letterhead from the primary company entity + branding. */
    public static function starterFromWorkspace(?int $userId = null): self
    {
        $entity = CompanyEntity::where('is_primary', true)->first() ?? CompanyEntity::first();
        $tenant = \App\Tenancy\Brand::tenant();

        $name = $entity->legal_name ?? $entity->name ?? ($tenant?->displayName() ?? config('app.name', 'Company'));
        $address = $entity
            ? collect([$entity->address_line1, $entity->address_line2, trim(($entity->city ?? '') . ' ' . ($entity->country ?? ''))])
                ->filter()->implode("\n")
            : null;

        return static::create([
            'name'              => 'Default letterhead',
            'company_entity_id' => $entity?->id,
            'company_name'      => mb_strtoupper($name),
            'company_suffix'    => $entity?->registration_number ? 'Reg. ' . $entity->registration_number : null,
            'address'           => $address ?: null,
            'email'             => $tenant?->from_email,
            'is_default'        => true,
            'created_by'        => $userId,
        ]);
    }
}
