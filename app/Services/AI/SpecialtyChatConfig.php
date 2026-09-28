<?php

namespace App\Services\AI;

/**
 * SpecialtyChatConfig
 *
 * Provides specialty-specific prompts, terminology, and keywords
 * for the AI chat pipeline. Auto-resolves from the current tenant.
 *
 * To add a new specialty:
 *   1. Add a new case in each method (clinicDescription, casesTerminology, etc.)
 *   2. The rest of the pipeline adapts automatically
 */
class SpecialtyChatConfig
{
    private string $specialty;

    public function __construct(?string $specialty = null)
    {
        $this->specialty = $specialty ?? (tenant('specialty') ?? 'dental');
    }

    public function specialty(): string
    {
        return $this->specialty;
    }

    /**
     * Short clinic type description for system prompts.
     * e.g. "dental clinic", "ophthalmology (eye care) clinic"
     */
    public function clinicDescription(): string
    {
        return match ($this->specialty) {
            'dental'        => 'dental clinic',
            'ophthalmology' => 'ophthalmology (eye care) clinic',
            default         => 'medical clinic',
        };
    }

    /**
     * Arabic clinic type label.
     */
    public function clinicDescriptionAr(): string
    {
        return match ($this->specialty) {
            'dental'        => 'عيادة أسنان',
            'ophthalmology' => 'عيادة عيون',
            default         => 'عيادة طبية',
        };
    }

    /**
     * What "cases" mean in this specialty — used in the system prompt
     * so GPT understands the domain vocabulary.
     */
    public function casesTerminology(): string
    {
        return match ($this->specialty) {
            'dental'        => '"Cases" = dental treatments/procedures (fillings, root canals, extractions, crowns, cleanings, etc.) with prices set by the dentist.',
            'ophthalmology' => '"Cases" = eye care treatments/procedures (cataract surgery, LASIK, retinal treatments, glaucoma treatments, eye exams, etc.) with prices set by the ophthalmologist.',
            default         => '"Cases" = medical treatments/procedures with prices set by the doctor.',
        };
    }

    /**
     * Specialty-specific context that gets appended to the system prompt
     * so GPT knows the domain.
     */
    public function domainContext(): string
    {
        return match ($this->specialty) {
            'dental' => 'This clinic handles dental patients. '
                . 'Cases include procedures like fillings, root canals, extractions, crowns, bridges, implants, cleanings, and orthodontics. '
                . 'Dental-specific fields: tooth_num (tooth number), root_stuffing (root canal filling). '
                . 'X-ray analysis is available for dental images. '
                . 'When discussing cases, use dental terminology the user will understand.',

            'ophthalmology' => 'This clinic handles eye care patients. '
                . 'Cases include procedures like cataract surgery, LASIK, retinal treatments, glaucoma management, eye exams, and vision tests. '
                . 'Ophthalmology-specific fields: eye_side (left/right/both), visual_acuity_left, visual_acuity_right, iop_left, iop_right (intraocular pressure), refraction_left, refraction_right, anterior_segment, posterior_segment, diagnosis. '
                . 'Image analysis is available for fundus photos, OCT scans, and slit-lamp images. '
                . 'When discussing cases, use eye care terminology the user will understand.',

            default => 'This is a general medical clinic. '
                . 'Cases include various medical treatments and procedures. '
                . 'When discussing cases, use clear medical terminology the user will understand.',
        };
    }

    /**
     * Extra keywords for the AI question analyzer's fallback (keyword-based).
     * Merged with the base keywords for the `cases` intent.
     */
    public function caseKeywords(): array
    {
        return match ($this->specialty) {
            'dental' => [
                'tooth', 'teeth', 'filling', 'root canal', 'extraction', 'crown', 'implant', 'cleaning', 'orthodontic', 'braces',
                'سن', 'أسنان', 'حشوة', 'خلع', 'تاج', 'زراعة', 'تقويم', 'تنظيف',
            ],
            'ophthalmology' => [
                'eye', 'vision', 'cataract', 'lasik', 'retina', 'glaucoma', 'iop', 'lens', 'cornea', 'fundus',
                'عين', 'عيون', 'نظر', 'ماء أبيض', 'ليزك', 'شبكية', 'جلوكوما', 'ضغط العين', 'عدسة', 'قرنية',
            ],
            default => [],
        };
    }

    /**
     * Medical knowledge keywords for the fallback analyzer.
     */
    public function medicalKeywords(): array
    {
        return match ($this->specialty) {
            'dental' => ['dental', 'teeth', 'gum', 'oral', 'أسنان', 'لثة', 'فم'],
            'ophthalmology' => ['eye', 'vision', 'optic', 'retina', 'عين', 'نظر', 'بصر', 'شبكية'],
            default => ['medical', 'health', 'طبي', 'صحة'],
        };
    }

    /**
     * The full system message for the SmartChatOrchestrator's callGPT().
     */
    public function buildChatSystemPrompt(): string
    {
        $clinicType = $this->clinicDescription();
        $casesTerm = $this->casesTerminology();
        $domain = $this->domainContext();

        return "You are a smart AI assistant for a {$clinicType} management system called SmartClinic. "
            . 'You have direct access to real-time clinic data including: payments/bills, expenses, patients, cases/treatments, and reservations/appointments. '
            . $domain . ' '
            . 'IMPORTANT financial data model: '
            . '- "Bills" (bills table) = payments received from the auditor/accountant, NOT invoices. Each bill records an amount paid. '
            . "- {$casesTerm} Cases have an is_paid flag. "
            . '- "Unpaid Amount" = sum of case prices where is_paid is false. '
            . '- When user asks about "المبالغ المدفوعة" (paid amounts), report the total payments received (bills sum). '
            . '- When user asks about unpaid amounts, report the sum of unpaid case prices. '
            . 'When real-time data context is provided, use it to give accurate, data-driven answers with specific numbers. '
            . 'Always present financial data clearly with totals. '
            . 'For analytics questions, provide insights and actionable recommendations. '
            . 'Be professional, friendly, and concise. Always respond in the same language the user asks in (Arabic or English). '
            . 'If data shows trends, explain possible reasons. '
            . 'The current date and time is: ' . now()->toDateTimeString() . '.';
    }

    /**
     * The system prompt for the AIQuestionAnalyzer.
     */
    public function buildAnalyzerSystemPrompt(): string
    {
        $clinicType = $this->clinicDescription();
        $today = now()->toDateString();

        $specialtyRules = match ($this->specialty) {
            'dental' => '- "cases" intent includes dental procedures: fillings, root canals, extractions, crowns, implants, cleanings, orthodontics'
                . "\n" . '- "medical_question" intent includes dental knowledge, oral health, teeth care questions',
            'ophthalmology' => '- "cases" intent includes eye procedures: cataract surgery, LASIK, retinal treatments, glaucoma management, eye exams'
                . "\n" . '- "medical_question" intent includes eye health, vision care, ophthalmology knowledge questions',
            default => '- "cases" intent includes medical treatments and procedures'
                . "\n" . '- "medical_question" intent includes general medical knowledge questions',
        };

        return <<<PROMPT
You are an AI question analyzer for a {$clinicType} management system.
Analyze the user's question and return a JSON object with this exact structure:

{
  "intent": "<one of: revenue, expenses, patients, reservations, cases, analytics, medical_question, search_patient, general>",
  "entities": {
    "patient_name": "<extracted patient name or empty string>",
    "doctor_name": "<extracted doctor name or empty string>"
  },
  "date_range": {
    "type": "<one of: today, yesterday, tomorrow, this_week, last_week, this_month, last_month, specific_date, custom, none>",
    "start": "<ISO date string or null>",
    "end": "<ISO date string or null>"
  },
  "needs_database": <true if the question requires real-time clinic database data>,
  "needs_vector_search": <true if the question might match embedded records like patient names or case details>,
  "needs_knowledge_base": <true if the question is about medical knowledge, procedures, or terminology>
}

Rules:
- "analytics" intent is for comparative/trend questions like "top doctor", "compare months", "growth rate", "why revenue decreased"
- "revenue" intent is for questions about payments received from auditor (bills table), case prices set by doctor, unpaid amounts, financial data, income
- "expenses" intent is for questions about clinic expenses, costs, spending
- "patients" intent is for questions about patient counts, demographics, registrations
- "reservations" intent is for questions about appointments, bookings, schedules
{$specialtyRules}
- "search_patient" intent is when user is looking for a specific patient by name
- "general" intent is for greetings, help requests, or unrelated questions
- Today's date is: {$today}
- Support Arabic and English questions
- For date_range, if user says "today" set type to "today", if "this month" set type to "this_month", etc.
- If a specific date is mentioned, set type to "specific_date" and fill start/end
- If no date is mentioned, set type to "none"
- Return ONLY valid JSON, no extra text
PROMPT;
    }
}
