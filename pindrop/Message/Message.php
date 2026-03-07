<?php

namespace Simp\Pindrop\Message;

class Message
{
    public static function info(string $message, array $options = [])
    {
        $_SESSION['messages'][] = [
            'text' => $message,
            'type' => "info",
            'options' => $options
        ];
    }

    public static function warn(string $message, array $options = [])
    {
        $_SESSION['messages'][] = [
            'text' => $message,
            'type' => "warning",
            'options' => $options
        ];
    }

    public static function error(string $message, array $options = []) {
        $_SESSION['messages'][] = [
            'text' => $message,
            'type' => "error",
            'options' => $options
        ];
    }

    public static function debug(string $message, array $options = [])
    {
        $_SESSION['messages'][] = [
            'text' => $message,
            'type' => "debug",
            'options' => $options
        ];
    }

    public static function send(): array
    {
        $list = array();
        if (!isset($_SESSION['messages'])) return $list;
        foreach ($_SESSION['messages'] as $message) {
           $text = $message['text'];
            foreach ($message['options'] as $key=>$option) {
                $text = str_replace($key,$option,$text);
            }
            $list[] = [
                'text' => $text,
                'type' => $message['type'],
            ];
        }
        unset($_SESSION['messages']);
        return $list;
    }
}