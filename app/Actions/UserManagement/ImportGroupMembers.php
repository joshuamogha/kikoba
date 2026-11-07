<?php

namespace App\Actions\UserManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportGroupMembers
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    /**
     * The columns of the import file, in order.
     *
     * @var array<int, string>
     */
    public const COLUMNS = ['name', 'phone_number'];

    /**
     * Add the people listed in a CSV file (name, phone_number) to the group as active members.
     * A phone number that belongs to a registered user links to their account. Rows that cannot be added are skipped and reported.
     *
     * @return array{imported: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    public function handle(Group $group, User $assigner, UploadedFile $file): array
    {
        $rows = $this->readCsv($file, self::COLUMNS, maxRows: self::MAX_ROWS);
        $membershipTypeId = $this->defaultMembershipTypeId($group);

        if ($membershipTypeId === null) {
            throw new InvalidArgumentException('This group has no active membership type. Add one in Setting Management first.');
        }

        return DB::transaction(function () use ($group, $assigner, $rows, $membershipTypeId): array {
            Group::query()->whereKey($group->id)->lockForUpdate()->first();

            $imported = 0;
            $skipped = [];

            foreach ($rows as $number => ['name' => $name, 'phone_number' => $rawPhone]) {
                $reason = $this->addMember($group, $assigner, trim($name), $rawPhone, $membershipTypeId);

                if ($reason === null) {
                    $imported++;
                } else {
                    $skipped[] = ['row' => $number, 'name' => trim($name), 'phone' => $rawPhone, 'reason' => $reason];
                }
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * Add one person, returning why they were skipped, or null when they were added.
     */
    private function addMember(Group $group, User $assigner, string $name, string $rawPhone, int $membershipTypeId): ?string
    {
        $phone = $this->cleanPhone($rawPhone);

        if ($name === '') {
            return 'The name is empty.';
        }

        if (preg_match('/^\+?[0-9]{9,15}$/', $phone) !== 1) {
            return 'The phone number is not valid.';
        }

        $user = User::query()->where('phone_number', $phone)->first();

        if ($user !== null && ! $user->canSignIn()) {
            return 'That user account is inactive.';
        }

        $existing = $group->groupMembers()
            ->where(fn ($query) => $query->where('phone_number', $phone)->when($user !== null, fn ($inner) => $inner->orWhere('user_id', $user->id)))
            ->first();

        if ($existing !== null && $existing->membership_status !== 'exited') {
            return 'Already a member of this group.';
        }

        if ($group->maximum_members !== null && $group->groupMembers()->where('membership_status', '!=', 'exited')->count() >= $group->maximum_members) {
            return 'The group has reached its maximum number of members.';
        }

        $attributes = [
            'name' => $name,
            'phone_number' => $phone,
            'membership_type_id' => $membershipTypeId,
            'joined_at' => now()->toDateString(),
            'membership_status' => 'active',
            'approved_at' => now(),
            'approved_by' => $assigner->id,
            'left_at' => null,
            'exit_reason' => null,
        ];

        if ($existing !== null) {
            $existing->update($attributes + ['user_id' => $existing->user_id ?? $user?->id]);
            $member = $existing;
        } else {
            $member = $group->groupMembers()->create([
                ...$attributes,
                'user_id' => $user?->id,
                'member_number' => $group->nextMemberNumber(),
            ]);
        }

        $member->giveMemberRole($assigner);

        return null;
    }

    private function defaultMembershipTypeId(Group $group): ?int
    {
        $types = $group->membershipTypes()->where('is_active', true)->orderBy('display_order')->orderBy('id');

        return ($types->clone()->where('name', 'Ordinary Member')->value('id') ?? $types->value('id')) ?: null;
    }
}
