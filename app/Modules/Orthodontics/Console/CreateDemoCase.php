<?php

namespace App\Modules\Orthodontics\Console;

use App\Models\CaseCategory;
use App\Models\CaseModel;
use App\Models\Patient;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Orthodontics\Cbct\CbctService;
use App\Modules\Orthodontics\Models\OrthodonticFile;
use App\Modules\Orthodontics\Services\AnalysisService;
use App\Modules\Orthodontics\Services\OrthodonticFileStorage;
use App\Modules\Orthodontics\Services\TreatmentPlanService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds a ready-to-show demonstration case in one clinic, so the module can
 * be presented without tracing a radiograph first:
 *
 *   php artisan orthodontics:demo --tenant=clinic_1
 *
 * It creates (or reuses) a patient marked as demo data, a new orthodontic case
 * for that patient, a lateral ceph with every landmark placed and the ruler
 * calibrated, the two synthetic 3D models with their units confirmed and a
 * cast analysis from caliper widths, a synthetic CBCT study, and a treatment
 * plan. Everything goes through the
 * module's own services, so measurements are computed exactly as they are for
 * a real case. The assets live in database/demo/orthodontics (see its README).
 */
class CreateDemoCase extends Command
{
    protected $signature = 'orthodontics:demo
        {--tenant= : Clinic (tenant) id, from php artisan tenants:list}
        {--doctor= : User id that owns the demo case (default: the first doctor)}
        {--category= : Case category id to use; it is flagged as orthodontic}
        {--no-enable : Leave the enable_orthodontics setting as it is}';

    protected $description = 'Create an orthodontics demo case (traced ceph, 3D models, CBCT, plan) in one clinic';

    public const PATIENT_IDENTIFIER = 'ORTHO-DEMO';

    /**
     * Landmarks placed by eye on ceph_lateral.jpg (2272 x 1818 px, patient
     * facing right). For demonstration only; not a clinical tracing.
     */
    public const LANDMARKS = [
        'S' => [1268, 638], 'N' => [1850, 652], 'Po' => [1105, 848], 'Or' => [1745, 935],
        'Ba' => [1080, 990], 'Pt' => [1425, 868], 'Ar' => [1195, 950], 'Co' => [1180, 912],
        'ANS' => [1860, 990], 'PNS' => [1445, 992], 'A' => [1832, 1062], 'B' => [1838, 1372],
        'Pog' => [1852, 1478], 'Gn' => [1830, 1540], 'Me' => [1790, 1566], 'Go' => [1250, 1298],
        'U1T' => [1892, 1237], 'U1A' => [1795, 1035], 'L1T' => [1876, 1250], 'L1A' => [1805, 1440],
        'U6' => [1597, 1192], 'L6' => [1597, 1210], 'Prn' => [2054, 932], 'Sn' => [1954, 1028],
        'Ls' => [1994, 1159], 'Li' => [1982, 1272], 'PogS' => [1928, 1455],
    ];

    /**
     * Typical mesiodistal widths (mm, FDI), entered as caliper readings. They
     * give a Bolton overall ratio of 83.6 / 92 = 90.87 % (inside the norm) and
     * an anterior ratio of 33.6 / 45 = 74.67 % (below it), so the demo shows
     * both cases.
     */
    public const TOOTH_WIDTHS_MM = [
        '16' => 10.0, '15' => 6.5, '14' => 7.0, '13' => 7.5, '12' => 6.5, '11' => 8.5,
        '21' => 8.5, '22' => 6.5, '23' => 7.5, '24' => 7.0, '25' => 6.5, '26' => 10.0,
        '46' => 11.0, '45' => 7.0, '44' => 7.0, '43' => 6.5, '42' => 5.3, '41' => 5.0,
        '31' => 5.0, '32' => 5.3, '33' => 6.5, '34' => 7.0, '35' => 7.0, '36' => 11.0,
    ];

    /** The 10 mm ruler printed at the top right of the radiograph. */
    public const RULER = ['p1' => ['x' => 2052, 'y' => 267.5], 'p2' => ['x' => 2052, 'y' => 352.5], 'known_mm' => 10];

    public function handle(
        OrthodonticFileStorage $files,
        AnalysisService $analyses,
        CbctService $cbct,
        TreatmentPlanService $plans,
    ): int {
        $tenantId = $this->option('tenant');
        if (!$tenantId) {
            $this->error('Pass the clinic id: --tenant=<id>. List them with: php artisan tenants:list');

            return self::FAILURE;
        }
        $result = $this->inClinic((string) $tenantId, fn () => $this->build($files, $analyses, $cbct, $plans));
        if ($result === null) {
            $this->error("No clinic with id '{$tenantId}'. List them with: php artisan tenants:list");

            return self::FAILURE;
        }

        return $result;
    }

    /**
     * Runs the work inside the clinic's database and storage, or returns null
     * when the clinic doesn't exist.
     */
    protected function inClinic(string $tenantId, \Closure $work): ?int
    {
        $tenant = Tenant::find($tenantId);

        return $tenant ? (int) $tenant->run($work) : null;
    }

    private function build(OrthodonticFileStorage $files, AnalysisService $analyses, CbctService $cbct, TreatmentPlanService $plans): int
    {
        if (!Schema::hasTable('orthodontic_files')) {
            $this->error('The orthodontics tables are missing in this clinic. Run php artisan tenants:migrate (or database/sql/orthodontics_module.sql) first.');

            return self::FAILURE;
        }

        $doctor = $this->doctor();
        if (!$doctor) {
            $this->error('No user found to own the demo case. Pass --doctor=<user id>.');

            return self::FAILURE;
        }
        // The services record who did what (created_by, uploaded_by, audit log).
        Auth::setUser($doctor);

        $category = $this->category();
        if (!$category) {
            return self::FAILURE;
        }
        $status = Status::orderBy('id')->first();
        if (!$status) {
            $this->error('This clinic has no case statuses yet.');

            return self::FAILURE;
        }

        if (!$this->option('no-enable')) {
            $this->enableModule();
        }

        $assets = database_path('demo/orthodontics');
        [$patient, $case] = $this->patientAndCase($doctor, $category, $status);

        // 1. Lateral ceph, fully traced and calibrated with the film's ruler.
        $xray = $files->store($case, $this->upload("$assets/ceph_lateral.jpg", 'ceph_demo.jpg', 'image/jpeg'), 'ceph_xray', [
            'label' => 'Demo lateral ceph (Wikimedia Commons, CC BY-SA 4.0)',
        ]);
        $ceph = $analyses->create($case, [
            'type' => 'ceph',
            'title' => 'التحليل الأولي (تجريبي)',
            'file_id' => $xray->id,
            'data' => [
                'landmarks' => collect(self::LANDMARKS)->map(fn ($p) => ['x' => $p[0], 'y' => $p[1]])->all(),
                'calibration' => self::RULER,
            ],
            'notes' => 'نقاط موضوعة للعرض فقط وليست تتبعاً سريرياً.',
        ]);
        $this->line('  Ceph analysis #' . $ceph->id . ' (draft, so the doctor can review and approve it during the demo)');

        // 2. Upper and lower 3D models, exported in millimetres, with a cast
        //    analysis from caliper widths so Bolton shows results at once.
        $modelIds = [];
        foreach (['model_upper', 'model_lower'] as $kind) {
            $model = $files->store($case, $this->upload("$assets/$kind.stl", "{$kind}_demo.stl", 'model/stl'), $kind, [
                'label' => 'Synthetic demo model',
            ]);
            $model->meta = array_merge($model->meta ?? [], [
                'units' => 'mm',
                'scale_to_mm' => 1.0,
                'units_confirmed' => true,
                'units_confirmed_by' => $doctor->id,
                'units_confirmed_at' => now()->toIso8601String(),
            ]);
            $model->save();
            $modelIds[$kind === 'model_upper' ? 'upper' : 'lower'] = $model->id;
        }
        $cast = $analyses->create($case, [
            'type' => 'cast',
            'title' => 'قياسات الأسنان بالمسماك (تجريبي)',
            'file_id' => $modelIds['upper'],
            'data' => [
                'scale_to_mm' => 1.0,
                'model_file_ids' => $modelIds,
                'teeth' => collect(self::TOOTH_WIDTHS_MM)->map(fn ($mm) => ['manual_mm' => $mm])->all(),
            ],
            'notes' => 'عروض أسنان نموذجية مُدخلة يدوياً للعرض فقط.',
        ]);
        $this->line('  3D models: upper and lower (units confirmed as mm), cast analysis #' . $cast->id);

        // 3. Synthetic CBCT phantom.
        $study = $this->importCbct($cbct, $case, "$assets/cbct_phantom.zip");
        if ($study) {
            $this->line('  CBCT study #' . $study->id . ': ' . $study->instances_count . ' slices');
        }

        // 4. Treatment plan.
        $plans->save($case, $this->plan(), null);
        $this->line('  Treatment plan saved');

        $m = $ceph->results['measurements'] ?? [];
        $value = fn ($k) => isset($m[$k]['value']) ? round((float) $m[$k]['value'], 1) : '-';

        $this->newLine();
        $this->info('Demo case ready.');
        $this->line("  Patient: {$patient->name} (#{$patient->id})");
        $this->line("  Case:    #{$case->id}, category \"{$category->name}\"");
        $this->line("  Open:    /cases/{$case->id}?tab=orthodontics");
        $this->line("  Ceph:    SNA {$value('SNA')}, SNB {$value('SNB')}, ANB {$value('ANB')}, FMA {$value('FMA')}");
        $this->newLine();
        $this->line('This is demo data. Delete the demo patient from the patients page when you no longer need it.');

        return self::SUCCESS;
    }

    private function doctor(): ?User
    {
        if ($id = $this->option('doctor')) {
            return User::find($id);
        }
        try {
            $doctor = User::role(['clinic_super_doctor', 'doctor'])->orderBy('id')->first();
        } catch (\Throwable) {
            $doctor = null; // a role that doesn't exist in this clinic
        }

        return $doctor ?: User::orderBy('id')->first();
    }

    private function category(): ?CaseCategory
    {
        if ($id = $this->option('category')) {
            $category = CaseCategory::find($id);
            if (!$category) {
                $this->error("No case category with id {$id}.");

                return null;
            }
            if (!$category->is_orthodontic) {
                $category->is_orthodontic = true;
                $category->save();
                $this->line("  Flagged category \"{$category->name}\" as orthodontic");
            }

            return $category;
        }

        $category = CaseCategory::where('is_orthodontic', true)->orderBy('id')->first();
        if ($category) {
            return $category;
        }

        $category = new CaseCategory();
        $category->forceFill(array_filter([
            'name' => 'تقويم',
            'name_ar' => Schema::hasColumn('case_categories', 'name_ar') ? 'تقويم' : null,
            'name_en' => Schema::hasColumn('case_categories', 'name_en') ? 'Orthodontics' : null,
            'is_orthodontic' => true,
        ], fn ($v) => $v !== null))->save();
        $this->line("  No orthodontic category existed: created \"تقويم\" (#{$category->id})");

        return $category;
    }

    private function enableModule(): void
    {
        $key = (string) config('orthodontics.setting_key', 'enable_orthodontics');
        $row = DB::table('clinic_settings')->where('setting_key', $key)->first();
        if ($row && $row->setting_value === '1' && $row->is_active) {
            return;
        }
        if ($row) {
            DB::table('clinic_settings')->where('id', $row->id)->update(['setting_value' => '1', 'is_active' => true, 'updated_at' => now()]);
        } else {
            DB::table('clinic_settings')->insert([
                'setting_key' => $key,
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Orthodontics module: cephalometric, 3D model, CBCT and facial analysis tools inside orthodontic cases',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->line('  Switched the orthodontics module on for this clinic');
    }

    /**
     * Model events are skipped: they would queue embeddings and run the
     * clinic's automations (messages to the patient) for a demo record.
     *
     * @return array{0: Patient, 1: CaseModel}
     */
    private function patientAndCase(User $doctor, CaseCategory $category, Status $status): array
    {
        return Patient::withoutEvents(function () use ($doctor, $category, $status) {
            $patient = Patient::where('identifier', self::PATIENT_IDENTIFIER)->first();
            if (!$patient) {
                $patient = Patient::create([
                    'name' => 'مريض تجريبي (عرض التقويم)',
                    'phone' => '00000000000',
                    'age' => 15,
                    'doctor_id' => $doctor->id,
                    'identifier' => self::PATIENT_IDENTIFIER,
                    'notes' => 'بيانات تجريبية لعرض وحدة التقويم، وليست لمريض حقيقي.',
                    'public_token' => Str::uuid()->toString(),
                    'is_public_profile_enabled' => false,
                    'creator_id' => $doctor->id,
                    'updator_id' => $doctor->id,
                ]);
            }

            $case = CaseModel::withoutEvents(fn () => CaseModel::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'case_categores_id' => $category->id,
                'status_id' => $status->id,
                'notes' => 'حالة تجريبية لعرض وحدة التقويم.',
                'price' => 0,
                'case_date' => now(),
            ]));

            return [$patient, $case];
        });
    }

    private function importCbct(CbctService $cbct, CaseModel $case, string $zipPath)
    {
        if (!class_exists(ZipArchive::class)) {
            $this->warn('  CBCT skipped: the PHP zip extension is not available.');

            return null;
        }

        $dir = sys_get_temp_dir() . '/ortho-demo-' . Str::random(8);
        File::ensureDirectoryExists($dir);
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $this->warn('  CBCT skipped: the demo archive could not be opened.');

                return null;
            }
            $uploads = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!str_ends_with(strtolower($name), '.dcm')) {
                    continue;
                }
                $target = $dir . '/' . basename($name);
                file_put_contents($target, $zip->getFromIndex($i));
                $uploads[] = $this->upload($target, basename($name), 'application/dicom');
            }
            $zip->close();

            $study = $cbct->create($case, 'Synthetic CBCT phantom (demo)');
            $result = $cbct->addInstances($study, $uploads);
            if ($result['skipped']) {
                $this->warn('  CBCT: ' . count($result['skipped']) . ' slice(s) skipped');
            }

            return $cbct->complete($study->fresh());
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function plan(): array
    {
        $today = now()->toDateString();

        return [
            'chief_complaint' => 'بيانات تجريبية: بروز الفك السفلي وعدم انتظام الأسنان الأمامية.',
            'clinical_examination' => 'بيانات تجريبية للعرض: وجه متناظر تقريباً، ملف جانبي مقعّر قليلاً، والشفاه تنطبق دون إجهاد.',
            'diagnosis' => 'بيانات تجريبية: علاقة هيكلية من الصنف الثالث (ANB و Wits بالسالب)، وزاوية مستوى الفك السفلي منخفضة، مع تعويض سني: القواطع العلوية مائلة للأمام والسفلية مائلة للخلف.',
            'skeletal_class' => 'III',
            'molar_class_right' => 'III',
            'molar_class_left' => 'III',
            'canine_class_right' => 'III',
            'canine_class_left' => 'III',
            'crowding_upper_mm' => 2,
            'crowding_lower_mm' => 1,
            'crossbite' => 'لا يوجد',
            'objectives' => 'تسوية الأسنان واصطفافها، وتحسين العلاقة الأمامية، والمحافظة على صحة اللثة، ثم تثبيت النتيجة.',
            'planned_treatment' => 'جهاز ثابت على الفكين مع تعويض سني، ومطاط صنف ثالث عند الحاجة. يُعاد التقييم بعد اكتمال النمو.',
            'appliance_type' => 'براكيت معدني ثابت',
            'estimated_duration_months' => 24,
            'stages' => [
                ['title' => 'التسوية والاصطفاف', 'description' => 'أسلاك NiTi مقاس 0.014 ثم 0.016', 'status' => 'pending', 'target_date' => now()->addMonths(6)->toDateString()],
                ['title' => 'أسلاك العمل', 'description' => 'تصحيح العلاقة الأمامية مع مطاط صنف ثالث', 'status' => 'pending', 'target_date' => now()->addMonths(16)->toDateString()],
                ['title' => 'الإنهاء', 'description' => 'ضبط التفاصيل والتعشيق النهائي', 'status' => 'pending', 'target_date' => now()->addMonths(22)->toDateString()],
                ['title' => 'التثبيت', 'description' => 'مثبت ثابت سفلي ومثبت متحرك علوي', 'status' => 'pending', 'target_date' => now()->addMonths(24)->toDateString()],
            ],
            'doctor_notes' => 'خطة تجريبية لعرض النظام فقط، وليست لمريض حقيقي.',
            'start_date' => $today,
            'status' => 'planned',
        ];
    }

    private function upload(string $path, string $name, string $mime): UploadedFile
    {
        // test mode: a local file, not an HTTP upload. The source is copied,
        // never moved, so the demo assets stay in place.
        return new UploadedFile($path, $name, $mime, null, true);
    }
}
