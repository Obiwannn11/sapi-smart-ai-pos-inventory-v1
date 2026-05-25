<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * Display the single-page landing page for SAPI.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('public.landing');
    }
}
