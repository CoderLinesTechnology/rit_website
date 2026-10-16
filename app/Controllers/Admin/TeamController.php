<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Milestone;
use App\Models\TeamMember;

final class TeamController extends AdminController
{
    use ContentSupport;

    private const PHOTO = ['path' => 'photo_path', 'thumb' => 'photo_thumb_path', 'alt' => null];

    public function index(Request $request): Response
    {
        return $this->page('admin/team/index', 'Team & milestones', 'team', [
            'members' => TeamMember::all(),
            'milestones' => Milestone::all(),
        ]);
    }

    // ── Team members ──────────────────────────────────────────────────────

    public function createMember(Request $request): Response
    {
        return $this->memberForm(null, $this->values(['sort_order' => '0', 'is_published' => '0', 'consent_confirmed' => '0'], ['is_published', 'consent_confirmed']));
    }

    public function storeMember(Request $request): Response
    {
        $v = $this->validateMember($request);
        if ($v->fails()) {
            return $this->invalid(url('/admin/team/members/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, null, self::PHOTO, 'team', null);
        if ($v->fails()) {
            return $this->invalid(url('/admin/team/members/create'), $v->errors(), $request->post());
        }
        try {
            $id = TeamMember::create($this->memberRow($d) + $plan['data']);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->audit('team.member_create', 'team_member', $id, 'Added a team member (' . ($d['is_published'] ? 'published' : 'unpublished') . ')');
        flash_message('success', 'Team member saved.');
        return redirect(url('/admin/team'));
    }

    public function editMember(Request $request, string $id): Response
    {
        $member = $this->findOr404(TeamMember::find((int) $id));
        return $this->memberForm($member, $this->values($member, ['is_published', 'consent_confirmed']));
    }

    public function updateMember(Request $request, string $id): Response
    {
        $member = $this->findOr404(TeamMember::find((int) $id));
        $mid = (int) $member['id'];
        $back = url('/admin/team/members/' . $mid . '/edit');
        $v = $this->validateMember($request);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        $d = $v->validated();
        $plan = $this->planImage($request, $v, $member, self::PHOTO, 'team', null);
        if ($v->fails()) {
            return $this->invalid($back, $v->errors(), $request->post());
        }
        // Unpublishing or withdrawing consent moves an unchanged photo to a new random name.
        $withdrawn = ((int) $member['is_published'] === 1 && !$d['is_published'])
            || ((int) $member['consent_confirmed'] === 1 && !$d['consent_confirmed']);
        if ($withdrawn && !array_key_exists('photo_path', $plan['data']) && (string) $member['photo_path'] !== '') {
            $renamed = (new \App\Services\ImageUploader())->rename((string) $member['photo_path'], (string) $member['photo_thumb_path']);
            if ($renamed !== null) {
                $plan['data']['photo_path'] = $renamed['path'];
                $plan['data']['photo_thumb_path'] = $renamed['thumb'];
            }
        }
        try {
            TeamMember::update($mid, $this->memberRow($d) + $plan['data']);
        } catch (\Throwable $e) {
            $this->discardStored($plan['stored']);
            throw $e;
        }
        $this->discardImages($plan['discard']);
        $this->audit('team.member_update', 'team_member', $mid, 'Updated a team member (' . ($d['is_published'] ? 'published' : 'unpublished') . ')');
        flash_message('success', 'Team member saved.');
        return redirect(url('/admin/team'));
    }

    public function destroyMember(Request $request, string $id): Response
    {
        $member = $this->findOr404(TeamMember::find((int) $id));
        TeamMember::delete((int) $member['id']);
        $this->discardImages([[(string) $member['photo_path'], (string) $member['photo_thumb_path']]]);
        $this->audit('team.member_delete', 'team_member', (int) $member['id'], 'Deleted a team member');
        flash_message('success', 'Team member deleted.');
        return redirect(url('/admin/team'));
    }

    // ── Milestones ────────────────────────────────────────────────────────

    public function createMilestone(Request $request): Response
    {
        return $this->milestoneForm(null, $this->values(['sort_order' => '0', 'is_verified' => '0', 'is_published' => '0'], ['is_verified', 'is_published']));
    }

    public function storeMilestone(Request $request): Response
    {
        $v = $this->validateMilestone($request);
        if ($v->fails()) {
            return $this->invalid(url('/admin/team/milestones/create'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        $id = Milestone::create($this->milestoneRow($d));
        $this->audit('team.milestone_create', 'milestone', $id, 'Added milestone "' . $d['title'] . '"');
        flash_message('success', 'Milestone saved.');
        return redirect(url('/admin/team#milestones'));
    }

    public function editMilestone(Request $request, string $id): Response
    {
        $milestone = $this->findOr404(Milestone::find((int) $id));
        return $this->milestoneForm($milestone, $this->values($milestone, ['is_verified', 'is_published']));
    }

    public function updateMilestone(Request $request, string $id): Response
    {
        $milestone = $this->findOr404(Milestone::find((int) $id));
        $mid = (int) $milestone['id'];
        $v = $this->validateMilestone($request);
        if ($v->fails()) {
            return $this->invalid(url('/admin/team/milestones/' . $mid . '/edit'), $v->errors(), $request->post());
        }
        $d = $v->validated();
        Milestone::update($mid, $this->milestoneRow($d));
        $this->audit('team.milestone_update', 'milestone', $mid, 'Updated milestone "' . $d['title'] . '"');
        flash_message('success', 'Milestone saved.');
        return redirect(url('/admin/team#milestones'));
    }

    public function destroyMilestone(Request $request, string $id): Response
    {
        $milestone = $this->findOr404(Milestone::find((int) $id));
        Milestone::delete((int) $milestone['id']);
        $this->audit('team.milestone_delete', 'milestone', (int) $milestone['id'], 'Deleted milestone "' . $milestone['title'] . '"');
        flash_message('success', 'Milestone deleted.');
        return redirect(url('/admin/team#milestones'));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** @param array<string, mixed>|null $member @param array<string, string> $values */
    private function memberForm(?array $member, array $values): Response
    {
        return $this->page('admin/team/member-form', $member === null ? 'New team member' : 'Edit team member', 'team', [
            'member' => $member,
            'v' => $values,
            'action' => $member === null ? '/admin/team/members' : '/admin/team/members/' . (int) $member['id'],
            'maxMb' => $this->maxMb(),
        ]);
    }

    /** @param array<string, mixed>|null $milestone @param array<string, string> $values */
    private function milestoneForm(?array $milestone, array $values): Response
    {
        return $this->page('admin/team/milestone-form', $milestone === null ? 'New milestone' : 'Edit milestone', 'team', [
            'milestone' => $milestone,
            'v' => $values,
            'action' => $milestone === null ? '/admin/team/milestones' : '/admin/team/milestones/' . (int) $milestone['id'],
        ]);
    }

    private function validateMember(Request $request): Validator
    {
        $v = Validator::make($request->post(), [
            'full_name' => 'required|line|max:150',
            'role_title' => 'required|line|max:150',
            'bio' => 'nullable|string|max:3000',
            'sort_order' => 'nullable|int|min:0|max:9999',
            'consent_confirmed' => 'boolean',
            'is_published' => 'boolean',
        ], ['full_name' => 'Full name', 'role_title' => 'Role or title']);
        $d = $v->validated();
        if ($v->passes() && $d['is_published'] && !$d['consent_confirmed']) {
            $v->addError('consent_confirmed', 'Confirm that this person approved the details and photo shown before publishing them.');
        }
        return $v;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function memberRow(array $d): array
    {
        return [
            'full_name' => $d['full_name'],
            'role_title' => $d['role_title'],
            'bio' => $d['bio'] ?? null,
            'sort_order' => (int) ($d['sort_order'] ?? 0),
            'consent_confirmed' => $this->bit($d['consent_confirmed']),
            'is_published' => $this->bit($d['is_published']),
        ];
    }

    private function validateMilestone(Request $request): Validator
    {
        $v = Validator::make($request->post(), [
            'period_label' => 'required|line|max:40',
            'title' => 'required|line|max:190',
            'description' => 'nullable|string|max:2000',
            'is_verified' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'nullable|int|min:0|max:9999',
        ], ['period_label' => 'Date or period']);
        $d = $v->validated();
        if ($v->passes() && $d['is_published'] && !$d['is_verified']) {
            $v->addError('is_verified', 'Confirm that this milestone has been verified before publishing it.');
        }
        return $v;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function milestoneRow(array $d): array
    {
        return [
            'period_label' => $d['period_label'],
            'title' => $d['title'],
            'description' => $d['description'] ?? null,
            'is_verified' => $this->bit($d['is_verified']),
            'is_published' => $this->bit($d['is_published']),
            'sort_order' => (int) ($d['sort_order'] ?? 0),
        ];
    }
}
