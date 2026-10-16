<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ImpactMetric;
use App\Models\LogEntry;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\TrainingApplication;
use App\Services\Charts;
use App\Services\Payments\GatewayFactory;
use App\Services\Settings;

/**
 * Landing page. Query budget: about 16 small indexed/aggregate queries, no per-row lookups.
 */
final class DashboardController extends AdminController
{
    /** Status colours for the donut; the legend and table repeat label and count. */
    private const STATUS_COLORS = [
        'new' => '#b7791f',
        'under_review' => '#1f5fa8',
        'shortlisted' => '#0f766e',
        'accepted' => '#1e7b45',
        'rejected' => '#b42318',
        'withdrawn' => '#6b7280',
    ];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** action prefix => [icon, css modifier, sentence template] */
    private const ACTIVITY = [
        'application.status_changed' => ['file-earmark-text', 'blue', 'changed an application status'],
        'application.deleted' => ['trash', 'red', 'deleted an application'],
        'application.' => ['file-earmark-text', 'blue', 'worked on an application'],
        'submission.deleted' => ['trash', 'red', 'deleted a submission'],
        'submission.' => ['inboxes', 'blue', 'handled a submission'],
        'program.created' => ['mortarboard', 'maroon', 'added a program'],
        'program.deleted' => ['trash', 'red', 'deleted a program'],
        'program.' => ['mortarboard', 'maroon', 'updated a program'],
        'news.published' => ['journal-text', 'green', 'published a news article'],
        'news.' => ['journal-text', 'green', 'updated a news article'],
        'gallery.' => ['images', 'amber', 'updated the gallery'],
        'impact.' => ['bar-chart', 'amber', 'updated impact figures'],
        'settings.' => ['gear', 'slate', 'changed website settings'],
        'admin.' => ['people', 'slate', 'changed an administrator account'],
        'account.' => ['person-badge', 'slate', 'updated their account'],
    ];

    public function index(Request $request): Response
    {
        $user = Auth::user() ?? [];
        $year = (int) gmdate('Y');
        $canSubmissions = Auth::can('submissions.manage');
        $canContent = Auth::can('content.manage');

        $headline = ImpactMetric::headline();
        $monthly = $canSubmissions ? TrainingApplication::monthlyCounts($year) : array_fill(1, 12, 0);
        $statusCounts = $canSubmissions ? TrainingApplication::countByStatus() : [];

        $tiles = [];
        if ($headline !== null) {
            $tiles[] = ['label' => 'Young People Reached', 'value' => (string) $headline['value'],
                'caption' => (string) $headline['label'] . ((int) $headline['is_approximate'] === 1 ? ' (approximate)' : ''), 'icon' => 'people-fill', 'variant' => 'maroon', 'href' => $canContent ? '/admin/impact' : null];
        }
        $tiles[] = ['label' => 'Programs', 'value' => Program::countByStatus('published'), 'caption' => 'Published programs', 'icon' => 'mortarboard-fill', 'variant' => 'slate', 'href' => $canContent ? '/admin/programs' : null];
        if ($canSubmissions) {
            $tiles[] = ['label' => 'Applications', 'value' => array_sum($monthly), 'caption' => 'Received in ' . $year, 'icon' => 'file-earmark-text', 'variant' => 'green', 'href' => '/admin/applications'];
        }
        $tiles[] = ['label' => 'News & Stories', 'value' => NewsArticle::countPublished(), 'caption' => 'Published', 'icon' => 'newspaper', 'variant' => 'amber', 'href' => $canContent ? '/admin/news' : null];

        $slices = [];
        $total = 0;
        foreach ($statusCounts as $status => $n) {
            $slices[] = ['status' => $status, 'label' => label($status), 'value' => $n, 'color' => self::STATUS_COLORS[$status] ?? '#6b7280'];
            $total += $n;
        }
        $donut = $total > 0
            ? Charts::donut($slices, 'Application status: ' . implode(', ', array_map(static fn (array $s): string => $s['label'] . ' ' . $s['value'], $slices)), 'applications')
            : '';
        $monthValues = array_values($monthly);
        $bars = Charts::bars(self::MONTHS, $monthValues, 'Applications received per month in ' . $year . ': '
            . implode(', ', array_map(static fn (string $m, int $n): string => $m . ' ' . $n, self::MONTHS, $monthValues)), '#9d1d2a', array_map(static fn (string $m): string => $m[0], self::MONTHS));

        return $this->page('admin/dashboard', 'Dashboard', 'dashboard', [
            'firstName' => explode(' ', trim((string) ($user['name'] ?? '')))[0] ?: 'there',
            'today' => format_date(gmdate('Y-m-d H:i:s'), 'l, j F Y'),
            'tiles' => $tiles,
            'year' => $year,
            'canSubmissions' => $canSubmissions,
            'canContent' => $canContent,
            'recentApplications' => $canSubmissions ? TrainingApplication::recent(5) : [],
            'programs' => $canSubmissions || $canContent ? $this->programOverview(5) : [],
            'slices' => $slices,
            'total' => $total,
            'donut' => $donut,
            'bars' => $bars,
            'months' => self::MONTHS,
            'monthValues' => $monthValues,
            'activity' => $this->activity(LogEntry::recentAudit(6, Auth::can('submissions.manage'), Auth::can('admins.manage'))),
            'checklist' => Auth::can('settings.system') ? $this->checklist() : null,
        ]);
    }

    /**
     * Programs with their application counts (adds the slug that
     * TrainingApplication::countsByProgram does not select, for the public link).
     *
     * @return list<array<string, mixed>>
     */
    private function programOverview(int $limit): array
    {
        return Database::connection()->fetchAll(
            "SELECT p.id, p.title, p.slug, p.status, c.name AS category_name, COUNT(a.id) AS application_count
             FROM programs p
             LEFT JOIN program_categories c ON c.id = p.category_id
             LEFT JOIN training_applications a ON a.program_id = p.id
             WHERE p.status <> 'archived'
             GROUP BY p.id, p.title, p.slug, p.status, c.name, p.sort_order
             ORDER BY application_count DESC, p.sort_order LIMIT :lim",
            ['lim' => $limit]
        );
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array{icon: string, tone: string, text: string, summary: string, when: string}>
     */
    private function activity(array $entries): array
    {
        $out = [];
        foreach ($entries as $e) {
            $action = (string) $e['action'];
            $meta = ['circle', 'slate', 'did something'];
            foreach (self::ACTIVITY as $prefix => $m) {
                if ($action === $prefix || (str_ends_with($prefix, '.') && str_starts_with($action, $prefix))) {
                    $meta = $m;
                    break;
                }
            }
            $out[] = [
                'icon' => $meta[0] === 'circle' ? 'clock-history' : $meta[0],
                'tone' => $meta[1],
                'text' => ((string) ($e['admin_name'] ?? '') !== '' ? (string) $e['admin_name'] : 'Someone') . ' ' . $meta[2],
                'summary' => (string) $e['summary'],
                'when' => self::ago((string) $e['created_at']),
            ];
        }
        return $out;
    }

    /** "2 minutes ago" from a UTC DATETIME string. */
    public static function ago(string $utc): string
    {
        $then = strtotime($utc . ' UTC');
        if ($then === false) {
            return '';
        }
        $diff = max(0, time() - $then);
        return match (true) {
            $diff < 60 => 'just now',
            $diff < 3600 => self::plural((int) ($diff / 60), 'minute'),
            $diff < 86400 => self::plural((int) ($diff / 3600), 'hour'),
            $diff < 86400 * 30 => self::plural((int) ($diff / 86400), 'day'),
            default => format_date(gmdate('Y-m-d H:i:s', $then), 'j M Y'),
        };
    }

    private static function plural(int $n, string $unit): string
    {
        return $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' ago';
    }

    /**
     * What is still missing before the site is fully ready (super administrators only).
     *
     * @return list<array{text: string, href: ?string, level: string}>
     */
    private function checklist(): array
    {
        $items = [];
        if (config('app.setup_token') !== null) {
            $items[] = ['text' => 'SETUP_TOKEN is still set in your .env file. Remove it now.', 'href' => null, 'level' => 'warn'];
        }
        $facts = Settings::missingVerifiedFacts();
        if ($facts !== []) {
            $items[] = ['text' => count($facts) . ' fact' . (count($facts) === 1 ? '' : 's') . ' to confirm and fill in: ' . implode(', ', array_slice($facts, 0, 3)) . (count($facts) > 3 ? '…' : ''), 'href' => '/admin/settings', 'level' => 'todo'];
        }
        if (!Settings::bool('privacy.reviewed')) {
            $items[] = ['text' => 'The privacy notice has not been reviewed and approved yet.', 'href' => '/admin/settings', 'level' => 'todo'];
        }
        if (!Settings::bool('terms.reviewed')) {
            $items[] = ['text' => 'The terms have not been reviewed and approved yet.', 'href' => '/admin/settings', 'level' => 'todo'];
        }
        if (config('mail.driver') === 'none') {
            $items[] = ['text' => 'Email is not configured, so no notifications or password resets can be sent.', 'href' => '/admin/email-log', 'level' => 'todo'];
        } elseif (trim(Settings::get('notify.email')) === '' && trim((string) config('mail.admin_address', '')) === '') {
            $items[] = ['text' => 'No staff notification email address is set.', 'href' => '/admin/settings', 'level' => 'todo'];
        }
        if (Settings::get('site.logo_path') === '') {
            $items[] = ['text' => 'No logo has been uploaded.', 'href' => '/admin/settings', 'level' => 'todo'];
        }
        if (!GatewayFactory::make()->isConfigured()) {
            $items[] = ['text' => 'Online donations are not configured.', 'href' => '/admin/donations', 'level' => 'todo'];
        }
        $failed = LogEntry::emailStatusCounts()['failed'];
        if ($failed > 0) {
            $items[] = ['text' => $failed . ' email' . ($failed === 1 ? '' : 's') . ' failed to send.', 'href' => '/admin/email-log?status=failed', 'level' => 'warn'];
        }
        return $items;
    }
}
