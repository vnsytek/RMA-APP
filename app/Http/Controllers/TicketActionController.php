<?php

namespace App\Http\Controllers;

use App\Enums\TicketAction;
use App\Models\RmaTicket;
use App\Services\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TicketActionController extends Controller
{
    public function store(Request $request, RmaTicket $ticket, TicketAction $action, TicketWorkflow $workflow): RedirectResponse
    {
        $input = $request->validateWithBag('action', $action->rules(), attributes: $action->attributes());
        $photos = Arr::pull($input, 'photos') ?? [];

        try {
            $workflow->perform($ticket, $action, $input, $request->user(), $photos);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('action');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('status', "{$ticket->ticket_no}: {$ticket->status->label()}.");
    }
}
