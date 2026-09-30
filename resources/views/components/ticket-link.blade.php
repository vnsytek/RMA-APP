@props(['ticket'])

{{-- A ticket number that links to the ticket only when the viewer may open it. --}}
@can('view', $ticket)
    <a href="{{ route('tickets.show', $ticket) }}" {{ $attributes->class(['ticket-no']) }}>{{ $ticket->ticket_no }}</a>
@else
    <span {{ $attributes->class(['ticket-no']) }} title="Phiếu do nhân viên khác phụ trách">{{ $ticket->ticket_no }}</span>
@endcan
