<?php

namespace App\Modules\Orthodontics\Services;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Rendered as the app's usual { success: false, message } JSON.
 */
class OrthodonticsException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 422,
        private string $errorCode = 'orthodontics_error',
        private ?string $messageAr = null,
        private array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function disabled(): self
    {
        return new self('The orthodontics module is not enabled for this clinic.', 403, 'orthodontics_disabled', 'وحدة تقويم الأسنان غير مفعلة لهذه العيادة.');
    }

    public static function notEligible(): self
    {
        return new self('Orthodontics tools are only available for cases in an orthodontic category.', 403, 'orthodontics_not_eligible', 'أدوات التقويم متاحة فقط لحالات فئة التقويم.');
    }

    public static function forbidden(string $permission): self
    {
        return new self('You do not have permission to perform this action', 403, 'orthodontics_forbidden', 'ليس لديك صلاحية لتنفيذ هذا الإجراء.', ['permission' => $permission]);
    }

    public static function notFound(string $what = 'Record'): self
    {
        return new self($what . ' not found', 404, 'not_found', 'غير موجود.');
    }

    public static function conflict(int $current): self
    {
        return new self('This analysis was changed by someone else. Reload it before saving.', 409, 'revision_conflict', 'تم تعديل هذا التحليل من مستخدم آخر. أعد تحميله قبل الحفظ.', ['current_revision' => $current]);
    }

    /** Expected outcomes, not errors - keep them out of the error log. */
    public function report(): void
    {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'message_ar' => $this->messageAr,
        ], fn ($v) => $v !== null) + $this->extra, $this->status);
    }
}
