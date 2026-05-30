<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin','teacher');
$pdo = db();
$examId=(int)($_GET['exam_id']??0);
$allExams=$pdo->query("SELECT id,title FROM exams WHERE status!='archived' ORDER BY title")->fetchAll();
if (!$examId && $allExams) $examId=(int)$allExams[0]['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    $eid=(int)($_POST['exam_id']??$examId);

    if ($action==='delete') {
        $pdo->prepare('DELETE FROM questions WHERE id=?')->execute([(int)$_POST['q_id']]);
    } elseif (in_array($action,['create','update'])) {
        $qText=trim($_POST['question_text']??'');
        $optA=trim($_POST['option_a']??''); $optB=trim($_POST['option_b']??'');
        $optC=trim($_POST['option_c']??''); $optD=trim($_POST['option_d']??'');
        $correct=strtoupper(trim($_POST['correct_option']??'A'));
        $expl=trim($_POST['explanation']??'');
        $marks=(float)($_POST['marks']??1);
        if ($qText && $optA && $optB && $optC && $optD && in_array($correct,['A','B','C','D'])) {
            if ($action==='create') {
                $ord=(int)$pdo->query("SELECT COALESCE(MAX(q_order),0)+1 FROM questions WHERE exam_id=$eid")->fetchColumn();
                $pdo->prepare('INSERT INTO questions(exam_id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,marks,q_order) VALUES(?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$eid,$qText,$optA,$optB,$optC,$optD,$correct,$expl,$marks,$ord]);
                setFlash('success','Question added.');
            } else {
                $pdo->prepare('UPDATE questions SET question_text=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_option=?,explanation=?,marks=? WHERE id=?')
                    ->execute([$qText,$optA,$optB,$optC,$optD,$correct,$expl,$marks,(int)$_POST['q_id']]);
                setFlash('success','Question updated.');
            }
            $tm=$pdo->prepare('SELECT COALESCE(SUM(marks),0) FROM questions WHERE exam_id=?');
            $tm->execute([$eid]);
            $pdo->prepare('UPDATE exams SET total_marks=? WHERE id=?')->execute([$tm->fetchColumn(),$eid]);
        } else { setFlash('error','All fields required; correct option must be A/B/C/D.'); }
    }
    redirect("/admin/questions.php?exam_id=$eid");
}

$editQ=null;
if (isset($_GET['edit'])) {
    $st=$pdo->prepare('SELECT * FROM questions WHERE id=? LIMIT 1');
    $st->execute([(int)$_GET['edit']]); $editQ=$st->fetch();
    if ($editQ) $examId=(int)$editQ['exam_id'];
}

$currentExam=null;
if ($examId) {
    $st=$pdo->prepare('SELECT e.*,c.name AS cat_name FROM exams e JOIN categories c ON c.id=e.category_id WHERE e.id=? LIMIT 1');
    $st->execute([$examId]); $currentExam=$st->fetch();
}
$page=max(1,(int)($_GET['page']??1)); $perPage=20; $offset=($page-1)*$perPage;
$total=$examId?(int)$pdo->query("SELECT COUNT(*) FROM questions WHERE exam_id=$examId")->fetchColumn():0;
$questions=$examId?$pdo->query("SELECT * FROM questions WHERE exam_id=$examId ORDER BY q_order,id LIMIT $perPage OFFSET $offset")->fetchAll():[];
$totalPages=(int)ceil($total/$perPage);
$pageTitle='Questions';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div>
    <h1 class="topbar__title">Questions</h1>
    <p class="topbar__sub"><?=$currentExam?e($currentExam['title']).' — '.$total.' question(s)':'Select an exam'?></p>
  </div>
  <button class="btn btn--primary" onclick="document.getElementById('qModal').classList.add('open')">＋ Add Question</button>
</div>

<div class="card" style="padding:.9rem 1.2rem;">
  <form method="GET" style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;">
    <label style="margin:0;font-size:.88rem;font-weight:600;text-transform:none;letter-spacing:0;color:var(--text);">Exam:</label>
    <select name="exam_id" onchange="this.form.submit()" style="flex:1;max-width:380px;">
      <?php foreach($allExams as $ex): ?>
      <option value="<?=$ex['id']?>" <?=$examId===$ex['id']?'selected':''?>><?=e($ex['title'])?></option>
      <?php endforeach; ?>
    </select>
    <a href="/admin/csv_import.php" class="btn btn--outline btn--sm">📤 CSV Import</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Question</th><th>Options</th><th>Correct</th><th>Marks</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($questions as $i=>$q): ?>
      <tr>
        <td><?=$offset+$i+1?></td>
        <td style="max-width:280px;font-size:.85rem;"><?=e(mb_strimwidth($q['question_text'],0,100,'…'))?><?=$q['explanation']?'<div style="font-size:.7rem;color:var(--text-muted);">💡 Explanation set</div>':''?></td>
        <td style="font-size:.73rem;color:var(--text-muted);">A:<?=e(mb_strimwidth($q['option_a'],0,22,'…'))?><br>B:<?=e(mb_strimwidth($q['option_b'],0,22,'…'))?><br>C:<?=e(mb_strimwidth($q['option_c'],0,22,'…'))?><br>D:<?=e(mb_strimwidth($q['option_d'],0,22,'…'))?></td>
        <td><span style="background:var(--navy);color:#fff;padding:.2rem .6rem;border-radius:6px;font-weight:700;font-size:.85rem;"><?=$q['correct_option']?></span></td>
        <td><?=$q['marks']?></td>
        <td style="white-space:nowrap;">
          <a href="?exam_id=<?=$examId?>&edit=<?=$q['id']?>" class="btn btn--sm btn--primary">Edit</a>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
            <input type="hidden" name="action"  value="delete">
            <input type="hidden" name="q_id"    value="<?=$q['id']?>">
            <input type="hidden" name="exam_id" value="<?=$examId?>">
            <button class="btn btn--sm btn--danger" data-confirm="Delete this question?">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; if(!$questions): ?>
      <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No questions. <a href="#" onclick="document.getElementById('qModal').classList.add('open');return false;">Add one</a> or <a href="/admin/csv_import.php">import CSV</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($totalPages>1): ?>
  <div class="pagination" style="padding:1rem;">
    <?php for($p=1;$p<=$totalPages;$p++): ?>
    <a href="?exam_id=<?=$examId?>&page=<?=$p?>" class="<?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php $isEdit=(bool)$editQ; $m=$editQ??[]; ?>
<div class="modal-backdrop <?=$isEdit?'open':''?>" id="qModal">
  <div class="modal-box">
    <div class="modal-header">
      <h2><?=$isEdit?'Edit':'Add'?> Question</h2>
      <button class="modal-close" data-modal-close>✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action"  value="<?=$isEdit?'update':'create'?>">
      <input type="hidden" name="exam_id" value="<?=$examId?>">
      <?php if($isEdit): ?><input type="hidden" name="q_id" value="<?=$m['id']?>"><?php endif; ?>
      <div class="form-group">
        <label>Question Text</label>
        <textarea name="question_text" required rows="3"><?=e($m['question_text']??'')?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Option A</label><input type="text" name="option_a" required value="<?=e($m['option_a']??'')?>"></div>
        <div class="form-group"><label>Option B</label><input type="text" name="option_b" required value="<?=e($m['option_b']??'')?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Option C</label><input type="text" name="option_c" required value="<?=e($m['option_c']??'')?>"></div>
        <div class="form-group"><label>Option D</label><input type="text" name="option_d" required value="<?=e($m['option_d']??'')?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Correct Option</label>
          <select name="correct_option" required>
            <?php foreach(['A','B','C','D'] as $l): ?>
            <option value="<?=$l?>" <?=($m['correct_option']??'A')===$l?'selected':''?>><?=$l?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Marks</label><input type="number" name="marks" step="0.25" min="0.25" value="<?=$m['marks']??1?>"></div>
      </div>
      <div class="form-group">
        <label>Explanation (optional)</label>
        <textarea name="explanation" rows="2"><?=e($m['explanation']??'')?></textarea>
      </div>
      <div style="display:flex;gap:.7rem;justify-content:flex-end;">
        <button type="button" class="btn btn--outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary"><?=$isEdit?'Update':'Add'?> Question</button>
      </div>
    </form>
  </div>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
