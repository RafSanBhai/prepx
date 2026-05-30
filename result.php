<?php
// result.php — PrepX instant result with answer review
require_once __DIR__.'/config.php';
requireLogin();
$pdo=db(); $user=currentUser();
$attemptId=(int)($_GET['attempt']??0);
if (!$attemptId) redirect('/student/dashboard.php');

$isAdmin=in_array($user['role'],['super_admin','teacher']);
if ($isAdmin) {
    $st=$pdo->prepare('SELECT * FROM exam_attempts WHERE id=? LIMIT 1');
    $st->execute([$attemptId]);
} else {
    $st=$pdo->prepare('SELECT * FROM exam_attempts WHERE id=? AND user_id=? LIMIT 1');
    $st->execute([$attemptId,$user['id']]);
}
$attempt=$st->fetch();
if (!$attempt||$attempt['status']!=='completed') { setFlash('error','Result not found.'); redirect('/student/dashboard.php'); }

$exSt=$pdo->prepare('SELECT e.*,c.name AS cat_name FROM exams e JOIN categories c ON c.id=e.category_id WHERE e.id=? LIMIT 1');
$exSt->execute([$attempt['exam_id']]); $exam=$exSt->fetch();

$stuSt=$pdo->prepare('SELECT name,email FROM users WHERE id=? LIMIT 1');
$stuSt->execute([$attempt['user_id']]); $student=$stuSt->fetch();

$ansSt=$pdo->prepare(
    'SELECT sa.*,q.question_text,q.option_a,q.option_b,q.option_c,q.option_d,
            q.correct_option,q.explanation,q.marks
     FROM student_answers sa JOIN questions q ON q.id=sa.question_id
     WHERE sa.attempt_id=? ORDER BY q.q_order,q.id');
$ansSt->execute([$attemptId]); $answers=$ansSt->fetchAll();

$totalMarks=(float)$attempt['total_marks'];
$score=(float)$attempt['score'];
$pct=$totalMarks>0?round($score/$totalMarks*100,2):0;
$passed=$score>=(float)($exam['pass_marks']??0);
$correct=(int)$attempt['correct_count'];
$wrong=(int)$attempt['wrong_count'];
$skipped=(int)$attempt['skipped_count'];
$timeTaken='';
if ($attempt['submitted_at']&&$attempt['started_at']) {
    $secs=strtotime($attempt['submitted_at'])-strtotime($attempt['started_at']);
    $timeTaken=sprintf('%d min %d sec',floor($secs/60),$secs%60);
}
if ($pct>=80)      { $lbl='🏆 Excellent'; $lblC='#1a7f4b'; }
elseif ($pct>=60)  { $lbl='👍 Good';      $lblC='#2980b9'; }
elseif ($pct>=40)  { $lbl='📘 Average';   $lblC='#d68910'; }
else               { $lbl='📖 Needs Work';$lblC='#c0392b'; }
$pageTitle='Result';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f1b2d">
<title>Result | PrepX</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
<style>
body{background:#f0f4f8;}
.res-wrap{max-width:820px;margin:0 auto;padding:1.2rem;}
.hero{background:linear-gradient(135deg,var(--navy),#1e3050);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:1.4rem;box-shadow:var(--shadow-lg);}
.hero-body{padding:1.8rem 1.5rem;text-align:center;color:#fff;}
.hero-title{font-family:var(--font-serif);font-size:1.3rem;margin-bottom:.3rem;}
.hero-meta{font-size:.78rem;opacity:.6;margin-bottom:1.2rem;}
/* Score ring */
.ring-wrap{display:flex;justify-content:center;margin-bottom:1rem;}
.ring{position:relative;width:130px;height:130px;}
.ring svg{transform:rotate(-90deg);}
.ring__bg{fill:none;stroke:rgba(255,255,255,.12);stroke-width:10;}
.ring__arc{fill:none;stroke:var(--gold-light);stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset 1.3s cubic-bezier(.4,0,.2,1);}
.ring__text{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.ring__pct{font-family:var(--font-serif);font-size:1.9rem;color:#fff;line-height:1;}
.ring__sub{font-size:.68rem;color:rgba(255,255,255,.55);}
.hero-label{display:inline-block;padding:.3rem 1rem;border-radius:99px;font-weight:700;font-size:.95rem;background:rgba(255,255,255,.1);margin:.4rem 0;}
.hero-pass{font-weight:700;font-size:1rem;margin-top:.3rem;}
.hero-stats{display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid rgba(255,255,255,.1);}
.hero-stat{padding:.9rem;text-align:center;border-right:1px solid rgba(255,255,255,.1);}
.hero-stat:last-child{border-right:none;}
.hero-stat__val{font-family:var(--font-serif);font-size:1.5rem;}
.hero-stat__lbl{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.45);margin-top:.1rem;}
/* Review items */
.rev-item{background:#fff;border-radius:var(--radius-lg);padding:1.1rem 1.2rem;margin-bottom:.9rem;box-shadow:var(--shadow);border-left:4px solid var(--border);}
.rev-item.correct{border-left-color:var(--success);}
.rev-item.wrong{border-left-color:var(--danger);}
.rev-item.skipped{border-left-color:var(--warning);}
.rev-qnum{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.4rem;}
.rev-qtext{font-size:.95rem;font-weight:500;line-height:1.65;margin-bottom:.8rem;}
.rev-opts{display:grid;grid-template-columns:1fr 1fr;gap:.35rem;}
.rev-opt{display:flex;align-items:flex-start;gap:.45rem;padding:.5rem .7rem;border-radius:8px;font-size:.82rem;line-height:1.4;}
.rev-opt .ok{width:20px;height:20px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;}
.rev-opt.opt-correct{background:#d4edda;}.rev-opt.opt-correct .ok{background:var(--success);color:#fff;}
.rev-opt.opt-chosen{background:#fde8e8;}.rev-opt.opt-chosen .ok{background:var(--danger);color:#fff;}
.rev-opt.opt-plain{background:#f4f4f4;}.rev-opt.opt-plain .ok{background:#ccc;color:#fff;}
.rev-expl{margin-top:.7rem;padding:.55rem .8rem;border-radius:8px;background:var(--gold-pale);font-size:.82rem;line-height:1.6;color:#6b5120;border-left:3px solid var(--gold);}
.rev-expl strong{display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--gold);margin-bottom:.15rem;}
@media(max-width:520px){.rev-opts{grid-template-columns:1fr;}.hero-stats{grid-template-columns:repeat(3,1fr);}}
@media print{.no-print{display:none!important;}.res-wrap{max-width:100%;}.rev-item{break-inside:avoid;}}
</style>
</head><body>

<!-- Nav -->
<nav class="site-nav no-print">
  <div class="container">
    <div class="site-nav__brand" style="text-decoration:none;">⚖ PrepX</div>
    <div class="site-nav__links">
      <?php if($isAdmin): ?>
        <a href="/admin/dashboard.php">Admin</a>
        <a href="/admin/results.php">Results</a>
      <?php else: ?>
        <a href="/student/dashboard.php">Dashboard</a>
        <a href="/student/exams.php">Exams</a>
      <?php endif; ?>
    </div>
    <button class="site-nav__ham" id="studentNavHam" aria-label="Menu">☰</button>
  </div>
  <div class="site-nav__mobile" id="studentNavMobile">
    <?php if($isAdmin): ?>
      <a href="/admin/dashboard.php">Admin</a>
      <a href="/admin/results.php">Results</a>
    <?php else: ?>
      <a href="/student/dashboard.php">Dashboard</a>
      <a href="/student/exams.php">Exams</a>
      <a href="/student/profile.php">Profile</a>
    <?php endif; ?>
    <a href="/logout.php">Logout</a>
  </div>
</nav>

<div class="res-wrap">

  <!-- Hero -->
  <div class="hero">
    <div class="hero-body">
      <?php if($isAdmin): ?>
      <p style="font-size:.78rem;opacity:.5;margin-bottom:.3rem;">Result for: <strong style="color:#fff;"><?=e($student['name'])?></strong></p>
      <?php endif; ?>
      <div class="hero-title"><?=e($exam['title'])?></div>
      <div class="hero-meta"><?=e($exam['cat_name'])?><?=$timeTaken?" · $timeTaken":''?></div>
      <div class="ring-wrap">
        <div class="ring">
          <svg width="130" height="130" viewBox="0 0 130 130">
            <circle class="ring__bg" cx="65" cy="65" r="54"/>
            <circle class="ring__arc" id="scoreArc" cx="65" cy="65" r="54"
              stroke-dasharray="339.29" stroke-dashoffset="339.29"/>
          </svg>
          <div class="ring__text">
            <div class="ring__pct"><?=$pct?>%</div>
            <div class="ring__sub"><?=number_format($score,1)?>/<?=number_format($totalMarks,1)?></div>
          </div>
        </div>
      </div>
      <div class="hero-label" style="color:<?=$lblC?>;"><?=$lbl?></div>
      <div class="hero-pass"><?=$passed?'✅ PASSED':'❌ NOT PASSED'?>
        <span style="font-size:.75rem;font-weight:400;opacity:.55;"> (Pass: <?=$exam['pass_marks']?> marks)</span>
      </div>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><div class="hero-stat__val" style="color:#5be68a;"><?=$correct?></div><div class="hero-stat__lbl">Correct</div></div>
      <div class="hero-stat"><div class="hero-stat__val" style="color:#ff7f7f;"><?=$wrong?></div><div class="hero-stat__lbl">Wrong</div></div>
      <div class="hero-stat"><div class="hero-stat__val" style="color:#ffd27f;"><?=$skipped?></div><div class="hero-stat__lbl">Skipped</div></div>
    </div>
  </div>

  <!-- Action buttons -->
  <div style="display:flex;gap:.7rem;flex-wrap:wrap;margin-bottom:1.4rem;" class="no-print">
    <?php if($isAdmin): ?>
      <a href="/admin/results.php" class="btn btn--outline">← All Results</a>
    <?php else: ?>
      <a href="/student/exams.php" class="btn btn--primary">← Browse Exams</a>
      <a href="/student/dashboard.php" class="btn btn--outline">Dashboard</a>
      <a href="/exam_view.php?exam=<?=$exam['id']?>&retake=1" class="btn btn--gold"
         onclick="return confirm('Start a new attempt for this exam?')">🔄 Retake</a>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn--outline">🖨 Print</button>
  </div>

  <!-- Answer Review -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="font-family:var(--font-serif);font-size:1.2rem;color:var(--navy);">Detailed Answer Review</h2>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;font-size:.72rem;">
      <span style="background:#d4edda;padding:.15rem .55rem;border-radius:20px;color:var(--success);">✔ Correct</span>
      <span style="background:#fde8e8;padding:.15rem .55rem;border-radius:20px;color:var(--danger);">✘ Wrong</span>
      <span style="background:#fef9e7;padding:.15rem .55rem;border-radius:20px;color:var(--warning);">— Skipped</span>
    </div>
  </div>

  <?php foreach($answers as $idx=>$ans):
    $isCor=(bool)$ans['is_correct'];
    $isSkip=($ans['chosen_option']===null||$ans['chosen_option']==='');
    $cls=$isSkip?'skipped':($isCor?'correct':'wrong');
    $icon=$isSkip?'—':($isCor?'✔':'✘');
    $iconC=$isSkip?'var(--warning)':($isCor?'var(--success)':'var(--danger)');
  ?>
  <div class="rev-item <?=$cls?>">
    <div class="rev-qnum">
      Q<?=$idx+1?> &nbsp;
      <span style="color:<?=$iconC?>;font-size:.78rem;"><?=$icon?></span>
      <?php if(!$isSkip&&!$isCor): ?>
        &nbsp;Your answer: <span style="color:var(--danger);"><?=e($ans['chosen_option']??'')?></span> &bull;
      <?php endif; ?>
      &nbsp;Correct: <span style="color:var(--success);"><?=e($ans['correct_option'])?></span>
      <span style="float:right;font-weight:400;"><?=$ans['marks']?> mark<?=$ans['marks']!=1?'s':''?></span>
    </div>
    <div class="rev-qtext"><?=nl2br(e($ans['question_text']))?></div>
    <div class="rev-opts">
      <?php foreach(['A','B','C','D'] as $ltr):
        $txt=$ans['option_'.strtolower($ltr)];
        $isCO=$ltr===$ans['correct_option'];
        $isCh=$ltr===$ans['chosen_option'];
        $cls2='opt-plain'; $icon2=$ltr;
        if ($isCO) { $cls2='opt-correct'; $icon2='✔'; }
        if ($isCh&&!$isCO) { $cls2='opt-chosen'; $icon2='✘'; }
      ?>
      <div class="rev-opt <?=$cls2?>"><span class="ok"><?=$icon2?></span><span><?=e($txt)?></span></div>
      <?php endforeach; ?>
    </div>
    <?php if($ans['explanation']): ?>
    <div class="rev-expl"><strong>💡 Explanation</strong><?=nl2br(e($ans['explanation']))?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div style="text-align:center;padding:1.5rem 0 2rem;" class="no-print">
    <?php if(!$isAdmin): ?>
    <a href="/student/exams.php" class="btn btn--primary btn--lg">Browse More Exams →</a>
    <?php else: ?>
    <a href="/admin/results.php" class="btn btn--primary btn--lg">← Back to Results</a>
    <?php endif; ?>
  </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
window.addEventListener('load',function(){
  const arc=document.getElementById('scoreArc'); if(!arc) return;
  const circ=2*Math.PI*54, pct=<?=$pct?>/100, offset=circ*(1-pct);
  arc.style.strokeDasharray=circ;
  arc.style.strokeDashoffset=circ;
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
    arc.style.transition='stroke-dashoffset 1.4s cubic-bezier(.4,0,.2,1)';
    arc.style.strokeDashoffset=offset;
  }));
});
</script>
</body></html>
