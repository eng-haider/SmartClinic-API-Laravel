<?php

use App\Http\Middleware\EnsureTokenMatchesTenant;
use App\Modules\Orthodontics\Http\Controllers\CbctStudyController;
use App\Modules\Orthodontics\Http\Controllers\OrthodonticAnalysisController;
use App\Modules\Orthodontics\Http\Controllers\OrthodonticFileController;
use App\Modules\Orthodontics\Http\Controllers\OrthodonticsWorkspaceController;
use App\Modules\Orthodontics\Http\Controllers\TreatmentPlanController;
use App\Modules\Orthodontics\Http\Middleware\EnsureOrthodonticsEnabled;
use Illuminate\Support\Facades\Route;

/*
| Orthodontics module routes, loaded inside the tenant API group of
| routes/tenant.php (so they are /api/tenant/cases/{case}/orthodontics/...).
| Every route needs a valid token issued for this clinic and the module
| switched on; the controllers then check permission, case access and the
| orthodontic category (see OrthodonticsAccess).
*/

Route::middleware(['jwt', EnsureTokenMatchesTenant::class, EnsureOrthodonticsEnabled::class])
    ->prefix('cases/{case}/orthodontics')
    ->whereNumber('case')
    ->group(function () {
        Route::get('/', [OrthodonticsWorkspaceController::class, 'overview']);
        Route::get('report', [OrthodonticsWorkspaceController::class, 'report']);

        // Original uploads (radiographs, photos, 3D models)
        Route::get('files', [OrthodonticFileController::class, 'index']);
        Route::post('files', [OrthodonticFileController::class, 'store']);
        Route::get('files/{file}/content', [OrthodonticFileController::class, 'content'])->whereNumber('file');
        Route::patch('files/{file}', [OrthodonticFileController::class, 'update'])->whereNumber('file');
        Route::delete('files/{file}', [OrthodonticFileController::class, 'destroy'])->whereNumber('file');
        Route::post('files/{file}/detect-landmarks', [OrthodonticAnalysisController::class, 'detectLandmarks'])->whereNumber('file');

        // Analyses: type = ceph | cast | face | cbct
        Route::post('analyses/compute', [OrthodonticAnalysisController::class, 'compute']);
        Route::get('analyses', [OrthodonticAnalysisController::class, 'index']);
        Route::post('analyses', [OrthodonticAnalysisController::class, 'store']);
        Route::get('analyses/{analysis}', [OrthodonticAnalysisController::class, 'show'])->whereNumber('analysis');
        Route::put('analyses/{analysis}', [OrthodonticAnalysisController::class, 'update'])->whereNumber('analysis');
        Route::delete('analyses/{analysis}', [OrthodonticAnalysisController::class, 'destroy'])->whereNumber('analysis');
        Route::post('analyses/{analysis}/review', [OrthodonticAnalysisController::class, 'review'])->whereNumber('analysis');
        Route::get('analyses/{analysis}/revisions', [OrthodonticAnalysisController::class, 'revisions'])->whereNumber('analysis');
        Route::get('analyses/{analysis}/revisions/{revision}', [OrthodonticAnalysisController::class, 'revision'])->whereNumber(['analysis', 'revision']);

        // CBCT studies (DICOM)
        Route::get('cbct', [CbctStudyController::class, 'index']);
        Route::post('cbct', [CbctStudyController::class, 'store']);
        Route::get('cbct/{study}', [CbctStudyController::class, 'show'])->whereNumber('study');
        Route::post('cbct/{study}/instances', [CbctStudyController::class, 'upload'])->whereNumber('study');
        Route::post('cbct/{study}/complete', [CbctStudyController::class, 'complete'])->whereNumber('study');
        Route::get('cbct/{study}/instances/{instance}', [CbctStudyController::class, 'instance'])->whereNumber(['study', 'instance']);
        Route::put('cbct/{study}/viewer-state', [CbctStudyController::class, 'viewerState'])->whereNumber('study');
        Route::delete('cbct/{study}', [CbctStudyController::class, 'destroy'])->whereNumber('study');

        // Treatment plan (one per case)
        Route::get('treatment-plan', [TreatmentPlanController::class, 'show']);
        Route::put('treatment-plan', [TreatmentPlanController::class, 'update']);
        Route::get('treatment-plan/history', [TreatmentPlanController::class, 'history']);
    });
