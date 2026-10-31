<?php

namespace Database\Seeders;

use App\Enums\PostType;
use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyPlan;
use App\Models\MonthlyReport;
use App\Models\Post;
use App\Models\ReportReview;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use App\Services\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * Fictional demo data for showing the planning and reporting screens.
 *
 * Every person, office figure and narrative here is invented. Never run this against production:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Demo users all share the password "demo-password".
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'demo-password';

    public function run(): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('DemoSeeder contains fictional data and must not run in production.');
        }

        $this->call(DatabaseSeeder::class);

        $today = EthiopianCalendar::today();
        $year = $today['year'];
        $currentMonth = $today['month'];

        $offices = $this->offices();
        $users = $this->users($offices);
        $targets = $this->targets($offices, $year);
        $this->posts();

        // Reports for every completed month and week so far this year.
        foreach ($targets as $code => $officeTargets) {
            $office = $offices[$code];
            $preparer = $users[$code] ?? $users['SERV'];

            for ($month = 1; $month <= $currentMonth; $month++) {
                $weeks = $month < $currentMonth
                    ? EthiopianCalendar::weeksInMonth($year, $month)
                    : max(0, $today['week'] - 1);

                for ($week = 1; $week <= $weeks; $week++) {
                    $this->weeklyReport($office, $preparer, $users['LEAD'], $officeTargets, $year, $month, $week, $code);
                }

                if ($month < $currentMonth) {
                    $this->monthlyReport($office, $preparer, $users['LEAD'], $officeTargets, $year, $month, $code);
                }
            }
        }
    }

    /**
     * @return array<string, Department>
     */
    private function offices(): array
    {
        $lead = Department::where('code', 'LEAD')->firstOrFail();

        $extra = [
            ['name' => 'Water and sewerage', 'code' => 'WAT', 'parent' => 'SERV'],
            ['name' => 'Revenue office', 'code' => 'REV', 'parent' => 'FIN'],
            ['name' => 'Urban development', 'code' => 'URB', 'parent' => 'LEAD'],
            ['name' => 'Trade and investment', 'code' => 'TRD', 'parent' => 'LEAD'],
        ];

        foreach ($extra as $office) {
            Department::firstOrCreate(
                ['code' => $office['code']],
                ['name' => $office['name'], 'parent_id' => Department::where('code', $office['parent'])->value('id') ?? $lead->id],
            );
        }

        return Department::all()->keyBy('code')->all();
    }

    /**
     * @param  array<string, Department>  $offices
     * @return array<string, User>
     */
    private function users(array $offices): array
    {
        $people = [
            'LEAD' => ['Demo Mayor Office', 'leadership@demo.test', 'town_leadership'],
            'SERV' => ['Demo Services Supervisor', 'services@demo.test', 'supervisor'],
            'SAN' => ['Demo Sanitation Manager', 'sanitation@demo.test', 'department_manager'],
            'WAT' => ['Demo Water Manager', 'water@demo.test', 'department_manager'],
            'FIN' => ['Demo Finance Supervisor', 'finance@demo.test', 'supervisor'],
            'REV' => ['Demo Revenue Officer', 'revenue@demo.test', 'department_manager'],
            'URB' => ['Demo Urban Planner', 'urban@demo.test', 'department_manager'],
            'TRD' => ['Demo Trade Officer', 'trade@demo.test', 'department_manager'],
        ];

        $users = [];

        foreach ($people as $code => [$name, $email, $role]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD]);
            $user->roles()->syncWithoutDetaching([Role::where('name', $role)->value('id')]);
            $user->departments()->syncWithoutDetaching([$offices[$code]->id => ['is_primary' => true]]);
            $users[$code] = $user;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Demo Administrator', 'password' => self::PASSWORD],
        );
        $admin->roles()->syncWithoutDetaching([Role::where('name', 'system_admin')->value('id')]);
        $admin->departments()->syncWithoutDetaching([$offices['LEAD']->id => ['is_primary' => true]]);

        return $users;
    }

    /**
     * @param  array<string, Department>  $offices
     * @return array<string, list<AnnualTarget>>
     */
    private function targets(array $offices, int $year): array
    {
        $definitions = [
            'SAN' => [
                ['Households with weekly waste collection', 'Households', 'sum', 1800],
                ['Public toilets rehabilitated', 'Facilities', 'sum', 12],
            ],
            'WAT' => [
                ['New household water connections', 'Connections', 'sum', 640],
                ['Average daily water supply hours', 'Hours', 'average', 18],
            ],
            'REV' => [
                ['Property tax collected', 'Thousand birr', 'sum', 24000],
                ['Businesses registered for e-tax', 'Businesses', 'sum', 900],
            ],
            'URB' => [
                ['Kilometres of cobblestone road completed', 'Km', 'sum', 9],
                ['Land-use plans approved', 'Plans', 'sum', 36],
            ],
            'TRD' => [
                ['New investment licences issued', 'Licences', 'sum', 48],
                ['Youth enterprises supported', 'Enterprises', 'sum', 120],
            ],
        ];

        // A seasonal shape: slower in the rainy months, faster after Meskel.
        $weights = [1 => 6, 2 => 8, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9, 10 => 9, 11 => 7, 12 => 7];
        $totalWeight = array_sum($weights);

        $targets = [];

        foreach ($definitions as $code => $list) {
            foreach ($list as [$title, $unit, $aggregation, $value]) {
                $target = AnnualTarget::firstOrCreate(
                    ['department_id' => $offices[$code]->id, 'fiscal_year' => $year, 'title' => $title],
                    [
                        'description' => 'Fictional demonstration target.',
                        'measurement_unit' => $unit,
                        'aggregation' => $aggregation,
                        'target_value' => $value,
                        'original_target_value' => $value,
                        'is_active' => true,
                    ],
                );

                foreach ($weights as $month => $weight) {
                    $planned = $aggregation === 'sum' ? round($value * $weight / $totalWeight, 2) : $value;

                    MonthlyPlan::firstOrCreate(
                        ['annual_target_id' => $target->id, 'ethiopian_month' => $month],
                        ['planned_value' => $planned, 'planned_activities' => 'Fictional planned activities for '.EthiopianCalendar::monthName($month).'.'],
                    );
                }

                $targets[$code][] = $target->fresh();
            }
        }

        return $targets;
    }

    /**
     * How well each office is doing, so the demo shows on-track, at-risk and behind offices.
     */
    private function performance(string $code): float
    {
        return ['SAN' => 1.04, 'WAT' => 0.82, 'REV' => 0.97, 'URB' => 0.58, 'TRD' => 1.1][$code] ?? 0.9;
    }

    /**
     * @param  list<AnnualTarget>  $targets
     */
    private function weeklyReport(Department $office, User $preparer, User $reviewer, array $targets, int $year, int $month, int $week, string $code): void
    {
        $exists = WeeklyProgressReport::where([
            'department_id' => $office->id, 'ethiopian_fiscal_year' => $year, 'ethiopian_month' => $month, 'week_number' => $week,
        ])->exists();

        // Urban development is behind and has skipped the latest week.
        if ($exists || ($code === 'URB' && $week >= 2 && $month === EthiopianCalendar::today()['month'])) {
            return;
        }

        $report = WeeklyProgressReport::create([
            'department_id' => $office->id,
            'prepared_by' => $preparer->id,
            'ethiopian_fiscal_year' => $year,
            'ethiopian_month' => $month,
            'week_number' => $week,
            'achievements' => 'Fictional summary of the week\'s field work and completed activities.',
            'challenges' => $week % 2 === 0 ? 'Fictional note: a delivery of materials arrived late.' : null,
            'next_steps' => 'Fictional plan for the coming week.',
        ]);

        $weeks = EthiopianCalendar::weeksInMonth($year, $month);

        foreach ($targets as $target) {
            $plan = $target->monthlyPlans()->where('ethiopian_month', $month)->first();
            $share = $target->aggregation->isAdditive() ? $plan->planned_value / $weeks : $plan->planned_value;
            $wobble = 1 + (($week * 7 + $target->id * 3) % 11 - 5) / 100;

            $report->entries()->create([
                'monthly_plan_id' => $plan->id,
                'actual_value' => round($share * $this->performance($code) * $wobble, $target->aggregation->isAdditive() ? 0 : 1),
                'progress_summary' => 'Fictional progress note.',
            ]);
        }

        $isLatestWeek = $month === EthiopianCalendar::today()['month'] && $week === EthiopianCalendar::today()['week'] - 1;
        $status = match (true) {
            $isLatestWeek && $code === 'WAT' => ReportStatus::Returned,
            $isLatestWeek && in_array($code, ['SAN', 'TRD'], true) => ReportStatus::Submitted,
            $isLatestWeek && $code === 'REV' => ReportStatus::Draft,
            default => ReportStatus::Approved,
        };

        $this->moveTo($report, $status, $preparer, $reviewer, EthiopianCalendar::weekEnd($year, $month, $week)->addDay()->setTime(10, 30));
    }

    /**
     * @param  list<AnnualTarget>  $targets
     */
    private function monthlyReport(Department $office, User $preparer, User $reviewer, array $targets, int $year, int $month, string $code): void
    {
        if (MonthlyReport::where(['department_id' => $office->id, 'ethiopian_fiscal_year' => $year, 'ethiopian_month' => $month])->exists()) {
            return;
        }

        // Urban development has not filed its monthly report: shows as overdue.
        if ($code === 'URB') {
            return;
        }

        $report = MonthlyReport::create([
            'department_id' => $office->id,
            'prepared_by' => $preparer->id,
            'ethiopian_fiscal_year' => $year,
            'ethiopian_month' => $month,
            'summary' => 'Fictional monthly summary of results against plan.',
            'missed_target_reasons' => $this->performance($code) < 1 ? 'Fictional reason: equipment shortages slowed the work.' : null,
            'corrective_actions' => $this->performance($code) < 1 ? 'Fictional corrective action agreed with the supervising office.' : null,
        ]);

        foreach ($targets as $target) {
            $plan = $target->monthlyPlans()->where('ethiopian_month', $month)->first();

            $report->lines()->create([
                'monthly_plan_id' => $plan->id,
                'actual_value' => round($plan->planned_value * $this->performance($code), $target->aggregation->isAdditive() ? 0 : 1),
                'variance_note' => null,
            ]);
        }

        $status = $code === 'TRD' ? ReportStatus::Submitted : ReportStatus::Approved;
        $this->moveTo($report, $status, $preparer, $reviewer, EthiopianCalendar::monthEnd($year, $month)->addDays(2)->setTime(14, 0));
    }

    private function moveTo(WeeklyProgressReport|MonthlyReport $report, ReportStatus $status, User $preparer, User $reviewer, CarbonImmutable $submittedAt): void
    {
        if ($status === ReportStatus::Draft) {
            return;
        }

        $submittedAt = $submittedAt->min(now()->subHours(2));

        $report->forceFill(['status' => ReportStatus::Submitted, 'submitted_by' => $preparer->id, 'submitted_at' => $submittedAt])->save();
        $this->review($report, $preparer, ReviewAction::Submitted, null, $submittedAt);

        if ($status === ReportStatus::Returned) {
            $report->forceFill(['status' => ReportStatus::Returned])->save();
            $this->review($report, $reviewer, ReviewAction::Returned, 'Fictional reviewer comment: the connection count does not match the field log. Please check and resubmit.', $submittedAt->addDay()->min(now()->subHour()));
        }

        if ($status === ReportStatus::Approved) {
            $report->forceFill(['status' => ReportStatus::Approved])->save();
            $this->review($report, $reviewer, ReviewAction::Approved, null, $submittedAt->addDay()->min(now()->subHour()));
        }
    }

    private function review(WeeklyProgressReport|MonthlyReport $report, User $user, ReviewAction $action, ?string $comment, CarbonImmutable $at): void
    {
        $review = new ReportReview(['user_id' => $user->id, 'action' => $action, 'comment' => $comment]);
        $review->reviewable()->associate($report);
        $review->created_at = $at;
        $review->updated_at = $at;
        $review->save();
    }

    /**
     * Fictional website posts in all three languages, including a draft and a scheduled post.
     */
    private function posts(): void
    {
        $editor = User::firstOrCreate(
            ['email' => 'content@demo.test'],
            ['name' => 'Demo Content Manager', 'password' => self::PASSWORD],
        );
        $editor->roles()->syncWithoutDetaching([Role::where('name', 'content_manager')->value('id')]);

        $posts = [
            [
                'type' => PostType::News, 'slug' => 'demo-market-road-opened', 'featured' => true, 'published' => now()->subDays(2),
                'title' => ['om' => 'Daandiin gabaa haaraan tajaajilaaf banameera', 'am' => 'አዲሱ የገበያ መንገድ ለአገልግሎት ክፍት ሆነ', 'en' => 'New market road opens to traffic'],
                'excerpt' => ['om' => 'Daandiin dhagaa gabaa giddugaleessaa fi buufata konkolaataa walqunnamsiisu xumuramee banameera.', 'am' => 'ማዕከላዊውን ገበያና የአውቶቡስ ጣቢያውን የሚያገናኘው የኮብልስቶን መንገድ ተጠናቆ ተከፍቷል።', 'en' => 'The cobblestone road linking the central market and the bus station is complete and open.'],
                'body' => ['om' => "<p>Daandiin kiloomeetira 1.2 dheeratu hojii ji'a jahaa booda xumurameera.</p><p>Hawaasni naannoo hojii kana irratti hirmaachuu isaaniif galateeffamaniiru.</p>", 'am' => '<p>1.2 ኪሎ ሜትር የሚረዝመው መንገድ ከስድስት ወር ሥራ በኋላ ተጠናቋል።</p>', 'en' => '<p>The 1.2 km road was completed after six months of work.</p><p>Residents were thanked for taking part in the work.</p>'],
            ],
            [
                'type' => PostType::Announcement, 'slug' => 'demo-water-interruption', 'featured' => false, 'published' => now()->subDay(), 'deadline' => now()->addDays(5),
                'title' => ['om' => "Beeksisa: Bishaan sa'aatii muraasaaf ni dhaabata", 'am' => 'ማስታወቂያ፦ ውሃ ለጥቂት ሰዓታት ይቋረጣል', 'en' => 'Notice: Water supply interruption'],
                'excerpt' => ['om' => 'Sababa suphaa ujummoo ijootiin bishaan naannoo 02 keessatti guyyaa Sanbataa ganama ni dhaabata.', 'am' => 'በዋናው ቧንቧ ጥገና ምክንያት በቀበሌ 02 ቅዳሜ ጠዋት ውሃ ይቋረጣል።', 'en' => 'Because of main pipe repairs, water in kebele 02 will be off on Saturday morning.'],
                'body' => ['om' => '<p>Jiraattonni bishaan kuusatanii akka turan gorfamu.</p>', 'am' => '<p>ነዋሪዎች ውሃ አጠራቅመው እንዲቆዩ ይመከራሉ።</p>', 'en' => '<p>Residents are advised to store water in advance.</p>'],
            ],
            [
                'type' => PostType::Vacancy, 'slug' => 'demo-vacancy-accountant', 'featured' => false, 'published' => now()->subDays(3), 'deadline' => now()->addDays(10),
                'title' => ['om' => 'Beeksisa hojii: Herregaa', 'am' => 'የሥራ ማስታወቂያ፦ የሂሳብ ባለሙያ', 'en' => 'Vacancy: Accountant'],
                'excerpt' => ['om' => 'Waajjirri maallaqaa herregaa tokko qacaruu barbaada. Iyyannoon hanga guyyaa xumuraatti ni fudhatama.', 'am' => 'የፋይናንስ ጽሕፈት ቤቱ አንድ የሂሳብ ባለሙያ መቅጠር ይፈልጋል።', 'en' => 'The finance office is hiring one accountant. Applications are accepted until the deadline.'],
                'body' => ['om' => '<ul><li>Digrii herreguu</li><li>Muuxannoo waggaa 2</li></ul>', 'am' => '<ul><li>በሂሳብ አያያዝ ዲግሪ</li><li>የ2 ዓመት ልምድ</li></ul>', 'en' => '<ul><li>Degree in accounting</li><li>2 years of experience</li></ul>'],
            ],
            [
                'type' => PostType::Tender, 'slug' => 'demo-tender-office-furniture', 'featured' => false, 'published' => now()->subDays(4), 'deadline' => now()->addDays(15),
                'title' => ['om' => 'Caalbaasii: Meeshaalee waajjiraa', 'am' => 'ጨረታ፦ የቢሮ ዕቃዎች', 'en' => 'Tender: Office furniture'],
                'excerpt' => ['om' => 'Bulchiinsi magaalaa meeshaalee waajjiraa bituuf caalbaasii banaa baaseera.', 'am' => 'የከተማ አስተዳደሩ የቢሮ ዕቃዎችን ለመግዛት ግልጽ ጨረታ አውጥቷል።', 'en' => 'The city administration invites bids to supply office furniture.'],
                'body' => ['om' => '<p>Sanadni caalbaasii waajjira bittaa irraa argama.</p>', 'am' => '<p>የጨረታ ሰነዱ ከግዥ ጽሕፈት ቤት ይገኛል።</p>', 'en' => '<p>Bid documents are available from the procurement office.</p>'],
            ],
            [
                'type' => PostType::News, 'slug' => 'demo-draft-cleanup-campaign', 'featured' => false, 'published' => null,
                'title' => ['om' => 'Duula qulqullina magaalaa (wixinee)', 'am' => null, 'en' => 'City clean-up campaign (draft)'],
                'excerpt' => ['om' => 'Wixinee: ammallee hin maxxanfamne.', 'am' => null, 'en' => null],
                'body' => ['om' => '<p>Wixinee.</p>', 'am' => null, 'en' => null],
            ],
            [
                'type' => PostType::Announcement, 'slug' => 'demo-scheduled-town-meeting', 'featured' => false, 'published' => now()->addDays(3),
                'title' => ['om' => "Walgahii hawaasaa (yeroo murtaa'eef)", 'am' => 'የሕዝብ ስብሰባ', 'en' => 'Town meeting (scheduled)'],
                'excerpt' => ['om' => 'Guyyaa sadii booda ofumaan ni maxxanfama.', 'am' => null, 'en' => null],
                'body' => ['om' => '<p>Yeroo murtaa\'eef.</p>', 'am' => null, 'en' => null],
            ],
        ];

        foreach ($posts as $post) {
            Post::firstOrCreate(['slug' => $post['slug']], [
                'type' => $post['type'],
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'body' => $post['body'],
                'is_featured' => $post['featured'],
                'published_at' => $post['published'],
                'deadline_on' => $post['deadline'] ?? null,
                'created_by' => $editor->id,
                'updated_by' => $editor->id,
            ]);
        }
    }
}
