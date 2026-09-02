<?php

namespace App\Http\Controllers;

use App\Support\HelpCenter;
use Illuminate\Support\Str;

/** Public help centre. */
class HelpController extends Controller
{
    public function index()
    {
        return view('help.index', [
            'grouped' => HelpCenter::grouped(),
        ]);
    }

    public function show(string $slug)
    {
        $article = HelpCenter::find($slug);
        abort_unless($article, 404);

        $article['html'] = Str::markdown($article['body']);

        return view('help.show', [
            'article' => $article,
            'categories' => HelpCenter::categories(),
            'grouped' => HelpCenter::grouped(),
        ]);
    }
}
