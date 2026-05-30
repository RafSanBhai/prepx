<?php
require_once __DIR__.'/config.php';
requireRole('student');
$pdo=db(); $user=currentUser();
$examId=(int)($_GET['exam']??0);
if (!$examId) redirect('/student/exams.php');

// Load exam
$st=$pdo->prepare("SELECT e.*,c.name AS cat_name FROM exams e JOIN categories c ON c.id=e.category_id WHERE e.id=? AND e.status='published' LIMIT 1");
$st->execute([$examId]); $exam=$st->fetch();
if (!$exam) { setFlash('error','Exam not available.'); redirect('/student/exams.php'); }
if ($exam['type']==='premium' && !hasCategoryAccess((int)$exam['category_id'])) {
    setFlash('error','Premium subscription required.'); redirect('/student/profile.php');
}

$qSt=$pdo->prepare('SELECT * FROM questions WHERE exam_id=? ORDER BY q_order ASC,id ASC');
$qSt->execute([$examId]); $questions=$qSt->fetchAll();
if (!$questions) { setFlash('error','No questions in this exam.'); redirect('/student/exams.php'); }
$totalQ=count($questions);

// Handle retake
if (isset($_GET['retake'])) {
    $pdo->prepare('DELETE FROM exam_attempts WHERE user_id=? AND exam_id=? AND status="in_progress"')->execute([$user['id'],$examId]);
}

// Resume or new attempt
$ea=$pdo->prepare('SELECT * FROM exam_attempts WHERE user_id=? AND exam_id=? AND status="in_progress" ORDER BY started_at DESC LIMIT 1');
$ea->execute([$user['id'],$examId]); $attempt=$ea->fetch();

if (!$attempt) {
    // Check already completed
    $done=$pdo->prepare('SELECT id FROM exam_attempts WHERE user_id=? AND exam_id=? AND status="completed" ORDER BY submitted_at DESC LIMIT 1');
    $done->execute([$user['id'],$examId]); $doneId=$done->fetchColumn();
    if ($doneId && !isset($_GET['retake'])) { redirect("/result.php?attempt=$doneId"); }
    $ins=$pdo->prepare('INSERT INTO exam_attempts(user_id,exam_id,started_at,total_marks,status) VALUES(?,?,NOW(),?,"in_progress")');
    $ins->execute([$user['id'],$examId,$exam['total_marks']]); $attemptId=$pdo->lastInsertId();
    $bi=$pdo->prepare('INSERT INTO student_answers(attempt_id,question_id) VALUES(?,?)');
    foreach($questions as $q) $bi->execute([$attemptId,$q['id']]);
} else { $attemptId=$attempt['id']; }

// Submit
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $score=$correct=$wrong=$skipped=0;
    foreach($questions as $q) {
        $chosen=isset($_POST['q_'.$q['id']])?strtoupper(trim($_POST['q_'.$q['id']])):null;
        if (!in_array($chosen,['A','B','C','D'])) $chosen=null;
        $isC=0;
        if ($chosen===null) $skipped++;
        elseif ($chosen===$q['correct_option']) { $isC=1; $correct++; $score+=(float)$q['marks']; }
        else { $wrong++; $score-=(float)$exam['negative_marking']; }
        $pdo->prepare('UPDATE student_answers SET chosen_option=?,is_correct=? WHERE attempt_id=? AND question_id=?')
            ->execute([$chosen,$isC,$attemptId,$q['id']]);
    }
    $score=max(0,$score);
    $pdo->prepare('UPDATE exam_attempts SET score=?,correct_count=?,wrong_count=?,skipped_count=?,status="completed",submitted_at=NOW() WHERE id=?')
        ->execute([$score,$correct,$wrong,$skipped,$attemptId]);
    redirect("/result.php?attempt=$attemptId");
}

$elapsed=$attempt?(time()-strtotime($attempt['started_at'])):0;
$remaining=max(0,$exam['duration_minutes']*60-$elapsed);
$saved=[];
$ss=$pdo->prepare('SELECT question_id,chosen_option FROM student_answers WHERE attempt_id=?');
$ss->execute([$attemptId]); foreach($ss->fetchAll() as $sa) $saved[$sa['question_id']]=$sa['chosen_option'];
$pageTitle=e($exam['title']);
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f1b2d">
<title><?=$pageTitle?> | PrepX</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
<style>
body{background:#f0f4f8;}
.exam-topbar{
  position:sticky;top:0;z-index:50;
  background:#fff;padding:.8rem 1rem;
  display:flex;justify-content:space-between;align-items:center;
  box-shadow:var(--shadow);flex-wrap:wrap;gap:.5rem;
}
.exam-topbar__left{display:flex;align-items:center;gap:.8rem;min-width:0;}
.exam-topbar__info{min-width:0;}
.exam-topbar__title{font-family:var(--font-serif);font-size:1rem;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.exam-topbar__meta{font-size:.72rem;color:var(--text-muted);}
.exam-topbar__right{display:flex;align-items:center;gap:.7rem;flex-shrink:0;}
.exam-wrap{max-width:1060px;margin:0 auto;padding:1rem;}
.qblock{background:#fff;border-radius:var(--radius-lg);margin-bottom:1rem;overflow:hidden;box-shadow:var(--shadow);}
.qblock-head{background:var(--navy);color:#fff;padding:.6rem 1rem;display:flex;justify-content:space-between;align-items:center;}
.qblock-num{font-weight:700;font-size:.88rem;}
.qblock-marks{background:var(--gold);color:var(--navy);padding:.1rem .5rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.qblock-body{padding:1.1rem 1.2rem;}
.qblock-text{font-size:.97rem;font-weight:500;line-height:1.7;margin-bottom:1rem;}
.opts{display:flex;flex-direction:column;gap:.5rem;}
.opt{display:flex;align-items:flex-start;gap:.7rem;padding:.7rem .9rem;border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:border-color .15s,background .15s;-webkit-tap-highlight-color:transparent;}
.opt:hover{border-color:var(--gold);background:var(--gold-pale);}
.opt.sel{border-color:var(--navy);background:#e8edf6;}
.opt input{position:absolute;opacity:0;pointer-events:none;}
.opt-key{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;background:var(--border);color:var(--text);transition:background .15s,color .15s;}
.opt.sel .opt-key{background:var(--navy);color:#fff;}
.opt-text{font-size:.9rem;line-height:1.5;}
/* Floating palette toggle on mobile */
.palette-fab{display:none;position:fixed;bottom:1.2rem;right:1.2rem;z-index:80;width:52px;height:52px;border-radius:50%;background:var(--navy);color:#fff;font-size:1.3rem;border:none;cursor:pointer;box-shadow:var(--shadow-lg);align-items:center;justify-content:center;}
.palette-drawer{display:none;position:fixed;bottom:0;left:0;right:0;z-index:90;background:#fff;border-radius:var(--radius-lg) var(--radius-lg) 0 0;box-shadow:0 -4px 30px rgba(0,0,0,.2);padding:1rem;max-height:60vh;overflow-y:auto;}
.palette-drawer.open{display:block;}
@media(max-width:900px){
  .exam-layout{flex-direction:column;}
  .exam-sidebar{display:none;}
  .palette-fab{display:flex;}
}
</style>
</head><body>

<div class="exam-topbar">
  <div class="exam-topbar__left">
    <a href="/student/exams.php" style="color:var(--text-muted);font-size:.82rem;white-space:nowrap;">← Back</a>
    <div class="exam-topbar__info">
      <div class="exam-topbar__title"><?=e($exam['title'])?></div>
      <div class="exam-topbar__meta"><?=e($exam['cat_name'])?> · <?=$totalQ?> Q · <?=$exam['total_marks']?> marks<?=$exam['negative_marking']>0?' · <span style="color:var(--danger);">−'.$exam['negative_marking'].'/wrong</span>':''?></div>
    </div>
  </div>
  <div class="exam-topbar__right">
    <div>
      <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);text-align:center;">Time Left</div>
      <div class="timer" id="examTimer" data-seconds="<?=$remaining?>"><?=gmdate('H:i:s',$remaining)?></div>
    </div>
    <button type="submit" form="examForm" class="btn btn--danger"
      onclick="return confirm('Submit exam now? This cannot be undone.')">✓ Finish</button>
  </div>
</div>

<div class="exam-wrap">

  <?php if($exam['instructions']): ?>
  <details style="margin-bottom:1rem;">
    <summary style="cursor:pointer;background:#fff;padding:.65rem 1rem;border-radius:var(--radius);box-shadow:var(--shadow);font-weight:500;font-size:.88rem;list-style:none;">📋 Instructions (tap to expand)</summary>
    <div style="background:#fff;padding:.9rem 1rem;border-radius:0 0 var(--radius) var(--radius);font-size:.88rem;color:var(--text-muted);line-height:1.7;"><?=nl2br(e($exam['instructions']))?></div>
  </details>
  <?php endif; ?>

  <!-- Progress -->
  <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;">
    <div class="progress-bar-wrap" style="flex:1;"><div class="progress-bar" id="progressBar" style="width:0%;"></div></div>
    <span id="progressText" style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">0 / <?=$totalQ?></span>
  </div>

  <div class="exam-layout" style="display:flex;gap:1.2rem;align-items:flex-start;">

    <!-- Questions -->
    <div class="exam-main" style="flex:1;min-width:0;">
      <form id="examForm" method="POST" action="/exam_view.php?exam=<?=$examId?>">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="attempt_id" value="<?=$attemptId?>">

        <?php foreach($questions as $idx=>$q):
          $savedChoice=$saved[$q['id']]??null;
        ?>
        <div class="qblock" id="q-<?=$idx+1?>" data-qnum="<?=$idx+1?>">
          <div class="qblock-head">
            <span class="qblock-num">Q<?=$idx+1?></span>
            <span class="qblock-marks"><?=$q['marks']?> mark<?=$q['marks']!=1?'s':''?></span>
          </div>
          <div class="qblock-body">
            <div class="qblock-text"><?=nl2br(e($q['question_text']))?></div>
            <div class="opts">
              <?php foreach(['A','B','C','D'] as $ltr):
                $optText=$q['option_'.strtolower($ltr)];
                $checked=$savedChoice===$ltr;
              ?>
              <label class="opt <?=$checked?'sel':''?>" data-qnum="<?=$idx+1?>">
                <input type="radio" name="q_<?=$q['id']?>" value="<?=$ltr?>" <?=$checked?'checked':''?>>
                <span class="opt-key"><?=$ltr?></span>
                <span class="opt-text"><?=e($optText)?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <div style="text-align:center;padding:1.5rem 0 2rem;">
          <button type="submit" class="btn btn--danger btn--lg"
            onclick="return confirm('Submit exam now? This cannot be undone.')">✓ Submit &amp; Finish Exam</button>
        </div>
      </form>
    </div>

    <!-- Desktop sidebar palette -->
    <div class="exam-sidebar" style="width:240px;flex-shrink:0;position:sticky;top:80px;">
      <?php include __DIR__.'/includes/_palette.php'; ?>
    </div>

  </div>
</div>

<!-- Mobile palette FAB -->
<button class="palette-fab" onclick="document.getElementById('paletteDrawer').classList.toggle('open')" aria-label="Question Palette">🗂</button>
<div class="palette-drawer" id="paletteDrawer">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;">
    <strong style="font-size:.88rem;">Question Palette</strong>
    <button onclick="document.getElementById('paletteDrawer').classList.remove('open')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;">✕</button>
  </div>
  <?php include __DIR__.'/includes/_palette.php'; ?>
</div>

<script src="/assets/js/main.js"></script>
<script>
document.querySelectorAll('.opt').forEach(lbl=>{
  lbl.addEventListener('click',function(){
    const r=this.querySelector('input'); if(!r) return;
    document.querySelectorAll(`input[name="${r.name}"]`).forEach(x=>x.closest('.opt').classList.remove('sel'));
    this.classList.add('sel'); r.checked=true;
    const qnum=this.closest('[data-qnum]')?.dataset.qnum;
    if(qnum){
      document.querySelectorAll(`.q-btn[data-qnum="${qnum}"]`).forEach(b=>b.classList.add('answered'));
    }
    updateProgress();
  });
});
function updateProgress(){
  const total=<?=$totalQ?>, ans=document.querySelectorAll('.opts input:checked').length;
  document.getElementById('progressBar').style.width=Math.round(ans/total*100)+'%';
  document.getElementById('progressText').textContent=ans+' / '+total;
}
updateProgress();
</script>
</body></html>
