<?php
/**
 * WP Agent Bridge - Internationalization (i18n) Synchronization CLI
 *
 * Scans the plugin codebase for gettext strings, generates a fresh .pot template,
 * synchronizes French .po translations, and compiles the binary .mo file.
 *
 * Usage:
 *   php cli/sync-i18n.php
 */

$rootDir = dirname(__DIR__);
$pluginDir = $rootDir . '/woo-get-data-for-ai';
$languagesDir = $pluginDir . '/languages';
$dictFile = __DIR__ . '/translations-fr.php';

echo "=== WP Agent Bridge i18n Synchronization ===\n\n";

if (!is_dir($languagesDir)) {
    mkdir($languagesDir, 0755, true);
}

// 1. Scan codebase for gettext calls
echo "[1/4] Scanning codebase for gettext strings...\n";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDir));
$strings = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (strpos($path, 'plugin-update-checker') !== false) {
        continue;
    }

    $relPath = str_replace(str_replace('\\', '/', $pluginDir) . '/', '', $path);
    $content = file_get_contents($path);
    $tokens = token_get_all($content);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i])) continue;
        $text = $tokens[$i][1];
        $line = $tokens[$i][2];

        if (in_array($text, ['__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_x', 'esc_html_x', 'esc_attr_x'])) {
            $j = $i + 1;
            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if ($j < $count && $tokens[$j] === '(') {
                $j++;
                while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
                if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $rawMsgid = $tokens[$j][1];
                    $quote = $rawMsgid[0];
                    $msgid = substr($rawMsgid, 1, -1);
                    if ($quote === "'") {
                        $msgid = str_replace(["\\'", "\\\\"], ["'", "\\"], $msgid);
                    } else {
                        $msgid = stripcslashes($msgid);
                    }

                    $k = $j + 1;
                    while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
                    if ($k < $count && $tokens[$k] === ',') {
                        $k++;
                        while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
                        $context = null;
                        if (in_array($text, ['_x', 'esc_html_x', 'esc_attr_x'])) {
                            if ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                                $context = substr($tokens[$k][1], 1, -1);
                                $k++;
                                while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
                                if ($k < $count && $tokens[$k] === ',') {
                                    $k++;
                                    while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;
                                }
                            }
                        }

                        if ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                            $domain = substr($tokens[$k][1], 1, -1);
                            if ($domain === 'woo-get-data-for-ai') {
                                $key = ($context ? $context . "\x04" : '') . $msgid;
                                if (!isset($strings[$key])) {
                                    $strings[$key] = [
                                        'msgid' => $msgid,
                                        'context' => $context,
                                        'references' => [],
                                    ];
                                }
                                $ref = $relPath . ':' . $line;
                                if (!in_array($ref, $strings[$key]['references'])) {
                                    $strings[$key]['references'][] = $ref;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

echo "   Found " . count($strings) . " unique gettext strings in codebase.\n\n";

function po_escape(string $str): string {
    return addcslashes($str, "\\\"\n\r\t");
}

function format_po_string(string $prefix, string $str): string {
    if (strpos($str, "\n") !== false) {
        $lines = explode("\n", $str);
        $out = $prefix . " \"\"\n";
        foreach ($lines as $idx => $line) {
            $suffix = ($idx < count($lines) - 1) ? "\\n" : "";
            $out .= "\"" . po_escape($line) . $suffix . "\"\n";
        }
        return $out;
    }
    return $prefix . " \"" . po_escape($str) . "\"\n";
}

// 2. Generate woo-get-data-for-ai.pot
echo "[2/4] Generating master template (woo-get-data-for-ai.pot)...\n";
$potFile = $languagesDir . '/woo-get-data-for-ai.pot';
$potDate = gmdate('Y-m-d H:i+00:00');

$potContent = 'msgid ""' . "\n";
$potContent .= 'msgstr ""' . "\n";
$potContent .= '"Project-Id-Version: WP Agent Bridge (Data for AI)\n"' . "\n";
$potContent .= '"Report-Msgid-Bugs-To: https://github.com/SOYOO974/woo-get-data-for-ai\n"' . "\n";
$potContent .= '"POT-Creation-Date: ' . $potDate . '\n"' . "\n";
$potContent .= '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"' . "\n";
$potContent .= '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"' . "\n";
$potContent .= '"Language-Team: LANGUAGE <LL@li.org>\n"' . "\n";
$potContent .= '"Language: \n"' . "\n";
$potContent .= '"MIME-Version: 1.0\n"' . "\n";
$potContent .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$potContent .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$potContent .= '"Plural-Forms: nplurals=2; plural=(n > 1);\n"' . "\n";
$potContent .= '"X-Domain: woo-get-data-for-ai\n"' . "\n\n";

foreach ($strings as $item) {
    foreach ($item['references'] as $ref) {
        $potContent .= "#: " . $ref . "\n";
    }
    if (!empty($item['context'])) {
        $potContent .= 'msgctxt "' . po_escape($item['context']) . '"' . "\n";
    }
    $potContent .= format_po_string('msgid', $item['msgid']);
    $potContent .= 'msgstr ""' . "\n\n";
}

file_put_contents($potFile, $potContent);
echo "   Written " . strlen($potContent) . " bytes to woo-get-data-for-ai.pot\n\n";

// 3. Synchronize French PO file (woo-get-data-for-ai-fr_FR.po)
echo "[3/4] Synchronizing French translations (woo-get-data-for-ai-fr_FR.po)...\n";
$poFile = $languagesDir . '/woo-get-data-for-ai-fr_FR.po';
$existingTranslations = [];

if (file_exists($poFile)) {
    $poLines = file($poFile);
    $currentMsgid = null;
    $currentMsgctxt = null;
    $currentMsgstr = null;
    $inMsgstr = false;
    $inMsgid = false;

    foreach ($poLines as $line) {
        $line = trim($line);
        if (strpos($line, 'msgctxt ') === 0) {
            $currentMsgctxt = stripcslashes(substr($line, 9, -1));
        } elseif (strpos($line, 'msgid ') === 0) {
            $currentMsgid = stripcslashes(substr($line, 7, -1));
            $inMsgid = true;
            $inMsgstr = false;
        } elseif (strpos($line, 'msgstr ') === 0) {
            $currentMsgstr = stripcslashes(substr($line, 8, -1));
            $inMsgid = false;
            $inMsgstr = true;
        } elseif ($line === '' || strpos($line, '#') === 0) {
            if ($currentMsgid !== null && $currentMsgid !== '') {
                $key = ($currentMsgctxt ? $currentMsgctxt . "\x04" : '') . $currentMsgid;
                $existingTranslations[$key] = $currentMsgstr;
            }
            $currentMsgid = null;
            $currentMsgctxt = null;
            $currentMsgstr = null;
            $inMsgid = false;
            $inMsgstr = false;
        } elseif ($inMsgid && strpos($line, '"') === 0) {
            $currentMsgid .= stripcslashes(substr($line, 1, -1));
        } elseif ($inMsgstr && strpos($line, '"') === 0) {
            $currentMsgstr .= stripcslashes(substr($line, 1, -1));
        }
    }
    if ($currentMsgid !== null && $currentMsgid !== '') {
        $key = ($currentMsgctxt ? $currentMsgctxt . "\x04" : '') . $currentMsgid;
        $existingTranslations[$key] = $currentMsgstr;
    }
}

// Load extra dictionary if available
$dict = file_exists($dictFile) ? require $dictFile : [];
$allTranslations = array_merge($existingTranslations, $dict);

$poContent = 'msgid ""' . "\n";
$poContent .= 'msgstr ""' . "\n";
$poContent .= '"Project-Id-Version: WP Agent Bridge (Data for AI)\n"' . "\n";
$poContent .= '"Report-Msgid-Bugs-To: https://github.com/SOYOO974/woo-get-data-for-ai\n"' . "\n";
$poContent .= '"POT-Creation-Date: ' . $potDate . '\n"' . "\n";
$poContent .= '"PO-Revision-Date: ' . $potDate . '\n"' . "\n";
$poContent .= '"Last-Translator: SOYOO <julien@soyoo.re>\n"' . "\n";
$poContent .= '"Language-Team: Français <julien@soyoo.re>\n"' . "\n";
$poContent .= '"Language: fr_FR\n"' . "\n";
$poContent .= '"MIME-Version: 1.0\n"' . "\n";
$poContent .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$poContent .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$poContent .= '"Plural-Forms: nplurals=2; plural=(n > 1);\n"' . "\n";
$poContent .= '"X-Domain: woo-get-data-for-ai\n"' . "\n\n";

$translatedCount = 0;
$missingCount = 0;
$moEntries = [];

// Header entry for MO file
$moEntries[''] = "Project-Id-Version: WP Agent Bridge (Data for AI)\nPO-Revision-Date: {$potDate}\nLanguage: fr_FR\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nPlural-Forms: nplurals=2; plural=(n > 1);\n";

foreach ($strings as $key => $item) {
    foreach ($item['references'] as $ref) {
        $poContent .= "#: " . $ref . "\n";
    }
    if (!empty($item['context'])) {
        $poContent .= 'msgctxt "' . po_escape($item['context']) . '"' . "\n";
    }
    $poContent .= format_po_string('msgid', $item['msgid']);

    $translation = $allTranslations[$key] ?? ($allTranslations[$item['msgid']] ?? '');
    if (!empty($translation)) {
        $poContent .= format_po_string('msgstr', $translation) . "\n";
        $translatedCount++;
        $moEntries[$key] = $translation;
    } else {
        $poContent .= 'msgstr ""' . "\n\n";
        $missingCount++;
    }
}

file_put_contents($poFile, $poContent);
echo "   Written " . strlen($poContent) . " bytes to woo-get-data-for-ai-fr_FR.po\n";
echo "   Translations status: {$translatedCount}/" . count($strings) . " translated (" . round(($translatedCount / count($strings)) * 100, 1) . "%)\n";
if ($missingCount > 0) {
    echo "   ⚠️ Warning: {$missingCount} strings are still untranslated in French!\n\n";
} else {
    echo "   ✨ 100% of strings translated in French!\n\n";
}

// 4. Compile binary MO file (woo-get-data-for-ai-fr_FR.mo)
echo "[4/4] Compiling binary MO file (woo-get-data-for-ai-fr_FR.mo)...\n";

function compile_mo_data(array $entries): string {
    uksort($entries, 'strcmp');
    $count = count($entries);
    $headerSize = 28;
    $origTableOffset = $headerSize;
    $transTableOffset = $origTableOffset + ($count * 8);
    $stringsOffset = $transTableOffset + ($count * 8);

    $origTable = '';
    $transTable = '';
    $stringsData = '';
    $currentOffset = $stringsOffset;

    $origOffsets = [];
    $transOffsets = [];

    foreach ($entries as $orig => $trans) {
        $origLen = strlen($orig);
        $origOffsets[] = ['len' => $origLen, 'offset' => $currentOffset];
        $stringsData .= $orig . "\0";
        $currentOffset += $origLen + 1;
    }

    foreach ($entries as $orig => $trans) {
        $transLen = strlen($trans);
        $transOffsets[] = ['len' => $transLen, 'offset' => $currentOffset];
        $stringsData .= $trans . "\0";
        $currentOffset += $transLen + 1;
    }

    for ($i = 0; $i < $count; $i++) {
        $origTable .= pack('VV', $origOffsets[$i]['len'], $origOffsets[$i]['offset']);
        $transTable .= pack('VV', $transOffsets[$i]['len'], $transOffsets[$i]['offset']);
    }

    $header = pack('V7', 0x950412de, 0, $count, $origTableOffset, $transTableOffset, 0, 0);
    return $header . $origTable . $transTable . $stringsData;
}

$moFile = $languagesDir . '/woo-get-data-for-ai-fr_FR.mo';
$moData = compile_mo_data($moEntries);
file_put_contents($moFile, $moData);
echo "   Compiled " . strlen($moData) . " bytes (" . count($moEntries) . " entries) into woo-get-data-for-ai-fr_FR.mo\n\n";

echo "✅ i18n Synchronization Complete! WordPress & Loco Translate are 100% up to date.\n";
