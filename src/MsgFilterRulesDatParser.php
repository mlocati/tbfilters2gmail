<?php

declare(strict_types=1);

namespace TBF2GM;

use RuntimeException;
use TBF2GM\Filter\Type;

class MsgFilterRulesDatParser
{
    private string $contents;

    private string $filename;

    /**
     * @var string[]
     */
    private ?array $lines = null;

    /**
     * @var array[]
     */
    private ?array $tokens = null;

    protected function __construct(string $contents, string $filename = '')
    {
        $this->contents = $contents;
        $this->filename = $filename;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Read a msgFilterRules.dat file.
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public static function fromFile(string $filename): self
    {
        if (!is_file($filename)) {
            throw new RuntimeException("Unable to find the file {$filename}");
        }
        set_error_handler(static function(): void {}, -1);
        $contents = file_get_contents($filename);
        restore_error_handler();
        if (!is_string($contents)) {
            throw new RuntimeException("Failed to read The file {$filename}");
        }

        return new static($contents, $filename);
    }

    /**
     * Use the contents of a msgFilterRules.dat file.
     *
     * @return static
     */
    public static function fromContents(string $contents): self
    {
        return new static($contents);
    }

    /**
     * @throws RuntimeException
     *
     * @see https://searchfox.org/comm-central/source/mailnews/search/src/nsMsgFilterList.cpp see the nsMsgFilterList::LoadTextFilters method
     *
     * @return \TBF2GM\FilterList|null returns NULL if the file is empty
     */
    public function parse(): ?FilterList
    {
        $tokens = $this->getTokens();
        $numTokens = count($tokens);
        if ($numTokens === 0) {
            return null;
        }
        $result = null;
        $currentFilter = null;
        for ($tokenIndex = 0; $tokenIndex < $numTokens; $tokenIndex++) {
            $token = $tokens[$tokenIndex];
            switch ($token['key']) {
                case 'version':
                    if ($result !== null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    switch ($token['value']) {
                        case '9':
                            break;
                        default:
                            throw new RuntimeException("Unsupported format version ({$token['value']}) detected");
                    }
                    $result = new FilterList($token['key']);
                    break;
                case 'logging':
                    if ($result === null || $result->isLogging() !== null || $currentFilter !== null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    switch ($token['value']) {
                        case 'yes':
                            $result->setLogging(true);
                            break;
                        case 'no':
                            $result->setLogging(false);
                            break;
                        default:
                            throw new RuntimeException("Unsupported value for the key '{$token['key']}' found at line {$token['line']}: '{$token['value']}'");
                    }
                    break;
                case 'name':
                    if ($result === null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    $currentFilter = new Filter($token['value']);
                    $result[] = $currentFilter;
                    break;
                case 'enabled':
                    if ($currentFilter === null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    switch ($token['value']) {
                        case 'yes':
                            $currentFilter->setEnabled(true);
                            break;
                        case 'no':
                            $currentFilter->setEnabled(false);
                            break;
                        default:
                            throw new RuntimeException("Unsupported value for the key '{$token['key']}' found at line {$token['line']}: '{$token['value']}'");
                    }
                    break;
                case 'type':
                    if ($currentFilter === null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    if (!preg_match('/^\d+$/', $token['value'])) {
                        throw new RuntimeException("Unsupported value for the key '{$token['key']}' found at line {$token['line']}: '{$token['value']}'");
                    }
                    $type = (int) $token['value'];
                    $typeNames = Type::getNames($type);
                    if ($typeNames === null) {
                        throw new RuntimeException("Unsupported value for the key '{$token['key']}' found at line {$token['line']}: '{$token['value']}'");
                    }
                    $currentFilter->setType($type);
                    break;
                case 'action':
                    if ($currentFilter === null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    $nextToken = $tokens[$tokenIndex + 1] ?? null;
                    if ($nextToken !== null && $nextToken['key'] === 'actionValue') {
                        $actionValue = $nextToken['value'];
                        $tokenIndex++;
                    } else {
                        $actionValue = null;
                    }
                    $currentFilter->addAction($this->parseAction($token['value'], $actionValue));
                    break;
                case 'condition':
                    if ($currentFilter === null) {
                        throw new RuntimeException("Unexpected '{$token['key']}' key at line {$token['line']}");
                    }
                    $currentFilter->addConditionsList(Filter\Condition::parse($token['value']));
                    break;
                default:
                    throw new RuntimeException("Unsupported '{$token['key']}' key at line {$token['line']}");
            }
        }

        return $result;
    }

    protected function getContents(): string
    {
        return $this->contents;
    }

    /**
     * @return string[]
     */
    protected function getLines(): array
    {
        if ($this->lines === null) {
            $lines = [];
            foreach (explode("\r\n", $this->getContents()) as $chunk1) {
                foreach (explode("\n", $chunk1) as $chunk2) {
                    $lines = array_merge($lines, explode("\r", $chunk2));
                }
            }
            $this->lines = $lines;
        }

        return $this->lines;
    }

    /**
     * @throws RuntimeException
     *
     * @return array[]
     */
    protected function getTokens(): array
    {
        if ($this->tokens === null) {
            $tokens = [];
            foreach ($this->getLines() as $lineIndex => $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine === '') {
                    continue;
                }
                $m = null;
                $userLineIndex = $lineIndex + 1;
                if (!preg_match('/^(?P<key>.*?)\s*=\s*"(?P<value>.*)"$/', $trimmedLine, $m)) {
                    throw new RuntimeException("Unable to recognize line {$userLineIndex}:\n{$line}");
                }
                $tokens[] = [
                    'line' => $userLineIndex,
                    'key' => $m['key'],
                    'value' => $this->valueToString($m['value']),
                ];
            }
            $this->tokens = $tokens;
        }

        return $this->tokens;
    }

    /**
     * @see https://searchfox.org/comm-central/source/mailnews/search/src/nsMsgFilterList.cpp see the nsMsgFilterList::LoadValue method
     *
     * @return string
     */
    protected function valueToString(string $value)
    {
        $result = '';
        $len = strlen($value);
        for ($index = 0; $index < $len; $index++) {
            $curChar = $value[$index];
            if ($curChar === '\\') {
                $nextChar = $value[++$index] ?? '';
                if ($nextChar === '"' || $nextChar === '\\') {
                    $curChar = $nextChar;
                }
            }
            $result .= $curChar;
        }

        return $result;
    }

    protected function parseAction(string $action, ?string $actionValue): Filter\Action
    {
        $className = $this->camelize($action);
        if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $className)) {
            $fqClassName = "TBF2GM\\Filter\\Action\\{$className}";
            if (!class_exists($fqClassName, true) || !is_a($fqClassName, Filter\Action::class, true)) {
                $fqClassName = '';
            }
        } else {
            $fqClassName = '';
        }
        if ($fqClassName === '') {
            throw new RuntimeException("Unrecognized action: {$action}");
        }

        return $fqClassName::create($actionValue);
    }

    protected function camelize(string $value): string
    {
        $words = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(
            static function (string $word): string {
                return ucfirst(strtolower($word));
            },
            $words
        );

        return implode('', $words);
    }
}
