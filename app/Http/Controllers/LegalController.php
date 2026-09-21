<?php

namespace App\Http\Controllers;

/** Public legal documents — Terms of Service, Privacy Policy, DPA, Cookie Policy, Refund Policy. */
class LegalController extends Controller
{
    public function terms()
    {
        return view('legal.terms');
    }

    public function privacy()
    {
        return view('legal.privacy');
    }

    public function dpa()
    {
        return view('legal.dpa');
    }

    public function cookies()
    {
        return view('legal.cookies');
    }

    public function refund()
    {
        return view('legal.refund');
    }

    /** RFC 9116 responsible-disclosure contact (/.well-known/security.txt). */
    public function securityTxt()
    {
        $email = config('legal.contact_email');
        $base = rtrim(config('app.url'), '/');

        $lines = [
            'Contact: mailto:' . $email,
            'Expires: ' . now()->addYear()->startOfDay()->toIso8601ZuluString(),
            'Preferred-Languages: en',
            'Canonical: ' . $base . '/.well-known/security.txt',
            'Policy: ' . route('legal.terms'),
        ];

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
