<?php
// Novixx Wiki 0.1
// License: GPL-3.0
// Description: A simple wiki software written in PHP
// Thanks for using Novixx Wiki!

// Path to the JSON file
define('DATA_FILE', 'wiki_data.json');
define('SETTINGS_FILE', 'settings.json');

// Load settings
$settings = json_decode(file_get_contents(SETTINGS_FILE), true);
$website = $settings['website'] ?? 'Novixx Wiki Demo';
$copyright = $settings['copyright'] ?? '';
$private_mode = $settings['private_mode'] ?? false;
$allowed_ips = $settings['allowed_ips'] ?? [];
$error_404 = 'This page does not exist. Create it by clicking "Edit".';

function not_allowed($message) {
    global $website, $copyright;
    die("<p>You are not allowed to perform this action. Reason: $message</p>
    <div class=\"footer\">
            <p>Novixx Wiki 0.1 (Released 2024). $website (c) ". date('Y') . " " . $copyright . ". All rights reserved.</p>
        </div>");
}

// Initialize data file if not exists
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
}

// Load data
$data = json_decode(file_get_contents(DATA_FILE), true);

// Special pages (_*)
if (isset($_GET['page']) && str_starts_with($_GET['page'], '_')) {
    if ($_GET['page'] === '_random') {
        global $data;
        $page = array_rand($data);
        header("Location: ?page=" . urlencode($page));
        exit;
    }
}

// Save data
function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data));
}

// Handle page creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page = $_POST['page'] ?? '';
    $content = $_POST['content'] ?? '';
    if ($page && $content) {
        if ($private_mode && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
            echo not_allowed("Private mode is enabled.");
            die(); // not_allowed() already exits but just in case
        }
        global $data;
        $dt = new DateTime();
        $timestamp = $dt->getTimestamp();
        $data[$page][$timestamp] = $content;
        saveData($data);
        header("Location: ?page=" . urlencode($page));
        exit;
    }
}

// Handle page viewing
$page = $_GET['page'] ?? 'home';
$highest_timestamp = 0;
$highest_content = $error_404;
foreach ($data[$page] ?? [] as $timestamp => $content) {
    if ($timestamp > $highest_timestamp) {
        $highest_timestamp = $timestamp;
        $highest_content = $content;
    }
}
if (isset($_GET['timestamp']) && isset($data[$page][$_GET['timestamp']])) {
    $highest_content = $data[$page][$_GET['timestamp']];
}
$content = $highest_content;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $website ?> - <?php echo htmlspecialchars($page); ?></title>
    <style>
        body {
            font-family: Verdana, sans-serif;
            background-color: #dfe3ee;
            margin: 0;
            padding: 0;
        }
        #header {
            background-color: #3b5998;
            color: white;
            padding: 10px 20px;
        }
        #header a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
        }
        #sidebar {
            background-color: #f7f7f7;
            padding: 15px;
            width: 200px;
            float: left;
            border-right: 1px solid #ccc;
            height: 100vh;
        }
        #container {
            width: calc(100% - 220px);
            margin-left: 220px;
            padding: 20px;
            background-color: white;
            min-height: 100vh;
        }
        h1 {
            font-size: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        textarea {
            width: 100%;
            height: 200px;
            font-family: monospace;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .content {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div id="header">
        <a href="?page=home">Home</a>
        <a href="?page=about">About</a>
        <a href="?page=contact">Contact</a>
    </div>
    <div id="sidebar">
        <h3>Navigation</h3>
        <ul>
            <li><a href="?page=home">Home</a></li>
            <li><a href="?page=about">About</a></li>
            <li><a href="?page=contact">Contact</a></li>
            <li><a href="?page=_random">Random</a></li>
        </ul>
    </div>
    <div id="container">
        <?php
        $delete = $_GET['delete'] ?? '';
        if ($delete) {
            $page = $_GET['page'] ?? '';
            if ($page) {
                if ($private_mode && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
                    echo not_allowed("Private mode is enabled.");
                    die(); // not_allowed() already exits but just in case
                }
                global $data;
                unset($data[$page]);
                saveData($data);
                header("Location: ?page=home");
                exit;
            }
        }
        $history = $_GET['history'] ?? '';
        if ($history) {
            $page = $_GET['page'] ?? '';
            if ($page) {
                global $data;
                $history_data = $data[$page] ?? [];
                krsort($history_data);
                echo '<h1>History of ' . htmlspecialchars($page) . '</h1>';
                echo '<hr>';
                foreach ($history_data as $timestamp => $content) {
                    echo '<a href="?page=' . urlencode($page) . '&timestamp=' . $timestamp . '">' . date('Y-m-d H:i:s', $timestamp) . '</a><br>';
                    echo '<hr>';
                }
            }
        }
        else {
        ?>
        <h1><?php echo htmlspecialchars($page); ?></h1>
        <div class="content">
            <?php
            $parsed_content = nl2br(htmlspecialchars($content));
            $parsed_content = preg_replace('/\[\[(.*?)\]\]/', '<a href="?page=$1">$1</a>', $parsed_content);
            echo $parsed_content;
            ?>
        </div>
        <hr>
        <?php if (isset($_GET['edit'])): ?>
            <?php
            if ($private_mode && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
                echo not_allowed("Private mode is enabled.");
            }
            if (str_starts_with($page, '_')) {
                echo not_allowed("Reserved page name.");
            }
            ?>
            <h2>Edit Page</h2>
            <form method="post">
                <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                <textarea name="content"><?php echo htmlspecialchars($content); ?></textarea>
                <br>
                <button type="submit">Save</button>
            </form>
        <?php else: ?>
            <img src="images/edit.png" alt="Edit"> <a href="?page=<?php echo urlencode($page); ?>&edit=1">Edit</a> | <img src="images/history.png" alt="History"> <a href="?page=<?php echo urlencode($page); ?>&history=1">History</a> | <img src="images/delete.png" alt="Delete"> <a href="?page=<?php echo urlencode($page); ?>&delete=1">Delete</a>
        <?php endif; ?>
        <hr>
        <?php } ?>
        <div class="footer">
            <p>Novixx Wiki 0.1 (Released 2024). <?php echo $website ?> (c) <?php echo date('Y') . " " . $copyright ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
