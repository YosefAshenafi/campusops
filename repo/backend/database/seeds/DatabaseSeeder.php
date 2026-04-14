<?php

use think\migration\Seeder;
use think\facade\Db;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedUsers();
        $this->seedViolationRules();
        $this->seedActivities();
        $this->seedOrders();
    }

    private function seedRoles(): void
    {
        $roles = [
            [
                'name' => 'administrator',
                'description' => 'Full system access, user management, export controls, refund approvals',
                'permissions' => json_encode([
                    'users.*', 'activities.*', 'orders.*', 'violations.*',
                    'search.*', 'reports.*', 'audit.*', 'settings.*',
                    'shipments.*', 'tasks.*', 'staffing.*', 'dashboard.*',
                    'uploads.*', 'files.*', 'index.*', 'notifications.*', 'preferences.*',
                    'checklists.*', 'orders.refund', 'orders.approve',
                    'users.role', 'users.password'
                ]),
            ],
            [
                'name' => 'operations_staff',
                'description' => 'Order management, activity creation, fulfillment tracking',
                'permissions' => json_encode([
                    'activities.read', 'activities.create', 'activities.update',
                    'activities.publish', 'activities.transition', 'activities.signup',
                    'orders.*', 'shipments.*', 'search.read',
                    'dashboard.read', 'notifications.read', 'preferences.*',
                    'uploads.create', 'files.read'
                ]),
            ],
            [
                'name' => 'team_lead',
                'description' => 'Task breakdowns, staffing, checklists',
                'permissions' => json_encode([
                    'activities.read', 'activities.signup', 'tasks.*', 'staffing.*',
                    'checklists.*',
                    'dashboard.read', 'notifications.read', 'preferences.*',
                    'search.read'
                ]),
            ],
            [
                'name' => 'reviewer',
                'description' => 'Approval workflows, violation appeals',
                'permissions' => json_encode([
                    'violations.read', 'violations.review', 'violations.final_decision',
                    'orders.read', 'orders.approve', 'audit.read',
                    'dashboard.read', 'notifications.read', 'preferences.*',
                    'search.read'
                ]),
            ],
            [
                'name' => 'regular_user',
                'description' => 'Browse activities, sign up, view dashboard',
                'permissions' => json_encode([
                    'activities.read', 'activities.signup',
                    'orders.read', 'notifications.read', 'preferences.*',
                    'dashboard.read', 'search.read',
                    'violations.read', 'violations.appeal'
                ]),
            ],
        ];

        foreach ($roles as $role) {
            $this->upsertBy('roles', ['name' => $role['name']], $role);
        }
    }

    private function seedUsers(): void
    {
        // All test users use password: "CampusOps1" (meets 10-char minimum)
        $testPassword = 'CampusOps1';

        $userDefs = [
            ['username' => 'admin', 'role' => 'administrator'],
            ['username' => 'ops_staff1', 'role' => 'operations_staff'],
            ['username' => 'ops_staff2', 'role' => 'operations_staff'],
            ['username' => 'team_lead', 'role' => 'team_lead'],
            ['username' => 'reviewer', 'role' => 'reviewer'],
            ['username' => 'user1', 'role' => 'regular_user'],
            ['username' => 'user2', 'role' => 'regular_user'],
            ['username' => 'user3', 'role' => 'regular_user'],
            ['username' => 'user4', 'role' => 'regular_user'],
            ['username' => 'user5', 'role' => 'regular_user'],
        ];

        $userIds = [];
        foreach ($userDefs as $def) {
            $salt = bin2hex(random_bytes(16));
            $userData = [
                'username' => $def['username'],
                'password_hash' => password_hash($testPassword . $salt, PASSWORD_BCRYPT),
                'salt' => $salt,
                'role' => $def['role'],
                'status' => 'active',
                'failed_attempts' => 0,
            ];

            $userIds[] = $this->upsertBy('users', ['username' => $def['username']], $userData);
        }

        // Create user preferences for all users
        $prefs = [];
        foreach ($userIds as $userId) {
            $prefs[] = [
                'user_id' => $userId,
                'arrival_reminders' => 1,
                'activity_alerts' => 1,
                'order_alerts' => 1,
            ];
        }

        foreach ($prefs as $pref) {
            $this->upsertBy('user_preferences', ['user_id' => $pref['user_id']], $pref);
        }
    }

    private function seedViolationRules(): void
    {
        $rules = [
            [
                'name' => 'On-time Task Completion',
                'description' => 'Reward for completing assigned tasks before the deadline',
                'points' => 5,
                'category' => 'reward',
                'created_by' => 1,
            ],
            [
                'name' => 'Outstanding Performance',
                'description' => 'Exceptional contribution to campus event execution',
                'points' => 10,
                'category' => 'reward',
                'created_by' => 1,
            ],
            [
                'name' => 'Missed Shift',
                'description' => 'Failed to show up for assigned shift without notice',
                'points' => -10,
                'category' => 'attendance',
                'created_by' => 1,
            ],
            [
                'name' => 'Late Arrival',
                'description' => 'Arrived more than 15 minutes late to assigned shift',
                'points' => -5,
                'category' => 'attendance',
                'created_by' => 1,
            ],
            [
                'name' => 'Equipment Damage',
                'description' => 'Negligent handling resulting in equipment damage',
                'points' => -15,
                'category' => 'conduct',
                'created_by' => 1,
            ],
        ];

        foreach ($rules as $rule) {
            $this->upsertBy('violation_rules', ['name' => $rule['name']], $rule);
        }
    }

    private function seedActivities(): void
    {
        // Keep sample activity graph stable; only insert once.
        if (Db::name('activity_groups')->count() > 0) {
            return;
        }

        // Create 3 activity groups
        $groups = $this->table('activity_groups');
        $groups->insert([
            ['created_by' => 2], // ops_staff1
            ['created_by' => 2],
            ['created_by' => 3], // ops_staff2
        ])->saveData();

        // Create activity versions in various states
        $versions = $this->table('activity_versions');
        $versions->insert([
            [
                'group_id' => 1,
                'version_number' => 1,
                'title' => 'Spring Campus Cleanup Day',
                'body' => 'Annual campus-wide cleanup event. Volunteers will be assigned areas to clean, landscape, and beautify.',
                'tags' => json_encode(['volunteer', 'outdoor', 'campus']),
                'state' => 'draft',
                'max_headcount' => 100,
                'signup_start' => '2026-05-01 08:00:00',
                'signup_end' => '2026-05-10 23:59:59',
                'eligibility_tags' => json_encode(['all_students']),
                'required_supplies' => json_encode(['gloves', 'trash bags', 'sunscreen']),
            ],
            [
                'group_id' => 2,
                'version_number' => 1,
                'title' => 'Tech Innovation Fair 2026',
                'body' => 'Showcase of student technology projects. Teams present demos to judges and visitors.',
                'tags' => json_encode(['technology', 'competition', 'indoor']),
                'state' => 'published',
                'max_headcount' => 50,
                'signup_start' => '2026-04-15 00:00:00',
                'signup_end' => '2026-04-30 23:59:59',
                'eligibility_tags' => json_encode(['engineering', 'cs_students']),
                'required_supplies' => json_encode(['laptop', 'poster board']),
                'published_at' => '2026-04-10 10:00:00',
            ],
            [
                'group_id' => 3,
                'version_number' => 1,
                'title' => 'Winter Charity Gala',
                'body' => 'Formal charity event raising funds for local community organizations. Includes dinner and auction.',
                'tags' => json_encode(['charity', 'formal', 'indoor']),
                'state' => 'completed',
                'max_headcount' => 200,
                'signup_start' => '2026-01-01 00:00:00',
                'signup_end' => '2026-01-20 23:59:59',
                'eligibility_tags' => json_encode(['all_students', 'faculty']),
                'required_supplies' => json_encode(['formal attire']),
                'published_at' => '2025-12-15 09:00:00',
            ],
        ])->saveData();

        // Add some signups
        $signups = $this->table('activity_signups');
        $signups->insert([
            ['group_id' => 2, 'user_id' => 6, 'status' => 'active'],
            ['group_id' => 2, 'user_id' => 7, 'status' => 'active'],
            ['group_id' => 3, 'user_id' => 6, 'status' => 'active'],
            ['group_id' => 3, 'user_id' => 8, 'status' => 'active'],
            ['group_id' => 3, 'user_id' => 9, 'status' => 'active'],
        ])->saveData();
    }

    private function seedOrders(): void
    {
        // Keep sample order graph stable; only insert once.
        if (Db::name('orders')->count() > 0) {
            return;
        }

        $orders = $this->table('orders');
        $orders->insert([
            [
                'activity_id' => 2,
                'created_by' => 2,
                'team_lead_id' => 4,
                'state' => 'placed',
                'items' => json_encode([
                    ['type' => 'supply', 'description' => 'Poster boards (A1)', 'quantity' => 25],
                    ['type' => 'supply', 'description' => 'Markers set', 'quantity' => 10],
                ]),
                'notes' => 'For Tech Innovation Fair booths',
            ],
            [
                'activity_id' => 2,
                'created_by' => 2,
                'team_lead_id' => 4,
                'state' => 'paid',
                'items' => json_encode([
                    ['type' => 'equipment', 'description' => 'Projector rental', 'quantity' => 3],
                ]),
                'notes' => 'Projectors for demo presentations',
                'payment_method' => 'campus_credit',
                'amount' => 450.00,
            ],
            [
                'activity_id' => 3,
                'created_by' => 3,
                'team_lead_id' => 4,
                'state' => 'closed',
                'items' => json_encode([
                    ['type' => 'catering', 'description' => 'Dinner service (200 pax)', 'quantity' => 1],
                    ['type' => 'decoration', 'description' => 'Floral centerpieces', 'quantity' => 20],
                ]),
                'notes' => 'Winter Gala catering and decor',
                'payment_method' => 'bank_transfer',
                'amount' => 8500.00,
                'ticket_number' => 'TKT-2026-0001',
                'closed_at' => '2026-02-01 10:00:00',
            ],
            [
                'activity_id' => 3,
                'created_by' => 3,
                'team_lead_id' => 4,
                'state' => 'canceled',
                'items' => json_encode([
                    ['type' => 'supply', 'description' => 'Extra chairs', 'quantity' => 50],
                ]),
                'notes' => 'Canceled - venue provided chairs',
            ],
            [
                'activity_id' => 1,
                'created_by' => 2,
                'team_lead_id' => 4,
                'state' => 'pending_payment',
                'items' => json_encode([
                    ['type' => 'supply', 'description' => 'Trash bags (industrial)', 'quantity' => 200],
                    ['type' => 'supply', 'description' => 'Work gloves', 'quantity' => 100],
                ]),
                'notes' => 'Cleanup day supplies',
                'auto_cancel_at' => '2026-04-15 11:00:00',
            ],
        ])->saveData();

        // Add state history for the closed order
        $history = $this->table('order_state_history');
        $history->insert([
            ['order_id' => 3, 'from_state' => null, 'to_state' => 'placed', 'changed_by' => 3],
            ['order_id' => 3, 'from_state' => 'placed', 'to_state' => 'pending_payment', 'changed_by' => 3],
            ['order_id' => 3, 'from_state' => 'pending_payment', 'to_state' => 'paid', 'changed_by' => 2],
            ['order_id' => 3, 'from_state' => 'paid', 'to_state' => 'ticketing', 'changed_by' => 2],
            ['order_id' => 3, 'from_state' => 'ticketing', 'to_state' => 'ticketed', 'changed_by' => 2],
            ['order_id' => 3, 'from_state' => 'ticketed', 'to_state' => 'closed', 'changed_by' => 2],
        ])->saveData();
    }

    private function upsertBy(string $table, array $where, array $data): int
    {
        $existing = Db::name($table)->where($where)->find();
        if ($existing) {
            Db::name($table)->where('id', $existing['id'])->update($data);
            return (int) $existing['id'];
        }

        return (int) Db::name($table)->insertGetId($data);
    }
}
