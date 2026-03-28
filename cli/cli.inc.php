<?php

use Simp\Pindrop\Services\ServiceProvider;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . "/colors.php";

$serviceProvider = new ServiceProvider();
$container = $serviceProvider->buildContainer();

$container->get('plugin.services.register');

function getAppContainer(): \DI\Container
{
    global $container;
    return $container;
}

$factory = $container->get('content.factory');


// Allow CLI only
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from the command line.');
}

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
                $colWidths[$i] = max($colWidths[$i] ?? 0, strlen((string)$cell));
            }
        }

        // Print rows
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $i => $cell) {
                $color = $colors[$i] ?? null;
                $colorCode = $color && isset($this->colorsCodes[$color]) ? $this->colorsCodes[$color] : '';
                $padding = str_repeat('', $colWidths[$i] - strlen((string)$cell));
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
                $choice = (int)$input - 1;
                if (isset($options[$choice])) {
                    return $options[$choice];
                }
            }

            $this->printLine("Invalid choice, please try again.", "red");
        }
    }
}
