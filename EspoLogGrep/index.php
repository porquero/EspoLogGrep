<?php
session_start();

// Define access password
const ACCESS_PASSWORD = 'mySecret123';

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check login
if (!isset($_SESSION['authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === ACCESS_PASSWORD) {
            $_SESSION['authenticated'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = 'Incorrect password.';
        }
    }

    // Login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Access Log Viewer</title>
    </head>
    <body>
        <h2>Login Required</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="post">
            <label for="password">Enter password:</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Access</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Continue with main functionality
date_default_timezone_set('GMT');

$search = '';
$exclude = '';
$date = date('Y-m-d');
$limit = 5000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $exclude = isset($_POST['exclude']) ? trim($_POST['exclude']) : '';
    $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 5000;

    if (!empty($_POST['date'])) {
        $date = trim($_POST['date']);
    }

    $file = __DIR__ . "/../../data/logs/espo-$date.log";

    $cmd = "cat " . escapeshellarg($file);

    if (!empty($search)) {
        $searchRegex = implode('|', array_map('trim', explode(',', $search)));
        $cmd .= " | grep -iE '$searchRegex'";
    }

    if (!empty($exclude)) {
        $excludeRegex = implode('|', array_map('trim', explode(',', $exclude)));
        $cmd .= " | grep -iEv '$excludeRegex'";
    }

    $cmd .= " | tac | head -n $limit";

    echo "<pre><strong>Executed command:</strong> " . htmlspecialchars($cmd) . "</pre>";

    $output = shell_exec($cmd);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EspoCRM Log Search</title>
    <style>
        .result-container {
            padding: 10px;
            border: 1px solid #ccc;
            margin-top: 20px;
            background: #f8f8f8;
            max-height: 600px;
            overflow-y: auto;
            font-family: monospace;
        }
        .line {
            padding: 4px;
            border-bottom: 1px dashed #ddd;
            display: block;
        }
        .line:last-child {
            border-bottom: none;
        }
        .highlight {
            background-color: yellow;
            font-weight: bold;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <p style="text-align:right;"><a href="?logout=1">Logout</a></p>
    <h2>GMT Time: <?php echo date("H:i:s"); ?></h2>

    <h1>EspoCRM Log Search</h1>
    <form method="post">
        <label for="search">Text(s) to search (comma-separated):</label>
        <input type="text" name="search" id="search" list="searchOptions" value="<?php echo htmlspecialchars($search); ?>">
        <datalist id="searchOptions">
            <option value="2022">
            <option value="_LOG">
            <option value="ERROR">
            <option value="Originate">
            <option value="refused">
            <option value="SQL">
            <option value="Voip">
            <option value="Rebuild">
        </datalist>
        <br><br>
        <label for="exclude">Text(s) to exclude (comma-separated):</label>
        <input type="text" name="exclude" id="exclude" list="excludeOptions" value="<?php echo htmlspecialchars($exclude); ?>">
        <datalist id="excludeOptions">
            <option value="DEBUG">
            <option value="Workflow">
        </datalist>
        <br><br>
        <label for="date">Log date:</label>
        <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date); ?>" required>
        <br><br>
        <label for="limit">Max lines to display:</label>
        <input type="number" name="limit" id="limit" min="1" max="100000" value="<?php echo htmlspecialchars($limit); ?>">
        <br><br>
        <button type="submit">Run Search</button>
    </form>

    <?php if (isset($output)): ?>
        <h2>Real-time Filter:</h2>
        <input type="text" id="filterInput" placeholder="Type to filter results...">
        
        <h2>Search Results</h2>
        <div class="result-container" id="result-container">
            <?php
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    echo '<div class="line">' . htmlspecialchars($line) . '</div>';
                }
            }
            ?>
        </div>
    <?php endif; ?>

    <script>
        function filterResults() {
            let filterText = document.getElementById("filterInput").value.toLowerCase();
            let lines = document.querySelectorAll(".line");

            // Don't filter until at least 3 characters
            if (filterText.length < 3) {
                lines.forEach(line => {
                    line.classList.remove("hidden");
                    line.innerHTML = line.textContent; // remove highlights
                });
                return;
            }

            lines.forEach(line => {
                let text = line.textContent.toLowerCase();
                if (text.includes(filterText)) {
                    line.classList.remove("hidden");
                    highlightText(line, filterText);
                } else {
                    line.classList.add("hidden");
                }
            });
        }


        function highlightText(element, text) {
            if (text === "") {
                element.innerHTML = element.textContent;
                return;
            }
            let regex = new RegExp(`(${text})`, "gi");
            element.innerHTML = element.textContent.replace(regex, match => `<span class="highlight">${match}</span>`);
        }

        document.addEventListener("mouseup", function () {
            let selectedText = window.getSelection().toString().trim();
            if (selectedText.length > 0) {
                highlightAllOccurrences(selectedText);
                
                // Copy selected text to clipboard
                navigator.clipboard.writeText(selectedText).then(() => {
                    console.log("Copied to clipboard: " + selectedText);
                }).catch(err => {
                    console.error("Clipboard copy failed: ", err);
                });
            }
        });

        function highlightAllOccurrences(text) {
            let container = document.getElementById("result-container");
            if (!container) return;

            let regex = new RegExp(text, "gi");

            container.innerHTML = container.innerHTML.replace(/<span class="highlight">(.*?)<\/span>/gi, "$1");

            container.innerHTML = container.innerHTML.replace(regex, function(match) {
                return `<span class="highlight">${match}</span>`;
            });
        }

        // Debounce utility
        function debounce(fn, delay) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        const filterInput = document.getElementById("filterInput");
        if (filterInput) {
            filterInput.addEventListener("input", debounce(() => {
                filterResults();
            }, 300)); // 300ms delay
        }

    </script>
</body>
</html>
