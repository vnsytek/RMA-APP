<?php

namespace App\Http\Controllers;

use App\Services\TicketNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TicketSequenceController extends Controller
{
    public function index(TicketNumberGenerator $numbers): View
    {
        return view('sequences.index', [
            'sequences' => DB::table('ticket_sequences')->orderByDesc('period')->get(),
            'nextTicketNo' => $numbers->peek(today()),
        ]);
    }
}
