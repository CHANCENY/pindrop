<?php

namespace Simp\Pindrop\Modules\languages\src\Services;

use Simp\Translate\lang\LanguageManager;
use Simp\Translate\translate\Translate;

class LanguageSupport
{
    public array $languages = [];

    protected string $language = 'en-US';

    public function __construct()
    {
        $supportedLanguages = new LanguageManager();
        $this->languages = $supportedLanguages->getLanguages();
        ksort($this->languages);

        if (isset($_SESSION['session_lang'])) {
            $this->language = $_SESSION['session_lang'];
        }
    }

    public function getDefaultLanguage(): string
    {
        return $this->language;
    }

    public function setDefaultLanguage(string $language): LanguageSupport
    {
        if (isset($this->languages[$language])) {
            $_SESSION['session_lang'] = $language;
        }
        return $this;
    }

    public function getLanguage(string $language = 'en'): string
    {
        return $this->languages[$language] ?? "";
    }

    public function translate(string $html, string $from, string $to, int $maxLength = 4999): string
    {
        if (!class_exists(Translate::class)) {
            return $html;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);

        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        $maxLength = 3000; // max per request
        $translated = '';

        // Split string into chunks of <= 5000 characters
        $chunks = str_split($innerHTML, $maxLength);
        foreach ($chunks as $chunk) {
            //dd($chunk, $from, $to, $maxLength);
            $translate = Translate::translate($chunk, from: $from, to: $to);
            $translate->process();
            $translated .= $translate->getTranslatedText();
        }

        return $translated;
    }

}