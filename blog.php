<?php
session_start();
$ADMIN_HASH = '656e567d56a0d06940e961c075effafcd3d168b36c3ce914bcd9b11eb20ceede'; // см. шаг 3
$DATA = __DIR__ . '/data';

function load($f,$d){ global $DATA; return is_file("$DATA/$f") ? json_decode(file_get_contents("$DATA/$f"),true) : $d; }
function save($f,$d){ global $DATA; if(!is_dir($DATA)) mkdir($DATA,0777,true); file_put_contents("$DATA/$f", json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$posts = load('posts.json', []); $comments = load('comments.json', []); $users = load('users.json', []);
$msg = '';

// вход автора
if (isset($_POST['login'])) {
  if (hash('sha256', $_POST['pass'] ?? '') === $ADMIN_HASH) $_SESSION['admin'] = true;
  else $msg = 'Неверный пароль автора';
}
if (isset($_GET['logout'])) unset($_SESSION['admin']);
$admin = !empty($_SESSION['admin']);

// новый пост / правка
if ($admin && isset($_POST['save_post'])) {
  $t = trim($_POST['title']); $x = trim($_POST['text']); $eid = $_POST['edit_id'];
  if ($t !== '' && $x !== '') {
    if ($eid === '') array_unshift($posts, ['id'=>time(), 'date'=>date('d.m.Y'), 'title'=>$t, 'text'=>$x]);
    else foreach ($posts as &$p) if ((string)$p['id'] === $eid) { $p['title']=$t; $p['text']=$x; }
    unset($p); save('posts.json',$posts); header('Location: blog.php'); exit;
  }
  $msg = 'Заполни заголовок и текст';
}

// комментарий: имя + пароль (защита от чужого имени) + текст
if (isset($_POST['add_comment'])) {
  $n = trim($_POST['name']); $pass = $_POST['pass'] ?? ''; $x = trim($_POST['text']); $pid = (int)$_POST['post_id'];
  if ($n === '' || $x === '' || $pass === '') $msg = 'Заполни имя, пароль и комментарий';
  else {
    $key = mb_strtolower($n); $h = hash('sha256', $key.'|'.$pass);
    if (isset($users[$key]) && $users[$key] !== $h) $msg = 'Имя «'.$n.'» занято — введи свой пароль от него или выбери другое имя';
    else {
      $users[$key] = $h; save('users.json',$users);
      $comments[] = ['post_id'=>$pid, 'name'=>$n, 'text'=>$x, 'date'=>date('d.m.Y H:i')];
      save('comments.json',$comments); $msg = 'Комментарий опубликован!';
    }
  }
}

$editPost = null;
if ($admin && isset($_GET['edit'])) foreach ($posts as $p) if ((string)$p['id'] === $_GET['edit']) $editPost = $p;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Блог — Мой уголок интернета</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1>Блог</h1>
  <nav>
    <a href="index.html">Главная</a> <a href="blog.php">Блог</a> <a href="guestbook.html">Гостевая книга</a>
    <?php if ($admin): ?><a class="logout" href="blog.php?logout=1">выйти</a><?php endif; ?>
  </nav>
  <?php if ($admin): ?><a class="icon-btn" href="blog.php?new=1" title="Новый пост">📝</a>
  <?php else: ?><a class="icon-btn" href="blog.php?login=1" title="Вход для автора">🔑</a><?php endif; ?>
</header>
<main>
<?php if ($msg): ?><p><b><?= e($msg) ?></b></p><?php endif; ?>

<?php if (isset($_GET['login']) && !$admin): ?>
  <form method="post" class="panel">
    <h2>Вход для автора</h2>
    <label>Пароль: <input type="password" name="pass" required></label>
    <button name="login" value="1">Войти</button>
  </form>
<?php endif; ?>

<?php if ($admin && (isset($_GET['new']) || $editPost)): ?>
  <form method="post" class="panel">
    <h2><?= $editPost ? 'Исправить пост' : 'Новый пост' ?></h2>
    <label>Заголовок: <input type="text" name="title" value="<?= e($editPost['title'] ?? '') ?>" required></label>
    <label>Текст: <textarea name="text" rows="6" required><?= e($editPost['text'] ?? '') ?></textarea></label>
    <input type="hidden" name="edit_id" value="<?= e($editPost['id'] ?? '') ?>">
    <button name="save_post" value="1">Сохранить</button> <a href="blog.php">Отмена</a>
  </form>
<?php endif; ?>

<?php foreach ($posts as $p): ?>
  <article>
    <h2><?= e($p['title']) ?> <?php if ($admin): ?><a class="edit-btn" href="blog.php?edit=<?= $p['id'] ?>">✏️ исправить</a><?php endif; ?></h2>
    <p class="date"><?= e($p['date']) ?></p>
    <p class="post-text"><?= e($p['text']) ?></p>
    <div class="comments">
      <?php $pc = array_values(array_filter($comments, fn($c) => $c['post_id'] === $p['id'])); ?>
      <?php if ($pc): ?><h3>Комментарии (<?= count($pc) ?>)</h3><?php endif; ?>
      <?php foreach ($pc as $c): ?>
        <p class="comment"><b><?= e($c['name']) ?></b> <span class="date"><?= e($c['date']) ?></span><br><?= e($c['text']) ?></p>
      <?php endforeach; ?>
      <form method="post" class="comment-form">
        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
        <label>Твоё имя/ник: <input type="text" name="name" required></label>
        <label>Пароль (чтобы никто не писал от твоего имени): <input type="password" name="pass" required></label>
        <label>Комментарий: <textarea name="text" rows="3" required></textarea></label>
        <button name="add_comment" value="1">Отправить</button>
      </form>
    </div>
  </article>
<?php endforeach; ?>
<?php if (!$posts): ?><p>Постов пока нет.</p><?php endif; ?>
</main>
<footer><p><a href="index.html">← на главную</a></p></footer>
</body>
</html>