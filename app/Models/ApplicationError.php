<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/**
 * A captured application error — platform-global (operator-visible), NOT
 * tenant-scoped. Written by the exception handler; read in the Platform Console.
 */
class ApplicationError extends Model
{
    protected $fillable = [
        'fingerprint', 'exception', 'message', 'file', 'line', 'url', 'method',
        'status_code', 'user_id', 'tenant_id', 'trace', 'occurrences', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'occurrences' => 'integer',
    ];

    /** Exception types that are normal request outcomes, not bugs — never logged. */
    private const IGNORE = [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Record an exception. Silently ignores expected/handled exceptions and any
     * HTTP exception below 500 (404s, 419s, 403s, etc.). Never throws.
     */
    public static function log(Throwable $e, ?Request $request = null): void
    {
        try {
            foreach (self::IGNORE as $type) {
                if ($e instanceof $type) {
                    return;
                }
            }

            $status = null;
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                if ($status < 500) {
                    return; // client-side HTTP errors aren't bugs
                }
            }

            $fingerprint = md5(get_class($e) . '|' . $e->getFile() . '|' . $e->getLine());

            $data = [
                'exception' => class_basename($e),
                'message' => \Illuminate\Support\Str::limit($e->getMessage(), 2000),
                'file' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', (string) $e->getFile()),
                'line' => $e->getLine(),
                'url' => $request ? \Illuminate\Support\Str::limit($request->fullUrl(), 1000) : null,
                'method' => $request?->method(),
                'status_code' => $status ?? 500,
                'user_id' => optional($request?->user())->id,
                'tenant_id' => optional($request?->user())->tenant_id,
                'trace' => \Illuminate\Support\Str::limit($e->getTraceAsString(), 3000),
            ];

            // De-dupe: bump an existing unresolved row, else create a new one.
            $existing = static::where('fingerprint', $fingerprint)->whereNull('resolved_at')->first();
            if ($existing) {
                $existing->fill($data);
                $existing->occurrences++;
                $existing->save(); // touches updated_at = last seen
            } else {
                static::create(['fingerprint' => $fingerprint] + $data);
            }
        } catch (Throwable $ignore) {
            // Logging errors must never break the request.
        }
    }
}
