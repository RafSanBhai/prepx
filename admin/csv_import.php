<?php
require_once __DIR__.'/../config.php';
requireRole('super_admin','teacher');
$pdo=db();
$errors=[]; $imported=0;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $eid=(int)($_POST['exam_id']??0);
    if (!$eid) { $errors[]='Please select a target exam.'; }
    elseif (empty($_FILES['csv_file']['tmp_name'])) { $errors[]='Please upload a CSV file.'; }
    else {
        $ext=strtolower(pathinfo($_FILES['csv_file']['name'],PATHINFO_EXTENSION));
        if ($ext!=='csv') { $errors[]='Only .csv files accepted.'; }
        else {
            $handle=fopen($_FILES['csv_file']['tmp_name'],'r');
            $rowNum=0;
            $ord=(int)$pdo->query("SELECT COALESCE(MAX(q_order),0) FROM questions WHERE exam_id=$eid")->fetchColumn();
            $ins=$pdo->prepare('INSERT INTO questions(exam_id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,marks,q_order) VALUES(?,?,?,?,?,?,?,?,?,?)');
            while(($row=fgetcsv($handle,4000,','))!==false) {
                $rowNum++;
                if ($rowNum===1 && strtolower(trim($row[0]))==='question_text') continue;
                if (count($row)<6) { $errors[]="Row $rowNum: too few columns. Skipped."; continue; }
                [$qText,$optA,$optB,$optC,$optD]=array_map('trim',array_slice($row,0,5));
                $correct=strtoupper(trim($row[5]??'A'));
                $expl=trim($row[6]??''); $marks=(float)($row[7]??1);
                if (!in_array($correct,['A','B','C','D'])) { $errors[]="Row $rowNum: invalid correct_option '$correct'. Skipped."; continue; }
                if (!$qText) { $errors[]="Row $rowNum: empty question. Skipped."; continue; }
                $ord++; $ins->execute([$eid,$qText,$optA,$optB,$optC,$optD,$correct,$expl,$marks,$ord]); $imported++;
            }
            fclose($handle);
            $tm=$pdo->prepare('SELECT COALESCE(SUM(marks),0) FROM questions WHERE exam_id=?');
            $tm->execute([$eid]);
            $pdo->prepare('UPDATE exams SET total_marks=? WHERE id=?')->execute([$tm->fetchColumn(),$eid]);
            if ($imported>0) setFlash('success',"$imported question(s) imported successfully!");
            else setFlash('warning','No questions imported. Check CSV format.');
        }
    }
}
$exams=$pdo->query("SELECT id,title FROM exams WHERE status!='archived' ORDER BY title")->fetchAll();
$pageTitle='CSV Import';
?>
<?php require_once __DIR__.'/../includes/header.php'; ?>
<div class="layout">
<?php require_once __DIR__.'/../includes/admin_sidebar.php'; ?>
<main class="main-content">

<div class="topbar">
  <div><h1 class="topbar__title">CSV Import</h1><p class="topbar__sub">Bulk-upload questions from a CSV spreadsheet.</p></div>
</div>

<?php if($errors): ?>
<div class="card" style="border-left:4px solid var(--danger);margin-bottom:1rem;">
  <strong style="color:var(--danger);">Import Issues:</strong>
  <ul style="margin-top:.4rem;padding-left:1.2rem;font-size:.85rem;color:var(--danger);">
    <?php foreach($errors as $e): ?><li><?=e($e)?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
  <div class="card__header"><h2 class="card__title">📤 Upload CSV File</h2></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
    <div class="form-row">
      <div class="form-group">
        <label>Target Exam</label>
        <select name="exam_id" required>
          <option value="">— Select Exam —</option>
          <?php foreach($exams as $ex): ?>
          <option value="<?=$ex['id']?>"><?=e($ex['title'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>CSV File</label>
        <input type="file" name="csv_file" accept=".csv" required>
      </div>
    </div>
    <button type="submit" class="btn btn--primary">Import Questions</button>
    <a href="/sample_questions.csv" download class="btn btn--outline" style="margin-left:.5rem;">⬇ Download Sample CSV</a>
  </form>
</div>

<div class="card">
  <div class="card__header"><h2 class="card__title">📋 CSV Format Guide</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Column</th><th>Required</th><th>Example</th></tr></thead>
      <tbody>
        <tr><td>question_text</td><td>✅</td><td>What is the limitation period under Section 468 CrPC?</td></tr>
        <tr><td>option_a</td><td>✅</td><td>3 years</td></tr>
        <tr><td>option_b</td><td>✅</td><td>6 months</td></tr>
        <tr><td>option_c</td><td>✅</td><td>1 year</td></tr>
        <tr><td>option_d</td><td>✅</td><td>10 years</td></tr>
        <tr><td>correct_option</td><td>✅</td><td>A</td></tr>
        <tr><td>explanation</td><td>❌ Optional</td><td>Section 468(2)(c) provides…</td></tr>
        <tr><td>marks</td><td>❌ Optional</td><td>1</td></tr>
      </tbody>
    </table>
  </div>
  <div style="margin-top:1rem;background:var(--gold-pale);border-radius:var(--radius);padding:.9rem 1rem;font-size:.83rem;">
    <strong>Tips:</strong> Save Excel as CSV (Comma delimited). Wrap cells with commas in double-quotes. First header row is auto-detected and skipped.
  </div>
  <pre style="background:#1a2332;color:#e2c06a;padding:1rem;border-radius:var(--radius);font-size:.75rem;overflow-x:auto;margin-top:1rem;line-height:1.7;">question_text,option_a,option_b,option_c,option_d,correct_option,explanation,marks
"What is CrPC?","Code of Criminal Procedure","Civil Rights Code","Court Record Procedure","Criminal Rights Code",A,"CrPC = Code of Criminal Procedure 1898",1</pre>
</div>

</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
