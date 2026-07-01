<?php
/**
 * Declarative schema for the guided wound-care clinical note, modeled on Randy's
 * "Wound Care Dictation Guide". The render engine (api/lib/clinical_note.php) turns
 * this into (a) the capture form and (b) the rendered note body, and the gap checker
 * reads the captured values by key. Keep this file purely declarative so wording and
 * fields are easy to tune and additional templates can be added later.
 *
 * Field shape:
 *   ['key','label','type'] + type-specific extras
 *   types: text | textarea | number | date | radio | checkbox | select
 *   radio/select/checkbox add 'options' => [...]
 *   optional 'unit', 'placeholder', 'help', 'showIf' => ['key'=>'dm_present','eq'=>'Yes']
 *
 * A section may set 'repeat' => 'wound' to indicate it is captured once per wound.
 */

function clinical_note_templates(): array {
    return [
        'wound_care_dictation' => [
            'key'   => 'wound_care_dictation',
            'label' => 'Wound Care Encounter (Dictation Guide)',
            'sections' => clinical_note_dictation_sections(),
        ],
    ];
}

function clinical_note_template(string $key): ?array {
    return clinical_note_templates()[$key] ?? null;
}

function clinical_note_dictation_sections(): array {
    return [
        // ---- Encounter ------------------------------------------------------
        [
            'key' => 'encounter', 'title' => 'Encounter',
            'fields' => [
                ['key'=>'date_of_service','label'=>'Date of Service','type'=>'date'],
                ['key'=>'provider','label'=>'Provider','type'=>'text'],
                ['key'=>'place_of_service','label'=>'Place of Service','type'=>'select',
                 'options'=>['Patient\'s home','Assisted living facility','Nursing home','SNF','Physician office']],
            ],
        ],

        // ---- Barriers: Diabetes --------------------------------------------
        [
            'key' => 'barrier_diabetes', 'title' => 'Barriers — Diabetes',
            'fields' => [
                ['key'=>'dm_present','label'=>'Is the patient diabetic?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'dm_type','label'=>'Type','type'=>'radio','options'=>['Type 1','Type 2'],'showIf'=>['key'=>'dm_present','eq'=>'Yes']],
                ['key'=>'dm_a1c','label'=>'A1c level','type'=>'text','showIf'=>['key'=>'dm_present','eq'=>'Yes'],'help'=>'If A1c > 8.0, address whether the wound is healing effectively.'],
                ['key'=>'dm_avg_glucose','label'=>'Average daily glucose','type'=>'text','showIf'=>['key'=>'dm_present','eq'=>'Yes']],
                ['key'=>'dm_managing_provider','label'=>'PCP / specialist managing comorbidities','type'=>'text','showIf'=>['key'=>'dm_present','eq'=>'Yes']],
            ],
        ],

        // ---- Barriers: Vascular --------------------------------------------
        [
            'key' => 'barrier_vascular', 'title' => 'Barriers — Venous / Arterial Insufficiency',
            'fields' => [
                ['key'=>'vasc_sufficient','label'=>'Sufficient vascularization?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'vasc_abi','label'=>'ABI','type'=>'text'],
                ['key'=>'vasc_pvr','label'=>'Pulse Volume Recording','type'=>'text'],
                ['key'=>'vasc_duplex','label'=>'Duplex scan','type'=>'text'],
                ['key'=>'vasc_doppler','label'=>'Doppler ultrasound','type'=>'text'],
                ['key'=>'vasc_other','label'=>'Other vascular / arterial assessment','type'=>'text'],
                ['key'=>'vasc_referral_needed','label'=>'Vascular referral needed?','type'=>'radio','options'=>['Yes','No']],
            ],
        ],

        // ---- Barriers: Nutrition / Tobacco / Alcohol -----------------------
        [
            'key' => 'barrier_nutrition', 'title' => 'Barriers — Nutrition, Tobacco & Alcohol',
            'fields' => [
                ['key'=>'nutrition_adequate','label'=>'Receiving proper nutrition?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'labs_ordered','label'=>'Labs ordered (CBC w/diff w/PLT, CMP, A1C)?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'smoker','label'=>'Smoker?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'smoking_counseled','label'=>'Counseled on smoking & wound healing; encouraged cessation?','type'=>'radio','options'=>['Yes','No'],'showIf'=>['key'=>'smoker','eq'=>'Yes']],
                ['key'=>'smoking_cessation_plan','label'=>'Smoking cessation plan','type'=>'textarea','showIf'=>['key'=>'smoker','eq'=>'Yes']],
                ['key'=>'alcohol','label'=>'Alcohol use?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'alcohol_counseled','label'=>'Counseled on alcohol effects; advised reduce/eliminate?','type'=>'radio','options'=>['Yes','No'],'showIf'=>['key'=>'alcohol','eq'=>'Yes']],
            ],
        ],

        // ---- Barriers: Offloading & History --------------------------------
        [
            'key' => 'barrier_offloading', 'title' => 'Barriers — Offloading & History',
            'fields' => [
                ['key'=>'offloading_done','label'=>'Measures taken to offload the wound?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'offloading_mattress','label'=>'Pressure-relieving / redistribution mattress','type'=>'text','showIf'=>['key'=>'offloading_done','eq'=>'Yes']],
                ['key'=>'offloading_roho','label'=>'ROHO cushion','type'=>'radio','options'=>['Yes','No'],'showIf'=>['key'=>'offloading_done','eq'=>'Yes']],
                ['key'=>'offloading_boots','label'=>'Offloading boots','type'=>'radio','options'=>['Yes','No'],'showIf'=>['key'=>'offloading_done','eq'=>'Yes']],
                ['key'=>'offloading_detail','label'=>'Measures taken / duration / results (or why not an option)','type'=>'textarea'],
                ['key'=>'history_prior','label'=>'History of prior ulceration, infection, osteomyelitis, or amputation?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'history_detail','label'=>'If yes, elaborate','type'=>'textarea','showIf'=>['key'=>'history_prior','eq'=>'Yes']],
                ['key'=>'past_treatments','label'=>'Past wound care treatment(s) — include % change over the past 30 days and why prior therapies failed (<50% reduction in 30 days = failed conservative care)','type'=>'textarea'],
            ],
        ],

        // ---- Risk Scales ----------------------------------------------------
        [
            'key' => 'risk_scales', 'title' => 'Risk Scales (Braden / Norton)',
            'fields' => [
                ['key'=>'braden_total','label'=>'Braden total','type'=>'number','help'=>'<9 severe · 10-12 high · 13-14 moderate · 15-18 mild'],
                ['key'=>'norton_total','label'=>'Norton total','type'=>'number','help'=>'>18 low · 14-18 medium · 10-14 high · <10 very high'],
            ],
        ],

        // ---- Pre-debridement wound characteristics (per wound) -------------
        [
            'key' => 'wound_pre', 'title' => 'Pre-Debridement Wound Characteristics', 'repeat' => 'wound',
            'fields' => [
                ['key'=>'location','label'=>'Location','type'=>'text'],
                ['key'=>'duration_years','label'=>'Wound duration (years)','type'=>'number'],
                ['key'=>'duration_months','label'=>'Wound duration (months)','type'=>'number'],
                ['key'=>'type','label'=>'Wound type','type'=>'select','options'=>['Diabetic','Venous','Arterial','Pressure','Surgical','Trauma','Other']],
                ['key'=>'stage','label'=>'Stage / grade','type'=>'text','help'=>'Wagner grade (diabetic) or pressure stage 1-4 / MDRPI / DTI'],
                ['key'=>'thickness','label'=>'Thickness','type'=>'radio','options'=>['Full thickness','Partial thickness']],
                ['key'=>'length_cm','label'=>'Length (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'width_cm','label'=>'Width (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'depth_cm','label'=>'Depth (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'prev_length_cm','label'=>'Previous length (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'prev_width_cm','label'=>'Previous width (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'prev_depth_cm','label'=>'Previous depth (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'undermining_tunneling','label'=>'Undermining / tunneling','type'=>'radio','options'=>['Undermining','Tunneling','Neither']],
                ['key'=>'ut_detail','label'=>'U/T size @ o\'clock','type'=>'text'],
                ['key'=>'bed_granular','label'=>'Wound bed — granular %','type'=>'number','unit'=>'%'],
                ['key'=>'bed_fibrotic','label'=>'Wound bed — fibrotic %','type'=>'number','unit'=>'%'],
                ['key'=>'bed_slough','label'=>'Wound bed — slough %','type'=>'number','unit'=>'%'],
                ['key'=>'bed_eschar','label'=>'Wound bed — eschar %','type'=>'number','unit'=>'%'],
                ['key'=>'color','label'=>'Wound color','type'=>'select','options'=>['Pink','Red','Yellow','Black']],
                ['key'=>'visible_structures','label'=>'Visible structures','type'=>'select','options'=>['None','Tendon','Bone','Muscle','Capsule','Ligament','Hardware']],
                ['key'=>'edges','label'=>'Wound edges','type'=>'select','options'=>['Attached','Unattached','Calloused','Rolled or Epibole']],
                ['key'=>'periwound','label'=>'Periwound tissue','type'=>'select','options'=>['Healthy','Macerated','Inflamed','Calloused']],
                ['key'=>'exudate_amount','label'=>'Exudate amount','type'=>'select','options'=>['None','Minimal','Moderate','Heavy']],
                ['key'=>'exudate_type','label'=>'Exudate type','type'=>'select','options'=>['None','Serous','Serosanguinous','Purulent','Bloody','Other']],
                ['key'=>'infection_signs','label'=>'Signs/symptoms of infection?','type'=>'radio','options'=>['Yes','No']],
                ['key'=>'infection_detail','label'=>'If yes, describe (osteo, MRSA, etc.) + acute/chronic + plan','type'=>'textarea','showIf'=>['key'=>'infection_signs','eq'=>'Yes']],
                ['key'=>'pain_scale','label'=>'Pain scale (0-10)','type'=>'number'],
                ['key'=>'icd10_primary','label'=>'ICD-10 primary','type'=>'text'],
                ['key'=>'icd10_secondary','label'=>'ICD-10 secondary','type'=>'text'],
                ['key'=>'progress_sa','label'=>'% change in surface area (30d)','type'=>'text'],
            ],
        ],

        // ---- Post-debridement (per wound) ----------------------------------
        [
            'key' => 'wound_post', 'title' => 'Post-Debridement / Treatment', 'repeat' => 'wound',
            'fields' => [
                ['key'=>'debridement_type','label'=>'Debridement type','type'=>'select','options'=>['None','Autolytic','Enzymatic','Mechanical','Surgical','Sharp','Non-Contact MIST','Other']],
                ['key'=>'post_length_cm','label'=>'Post-debridement length (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'post_width_cm','label'=>'Post-debridement width (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'post_depth_cm','label'=>'Post-debridement depth (cm)','type'=>'number','unit'=>'cm'],
                ['key'=>'primary_dressing','label'=>'Primary dressing(s)','type'=>'text'],
                ['key'=>'secondary_dressing','label'=>'Secondary dressing(s)','type'=>'text'],
                ['key'=>'skinsub_product','label'=>'Skin substitute — product name & size','type'=>'text'],
                ['key'=>'skinsub_hcpcs','label'=>'Skin substitute — HCPCS','type'=>'text'],
                ['key'=>'skinsub_used','label'=>'Amount used (cm)','type'=>'text'],
                ['key'=>'skinsub_wasted','label'=>'Amount wasted (cm)','type'=>'text'],
                ['key'=>'skinsub_tissue_id','label'=>'Tissue ID','type'=>'text'],
                ['key'=>'skinsub_exp','label'=>'Expiration date','type'=>'text'],
            ],
        ],

        // ---- Treatment plan -------------------------------------------------
        [
            'key' => 'treatment_plan', 'title' => 'Treatment Plan',
            'fields' => [
                ['key'=>'tp_goals','label'=>'Treatment plan goals','type'=>'textarea'],
                ['key'=>'tp_dressing_protocol','label'=>'Dressing change protocol','type'=>'textarea'],
                ['key'=>'tp_advanced_modalities','label'=>'Advanced modalities','type'=>'textarea'],
                ['key'=>'tp_referrals','label'=>'Specialist referrals','type'=>'textarea'],
                ['key'=>'tp_medications','label'=>'Medications','type'=>'textarea'],
                ['key'=>'tp_additional','label'=>'Additional info','type'=>'textarea'],
                ['key'=>'medical_necessity','label'=>'Medical necessity statement','type'=>'textarea',
                 'help'=>'e.g. "The patient has undergone greater than 4 weeks of conservative wound care without resolution…"'],
            ],
        ],
    ];
}
