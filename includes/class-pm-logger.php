<?php


// -------------------------------
// Logger (writes to uploads/pet-match/pet-match.log)
// -------------------------------
if (!class_exists('PM_Logger')) {
  class PM_Logger {
    const OPTION_ENABLED = 'pm_log_enabled';
    const MAX_BYTES = 1048576; // 1MB
    const LEVELS = ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'];

    public static function enabled() : bool {
      $val = get_option(self::OPTION_ENABLED, '1');
      return $val === '1' || $val === 1 || $val === true;
    }

    public static function log(string $level, string $message, array $context = []) : void {
      if (!function_exists('wp_upload_dir')) return;
      if (!self::enabled()) return;

      try {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'pet-match';
        if (!is_dir($dir)) {
          wp_mkdir_p($dir);
        }
        $file = trailingslashit($dir) . 'pet-match.log';

        // rotate if too big
        if (file_exists($file) && filesize($file) > self::MAX_BYTES) {
          $rot = trailingslashit($dir) . 'pet-match-' . date('Ymd-His') . '.log';
          @rename($file, $rot);
        }

        $ts = date('Y-m-d H:i:s');
        $level = strtoupper(trim($level));
        if (!in_array($level, self::LEVELS, true)) {
          $level = 'INFO';
        }

        $context = self::normalize_context($context);
        $event = '';
        if (isset($context['event']) && is_string($context['event']) && $context['event'] !== '') {
          $event = '[' . $context['event'] . ']';
        }

        $ctx = '';
        if (!empty($context)) {
          $ctx = ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line = "[$ts][$level]$event $message$ctx\n";
        @file_put_contents($file, $line, FILE_APPEND);
      } catch (\Throwable $e) {
        // ignore
      }
    }

    private static function normalize_context(array $context, int $depth = 0) : array {
      if ($depth > 3) {
        return ['_truncated' => true];
      }

      $normalized = [];
      $count = 0;
      foreach ($context as $key => $value) {
        if ($count >= 25) {
          $normalized['_extra'] = 'truncated';
          break;
        }
        $count++;

        $safe_key = sanitize_key(is_string($key) ? $key : (string) $key);
        if ($safe_key === '') {
          $safe_key = 'item_' . $count;
        }
        $normalized[$safe_key] = self::normalize_value($value, $depth + 1);
      }

      ksort($normalized);
      return $normalized;
    }

    private static function normalize_value($value, int $depth) {
      if (is_array($value)) {
        return self::normalize_context($value, $depth);
      }
      if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
        return $value;
      }
      if (is_object($value)) {
        return '[object ' . get_class($value) . ']';
      }

      $value = trim((string) $value);
      if ($value === '') {
        return '';
      }
      if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 300);
      }
      return substr($value, 0, 300);
    }

    public static function path() : string {
      $upload = wp_upload_dir();
      return trailingslashit($upload['basedir']) . 'pet-match/pet-match.log';
    }

    public static function read_tail(int $max_lines = 400) : string {
      $file = self::path();
      if (!file_exists($file)) return '';
      $lines = @file($file, FILE_IGNORE_NEW_LINES);
      if (!$lines) return '';
      $slice = array_slice($lines, max(0, count($lines) - $max_lines));
      return implode("\n", $slice);
    }

    public static function clear() : void {
      $file = self::path();
      if (file_exists($file)) {
        @file_put_contents($file, '');
      }
    }
  }
}

// Capture fatals early
if (function_exists('register_shutdown_function')) {
  register_shutdown_function(function(){
    $err = error_get_last();
    if (!$err) return;
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (in_array($err['type'], $fatal_types, true)) {
      if (class_exists('PM_Logger')) {
        PM_Logger::log('FATAL', $err['message'], ['file'=>$err['file'], 'line'=>$err['line']]);
      }
    }
  });
}
