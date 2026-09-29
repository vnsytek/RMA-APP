<x-layouts.app title="Bộ đếm số phiếu" subtitle="Số thứ tự đã cấp theo từng tháng. Mọi hình thức dùng chung một dãy số.">
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tháng</th><th class="text-right">Số đã cấp</th><th>Số phiếu cuối</th></tr></thead>
                <tbody>
                    @forelse ($sequences as $sequence)
                        <tr>
                            <td>{{ substr($sequence->period, 2, 2) }}/20{{ substr($sequence->period, 0, 2) }}</td>
                            <td class="text-right">{{ $sequence->last_number }}</td>
                            <td class="ticket-no">{{ $sequence->period }}{{ str_pad((string) $sequence->last_number, 4, '0', STR_PAD_LEFT) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">Chưa cấp số phiếu nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card p-5">
            <h2 class="font-semibold">Số phiếu tiếp theo</h2>
            <p class="mt-2 font-mono text-2xl font-semibold text-brand">{{ $nextTicketNo }}</p>
            <p class="mt-2 text-sm text-muted">Định dạng năm (2 số) + tháng (2 số) + số thứ tự 4 số. Sang tháng mới số thứ tự quay về 0001. Tối đa {{ config('rma.max_tickets_per_month') }} phiếu mỗi tháng.</p>
        </div>
    </div>
</x-layouts.app>
