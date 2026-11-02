<?php

namespace App\Http\Controllers;

use App\Enums\TicketAction;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Services\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketActionController extends Controller
{
    public function store(Request $request, RmaTicket $ticket, TicketAction $action, TicketWorkflow $workflow): RedirectResponse
    {
        $input = $request->validateWithBag('action', $action->rules(), attributes: $action->attributes());
        $photos = Arr::pull($input, 'photos') ?? [];
        $newCenter = Arr::only($input, ['new_center_name', 'new_center_phone']);
        $input = Arr::except($input, array_keys($newCenter));

        try {
            DB::transaction(function () use ($workflow, $ticket, $action, $input, $request, $photos, $newCenter): void {
                if (($input['service_center_id'] ?? null) === 'new') {
                    $input['service_center_id'] = $this->findOrCreateCenter($newCenter)->id;
                }

                $workflow->perform($ticket, $action, $input, $request->user(), $photos);
            });
        } catch (ValidationException $exception) {
            throw $exception->errorBag('action');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('status', "{$ticket->ticket_no}: {$ticket->status->label()}.");
    }

    /**
     * A center typed in by staff in the step dialog. An existing one with the same name is reused (and
     * shown again if it was hidden) so the list never gets a duplicate.
     *
     * @param  array{new_center_name?: string, new_center_phone?: ?string}  $data
     */
    private function findOrCreateCenter(array $data): ServiceCenter
    {
        $center = ServiceCenter::firstOrCreate(
            ['name' => Str::squish($data['new_center_name'])],
            ['phone' => $data['new_center_phone'] ?? null],
        );

        if (! $center->is_active) {
            $center->update(['is_active' => true]);
        }

        return $center;
    }
}
