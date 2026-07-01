<?php
/**
 * Guided clinical-note engine. Renders the capture form and the finished note from
 * the declarative template (api/lib/clinical_note_template.php), and runs deterministic
 * documentation-gap checks derived from Randy's "New Order Review" checklist.
 *
 * Captured data shape (stored in clinical_notes.structured JSONB):
 *   { "f": { <key>: <value> }, "w": [ { <key>: <value> }, ... ] }
 *   f = flat (once-per-encounter) fields, w = per-wound fields.
 */

require_once __DIR__ . '/clinical_note_template.php';

function cn_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

/** Number of wounds captured (defaults to 1). */
function cn_wound_count(array $data): int {
    $n = count($data['w'] ?? []);
    return max(1, $n);
}

/* ------------------------------------------------------------------ FORM --- */

function cn_render_form(array $template, array $data, int $woundCount): string {
    $f = $data['f'] ?? [];
    $w = $data['w'] ?? [];
    ob_start();
    foreach ($template['sections'] as $sec) {
        $repeat = ($sec['repeat'] ?? '') === 'wound';
        if ($repeat) {
            for ($i = 0; $i < $woundCount; $i++) {
                echo '<div class="cn-section"><h3>' . cn_h($sec['title']) . ' — Wound #' . ($i + 1) . '</h3><div class="cn-grid">';
                foreach ($sec['fields'] as $fld) {
                    echo cn_field($fld, $w[$i][$fld['key']] ?? '', "w[$i][{$fld['key']}]", $i);
                }
                echo '</div></div>';
            }
        } else {
            echo '<div class="cn-section"><h3>' . cn_h($sec['title']) . '</h3><div class="cn-grid">';
            foreach ($sec['fields'] as $fld) {
                echo cn_field($fld, $f[$fld['key']] ?? '', "f[{$fld['key']}]", null);
            }
            echo '</div></div>';
        }
    }
    return ob_get_clean();
}

function cn_field(array $fld, $value, string $name, ?int $woundIdx): string {
    $id = 'cnf_' . preg_replace('/[^a-z0-9]+/i', '_', $name);
    $type = $fld['type'] ?? 'text';
    // showIf lets the page's JS collapse dependent fields; scope to the same wound for repeats.
    $showif = '';
    if (!empty($fld['showIf'])) {
        $depKey = $fld['showIf']['key'];
        $depName = $woundIdx === null ? "f[$depKey]" : "w[$woundIdx][$depKey]";
        $showif = ' data-showif-name="' . cn_h($depName) . '" data-showif-eq="' . cn_h($fld['showIf']['eq']) . '"';
    }
    ob_start();
    echo '<div class="cn-field"' . $showif . '>';
    echo '<label for="' . cn_h($id) . '">' . cn_h($fld['label']) . (!empty($fld['unit']) ? ' (' . cn_h($fld['unit']) . ')' : '') . '</label>';
    switch ($type) {
        case 'textarea':
            echo '<textarea id="' . cn_h($id) . '" name="' . cn_h($name) . '" rows="2">' . cn_h($value) . '</textarea>';
            break;
        case 'radio':
            echo '<div class="cn-opts">';
            foreach ($fld['options'] as $opt) {
                $ck = ((string)$value === (string)$opt) ? ' checked' : '';
                echo '<label class="cn-opt"><input type="radio" name="' . cn_h($name) . '" value="' . cn_h($opt) . '"' . $ck . '> ' . cn_h($opt) . '</label>';
            }
            echo '</div>';
            break;
        case 'select':
            echo '<select id="' . cn_h($id) . '" name="' . cn_h($name) . '"><option value="">—</option>';
            foreach ($fld['options'] as $opt) {
                $sel = ((string)$value === (string)$opt) ? ' selected' : '';
                echo '<option value="' . cn_h($opt) . '"' . $sel . '>' . cn_h($opt) . '</option>';
            }
            echo '</select>';
            break;
        case 'number':
            echo '<input id="' . cn_h($id) . '" type="number" step="any" name="' . cn_h($name) . '" value="' . cn_h($value) . '">';
            break;
        case 'date':
            echo '<input id="' . cn_h($id) . '" type="date" name="' . cn_h($name) . '" value="' . cn_h($value) . '">';
            break;
        default:
            echo '<input id="' . cn_h($id) . '" type="text" name="' . cn_h($name) . '" value="' . cn_h($value) . '"' . (!empty($fld['placeholder']) ? ' placeholder="' . cn_h($fld['placeholder']) . '"' : '') . '>';
    }
    if (!empty($fld['help'])) echo '<div class="cn-help">' . cn_h($fld['help']) . '</div>';
    echo '</div>';
    return ob_get_clean();
}

/* --------------------------------------------------------------- RENDER ---- */

/** Render the finished clinical note (HTML) from captured data. */
function cn_render_note(array $template, array $data, array $patient, int $woundCount): string {
    $f = $data['f'] ?? [];
    $w = $data['w'] ?? [];
    ob_start();
    $pname = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
    echo '<div class="cn-note">';
    echo '<h2>Wound Care Encounter Note</h2>';
    echo '<p><strong>Patient:</strong> ' . cn_h($pname ?: '—')
        . ' &nbsp;|&nbsp; <strong>DOB:</strong> ' . cn_h($patient['dob'] ?? '—')
        . ' &nbsp;|&nbsp; <strong>Date of Service:</strong> ' . cn_h($f['date_of_service'] ?? '—') . '</p>';

    foreach ($template['sections'] as $sec) {
        $repeat = ($sec['repeat'] ?? '') === 'wound';
        if ($repeat) {
            for ($i = 0; $i < $woundCount; $i++) {
                $rows = cn_section_rows($sec['fields'], $w[$i] ?? []);
                if (!$rows) continue;
                echo '<h3>' . cn_h($sec['title']) . ' — Wound #' . ($i + 1) . '</h3>' . $rows;
            }
        } else {
            if (($sec['key'] ?? '') === 'encounter') continue; // shown in the header line
            $rows = cn_section_rows($sec['fields'], $f);
            if (!$rows) continue;
            echo '<h3>' . cn_h($sec['title']) . '</h3>' . $rows;
        }
    }
    echo '</div>';
    return ob_get_clean();
}

function cn_section_rows(array $fields, array $vals): string {
    $out = '';
    foreach ($fields as $fld) {
        $v = $vals[$fld['key']] ?? '';
        if ($v === '' || $v === null) continue;
        $unit = !empty($fld['unit']) ? ' ' . $fld['unit'] : '';
        $out .= '<div class="cn-row"><span class="cn-k">' . cn_h($fld['label']) . ':</span> ' . cn_h($v) . cn_h($unit) . '</div>';
    }
    return $out;
}

/* ------------------------------------------------------------- GAP CHECK --- */

/**
 * Deterministic documentation-gap findings. Routes to the rule set for the note's
 * template. Returns [ ['level'=>'high|moderate|low','message'=>'...'], ... ].
 * (Phase 2 will add AI-assisted checks against uploaded free-text notes.)
 */
function cn_gap_check(array $template, array $data, int $woundCount): array {
    switch ($template['ruleset'] ?? 'iwc_skinsub') {
        case 'md_surgical': return cn_gaps_md_surgical($data, $woundCount);
        case 'md_arobella': return cn_gaps_md_arobella($data, $woundCount);
        default:            return cn_gaps_iwc_skinsub($data, $woundCount);
    }
}

/** IWC skin-substitute / PRP gap rules (from Randy's New Order Review checklist). */
function cn_gaps_iwc_skinsub(array $data, int $woundCount): array {
    $f = $data['f'] ?? [];
    $w = $data['w'] ?? [];
    $out = [];
    $add = function ($level, $msg) use (&$out) { $out[] = ['level' => $level, 'message' => $msg]; };

    // 30-day conservative care attestation
    if (trim((string)($f['past_treatments'] ?? '')) === '') {
        $add('high', 'Missing 30-day conservative care attestation — document a defined ≥30-day treatment period (start/end dates) with interventions and % change over 30 days.');
    }
    // Diabetes: A1c present when diabetic
    if (($f['dm_present'] ?? '') === 'Yes' && trim((string)($f['dm_a1c'] ?? '')) === '') {
        $add('high', 'Diabetic patient — A1c not documented (upload or note the value).');
    }
    // Labs
    if (($f['labs_ordered'] ?? '') !== 'Yes') {
        $add('moderate', 'Current labs (CBC w/diff w/PLT, CMP) not documented as ordered.');
    }
    // Nutrition
    if (($f['nutrition_adequate'] ?? '') === '') {
        $add('moderate', 'Nutrition / malnutrition status not addressed.');
    }
    // Tobacco cessation
    if (($f['smoker'] ?? '') === 'Yes' && ($f['smoking_counseled'] ?? '') !== 'Yes') {
        $add('moderate', 'Tobacco user — cessation counseling/plan not documented.');
    }

    // Per-wound checks
    for ($i = 0; $i < $woundCount; $i++) {
        $wd = $w[$i] ?? [];
        $n = 'Wound #' . ($i + 1) . ': ';
        // Vascular studies for lower-extremity / venous / arterial etiologies
        $type = $wd['type'] ?? '';
        if (in_array($type, ['Venous', 'Arterial'], true)
            && trim((string)($wd['type'] === 'Venous' ? ($f['vasc_abi'] ?? '') : ($f['vasc_abi'] ?? ''))) === ''
            && trim((string)($f['vasc_doppler'] ?? '')) === '' && trim((string)($f['vasc_duplex'] ?? '')) === '') {
            $add('high', $n . 'Objective vascular assessment (ABI / Doppler / duplex) with results not documented.');
        }
        // Wound onset / duration
        if (trim((string)($wd['duration_years'] ?? '')) === '' && trim((string)($wd['duration_months'] ?? '')) === '') {
            $add('high', $n . 'Wound onset / duration not documented (needed to establish chronicity).');
        }
        // Complete measurements
        if (trim((string)($wd['length_cm'] ?? '')) === '' || trim((string)($wd['width_cm'] ?? '')) === '' || trim((string)($wd['depth_cm'] ?? '')) === '') {
            $add('moderate', $n . 'Incomplete wound measurements (need L × W × D).');
        }
        // Offloading for pressure wounds
        if ($type === 'Pressure' && ($f['offloading_done'] ?? '') !== 'Yes') {
            $add('moderate', $n . 'Pressure wound — offloading measures/compliance not documented.');
        }
        // Exposed structures (graft readiness / severity)
        $vs = $wd['visible_structures'] ?? '';
        if ($vs !== '' && $vs !== 'None') {
            $add('low', $n . 'Exposed ' . strtolower($vs) . ' noted — confirm this is addressed per product/LCD graft-readiness criteria.');
        }
        // Infection status
        if (($wd['infection_signs'] ?? '') === 'Yes') {
            $add('low', $n . 'Signs of infection present — document management; wound bed should be free of active infection prior to graft application.');
        }
    }
    return $out;
}

/** MD DME surgical-dressing (LCD L33831) gap rules. */
function cn_gaps_md_surgical(array $data, int $woundCount): array {
    $f = $data['f'] ?? [];
    $w = $data['w'] ?? [];
    $out = [];
    $add = function ($level, $msg) use (&$out) { $out[] = ['level' => $level, 'message' => $msg]; };

    // Coverage basis: surgical dressings require a debrided / surgically-created wound
    if (trim((string)($f['qualifying_type'] ?? '')) === '') {
        $add('high', 'Qualifying event not documented — surgical dressings are covered only for a debrided or surgically-created wound.');
    }
    for ($i = 0; $i < $woundCount; $i++) {
        $wd = $w[$i] ?? [];
        $n = 'Wound #' . ($i + 1) . ': ';
        if (trim((string)($wd['icd10_primary'] ?? '')) === '') {
            $add('high', $n . 'Wound diagnosis (ICD-10) not documented.');
        }
        if (trim((string)($wd['length_cm'] ?? '')) === '' || trim((string)($wd['width_cm'] ?? '')) === '' || trim((string)($wd['depth_cm'] ?? '')) === '') {
            $add('moderate', $n . 'Incomplete wound measurements — size drives covered dressing size and quantity.');
        }
        if (trim((string)($wd['exudate_amount'] ?? '')) === '') {
            $add('moderate', $n . 'Exudate amount not documented — justifies dressing selection and change frequency.');
        }
    }
    // Dressing order specifics
    for ($i = 0; $i < $woundCount; $i++) {
        $wd = $w[$i] ?? [];
        $n = 'Wound #' . ($i + 1) . ': ';
        if (trim((string)($wd['primary_hcpcs'] ?? '')) === '') {
            $add('moderate', $n . 'Primary dressing HCPCS not documented.');
        }
        if (trim((string)($wd['qty_per_change'] ?? '')) === '' || trim((string)($wd['changes_per_week'] ?? '')) === '') {
            $add('moderate', $n . 'Dressing quantity per change and/or change frequency not documented.');
        }
    }
    if (trim((string)($f['next_eval_date'] ?? '')) === '') {
        $add('low', 'No re-evaluation date documented — continued coverage requires periodic re-evaluation of need.');
    }
    return $out;
}

/** MD DME Arobella / ultrasound-debridement (CPT 97610) gap rules. */
function cn_gaps_md_arobella(array $data, int $woundCount): array {
    $f = $data['f'] ?? [];
    $w = $data['w'] ?? [];
    $out = [];
    $add = function ($level, $msg) use (&$out) { $out[] = ['level' => $level, 'message' => $msg]; };

    for ($i = 0; $i < $woundCount; $i++) {
        $wd = $w[$i] ?? [];
        $n = 'Wound #' . ($i + 1) . ': ';
        if (trim((string)($wd['indication'] ?? '')) === '') {
            $add('high', $n . 'Indication / rationale for ultrasound debridement (97610) not documented.');
        }
        if (($wd['tissue_type'] ?? '') !== 'Yes') {
            $add('moderate', $n . 'Devitalized tissue (slough/eschar/fibrin) not documented — supports the debridement indication.');
        }
        if (trim((string)($wd['length_cm'] ?? '')) === '' || trim((string)($wd['width_cm'] ?? '')) === '' || trim((string)($wd['depth_cm'] ?? '')) === '') {
            $add('moderate', $n . 'Incomplete wound measurements (need L × W × D for progress tracking).');
        }
    }
    if (trim((string)($f['response'] ?? '')) === '') {
        $add('moderate', 'Response / measurable progress since prior treatment not documented — required to support continued treatments.');
    }
    if (trim((string)($f['medical_necessity'] ?? '')) === '') {
        $add('low', 'Medical-necessity statement not documented.');
    }
    return $out;
}
