<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class InfoController extends Controller
{
    public function playerGuide()
    {
        return Inertia::render('Info/PlayerGuide');
    }
}
