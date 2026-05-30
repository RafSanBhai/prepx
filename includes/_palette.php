<?php
// includes/_palette.php — reusable question palette grid
// $questions, $saved, $totalQ must be in scope
?>
<div class="q-palette">
  <div class="q-palette__title">Question Palette</div>
  <div style="display:flex;gap:.5rem;font-size:.7rem;margin-bottom:.6rem;flex-wrap:wrap;">
    <span style="display:flex;align-items:center;gap:.25rem;">
      <span style="width:12px;height:12px;background:var(--navy);border-radius:3px;display:inline-block;"></span> Answered
    </span>
    <span style="display:flex;align-items:center;gap:.25rem;">
      <span style="width:12px;height:12px;background:#fff;border:2px solid var(--border);border-radius:3px;display:inline-block;"></span> Unanswered
    </span>
  </div>
  <div class="q-grid">
    <?php foreach($questions as $idx=>$q):
      $isAnswered=!empty($saved[$q['id']]);
    ?>
    <button type="button"
      class="q-btn <?=$isAnswered?'answered':''?>"
      data-qnum="<?=$idx+1?>"
      onclick="document.getElementById('q-<?=$idx+1?>').scrollIntoView({behavior:'smooth',block:'center'});document.getElementById('paletteDrawer')&&document.getElementById('paletteDrawer').classList.remove('open');">
      <?=$idx+1?>
    </button>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:.8rem;padding-top:.6rem;border-top:1px solid var(--border);font-size:.78rem;color:var(--text-muted);">
    Answered: <strong id="palAns"><?=count(array_filter($saved))?></strong> / <?=$totalQ?>
  </div>
</div>
<div style="background:#fff;border-radius:var(--radius);padding:.9rem;box-shadow:var(--shadow);margin-top:.8rem;font-size:.8rem;line-height:1.8;">
  <strong style="color:var(--navy);display:block;margin-bottom:.3rem;">Marking Scheme</strong>
  ✔ Correct: <strong style="color:var(--success);">+<?=$exam['mark_per_q']??1?></strong><br>
  ✘ Wrong: <strong style="color:var(--danger);">−<?=$exam['negative_marking']>0?$exam['negative_marking']:'0'?></strong><br>
  — Skipped: <strong>0</strong>
</div>
