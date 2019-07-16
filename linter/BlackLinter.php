<?php
final class BlackLinter extends ArcanistExternalLinter {
  public function getInfoName() {
    return 'black';
  }
  public function getInfoURI() {
    return 'https://github.com/ambv/black';
  }
  public function getInfoDescription() {
    return pht('Use black for processing specified files.');
  }
  public function getLinterName() {
    return 'black';
  }
  public function getLinterConfigurationName() {
    return 'black';
  }
  public function getDefaultBinary() {
    return 'black';
  }
  public function getInstallInstructions() {
    return pht('Make sure black is in directory specified by $PATH');
  }
  protected function getMandatoryFlags() {
    return array("--diff", "-S", "-t", "py35");
  }
  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if (empty($stdout) || substr($stdout, 0, 3) != '---') {
        return array();
    }
    $messages = array();
    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($stdout);
    foreach ($changes as $change) {
      foreach ($change->getHunks() as $hunk) {
        $repl = array();
        $orig = array();
        $lines = phutil_split_lines($hunk->getCorpus(), false);
        foreach ($lines as $line) {
          if (empty($line)) {
            continue;
          }
          $char = $line[0];
          $rest = substr($line, 1);
          switch ($char) {
            case '-':
              $orig[] = $rest;
              break;
            case '+':
              $repl[] = $rest;
              break;
            case '~':
              break;
            case ' ':
              $orig[] = $rest;
              $repl[] = $rest;
              break;
          }
        }
        $messages[] = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($hunk->getOldOffset())
          ->setChar(1)
          ->setCode($this->getLinterName())
          ->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX)
          ->setName('format')
          ->setOriginalText(implode("\n", $orig))
          ->setReplacementText(implode("\n", $repl))
          ->setBypassChangedLineFiltering(true);
      }
    }
    return $messages;
  }
  protected function parseLinterError($path, $stdout, $stderr, $err) {
    $matches = null;
    preg_match(
      '/error: cannot format -: Cannot parse: (?P<line>\d+):(?P<column>\d+): (?P<message>.*)/',
      $stderr,
      $matches);
    if ($matches) {
      $line = $matches['line'];
      $col = $matches['column'] + 1;
      $message = $matches['message'];
      $name = 'Cannot parse';
      $severity = ArcanistLintSeverity::SEVERITY_ERROR;
    } else {
      $line = 1;
      $col = 1;
      $message = $stderr;
      $name = sprintf('Command execution failed (%d)', $err);
      $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
    }
    return id(new ArcanistLintMessage())
      ->setPath($path)
      ->setLine($line)
      ->setChar($col)
      ->setCode($this->getLinterName())
      ->setSeverity($severity)
      ->setName($name)
      ->setDescription($message)
      ->setBypassChangedLineFiltering(true);
  }
}
