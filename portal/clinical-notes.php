<?php
/**
 * Clinical Documentation page — included in portal/index.php when page=clinical-notes.
 * Guided wound-care notes built from the Dictation Guide schema, with a deterministic
 * documentation-gap panel from the New Order Review checklist. Scope: $pdo, $user set
 * by portal/index.php. Data ops post to /api/portal/clinical-note.save.php.
 */
require_once __DIR__ . '/../api/lib/clinical_note.php';
require_once __DIR__ . '/../api/lib/features.php';   // portal_brand_labels()

$cnUserId = $user['id'] ?? ($_SESSION['user_id'] ?? '');
$cnBrands = entitled_note_brands($user);   // which brands' templates this account may use
// Scope the picker to the brand from the nav (?brand=), when entitled; else show all entitled.
$cnActiveBrand = trim((string)($_GET['brand'] ?? ''));
$cnPickerBrands = ($cnActiveBrand !== '' && in_array($cnActiveBrand, $cnBrands, true)) ? [$cnActiveBrand] : $cnBrands;
$cnTplKey = trim((string)($_GET['template_key'] ?? 'wound_care_dictation'));

$cnViewId = trim((string)($_GET['note'] ?? ''));
$cnNew    = !empty($_GET['new']);
$cnPatientId = trim((string)($_GET['patient_id'] ?? ''));
$cnWounds = max(1, min(10, (int)($_GET['wounds'] ?? 1)));

// Load a note being viewed/edited
$cnNote = null; $cnData = ['f'=>[], 'w'=>[]]; $cnPatient = null;
if ($cnViewId !== '') {
  $st = $pdo->prepare("SELECT cn.*, p.first_name, p.last_name, p.dob
                       FROM clinical_notes cn JOIN patients p ON p.id = cn.patient_id
                       WHERE cn.id = ? AND cn.user_id = ?");
  $st->execute([$cnViewId, $cnUserId]);
  $cnNote = $st->fetch(PDO::FETCH_ASSOC);
  if ($cnNote) {
    $cnData = json_decode((string)$cnNote['structured'], true) ?: ['f'=>[], 'w'=>[]];
    $cnPatient = ['first_name'=>$cnNote['first_name'],'last_name'=>$cnNote['last_name'],'dob'=>$cnNote['dob']];
    $cnPatientId = $cnNote['patient_id'];
    $cnWounds = cn_wound_count($cnData);
    $cnTplKey = $cnNote['template_key'] ?: $cnTplKey;
  }
}
$cnTemplate = clinical_note_template($cnTplKey) ?: clinical_note_template('wound_care_dictation');

// Patient for a brand-new note
if (!$cnPatient && $cnPatientId !== '') {
  $ps = $pdo->prepare("SELECT id, first_name, last_name, dob FROM patients WHERE id = ? AND user_id = ?");
  $ps->execute([$cnPatientId, $cnUserId]);
  $cnPatient = $ps->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Patients + recent notes for the list view
$cnPatients = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE user_id = ? ORDER BY last_name, first_name LIMIT 500");
$cnPatients->execute([$cnUserId]); $cnPatients = $cnPatients->fetchAll(PDO::FETCH_ASSOC);
$cnRecent = $pdo->prepare("SELECT cn.id, cn.status, cn.updated_at, p.first_name, p.last_name
                           FROM clinical_notes cn JOIN patients p ON p.id = cn.patient_id
                           WHERE cn.user_id = ? ORDER BY cn.updated_at DESC LIMIT 50");
$cnRecent->execute([$cnUserId]); $cnRecent = $cnRecent->fetchAll(PDO::FETCH_ASSOC);

$cnEditing = ($cnNew && $cnPatient) || (!empty($_GET['edit']) && $cnNote);
?>
<style>
  .cn-wrap{max-width:1000px}
  .cn-section{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:14px}
  .cn-section h3{font-size:14px;font-weight:700;color:#20419b;margin:0 0 10px;text-transform:uppercase;letter-spacing:.03em}
  .cn-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px}
  .cn-field{display:flex;flex-direction:column;font-size:13px}
  .cn-field label{color:#475569;font-weight:600;margin-bottom:3px}
  .cn-field input,.cn-field select,.cn-field textarea{border:1px solid #cbd5e1;border-radius:6px;padding:6px 8px;font-size:13px}
  .cn-opts{display:flex;gap:14px;flex-wrap:wrap;padding-top:4px}
  .cn-opt{font-weight:500;color:#334155}
  .cn-help{color:#94a3b8;font-size:11px;margin-top:3px}
  .cn-note h3{font-size:13px;color:#20419b;border-bottom:1px solid #e5e7eb;padding-bottom:3px;margin:14px 0 6px;text-transform:uppercase}
  .cn-row{font-size:13px;padding:2px 0}.cn-row .cn-k{color:#64748b;font-weight:600}
  .cn-gaps{border-radius:10px;padding:14px 16px;margin-bottom:14px;border:1px solid}
  .cn-gap{font-size:13px;padding:4px 0;display:flex;gap:8px;align-items:flex-start}
  .cn-badge{font-size:10px;font-weight:700;text-transform:uppercase;border-radius:4px;padding:1px 6px;white-space:nowrap}
  .cn-high{background:#fee2e2;color:#b91c1c}.cn-mod{background:#fef3c7;color:#92400e}.cn-low{background:#e0f2fe;color:#075985}
  .cn-btn{background:#0075bc;color:#fff;border:none;border-radius:6px;padding:8px 14px;font-size:13px;cursor:pointer}
  .cn-btn.secondary{background:#fff;color:#0075bc;border:1px solid #0075bc}
  @media(max-width:700px){.cn-grid{grid-template-columns:1fr}}
</style>

<div class="page-header">
  <h1>Clinical Documentation</h1>
  <p style="color:#64748b;margin-top:.4rem">Guided wound-care notes with real-time documentation-gap checks (Medicare LCD / Standards of Care).</p>
</div>

<div class="cn-wrap">
<?php if ($cnEditing): /* ---------------- CREATE / EDIT FORM ---------------- */ ?>
  <?php
    $formData = $cnNote ? $cnData : ['f'=>[], 'w'=>[]];
    $formWounds = $cnWounds;
    $pName = trim(($cnPatient['first_name'] ?? '').' '.($cnPatient['last_name'] ?? ''));
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <div><strong><?=htmlspecialchars($cnNote ? 'Edit note' : 'New note')?></strong> — <?=htmlspecialchars($pName)?>
      <span class="cn-help" style="display:inline"> · <?=htmlspecialchars($cnTemplate['label'] ?? '')?></span></div>
    <a class="cn-btn secondary" href="?page=clinical-notes" style="text-decoration:none">Cancel</a>
  </div>

  <?php if (!$cnNote): ?>
  <div class="cn-section" style="display:flex;gap:14px;align-items:center">
    <label style="font-size:13px;color:#475569;font-weight:600">Number of wounds:</label>
    <?php for ($n=1;$n<=6;$n++): ?>
      <a href="?page=clinical-notes&new=1&patient_id=<?=urlencode($cnPatientId)?>&template_key=<?=urlencode($cnTplKey)?>&wounds=<?=$n?>"
         style="text-decoration:none;font-weight:<?=$n===$formWounds?'700':'400'?>;color:<?=$n===$formWounds?'#0075bc':'#64748b'?>"><?=$n?></a>
    <?php endfor; ?>
    <span class="cn-help">Set this first — changing it reloads the form.</span>
  </div>
  <?php endif; ?>

  <form method="post" action="/api/portal/clinical-note.save.php">
    <input type="hidden" name="patient_id" value="<?=htmlspecialchars($cnPatientId)?>">
    <input type="hidden" name="template_key" value="<?=htmlspecialchars($cnTplKey)?>">
    <?php if ($cnNote): ?><input type="hidden" name="note_id" value="<?=htmlspecialchars($cnNote['id'])?>"><?php endif; ?>
    <?= cn_render_form($cnTemplate, $formData, $formWounds) ?>
    <div style="display:flex;gap:10px;margin:6px 0 30px">
      <button class="cn-btn" type="submit" name="status" value="draft">Save draft</button>
      <button class="cn-btn" type="submit" name="status" value="final">Finalize note</button>
    </div>
  </form>
  <script>
  (function(){
    // Collapse showIf-dependent fields until their controlling value matches.
    function sync(){
      document.querySelectorAll('.cn-field[data-showif-name]').forEach(function(el){
        var nm=el.getAttribute('data-showif-name'), want=el.getAttribute('data-showif-eq');
        var ctrl=document.querySelector('[name="'+CSS.escape(nm)+'"]:checked') || document.querySelector('[name="'+CSS.escape(nm)+'"]');
        var val=ctrl?ctrl.value:'';
        el.style.display=(val===want)?'':'none';
      });
    }
    document.addEventListener('change',sync); sync();
  })();
  </script>

<?php elseif ($cnNote): /* ---------------- VIEW A NOTE ---------------- */ ?>
  <?php
    $gaps = cn_gap_check($cnTemplate, $cnData, cn_wound_count($cnData));
    $counts = ['high'=>0,'moderate'=>0,'low'=>0];
    foreach ($gaps as $g) { $counts[$g['level']]=($counts[$g['level']]??0)+1; }
    $panelBorder = $counts['high']?'#fecaca':($counts['moderate']?'#fde68a':'#bae6fd');
  ?>
  <?php if (!empty($_GET['saved'])): ?><div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:8px 12px;border-radius:8px;margin-bottom:12px;font-size:13px">Note saved.</div><?php endif; ?>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <div><strong><?=htmlspecialchars(trim(($cnNote['first_name']??'').' '.($cnNote['last_name']??'')))?></strong>
      <span class="cn-badge <?=$cnNote['status']==='final'?'cn-low':'cn-mod'?>" style="margin-left:8px"><?=htmlspecialchars($cnNote['status'])?></span></div>
    <div style="display:flex;gap:8px">
      <a class="cn-btn secondary" href="?page=clinical-notes&note=<?=urlencode($cnNote['id'])?>&edit=1" style="text-decoration:none">Edit</a>
      <button class="cn-btn secondary" type="button" onclick="cnPrint()">Print</button>
      <a class="cn-btn secondary" href="?page=clinical-notes" style="text-decoration:none">All notes</a>
    </div>
  </div>

  <div class="cn-gaps" style="border-color:<?=$panelBorder?>;background:#fff">
    <div style="font-weight:700;font-size:13px;margin-bottom:6px">Documentation Gap Check
      <span style="font-weight:400;color:#64748b">— <?=$counts['high']?>high · <?=$counts['moderate']?>moderate · <?=$counts['low']?>low</span></div>
    <?php if (!$gaps): ?>
      <div style="font-size:13px;color:#065f46">No documentation gaps detected. 🎉</div>
    <?php else: foreach ($gaps as $g):
      $cls = $g['level']==='high'?'cn-high':($g['level']==='moderate'?'cn-mod':'cn-low'); ?>
      <div class="cn-gap"><span class="cn-badge <?=$cls?>"><?=htmlspecialchars($g['level'])?></span><span><?=htmlspecialchars($g['message'])?></span></div>
    <?php endforeach; endif; ?>
  </div>

  <div class="cn-section" id="cn-note-print"><?= $cnNote['body'] ?: '<em>Empty note.</em>' ?></div>
  <script>
  function cnPrint(){
    var w=window.open('','_blank');
    w.document.write('<html><head><title>Clinical Note</title><style>body{font-family:Arial,sans-serif;font-size:13px;color:#111;padding:24px}h2{font-size:18px}h3{font-size:13px;color:#20419b;border-bottom:1px solid #ddd;text-transform:uppercase}.cn-row{padding:2px 0}.cn-k{color:#555;font-weight:600}</style></head><body>'+document.getElementById('cn-note-print').innerHTML+'</body></html>');
    w.document.close(); w.focus(); w.print();
  }
  </script>

<?php else: /* ---------------- LIST ---------------- */ ?>
  <div class="cn-section">
    <div style="font-weight:700;font-size:13px;margin-bottom:8px">Start a new clinical note</div>
    <?php if (!$cnPatients): ?>
      <div style="font-size:13px;color:#64748b">Add a patient first, then start a note.</div>
    <?php else: ?>
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="page" value="clinical-notes">
      <input type="hidden" name="new" value="1">
      <input type="text" id="cn-patient-search" placeholder="Search patient…" autocomplete="off"
             oninput="cnFilterPatients(this.value)" style="border:1px solid #cbd5e1;border-radius:6px;padding:7px 9px;font-size:13px;min-width:200px">
      <select name="patient_id" id="cn-patient-select" required size="1" style="border:1px solid #cbd5e1;border-radius:6px;padding:7px 9px;font-size:13px;min-width:240px">
        <option value="">Select patient…</option>
        <?php foreach ($cnPatients as $p): ?>
          <option value="<?=htmlspecialchars($p['id'])?>"><?=htmlspecialchars(trim($p['last_name'].', '.$p['first_name']))?></option>
        <?php endforeach; ?>
      </select>
      <select name="template_key" required style="border:1px solid #cbd5e1;border-radius:6px;padding:7px 9px;font-size:13px;min-width:260px">
        <?php foreach (clinical_note_templates_by_brand() as $brand => $tpls):
          if (!in_array($brand, $cnPickerBrands, true)) continue;   // brand from the nav (or all entitled)
          $blabel = portal_brand_labels()[$brand] ?? strtoupper((string)$brand); ?>
          <optgroup label="<?=htmlspecialchars($blabel)?>">
            <?php foreach ($tpls as $tk => $t): ?>
              <option value="<?=htmlspecialchars($tk)?>"><?=htmlspecialchars($t['label'])?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <button class="cn-btn" type="submit">Start note</button>
    </form>
    <script>
      function cnFilterPatients(q){
        q=(q||'').toLowerCase();
        var sel=document.getElementById('cn-patient-select'), first=null;
        for(var i=0;i<sel.options.length;i++){
          var o=sel.options[i];
          if(!o.value){continue;}
          var match=o.text.toLowerCase().indexOf(q)>=0;
          o.hidden=!match;
          if(match&&!first)first=o;
        }
        sel.value=first?first.value:'';
      }
    </script>
    <?php endif; ?>
  </div>

  <div class="cn-section">
    <div style="font-weight:700;font-size:13px;margin-bottom:8px">Recent notes</div>
    <?php if (!$cnRecent): ?>
      <div style="font-size:13px;color:#64748b">No notes yet.</div>
    <?php else: ?>
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <?php foreach ($cnRecent as $r): ?>
          <tr style="border-bottom:1px solid #eef2f7">
            <td style="padding:7px 4px"><?=htmlspecialchars(trim(($r['last_name']??'').', '.($r['first_name']??'')))?></td>
            <td style="padding:7px 4px"><span class="cn-badge <?=$r['status']==='final'?'cn-low':'cn-mod'?>"><?=htmlspecialchars($r['status'])?></span></td>
            <td style="padding:7px 4px;color:#94a3b8"><?=htmlspecialchars(date('M j, Y', strtotime((string)$r['updated_at'])))?></td>
            <td style="padding:7px 4px;text-align:right"><a href="?page=clinical-notes&note=<?=urlencode($r['id'])?>" style="color:#0075bc">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
