<?php
session_start();

$wordDataPath = __DIR__ . '/words.json';
$wordDataRaw = file_get_contents($wordDataPath);
$words = json_decode($wordDataRaw ?: '[]', true);

if (!is_array($words) || empty($words)) {
    http_response_code(500);
    echo '单词数据加载失败，请检查 words.json';
    exit;
}

$totalWords = count($words);

if (!isset($_SESSION['known'])) {
    $_SESSION['known'] = [];
}

if (!isset($_SESSION['quiz'])) {
    $_SESSION['quiz'] = [
        'current' => 0,
        'score' => 0,
        'questions' => [],
        'finished' => false,
    ];
}

function resetQuiz(array $words): void
{
    $indexes = array_keys($words);
    shuffle($indexes);
    $_SESSION['quiz'] = [
        'current' => 0,
        'score' => 0,
        'questions' => $indexes,
        'finished' => false,
    ];
}

if (empty($_SESSION['quiz']['questions'])) {
    resetQuiz($words);
}

$flashIndex = isset($_GET['word']) ? (int)$_GET['word'] : 0;
$flashIndex = max(0, min($flashIndex, $totalWords - 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_known') {
        $index = (int)($_POST['index'] ?? 0);
        if ($index >= 0 && $index < $totalWords && !in_array($index, $_SESSION['known'], true)) {
            $_SESSION['known'][] = $index;
        }
        header('Location: ?tab=flashcard&word=' . $index);
        exit;
    }

    if ($action === 'start_quiz') {
        resetQuiz($words);
        header('Location: ?tab=quiz');
        exit;
    }

    if ($action === 'answer') {
        $quiz = &$_SESSION['quiz'];
        $questionIndex = $quiz['questions'][$quiz['current']] ?? null;
        $selected = (int)($_POST['choice'] ?? -1);

        if ($questionIndex !== null && $selected === $questionIndex) {
            $quiz['score']++;
        }

        $quiz['current']++;
        if ($quiz['current'] >= $totalWords) {
            $quiz['finished'] = true;
        }

        header('Location: ?tab=quiz');
        exit;
    }
}

$tab = $_GET['tab'] ?? 'flashcard';
$knownCount = count($_SESSION['known']);

function buildChoices(array $words, int $answerIndex): array
{
    $allIndexes = array_keys($words);
    $others = array_values(array_filter($allIndexes, fn($i) => $i !== $answerIndex));
    shuffle($others);

    $choices = [$answerIndex];
    $choices = array_merge($choices, array_slice($others, 0, 3));
    shuffle($choices);

    return $choices;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>英语单词学习站</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <header>
        <h1>英语单词学习站</h1>
        <p>每天背一点，词汇增长看得见。</p>
    </header>

    <div class="stats">
        <div class="stat-card">
            <strong><?php echo $totalWords; ?></strong>
            <span>总单词数</span>
        </div>
        <div class="stat-card">
            <strong><?php echo $knownCount; ?></strong>
            <span>已掌握</span>
        </div>
        <div class="stat-card">
            <strong><?php echo (int)(($knownCount / $totalWords) * 100); ?>%</strong>
            <span>掌握率</span>
        </div>
    </div>

    <nav>
        <a class="<?php echo $tab === 'flashcard' ? 'active' : ''; ?>" href="?tab=flashcard&word=<?php echo $flashIndex; ?>">单词卡片</a>
        <a class="<?php echo $tab === 'quiz' ? 'active' : ''; ?>" href="?tab=quiz">选择测验</a>
        <a class="<?php echo $tab === 'words' ? 'active' : ''; ?>" href="?tab=words">全部单词</a>
    </nav>

    <?php if ($tab === 'flashcard'): ?>
        <?php $item = $words[$flashIndex]; ?>
        <section class="panel card-panel">
            <h2><?php echo htmlspecialchars($item['word']); ?></h2>
            <p class="phonetic"><?php echo htmlspecialchars($item['phonetic']); ?></p>
            <p><strong>中文释义：</strong><?php echo htmlspecialchars($item['meaning']); ?></p>
            <p><strong>例句：</strong><?php echo htmlspecialchars($item['example']); ?></p>

            <div class="audio-actions">
                <button
                    class="btn secondary"
                    type="button"
                    data-speak="<?php echo htmlspecialchars($item['word']); ?>"
                    data-lang="en-US"
                >
                    🔊 播放单词
                </button>
                <button
                    class="btn secondary"
                    type="button"
                    data-speak="<?php echo htmlspecialchars($item['example']); ?>"
                    data-lang="en-US"
                >
                    🎵 播放例句
                </button>
            </div>

            <div class="actions">
                <a class="btn" href="?tab=flashcard&word=<?php echo max(0, $flashIndex - 1); ?>">上一词</a>
                <a class="btn" href="?tab=flashcard&word=<?php echo min($totalWords - 1, $flashIndex + 1); ?>">下一词</a>
                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="mark_known">
                    <input type="hidden" name="index" value="<?php echo $flashIndex; ?>">
                    <button class="btn success" type="submit">标记已掌握</button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'quiz'): ?>
        <section class="panel">
            <h2>单词小测验</h2>
            <?php
            $quiz = $_SESSION['quiz'];
            if ($quiz['finished']):
            ?>
                <p class="result">测验完成！得分：<?php echo $quiz['score']; ?> / <?php echo $totalWords; ?></p>
                <form method="post">
                    <input type="hidden" name="action" value="start_quiz">
                    <button class="btn success" type="submit">再测一次</button>
                </form>
            <?php
            else:
                $current = $quiz['current'];
                $answerIndex = $quiz['questions'][$current];
                $question = $words[$answerIndex];
                $choices = buildChoices($words, $answerIndex);
            ?>
                <p>第 <?php echo $current + 1; ?> / <?php echo $totalWords; ?> 题</p>
                <p><strong>请选择 “<?php echo htmlspecialchars($question['word']); ?>” 的正确中文释义：</strong></p>
                <form method="post" class="quiz-form">
                    <input type="hidden" name="action" value="answer">
                    <?php foreach ($choices as $choiceIndex): ?>
                        <label class="choice">
                            <input type="radio" name="choice" value="<?php echo $choiceIndex; ?>" required>
                            <?php echo htmlspecialchars($words[$choiceIndex]['meaning']); ?>
                        </label>
                    <?php endforeach; ?>
                    <button class="btn" type="submit">提交答案</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'words'): ?>
        <section class="panel">
            <h2>全部单词</h2>
            <p>共 <?php echo $totalWords; ?> 个单词，点击“查看卡片”可进入对应学习页。</p>
            <div class="word-list">
                <?php foreach ($words as $index => $word): ?>
                    <article class="word-item">
                        <div class="word-main">
                            <h3><?php echo htmlspecialchars($word['word']); ?></h3>
                            <p class="phonetic"><?php echo htmlspecialchars($word['phonetic']); ?></p>
                            <p><strong>中文释义：</strong><?php echo htmlspecialchars($word['meaning']); ?></p>
                            <p><strong>例句：</strong><?php echo htmlspecialchars($word['example']); ?></p>

                        </div>
                        <div class="word-actions">
                            <button
                                class="btn secondary"
                                type="button"
                                data-speak="<?php echo htmlspecialchars($word['word']); ?>"
                                data-lang="en-US"
                            >
                                🔊 播放单词
                            </button>
                            <a class="btn" href="?tab=flashcard&word=<?php echo $index; ?>">查看卡片</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<script>
(() => {
    const speakButtons = document.querySelectorAll('[data-speak]');
    if (!('speechSynthesis' in window) || speakButtons.length === 0) {
        return;
    }

    const stopSpeaking = () => {
        if (window.speechSynthesis.speaking) {
            window.speechSynthesis.cancel();
        }
    };

    speakButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const text = button.getAttribute('data-speak');
            const lang = button.getAttribute('data-lang') || 'en-US';
            if (!text) {
                return;
            }

            stopSpeaking();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = lang;
            utterance.rate = 0.95;
            utterance.pitch = 1;
            window.speechSynthesis.speak(utterance);
        });
    });
})();
</script>
</body>
</html>
