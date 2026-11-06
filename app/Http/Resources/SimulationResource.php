<?php

namespace App\Http\Resources;

use App\Models\SimulationClick;
use App\Models\SimulationRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimulationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $data = [
            'dataSource' => 'database', 'id' => $item->id, 'name' => $item->name, 'description' => $item->description ?? '', 'status' => $item->effectiveStatus(),
            'senderName' => $item->sender_name, 'senderEmail' => $item->sender_email, 'replyTo' => $item->reply_to,
            'scheduledAt' => $item->starts_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') ?? '',
            'endsAt' => $item->ends_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') ?? '',
            'recipientMode' => $item->recipient_mode, 'templateId' => $item->template_id ? (string) $item->template_id : '',
            'message' => ['subject' => $item->subject, 'delta' => $item->delta],
            'target' => (int) $item->target_count, 'sent' => (int) $item->sent_count, 'clicked' => (int) $item->clicked_count,
            'pending' => (int) $item->pending_count + (int) $item->processing_count, 'uncertain' => (int) $item->uncertain_count, 'skipped' => (int) $item->skipped_count,
            'failed' => 0, 'sendIntervalSeconds' => $item->send_interval_seconds, 'lastError' => $item->last_error,
            'updatedAt' => $item->updated_at?->toISOString(),
        ];
        if ($data['status'] === 'completed') {
            $data['pending'] = (int) $item->processing_count;
            $data['skipped'] += (int) $item->pending_count;
        }
        if ($item->relationLoaded('recipients')) {
            $data['recipientIds'] = $item->recipients->whereNotNull('recipient_id')->pluck('recipient_id')->all();
            $data['recipientRegistry'] = $item->recipients->map(fn (SimulationRecipient $r): array => ['id' => $r->recipient_id ?? -$r->id, 'email' => $r->email])->all();
            $data['recipientRows'] = $item->recipients->map(function (SimulationRecipient $r) use ($data): array {
                $events = $r->clicks->sortBy('clicked_at')->map(fn (SimulationClick $click): array => ['at' => $click->clicked_at->toISOString(), 'ip' => $click->ip ?? '', 'period' => $click->period, 'browser' => 'Tidak diketahui', 'os' => 'Tidak diketahui', 'device' => 'Tidak diketahui'])->values()->all();
                $within = count(array_filter($events, fn (array $e): bool => $e['period'] === 'within'));
                $after = count(array_filter($events, fn (array $e): bool => $e['period'] === 'after'));
                $before = count(array_filter($events, fn (array $e): bool => $e['period'] === 'before'));
                $delivery = match ($r->status) {
                    'sent' => 'Berhasil','uncertain' => 'Perlu diperiksa','skipped' => 'Tidak dikirim','sending' => 'Sedang diproses',default => $data['status'] === 'completed' ? 'Tidak dikirim' : 'Belum dikirim'
                };

                return [
                    'id' => $data['id'].'-recipient-'.$r->id, 'recipientId' => $r->id, 'campaignId' => $data['id'], 'campaignName' => $data['name'], 'campaignDate' => substr($data['scheduledAt'], 0, 10),
                    'scheduledAt' => $data['scheduledAt'], 'endsAt' => $data['endsAt'], 'hasPeriod' => $data['scheduledAt'] !== '' && $data['endsAt'] !== '',
                    'dataSource' => 'database', 'email' => $r->email, 'delivery' => $delivery, 'deliveryStatus' => $r->status, 'status' => $r->status === 'sent' ? ($events ? 'clicked' : 'not_clicked') : ($r->status === 'pending' && $data['status'] === 'completed' ? 'skipped' : $r->status),
                    'clicked' => (bool) $events, 'clicks' => count($events), 'withinClicks' => $within, 'afterClicks' => $after, 'beforeClicks' => $before, 'unclassifiedClicks' => 0,
                    'period' => $within ? 'within' : ($after ? 'after' : ($before ? 'before' : 'none')),
                    'firstClick' => $events[0]['at'] ?? '', 'lastClick' => $events ? $events[array_key_last($events)]['at'] : '',
                    'ip' => $events ? $events[array_key_last($events)]['ip'] : '',
                    'browser' => 'Tidak diketahui', 'os' => 'Tidak diketahui', 'device' => 'Tidak diketahui', 'events' => $events, 'error' => $r->error,
                ];
            })->all();
        }

        return $data;
    }
}
