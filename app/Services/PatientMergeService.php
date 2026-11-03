<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Conversation;
use App\Models\Image;
use App\Models\Message;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Merges a duplicate patient record into the one the clinic keeps: every case, bill,
 * reservation, prescription, note, image, booking request, WhatsApp conversation and
 * automation target moves over, the kept record's empty details are filled from the
 * duplicate, and the duplicate is deleted. Switched on per clinic by the tenant's
 * can_merge_patients flag.
 */
class PatientMergeService
{
    /**
     * Tables whose rows belong to a patient through patient_id. Conversations are
     * moved separately: a patient has at most one per channel.
     */
    private const PATIENT_TABLES = ['cases', 'bills', 'reservations', 'recipes', 'booking_requests', 'automation_targets'];

    // Morph values a patient's notes and images are stored under (see the morph map)
    private const PATIENT_TYPES = ['Patient', 'App\\Models\\Patient'];

    // Details copied from the duplicate when the kept record has none
    private const FILLED_FIELDS = [
        'age', 'birth_date', 'sex', 'address', 'systemic_conditions', 'from_where_come_id',
        'identifier', 'rx_id', 'doctor_id', 'tooth_details', 'credit_balance_add_at',
    ];

    // Free-text fields: both records' text is kept
    private const TEXT_FIELDS = ['notes', 'note'];

    /**
     * What each record has, so staff can see what the merge brings together.
     */
    public function summary(Patient $patient): array
    {
        $bills = Bill::where('patient_id', $patient->id);

        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'phone2' => $patient->phone2,
            'created_at' => $patient->created_at?->format('Y-m-d'),
            'cases' => DB::table('cases')->where('patient_id', $patient->id)->whereNull('deleted_at')->count(),
            'bills' => (clone $bills)->count(),
            'paid' => (float) (clone $bills)->sum('price'),
            'reservations' => DB::table('reservations')->where('patient_id', $patient->id)->whereNull('deleted_at')->count(),
            'images' => $this->images($patient)->count(),
            'credit_balance' => (int) $patient->credit_balance,
        ];
    }

    /**
     * Move everything from $duplicate onto $keep and delete $duplicate.
     */
    public function merge(Patient $keep, Patient $duplicate): Patient
    {
        if ($keep->is($duplicate)) {
            throw new \InvalidArgumentException('A patient cannot be merged with itself');
        }

        $moved = DB::transaction(function () use ($keep, $duplicate) {
            $moved = [];

            foreach (self::PATIENT_TABLES as $table) {
                $moved[$table] = DB::table($table)->where('patient_id', $duplicate->id)->update(['patient_id' => $keep->id]);
            }

            $moved['conversations'] = $this->moveConversations($keep, $duplicate);
            $moved['notes'] = DB::table('notes')
                ->whereIn('noteable_type', self::PATIENT_TYPES)
                ->where('noteable_id', $duplicate->id)
                ->update(['noteable_id' => $keep->id]);
            $moved['images'] = $this->moveImages($keep, $duplicate);

            $this->fillDetails($keep, $duplicate);

            // Everything is on $keep now, so the duplicate's delete cascade finds nothing to delete
            $duplicate->delete();

            return $moved;
        });

        Log::info('Patients merged', [
            'tenant' => tenant('id'),
            'kept_patient_id' => $keep->id,
            'merged_patient_id' => $duplicate->id,
            'merged_patient_name' => $duplicate->name,
            'merged_by' => Auth::id(),
            'moved' => $moved,
        ]);

        return $keep->fresh();
    }

    /**
     * A conversation the kept patient doesn't have (per channel) moves over; for one it
     * does have, the messages join the kept conversation.
     */
    private function moveConversations(Patient $keep, Patient $duplicate): int
    {
        $count = 0;

        foreach (Conversation::where('patient_id', $duplicate->id)->get() as $conversation) {
            $existing = Conversation::where('patient_id', $keep->id)->where('channel', $conversation->channel)->first();

            if ($existing) {
                Message::where('conversation_id', $conversation->id)->update(['conversation_id' => $existing->id]);
                if ($conversation->last_message_at && (!$existing->last_message_at || $conversation->last_message_at->gt($existing->last_message_at))) {
                    $existing->update(['last_message_at' => $conversation->last_message_at]);
                }
                $conversation->delete();
            } else {
                $conversation->update(['patient_id' => $keep->id]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * All images move over. When the kept patient already has a profile photo, the
     * duplicate's becomes an ordinary image ('other'), since the newest profile photo
     * is the one shown.
     */
    private function moveImages(Patient $keep, Patient $duplicate): int
    {
        $images = $this->images($duplicate);

        if ($this->images($keep)->where('type', 'profile')->exists()) {
            (clone $images)->where('type', 'profile')->update(['type' => 'other']);
        }

        return $images->update(['imageable_id' => $keep->id]);
    }

    /**
     * A patient's images under either morph name; $patient->images() only sees one.
     */
    private function images(Patient $patient)
    {
        return Image::query()->whereIn('imageable_type', self::PATIENT_TYPES)->where('imageable_id', $patient->id);
    }

    private function fillDetails(Patient $keep, Patient $duplicate): void
    {
        foreach (self::FILLED_FIELDS as $field) {
            if ($this->isEmpty($keep->{$field}) && !$this->isEmpty($duplicate->{$field})) {
                $keep->{$field} = $duplicate->{$field};
            }
        }

        // The duplicate's numbers stay reachable: as the second number if there is room
        foreach ([$duplicate->phone, $duplicate->phone2] as $phone) {
            if ($this->isEmpty($phone) || in_array($phone, [$keep->phone, $keep->phone2], true)) {
                continue;
            }
            if ($this->isEmpty($keep->phone)) {
                $keep->phone = $phone;
            } elseif ($this->isEmpty($keep->phone2)) {
                $keep->phone2 = $phone;
            }
        }

        foreach (self::TEXT_FIELDS as $field) {
            $text = trim((string) $duplicate->{$field});
            if ($text !== '' && !str_contains((string) $keep->{$field}, $text)) {
                $keep->{$field} = trim(trim((string) $keep->{$field}) . "\n" . $text);
            }
        }

        // Credit the patient paid in advance on either record
        if ($duplicate->credit_balance) {
            $keep->credit_balance = (int) $keep->credit_balance + (int) $duplicate->credit_balance;
        }

        $keep->save();
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === 0;
    }
}
