<?php

class CLIPrinter
{
    private array $colorsCodes = [
        // Reset
        'reset' => "\033[0m",

        // Regular colors
        'black' => "\033[0;30m",
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[0;33m",
        'blue' => "\033[0;34m",
        'magenta' => "\033[0;35m",
        'cyan' => "\033[0;36m",
        'white' => "\033[0;37m",

        // Bold/bright colors
        'bright_black' => "\033[1;30m",
        'bright_red' => "\033[1;31m",
        'bright_green' => "\033[1;32m",
        'bright_yellow' => "\033[1;33m",
        'bright_blue' => "\033[1;34m",
        'bright_magenta' => "\033[1;35m",
        'bright_cyan' => "\033[1;36m",
        'bright_white' => "\033[1;37m",

        // Background colors
        'bg_black' => "\033[40m",
        'bg_red' => "\033[41m",
        'bg_green' => "\033[42m",
        'bg_yellow' => "\033[43m",
        'bg_blue' => "\033[44m",
        'bg_magenta' => "\033[45m",
        'bg_cyan' => "\033[46m",
        'bg_white' => "\033[47m",

        // Bright background colors
        'bg_bright_black' => "\033[100m",
        'bg_bright_red' => "\033[101m",
        'bg_bright_green' => "\033[102m",
        'bg_bright_yellow' => "\033[103m",
        'bg_bright_blue' => "\033[104m",
        'bg_bright_magenta' => "\033[105m",
        'bg_bright_cyan' => "\033[106m",
        'bg_bright_white' => "\033[107m",

        // Styles
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'underline' => "\033[4m",
        'blink' => "\033[5m",
        'reverse' => "\033[7m",
        'hidden' => "\033[8m",
    ];

    private ?int $cachedTerminalWidth = null;

    // Print a single line with optional color
    public function printLine(string $text, ?string $color = null): void
    {
        $colorCode = $color && isset($this->colorsCodes[$color]) ? $this->colorsCodes[$color] : '';
        echo trim($colorCode) . trim($text) . $this->colorsCodes['reset'] . PHP_EOL;
    }

    // Print multiple lines (array) with optional color
    public function printLines(array $lines, ?string $color = null): void
    {
        foreach ($lines as $line) {
            $this->printLine($line, $color);
        }
    }

    // Print a table (array of rows) with optional column widths and colors
    public function printTable(array $rows, array $colors = []): void
    {
        // Calculate max column widths
        $colWidths = [];
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, strlen((string) $cell));
            }
        }

        // Print rows
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $i => $cell) {
                $color = $colors[$i] ?? null;
                $colorCode = $color && isset($this->colorsCodes[$color]) ? $this->colorsCodes[$color] : '';
                $padding = str_repeat('', $colWidths[$i] - strlen((string) $cell));
                echo $colorCode . $cell . $padding . $this->colorsCodes['reset'] . "  ";
            }
            echo PHP_EOL;
        }
    }

    /**
     * Ask a question to the user and return input
     *
     * @param string $question The question text
     * @param string|null $default Default value if user presses Enter
     * @param callable|null $validator Optional function to validate input. Return true if valid.
     * @return string User input
     */
    public function ask(string $question, ?string $default = null, ?callable $validator = null): string
    {
        while (true) {
            $prompt = $default ? "$question [$default]: " : "$question: ";
            echo $prompt;

            $input = trim(fgets(STDIN));

            if ($input === '' && $default !== null) {
                return $default;
            }

            if ($validator) {
                if ($validator($input)) {
                    return $input;
                } else {
                    $this->printLine("Invalid input, please try again.", "red");
                    continue;
                }
            }

            return $input;
        }
    }

    /**
     * Ask the user to choose from a list of options
     *
     * @param string $question The question to display
     * @param array $options Array of options
     * @param int|null $defaultIndex Default selected index (0-based)
     * @return string The chosen option
     */
    public function askChoice(string $question, array $options, ?int $defaultIndex = null): string
    {
        if (empty($options)) {
            throw new InvalidArgumentException("Options array cannot be empty.");
        }

        while (true) {
            // Print question
            echo $question . PHP_EOL;

            // Print options with numbers
            foreach ($options as $index => $option) {
                $num = $index + 1;
                $defaultMark = ($defaultIndex !== null && $index === $defaultIndex) ? '*' : ' ';
                echo "  [$num] $option $defaultMark" . PHP_EOL;
            }

            // Prompt
            $prompt = "Enter choice number";
            if ($defaultIndex !== null) {
                $prompt .= " [" . ($defaultIndex + 1) . "]";
            }
            $prompt .= ": ";

            echo $prompt;
            $input = trim(fgets(STDIN));

            // Use default if input is empty
            if ($input === '' && $defaultIndex !== null) {
                return $options[$defaultIndex];
            }

            // Validate number
            if (is_numeric($input)) {
                $choice = (int) $input - 1;
                if (isset($options[$choice])) {
                    return $options[$choice];
                }
            }

            $this->printLine("Invalid choice, please try again.", "red");
        }
    }


    /**
     * Print any PHP data structure as a clean tree.
     *
     * Example:
     *
     * ├── format
     * │   ├── filename: song.mp3
     * │   └── tags
     * │       ├── title: My Song
     * │       └── artist: John Doe
     * └── streams
     *     └── [0]
     *         ├── codec_name: mp3
     *         └── channels: 2
     */
    public function printData(
        mixed $data,
        ?string $title = null
    ): void {
        if ($title !== null) {
            echo PHP_EOL;
            $this->printLine($title, 'bright_blue');
            echo PHP_EOL;
        }

        /*
         * Root scalar.
         */
        if (!is_array($data) && !is_object($data)) {
            $this->printTreeValue('', $data, '', true);
            return;
        }

        /*
         * Convert objects to arrays.
         */
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        $items = array_keys($data);
        $count = count($items);

        foreach ($items as $position => $key) {
            $isLast = $position === $count - 1;

            $this->printTreeNode(
                (string) $key,
                $data[$key],
                '',
                $isLast
            );
        }
    }


    /**
     * Recursively print one tree node.
     */
    private function printTreeNode(
        string $key,
        mixed $value,
        string $prefix,
        bool $isLast
    ): void {
        /*
         * The connector for this node.
         */
        $connector = $isLast
            ? '└── '
            : '├── ';

        /*
         * Format array keys.
         */
        $displayKey = $this->formatTreeKey($key);

        /*
         * Nested array/object.
         */
        if (is_array($value) || is_object($value)) {

            echo $prefix;

            echo $this->colorsCodes['bright_cyan'];
            echo $connector;
            echo $displayKey;
            echo $this->colorsCodes['reset'];

            echo PHP_EOL;

            /*
             * Convert object to array.
             */
            if (is_object($value)) {
                $value = get_object_vars($value);
            }

            /*
             * Empty array.
             */
            if (empty($value)) {
                $childPrefix = $prefix . ($isLast ? '    ' : '│   ');

                echo $childPrefix;
                echo $this->colorsCodes['dim'];
                echo '(empty)';
                echo $this->colorsCodes['reset'];
                echo PHP_EOL;

                return;
            }

            /*
             * Print children.
             */
            $childPrefix = $prefix . (
                $isLast
                ? '    '
                : '│   '
            );

            $keys = array_keys($value);
            $count = count($keys);

            foreach ($keys as $position => $childKey) {
                $childIsLast = $position === $count - 1;

                $this->printTreeNode(
                    (string) $childKey,
                    $value[$childKey],
                    $childPrefix,
                    $childIsLast
                );
            }

            return;
        }

        /*
         * Scalar value.
         */
        echo $prefix;

        echo $connector;

        echo $this->colorsCodes['bright_white'];
        echo $displayKey;
        echo $this->colorsCodes['reset'];

        echo ': ';

        echo $this->getValueColor($value);
        echo $this->formatValue($value);
        echo $this->colorsCodes['reset'];

        echo PHP_EOL;
    }


    /**
     * Print a scalar without a key.
     */
    private function printTreeValue(
        string $key,
        mixed $value,
        string $prefix,
        bool $isLast
    ): void {
        echo $prefix;

        echo $this->getValueColor($value);
        echo $this->formatValue($value);
        echo $this->colorsCodes['reset'];

        echo PHP_EOL;
    }


    /**
     * Format a tree key.
     *
     * Numeric keys are displayed as [0], [1], etc.
     *
     * String keys are converted to a readable format.
     */
    private function formatTreeKey(string $key): string
    {
        if (is_numeric($key)) {
            return '[' . $key . ']';
        }

        $key = str_replace(
            ['_', '-'],
            ' ',
            $key
        );

        return ucwords($key);
    }


    /**
     * Format values.
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return $value === ''
                ? '(empty)'
                : $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        if (is_resource($value)) {
            return 'Resource';
        }

        return (string) $value;
    }


    /**
     * Get value color based on type.
     */
    private function getValueColor(mixed $value): string
    {
        if ($value === null) {
            return $this->colorsCodes['dim'];
        }

        if (is_bool($value)) {
            return $value
                ? $this->colorsCodes['green']
                : $this->colorsCodes['red'];
        }

        if (is_int($value) || is_float($value)) {
            return $this->colorsCodes['yellow'];
        }

        return $this->colorsCodes['white'];
    }

    /**
     * Detect the terminal width in columns.
     * Falls back to $default when it can't be detected (piped output, no TTY, etc.)
     */
    public function getTerminalWidth(int $default = 80, bool $refresh = false): int
    {
        if ($this->cachedTerminalWidth !== null && !$refresh) {
            return $this->cachedTerminalWidth;
        }

        $width = null;

        // 1) COLUMNS env var (set by some shells, often not exported)
        $env = getenv('COLUMNS');
        if ($env !== false && (int) $env > 0) {
            $width = (int) $env;
        }

        // 2) Windows
        if ($width === null && DIRECTORY_SEPARATOR === '\\') {
            $out = @shell_exec('mode con 2>NUL');
            if ($out && preg_match('/Columns:\s+(\d+)/i', $out, $m)) {
                $width = (int) $m[1];
            }
        }

        // 3) Linux / macOS: stty, then tput
        if ($width === null && DIRECTORY_SEPARATOR === '/') {
            $out = @shell_exec('stty size 2>/dev/null');          // "rows cols"
            if ($out && preg_match('/^\d+\s+(\d+)/', trim($out), $m)) {
                $width = (int) $m[1];
            } else {
                $out = @shell_exec('tput cols 2>/dev/null');
                if ($out && (int) $out > 0) {
                    $width = (int) $out;
                }
            }
        }

        return $this->cachedTerminalWidth = ($width !== null && $width > 0) ? $width : $default;
    }

    /**
     * Draw a progress bar that redraws itself on the same line.
     *
     * @param float    $value Current value (between $min and $max)
     * @param float    $min   Value that represents 0%
     * @param float    $max   Value that represents 100%
     * @param int|null $width Bar width in characters (null = auto from terminal width)
     * @param string   $label Optional text shown after the percentage
     */
    public function printProgress(
        float $value,
        float $min = 0,
        float $max = 100,
        ?int $width = null,
        string $label = ''
    ): void {
        $range = $max - $min;
        $ratio = $range > 0 ? ($value - $min) / $range : 1;
        $ratio = max(0, min(1, $ratio));              // clamp to 0..1

        $percent = (int) round($ratio * 100);

        $suffix = sprintf(' %3d%%', $percent) . ($label !== '' ? ' ' . $label : '');

        if ($width === null) {
            // terminal width - suffix - 1 spare column (avoids line wrapping)
            $width = $this->getTerminalWidth() - mb_strlen($suffix) - 1;
        }
        $width = max(10, $width);                     // never let the bar get too small

        $filled = (int) round($ratio * $width);
        $empty  = $width - $filled;

        $fillColor = $percent >= 100
            ? $this->colorsCodes['bright_green']
            : $this->colorsCodes['bright_yellow'];

        $bar = $fillColor . str_repeat('█', $filled)
             . $this->colorsCodes['white'] . str_repeat('░', $empty)
             . $this->colorsCodes['reset'];

        // \r returns to line start, \033[K clears leftover characters
        echo "\r\033[K" . $bar . $suffix;

        if ($percent >= 100) {
            echo PHP_EOL;                             // finish the line
        }
    }

    /**
     * Loop over any array and show progress automatically.
     *
     * @param array    $items    Anything iterable as an array
     * @param callable $callback fn($item, $key) called for each element
     * @param int|null $width    Bar width (null = auto from terminal width)
     */
    public function eachWithProgress(array $items, callable $callback, ?int $width = null): void
    {
        $total = count($items);

        if ($total === 0) {
            return;
        }

        if ($width === null) {
            $longestLabel = "($total/$total)";
            // " 100%" (5) + space (1) + label + 1 spare column
            $width = $this->getTerminalWidth() - 5 - 1 - strlen($longestLabel) - 1;
        }

        $this->printProgress(0, 0, $total, $width);

        $done = 0;
        foreach ($items as $key => $item) {
            $callback($item, $key);
            $done++;
            $this->printProgress($done, 0, $total, $width, "($done/$total)");
        }
    }

}
