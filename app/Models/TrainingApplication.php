<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Request;

final class TrainingApplication extends Submission
{
    public const TABLE = 'training_applications';
    public const REFERENCE_PREFIX = 'RIT-APP';
    public const STATUSES = ['new', 'under_review', 'shortlisted', 'accepted', 'rejected', 'withdrawn'];
    public const FILLABLE = [
        'reference', 'program_id', 'program_title', 'full_name', 'email', 'phone', 'age_range', 'is_adult',
        'guardian_name', 'guardian_phone', 'guardian_consent', 'location', 'background', 'reason', 'availability',
        'attachment_path', 'attachment_name', 'attachment_size', 'attachment_mime', 'privacy_accepted_at', 'idempotency_key',
    ];
    public const SEARCH_COLUMNS = ['full_name', 'email', 'phone'];

    /** Age ranges offered on the form => whether that range is 18 or over. */
    public const AGE_RANGES = [
        'under-15' => ['label' => 'Under 15', 'adult' => false],
        '15-17' => ['label' => '15–17', 'adult' => false],
        '18-24' => ['label' => '18–24', 'adult' => true],
        '25-35' => ['label' => '25–35', 'adult' => true],
        '36-plus' => ['label' => '36 and over', 'adult' => true],
    ];

    public const REGIONS = [
        'Ahafo', 'Ashanti', 'Bono', 'Bono East', 'Central', 'Eastern', 'Greater Accra', 'North East', 'Northern',
        'Oti', 'Savannah', 'Upper East', 'Upper West', 'Volta', 'Western', 'Western North', 'Outside Ghana',
    ];

    public const AVAILABILITY = [
        'weekdays' => 'Weekdays',
        'weekends' => 'Weekends',
        'evenings' => 'Weekday evenings',
        'flexible' => 'Flexible / any time',
    ];

    /**
     * Admin listing; adds a "program" (id) filter and a possible-duplicate flag
     * (another application from the same email for the same program).
     *
     * @return array{items: list<array<string, mixed>>, paginator: \App\Core\Paginator}
     */
    public static function adminPaginate(Request $request, int $perPage = 25, string $select = '', string $from = '', array $extra = []): array
    {
        $program = $request->queryInt('program');
        $extra = $program > 0 ? ['where' => 's.program_id = :program', 'params' => ['program' => $program]] : [];
        // "Possible duplicates" of one application, by id, so no email address appears in the URL.
        $duplicatesOf = $request->queryInt('duplicates_of');
        if ($duplicatesOf > 0) {
            $where = 's.email = (SELECT d0.email FROM training_applications d0 WHERE d0.id = :dup1)'
                . ' AND s.program_id <=> (SELECT d1.program_id FROM training_applications d1 WHERE d1.id = :dup2)';
            $extra = [
                'where' => ($extra['where'] ?? '') !== '' ? $extra['where'] . ' AND ' . $where : $where,
                'params' => ($extra['params'] ?? []) + ['dup1' => $duplicatesOf, 'dup2' => $duplicatesOf],
            ];
        }
        return parent::adminPaginate(
            $request,
            $perPage,
            's.id, s.reference, s.full_name, s.email, s.program_id, s.program_title, s.age_range, s.is_adult, s.status, s.created_at, s.attachment_path IS NOT NULL AS has_attachment,
             (SELECT COUNT(*) FROM training_applications d WHERE d.email = s.email AND d.program_id <=> s.program_id AND d.id <> s.id) AS duplicate_count',
            '',
            $extra
        );
    }

    /** @return list<array<string, mixed>> Applications per program for the dashboard overview. */
    public static function countsByProgram(int $limit = 6): array
    {
        return static::db()->fetchAll(
            "SELECT p.id, p.title, p.status, c.name AS category_name, COUNT(a.id) AS application_count
             FROM programs p
             LEFT JOIN program_categories c ON c.id = p.category_id
             LEFT JOIN training_applications a ON a.program_id = p.id
             WHERE p.status <> 'archived'
             GROUP BY p.id ORDER BY application_count DESC, p.sort_order LIMIT :lim",
            ['lim' => $limit]
        );
    }

    public static function duplicateCount(int $id, string $email, ?int $programId): int
    {
        return (int) static::db()->fetchValue(
            'SELECT COUNT(*) FROM training_applications WHERE email = :e AND program_id <=> :p AND id <> :id',
            ['e' => $email, 'p' => $programId, 'id' => $id]
        );
    }
}
