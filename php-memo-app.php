<?php
$db_file = 'memo.db';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

date_default_timezone_set('Asia/Tokyo');

// 編集対象のデータを保持する変数
$edit_id = '';
$edit_content = '';

// ─── 1. メモの追加・更新処理 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['memo'])) {
    $memo = htmlspecialchars($_POST['memo'], ENT_QUOTES, 'UTF-8');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        // IDがある場合は【更新（EDIT）】
        $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ?");
        $stmt->execute([$memo, $id]);
    } else {
        // IDがない場合は【新規追加（ADD）】
        $stmt = $pdo->prepare("INSERT INTO notes (content, created_at) VALUES (?, ?)");
        $stmt->execute([$memo, date('Y-m-d H:i:s')]);
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── 2. 編集モードの切り替え ───
if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    
    // 編集するデータを1件だけ取得してフォームにセットする準備
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_note) {
        $edit_content = $edit_note['content'];
    }
}

// ─── 3. メモの個別削除処理 ───
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── 4. メモの一覧取得（検索機能付き） ───
$search_word = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search_word !== '') {
    // 検索ワードがある場合：曖昧検索（LIKE）を実行
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE content LIKE ? ORDER BY id DESC");
    // `%ワード%` の形で指定することで、前後に文字があってもヒットするようにします
    $stmt->execute(["%{$search_word}%"]);
} else {
    // 検索ワードがない場合：全件取得
    $stmt = $pdo->query("SELECT * FROM notes ORDER BY id DESC");
}
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>多機能PHPメモ帳</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 20px auto; padding: 0 10px; line-height: 1.6; background-color: #f4f6f9; color: #333; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #222; font-size: 24px; text-align: center; }
        
        /* 検索フォームのスタイル */
        .search-box { margin-bottom: 20px; display: flex; gap: 5px; }
        .search-box input { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .search-box button { width: auto; padding: 8px 15px; font-size: 14px; background-color: #6c757d; }
        .search-box button:hover { background-color: #5a6268; }
        .clear-search { padding: 8px 15px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 4px; font-size: 14px; }

        textarea { width: 100%; height: 100px; padding: 12px; box-sizing: border-box; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; resize: vertical; }
        
        /* ボタンの色分け */
        .btn-submit { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn-submit:hover { background-color: #218838; }
        .btn-update { background-color: #007bff; }
        .btn-update:hover { background-color: #0069d9; }
        .btn-cancel { display: block; text-align: center; margin-top: 8px; color: #007bff; text-decoration: none; font-size: 14px; }

        .note-list { margin-top: 30px; }
        .note-item { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 6px; margin-bottom: 12px; position: relative; }
        .note-date { font-size: 12px; color: #6c757d; margin-bottom: 5px; }
        .note-text { word-wrap: break-word; white-space: pre-wrap; padding-right: 90px; }
        
        /* 操作リンクの配置 */
        .actions { position: absolute; right: 15px; top: 15px; display: flex; gap: 8px; }
        .action-link { text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: white; }
        .edit-link { background-color: #ffc107; color: #212529; }
        .edit-link:hover { background-color: #e0a800; }
        .delete-link { background-color: #dc3545; }
        .delete-link:hover { background-color: #c82333; }
        .empty-message { text-align: center; color: #6c757d; margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1>多機能PHPメモ帳</h1>
    
    <!-- 検索フォーム -->
    <form action="" method="get" class="search-box">
        <input type="text" name="search" placeholder="メモを検索..." value="<?php echo htmlspecialchars($search_word, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">検索</button>
        <?php if ($search_word !== ''): ?>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="clear-search">解除</a>
        <?php endif; ?>
    </form>

    <hr style="border: 0; border-top: 1px solid #dee2e6; margin: 20px 0;">
    
    <!-- 入力・編集フォーム -->
    <form action="" method="post">
        <!-- 編集時のみ、対象のIDを隠しデータ（hidden）として送信する -->
        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
        
        <textarea name="memo" placeholder="<?php echo $edit_id ? 'メモを編集中...' : '新しくメモを残す...'; ?>" required><?php echo $edit_content; ?></textarea>
        
        <?php if ($edit_id): ?>
            <button type="submit" class="btn-submit btn-update">更新する</button>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn-cancel">編集をキャンセル</a>
        <?php else: ?>
            <button type="submit" class="btn-submit">メモを保存</button>
        <?php endif; ?>
    </form>

    <!-- 一覧表示 -->
    <div class="note-list">
        <?php if (empty($notes)): ?>
            <p class="empty-message">
                <?php echo $search_word !== '' ? '検索結果に一致するメモはありません。' : '保存されたメモはありません。'; ?>
            </p>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <div class="note-item">
                    <div class="note-date"><?php echo $note['created_at']; ?></div>
                    <div class="note-text"><?php echo nl2br($note['content']); ?></div>
                    
                    <div class="actions">
                        <!-- 編集リンク -->
                        <a href="?action=edit&id=<?php echo $note['id']; ?>" class="action-link edit-link">編集</a>
                        <!-- 削除リンク -->
                        <a href="?action=delete&id=<?php echo $note['id']; ?>" class="action-link delete-link" onclick="return confirm('このメモを削除してもよろしいですか？');">削除</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
