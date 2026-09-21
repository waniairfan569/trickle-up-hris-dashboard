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
}
