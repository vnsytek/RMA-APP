<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketNumberGenerator
{
    /**
     * Reserve the next ticket number for the month of the given date, e.g. "26090001".
     *
     * The sequence row is locked until the surrounding transaction commits, so two
     * people creating tickets at the same time can never receive the same number.
     */
    public function next(CarbonInterface $date): string
    {
        $type = config('rma.ticket_type');
        $period = $date->format('ym');

        return DB::transaction(function () use ($type, $period): string {
            DB::table('ticket_sequences')->insertOrIgnore([
                'ticket_type' => $type,
                'period' => $period,
                'last_number' => 0,
            ]);

            $sequence = DB::table('ticket_sequences')
                ->where('ticket_type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $next = $sequence->last_number + 1;

            if ($next > config('rma.max_tickets_per_month')) {
                throw ValidationException::withMessages([
                    'ticket' => "Đã hết số phiếu của tháng {$period} (tối đa ".config('rma.max_tickets_per_month').' phiếu).',
                ]);
            }

            DB::table('ticket_sequences')
                ->where('ticket_type', $type)
                ->where('period', $period)
                ->update(['last_number' => $next]);

            return $period.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * The number the next ticket would get, without reserving it.
     */
    public function peek(CarbonInterface $date): string
    {
        $period = $date->format('ym');
        $last = (int) DB::table('ticket_sequences')
            ->where('ticket_type', config('rma.ticket_type'))
            ->where('period', $period)
            ->value('last_number');

        return $period.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
